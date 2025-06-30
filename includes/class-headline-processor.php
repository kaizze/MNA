<?php
/**
 * Headline processing orchestrator
 */

if (!defined('ABSPATH')) {
    exit;
}

class MNA_Headline_Processor {
    
    private $perplexity_api;
    private $llm_service;
    
    public function __construct() {
        $this->perplexity_api = new MNA_Perplexity_API();
        $this->llm_service = new MNA_LLM_Service();
    }
    
    /**
     * Process a single headline through the complete pipeline
     */
    public function process_single_headline($headline_id) {
        global $wpdb;
        
        // Get headline data
        $headlines_table = $wpdb->prefix . 'mna_headlines';
        $headline_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $headlines_table WHERE id = %d",
            $headline_id
        ));
        
        if (!$headline_data) {
            return array(
                'success' => false,
                'message' => 'Headline not found'
            );
        }
        
        // Check if already processed
        if ($headline_data->status !== 'pending') {
            return array(
                'success' => false,
                'message' => 'Headline already processed or in progress'
            );
        }
        
        try {
            // Update status to processing
            MNA_Database::update_headline_status($headline_id, 'processing');
            
            // Step 1: Research with Perplexity
            $research_result = $this->research_headline($headline_data);
            if (!$research_result['success']) {
                MNA_Database::update_headline_status($headline_id, 'failed', $research_result['error']);
                return $research_result;
            }
            
            // Step 2: Generate article with LLM
            $article_result = $this->generate_article($headline_data, $research_result);
            if (!$article_result['success']) {
                MNA_Database::update_headline_status($headline_id, 'failed', $article_result['error']);
                return $article_result;
            }
            
            // Step 3: Store article for review
            $article_id = $this->store_article_for_review($headline_data, $research_result, $article_result);
            if (!$article_id) {
                MNA_Database::update_headline_status($headline_id, 'failed', 'Failed to store article');
                return array(
                    'success' => false,
                    'message' => 'Failed to store article for review'
                );
            }
            
            // Update headline status to generated
            MNA_Database::update_headline_status($headline_id, 'generated', 'Article generated successfully');
            
            // Send notification if enabled
            $this->send_review_notification($headline_data, $article_id);
            
            return array(
                'success' => true,
                'message' => 'Article generated successfully and ready for review',
                'article_id' => $article_id,
                'headline_id' => $headline_id
            );
            
        } catch (Exception $e) {
            MNA_Database::update_headline_status($headline_id, 'failed', $e->getMessage());
            
            return array(
                'success' => false,
                'message' => 'Processing failed: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Process multiple headlines in batch
     */
    public function process_batch_headlines($limit = null) {
        if (!$limit) {
            $limit = get_option('mna_batch_size', 5);
        }
        
        // Get pending headlines
        $pending_headlines = MNA_Database::get_headlines_by_status('pending', $limit);
        
        if (empty($pending_headlines)) {
            return array(
                'success' => true,
                'message' => 'No pending headlines to process',
                'processed' => 0
            );
        }
        
        $results = array(
            'processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'details' => array()
        );
        
        foreach ($pending_headlines as $headline) {
            $result = $this->process_single_headline($headline->id);
            
            $results['processed']++;
            
            if ($result['success']) {
                $results['successful']++;
            } else {
                $results['failed']++;
            }
            
            $results['details'][] = array(
                'headline_id' => $headline->id,
                'headline' => $headline->headline,
                'success' => $result['success'],
                'message' => $result['message']
            );
            
            // Small delay between API calls to respect rate limits
            sleep(2);
        }
        
        return array(
            'success' => true,
            'message' => "Batch processing completed: {$results['successful']} successful, {$results['failed']} failed",
            'results' => $results
        );
    }
    
    /**
     * Research headline using Perplexity
     */
    private function research_headline($headline_data) {
        $research_result = $this->perplexity_api->research_headline($headline_data->headline);
        
        if (!$research_result['success']) {
            return $research_result;
        }
        
        // Store research data in database
        $research_id = MNA_Database::insert_research(
            $headline_data->id,
            $research_result['query_used'],
            $research_result['research_data'],
            $research_result['sources'],
            $this->calculate_research_quality($research_result)
        );
        
        if (!$research_id) {
            return array(
                'success' => false,
                'error' => 'Failed to store research data'
            );
        }
        
        // Update headline status
        MNA_Database::update_headline_status($headline_data->id, 'researched');
        
        $research_result['research_id'] = $research_id;
        return $research_result;
    }
    
    /**
     * Generate article using LLM
     */
    private function generate_article($headline_data, $research_result) {
        $article_result = $this->llm_service->generate_article(
            $headline_data->headline,
            $research_result['research_data'],
            $research_result['sources']
        );
        
        return $article_result;
    }
    
    /**
     * Store generated article for review
     */
    private function store_article_for_review($headline_data, $research_result, $article_result) {
        return MNA_Database::insert_article(
            $headline_data->id,
            $research_result['research_id'],
            $article_result['content'],
            $article_result['llm_used'],
            $article_result['quality_score'] ?? null
        );
    }
    
    /**
     * Calculate research quality score
     */
    private function calculate_research_quality($research_result) {
        $score = 5; // Base score
        
        // Check research content length
        $content_length = strlen($research_result['research_data']);
        if ($content_length > 1000) {
            $score += 2;
        } elseif ($content_length > 500) {
            $score += 1;
        }
        
        // Check number of sources
        $source_count = count($research_result['sources']);
        if ($source_count >= 5) {
            $score += 2;
        } elseif ($source_count >= 3) {
            $score += 1;
        }
        
        // Check source credibility
        $avg_credibility = 0;
        if (!empty($research_result['sources'])) {
            $total_credibility = 0;
            foreach ($research_result['sources'] as $source) {
                $total_credibility += $source['credibility_score'] ?? 5;
            }
            $avg_credibility = $total_credibility / count($research_result['sources']);
            
            if ($avg_credibility >= 8) {
                $score += 2;
            } elseif ($avg_credibility >= 6) {
                $score += 1;
            }
        }
        
        return min($score, 10); // Cap at 10
    }
    
    /**
     * Send notification to journalists about new article for review
     */
    private function send_review_notification($headline_data, $article_id) {
        if (!get_option('mna_email_notifications', true)) {
            return;
        }
        
        // Get admin email or journalist emails
        $notification_emails = $this->get_notification_emails();
        
        if (empty($notification_emails)) {
            return;
        }
        
        $subject = '[Medical News Automation] New Article Ready for Review';
        $review_url = admin_url('admin.php?page=mna-review&article_id=' . $article_id);
        
        $message = "A new medical news article has been generated and is ready for review.\n\n";
        $message .= "Headline: " . $headline_data->headline . "\n";
        $message .= "Category: " . ($headline_data->category ?: 'Uncategorized') . "\n";
        $message .= "Generated: " . current_time('Y-m-d H:i:s') . "\n\n";
        $message .= "Review the article here: " . $review_url . "\n\n";
        $message .= "---\n";
        $message .= "Medical News Automation Plugin\n";
        $message .= get_site_url();
        
        foreach ($notification_emails as $email) {
            wp_mail($email, $subject, $message);
        }
    }
    
    /**
     * Get email addresses for notifications
     */
    private function get_notification_emails() {
        $emails = array();
        
        // Get users with editor or administrator capabilities
        $users = get_users(array(
            'capability' => 'edit_posts',
            'fields' => array('user_email')
        ));
        
        foreach ($users as $user) {
            if (!empty($user->user_email)) {
                $emails[] = $user->user_email;
            }
        }
        
        // Fallback to admin email
        if (empty($emails)) {
            $emails[] = get_option('admin_email');
        }
        
        return array_unique($emails);
    }
    
    /**
     * Validate headline before processing
     */
    public function validate_headline($headline) {
        $errors = array();
        
        // Check minimum length
        if (strlen(trim($headline)) < 10) {
            $errors[] = 'Headline too short (minimum 10 characters)';
        }
        
        // Check maximum length
        if (strlen($headline) > 500) {
            $errors[] = 'Headline too long (maximum 500 characters)';
        }
        
        // Check for medical relevance (basic keywords)
        $medical_keywords = array(
            'health', 'medical', 'medicine', 'disease', 'treatment', 'therapy',
            'hospital', 'doctor', 'patient', 'clinical', 'study', 'research',
            'vaccine', 'drug', 'medication', 'diagnosis', 'symptoms', 'cancer',
            'diabetes', 'heart', 'brain', 'surgery', 'virus', 'bacteria',
            'WHO', 'CDC', 'FDA', 'pandemic', 'epidemic', 'outbreak'
        );
        
        $headline_lower = strtolower($headline);
        $has_medical_keyword = false;
        
        foreach ($medical_keywords as $keyword) {
            if (strpos($headline_lower, strtolower($keyword)) !== false) {
                $has_medical_keyword = true;
                break;
            }
        }
        
        if (!$has_medical_keyword) {
            $errors[] = 'Headline does not appear to be medical/health related';
        }
        
        // Check for duplicate headlines
        global $wpdb;
        $headlines_table = $wpdb->prefix . 'mna_headlines';
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $headlines_table WHERE headline = %s",
            $headline
        ));
        
        if ($existing > 0) {
            $errors[] = 'This headline has already been processed';
        }
        
        return array(
            'valid' => empty($errors),
            'errors' => $errors
        );
    }
    
    /**
     * Get processing statistics
     */
    public function get_processing_stats($days = 7) {
        global $wpdb;
        
        $headlines_table = $wpdb->prefix . 'mna_headlines';
        $articles_table = $wpdb->prefix . 'mna_articles';
        $logs_table = $wpdb->prefix . 'mna_logs';
        
        $since_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $stats = array(
            'headlines_processed' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $headlines_table WHERE processed_at >= %s",
                $since_date
            )),
            'articles_generated' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $articles_table WHERE created_at >= %s",
                $since_date
            )),
            'articles_published' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $articles_table WHERE published_at >= %s",
                $since_date
            )),
            'success_rate' => 0,
            'avg_processing_time' => 0,
            'total_tokens_used' => 0
        );
        
        // Calculate success rate
        $total_attempts = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $headlines_table WHERE created_at >= %s",
            $since_date
        ));
        
        if ($total_attempts > 0) {
            $stats['success_rate'] = round(($stats['articles_generated'] / $total_attempts) * 100, 1);
        }
        
        // Calculate average processing time
        $avg_time = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(execution_time) FROM $logs_table WHERE created_at >= %s AND execution_time IS NOT NULL",
            $since_date
        ));
        
        $stats['avg_processing_time'] = round($avg_time, 2);
        
        // Calculate total API tokens used
        $stats['total_tokens_used'] = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(api_tokens_used) FROM $logs_table WHERE created_at >= %s AND api_tokens_used IS NOT NULL",
            $since_date
        ));
        
        return $stats;
    }
    
    /**
     * Clean up old processed headlines
     */
    public function cleanup_old_headlines($days = 30) {
        global $wpdb;
        
        $headlines_table = $wpdb->prefix . 'mna_headlines';
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Delete old processed headlines (keeping failed ones for analysis)
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM $headlines_table WHERE status IN ('published', 'generated') AND processed_at < %s",
            $cutoff_date
        ));
        
        return $deleted;
    }
    
    /**
     * Retry failed headlines
     */
    public function retry_failed_headlines($limit = 5) {
        global $wpdb;
        
        $headlines_table = $wpdb->prefix . 'mna_headlines';
        
        // Reset failed headlines to pending (only retry once per day)
        $yesterday = date('Y-m-d H:i:s', strtotime('-1 day'));
        
        $reset_count = $wpdb->query($wpdb->prepare(
            "UPDATE $headlines_table SET status = 'pending', notes = CONCAT(COALESCE(notes, ''), ' [Retrying]') 
             WHERE status = 'failed' AND processed_at < %s LIMIT %d",
            $yesterday,
            $limit
        ));
        
        if ($reset_count > 0) {
            return array(
                'success' => true,
                'message' => "Reset {$reset_count} failed headlines for retry"
            );
        } else {
            return array(
                'success' => true,
                'message' => 'No failed headlines to retry'
            );
        }
    }
}