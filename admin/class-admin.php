<?php
/**
 * Admin interface class
 */

if (!defined('ABSPATH')) {
    exit;
}

class MNA_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_mna_process_headline', array($this, 'ajax_process_headline'));
        add_action('wp_ajax_mna_approve_article', array($this, 'ajax_approve_article'));
        add_action('admin_notices', array($this, 'admin_notices'));
    }
    
    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            __('Medical News Automation', 'medical-news-automation'),
            __('News Automation', 'medical-news-automation'),
            'manage_options',
            'medical-news-automation',
            array($this, 'dashboard_page'),
            'dashicons-rss',
            30
        );
        
        // Headlines queue submenu
        add_submenu_page(
            'medical-news-automation',
            __('Headlines Queue', 'medical-news-automation'),
            __('Headlines Queue', 'medical-news-automation'),
            'edit_posts',
            'mna-headlines',
            array($this, 'headlines_page')
        );
        
        // Article review submenu
        add_submenu_page(
            'medical-news-automation',
            __('Article Review', 'medical-news-automation'),
            __('Article Review', 'medical-news-automation'),
            'edit_posts',
            'mna-review',
            array($this, 'review_page')
        );
        
        // Settings submenu
        add_submenu_page(
            'medical-news-automation',
            __('Settings', 'medical-news-automation'),
            __('Settings', 'medical-news-automation'),
            'manage_options',
            'mna-settings',
            array($this, 'settings_page')
        );
        
        // Analytics submenu
        add_submenu_page(
            'medical-news-automation',
            __('Analytics', 'medical-news-automation'),
            __('Analytics', 'medical-news-automation'),
            'manage_options',
            'mna-analytics',
            array($this, 'analytics_page')
        );
    }
    
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'medical-news-automation') !== false || strpos($hook, 'mna-') !== false) {
            wp_enqueue_style('mna-admin-style', MNA_PLUGIN_URL . 'admin/css/admin.css', array(), MNA_VERSION);
            wp_enqueue_script('mna-admin-script', MNA_PLUGIN_URL . 'admin/js/admin.js', array('jquery'), MNA_VERSION, true);
            
            wp_localize_script('mna-admin-script', 'mna_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mna_nonce'),
                'processing_text' => __('Processing...', 'medical-news-automation'),
                'success_text' => __('Success!', 'medical-news-automation'),
                'error_text' => __('Error occurred', 'medical-news-automation')
            ));
        }
    }
    
    public function dashboard_page() {
        // Get dashboard statistics
        global $wpdb;
        
        $headlines_table = $wpdb->prefix . 'mna_headlines';
        $articles_table = $wpdb->prefix . 'mna_articles';
        
        $stats = array(
            'pending_headlines' => $wpdb->get_var("SELECT COUNT(*) FROM $headlines_table WHERE status = 'pending'"),
            'processing_headlines' => $wpdb->get_var("SELECT COUNT(*) FROM $headlines_table WHERE status IN ('processing', 'researched')"),
            'articles_for_review' => $wpdb->get_var("SELECT COUNT(*) FROM $articles_table WHERE status IN ('draft', 'under_review')"),
            'published_today' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $articles_table WHERE status = 'published' AND DATE(published_at) = %s",
                current_time('Y-m-d')
            ))
        );
        
        // Get recent activity
        $recent_headlines = MNA_Database::get_headlines_by_status('pending', 5);
        $recent_articles = MNA_Database::get_articles_for_review(5);
        
        include MNA_PLUGIN_PATH . 'admin/partials/dashboard.php';
    }
    
    public function headlines_page() {
        // Handle form submissions
        if (isset($_POST['submit_headline']) && wp_verify_nonce($_POST['mna_nonce'], 'add_headline')) {
            $headline = sanitize_textarea_field($_POST['headline']);
            $priority = intval($_POST['priority']);
            $category = sanitize_text_field($_POST['category']);
            
            if (MNA_Database::insert_headline($headline, 'manual', $priority, $category)) {
                $this->add_admin_notice('Headline added successfully!', 'success');
            } else {
                $this->add_admin_notice('Error adding headline.', 'error');
            }
        }
        
        // Handle bulk import
        if (isset($_POST['import_headlines']) && wp_verify_nonce($_POST['mna_nonce'], 'import_headlines')) {
            $headlines_text = sanitize_textarea_field($_POST['headlines_bulk']);
            $headlines = array_filter(array_map('trim', explode("\n", $headlines_text)));
            
            $imported = 0;
            foreach ($headlines as $headline) {
                if (MNA_Database::insert_headline($headline, 'bulk_import')) {
                    $imported++;
                }
            }
            
            $this->add_admin_notice("Imported $imported headlines successfully!", 'success');
        }
        
        // Get headlines for display
        $all_headlines = $this->get_all_headlines_for_admin();
        
        include MNA_PLUGIN_PATH . 'admin/partials/headlines.php';
    }
    
    public function review_page() {
        // Get articles for review
        $articles = MNA_Database::get_articles_for_review(20);
        
        include MNA_PLUGIN_PATH . 'admin/partials/review.php';
    }
    
    public function settings_page() {
        // Handle settings form submission
        if (isset($_POST['submit_settings']) && wp_verify_nonce($_POST['mna_nonce'], 'save_settings')) {
            $settings = array(
                'perplexity_api_key' => sanitize_text_field($_POST['perplexity_api_key']),
                'openai_api_key' => sanitize_text_field($_POST['openai_api_key']),
                'claude_api_key' => sanitize_text_field($_POST['claude_api_key']),
                'preferred_llm' => sanitize_text_field($_POST['preferred_llm']),
                'auto_process' => isset($_POST['auto_process']),
                'email_notifications' => isset($_POST['email_notifications']),
                'batch_size' => intval($_POST['batch_size'])
            );
            
            foreach ($settings as $key => $value) {
                update_option('mna_' . $key, $value);
            }
            
            $this->add_admin_notice('Settings saved successfully!', 'success');
        }
        
        // Get current settings
        $current_settings = array(
            'perplexity_api_key' => get_option('mna_perplexity_api_key', ''),
            'openai_api_key' => get_option('mna_openai_api_key', ''),
            'claude_api_key' => get_option('mna_claude_api_key', ''),
            'preferred_llm' => get_option('mna_preferred_llm', 'openai'),
            'auto_process' => get_option('mna_auto_process', false),
            'email_notifications' => get_option('mna_email_notifications', true),
            'batch_size' => get_option('mna_batch_size', 5)
        );
        
        include MNA_PLUGIN_PATH . 'admin/partials/settings.php';
    }
    
    public function analytics_page() {
        global $wpdb;
        
        // Get analytics data
        $headlines_table = $wpdb->prefix . 'mna_headlines';
        $articles_table = $wpdb->prefix . 'mna_articles';
        $logs_table = $wpdb->prefix . 'mna_logs';
        
        // Daily stats for the last 30 days
        $daily_stats = $wpdb->get_results("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as headlines_added
            FROM $headlines_table 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date DESC
        ");
        
        // Processing success rates
        $processing_stats = $wpdb->get_results("
            SELECT 
                process_type,
                status,
                COUNT(*) as count
            FROM $logs_table 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY process_type, status
        ");
        
        include MNA_PLUGIN_PATH . 'admin/partials/analytics.php';
    }
    
    // AJAX handlers
    public function ajax_process_headline() {
        check_ajax_referer('mna_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        $headline_id = intval($_POST['headline_id']);
        
        // Process the headline
        $processor = new MNA_Headline_Processor();
        $result = $processor->process_single_headline($headline_id);
        
        if ($result['success']) {
            wp_send_json_success(array(
                'message' => __('Headline processed successfully!', 'medical-news-automation'),
                'article_id' => $result['article_id']
            ));
        } else {
            wp_send_json_error(array(
                'message' => $result['message']
            ));
        }
    }
    
    public function ajax_approve_article() {
        check_ajax_referer('mna_nonce', 'nonce');
        
        if (!current_user_can('publish_posts')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        $article_id = intval($_POST['article_id']);
        $action = sanitize_text_field($_POST['action_type']); // approve, reject, or publish
        $notes = sanitize_textarea_field($_POST['notes']);
        
        // Handle the approval/rejection
        $workflow = new MNA_Workflow_Manager();
        $result = $workflow->handle_article_review($article_id, $action, $notes);
        
        if ($result['success']) {
            wp_send_json_success(array(
                'message' => $result['message']
            ));
        } else {
            wp_send_json_error(array(
                'message' => $result['message']
            ));
        }
    }
    
    // Helper methods
    private function get_all_headlines_for_admin() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'mna_headlines';
        
        return $wpdb->get_results("
            SELECT * FROM $table 
            ORDER BY 
                CASE status 
                    WHEN 'pending' THEN 1 
                    WHEN 'processing' THEN 2 
                    WHEN 'researched' THEN 3 
                    WHEN 'generated' THEN 4 
                    ELSE 5 
                END,
                priority DESC, 
                created_at DESC 
            LIMIT 50
        ");
    }
    
    private function add_admin_notice($message, $type = 'info') {
        set_transient('mna_admin_notice', array(
            'message' => $message,
            'type' => $type
        ), 30);
    }
    
    public function admin_notices() {
        $notice = get_transient('mna_admin_notice');
        if ($notice) {
            echo '<div class="notice notice-' . esc_attr($notice['type']) . ' is-dismissible">';
            echo '<p>' . esc_html($notice['message']) . '</p>';
            echo '</div>';
            delete_transient('mna_admin_notice');
        }
    }
}