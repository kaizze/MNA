<?php
/**
 * Admin dashboard template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Medical News Automation Dashboard', 'medical-news-automation'); ?></h1>
    
    <!-- Status Overview -->
    <div class="mna-dashboard-stats">
        <div class="mna-stat-boxes">
            <div class="mna-stat-box pending">
                <h3><?php echo intval($stats['pending_headlines']); ?></h3>
                <p><?php _e('Pending Headlines', 'medical-news-automation'); ?></p>
                <a href="<?php echo admin_url('admin.php?page=mna-headlines'); ?>" class="button button-small">
                    <?php _e('View Queue', 'medical-news-automation'); ?>
                </a>
            </div>
            
            <div class="mna-stat-box processing">
                <h3><?php echo intval($stats['processing_headlines']); ?></h3>
                <p><?php _e('Processing', 'medical-news-automation'); ?></p>
            </div>
            
            <div class="mna-stat-box review">
                <h3><?php echo intval($stats['articles_for_review']); ?></h3>
                <p><?php _e('Articles for Review', 'medical-news-automation'); ?></p>
                <a href="<?php echo admin_url('admin.php?page=mna-review'); ?>" class="button button-small">
                    <?php _e('Review Articles', 'medical-news-automation'); ?>
                </a>
            </div>
            
            <div class="mna-stat-box published">
                <h3><?php echo intval($stats['published_today']); ?></h3>
                <p><?php _e('Published Today', 'medical-news-automation'); ?></p>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="mna-quick-actions">
        <h2><?php _e('Quick Actions', 'medical-news-automation'); ?></h2>
        <div class="mna-action-buttons">
            <a href="<?php echo admin_url('admin.php?page=mna-headlines'); ?>" class="button button-primary">
                <?php _e('Add New Headlines', 'medical-news-automation'); ?>
            </a>
            
            <button id="mna-test-apis" class="button">
                <?php _e('Test API Connections', 'medical-news-automation'); ?>
            </button>
        </div>
    </div>

    <div class="mna-dashboard-content">
        <!-- Recent Headlines -->
        <div class="mna-dashboard-section">
            <h2><?php _e('Recent Headlines', 'medical-news-automation'); ?></h2>
            
            <?php if (!empty($recent_headlines)): ?>
                <div class="mna-headlines-list">
                    <?php foreach ($recent_headlines as $headline): ?>
                        <div class="mna-headline-item status-<?php echo esc_attr($headline->status); ?>">
                            <div class="headline-content">
                                <h4><?php echo esc_html($headline->headline); ?></h4>
                                <div class="headline-meta">
                                    <span class="status"><?php echo ucfirst($headline->status); ?></span>
                                    <span class="date"><?php echo human_time_diff(strtotime($headline->created_at)); ?> ago</span>
                                    <?php if ($headline->category): ?>
                                        <span class="category"><?php echo esc_html($headline->category); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="headline-actions">
                                <?php if ($headline->status === 'pending'): ?>
                                    <button class="button button-small mna-process-single" data-headline-id="<?php echo $headline->id; ?>">
                                        <?php _e('Process Now', 'medical-news-automation'); ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p><?php _e('No pending headlines. Add some headlines to get started!', 'medical-news-automation'); ?></p>
                <a href="<?php echo admin_url('admin.php?page=mna-headlines'); ?>" class="button button-primary">
                    <?php _e('Add Headlines', 'medical-news-automation'); ?>
                </a>
            <?php endif; ?>
        </div>

        <!-- Articles for Review -->
        <div class="mna-dashboard-section">
            <h2><?php _e('Articles Awaiting Review', 'medical-news-automation'); ?></h2>
            
            <?php if (!empty($recent_articles)): ?>
                <div class="mna-articles-list">
                    <?php foreach ($recent_articles as $article): ?>
                        <div class="mna-article-item status-<?php echo esc_attr($article->status); ?>">
                            <div class="article-content">
                                <h4><?php echo esc_html($article->headline); ?></h4>
                                <div class="article-meta">
                                    <span class="llm-used"><?php echo esc_html(strtoupper($article->llm_used)); ?></span>
                                    <span class="date"><?php echo human_time_diff(strtotime($article->created_at)); ?> ago</span>
                                    <?php if ($article->content_quality_score): ?>
                                        <span class="quality-score">Quality: <?php echo intval($article->content_quality_score); ?>/10</span>
                                    <?php endif; ?>
                                    <?php if ($article->category): ?>
                                        <span class="category"><?php echo esc_html($article->category); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="article-preview">
                                    <?php echo wp_trim_words(strip_tags($article->generated_content), 30); ?>
                                </div>
                            </div>
                            <div class="article-actions">
                                <a href="<?php echo admin_url('admin.php?page=mna-review&article_id=' . $article->id); ?>" class="button button-primary button-small">
                                    <?php _e('Review', 'medical-news-automation'); ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p><?php _e('No articles awaiting review.', 'medical-news-automation'); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- System Status -->
    <div class="mna-system-status">
        <h2><?php _e('System Status', 'medical-news-automation'); ?></h2>
        
        <div class="mna-status-grid">
            <div class="status-item">
                <h4><?php _e('Auto Processing', 'medical-news-automation'); ?></h4>
                <span class="status-indicator <?php echo get_option('mna_auto_process') ? 'enabled' : 'disabled'; ?>">
                    <?php echo get_option('mna_auto_process') ? __('Enabled', 'medical-news-automation') : __('Disabled', 'medical-news-automation'); ?>
                </span>
            </div>
            
            <div class="status-item">
                <h4><?php _e('Perplexity API', 'medical-news-automation'); ?></h4>
                <span class="status-indicator <?php echo !empty(get_option('mna_perplexity_api_key')) ? 'configured' : 'not-configured'; ?>">
                    <?php echo !empty(get_option('mna_perplexity_api_key')) ? __('Configured', 'medical-news-automation') : __('Not Configured', 'medical-news-automation'); ?>
                </span>
            </div>
            
            <div class="status-item">
                <h4><?php _e('LLM Service', 'medical-news-automation'); ?></h4>
                <?php 
                $openai_key = get_option('mna_openai_api_key');
                $claude_key = get_option('mna_claude_api_key');
                $has_llm = !empty($openai_key) || !empty($claude_key);
                ?>
                <span class="status-indicator <?php echo $has_llm ? 'configured' : 'not-configured'; ?>">
                    <?php echo $has_llm ? __('Configured', 'medical-news-automation') : __('Not Configured', 'medical-news-automation'); ?>
                </span>
            </div>
            
            <div class="status-item">
                <h4><?php _e('Email Notifications', 'medical-news-automation'); ?></h4>
                <span class="status-indicator <?php echo get_option('mna_email_notifications') ? 'enabled' : 'disabled'; ?>">
                    <?php echo get_option('mna_email_notifications') ? __('Enabled', 'medical-news-automation') : __('Disabled', 'medical-news-automation'); ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Recent Activity Log -->
    <div class="mna-activity-log">
        <h2><?php _e('Recent Activity', 'medical-news-automation'); ?></h2>
        
        <?php
        global $wpdb;
        $logs_table = $wpdb->prefix . 'mna_logs';
        $recent_logs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $logs_table ORDER BY created_at DESC LIMIT %d",
            10
        ));
        ?>
        
        <?php if (!empty($recent_logs)): ?>
            <div class="mna-log-entries">
                <?php foreach ($recent_logs as $log): ?>
                    <div class="log-entry status-<?php echo esc_attr($log->status); ?>">
                        <div class="log-content">
                            <span class="log-type"><?php echo esc_html(ucfirst($log->process_type)); ?>:</span>
                            <span class="log-message"><?php echo esc_html($log->message); ?></span>
                        </div>
                        <div class="log-meta">
                            <span class="log-time"><?php echo human_time_diff(strtotime($log->created_at)); ?> ago</span>
                            <?php if ($log->execution_time): ?>
                                <span class="execution-time"><?php echo number_format($log->execution_time, 2); ?>s</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p><?php _e('No recent activity.', 'medical-news-automation'); ?></p>
        <?php endif; ?>
    </div>
</div>

<style>
.mna-dashboard-stats {
    margin: 20px 0;
}

.mna-stat-boxes {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.mna-stat-box {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    text-align: center;
    border-left: 4px solid #ccc;
}

.mna-stat-box.pending { border-left-color: #f39c12; }
.mna-stat-box.processing { border-left-color: #3498db; }
.mna-stat-box.review { border-left-color: #e74c3c; }
.mna-stat-box.published { border-left-color: #27ae60; }

.mna-stat-box h3 {
    font-size: 36px;
    margin: 0 0 10px 0;
    font-weight: bold;
}

.mna-stat-box p {
    margin: 0 0 15px 0;
    color: #666;
}

.mna-quick-actions {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 30px;
}

.mna-action-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.mna-dashboard-content {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 30px;
}

.mna-dashboard-section {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.mna-headline-item, .mna-article-item {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 15px;
    border-bottom: 1px solid #eee;
    border-left: 4px solid #ccc;
}

.mna-headline-item.status-pending { border-left-color: #f39c12; }
.mna-headline-item.status-processing { border-left-color: #3498db; }
.mna-headline-item.status-researched { border-left-color: #9b59b6; }
.mna-headline-item.status-generated { border-left-color: #27ae60; }

.mna-article-item.status-draft { border-left-color: #f39c12; }
.mna-article-item.status-under_review { border-left-color: #e74c3c; }

.headline-content, .article-content {
    flex: 1;
}

.headline-content h4, .article-content h4 {
    margin: 0 0 10px 0;
    font-size: 14px;
    line-height: 1.4;
}

.headline-meta, .article-meta {
    display: flex;
    gap: 15px;
    font-size: 12px;
    color: #666;
    margin-bottom: 10px;
}

.article-preview {
    font-size: 13px;
    color: #888;
    line-height: 1.4;
}

.mna-system-status, .mna-activity-log {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}

.mna-status-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.status-item {
    text-align: center;
}

.status-item h4 {
    margin: 0 0 10px 0;
    font-size: 14px;
}

.status-indicator {
    padding: 5px 12px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: bold;
    text-transform: uppercase;
}

.status-indicator.enabled, .status-indicator.configured {
    background: #d4edda;
    color: #155724;
}

.status-indicator.disabled, .status-indicator.not-configured {
    background: #f8d7da;
    color: #721c24;
}

.log-entry {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px;
    border-bottom: 1px solid #eee;
    border-left: 3px solid #ccc;
}

.log-entry.status-completed { border-left-color: #27ae60; }
.log-entry.status-failed { border-left-color: #e74c3c; }
.log-entry.status-started { border-left-color: #3498db; }

.log-content {
    flex: 1;
}

.log-type {
    font-weight: bold;
    text-transform: capitalize;
}

.log-meta {
    display: flex;
    gap: 10px;
    font-size: 12px;
    color: #666;
}

@media (max-width: 768px) {
    .mna-dashboard-content {
        grid-template-columns: 1fr;
    }
    
    .mna-action-buttons {
        flex-direction: column;
    }
    
    .mna-headline-item, .mna-article-item {
        flex-direction: column;
        gap: 10px;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Process single headline
    $('.mna-process-single').on('click', function() {
        var button = $(this);
        var headlineId = button.data('headline-id');
        
        button.prop('disabled', true).text('<?php _e('Processing...', 'medical-news-automation'); ?>');
        
        $.post(ajaxurl, {
            action: 'mna_process_headline',
            headline_id: headlineId,
            nonce: mna_ajax.nonce
        }, function(response) {
            if (response.success) {
                button.text('<?php _e('Completed', 'medical-news-automation'); ?>').removeClass('button-primary').addClass('button-secondary');
                location.reload();
            } else {
                button.prop('disabled', false).text('<?php _e('Process Now', 'medical-news-automation'); ?>');
                alert('Error: ' + response.data.message);
            }
        });
    });
    
    // Process batch headlines
    $('#mna-process-batch').on('click', function() {
        var button = $(this);
        button.prop('disabled', true).text('<?php _e('Processing...', 'medical-news-automation'); ?>');
        
        $.post(ajaxurl, {
            action: 'mna_process_batch',
            nonce: mna_ajax.nonce
        }, function(response) {
            button.prop('disabled', false).text('<?php _e('Process Headlines Batch', 'medical-news-automation'); ?>');
            
            if (response.success) {
                alert(response.data.message);
                location.reload();
            } else {
                alert('Error: ' + response.data.message);
            }
        });
    });
    
    // Test API connections
    $('#mna-test-apis').on('click', function() {
        var button = $(this);
        button.prop('disabled', true).text('<?php _e('Testing...', 'medical-news-automation'); ?>');
        
        $.post(ajaxurl, {
            action: 'mna_test_apis',
            nonce: mna_ajax.nonce
        }, function(response) {
            button.prop('disabled', false).text('<?php _e('Test API Connections', 'medical-news-automation'); ?>');
            
            if (response.success) {
                alert('API Tests:\n' + response.data.message);
            } else {
                alert('Error: ' + response.data.message);
            }
        });
    });
});
</script>d="mna-process-batch" class="button button-secondary">
                <?php _e('Process Headlines Batch', 'medical-news-automation'); ?>
            </button>
            
            <a href="<?php echo admin_url('admin.php?page=mna-settings'); ?>" class="button">
                <?php _e('Plugin Settings', 'medical-news-automation'); ?>
            </a>
            
            <button i