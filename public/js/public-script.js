/**
 * MSKD Public Scripts
 *
 * @package MSKD
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Subscribe form handler
        $('.mskd-subscribe-form').on('submit', function(e) {
            e.preventDefault();

            var $form = $(this);
            var $button = $form.find('.mskd-submit-btn');
            var $message = $form.find('.mskd-form-message');
            var originalText = $button.text();

            // Disable button and show loading
            $button.prop('disabled', true).text(mskd_public.strings.subscribing);
            $message.hide().removeClass('success error');

            $.ajax({
                url: mskd_public.ajax_url,
                type: 'POST',
                data: {
                    action: 'mskd_subscribe',
                    nonce: mskd_public.nonce,
                    email: $form.find('input[name="email"]').val(),
                    first_name: $form.find('input[name="first_name"]').val(),
                    last_name: $form.find('input[name="last_name"]').val(),
                    list_id: $form.find('input[name="list_id"]').val()
                },
                success: function(response) {
                    if (response.success) {
                        $message.addClass('success').text(response.data.message).fadeIn();
                        $form.find('input[type="text"], input[type="email"]').val('');
                    } else {
                        $message.addClass('error').text(response.data.message).fadeIn();
                    }
                },
                error: function() {
                    $message.addClass('error').text(mskd_public.strings.error).fadeIn();
                },
                complete: function() {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        });

        // Copy shortcode button handler
        $('.mskd-copy-btn').on('click', function() {
            var $button = $(this);
            var shortcode = $button.data('shortcode');
            var originalText = $button.text();

            // Copy to clipboard
            if (navigator.clipboard && window.isSecureContext) {
                // Modern async clipboard API
                navigator.clipboard.writeText(shortcode).then(function() {
                    showCopySuccess($button, originalText);
                }).catch(function() {
                    fallbackCopyToClipboard(shortcode, $button, originalText);
                });
            } else {
                // Fallback for older browsers
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
                $button.text(mskd_public.strings.copy_error || 'Error');
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
            $button.text(mskd_public.strings.copied || 'Copied!');
            $button.addClass('mskd-copy-success');
            setTimeout(function() {
                $button.text(originalText);
                $button.removeClass('mskd-copy-success');
            }, 2000);
        }
    });

})(jQuery);
