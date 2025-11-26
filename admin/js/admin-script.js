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

        // SMTP Test Button - Store original text before binding click handler
        var $smtpTestButton = $('#mskd-smtp-test');
        if ($smtpTestButton.length) {
            $smtpTestButton.data('original-text', $smtpTestButton.text());
        }

        $smtpTestButton.on('click', function(e) {
            e.preventDefault();

            var $button = $(this);
            var $result = $('#mskd-smtp-test-result');

            $button.prop('disabled', true).text(mskd_admin.strings.sending);
            $result.removeClass('mskd-smtp-success mskd-smtp-error').text('');

            $.ajax({
                url: mskd_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'mskd_test_smtp',
                    nonce: mskd_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $result.addClass('mskd-smtp-success').text(response.data.message);
                    } else {
                        $result.addClass('mskd-smtp-error').text(response.data.message);
                    }
                },
                error: function() {
                    $result.addClass('mskd-smtp-error').text(mskd_admin.strings.error);
                },
                complete: function() {
                    $button.prop('disabled', false).text($button.data('original-text'));
                }
            });
        });
    });

})(jQuery);
