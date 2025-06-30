<?php
/**
 * API Manager wrapper class
 */

if (!defined('ABSPATH')) {
    exit;
}

class MNA_API_Manager {
    
    private $perplexity_api;
    private $llm_service;
    
    public function __construct() {
        $this->perplexity_api = new MNA_Perplexity_API();
        $this->llm_service = new MNA_LLM_Service();
        
        // Add AJAX handlers for API testing
        add_action('wp_ajax_mna_test_apis', array($this, 'ajax_test_apis'));
        add_action('wp_ajax_mna_process_batch', array($this, 'ajax_process_batch'));
    }
    
    /**
     * Test all API connections
     */
    public function test_all_apis() {
        $results = array();
        
        // Test Perplexity
        $perplexity_result = $this->perplexity_api->test_connection();
        $results['perplexity'] = $perplexity_result;
        
        // Test LLM services
        $llm_results = $this->llm_service->test_connection('all');
        $results = array_merge($results, $llm_results);
        
        return $results;
    }
    
    /**
     * AJAX handler for testing APIs
     */
    public function ajax_test_apis() {
        check_ajax_referer('mna_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        $results = $this->test_all_apis();
        $message_parts = array();
        
        foreach ($results as $service => $result) {
            $status = $result['success'] ? 'SUCCESS' : 'FAILED';
            $message_parts[] = ucfirst($service) . ': ' . $status;
            if (!$result['success']) {
                $message_parts[] = '  Error: ' . $result['error'];
            }
        }
        
        wp_send_json_success(array(
            'message' => implode("\n", $message_parts)
        ));
    }
    
    /**
     * AJAX handler for batch processing
     */
    public function ajax_process_batch() {
        check_ajax_referer('mna_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        $processor = new MNA_Headline_Processor();
        $result = $processor->process_batch_headlines();
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
}