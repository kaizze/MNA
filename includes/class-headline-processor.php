<?php
/**
 * Headline processor class - handles the processing workflow for individual headlines
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
     * Process a single headline through the complete workflow
     */
    public function process_single_headline($headline_id) {
        global $wpdb;
        
        $headlines_table = $wpdb->prefix . 'mna_headlines';
        
        // Get headline data
        $headline = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $headlines_table WHERE id = %d",
            $headline_id
        ));
        
        if (!$headline) {
            return array(
                'success' => false,
                'message' => 'Headline not found'
            );
        }
        
        if ($headline->status !== 'pending') {
            return array(
                'success' => false,
                'message' => 'Headline is not in pending status'
            );
        }
        
        // Update status to processing
        MNA_Database::update_headline_status($headline_id, 'processing', 'Started automated processing');
        
        try {
            // Step 1: Research with Perplexity
            $research_result = $this->research_headline($headline);
            
            if (!$research_result['success']) {
                MNA_Database::update_headline_status($headline_id, 'failed', 'Research failed: ' . $research_result['error']);
                return array(
                    'success' => false,
                    'message' => 'Research failed: ' . $research_result['error']
                );
            }
            
            // Store research data
            $research_id = MNA_Database::insert_research(
                $headline_id,
                $research_result['query_used'],
                $research_result['research_data'],
                $research_result['sources']
            );
            
            if (!$research_id) {
                MNA_Database::update_headline_status($headline_id, 'failed', 'Failed to store research data');
                return array(
                    'success' => false,
                    'message' => 'Failed to store research data'
                );
            }
            
            // Update status to researched
            MNA_Database::update_headline_status($headline_id, 'researched', 'Research completed successfully');
            
            // Step 2: Generate article with LLM
            $article_result = $this->generate_article($headline, $research_result);
            
            if (!$article_result['success']) {
                MNA_Database::update_headline_status($headline_id, 'failed', 'Article generation failed: ' . $article_result['error']);
                return array(
                    'success' => false,
                    'message' => 'Article generation failed: ' . $article_result['error']
                );
            }
            
            // Store generated article
            $article_id = MNA_Database::insert_article(
                $headline_id,
                $research_id,
                $article_result['content'],
                $article_result['llm_used'],
                $article_result['quality_score']
            );
            
            if (!$article_id) {
                MNA_Database::update_headline_status($headline_id, 'failed', 'Failed to store generated article');
                return array(
                    'success' => false,
                    'message' => 'Failed to store generated article'
                );
            }
            
            // Update status to generated
            MNA_Database::update_headline_status($headline_id, 'generated', 'Article generated successfully, ready for review');
            
            // Send notification if enabled
            $this->send_notification($headline, $article_id);
            
            return array(
                'success' => true,
                'message' => 'Headline processed successfully',
                'article_id' => $article_id,
                'research_id' => $research_id
            );
            
        } catch (Exception $e) {
            MNA_Database::update_headline_status($headline_id, 'failed', 'Processing error: ' . $e->getMessage());
            
            // Log the error
            MNA_Database::log_activity(
                $headline_id,
                'error',
                'failed',
                'Processing exception: ' . $e->getMessage()
            );
            
            return array(
                'success' => false,
                'message' => 'Processing error: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Research headline using Perplexity API
     */
    private function research_headline($headline) {
        return $this->perplexity_api->research_headline($headline->headline);
    }
    
    /**
     * Generate article using LLM service
     */
    private function generate_article($headline, $research_result) {
        return $this->llm_service->generate_article(
            $headline->headline,
            $research_result['research_data'],
            $research_result['sources']
        );
    }
    
    /**
     * Process headlines in batch
     */
    public function process_batch($batch_size = null) {
        $batch_size = $batch_size ?: get_option('mna_batch_size', 5);
        
        // Get pending headlines
        $pending_headlines = MNA_Database::get_headlines_by_status('pending', $batch_size);
        
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
            'errors' => array()
        );
        
        foreach ($pending_headlines as $headline) {
            $result = $this->process_single_headline($headline->id);
            $results['processed']++;
            
            if ($result['success']) {
                $results['successful']++;
            } else {
                $results['failed']++;
                $results['errors'][] = array(
                    'headline_id' => $headline->id,
                    'headline' => $headline->headline,
                    'error' => $result['message']
                );
            }
            
            // Add delay between processing to avoid rate limits
            sleep(2);
        }
        
        return array(
            'success' => true,
            'message' => sprintf(
                'Batch processing completed. %d processed, %d successful, %d failed.',
                $results['processed'],
                $results['successful'],
                $results['failed']
            ),
            'results' => $results
        );
    }
    
    /**
     * Validate headline before processing
     */
    public function validate_headline($headline_text) {
        $issues = array();
        
        // Check length
        if (strlen($headline_text) < 10) {
            $issues[] = 'Headline too short (minimum 10 characters)';
        }
        
        if (strlen($headline_text) > 200) {
            $issues[] = 'Headline too long (maximum 200 characters)';
        }
        
        // Check for medical relevance (basic keywords)
        $medical_keywords = array(
            'health', 'medical', 'drug', 'treatment', 'disease', 'study',
            'research', 'patient', 'doctor', 'hospital', 'medicine', 'therapy',
            'clinical', 'trial', 'diagnosis', 'symptom', 'virus', 'bacteria',
            'cancer', 'diabetes', 'heart', 'brain', 'covid', 'vaccine'
        );
        
        $has_medical_keyword = false;
        foreach ($medical_keywords as $keyword) {
            if (stripos($headline_text, $keyword) !== false) {
                $has_medical_keyword = true;
                break;
            }
        }
        
        if (!$has_medical_keyword) {
            $issues[] = 'Headline may not be medical/health related';
        }
        
        // Check for duplicate
        if ($this->is_duplicate_headline($headline_text)) {
            $issues[] = 'Similar headline already exists in the system';
        }
        
        return array(
            'valid' => empty($issues),
            'issues' => $issues
        );
    }
    
    /**
     * Check for duplicate headlines
     */
    private function is_duplicate_headline($headline_text) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'mna_headlines';
        
        // Simple similarity check (can be improved with fuzzy matching)
        $similar = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE headline LIKE %s OR headline LIKE %s",
            '%' . $wpdb->esc_like($headline_text) . '%',
            '%' . $wpdb->esc_like(substr($headline_text, 0, 50)) . '%'
        ));
        
        return $similar > 0;
    }
    
    /**
     * Send notification about processed article
     */
    private function send_notification($headline, $article_id) {
        if (!get_option('mna_email_notifications', true)) {
            return;
        }
        
        $admin_email = get_option('admin_email');
        $site_name = get_bloginfo('name');
        
        $subject = sprintf('[%s] New Article Ready for Review', $site_name);
        
        $message = sprintf(
            "A new medical news article has been generated and is ready for review:\n\n" .
            "Headline: %s\n\n" .
            "Please review the article in your WordPress admin:\n" .
            "%s\n\n" .
            "This is an automated message from the Medical News Automation plugin.",
            $headline->headline,
            admin_url('admin.php?page=mna-review&article_id=' . $article_id)
        );
        
        wp_mail($admin_email, $subject, $message);
    }
    
    /**
     * Get processing statistics
     */
    public function get_processing_stats($days = 7) {
        global $wpdb;
        
        $headlines_table = $wpdb->prefix . 'mna_headlines';
        $articles_table = $wpdb->prefix . 'mna_articles';
        
        $date_limit = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $stats = array(
            'headlines_added' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $headlines_table WHERE created_at >= %s",
                $date_limit
            )),
            'headlines_processed' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $headlines_table WHERE processed_at >= %s",
                $date_limit
            )),
            'articles_generated' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $articles_table WHERE created_at >= %s",
                $date_limit
            )),
            'processing_success_rate' => 0
        );
        
        if ($stats['headlines_processed'] > 0) {
            $stats['processing_success_rate'] = round(
                ($stats['articles_generated'] / $stats['headlines_processed']) * 100,
                2
            );
        }
        
        return $stats;
    }
    
    /**
     * Retry failed headlines
     */
    public function retry_failed_headlines($limit = 5) {
        global $wpdb;
        
        $headlines_table = $wpdb->prefix . 'mna_headlines';
        
        // Get failed headlines from the last 24 hours
        $failed_headlines = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $headlines_table 
             WHERE status = 'failed' 
             AND processed_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
             ORDER BY processed_at ASC 
             LIMIT %d",
            $limit
        ));
        
        $results = array(
            'retried' => 0,
            'successful' => 0,
            'failed' => 0
        );
        
        foreach ($failed_headlines as $headline) {
            // Reset status to pending
            MNA_Database::update_headline_status($headline->id, 'pending', 'Retrying failed processing');
            
            // Process again
            $result = $this->process_single_headline($headline->id);
            $results['retried']++;
            
            if ($result['success']) {
                $results['successful']++;
            } else {
                $results['failed']++;
            }
            
            // Add delay between retries
            sleep(3);
        }
        
        return $results;
    }
}