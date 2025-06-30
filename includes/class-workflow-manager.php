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
        
        $updated = $wpdb->update(
            $articles_table,
            array(
                'status' => 'approved',
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
                'message' => 'Article approved successfully'
            );
        } else {
            return array(
                'success' => false,
                'message' => 'Failed to approve article'
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
        // Look for headline patterns in the content
        $lines = explode("\n", $content);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip empty lines
            if (empty($line)) {
                continue;
            }
            
            // Check if line looks like a title (first non-empty line, or marked with #)
            if (strpos($line, '#') === 0) {
                return trim(str_replace('#', '', $line));
            }
            
            // If it's the first substantial line and not too long, use it as title
            if (strlen($line) > 20 && strlen($line) < 150 && !strpos($line, '.') === false) {
                return $line;
            }
            
            // If first line is short, it's likely the title
            if (strlen($line) < 150) {
                return $line;
            }
            
            break; // Only check first few lines
        }
        
        return null;
    }
    
    /**
     * Prepare content for WordPress publication
     */
    private function prepare_content_for_publication($content, $research_data) {
        // Remove title if it exists (it will be the post title)
        $lines = explode("\n", $content);
        $processed_lines = array();
        $title_removed = false;
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip empty lines at the beginning
            if (empty($line) && empty($processed_lines)) {
                continue;
            }
            
            // Remove first title-like line
            if (!$title_removed && (strpos($line, '#') === 0 || (strlen($line) < 150 && !empty($processed_lines) === false))) {
                $title_removed = true;
                continue;
            }
            
            $processed_lines[] = $line;
        }
        
        $content = implode("\n", $processed_lines);
        
        // Convert citations to HTML links
        $content = $this->convert_citations_to_links($content, $research_data);
        
        // Add proper paragraph breaks
        $content = wpautop($content);
        
        // Add source attribution at the end
        $content .= $this->generate_source_attribution($research_data);
        
        return $content;
    }
    
    /**
     * Convert [Source: URL] citations to HTML links
     */
    private function convert_citations_to_links($content, $research_data) {
        $sources = json_decode($research_data->sources_json, true);
        
        if (!$sources || !is_array($sources)) {
            return $content;
        }
        
        // Create a map of URLs to titles
        $url_map = array();
        foreach ($sources as $index => $source) {
            if (!empty($source['url'])) {
                $title = !empty($source['title']) ? $source['title'] : $source['domain'];
                $url_map[$source['url']] = array(
                    'title' => $title,
                    'index' => $index + 1
                );
            }
        }
        
        // Replace citations with proper HTML links
        $content = preg_replace_callback(
            '/\[Source:\s*(https?:\/\/[^\]]+)\]/i',
            function ($matches) use ($url_map) {
                $url = trim($matches[1]);
                
                if (isset($url_map[$url])) {
                    $title = $url_map[$url]['title'];
                    $index = $url_map[$url]['index'];
                    return '<a href="' . esc_url($url) . '" target="_blank" rel="noopener">[' . $index . ']</a>';
                } else {
                    return '<a href="' . esc_url($url) . '" target="_blank" rel="noopener">[Source]</a>';
                }
            },
            $content
        );
        
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
        
        $attribution = "\n\n<hr>\n<h4>Sources:</h4>\n<ol>\n";
        
        foreach ($sources as $source) {
            if (!empty($source['url'])) {
                $title = !empty($source['title']) ? $source['title'] : $source['domain'];
                $attribution .= '<li><a href="' . esc_url($source['url']) . '" target="_blank" rel="noopener">' . esc_html($title) . '</a></li>' . "\n";
            }
        }
        
        $attribution .= "</ol>\n";
        
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
}