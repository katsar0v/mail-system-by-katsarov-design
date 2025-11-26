/**
 * MSKD Admin Scripts
 *
 * @package MSKD
 */

(function($) {
    'use strict';

    $(document).ready(function() {
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
    });

})(jQuery);
