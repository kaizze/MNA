<?php
/**
 * Plugin Name: Medical News Automation
 * Plugin URI: https://yourwebsite.com/medical-news-automation
 * Description: Automates medical news article creation using headlines, Perplexity research, and AI content generation.
 * Version: 1.0.0
 * Author: mlnc
 * License: GPL v2 or later
 * Text Domain: medical-news-automation
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('MNA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MNA_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('MNA_VERSION', '1.0.0');

// Main plugin class
class MedicalNewsAutomation {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        // Load plugin textdomain
        load_plugin_textdomain('medical-news-automation', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Include required files
        $this->includes();
        
        // Initialize admin if in admin area
        if (is_admin()) {
            new MNA_Admin();
        }
        
        // Initialize API services
        new MNA_API_Manager();
        
        // Initialize workflow manager
        new MNA_Workflow_Manager();
        
        // Schedule cron jobs
        $this->schedule_events();
    }
    
    private function includes() {
        require_once MNA_PLUGIN_PATH . 'includes/class-database.php';
        require_once MNA_PLUGIN_PATH . 'includes/class-api-manager.php';
        require_once MNA_PLUGIN_PATH . 'includes/class-perplexity-api.php';
        require_once MNA_PLUGIN_PATH . 'includes/class-llm-service.php';
        require_once MNA_PLUGIN_PATH . 'includes/class-headline-processor.php';
        require_once MNA_PLUGIN_PATH . 'includes/class-workflow-manager.php';
        
        if (is_admin()) {
            require_once MNA_PLUGIN_PATH . 'admin/class-admin.php';
            require_once MNA_PLUGIN_PATH . 'admin/class-settings.php';
        }
    }
    
    public function activate() {
        // Load database class for activation
        require_once MNA_PLUGIN_PATH . 'includes/class-database.php';
        
        // Create database tables
        MNA_Database::create_tables();
        
        // Create default options
        $default_options = array(
            'perplexity_api_key' => '',
            'openai_api_key' => '',
            'claude_api_key' => '',
            'preferred_llm' => 'openai',
            'auto_process' => false,
            'email_notifications' => true,
            'batch_size' => 5
        );
        
        foreach ($default_options as $key => $value) {
            add_option('mna_' . $key, $value);
        }
        
        // Schedule cron events
        if (!wp_next_scheduled('mna_process_headlines')) {
            wp_schedule_event(time(), 'hourly', 'mna_process_headlines');
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('mna_process_headlines');
        flush_rewrite_rules();
    }
    
    private function schedule_events() {
        // Hook cron event to processing function
        add_action('mna_process_headlines', array('MNA_Workflow_Manager', 'batch_process_headlines'));
    }
}

// Initialize the plugin
new MedicalNewsAutomation();