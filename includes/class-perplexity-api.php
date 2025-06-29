<?php
/**
 * Perplexity API integration class
 */

if (!defined('ABSPATH')) {
    exit;
}

class MNA_Perplexity_API {
    
    private $api_key;
    private $base_url = 'https://api.perplexity.ai/chat/completions';
    private $model = 'llama-3.1-sonar-small-128k-online'; // Default model for online search
    
    public function __construct() {
        $this->api_key = get_option('mna_perplexity_api_key', '');
    }
    
    /**
     * Research a medical headline using Perplexity
     */
    public function research_headline($headline) {
        if (empty($this->api_key)) {
            return array(
                'success' => false,
                'error' => 'Perplexity API key not configured'
            );
        }
        
        $start_time = microtime(true);
        
        // Construct the research query
        $research_prompt = $this->build_research_prompt($headline);
        
        // Make API request
        $response = $this->make_api_request($research_prompt);
        
        $execution_time = microtime(true) - $start_time;
        
        if ($response['success']) {
            // Parse the response and extract sources
            $parsed_data = $this->parse_research_response($response['data']);
            
            // Log the successful research
            MNA_Database::log_activity(
                null, 
                'perplexity', 
                'completed', 
                'Research completed successfully', 
                $execution_time,
                $response['tokens_used'] ?? null
            );
            
            return array(
                'success' => true,
                'research_data' => $parsed_data['content'],
                'sources' => $parsed_data['sources'],
                'query_used' => $research_prompt,
                'execution_time' => $execution_time,
                'tokens_used' => $response['tokens_used'] ?? 0
            );
            
        } else {
            // Log the failed research
            MNA_Database::log_activity(
                null, 
                'perplexity', 
                'failed', 
                'Research failed: ' . $response['error'], 
                $execution_time
            );
            
            return array(
                'success' => false,
                'error' => $response['error']
            );
        }
    }
    
    /**
     * Build research prompt for medical headlines
     */
    private function build_research_prompt($headline) {
        $prompt = "Research the following medical news headline thoroughly. Provide comprehensive information including:

1. Key medical facts and details
2. Recent developments or studies related to this topic
3. Expert opinions or statements
4. Statistical data if available
5. Context and background information
6. Potential implications for public health

Please focus on credible medical sources, research institutions, health organizations, and peer-reviewed studies.

Headline: {$headline}

Provide detailed research with proper source citations.";
        
        return $prompt;
    }
    
    /**
     * Make API request to Perplexity
     */
    private function make_api_request($prompt) {
        $headers = array(
            'Authorization' => 'Bearer ' . $this->api_key,
            'Content-Type' => 'application/json'
        );
        
        $body = array(
            'model' => $this->model,
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => 'You are a medical research assistant. Provide accurate, well-sourced information about medical topics from reliable sources.'
                ),
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'max_tokens' => 2000,
            'temperature' => 0.2,
            'return_citations' => true
        );
        
        $response = wp_remote_post($this->base_url, array(
            'headers' => $headers,
            'body' => wp_json_encode($body),
            'timeout' => 60,
            'data_format' => 'body'
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => $response->get_error_message()
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            $error_data = json_decode($response_body, true);
            return array(
                'success' => false,
                'error' => isset($error_data['error']['message']) ? $error_data['error']['message'] : 'API request failed'
            );
        }
        
        $data = json_decode($response_body, true);
        
        if (!$data || !isset($data['choices'][0]['message']['content'])) {
            return array(
                'success' => false,
                'error' => 'Invalid API response format'
            );
        }
        
        return array(
            'success' => true,
            'data' => $data,
            'tokens_used' => isset($data['usage']['total_tokens']) ? $data['usage']['total_tokens'] : null
        );
    }
    
    /**
     * Parse research response and extract sources
     */
    private function parse_research_response($api_data) {
        $content = $api_data['choices'][0]['message']['content'];
        $sources = array();
        
        // Extract citations if available
        if (isset($api_data['citations'])) {
            foreach ($api_data['citations'] as $citation) {
                $sources[] = array(
                    'url' => $citation['url'] ?? '',
                    'title' => $citation['title'] ?? '',
                    'snippet' => $citation['text'] ?? '',
                    'domain' => isset($citation['url']) ? parse_url($citation['url'], PHP_URL_HOST) : '',
                    'credibility_score' => $this->calculate_source_credibility($citation['url'] ?? '')
                );
            }
        }
        
        // If no citations, try to extract URLs from content
        if (empty($sources)) {
            $sources = $this->extract_urls_from_content($content);
        }
        
        // Store/update source credibility in database
        $this->update_source_credibility($sources);
        
        return array(
            'content' => $content,
            'sources' => $sources
        );
    }
    
    /**
     * Extract URLs from content text
     */
    private function extract_urls_from_content($content) {
        $sources = array();
        
        // Pattern to match URLs
        $url_pattern = '/https?:\/\/[^\s\]]+/i';
        preg_match_all($url_pattern, $content, $matches);
        
        foreach ($matches[0] as $url) {
            $domain = parse_url($url, PHP_URL_HOST);
            $sources[] = array(
                'url' => $url,
                'title' => '',
                'snippet' => '',
                'domain' => $domain,
                'credibility_score' => $this->calculate_source_credibility($url)
            );
        }
        
        return $sources;
    }
    
    /**
     * Calculate source credibility score
     */
    private function calculate_source_credibility($url) {
        if (empty($url)) {
            return 5; // Default neutral score
        }
        
        $domain = parse_url($url, PHP_URL_HOST);
        $domain = strtolower(str_replace('www.', '', $domain));
        
        // High credibility medical sources
        $high_credibility = array(
            'who.int', 'cdc.gov', 'nih.gov', 'pubmed.ncbi.nlm.nih.gov',
            'nejm.org', 'thelancet.com', 'bmj.com', 'jama.jamanetwork.com',
            'nature.com', 'cell.com', 'science.org', 'pnas.org',
            'mayoclinic.org', 'clevelandclinic.org', 'hopkinsmedicine.org'
        );
        
        // Medium credibility sources
        $medium_credibility = array(
            'healthline.com', 'webmd.com', 'medscape.com', 'reuters.com',
            'bbc.com', 'cnn.com', 'nytimes.com', 'washingtonpost.com'
        );
        
        // Check domain credibility
        if (in_array($domain, $high_credibility)) {
            return 9;
        } elseif (in_array($domain, $medium_credibility)) {
            return 7;
        } elseif (strpos($domain, '.gov') !== false || strpos($domain, '.edu') !== false) {
            return 8;
        } else {
            return 5; // Default score
        }
    }
    
    /**
     * Update source credibility in database
     */
    private function update_source_credibility($sources) {
        global $wpdb;
        
        $sources_table = $wpdb->prefix . 'mna_sources';
        
        foreach ($sources as $source) {
            if (empty($source['url'])) {
                continue;
            }
            
            // Check if source already exists
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT id, times_cited FROM $sources_table WHERE url = %s",
                $source['url']
            ));
            
            if ($existing) {
                // Update existing source
                $wpdb->update(
                    $sources_table,
                    array(
                        'times_cited' => $existing->times_cited + 1,
                        'last_verified' => current_time('mysql')
                    ),
                    array('id' => $existing->id),
                    array('%d', '%s'),
                    array('%d')
                );
            } else {
                // Insert new source
                $wpdb->insert(
                    $sources_table,
                    array(
                        'url' => $source['url'],
                        'domain' => $source['domain'],
                        'title' => $source['title'],
                        'credibility_score' => $source['credibility_score'],
                        'times_cited' => 1,
                        'last_verified' => current_time('mysql')
                    ),
                    array('%s', '%s', '%s', '%d', '%d', '%s')
                );
            }
        }
    }
    
    /**
     * Test API connection
     */
    public function test_connection() {
        if (empty($this->api_key)) {
            return array(
                'success' => false,
                'error' => 'API key not configured'
            );
        }
        
        $test_prompt = "Test connection: What is the World Health Organization?";
        $response = $this->make_api_request($test_prompt);
        
        if ($response['success']) {
            return array(
                'success' => true,
                'message' => 'Perplexity API connection successful',
                'tokens_used' => $response['tokens_used'] ?? 0
            );
        } else {
            return array(
                'success' => false,
                'error' => $response['error']
            );
        }
    }
}