<?php
/**
 * Image service for medical articles
 */

if (!defined('ABSPATH')) {
    exit;
}

class MNA_Image_Service {
    
    private $unsplash_access_key;
    private $openai_api_key;
    
    public function __construct() {
        $this->unsplash_access_key = get_option('mna_unsplash_api_key', '');
        $this->openai_api_key = get_option('mna_openai_api_key', '');
    }
    
    /**
     * Get relevant images for medical article
     */
    public function get_article_images($headline, $category, $content) {
        $images = array();
        
        // Extract medical keywords from content
        $keywords = $this->extract_medical_keywords($headline, $category, $content);
        
        // Try different image sources
        $featured_image = $this->get_featured_image($keywords);
        if ($featured_image) {
            $images['featured'] = $featured_image;
        }
        
        // Get additional content images if needed
        $content_images = $this->get_content_images($keywords, 2);
        if (!empty($content_images)) {
            $images['content'] = $content_images;
        }
        
        return $images;
    }
    
    /**
     * Get featured image for article
     */
    private function get_featured_image($keywords) {
        // Try Unsplash first
        if (!empty($this->unsplash_access_key)) {
            $unsplash_image = $this->search_unsplash($keywords['primary'], 'featured');
            if ($unsplash_image) {
                return $unsplash_image;
            }
        }
        
        // Fallback to AI generation for specific medical topics
        if (!empty($this->openai_api_key)) {
            $ai_image = $this->generate_medical_illustration($keywords['primary']);
            if ($ai_image) {
                return $ai_image;
            }
        }
        
        return null;
    }
    
    /**
     * Get content images for article body
     */
    private function get_content_images($keywords, $count = 2) {
        $images = array();
        
        if (!empty($this->unsplash_access_key)) {
            // Get diverse images for different aspects
            foreach (array_slice($keywords['secondary'], 0, $count) as $keyword) {
                $image = $this->search_unsplash($keyword, 'content');
                if ($image) {
                    $images[] = $image;
                }
            }
        }
        
        return $images;
    }
    
    /**
     * Extract medical keywords from article
     */
    private function extract_medical_keywords($headline, $category, $content) {
        $all_text = $headline . ' ' . $category . ' ' . $content;
        $all_text = strtolower($all_text);
        
        // Medical keyword categories
        $medical_categories = array(
            'cardiology' => array('heart', 'cardiac', 'cardiovascular', 'blood pressure', 'coronary', 'καρδιά', 'καρδιακός', 'αγγειακός'),
            'oncology' => array('cancer', 'tumor', 'oncology', 'chemotherapy', 'radiation', 'καρκίνος', 'όγκος', 'ογκολογία'),
            'neurology' => array('brain', 'neurology', 'alzheimer', 'parkinson', 'stroke', 'εγκέφαλος', 'νευρολογία'),
            'diabetes' => array('diabetes', 'insulin', 'glucose', 'blood sugar', 'διαβήτης', 'ινσουλίνη', 'γλυκόζη'),
            'respiratory' => array('lung', 'respiratory', 'asthma', 'copd', 'breathing', 'πνεύμονας', 'αναπνευστικός'),
            'mental_health' => array('depression', 'anxiety', 'mental health', 'psychiatric', 'ψυχική υγεία', 'κατάθλιψη'),
            'orthopedic' => array('bone', 'joint', 'arthritis', 'osteoporosis', 'κόκαλο', 'άρθρωση'),
            'general' => array('medicine', 'health', 'medical', 'treatment', 'therapy', 'ιατρική', 'υγεία', 'θεραπεία')
        );
        
        // Find primary category
        $primary_keyword = 'medical research';
        $detected_category = 'general';
        
        foreach ($medical_categories as $cat => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($all_text, $keyword) !== false) {
                    $detected_category = $cat;
                    $primary_keyword = $keyword;
                    break 2;
                }
            }
        }
        
        // Generate search terms
        $search_terms = $this->generate_search_terms($detected_category, $primary_keyword);
        
        return array(
            'primary' => $search_terms['primary'],
            'secondary' => $search_terms['secondary'],
            'category' => $detected_category
        );
    }
    
    /**
     * Generate appropriate search terms for images
     */
    private function generate_search_terms($category, $primary_keyword) {
        $search_mapping = array(
            'cardiology' => array(
                'primary' => 'heart health medical illustration',
                'secondary' => array('stethoscope', 'ECG monitor', 'healthy lifestyle')
            ),
            'oncology' => array(
                'primary' => 'medical research laboratory',
                'secondary' => array('microscope', 'medical test tubes', 'laboratory equipment')
            ),
            'neurology' => array(
                'primary' => 'brain scan medical',
                'secondary' => array('medical imaging', 'doctor consultation', 'neuroscience')
            ),
            'diabetes' => array(
                'primary' => 'diabetes medical care',
                'secondary' => array('healthy food', 'medical check up', 'blood glucose meter')
            ),
            'respiratory' => array(
                'primary' => 'respiratory health',
                'secondary' => array('doctor with stethoscope', 'medical examination', 'lung health')
            ),
            'mental_health' => array(
                'primary' => 'mental health support',
                'secondary' => array('therapy session', 'meditation wellness', 'mental wellbeing')
            ),
            'orthopedic' => array(
                'primary' => 'orthopedic medical care',
                'secondary' => array('x-ray medical', 'physical therapy', 'joint health')
            ),
            'general' => array(
                'primary' => 'medical research',
                'secondary' => array('doctor consultation', 'medical equipment', 'healthcare professionals')
            )
        );
        
        return $search_mapping[$category] ?? $search_mapping['general'];
    }
    
    /**
     * Search Unsplash for medical images
     */
    private function search_unsplash($query, $type = 'featured') {
        if (empty($this->unsplash_access_key)) {
            return null;
        }
        
        $orientation = ($type === 'featured') ? 'landscape' : 'any';
        $url = 'https://api.unsplash.com/search/photos';
        
        $params = array(
            'query' => $query,
            'per_page' => 5,
            'orientation' => $orientation,
            'content_filter' => 'high',
            'order_by' => 'relevant'
        );
        
        $headers = array(
            'Authorization' => 'Client-ID ' . $this->unsplash_access_key,
            'Accept-Version' => 'v1'
        );
        
        $response = wp_remote_get($url . '?' . http_build_query($params), array(
            'headers' => $headers,
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return null;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!isset($data['results']) || empty($data['results'])) {
            return null;
        }
        
        // Select appropriate image
        $selected_image = $this->select_appropriate_image($data['results'], $type);
        
        if ($selected_image) {
            return $this->process_unsplash_image($selected_image);
        }
        
        return null;
    }
    
    /**
     * Select appropriate image from results
     */
    private function select_appropriate_image($images, $type) {
        // Filter out images with people in medical context (avoid privacy issues)
        $filtered_images = array();
        
        foreach ($images as $image) {
            $description = strtolower($image['description'] ?? '');
            $alt_description = strtolower($image['alt_description'] ?? '');
            
            // Avoid images with people's faces or personal medical situations
            $avoid_keywords = array('patient', 'person', 'man', 'woman', 'face', 'people', 'individual');
            $has_people = false;
            
            foreach ($avoid_keywords as $keyword) {
                if (strpos($description, $keyword) !== false || strpos($alt_description, $keyword) !== false) {
                    $has_people = true;
                    break;
                }
            }
            
            if (!$has_people) {
                $filtered_images[] = $image;
            }
        }
        
        // Return first suitable image or fallback to original first image
        return !empty($filtered_images) ? $filtered_images[0] : $images[0];
    }
    
    /**
     * Process Unsplash image data
     */
    private function process_unsplash_image($image_data) {
        return array(
            'url' => $image_data['urls']['regular'],
            'url_small' => $image_data['urls']['small'],
            'url_thumb' => $image_data['urls']['thumb'],
            'alt_text' => $image_data['alt_description'] ?? 'Medical illustration',
            'credit' => $image_data['user']['name'] ?? 'Unknown',
            'credit_url' => $image_data['user']['links']['html'] ?? '',
            'source' => 'unsplash',
            'width' => $image_data['width'],
            'height' => $image_data['height'],
            'download_location' => $image_data['links']['download_location'] ?? ''
        );
    }
    
    /**
     * Generate medical illustration using DALL-E
     */
    private function generate_medical_illustration($topic) {
        if (empty($this->openai_api_key)) {
            return null;
        }
        
        // Create appropriate prompt for medical illustration
        $prompt = $this->create_illustration_prompt($topic);
        
        $headers = array(
            'Authorization' => 'Bearer ' . $this->openai_api_key,
            'Content-Type' => 'application/json'
        );
        
        $body = array(
            'model' => 'dall-e-3',
            'prompt' => $prompt,
            'n' => 1,
            'size' => '1024x1024',
            'quality' => 'standard',
            'style' => 'natural'
        );
        
        $response = wp_remote_post('https://api.openai.com/v1/images/generations', array(
            'headers' => $headers,
            'body' => wp_json_encode($body),
            'timeout' => 60
        ));
        
        if (is_wp_error($response)) {
            return null;
        }
        
        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);
        
        if (isset($data['data'][0]['url'])) {
            return array(
                'url' => $data['data'][0]['url'],
                'alt_text' => 'AI-generated medical illustration',
                'source' => 'dall-e',
                'width' => 1024,
                'height' => 1024
            );
        }
        
        return null;
    }
    
    /**
     * Create appropriate illustration prompt
     */
    private function create_illustration_prompt($topic) {
        $base_prompt = "A professional medical illustration showing ";
        
        $topic_prompts = array(
            'heart' => "anatomical heart diagram with clean medical style, no people",
            'brain' => "brain anatomy illustration in medical textbook style, no people", 
            'diabetes' => "medical equipment for diabetes care, glucose meter and supplies, no people",
            'cancer' => "medical research laboratory with microscopes and test equipment, no people",
            'lung' => "respiratory system anatomical illustration, medical diagram style, no people",
            'medical research' => "modern medical laboratory with equipment and charts, no people visible"
        );
        
        $specific_prompt = $topic_prompts[$topic] ?? $topic_prompts['medical research'];
        
        return $base_prompt . $specific_prompt . ". Clean, professional, medical illustration style. No faces or identifiable people.";
    }
    
    /**
     * Download and attach image to WordPress
     */
    public function attach_image_to_post($image_data, $post_id, $is_featured = false) {
        if (!$image_data || !isset($image_data['url'])) {
            return false;
        }
        
        // Download image
        $image_url = $image_data['url'];
        $image_response = wp_remote_get($image_url, array('timeout' => 30));
        
        if (is_wp_error($image_response)) {
            return false;
        }
        
        $image_body = wp_remote_retrieve_body($image_response);
        
        if (empty($image_body)) {
            return false;
        }
        
        // Generate filename
        $filename = 'medical-' . $post_id . '-' . uniqid() . '.jpg';
        
        // Upload to WordPress
        $upload = wp_upload_bits($filename, null, $image_body);
        
        if ($upload['error']) {
            return false;
        }
        
        // Create attachment
        $attachment_data = array(
            'post_mime_type' => 'image/jpeg',
            'post_title' => $image_data['alt_text'],
            'post_content' => '',
            'post_status' => 'inherit',
            'post_parent' => $post_id
        );
        
        $attachment_id = wp_insert_attachment($attachment_data, $upload['file'], $post_id);
        
        if (!$attachment_id) {
            return false;
        }
        
        // Generate metadata
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attachment_metadata = wp_generate_attachment_metadata($attachment_id, $upload['file']);
        wp_update_attachment_metadata($attachment_id, $attachment_metadata);
        
        // Set alt text
        update_post_meta($attachment_id, '_wp_attachment_image_alt', $image_data['alt_text']);
        
        // Add image credit if from Unsplash
        if ($image_data['source'] === 'unsplash' && !empty($image_data['credit'])) {
            update_post_meta($attachment_id, 'mna_image_credit', $image_data['credit']);
            update_post_meta($attachment_id, 'mna_image_source', 'Unsplash');
            
            // Track Unsplash download for analytics
            $this->track_unsplash_download($image_data);
        }
        
        // Set as featured image if requested
        if ($is_featured) {
            set_post_thumbnail($post_id, $attachment_id);
        }
        
        return $attachment_id;
    }
    
    /**
     * Track Unsplash download for analytics
     */
    private function track_unsplash_download($image_data) {
        if (!empty($image_data['download_location'])) {
            wp_remote_get($image_data['download_location'], array(
                'headers' => array(
                    'Authorization' => 'Client-ID ' . $this->unsplash_access_key
                ),
                'timeout' => 10
            ));
        }
    }
    
    /**
     * Test image service connections
     */
    public function test_connections() {
        $results = array();
        
        // Test Unsplash
        if (!empty($this->unsplash_access_key)) {
            $results['unsplash'] = $this->test_unsplash_connection();
        } else {
            $results['unsplash'] = array(
                'success' => false,
                'message' => 'Unsplash API key not configured'
            );
        }
        
        // Test DALL-E
        if (!empty($this->openai_api_key)) {
            $results['dalle'] = array(
                'success' => true,
                'message' => 'DALL-E available (OpenAI key configured)'
            );
        } else {
            $results['dalle'] = array(
                'success' => false,
                'message' => 'DALL-E not available (OpenAI key required)'
            );
        }
        
        return $results;
    }
    
    /**
     * Test Unsplash API connection
     */
    private function test_unsplash_connection() {
        $url = 'https://api.unsplash.com/photos/random';
        
        $params = array(
            'count' => 1,
            'query' => 'medical',
            'content_filter' => 'high'
        );
        
        $headers = array(
            'Authorization' => 'Client-ID ' . $this->unsplash_access_key,
            'Accept-Version' => 'v1'
        );
        
        $response = wp_remote_get($url . '?' . http_build_query($params), array(
            'headers' => $headers,
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => 'Connection error: ' . $response->get_error_message()
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code === 200) {
            $data = json_decode($response_body, true);
            if (is_array($data) && !empty($data)) {
                return array(
                    'success' => true,
                    'message' => 'Unsplash API connection successful'
                );
            }
        }
        
        // Parse error from response
        $error_data = json_decode($response_body, true);
        $error_message = 'API test failed';
        
        if (isset($error_data['errors']) && is_array($error_data['errors'])) {
            $error_message = implode(', ', $error_data['errors']);
        } elseif (isset($error_data['error'])) {
            $error_message = $error_data['error'];
        } elseif ($response_code === 401) {
            $error_message = 'Invalid API key or unauthorized access';
        } elseif ($response_code === 403) {
            $error_message = 'API key does not have required permissions';
        }
        
        return array(
            'success' => false,
            'message' => "HTTP {$response_code}: {$error_message}"
        );
    }
}