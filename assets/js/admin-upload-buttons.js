jQuery(function($){
    $('body').on('click', '.edd_upload_file_button', function() {
        // Store references to the input fields in the window object
        window.edd_fileurl = $(this).parent().prev().find('input');
        window.edd_filename = $(this).parent().parent().parent().prev().find('input');
        
        // Debug: Log the elements to console for troubleshooting
        console.log('EDD File URL input:', window.edd_fileurl);
        console.log('EDD Filename input:', window.edd_filename);
    });
    
    // Also handle the S3 file selection for upload tab
    $('#s3cs_edd_save_link').click(function() {
        if (window.edd_filename && window.edd_fileurl) {
            $(window.edd_filename).val($(this).data('s3-fn'));
            $(window.edd_fileurl).val(s3cs_edd_url_prefix + $(this).data('s3-path'));
            try { parent.window.tb_remove(); } catch(e) { window.tb_remove(); }
        }
        return false;
    });
});