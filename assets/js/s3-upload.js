jQuery(function($){
    $('#s3cs_edd_save_link').click(function() {
        $(parent.window.edd_filename).val($(this).data('s3-fn'));
        $(parent.window.edd_fileurl).val(s3cs_edd_url_prefix + $(this).data('s3-path'));
        parent.window.tb_remove();
    });

    $('input[name="s3cs_edd_file"]').on('change', function() {
        var fileSize = this.files[0].size;
        var maxSize = s3cs_edd_max_upload_size;
        if(fileSize > maxSize) {
            alert(s3cs_edd_i18n.file_size_too_large + ' ' + (maxSize/1024/1024) + 'MB');
            this.value = '';
        }
    });
});