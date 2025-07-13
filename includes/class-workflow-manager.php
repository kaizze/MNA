<?php
/**
 * Workflow management and automation
 */

if (!defined('ABSPATH')) {
    exit;
}

class MNA_Workflow_Manager {
    
    public function __construct() {
        add_action('mna_process_headlines', array($this, 'batch_process_headlines'));
    }
    
    /**
     * Handle cron job for batch processing headlines
     */
    public static function batch_process_headlines() {
        if (!get_option('mna_auto_process', false)) {
            return; // Auto-processing is disabled
        }
        
        $processor = new MNA_Headline_Processor();
        $result = $processor->process_batch_headlines();
        
        // Log the batch processing result
        MNA_Database::log_activity(
            null,
            'batch_process',
            $result['success'] ? 'completed' : 'failed',
            $result['message']
        );
    }
    
    /**
     * Handle article review actions (approve, reject, publish)
     */
    public function handle_article_review($article_id, $action, $notes = '', $journalist_id = null) {
        global $wpdb;
        
        $articles_table = $wpdb->prefix . 'mna_articles';
        
        // Get current article data
        $article = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $articles_table WHERE id = %d",
            $article_id
        ));
        
        if (!$article) {
            return array(
                'success' => false,
                'message' => 'Article not found'
            );
        }
        
        $journalist_id = $journalist_id ?: get_current_user_id();
        
        switch ($action) {
            case 'approve':
                return $this->approve_article($article, $journalist_id, $notes);
            
            case 'reject':
                return $this->reject_article($article, $journalist_id, $notes);
            
            case 'publish':
                return $this->publish_article($article, $journalist_id, $notes);
            
            case 'request_changes':
                return $this->request_article_changes($article, $journalist_id, $notes);
            
            default:
                return array(
                    'success' => false,
                    'message' => 'Invalid action'
                );
        }
    }
    
    /**
     * Approve article for publication
     */
    private function approve_article($article, $journalist_id, $notes) {
        global $wpdb;
        
        $articles_table = $wpdb->prefix . 'mna_articles';
        
        // Get headline and research data for creating WordPress draft
        $headlines_table = $wpdb->prefix . 'mna_headlines';
        $research_table = $wpdb->prefix . 'mna_research';
        
        $headline_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $headlines_table WHERE id = %d",
            $article->headline_id
        ));
        
        $research_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $research_table WHERE id = %d",
            $article->research_id
        ));
        
        if (!$headline_data || !$research_data) {
            return array(
                'success' => false,
                'message' => 'Missing headline or research data'
            );
        }
        
        // Create WordPress draft post
        $post_data = array(
            'post_title' => $this->extract_title_from_content($article->generated_content) ?: $headline_data->headline,
            'post_content' => $this->prepare_content_for_publication($article->generated_content, $research_data),
            'post_status' => 'draft', // Create as draft, not published
            'post_type' => 'post',
            'post_author' => $journalist_id,
            'post_category' => $this->get_post_categories($headline_data->category),
            'meta_input' => array(
                'mna_original_headline' => $headline_data->headline,
                'mna_research_sources' => $research_data->sources_json,
                'mna_generated_by' => $article->llm_used,
                'mna_quality_score' => $article->content_quality_score,
                'mna_journalist_notes' => $notes,
                'mna_processed_date' => current_time('mysql'),
                'mna_article_id' => $article->id
            )
        );
        
        $post_id = wp_insert_post($post_data);
        
        if (is_wp_error($post_id)) {
            return array(
                'success' => false,
                'message' => 'Failed to create WordPress draft: ' . $post_id->get_error_message()
            );
        }
        
        // Add images to the post
        $this->add_images_to_post($post_id, $headline_data, $article);
        
        // Update article record
        $updated = $wpdb->update(
            $articles_table,
            array(
                'status' => 'approved',
                'wordpress_post_id' => $post_id,
                'journalist_id' => $journalist_id,
                'journalist_notes' => $notes,
                'reviewed_at' => current_time('mysql')
            ),
            array('id' => $article->id),
            array('%s', '%d', '%d', '%s', '%s'),
            array('%d')
        );
        
        // Update headline status
        MNA_Database::update_headline_status($article->headline_id, 'approved', 'Article approved and saved as draft');
        
        // Log the approval
        MNA_Database::log_activity(
            $article->headline_id,
            'approve',
            'completed',
            'Article approved and saved as WordPress draft (ID: ' . $post_id . ')'
        );
        
        if ($updated) {
            return array(
                'success' => true,
                'message' => 'Article approved and saved as WordPress draft',
                'post_id' => $post_id,
                'edit_url' => admin_url('post.php?post=' . $post_id . '&action=edit')
            );
        } else {
            return array(
                'success' => false,
                'message' => 'Failed to update article status'
            );
        }
    }
    
    /**
     * Reject article
     */
    private function reject_article($article, $journalist_id, $notes) {
        global $wpdb;
        
        $articles_table = $wpdb->prefix . 'mna_articles';
        
        $updated = $wpdb->update(
            $articles_table,
            array(
                'status' => 'rejected',
                'journalist_id' => $journalist_id,
                'journalist_notes' => $notes,
                'reviewed_at' => current_time('mysql')
            ),
            array('id' => $article->id),
            array('%s', '%d', '%s', '%s'),
            array('%d')
        );
        
        if ($updated) {
            // Also update the original headline status
            MNA_Database::update_headline_status(
                $article->headline_id, 
                'failed', 
                'Article rejected: ' . $notes
            );
            
            return array(
                'success' => true,
                'message' => 'Article rejected'
            );
        } else {
            return array(
                'success' => false,
                'message' => 'Failed to reject article'
            );
        }
    }
    
    /**
     * Publish article to WordPress
     */
    private function publish_article($article, $journalist_id, $notes) {
        global $wpdb;
        
        // Get headline and research data
        $headlines_table = $wpdb->prefix . 'mna_headlines';
        $research_table = $wpdb->prefix . 'mna_research';
        $articles_table = $wpdb->prefix . 'mna_articles';
        
        $headline_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $headlines_table WHERE id = %d",
            $article->headline_id
        ));
        
        $research_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $research_table WHERE id = %d",
            $article->research_id
        ));
        
        if (!$headline_data || !$research_data) {
            return array(
                'success' => false,
                'message' => 'Missing headline or research data'
            );
        }
        
        // Create WordPress post
        $post_data = array(
            'post_title' => $this->extract_title_from_content($article->generated_content) ?: $headline_data->headline,
            'post_content' => $this->prepare_content_for_publication($article->generated_content, $research_data),
            'post_status' => 'publish',
            'post_type' => 'post',
            'post_author' => $journalist_id,
            'post_category' => $this->get_post_categories($headline_data->category),
            'meta_input' => array(
                'mna_original_headline' => $headline_data->headline,
                'mna_research_sources' => $research_data->sources_json,
                'mna_generated_by' => $article->llm_used,
                'mna_quality_score' => $article->content_quality_score,
                'mna_journalist_notes' => $notes,
                'mna_processed_date' => current_time('mysql')
            )
        );
        
        $post_id = wp_insert_post($post_data);
        
        if (is_wp_error($post_id)) {
            return array(
                'success' => false,
                'message' => 'Failed to create WordPress post: ' . $post_id->get_error_message()
            );
        }
        
        // Update article record
        $updated = $wpdb->update(
            $articles_table,
            array(
                'status' => 'published',
                'wordpress_post_id' => $post_id,
                'journalist_id' => $journalist_id,
                'journalist_notes' => $notes,
                'reviewed_at' => current_time('mysql'),
                'published_at' => current_time('mysql')
            ),
            array('id' => $article->id),
            array('%s', '%d', '%d', '%s', '%s', '%s'),
            array('%d')
        );
        
        // Update headline status
        MNA_Database::update_headline_status($article->headline_id, 'published');
        
        // Log the publication
        MNA_Database::log_activity(
            $article->headline_id,
            'publish',
            'completed',
            'Article published as WordPress post ID: ' . $post_id
        );
        
        return array(
            'success' => true,
            'message' => 'Article published successfully',
            'post_id' => $post_id,
            'post_url' => get_permalink($post_id)
        );
    }
    
    /**
     * Request changes to article
     */
    private function request_article_changes($article, $journalist_id, $notes) {
        global $wpdb;
        
        $articles_table = $wpdb->prefix . 'mna_articles';
        
        $updated = $wpdb->update(
            $articles_table,
            array(
                'status' => 'under_review',
                'journalist_id' => $journalist_id,
                'journalist_notes' => $notes,
                'reviewed_at' => current_time('mysql')
            ),
            array('id' => $article->id),
            array('%s', '%d', '%s', '%s'),
            array('%d')
        );
        
        if ($updated) {
            return array(
                'success' => true,
                'message' => 'Changes requested. Article marked for revision.'
            );
        } else {
            return array(
                'success' => false,
                'message' => 'Failed to request changes'
            );
        }
    }
    
    /**
     * Extract title from generated content
     */
    private function extract_title_from_content($content) {
        $lines = explode("\n", $content);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip empty lines
            if (empty($line)) {
                continue;
            }
            
            // Check for **HEADLINE:** pattern
            if (preg_match('/^\*\*HEADLINE:\*\*\s*(.+)$/i', $line, $matches)) {
                return trim($matches[1]);
            }
            
            // Check for **Title** pattern
            if (preg_match('/^\*\*([^*]+)\*\*$/', $line, $matches)) {
                $title = trim($matches[1]);
                if (strlen($title) > 20 && strlen($title) < 150) {
                    return $title;
                }
            }
            
            // Check for markdown headers
            if (preg_match('/^#+\s*(.+)$/', $line, $matches)) {
                return trim($matches[1]);
            }
            
            // Check if it's the first substantial line (likely title)
            if (strlen($line) > 20 && strlen($line) < 150) {
                // Make sure it's not a regular sentence (doesn't end with period)
                if (!preg_match('/\.\s*$/', $line)) {
                    return $line;
                }
            }
            
            // If we've reached content that's clearly not a title, stop
            if (strlen($line) > 150 || strpos($line, '. ') !== false) {
                break;
            }
        }
        
        return null;
    }
    
    /**
     * Prepare content for WordPress publication
     */
    private function prepare_content_for_publication($content, $research_data) {
        // Step 1: Clean and structure the content
        $content = $this->clean_content_structure($content);
        
        // Step 2: Convert markdown-style formatting to HTML
        $content = $this->convert_markdown_to_html($content);
        
        // Step 3: Convert citations to numbered links
        $content = $this->convert_citations_to_links($content, $research_data);
        
        // Step 4: Add proper paragraph breaks
        $content = wpautop($content);
        
        // Step 5: Add source attribution at the end
        $content .= $this->generate_source_attribution($research_data);
        
        return $content;
    }
    
    /**
     * Clean and structure content by removing title and organizing sections
     */
    private function clean_content_structure($content) {
        $lines = explode("\n", $content);
        $processed_lines = array();
        $title_removed = false;
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip empty lines at the beginning
            if (empty($line) && empty($processed_lines)) {
                continue;
            }
            
            // Remove first title-like line (it becomes the post title)
            if (!$title_removed && $this->is_title_line($line)) {
                $title_removed = true;
                continue;
            }
            
            // Skip "Medical Disclaimer" section (we'll add our own)
            if (stripos($line, 'medical disclaimer') !== false) {
                break;
            }
            
            $processed_lines[] = $line;
        }
        
        return implode("\n", $processed_lines);
    }
    
    /**
     * Check if a line looks like a title
     */
    private function is_title_line($line) {
        // Check for markdown headers
        if (strpos($line, '#') === 0) {
            return true;
        }
        
        // Check for **HEADLINE:** pattern
        if (preg_match('/^\*\*[A-Z][^*]+:\*\*/', $line)) {
            return true;
        }
        
        // Check if it's short, at start, and looks like a title
        if (strlen($line) < 150 && !strpos($line, '.')) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Convert markdown-style formatting to HTML
     */
    private function convert_markdown_to_html($content) {
        // Convert **bold text** to proper headings or strong tags
        $content = preg_replace_callback(
            '/\*\*([^*]+)\*\*/i',
            function($matches) {
                $text = $matches[1];
                
                // If it's a section header (contains keywords), make it an H3
                $section_keywords = array('understanding', 'implications', 'conclusion', 'background', 'study', 'research', 'findings', 'results');
                $is_section = false;
                
                foreach ($section_keywords as $keyword) {
                    if (stripos($text, $keyword) !== false) {
                        $is_section = true;
                        break;
                    }
                }
                
                if ($is_section || strlen($text) > 20) {
                    return '<h3>' . $text . '</h3>';
                } else {
                    return '<strong>' . $text . '</strong>';
                }
            },
            $content
        );
        
        // Convert ### headers to H3
        $content = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $content);
        
        // Convert ## headers to H2  
        $content = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $content);
        
        // Convert # headers to H2 (avoid H1 as that's the post title)
        $content = preg_replace('/^# (.+)$/m', '<h2>$1</h2>', $content);
        
        return $content;
    }
    
    /**
     * Convert [Source: URL] citations to numbered HTML links
     */
    private function convert_citations_to_links($content, $research_data) {
        $sources = json_decode($research_data->sources_json, true);
        
        if (!$sources || !is_array($sources)) {
            return $content;
        }
        
        // Filter out sources without URLs
        $valid_sources = array_filter($sources, function($source) {
            return !empty($source['url']);
        });
        
        if (empty($valid_sources)) {
            return $content;
        }
        
        // Re-index valid sources
        $valid_sources = array_values($valid_sources);
        
        // Create a map of URLs to citation numbers
        $url_map = array();
        foreach ($valid_sources as $index => $source) {
            $url_map[$source['url']] = $index + 1;
        }
        
        // Replace [Source: URL] with numbered links
        $content = preg_replace_callback(
            '/\[Source:\s*(https?:\/\/[^\]]+)\]/i',
            function ($matches) use ($url_map) {
                $url = trim($matches[1]);
                
                if (isset($url_map[$url])) {
                    $citation_number = $url_map[$url];
                    return '<sup><a href="' . esc_url($url) . '" target="_blank" rel="noopener nofollow">[' . $citation_number . ']</a></sup>';
                } else {
                    // If URL not in our sources list, still make it a link
                    return '<sup><a href="' . esc_url($url) . '" target="_blank" rel="noopener nofollow">[Source]</a></sup>';
                }
            },
            $content
        );
        
        // Also look for and replace any standalone URLs that should be citations
        foreach ($valid_sources as $index => $source) {
            $url = $source['url'];
            $citation_number = $index + 1;
            
            // Replace standalone URLs in text (not already in links)
            $content = preg_replace(
                '/(?<!\[Source:\s)(?<!")\b' . preg_quote($url, '/') . '\b(?!")/i',
                '<sup><a href="' . esc_url($url) . '" target="_blank" rel="noopener nofollow">[' . $citation_number . ']</a></sup>',
                $content
            );
        }
        
        return $content;
    }
    
    /**
     * Generate source attribution section
     */
    private function generate_source_attribution($research_data) {
        $sources = json_decode($research_data->sources_json, true);
        
        if (!$sources || !is_array($sources)) {
            return '';
        }
        
        // Filter out sources without URLs
        $valid_sources = array_filter($sources, function($source) {
            return !empty($source['url']);
        });
        
        if (empty($valid_sources)) {
            return '';
        }
        
        // Re-index valid sources
        $valid_sources = array_values($valid_sources);
        
        $attribution = "\n\n<div class=\"mna-sources-section\">\n";
        $attribution .= "<hr style=\"margin: 30px 0; border: none; border-top: 1px solid #eee;\">\n";
        $attribution .= "<h4 style=\"margin-bottom: 15px; color: #333; font-size: 16px;\">Πηγές:</h4>\n";
        $attribution .= "<ol style=\"margin: 0; padding-left: 20px; line-height: 1.6;\">\n";
        
        foreach ($valid_sources as $index => $source) {
            $title = !empty($source['title']) ? $source['title'] : $source['domain'];
            if (empty($title)) {
                $title = 'Source ' . ($index + 1);
            }
            
            $attribution .= '<li style="margin-bottom: 8px;">';
            $attribution .= '<a href="' . esc_url($source['url']) . '" target="_blank" rel="noopener nofollow" style="color: #0073aa; text-decoration: none;">';
            $attribution .= esc_html($title);
            $attribution .= '</a>';
            
            // Add domain if title doesn't include it
            if (!empty($source['domain']) && stripos($title, $source['domain']) === false) {
                $attribution .= ' <em style="color: #666; font-size: 0.9em;">(' . esc_html($source['domain']) . ')</em>';
            }
            
            $attribution .= '</li>' . "\n";
        }
        
        $attribution .= "</ol>\n";
        
        // Add medical disclaimer in Greek
        $attribution .= "<div style=\"margin-top: 20px; padding: 15px; background: #f8f9fa; border-left: 4px solid #007cba; border-radius: 4px;\">\n";
        $attribution .= "<p style=\"margin: 0; font-size: 14px; color: #555; font-style: italic;\">";
        $attribution .= "<strong>Ιατρική Αποποίηση:</strong> Αυτό το άρθρο είναι μόνο για ενημερωτικούς σκοπούς και δεν πρέπει να θεωρείται ιατρική συμβουλή. ";
        $attribution .= "Συμβουλευτείτε πάντα έναν εξειδικευμένο επαγγελματία υγείας πριν λάβετε οποιεσδήποτε ιατρικές αποφάσεις ή κάνετε αλλαγές στο υγειονομικό σας πρόγραμμα.";
        $attribution .= "</p>\n";
        $attribution .= "</div>\n";
        $attribution .= "</div>\n";
        
        return $attribution;
    }
    
    /**
     * Get WordPress categories for the post
     */
    private function get_post_categories($category_name) {
        if (empty($category_name)) {
            return array();
        }
        
        // Check if category exists
        $category = get_category_by_slug(sanitize_title($category_name));
        
        if (!$category) {
            // Create category if it doesn't exist
            $category_id = wp_create_category($category_name);
            return array($category_id);
        }
        
        return array($category->term_id);
    }
    
    /**
     * Get workflow statistics
     */
    public function get_workflow_stats($days = 7) {
        global $wpdb;
        
        $articles_table = $wpdb->prefix . 'mna_articles';
        $since_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $stats = array(
            'articles_pending_review' => $wpdb->get_var(
                "SELECT COUNT(*) FROM $articles_table WHERE status IN ('draft', 'under_review')"
            ),
            'articles_approved' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $articles_table WHERE status = 'approved' AND reviewed_at >= %s",
                $since_date
            )),
            'articles_published' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $articles_table WHERE status = 'published' AND published_at >= %s",
                $since_date
            )),
            'articles_rejected' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $articles_table WHERE status = 'rejected' AND reviewed_at >= %s",
                $since_date
            )),
            'avg_review_time' => 0
        );
        
        // Calculate average review time
        $avg_review_time = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, reviewed_at)) 
             FROM $articles_table 
             WHERE reviewed_at IS NOT NULL AND reviewed_at >= %s",
            $since_date
        ));
        
        $stats['avg_review_time'] = round($avg_review_time, 1);
        
        return $stats;
    }
    
    /**
     * Clean up old articles
     */
    public function cleanup_old_articles($days = 60) {
        global $wpdb;
        
        $articles_table = $wpdb->prefix . 'mna_articles';
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Delete old rejected articles
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM $articles_table WHERE status = 'rejected' AND reviewed_at < %s",
            $cutoff_date
        ));
        
        return $deleted;
    }
    
    /**
     * Add images to WordPress post
     */
    private function add_images_to_post($post_id, $headline_data, $article) {
        // Initialize image service
        require_once MNA_PLUGIN_PATH . 'includes/class-image-service.php';
        $image_service = new MNA_Image_Service();
        
        // Get relevant images
        $images = $image_service->get_article_images(
            $headline_data->headline,
            $headline_data->category,
            $article->generated_content
        );
        
        if (empty($images)) {
            return;
        }
        
        // Set featured image
        if (isset($images['featured'])) {
            $featured_attachment_id = $image_service->attach_image_to_post(
                $images['featured'], 
                $post_id, 
                true // Set as featured
            );
            
            if ($featured_attachment_id) {
                // Log successful image attachment
                MNA_Database::log_activity(
                    null,
                    'image',
                    'completed',
                    'Featured image added to post ' . $post_id
                );
            }
        }
        
        // Add content images
        if (isset($images['content']) && !empty($images['content'])) {
            $content_attachment_ids = array();
            
            foreach ($images['content'] as $content_image) {
                $attachment_id = $image_service->attach_image_to_post(
                    $content_image, 
                    $post_id, 
                    false // Not featured
                );
                
                if ($attachment_id) {
                    $content_attachment_ids[] = $attachment_id;
                }
            }
            
            // Store content image IDs for potential future use
            if (!empty($content_attachment_ids)) {
                update_post_meta($post_id, 'mna_content_images', $content_attachment_ids);
            }
        }
    }
}