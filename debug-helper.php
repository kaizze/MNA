<?php
/**
 * Debug Helper - Add this temporarily to debug the issue
 * Place this in your plugin root directory
 */

if (!defined('ABSPATH')) {
    exit;
}

// Add debug function to check database
function mna_debug_database() {
    global $wpdb;
    
    echo "<h2>MNA Database Debug</h2>";
    
    // Check headlines
    $headlines_table = $wpdb->prefix . 'mna_headlines';
    $headlines = $wpdb->get_results("SELECT * FROM $headlines_table ORDER BY id DESC LIMIT 5");
    echo "<h3>Recent Headlines:</h3>";
    echo "<pre>" . print_r($headlines, true) . "</pre>";
    
    // Check articles
    $articles_table = $wpdb->prefix . 'mna_articles';
    $articles = $wpdb->get_results("SELECT * FROM $articles_table ORDER BY id DESC LIMIT 5");
    echo "<h3>Recent Articles:</h3>";
    echo "<pre>" . print_r($articles, true) . "</pre>";
    
    // Check research
    $research_table = $wpdb->prefix . 'mna_research';
    $research = $wpdb->get_results("SELECT * FROM $research_table ORDER BY id DESC LIMIT 5");
    echo "<h3>Recent Research:</h3>";
    echo "<pre>" . print_r($research, true) . "</pre>";
    
    // Test the review query specifically
    echo "<h3>Review Query Test:</h3>";
    $review_articles = MNA_Database::get_articles_for_review(20);
    echo "Count: " . count($review_articles) . "<br>";
    echo "<pre>" . print_r($review_articles, true) . "</pre>";
    
    // Show raw SQL for debugging
    $query = "SELECT a.*, h.headline, h.category, r.sources_json 
              FROM {$articles_table} a 
              LEFT JOIN {$headlines_table} h ON a.headline_id = h.id 
              LEFT JOIN {$research_table} r ON a.research_id = r.id 
              WHERE a.status IN ('draft', 'under_review') 
              ORDER BY a.created_at DESC 
              LIMIT 20";
    echo "<h3>SQL Query:</h3>";
    echo "<pre>" . $query . "</pre>";
    
    $manual_result = $wpdb->get_results($query);
    echo "<h3>Manual Query Result:</h3>";
    echo "Count: " . count($manual_result) . "<br>";
    echo "<pre>" . print_r($manual_result, true) . "</pre>";
}

// Add this to WordPress admin (temporary)
add_action('wp_dashboard_setup', function() {
    if (current_user_can('manage_options') && isset($_GET['mna_debug'])) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-info" style="max-height: 400px; overflow: auto;">';
            mna_debug_database();
            echo '</div>';
        });
    }
});
?>