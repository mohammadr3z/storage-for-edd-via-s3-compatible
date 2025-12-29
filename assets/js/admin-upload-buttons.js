/**
 * Admin Upload Buttons Handler for S3
 * 
 * Extends EDD's default upload button behavior to work with S3.
 * This sets up the edd_filename and edd_fileurl variables when
 * the upload button is clicked, so the S3 library can populate them.
 */
jQuery(function ($) {
    $('body').on('click', '.edd_upload_file_button', function () {
        window.edd_fileurl = $(this).parent().prev().find('input');
        window.edd_filename = $(this).parent().parent().parent().prev().find('input');
    });

    $('#s3cs_edd_save_link').click(function () {
        if (window.edd_filename && window.edd_fileurl) {
            $(window.edd_filename).val($(this).data('s3-fn'));
            $(window.edd_fileurl).val(s3cs_edd_url_prefix + $(this).data('s3-path'));
            try { parent.window.tb_remove(); } catch (e) { window.tb_remove(); }
        }
        return false;
    });
});