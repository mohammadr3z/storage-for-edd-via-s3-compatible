/**
 * S3 Browse Button Script
 * Handles S3 browse button click events in EDD download files section
 */
jQuery(function ($) {
    // Event delegation for all browse buttons
    $(document).on('click', '.s3cs_browse_button', function (e) {
        e.preventDefault();

        var $btn = $(this);
        var $row = $btn.closest('.edd_repeatable_row');

        // Store references to the input fields for this row
        window.s3cs_current_row = $row;
        window.s3cs_current_name_input = $row.find('input[name^="edd_download_files"][name$="[name]"]');
        window.s3cs_current_url_input = $row.find('input[name^="edd_download_files"][name$="[file]"]');

        // Context-Aware: Extract folder path from current URL
        var currentUrl = window.s3cs_current_url_input.val();
        var folderPath = '';
        var urlPrefix = s3cs_browse_button.url_prefix;

        if (currentUrl && currentUrl.indexOf(urlPrefix) === 0) {
            // Remove prefix
            var path = currentUrl.substring(urlPrefix.length);
            // Remove filename, keep folder path
            var lastSlash = path.lastIndexOf('/');
            if (lastSlash !== -1) {
                folderPath = path.substring(0, lastSlash);
            }
        }

        var modalUrl = s3cs_browse_button.modal_url + '&_wpnonce=' + s3cs_browse_button.nonce;
        if (folderPath) {
            modalUrl += '&path=' + encodeURIComponent(folderPath);
        }

        // Open Modal
        S3CSModal.open(modalUrl, s3cs_browse_button.modal_title);
    });
});
