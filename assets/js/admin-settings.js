jQuery(document).ready(function ($) {
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

    $('.edd-s3cs-credential').on('input change', function () {
        s3cs_checkCredentials();
    });

    // Custom Region toggle
    function s3cs_toggleCustomRegion() {
        var $checkbox = $('input[name="edd_settings[s3cs_edd_custom_region_enabled]"]');
        var $regionRow = $('input[name="edd_settings[s3cs_edd_custom_region]"]').closest('tr');

        if ($checkbox.is(':checked')) {
            $regionRow.show();
        } else {
            $regionRow.hide();
        }
    }

    s3cs_toggleCustomRegion();

    $('input[name="edd_settings[s3cs_edd_custom_region_enabled]"]').on('change', function () {
        s3cs_toggleCustomRegion();
    });
});