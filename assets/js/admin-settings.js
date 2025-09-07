jQuery(document).ready(function($) {
    function s3cs_checkCredentials() {
        var accessKey = $('input[name="s3cs_edd_access_key"]').val();
        var secretKey = $('input[name="s3cs_edd_secret_key"]').val();
        var endpoint = $('input[name="s3cs_edd_endpoint"]').val();
        
        var bucketRow = $('select[name="s3cs_edd_bucket"]').closest('tr');
        var bucketSelect = $('select[name="s3cs_edd_bucket"]');
        
        if (accessKey && secretKey && endpoint) {
            bucketRow.removeClass('edd-s3cs-bucket-disabled');
            bucketSelect.prop('disabled', false);
        } else {
            bucketRow.addClass('edd-s3cs-bucket-disabled');
            bucketSelect.prop('disabled', true);
            bucketSelect.val('');
        }
    }
    
    s3cs_checkCredentials();
    
    $('.edd-s3cs-credential').on('input change', function() {
        s3cs_checkCredentials();
    });
});