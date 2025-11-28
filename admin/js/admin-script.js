/**
 * MSKD Admin Scripts
 *
 * @package MSKD
 */

(function($) {
    'use strict';

    /**
     * Initialize SlimSelect on list dropdowns
     */
    function initSlimSelect() {
        var selectElement = document.querySelector('.mskd-slimselect-lists');
        
        if (!selectElement) {
            return;
        }

        // Check if SlimSelect is available
        if (typeof SlimSelect === 'undefined') {
            console.warn('MSKD: SlimSelect not loaded, retrying...');
            setTimeout(initSlimSelect, 100);
            return;
        }

        // Initialize SlimSelect
        new SlimSelect({
            select: selectElement,
            settings: {
                placeholderText: (typeof mskd_admin !== 'undefined' && mskd_admin.strings.select_lists_placeholder) || 'Select lists...',
                searchPlaceholderText: (typeof mskd_admin !== 'undefined' && mskd_admin.strings.search_placeholder) || 'Search...',
                searchText: (typeof mskd_admin !== 'undefined' && mskd_admin.strings.no_results) || 'No results found',
                allowDeselect: true,
                closeOnSelect: false,
                showSearch: true
            }
        });
    }

    $(document).ready(function() {
        // Initialize SlimSelect for lists multi-select
        initSlimSelect();

        // Confirm delete
        $('.mskd-delete-link').on('click', function(e) {
            if (!confirm(mskd_admin.strings.confirm_delete)) {
                e.preventDefault();
                return false;
            }
        });

        // Auto-dismiss notices after 5 seconds
        setTimeout(function() {
            $('.mskd-wrap .notice.is-dismissible').fadeOut();
        }, 5000);

        // SMTP Settings Toggle
        function toggleSmtpSettings() {
            var isEnabled = $('#smtp_enabled').is(':checked');
            if (isEnabled) {
                $('.mskd-smtp-setting').show();
            } else {
                $('.mskd-smtp-setting').hide();
            }
            toggleSmtpAuthSettings();
        }

        function toggleSmtpAuthSettings() {
            var isAuthEnabled = $('#smtp_auth').is(':checked');
            var isSmtpEnabled = $('#smtp_enabled').is(':checked');
            if (isAuthEnabled && isSmtpEnabled) {
                $('.mskd-smtp-auth-setting').show();
            } else {
                $('.mskd-smtp-auth-setting').hide();
            }
        }

        // Initial toggle on page load
        if ($('#smtp_enabled').length) {
            toggleSmtpSettings();

            $('#smtp_enabled').on('change', toggleSmtpSettings);
            $('#smtp_auth').on('change', toggleSmtpAuthSettings);
        }

        // Schedule Type Toggle
        function toggleScheduleFields() {
            var scheduleType = $('#schedule_type').val();
            var $absoluteFields = $('.mskd-schedule-absolute');
            var $relativeFields = $('.mskd-schedule-relative');
            var $submitBtn = $('.mskd-submit-btn');

            // Hide all schedule-specific fields first
            $absoluteFields.hide();
            $relativeFields.hide();

            // Show relevant fields based on selection
            if (scheduleType === 'absolute') {
                $absoluteFields.show();
            } else if (scheduleType === 'relative') {
                $relativeFields.show();
            }

            // Update button text
            if ($submitBtn.length && $submitBtn.data('send-now') && $submitBtn.data('schedule')) {
                if (scheduleType === 'now') {
                    $submitBtn.val($submitBtn.data('send-now'));
                } else {
                    $submitBtn.val($submitBtn.data('schedule'));
                }
            }
        }

        // Initial toggle on page load
        if ($('#schedule_type').length) {
            toggleScheduleFields();
            $('#schedule_type').on('change', toggleScheduleFields);
        }

        // Datetime picker - enforce 10-minute intervals
        if ($('.mskd-datetime-picker').length) {
            $('.mskd-datetime-picker').on('change', function() {
                var $input = $(this);
                var value = $input.val();
                
                if (value) {
                    // Parse the datetime
                    var date = new Date(value);
                    var minutes = date.getMinutes();
                    
                    // Round to nearest 10 minutes
                    var roundedMinutes = Math.round(minutes / 10) * 10;
                    if (roundedMinutes >= 60) {
                        date.setHours(date.getHours() + 1);
                        roundedMinutes = 0;
                    }
                    date.setMinutes(roundedMinutes);
                    date.setSeconds(0);
                    
                    // Format back to datetime-local format (YYYY-MM-DDTHH:MM)
                    var year = date.getFullYear();
                    var month = String(date.getMonth() + 1).padStart(2, '0');
                    var day = String(date.getDate()).padStart(2, '0');
                    var hours = String(date.getHours()).padStart(2, '0');
                    var mins = String(date.getMinutes()).padStart(2, '0');
                    
                    var formattedDate = year + '-' + month + '-' + day + 'T' + hours + ':' + mins;
                    $input.val(formattedDate);
                }
            });

            // Validate minimum datetime on form submit
            $('form').on('submit', function() {
                var $scheduleType = $('#schedule_type');
                var $datetimePicker = $('.mskd-datetime-picker');
                
                if ($scheduleType.length && $scheduleType.val() === 'absolute' && $datetimePicker.length) {
                    var selectedDate = new Date($datetimePicker.val());
                    var now = new Date();
                    
                    if (selectedDate <= now) {
                        alert(mskd_admin.strings.datetime_past || 'Моля, изберете бъдеща дата и час.');
                        return false;
                    }
                }
                return true;
            });
        }

        // SMTP Test Button
        $(document).on('click', '#mskd-smtp-test', function(e) {
            e.preventDefault();

            var $button = $(this);
            var $result = $('#mskd-smtp-test-result');
            var originalText = $button.data('original-text');

            // Store original text if not already stored
            if (!originalText) {
                originalText = $button.text();
                $button.data('original-text', originalText);
            }

            $button.prop('disabled', true).text(mskd_admin.strings.sending);
            $result.removeClass('mskd-smtp-success mskd-smtp-error').text('');

            $.ajax({
                url: mskd_admin.ajax_url,
                type: 'POST',
                timeout: 30000,
                data: {
                    action: 'mskd_test_smtp',
                    nonce: mskd_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $result.addClass('mskd-smtp-success').text(response.data.message);
                    } else {
                        var errorMsg = (response.data && response.data.message) ? response.data.message : mskd_admin.strings.error;
                        $result.addClass('mskd-smtp-error').text(errorMsg);
                    }
                },
                error: function(xhr, status, error) {
                    var errorMsg = mskd_admin.strings.error;
                    if (status === 'timeout') {
                        errorMsg = mskd_admin.strings.timeout || 'Времето за изчакване изтече.';
                    }
                    $result.addClass('mskd-smtp-error').text(errorMsg);
                },
                complete: function() {
                    $button.prop('disabled', false).text($button.data('original-text'));
                }
            });
        });

        // Truncate Subscribers Button
        $(document).on('click', '#mskd-truncate-subscribers', function(e) {
            e.preventDefault();
            handleTruncate($(this), 'mskd_truncate_subscribers', '#mskd-truncate-subscribers-result', mskd_admin.strings.confirm_truncate_subscribers);
        });

        // Truncate Lists Button
        $(document).on('click', '#mskd-truncate-lists', function(e) {
            e.preventDefault();
            handleTruncate($(this), 'mskd_truncate_lists', '#mskd-truncate-lists-result', mskd_admin.strings.confirm_truncate_lists);
        });

        // Truncate Queue Button
        $(document).on('click', '#mskd-truncate-queue', function(e) {
            e.preventDefault();
            handleTruncate($(this), 'mskd_truncate_queue', '#mskd-truncate-queue-result', mskd_admin.strings.confirm_truncate_queue);
        });

        /**
         * Handle truncate action with confirmation
         */
        function handleTruncate($button, action, resultSelector, confirmMessage) {
            if (!confirm(confirmMessage)) {
                return;
            }

            var $result = $(resultSelector);
            var originalText = $button.data('original-text');

            // Store original text if not already stored
            if (!originalText) {
                originalText = $button.text();
                $button.data('original-text', originalText);
            }

            $button.prop('disabled', true).text(mskd_admin.strings.processing);
            $result.removeClass('mskd-truncate-success mskd-truncate-error').text('');

            $.ajax({
                url: mskd_admin.ajax_url,
                type: 'POST',
                timeout: 30000,
                data: {
                    action: action,
                    nonce: mskd_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $result.addClass('mskd-truncate-success').text(response.data.message);
                    } else {
                        var errorMsg = (response.data && response.data.message) ? response.data.message : mskd_admin.strings.error;
                        $result.addClass('mskd-truncate-error').text(errorMsg);
                    }
                },
                error: function(xhr, status, error) {
                    var errorMsg = mskd_admin.strings.error;
                    $result.addClass('mskd-truncate-error').text(errorMsg);
                },
                complete: function() {
                    $button.prop('disabled', false).text($button.data('original-text'));
                }
            });
        }

        // =====================================================================
        // Import/Export Page
        // =====================================================================

        // Only run import/export logic if on the import/export page
        if ($('.mskd-import-export-container').length) {
            // Toggle subscriber-specific export options
            $('#export_type').on('change', function() {
                if ($(this).val() === 'subscribers') {
                    $('.mskd-export-subscribers-options').show();
                } else {
                    $('.mskd-export-subscribers-options').hide();
                }
            });

            // Toggle subscriber-specific import options
            $('#import_type').on('change', function() {
                if ($(this).val() === 'subscribers') {
                    $('.mskd-import-subscribers-options').show();
                } else {
                    $('.mskd-import-subscribers-options').hide();
                }
            });

            // Update accepted file types based on format
            $('#import_format').on('change', function() {
                var format = $(this).val();
                $('#import_file').attr('accept', '.' + format);
            });
        }

        // =====================================================================
        // Shortcodes Page - Copy functionality
        // =====================================================================

        $('.mskd-copy-btn').on('click', function() {
            var $button = $(this);
            var targetId = $button.data('target');
            var $codeElement = $('#' + targetId);
            var shortcode = $codeElement.text();
            var originalText = $button.text();

            // Copy to clipboard
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(shortcode).then(function() {
                    showCopySuccess($button, originalText);
                }).catch(function() {
                    fallbackCopyToClipboard(shortcode, $button, originalText);
                });
            } else {
                fallbackCopyToClipboard(shortcode, $button, originalText);
            }
        });

        /**
         * Fallback copy method for older browsers
         */
        function fallbackCopyToClipboard(text, $button, originalText) {
            var textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-9999px';
            textArea.style.top = '-9999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();

            try {
                document.execCommand('copy');
                showCopySuccess($button, originalText);
            } catch (err) {
                $button.text(mskd_admin.strings.copy_error || 'Error');
                setTimeout(function() {
                    $button.text(originalText);
                }, 2000);
            }

            document.body.removeChild(textArea);
        }

        /**
         * Show copy success feedback
         */
        function showCopySuccess($button, originalText) {
            $button.text(mskd_admin.strings.copied || 'Copied!');
            $button.addClass('button-primary');
            setTimeout(function() {
                $button.text(originalText);
                $button.removeClass('button-primary');
            }, 2000);
        }

        // =====================================================================
        // Email Content Accordion Toggle
        // =====================================================================

        $('.mskd-accordion-toggle').on('click', function() {
            var $toggle = $(this);
            var $content = $toggle.next('.mskd-accordion-content');
            var isExpanded = $toggle.attr('aria-expanded') === 'true';

            if (isExpanded) {
                $content.slideUp(200);
                $toggle.attr('aria-expanded', 'false');
            } else {
                $content.slideDown(200);
                $toggle.attr('aria-expanded', 'true');
            }
        });
    });

})(jQuery);
