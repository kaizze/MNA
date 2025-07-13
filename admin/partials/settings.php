<?php
/**
 * Settings page template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Medical News Automation Settings', 'medical-news-automation'); ?></h1>
    
    <form method="post" action="">
        <?php wp_nonce_field('save_settings', 'mna_nonce'); ?>
        
        <nav class="nav-tab-wrapper">
            <a href="#api-settings" class="nav-tab nav-tab-active"><?php _e('API Settings', 'medical-news-automation'); ?></a>
            <a href="#automation-settings" class="nav-tab"><?php _e('Automation', 'medical-news-automation'); ?></a>
            <a href="#notification-settings" class="nav-tab"><?php _e('Notifications', 'medical-news-automation'); ?></a>
            <a href="#advanced-settings" class="nav-tab"><?php _e('Advanced', 'medical-news-automation'); ?></a>
        </nav>
        
        <!-- API Settings Tab -->
        <div id="api-settings" class="tab-content active">
            <h2><?php _e('API Configuration', 'medical-news-automation'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="perplexity_api_key"><?php _e('Perplexity API Key', 'medical-news-automation'); ?></label>
                    </th>
                    <td>
                        <input type="password" id="perplexity_api_key" name="perplexity_api_key" 
                               value="<?php echo esc_attr($current_settings['perplexity_api_key']); ?>" 
                               class="regular-text" placeholder="pplx-...">
                        <button type="button" class="button button-secondary toggle-password" data-target="perplexity_api_key">
                            <?php _e('Show', 'medical-news-automation'); ?>
                        </button>
                        <button type="button" class="button button-secondary test-api" data-service="perplexity">
                            <?php _e('Test', 'medical-news-automation'); ?>
                        </button>
                        <p class="description">
                            <?php _e('Required for medical research. Get your API key from', 'medical-news-automation'); ?> 
                            <a href="https://www.perplexity.ai/" target="_blank">Perplexity.ai</a>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="openai_api_key"><?php _e('OpenAI API Key', 'medical-news-automation'); ?></label>
                    </th>
                    <td>
                        <input type="password" id="openai_api_key" name="openai_api_key" 
                               value="<?php echo esc_attr($current_settings['openai_api_key']); ?>" 
                               class="regular-text" placeholder="sk-...">
                        <button type="button" class="button button-secondary toggle-password" data-target="openai_api_key">
                            <?php _e('Show', 'medical-news-automation'); ?>
                        </button>
                        <button type="button" class="button button-secondary test-api" data-service="openai">
                            <?php _e('Test', 'medical-news-automation'); ?>
                        </button>
                        <p class="description">
                            <?php _e('For GPT-4 article generation. Get your API key from', 'medical-news-automation'); ?> 
                            <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI</a>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="claude_api_key"><?php _e('Anthropic Claude API Key', 'medical-news-automation'); ?></label>
                    </th>
                    <td>
                        <input type="password" id="claude_api_key" name="claude_api_key" 
                               value="<?php echo esc_attr($current_settings['claude_api_key']); ?>" 
                               class="regular-text" placeholder="sk-ant-...">
                        <button type="button" class="button button-secondary toggle-password" data-target="claude_api_key">
                            <?php _e('Show', 'medical-news-automation'); ?>
                        </button>
                        <button type="button" class="button button-secondary test-api" data-service="claude">
                            <?php _e('Test', 'medical-news-automation'); ?>
                        </button>
                        <p class="description">
                            <?php _e('Alternative to OpenAI. Get your API key from', 'medical-news-automation'); ?> 
                            <a href="https://console.anthropic.com/" target="_blank">Anthropic</a>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="preferred_llm"><?php _e('Preferred LLM', 'medical-news-automation'); ?></label>
                    </th>
                    <td>
                        <select id="preferred_llm" name="preferred_llm">
                            <option value="openai" <?php selected($current_settings['preferred_llm'], 'openai'); ?>>
                                <?php _e('OpenAI GPT-4', 'medical-news-automation'); ?>
                            </option>
                            <option value="claude" <?php selected($current_settings['preferred_llm'], 'claude'); ?>>
                                <?php _e('Anthropic Claude', 'medical-news-automation'); ?>
                            </option>
                        </select>
                        <p class="description">
                            <?php _e('Primary LLM service for article generation. Will fallback to the other if unavailable.', 'medical-news-automation'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="unsplash_api_key"><?php _e('Unsplash API Key', 'medical-news-automation'); ?></label>
                    </th>
                    <td>
                        <input type="password" id="unsplash_api_key" name="unsplash_api_key" 
                               value="<?php echo esc_attr($current_settings['unsplash_api_key'] ?? ''); ?>" 
                               class="regular-text" placeholder="Free API key...">
                        <button type="button" class="button button-secondary toggle-password" data-target="unsplash_api_key">
                            <?php _e('Show', 'medical-news-automation'); ?>
                        </button>
                        <button type="button" class="button button-secondary test-api" data-service="unsplash">
                            <?php _e('Test', 'medical-news-automation'); ?>
                        </button>
                        <p class="description">
                            <?php _e('For high-quality medical stock photos. Get your free API key from', 'medical-news-automation'); ?> 
                            <a href="https://unsplash.com/developers" target="_blank">Unsplash Developers</a>
                        </p>
                    </td>
                </tr>
            </table>
            
            <div class="api-status-section">
                <h3><?php _e('API Status', 'medical-news-automation'); ?></h3>
                <div id="api-status-results">
                    <p><?php _e('Click "Test All APIs" to check connection status.', 'medical-news-automation'); ?></p>
                </div>
                <button type="button" class="button button-secondary" id="test-all-apis">
                    <?php _e('Test All APIs', 'medical-news-automation'); ?>
                </button>
            </div>
        </div>
        
        <!-- Automation Settings Tab -->
        <div id="automation-settings" class="tab-content">
            <h2><?php _e('Automation Settings', 'medical-news-automation'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Automatic Processing', 'medical-news-automation'); ?></th>
                    <td>
                        <fieldset>
                            <label for="auto_process">
                                <input type="checkbox" id="auto_process" name="auto_process" value="1" 
                                       <?php checked($current_settings['auto_process']); ?>>
                                <?php _e('Enable automatic headline processing', 'medical-news-automation'); ?>
                            </label>
                            <p class="description">
                                <?php _e('Notifications will be sent to users with editor or administrator capabilities.', 'medical-news-automation'); ?>
                            </p>
                        </fieldset>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Notification Recipients', 'medical-news-automation'); ?></th>
                    <td>
                        <?php
                        $users = get_users(array('capability' => 'edit_posts'));
                        if (!empty($users)):
                        ?>
                            <p class="description"><?php _e('Current notification recipients:', 'medical-news-automation'); ?></p>
                            <ul>
                                <?php foreach ($users as $user): ?>
                                    <li><?php echo esc_html($user->display_name . ' (' . $user->user_email . ')'); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="description"><?php _e('No users with editing capabilities found.', 'medical-news-automation'); ?></p>
                        <?php endif; ?>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="batch_size"><?php _e('Batch Size', 'medical-news-automation'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="batch_size" name="batch_size" 
                               value="<?php echo intval($current_settings['batch_size']); ?>" 
                               min="1" max="20" class="small-text">
                        <p class="description">
                            <?php _e('Number of headlines to process in each batch (1-20). Lower numbers are safer for API rate limits.', 'medical-news-automation'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Processing Schedule', 'medical-news-automation'); ?></th>
                    <td>
                        <p class="description">
                            <?php _e('Automatic processing runs every hour when enabled. Next scheduled run:', 'medical-news-automation'); ?>
                            <strong>
                                <?php 
                                $next_cron = wp_next_scheduled('mna_process_headlines');
                                echo $next_cron ? date('Y-m-d H:i:s', $next_cron) : __('Not scheduled', 'medical-news-automation');
                                ?>
                            </strong>
                        </p>
                        <button type="button" class="button button-secondary" id="trigger-manual-batch">
                            <?php _e('Run Manual Batch Now', 'medical-news-automation'); ?>
                        </button>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Advanced Settings Tab -->
        <div id="advanced-settings" class="tab-content">
            <h2><?php _e('Advanced Settings', 'medical-news-automation'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Data Retention', 'medical-news-automation'); ?></th>
                    <td>
                        <fieldset>
                            <p class="description">
                                <?php _e('Automatic cleanup helps maintain database performance.', 'medical-news-automation'); ?>
                            </p>
                            
                            <label>
                                <strong><?php _e('Headlines:', 'medical-news-automation'); ?></strong>
                                <?php _e('Published headlines older than 30 days are automatically removed.', 'medical-news-automation'); ?>
                            </label><br><br>
                            
                            <label>
                                <strong><?php _e('Articles:', 'medical-news-automation'); ?></strong>
                                <?php _e('Rejected articles older than 60 days are automatically removed.', 'medical-news-automation'); ?>
                            </label><br><br>
                            
                            <button type="button" class="button button-secondary" id="cleanup-old-data">
                                <?php _e('Run Cleanup Now', 'medical-news-automation'); ?>
                            </button>
                        </fieldset>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Debug Mode', 'medical-news-automation'); ?></th>
                    <td>
                        <fieldset>
                            <label for="debug_mode">
                                <input type="checkbox" id="debug_mode" name="debug_mode" value="1" 
                                       <?php checked(get_option('mna_debug_mode', false)); ?>>
                                <?php _e('Enable detailed logging for troubleshooting', 'medical-news-automation'); ?>
                            </label>
                            <p class="description">
                                <?php _e('When enabled, detailed information about API calls and processing will be logged.', 'medical-news-automation'); ?>
                            </p>
                        </fieldset>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Reset Plugin', 'medical-news-automation'); ?></th>
                    <td>
                        <button type="button" class="button button-secondary" id="reset-plugin-data" 
                                style="color: #d63638; border-color: #d63638;">
                            <?php _e('Reset All Data', 'medical-news-automation'); ?>
                        </button>
                        <p class="description">
                            <?php _e('⚠️ Warning: This will delete ALL headlines, articles, and processing data. Settings will be preserved.', 'medical-news-automation'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            
            <div class="system-info-section">
                <h3><?php _e('System Information', 'medical-news-automation'); ?></h3>
                <table class="widefat striped">
                    <tbody>
                        <tr>
                            <td><strong><?php _e('Plugin Version', 'medical-news-automation'); ?></strong></td>
                            <td><?php echo MNA_VERSION; ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('WordPress Version', 'medical-news-automation'); ?></strong></td>
                            <td><?php echo get_bloginfo('version'); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('PHP Version', 'medical-news-automation'); ?></strong></td>
                            <td><?php echo PHP_VERSION; ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('WordPress Cron', 'medical-news-automation'); ?></strong></td>
                            <td><?php echo defined('DISABLE_WP_CRON') && DISABLE_WP_CRON ? __('Disabled', 'medical-news-automation') : __('Enabled', 'medical-news-automation'); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('Database Tables', 'medical-news-automation'); ?></strong></td>
                            <td>
                                <?php
                                global $wpdb;
                                $tables = array('headlines', 'research', 'articles', 'sources', 'logs');
                                $existing_tables = array();
                                foreach ($tables as $table) {
                                    $table_name = $wpdb->prefix . 'mna_' . $table;
                                    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
                                        $existing_tables[] = $table;
                                    }
                                }
                                echo count($existing_tables) . '/' . count($tables) . ' (' . implode(', ', $existing_tables) . ')';
                                ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Notification Settings Tab -->
        <div id="notification-settings" class="tab-content">
            <h2><?php _e('Notification Settings', 'medical-news-automation'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Email Notifications', 'medical-news-automation'); ?></th>
                    <td>
                        <fieldset>
                            <label for="email_notifications">
                                <input type="checkbox" id="email_notifications" name="email_notifications" value="1" 
                                       <?php checked($current_settings['email_notifications']); ?>>
                                <?php _e('Send email notifications when articles are ready for review', 'medical-news-automation'); ?>
                            </label>
                            <p class="description">
                                <?php _e('When enabled, pending headlines will be automatically processed every hour via WordPress cron.', 'medical-news-automation'); ?>
                            </p>
                        </fieldset>
                    </td>
                </tr>
            </table>
        </div>
        
        <?php submit_button(__('Save Settings', 'medical-news-automation'), 'primary', 'submit_settings'); ?>
    </form>
</div>

<style>
.nav-tab-wrapper {
    margin-bottom: 20px;
}

.tab-content {
    display: none;
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.tab-content.active {
    display: block;
}

.api-status-section, .system-info-section {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #ddd;
}

#api-status-results {
    background: #f9f9f9;
    padding: 15px;
    border-radius: 4px;
    margin-bottom: 15px;
    font-family: monospace;
    white-space: pre-line;
}

.toggle-password, .test-api {
    margin-left: 10px;
}

.form-table th {
    width: 200px;
}

.button.loading {
    opacity: 0.6;
    pointer-events: none;
}

.api-test-result {
    display: inline-block;
    margin-left: 10px;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: bold;
}

.api-test-result.success {
    background: #d4edda;
    color: #155724;
}

.api-test-result.error {
    background: #f8d7da;
    color: #721c24;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Tab switching
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        
        var target = $(this).attr('href');
        
        // Update tab appearance
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        // Show/hide content
        $('.tab-content').removeClass('active');
        $(target).addClass('active');
    });
    
    // Toggle password visibility
    $('.toggle-password').on('click', function() {
        var target = $(this).data('target');
        var input = $('#' + target);
        var button = $(this);
        
        if (input.attr('type') === 'password') {
            input.attr('type', 'text');
            button.text('<?php _e('Hide', 'medical-news-automation'); ?>');
        } else {
            input.attr('type', 'password');
            button.text('<?php _e('Show', 'medical-news-automation'); ?>');
        }
    });
    
    // Test individual API
    $('.test-api').on('click', function() {
        var button = $(this);
        var service = button.data('service');
        
        button.addClass('loading').text('<?php _e('Testing...', 'medical-news-automation'); ?>');
        
        // Remove previous results
        button.siblings('.api-test-result').remove();
        
        $.post(ajaxurl, {
            action: 'mna_test_single_api',
            service: service,
            nonce: mna_ajax.nonce
        }, function(response) {
            button.removeClass('loading').text('<?php _e('Test', 'medical-news-automation'); ?>');
            
            var resultClass = response.success ? 'success' : 'error';
            var resultText = response.success ? '✓ Connected' : '✗ Failed';
            
            $('<span class="api-test-result ' + resultClass + '">' + resultText + '</span>').insertAfter(button);
            
            if (!response.success) {
                alert('API Test Failed: ' + response.data.message);
            }
        });
    });
    
    // Test all APIs
    $('#test-all-apis').on('click', function() {
        var button = $(this);
        button.addClass('loading').text('<?php _e('Testing...', 'medical-news-automation'); ?>');
        
        $('#api-status-results').text('<?php _e('Testing API connections...', 'medical-news-automation'); ?>');
        
        $.post(ajaxurl, {
            action: 'mna_test_apis',
            nonce: mna_ajax.nonce
        }, function(response) {
            button.removeClass('loading').text('<?php _e('Test All APIs', 'medical-news-automation'); ?>');
            
            if (response.success) {
                $('#api-status-results').text(response.data.message);
            } else {
                $('#api-status-results').text('Error: ' + response.data.message);
            }
        });
    });
    
    // Manual batch processing
    $('#trigger-manual-batch').on('click', function() {
        var button = $(this);
        button.addClass('loading').text('<?php _e('Processing...', 'medical-news-automation'); ?>');
        
        $.post(ajaxurl, {
            action: 'mna_process_batch',
            nonce: mna_ajax.nonce
        }, function(response) {
            button.removeClass('loading').text('<?php _e('Run Manual Batch Now', 'medical-news-automation'); ?>');
            
            if (response.success) {
                alert('Batch processing completed:\n' + response.data.message);
            } else {
                alert('Error: ' + response.data.message);
            }
        });
    });
    
    // Cleanup old data
    $('#cleanup-old-data').on('click', function() {
        if (!confirm('<?php _e('Are you sure you want to clean up old data?', 'medical-news-automation'); ?>')) {
            return;
        }
        
        var button = $(this);
        button.addClass('loading').text('<?php _e('Cleaning...', 'medical-news-automation'); ?>');
        
        $.post(ajaxurl, {
            action: 'mna_cleanup_data',
            nonce: mna_ajax.nonce
        }, function(response) {
            button.removeClass('loading').text('<?php _e('Run Cleanup Now', 'medical-news-automation'); ?>');
            
            if (response.success) {
                alert('Cleanup completed: ' + response.data.message);
            } else {
                alert('Error: ' + response.data.message);
            }
        });
    });
    
    // Reset plugin data
    $('#reset-plugin-data').on('click', function() {
        var confirmText = '<?php _e('Are you absolutely sure? This will delete ALL headlines, articles, and processing data. Type "RESET" to confirm:', 'medical-news-automation'); ?>';
        var userInput = prompt(confirmText);
        
        if (userInput !== 'RESET') {
            return;
        }
        
        var button = $(this);
        button.addClass('loading').text('<?php _e('Resetting...', 'medical-news-automation'); ?>');
        
        $.post(ajaxurl, {
            action: 'mna_reset_plugin_data',
            nonce: mna_ajax.nonce
        }, function(response) {
            button.removeClass('loading').text('<?php _e('Reset All Data', 'medical-news-automation'); ?>');
            
            if (response.success) {
                alert('Plugin data has been reset.');
                location.reload();
            } else {
                alert('Error: ' + response.data.message);
            }
        });
    });
});
</script>