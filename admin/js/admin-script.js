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
    });

})(jQuery);
