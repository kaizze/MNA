<?php
/**
 * LLM service for article generation
 */

if (!defined('ABSPATH')) {
    exit;
}

class MNA_LLM_Service {
    
    private $openai_api_key;
    private $claude_api_key;
    private $preferred_llm;
    
    public function __construct() {
        $this->openai_api_key = get_option('mna_openai_api_key', '');
        $this->claude_api_key = get_option('mna_claude_api_key', '');
        $this->preferred_llm = get_option('mna_preferred_llm', 'openai');
    }
    
    /**
     * Generate article from headline and research data
     */
    public function generate_article($headline, $research_data, $sources) {
        $start_time = microtime(true);
        
        // Choose which LLM to use
        $llm_to_use = $this->determine_llm_to_use();
        
        if (!$llm_to_use) {
            return array(
                'success' => false,
                'error' => 'No LLM API keys configured'
            );
        }
        
        // Generate the article
        switch ($llm_to_use) {
            case 'openai':
                $result = $this->generate_with_openai($headline, $research_data, $sources);
                break;
            case 'claude':
                $result = $this->generate_with_claude($headline, $research_data, $sources);
                break;
            default:
                return array(
                    'success' => false,
                    'error' => 'Unknown LLM service'
                );
        }
        
        $execution_time = microtime(true) - $start_time;
        
        if ($result['success']) {
            // Log successful generation
            MNA_Database::log_activity(
                null,
                'llm',
                'completed',
                'Article generated successfully with ' . $llm_to_use,
                $execution_time,
                $result['tokens_used'] ?? null
            );
            
            $result['llm_used'] = $llm_to_use;
            $result['execution_time'] = $execution_time;
        } else {
            // Log failed generation
            MNA_Database::log_activity(
                null,
                'llm',
                'failed',
                'Article generation failed: ' . $result['error'],
                $execution_time
            );
        }
        
        return $result;
    }
    
    /**
     * Generate article using OpenAI GPT
     */
    private function generate_with_openai($headline, $research_data, $sources) {
        if (empty($this->openai_api_key)) {
            return array(
                'success' => false,
                'error' => 'OpenAI API key not configured'
            );
        }
        
        $prompt = $this->build_article_prompt($headline, $research_data, $sources);
        
        $headers = array(
            'Authorization' => 'Bearer ' . $this->openai_api_key,
            'Content-Type' => 'application/json'
        );
        
        $body = array(
            'model' => 'gpt-4o',
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => $this->get_system_prompt()
                ),
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'max_tokens' => 2500,
            'temperature' => 0.3,
            'presence_penalty' => 0.1,
            'frequency_penalty' => 0.1
        );
        
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
            'headers' => $headers,
            'body' => wp_json_encode($body),
            'timeout' => 90
        ));
        
        return $this->process_api_response($response, 'openai');
    }
    
    /**
     * Generate article using Claude
     */
    private function generate_with_claude($headline, $research_data, $sources) {
        if (empty($this->claude_api_key)) {
            return array(
                'success' => false,
                'error' => 'Claude API key not configured'
            );
        }
        
        $prompt = $this->build_article_prompt($headline, $research_data, $sources);
        
        $headers = array(
            'x-api-key' => $this->claude_api_key,
            'Content-Type' => 'application/json',
            'anthropic-version' => '2023-06-01'
        );
        
        $body = array(
            'model' => 'claude-3-sonnet-20240229',
            'max_tokens' => 2500,
            'temperature' => 0.3,
            'system' => $this->get_system_prompt(),
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            )
        );
        
        $response = wp_remote_post('https://api.anthropic.com/v1/messages', array(
            'headers' => $headers,
            'body' => wp_json_encode($body),
            'timeout' => 90
        ));
        
        return $this->process_api_response($response, 'claude');
    }
    
    /**
     * Build article generation prompt in Greek
     */
    private function build_article_prompt($headline, $research_data, $sources) {
        $sources_text = $this->format_sources_for_prompt($sources);
        
        $prompt = "Δημιούργησε ένα περιεκτικό άρθρο ιατρικών ειδήσεων στα ελληνικά βασισμένο στις παρακάτω πληροφορίες:

**ΤΙΤΛΟΣ:** {$headline}

**ΔΕΔΟΜΕΝΑ ΕΡΕΥΝΑΣ:**
{$research_data}

**ΠΗΓΕΣ ΠΡΟΣ ΠΑΡΑΠΟΜΠΗ:**
{$sources_text}

**ΑΠΑΙΤΗΣΕΙΣ:**
1. Γράψε ένα πλήρες άρθρο ειδήσεων (500-800 λέξεις) στα ελληνικά
2. Χρησιμοποίησε έναν ελκυστικό αλλά επαγγελματικό τόνο κατάλληλο για γενικούς αναγνώστες
3. Συμπεριέλαβε σωστές παραπομπές χρησιμοποιώντας τη μορφή [Source: URL] μετά από σχετικές δηλώσεις
4. Δόμησε με σαφή τίτλο, εισαγωγική παράγραφο, κύριες παραγράφους και συμπέρασμα
5. Συμπεριέλαβε σχετικό ιατρικό πλαίσιο και πληροφορίες υπόβαθρου
6. Εξήγησε τεχνικούς όρους για το γενικό κοινό
7. Διατήρησε δημοσιογραφική αντικειμενικότητα και ακρίβεια
8. Χρησιμοποίησε μόνο πληροφορίες από τις παρεχόμενες έρευνες και πηγές
9. Πρόσθεσε μια σύντομη αποποίηση σχετικά με τη συμβουλή επαγγελματιών υγείας

**ΔΟΜΗ ΑΡΘΡΟΥ:**
- Ελκυστικός τίτλος (αν διαφέρει από τον παρεχόμενο)
- Εισαγωγική παράγραφος με βασικές πληροφορίες (ποιος, τι, πότε, πού, γιατί)
- 3-4 κύριες παράγραφοι με λεπτομέρειες, πλαίσιο και γνώμες ειδικών
- Συμπέρασμα με επιπτώσεις ή επόμενα βήματα
- Ιατρική αποποίηση

Δημιούργησε το άρθρο τώρα στα ελληνικά:";

        return $prompt;
    }
    
    /**
     * Get system prompt for Greek medical journalism
     */
    private function get_system_prompt() {
        return "Είσαι έμπειρος ιατρικός δημοσιογράφος που ειδικεύεται στη δημιουργία ακριβών, ελκυστικών και προσβάσιμων άρθρων υγείας στα ελληνικά. Η γραφή σου πρέπει να είναι:

- Επιστημονικά ακριβής και βασισμένη σε αποδείξεις
- Προσβάσιμη σε γενικούς αναγνώστες χωρίς ιατρικό υπόβαθρο
- Σωστά παραπεμπόμενη με αναφορές πηγών
- Αντικειμενική και αμερόληπτη
- Ακολουθεί τις τυπικές δημοσιογραφικές πρακτικές
- Συμμορφώνεται με την ιατρική δημοσιογραφική δεοντολογία
- Σαφής σχετικά με τους περιορισμούς και τις αβεβαιότητες στην ιατρική έρευνα

Πάντα να συμπεριλαμβάνεις σωστές παραπομπές και να διατηρείς τα υψηλότερα πρότυπα της ιατρικής δημοσιογραφίας. Μην κάνεις ισχυρισμούς πέρα από αυτό που υποστηρίζει το υλικό των πηγών. Γράφε πάντα στα ελληνικά με σωστή γραμματική και σύνταξη.";
    }
    
    /**
     * Format sources for prompt
     */
    private function format_sources_for_prompt($sources) {
        $formatted = array();
        
        foreach ($sources as $index => $source) {
            $source_text = ($index + 1) . ". ";
            $source_text .= !empty($source['title']) ? $source['title'] . " - " : "";
            $source_text .= $source['url'];
            $source_text .= !empty($source['domain']) ? " ({$source['domain']})" : "";
            $source_text .= !empty($source['snippet']) ? "\n   Excerpt: " . substr($source['snippet'], 0, 200) . "..." : "";
            
            $formatted[] = $source_text;
        }
        
        return implode("\n\n", $formatted);
    }
    
    /**
     * Process API response from either service
     */
    private function process_api_response($response, $service) {
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
            $error_message = 'API request failed';
            
            if ($service === 'openai' && isset($error_data['error']['message'])) {
                $error_message = $error_data['error']['message'];
            } elseif ($service === 'claude' && isset($error_data['error']['message'])) {
                $error_message = $error_data['error']['message'];
            }
            
            return array(
                'success' => false,
                'error' => $error_message
            );
        }
        
        $data = json_decode($response_body, true);
        
        if (!$data) {
            return array(
                'success' => false,
                'error' => 'Invalid API response format'
            );
        }
        
        // Extract content based on service
        $content = '';
        $tokens_used = 0;
        
        if ($service === 'openai') {
            if (isset($data['choices'][0]['message']['content'])) {
                $content = $data['choices'][0]['message']['content'];
                $tokens_used = $data['usage']['total_tokens'] ?? 0;
            }
        } elseif ($service === 'claude') {
            if (isset($data['content'][0]['text'])) {
                $content = $data['content'][0]['text'];
                $tokens_used = $data['usage']['output_tokens'] ?? 0;
            }
        }
        
        if (empty($content)) {
            return array(
                'success' => false,
                'error' => 'No content generated by API'
            );
        }
        
        // Calculate quality score
        $quality_score = $this->calculate_content_quality($content);
        
        return array(
            'success' => true,
            'content' => $content,
            'tokens_used' => $tokens_used,
            'quality_score' => $quality_score
        );
    }
    
    /**
     * Calculate content quality score
     */
    private function calculate_content_quality($content) {
        $score = 5; // Base score
        
        // Check word count (ideal range: 500-800 words)
        $word_count = str_word_count($content);
        if ($word_count >= 500 && $word_count <= 800) {
            $score += 2;
        } elseif ($word_count >= 300) {
            $score += 1;
        }
        
        // Check for citations
        $citation_count = preg_match_all('/\[Source:.*?\]/i', $content);
        if ($citation_count > 0) {
            $score += min($citation_count, 3); // Max 3 points for citations
        }
        
        // Check for medical disclaimer
        if (stripos($content, 'consult') !== false && stripos($content, 'healthcare') !== false) {
            $score += 1;
        }
        
        // Check structure (paragraphs)
        $paragraph_count = substr_count($content, "\n\n") + 1;
        if ($paragraph_count >= 3) {
            $score += 1;
        }
        
        return min($score, 10); // Cap at 10
    }
    
    /**
     * Determine which LLM to use
     */
    private function determine_llm_to_use() {
        // Check preferred LLM first
        if ($this->preferred_llm === 'openai' && !empty($this->openai_api_key)) {
            return 'openai';
        } elseif ($this->preferred_llm === 'claude' && !empty($this->claude_api_key)) {
            return 'claude';
        }
        
        // Fallback to any available LLM
        if (!empty($this->openai_api_key)) {
            return 'openai';
        } elseif (!empty($this->claude_api_key)) {
            return 'claude';
        }
        
        return false;
    }
    
    /**
     * Test LLM connection
     */
    public function test_connection($service = null) {
        $service = $service ?: $this->preferred_llm;
        
        $test_result = array();
        
        if ($service === 'openai' || $service === 'all') {
            $test_result['openai'] = $this->test_openai_connection();
        }
        
        if ($service === 'claude' || $service === 'all') {
            $test_result['claude'] = $this->test_claude_connection();
        }
        
        return $test_result;
    }
    
    private function test_openai_connection() {
        if (empty($this->openai_api_key)) {
            return array('success' => false, 'error' => 'API key not configured');
        }
        
        $result = $this->generate_with_openai(
            'Test Connection', 
            'This is a test to verify API connectivity.', 
            array()
        );
        
        return $result;
    }
    
    private function test_claude_connection() {
        if (empty($this->claude_api_key)) {
            return array('success' => false, 'error' => 'API key not configured');
        }
        
        $result = $this->generate_with_claude(
            'Test Connection', 
            'This is a test to verify API connectivity.', 
            array()
        );
        
        return $result;
    }
}