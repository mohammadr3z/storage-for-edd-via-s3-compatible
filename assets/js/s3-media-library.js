jQuery(function($){
    $('.save-s3cs-file').click(function() {
        var filename = $(this).data('s3cs-filename');
        var fileurl = s3cs_edd_url_prefix + $(this).data('s3cs-link');
        var success = false;
        
        if (parent.window && parent.window !== window) {
            if (parent.window.edd_filename && parent.window.edd_fileurl) {
                $(parent.window.edd_filename).val(filename);
                $(parent.window.edd_fileurl).val(fileurl);
                success = true;
                try { parent.window.tb_remove(); } catch(e) { parent.window.tb_remove(); }
            }
        } else {
            if (window.edd_filename && window.edd_fileurl) {
                $(window.edd_filename).val(filename);
                $(window.edd_fileurl).val(fileurl);
                success = true;
            }
        }
        
        if (!success) {
            var $filenameInput = $('input[name*="edd_download_files"][name*="[name]"]').last();
            var $fileurlInput = $('input[name*="edd_download_files"][name*="[file]"]').last();
            
            if ($filenameInput.length && $fileurlInput.length) {
                $filenameInput.val(filename);
                $fileurlInput.val(fileurl);
                success = true;
            }
        }
        
        if (success) {
            alert(s3cs_edd_i18n.file_selected_success);
        } else {
            alert(s3cs_edd_i18n.file_selected_error);
        }
        
        return false;
    });
});
