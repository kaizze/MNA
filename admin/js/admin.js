/**
 * Medical News Automation - Admin JavaScript
 */

(function($) {
    'use strict';
    
    // Global MNA Admin object
    window.MNA_Admin = {
        
        // Initialize all admin functionality
        init: function() {
            this.setupTooltips();
            this.setupFormValidation();
            this.setupProgressBars();
            this.setupNotifications();
            this.setupAjaxHandlers();
            
            console.log('MNA Admin initialized');
        },
        
        // Setup tooltips
        setupTooltips: function() {
            $('[data-tooltip]').hover(
                function() {
                    var tooltip = $('<div class="mna-tooltip-content">' + $(this).data('tooltip') + '</div>');
                    $('body').append(tooltip);
                    
                    var offset = $(this).offset();
                    tooltip.css({
                        top: offset.top - tooltip.outerHeight() - 10,
                        left: offset.left + ($(this).outerWidth() / 2) - (tooltip.outerWidth() / 2),
                        position: 'absolute',
                        background: '#333',
                        color: '#fff',
                        padding: '5px 10px',
                        borderRadius: '4px',
                        fontSize: '12px',
                        zIndex: 1000
                    });
                },
                function() {
                    $('.mna-tooltip-content').remove();
                }
            );
        },
        
        // Setup form validation
        setupFormValidation: function() {
            // Validate headline length
            $('textarea[name="headline"]').on('input', function() {
                var length = $(this).val().length;
                var maxLength = 500;
                var warningLength = 400;
                
                var counter = $(this).siblings('.char-counter');
                if (counter.length === 0) {
                    counter = $('<div class="char-counter"></div>');
                    $(this).after(counter);
                }
                
                counter.text(length + '/' + maxLength);
                
                if (length > maxLength) {
                    counter.addClass('error').removeClass('warning');
                } else if (length > warningLength) {
                    counter.addClass('warning').removeClass('error');
                } else {
                    counter.removeClass('error warning');
                }
            });
            
            // API key format validation
            $('input[type="password"][id$="_api_key"]').on('blur', function() {
                var value = $(this).val();
                var fieldId = $(this).attr('id');
                
                if (value && !this.validateApiKey(fieldId, value)) {
                    this.showFieldError($(this), 'Invalid API key format');
                } else {
                    this.clearFieldError($(this));
                }
            }.bind(this));
        },
        
        // Validate API key formats
        validateApiKey: function(fieldId, value) {
            switch(fieldId) {
                case 'perplexity_api_key':
                    return value.startsWith('pplx-');
                case 'openai_api_key':
                    return value.startsWith('sk-');
                case 'claude_api_key':
                    return value.startsWith('sk-ant-');
                default:
                    return true;
            }
        },
        
        // Show field error
        showFieldError: function($field, message) {
            this.clearFieldError($field);
            var error = $('<div class="field-error">' + message + '</div>');
            $field.after(error);
            $field.addClass('error');
        },
        
        // Clear field error
        clearFieldError: function($field) {
            $field.removeClass('error');
            $field.siblings('.field-error').remove();
        },
        
        // Setup progress bars
        setupProgressBars: function() {
            $('.mna-progress-bar').each(function() {
                var $bar = $(this);
                var percentage = $bar.data('percentage') || 0;
                
                // Animate progress bar
                setTimeout(function() {
                    $bar.css('width', percentage + '%');
                }, 100);
            });
        },
        
        // Setup notifications
        setupNotifications: function() {
            // Auto-hide notices after 5 seconds
            $('.notice.is-dismissible').delay(5000).fadeOut();
            
            // Manual dismiss functionality
            $(document).on('click', '.notice-dismiss', function() {
                $(this).closest('.notice').fadeOut();
            });
        },
        
        // Setup AJAX handlers
        setupAjaxHandlers: function() {
            // Global AJAX error handler
            $(document).ajaxError(function(event, xhr, settings, error) {
                console.error('AJAX Error:', error);
                console.error('Response:', xhr.responseText);
                if (xhr.status === 400) {
                    console.error('Bad Request - check AJAX parameters');
                }
                // Don't show notification for every AJAX error, let specific handlers deal with it
            });
            
            // Global AJAX success handler for common responses
            $(document).ajaxSuccess(function(event, xhr, settings, data) {
                if (settings.url === ajaxurl && data && data.data && data.data.reload) {
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                }
            });
        },
        
        // Show notification
        showNotification: function(message, type, duration) {
            type = type || 'info';
            duration = duration || 4000;
            
            var notification = $('<div class="notice notice-' + type + ' is-dismissible mna-notification">' +
                '<p>' + message + '</p>' +
                '<button type="button" class="notice-dismiss">' +
                '<span class="screen-reader-text">Dismiss this notice.</span>' +
                '</button>' +
                '</div>');
            
            $('.wrap h1').after(notification);
            
            // Auto-dismiss
            setTimeout(function() {
                notification.fadeOut(function() {
                    $(this).remove();
                });
            }, duration);
        },
        
        // Loading state helpers
        setLoading: function($element, state) {
            if (state) {
                $element.addClass('mna-loading').prop('disabled', true);
                if ($element.is('button')) {
                    $element.data('original-text', $element.text()).text('Loading...');
                }
            } else {
                $element.removeClass('mna-loading').prop('disabled', false);
                if ($element.is('button') && $element.data('original-text')) {
                    $element.text($element.data('original-text'));
                }
            }
        },
        
        // Confirm dialog helper
        confirm: function(message, callback) {
            if (confirm(message)) {
                callback();
            }
        },
        
        // Format numbers
        formatNumber: function(num) {
            return new Intl.NumberFormat().format(num);
        },
        
        // Format time
        formatTime: function(seconds) {
            if (seconds < 60) {
                return seconds.toFixed(1) + 's';
            } else if (seconds < 3600) {
                return Math.floor(seconds / 60) + 'm ' + Math.floor(seconds % 60) + 's';
            } else {
                return Math.floor(seconds / 3600) + 'h ' + Math.floor((seconds % 3600) / 60) + 'm';
            }
        },
        
        // Debounce function
        debounce: function(func, wait, immediate) {
            var timeout;
            return function() {
                var context = this, args = arguments;
                var later = function() {
                    timeout = null;
                    if (!immediate) func.apply(context, args);
                };
                var callNow = immediate && !timeout;
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
                if (callNow) func.apply(context, args);
            };
        },
        
        // Local storage helpers
        storage: {
            get: function(key) {
                try {
                    return JSON.parse(localStorage.getItem('mna_' + key));
                } catch (e) {
                    return null;
                }
            },
            
            set: function(key, value) {
                try {
                    localStorage.setItem('mna_' + key, JSON.stringify(value));
                    return true;
                } catch (e) {
                    return false;
                }
            },
            
            remove: function(key) {
                try {
                    localStorage.removeItem('mna_' + key);
                    return true;
                } catch (e) {
                    return false;
                }
            }
        }
    };
    
    // Page-specific functionality
    var PageHandlers = {
        
        // Dashboard page
        dashboard: function() {
            console.log('Dashboard page loaded');
            
            // Refresh stats every 30 seconds
            setInterval(function() {
                MNA_Admin.refreshDashboardStats();
            }, 30000);
        },
        
        // Headlines page
        headlines: function() {
            console.log('Headlines page loaded');
            
            // Auto-save draft headlines
            var draftSave = MNA_Admin.debounce(function() {
                var headline = $('#headline').val();
                if (headline.length > 10) {
                    MNA_Admin.storage.set('draft_headline', {
                        headline: headline,
                        timestamp: Date.now()
                    });
                }
            }, 1000);
            
            $('#headline').on('input', draftSave);
            
            // Restore draft on page load
            var draft = MNA_Admin.storage.get('draft_headline');
            if (draft && (Date.now() - draft.timestamp) < 86400000) { // 24 hours
                $('#headline').val(draft.headline);
            }
        },
        
        // Review page
        review: function() {
            console.log('Review page loaded');
            
            // Keyboard shortcuts
            $(document).on('keydown', function(e) {
                if (e.ctrlKey || e.metaKey) {
                    switch(e.which) {
                        case 13: // Ctrl+Enter = Approve
                            $('.approve-article:first').click();
                            e.preventDefault();
                            break;
                        case 83: // Ctrl+S = Save notes
                            $('.save-notes:first').click();
                            e.preventDefault();
                            break;
                    }
                }
            });
        },
        
        // Settings page
        settings: function() {
            console.log('Settings page loaded');
            
            // Warn about unsaved changes
            var formChanged = false;
            $('form input, form select, form textarea').on('change', function() {
                formChanged = true;
            });
            
            $(window).on('beforeunload', function(e) {
                if (formChanged) {
                    return 'You have unsaved changes. Are you sure you want to leave?';
                }
            });
            
            $('form').on('submit', function() {
                formChanged = false;
            });
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        MNA_Admin.init();
        
        // Detect current page and run page-specific handlers
        var page = $('body').attr('class').match(/toplevel_page_([\w-]+)/);
        if (page) {
            var pageName = page[1].replace('medical-news-automation', 'dashboard');
            if (PageHandlers[pageName]) {
                PageHandlers[pageName]();
            }
        }
        
        // Check for page parameter
        var urlParams = new URLSearchParams(window.location.search);
        var currentPage = urlParams.get('page');
        if (currentPage) {
            var handler = currentPage.replace('mna-', '').replace('medical-news-automation', 'dashboard');
            if (PageHandlers[handler]) {
                PageHandlers[handler]();
            }
        }
    });
    
    // Extend MNA_Admin with dashboard-specific methods
    $.extend(MNA_Admin, {
        refreshDashboardStats: function() {
            $.post(ajaxurl, {
                action: 'mna_get_dashboard_stats',
                nonce: mna_ajax.nonce
            }, function(response) {
                if (response.success) {
                    // Update stats without full page reload
                    var stats = response.data;
                    $('.mna-stat-box.pending h3').text(stats.pending_headlines);
                    $('.mna-stat-box.processing h3').text(stats.processing_headlines);
                    $('.mna-stat-box.review h3').text(stats.articles_for_review);
                    $('.mna-stat-box.published h3').text(stats.published_today);
                }
            });
        }
    });
    
})(jQuery);