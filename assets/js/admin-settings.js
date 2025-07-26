jQuery(document).ready(function($) {
    // Function to check if credentials are filled
    function s3cs_checkCredentials() {
        var accessKey = $('input[name="s3cs_edd_access_key"]').val();
        var secretKey = $('input[name="s3cs_edd_secret_key"]').val();
        var endpoint = $('input[name="s3cs_edd_endpoint"]').val();
        
        var bucketRow = $('select[name="s3cs_edd_bucket"]').closest('tr');
        var bucketSelect = $('select[name="s3cs_edd_bucket"]');
        
        if (accessKey && secretKey && endpoint) {
            // Enable bucket selection
            bucketRow.removeClass('edd-s3cs-bucket-disabled');
            bucketSelect.prop('disabled', false);
        } else {
            // Disable bucket selection
            bucketRow.addClass('edd-s3cs-bucket-disabled');
            bucketSelect.prop('disabled', true);
            bucketSelect.val(''); // Clear selection
        }
    }
    
    // Check on page load
    s3cs_checkCredentials();
    
    // Check when credentials change
    $('.edd-s3cs-credential').on('input change', function() {
        s3cs_checkCredentials();
    });
});