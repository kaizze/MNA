<?php
/**
 * Headlines management template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Headlines Queue', 'medical-news-automation'); ?></h1>
    
    <!-- Add New Headline Form -->
    <div class="mna-add-headline">
        <h2><?php _e('Add New Headline', 'medical-news-automation'); ?></h2>
        
        <form method="post" action="">
            <?php wp_nonce_field('add_headline', 'mna_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="headline"><?php _e('Headline', 'medical-news-automation'); ?></label>
                    </th>
                    <td>
                        <textarea id="headline" name="headline" class="large-text" rows="3" required placeholder="<?php _e('Enter medical news headline...', 'medical-news-automation'); ?>"></textarea>
                        <p class="description"><?php _e('Enter a medical news headline to be processed.', 'medical-news-automation'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="category"><?php _e('Category', 'medical-news-automation'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="category" name="category" class="regular-text" placeholder="<?php _e('e.g., Cardiology, Oncology, Public Health', 'medical-news-automation'); ?>">
                        <p class="description"><?php _e('Optional: Specify medical category for better organization.', 'medical-news-automation'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="priority"><?php _e('Priority', 'medical-news-automation'); ?></label>
                    </th>
                    <td>
                        <select id="priority" name="priority">
                            <option value="1"><?php _e('1 - Urgent', 'medical-news-automation'); ?></option>
                            <option value="2"><?php _e('2 - High', 'medical-news-automation'); ?></option>
                            <option value="3"><?php _e('3 - Medium High', 'medical-news-automation'); ?></option>
                            <option value="4"><?php _e('4 - Medium', 'medical-news-automation'); ?></option>
                            <option value="5" selected><?php _e('5 - Normal', 'medical-news-automation'); ?></option>
                            <option value="6"><?php _e('6 - Low', 'medical-news-automation'); ?></option>
                        </select>
                        <p class="description"><?php _e('Higher priority headlines are processed first.', 'medical-news-automation'); ?></p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(__('Add Headline', 'medical-news-automation'), 'primary', 'submit_headline'); ?>
        </form>
    </div>

    <!-- Bulk Import Section -->
    <div class="mna-bulk-import">
        <h2><?php _e('Bulk Import Headlines', 'medical-news-automation'); ?></h2>
        
        <form method="post" action="">
            <?php wp_nonce_field('import_headlines', 'mna_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="headlines_bulk"><?php _e('Headlines (One per line)', 'medical-news-automation'); ?></label>
                    </th>
                    <td>
                        <textarea id="headlines_bulk" name="headlines_bulk" class="large-text" rows="10" placeholder="<?php _e('Paste multiple headlines here, one per line...', 'medical-news-automation'); ?>"></textarea>
                        <p class="description"><?php _e('Enter one headline per line. Each will be added with normal priority.', 'medical-news-automation'); ?></p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(__('Import Headlines', 'medical-news-automation'), 'secondary', 'import_headlines'); ?>
        </form>
    </div>

    <!-- Headlines List -->
    <div class="mna-headlines-list">
        <h2><?php _e('All Headlines', 'medical-news-automation'); ?></h2>
        
        <?php if (!empty($all_headlines)): ?>
            <div class="tablenav top">
                <div class="alignleft actions">
                    <button id="mna-process-selected" class="button action" disabled>
                        <?php _e('Process Selected', 'medical-news-automation'); ?>
                    </button>
                    <button id="mna-delete-selected" class="button action" disabled>
                        <?php _e('Delete Selected', 'medical-news-automation'); ?>
                    </button>
                </div>
            </div>
            
            <table class="wp-list-table widefat fixed striped headlines">
                <thead>
                    <tr>
                        <td class="manage-column column-cb check-column">
                            <input type="checkbox" id="cb-select-all">
                        </td>
                        <th class="manage-column column-headline column-primary"><?php _e('Headline', 'medical-news-automation'); ?></th>
                        <th class="manage-column column-status"><?php _e('Status', 'medical-news-automation'); ?></th>
                        <th class="manage-column column-priority"><?php _e('Priority', 'medical-news-automation'); ?></th>
                        <th class="manage-column column-category"><?php _e('Category', 'medical-news-automation'); ?></th>
                        <th class="manage-column column-date"><?php _e('Created', 'medical-news-automation'); ?></th>
                        <th class="manage-column column-actions"><?php _e('Actions', 'medical-news-automation'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_headlines as $headline): ?>
                        <tr class="headline-row status-<?php echo esc_attr($headline->status); ?>">
                            <td class="check-column">
                                <input type="checkbox" name="headline_ids[]" value="<?php echo $headline->id; ?>" class="headline-checkbox">
                            </td>
                            <td class="column-headline column-primary">
                                <strong><?php echo esc_html($headline->headline); ?></strong>
                                <?php if ($headline->notes): ?>
                                    <br><small class="notes"><?php echo esc_html($headline->notes); ?></small>
                                <?php endif; ?>
                                <div class="row-actions">
                                    <?php if ($headline->status === 'pending'): ?>
                                        <span class="process">
                                            <a href="#" class="mna-process-single" data-headline-id="<?php echo $headline->id; ?>"><?php _e('Process', 'medical-news-automation'); ?></a> |
                                        </span>
                                    <?php endif; ?>
                                    <span class="edit">
                                        <a href="#" class="mna-edit-headline" data-headline-id="<?php echo $headline->id; ?>"><?php _e('Edit', 'medical-news-automation'); ?></a> |
                                    </span>
                                    <span class="delete">
                                        <a href="#" class="mna-delete-headline" data-headline-id="<?php echo $headline->id; ?>"><?php _e('Delete', 'medical-news-automation'); ?></a>
                                    </span>
                                </div>
                            </td>
                            <td class="column-status">
                                <span class="status-badge status-<?php echo esc_attr($headline->status); ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $headline->status)); ?>
                                </span>
                            </td>
                            <td class="column-priority">
                                <span class="priority-<?php echo $headline->priority; ?>">
                                    <?php echo $headline->priority; ?>
                                </span>
                            </td>
                            <td class="column-category">
                                <?php echo $headline->category ? esc_html($headline->category) : '—'; ?>
                            </td>
                            <td class="column-date">
                                <?php echo human_time_diff(strtotime($headline->created_at)); ?> ago
                                <br><small><?php echo date('Y-m-d H:i', strtotime($headline->created_at)); ?></small>
                            </td>
                            <td class="column-actions">
                                <?php if ($headline->status === 'pending'): ?>
                                    <button class="button button-small button-primary mna-process-single" data-headline-id="<?php echo $headline->id; ?>">
                                        <?php _e('Process Now', 'medical-news-automation'); ?>
                                    </button>
                                <?php elseif ($headline->status === 'generated'): ?>
                                    <a href="<?php echo admin_url('admin.php?page=mna-review&headline_id=' . $headline->id); ?>" class="button button-small">
                                        <?php _e('Review Article', 'medical-news-automation'); ?>
                                    </a>
                                <?php elseif ($headline->status === 'failed'): ?>
                                    <button class="button button-small mna-retry-headline" data-headline-id="<?php echo $headline->id; ?>">
                                        <?php _e('Retry', 'medical-news-automation'); ?>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="mna-empty-state">
                <h3><?php _e('No headlines yet', 'medical-news-automation'); ?></h3>
                <p><?php _e('Add your first medical news headline using the form above.', 'medical-news-automation'); ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.mna-add-headline, .mna-bulk-import {
    background: #fff;
    padding: 20px;
    margin: 20px 0;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.mna-headlines-list {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.status-badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
}

.status-badge.status-pending { background: #fff3cd; color: #856404; }
.status-badge.status-processing { background: #d1ecf1; color: #0c5460; }
.status-badge.status-researched { background: #e2e3f3; color: #383d41; }
.status-badge.status-generated { background: #d4edda; color: #155724; }
.status-badge.status-published { background: #d1ecf1; color: #0c5460; }
.status-badge.status-failed { background: #f8d7da; color: #721c24; }

.priority-1, .priority-2 { color: #e74c3c; font-weight: bold; }
.priority-3, .priority-4 { color: #f39c12; }
.priority-5 { color: #3498db; }
.priority-6 { color: #95a5a6; }

.headline-row.status-pending { border-left: 4px solid #f39c12; }
.headline-row.status-processing { border-left: 4px solid #3498db; }
.headline-row.status-researched { border-left: 4px solid #9b59b6; }
.headline-row.status-generated { border-left: 4px solid #27ae60; }
.headline-row.status-published { border-left: 4px solid #2980b9; }
.headline-row.status-failed { border-left: 4px solid #e74c3c; }

.notes {
    color: #666;
    font-style: italic;
}

.mna-empty-state {
    text-align: center;
    padding: 40px;
    color: #666;
}

.column-headline { width: 40%; }
.column-status { width: 12%; }
.column-priority { width: 8%; }
.column-category { width: 15%; }
.column-date { width: 15%; }
.column-actions { width: 10%; }
</style>

<script>
jQuery(document).ready(function($) {
    // Select all checkbox functionality
    $('#cb-select-all').on('change', function() {
        $('.headline-checkbox').prop('checked', this.checked);
        toggleBulkActions();
    });
    
    $('.headline-checkbox').on('change', function() {
        toggleBulkActions();
    });
    
    function toggleBulkActions() {
        var checkedCount = $('.headline-checkbox:checked').length;
        $('#mna-process-selected, #mna-delete-selected').prop('disabled', checkedCount === 0);
    }
    
    // Process single headline
    $(document).on('click', '.mna-process-single', function(e) {
        e.preventDefault();
        var button = $(this);
        var headlineId = button.data('headline-id');
        
        button.prop('disabled', true).text('<?php _e('Processing...', 'medical-news-automation'); ?>');
        
        $.post(ajaxurl, {
            action: 'mna_process_headline',
            headline_id: headlineId,
            nonce: mna_ajax.nonce
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                button.prop('disabled', false).text('<?php _e('Process Now', 'medical-news-automation'); ?>');
                alert('Error: ' + response.data.message);
            }
        });
    });
    
    // Delete headline
    $(document).on('click', '.mna-delete-headline', function(e) {
        e.preventDefault();
        
        if (!confirm('<?php _e('Are you sure you want to delete this headline?', 'medical-news-automation'); ?>')) {
            return;
        }
        
        var headlineId = $(this).data('headline-id');
        var row = $(this).closest('tr');
        
        $.post(ajaxurl, {
            action: 'mna_delete_headline',
            headline_id: headlineId,
            nonce: mna_ajax.nonce
        }, function(response) {
            if (response.success) {
                row.fadeOut();
            } else {
                alert('Error: ' + response.data.message);
            }
        });
    });
    
    // Retry failed headline
    $(document).on('click', '.mna-retry-headline', function(e) {
        e.preventDefault();
        var button = $(this);
        var headlineId = button.data('headline-id');
        
        button.prop('disabled', true).text('<?php _e('Retrying...', 'medical-news-automation'); ?>');
        
        $.post(ajaxurl, {
            action: 'mna_retry_headline',
            headline_id: headlineId,
            nonce: mna_ajax.nonce
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                button.prop('disabled', false).text('<?php _e('Retry', 'medical-news-automation'); ?>');
                alert('Error: ' + response.data.message);
            }
        });
    });
});
</script>