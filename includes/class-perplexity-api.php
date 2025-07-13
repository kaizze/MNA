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
    private $models = array(
        'llama-3.1-sonar-large-128k-online',
        'llama-3.1-sonar-small-128k-online',
        'llama-3.1-sonar-huge-128k-online',
        'sonar-large-32k-online',
        'sonar-medium-32k-online'
    );
    
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
     * Build research prompt for medical headlines in Greek
     */
    private function build_research_prompt($headline) {
        $prompt = "Ερεύνησε διεξοδικά τον ακόλουθο τίτλο ιατρικής είδησης. Παρέχε περιεκτικές πληροφορίες που περιλαμβάνουν:

1. Βασικά ιατρικά γεγονότα και λεπτομέρειες
2. Πρόσφατες εξελίξεις ή μελέτες σχετικές με αυτό το θέμα
3. Γνώμες ή δηλώσεις ειδικών
4. Στατιστικά δεδομένα αν είναι διαθέσιμα
5. Πλαίσιο και πληροφορίες υπόβαθρου
6. Πιθανές επιπτώσεις για τη δημόσια υγεία

Παρακαλώ εστίασε σε αξιόπιστες ιατρικές πηγές, ερευνητικά ιδρύματα, οργανισμούς υγείας και κριτικά αξιολογημένες μελέτες. Προτιμήστε πηγές στα ελληνικά όταν είναι διαθέσιμες.

Τίτλος: {$headline}

Παρέχε λεπτομερή έρευνα με σωστές παραπομπές πηγών:";
        
        return $prompt;
    }
    
    /**
     * Make API request to Perplexity with model fallback
     */
    private function make_api_request($prompt) {
        $headers = array(
            'Authorization' => 'Bearer ' . $this->api_key,
            'Content-Type' => 'application/json'
        );
        
        $base_body = array(
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => 'Είσαι βοηθός ιατρικής έρευνας. Παρέχεις ακριβείς, καλά τεκμηριωμένες πληροφορίες για ιατρικά θέματα από αξιόπιστες πηγές. Απαντάς στα ελληνικά όταν είναι δυνατόν.'
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
        
        $last_error = '';
        
        // Try each model until one works
        foreach ($this->models as $model) {
            $body = array_merge($base_body, array('model' => $model));
            
            $response = wp_remote_post($this->base_url, array(
                'headers' => $headers,
                'body' => wp_json_encode($body),
                'timeout' => 60,
                'data_format' => 'body'
            ));
            
            if (is_wp_error($response)) {
                $last_error = $response->get_error_message();
                continue; // Try next model
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            
            if ($response_code === 200) {
                $data = json_decode($response_body, true);
                
                if ($data && isset($data['choices'][0]['message']['content'])) {
                    return array(
                        'success' => true,
                        'data' => $data,
                        'tokens_used' => isset($data['usage']['total_tokens']) ? $data['usage']['total_tokens'] : null,
                        'model_used' => $model
                    );
                }
            } else {
                // Parse error from response
                $error_data = json_decode($response_body, true);
                $last_error = isset($error_data['error']['message']) 
                    ? $error_data['error']['message'] 
                    : "HTTP {$response_code}: {$response_body}";
            }
        }
        
        // If all models failed, return the last error
        return array(
            'success' => false,
            'error' => $last_error ?: 'All Perplexity models failed. Please check your API key and try again.'
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
        
        // Always try to extract URLs from content (Perplexity often embeds sources in text)
        $content_sources = $this->extract_urls_from_content($content);
        
        // Merge with citations, avoiding duplicates
        foreach ($content_sources as $content_source) {
            $is_duplicate = false;
            foreach ($sources as $existing_source) {
                if ($existing_source['url'] === $content_source['url']) {
                    $is_duplicate = true;
                    break;
                }
            }
            if (!$is_duplicate && !empty($content_source['url'])) {
                $sources[] = $content_source;
            }
        }
        
        // Also extract from markdown-style references in content
        $markdown_sources = $this->extract_markdown_references($content);
        foreach ($markdown_sources as $markdown_source) {
            $is_duplicate = false;
            foreach ($sources as $existing_source) {
                if ($existing_source['url'] === $markdown_source['url']) {
                    $is_duplicate = true;
                    break;
                }
            }
            if (!$is_duplicate && !empty($markdown_source['url'])) {
                $sources[] = $markdown_source;
            }
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
     * Extract markdown-style references [1], [2], etc.
     */
    private function extract_markdown_references($content) {
        $sources = array();
        
        // Look for reference-style links at the end of content
        // Pattern: 1. **Source Name:** URL
        // Pattern: [1] Source Name - URL
        // Pattern: ### Sources:\n1. Source Name (URL)
        
        $patterns = array(
            '/^\d+\.\s*\*\*([^:]+):\*\*\s*(https?:\/\/[^\s\n]+)/m',
            '/^\[\d+\]\s*([^-]+)\s*-\s*(https?:\/\/[^\s\n]+)/m',
            '/^\d+\.\s*([^(]+)\s*\((https?:\/\/[^)]+)\)/m',
            '/^\d+\.\s*([^:]+):\s*(https?:\/\/[^\s\n]+)/m'
        );
        
        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);
            
            foreach ($matches as $match) {
                if (count($match) >= 3) {
                    $title = trim($match[1]);
                    $url = trim($match[2]);
                    $domain = parse_url($url, PHP_URL_HOST);
                    
                    $sources[] = array(
                        'url' => $url,
                        'title' => $title,
                        'snippet' => '',
                        'domain' => $domain,
                        'credibility_score' => $this->calculate_source_credibility($url)
                    );
                }
            }
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
        
        // Simple test prompt
        $test_prompt = "What is WHO?";
        
        // Test with just the first model and simple request
        $headers = array(
            'Authorization' => 'Bearer ' . $this->api_key,
            'Content-Type' => 'application/json'
        );
        
        $body = array(
            'model' => $this->models[0],
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $test_prompt
                )
            ),
            'max_tokens' => 100
        );
        
        $response = wp_remote_post($this->base_url, array(
            'headers' => $headers,
            'body' => wp_json_encode($body),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => 'Connection error: ' . $response->get_error_message()
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code === 200) {
            return array(
                'success' => true,
                'message' => 'Perplexity API connection successful',
                'model_used' => $this->models[0]
            );
        } else {
            $error_data = json_decode($response_body, true);
            $error_message = isset($error_data['error']['message']) 
                ? $error_data['error']['message'] 
                : "HTTP {$response_code}: {$response_body}";
                
            return array(
                'success' => false,
                'error' => $error_message
            );
        }
    }
}