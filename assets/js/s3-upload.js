jQuery(function ($) {
    // Handle "Use this file" button after upload success
    $('#s3cs_edd_save_link').click(function () {
        var filename = $(this).data('s3-fn');
        var fileurl = s3cs_edd_url_prefix + $(this).data('s3-path');

        // Use new modal references
        if (parent.window && parent.window.s3cs_current_name_input && parent.window.s3cs_current_url_input) {
            parent.window.s3cs_current_name_input.val(filename);
            parent.window.s3cs_current_url_input.val(fileurl);
            // Close the modal
            if (parent.window.S3CSModal) {
                parent.window.S3CSModal.close();
            }
        }
        return false;
    });

    $('input[name="s3cs_edd_file"]').on('change', function () {
        var fileSize = this.files[0].size;
        var maxSize = s3cs_edd_max_upload_size;
        if (fileSize > maxSize) {
            alert(s3cs_edd_i18n.file_size_too_large + ' ' + (maxSize / 1024 / 1024) + 'MB');
            this.value = '';
        }
    });
});