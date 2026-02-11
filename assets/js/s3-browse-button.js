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

        var currentUrl = window.s3cs_current_url_input.val();
        var folderPath = '';
        // Using s3cs_browse_button object which should be localized
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

        // Open Modal
        // Pass true for isPath to indicate this is a pre-filled path
        S3CSModal.open(folderPath, s3cs_browse_button.modal_title, true);
    });
});
