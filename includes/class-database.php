<?php
/**
 * Database management class
 */

if (!defined('ABSPATH')) {
    exit;
}

class MNA_Database {
    
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Headlines table
        $headlines_table = $wpdb->prefix . 'mna_headlines';
        $headlines_sql = "CREATE TABLE $headlines_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            headline text NOT NULL,
            source_feed varchar(255) DEFAULT NULL,
            status enum('pending','processing','researched','generated','published','failed') DEFAULT 'pending',
            priority int(3) DEFAULT 5,
            category varchar(100) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            processed_at datetime DEFAULT NULL,
            notes text DEFAULT NULL,
            PRIMARY KEY (id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Research data table
        $research_table = $wpdb->prefix . 'mna_research';
        $research_sql = "CREATE TABLE $research_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            headline_id int(11) NOT NULL,
            perplexity_query text NOT NULL,
            perplexity_response longtext NOT NULL,
            sources_json longtext NOT NULL,
            research_quality_score int(3) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY headline_id (headline_id),
            FOREIGN KEY (headline_id) REFERENCES $headlines_table(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // Generated articles table
        $articles_table = $wpdb->prefix . 'mna_articles';
        $articles_sql = "CREATE TABLE $articles_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            headline_id int(11) NOT NULL,
            research_id int(11) NOT NULL,
            generated_content longtext NOT NULL,
            llm_used varchar(50) NOT NULL,
            content_quality_score int(3) DEFAULT NULL,
            wordpress_post_id int(11) DEFAULT NULL,
            status enum('draft','under_review','approved','published','rejected') DEFAULT 'draft',
            journalist_id int(11) DEFAULT NULL,
            journalist_notes text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            reviewed_at datetime DEFAULT NULL,
            published_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY headline_id (headline_id),
            KEY research_id (research_id),
            KEY status (status),
            KEY wordpress_post_id (wordpress_post_id),
            FOREIGN KEY (headline_id) REFERENCES $headlines_table(id) ON DELETE CASCADE,
            FOREIGN KEY (research_id) REFERENCES $research_table(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // Sources credibility table
        $sources_table = $wpdb->prefix . 'mna_sources';
        $sources_sql = "CREATE TABLE $sources_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            url varchar(500) NOT NULL,
            domain varchar(255) NOT NULL,
            title varchar(500) DEFAULT NULL,
            credibility_score int(3) DEFAULT 5,
            last_verified datetime DEFAULT NULL,
            verification_notes text DEFAULT NULL,
            times_cited int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY url (url),
            KEY domain (domain),
            KEY credibility_score (credibility_score)
        ) $charset_collate;";
        
        // Processing logs table
        $logs_table = $wpdb->prefix . 'mna_logs';
        $logs_sql = "CREATE TABLE $logs_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            headline_id int(11) DEFAULT NULL,
            process_type enum('perplexity','llm','publish','error') NOT NULL,
            status enum('started','completed','failed') NOT NULL,
            message text DEFAULT NULL,
            execution_time decimal(8,3) DEFAULT NULL,
            api_tokens_used int(11) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY headline_id (headline_id),
            KEY process_type (process_type),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($headlines_sql);
        dbDelta($research_sql);
        dbDelta($articles_sql);
        dbDelta($sources_sql);
        dbDelta($logs_sql);
    }
    
    /**
     * Insert a new headline
     */
    public static function insert_headline($headline, $source_feed = null, $priority = 5, $category = null) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'mna_headlines';
        
        return $wpdb->insert(
            $table,
            array(
                'headline' => sanitize_text_field($headline),
                'source_feed' => sanitize_text_field($source_feed),
                'priority' => intval($priority),
                'category' => sanitize_text_field($category),
                'status' => 'pending'
            ),
            array('%s', '%s', '%d', '%s', '%s')
        );
    }
    
    /**
     * Get headlines by status
     */
    public static function get_headlines_by_status($status = 'pending', $limit = 10) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'mna_headlines';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE status = %s ORDER BY priority DESC, created_at ASC LIMIT %d",
            $status,
            $limit
        ));
    }
    
    /**
     * Update headline status
     */
    public static function update_headline_status($headline_id, $status, $notes = null) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'mna_headlines';
        
        $data = array(
            'status' => $status,
            'processed_at' => current_time('mysql')
        );
        
        if ($notes) {
            $data['notes'] = sanitize_textarea_field($notes);
        }
        
        return $wpdb->update(
            $table,
            $data,
            array('id' => intval($headline_id)),
            array('%s', '%s', '%s'),
            array('%d')
        );
    }
    
    /**
     * Insert research data
     */
    public static function insert_research($headline_id, $query, $response, $sources, $quality_score = null) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'mna_research';
        
        return $wpdb->insert(
            $table,
            array(
                'headline_id' => intval($headline_id),
                'perplexity_query' => sanitize_textarea_field($query),
                'perplexity_response' => wp_kses_post($response),
                'sources_json' => wp_json_encode($sources),
                'research_quality_score' => $quality_score ? intval($quality_score) : null
            ),
            array('%d', '%s', '%s', '%s', '%d')
        );
    }
    
    /**
     * Insert generated article
     */
    public static function insert_article($headline_id, $research_id, $content, $llm_used, $quality_score = null) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'mna_articles';
        
        return $wpdb->insert(
            $table,
            array(
                'headline_id' => intval($headline_id),
                'research_id' => intval($research_id),
                'generated_content' => wp_kses_post($content),
                'llm_used' => sanitize_text_field($llm_used),
                'content_quality_score' => $quality_score ? intval($quality_score) : null,
                'status' => 'draft'
            ),
            array('%d', '%d', '%s', '%s', '%d')
        );
    }
    
    /**
     * Get articles for review
     */
    public static function get_articles_for_review($limit = 10) {
        global $wpdb;
        
        $articles_table = $wpdb->prefix . 'mna_articles';
        $headlines_table = $wpdb->prefix . 'mna_headlines';
        $research_table = $wpdb->prefix . 'mna_research';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, h.headline, h.category, r.sources_json 
             FROM $articles_table a 
             LEFT JOIN $headlines_table h ON a.headline_id = h.id 
             LEFT JOIN $research_table r ON a.research_id = r.id 
             WHERE a.status IN ('draft', 'under_review') 
             ORDER BY a.created_at DESC 
             LIMIT %d",
            $limit
        ));
    }
    
    /**
     * Log processing activity
     */
    public static function log_activity($headline_id, $process_type, $status, $message = null, $execution_time = null, $tokens_used = null) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'mna_logs';
        
        return $wpdb->insert(
            $table,
            array(
                'headline_id' => $headline_id ? intval($headline_id) : null,
                'process_type' => sanitize_text_field($process_type),
                'status' => sanitize_text_field($status),
                'message' => $message ? sanitize_textarea_field($message) : null,
                'execution_time' => $execution_time ? floatval($execution_time) : null,
                'api_tokens_used' => $tokens_used ? intval($tokens_used) : null
            ),
            array('%d', '%s', '%s', '%s', '%f', '%d')
        );
    }
}