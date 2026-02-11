jQuery(function ($) {
    // File size validation - using existing s3cs_edd_max_upload_size
    $(document).on('change', 'input[name="s3cs_edd_file"]', function () {
        if (this.files && this.files[0]) {
            var fileSize = this.files[0].size;
            var maxSize = s3cs_edd_max_upload_size;
            if (fileSize > maxSize) {
                alert(s3cs_edd_i18n.file_size_too_large + ' ' + (maxSize / 1024 / 1024).toFixed(2) + 'MB');
                this.value = '';
            }
        }
    });

    // Helper to show notice
    function showUploadError(message) {
        $('.s3cs-notice').remove();
        var errorHtml = '<div class="s3cs-notice warning"><p>' + message + '</p></div>';
        var $uploadSection = $('#s3cs-upload-section');
        if ($uploadSection.length && $uploadSection.is(':visible')) {
            $uploadSection.prepend(errorHtml);
        } else {
            // Fallback
            $('#s3cs-modal-container').prepend(errorHtml);
        }
    }

    // Handle Upload Form Submission
    $(document).on('submit', '.s3cs-upload-form', function (e) {
        e.preventDefault();

        var $form = $(this);
        var $btn = $form.find('input[type="submit"]');
        var $fileInput = $form.find('input[name="s3cs_edd_file"]');
        var file = $fileInput[0].files[0];

        if (!file) {
            showUploadError(s3cs_edd_i18n.file_selected_error || 'Please select a file.');
            return;
        }

        // Prepare FormData
        var formData = new FormData();
        formData.append('action', 's3cs_edd_ajax_upload');
        formData.append('s3cs_edd_file', file);
        formData.append('s3cs_edd_nonce', $form.find('input[name="s3cs_edd_nonce"]').val());
        // Path input is updated by media library JS on navigation
        formData.append('s3cs_edd_path', $form.find('input[name="s3cs_edd_path"]').val());

        $btn.prop('disabled', true).val('Uploading...');

        // Remove previous notices
        $('.s3cs-notice').remove();

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                if (response.success) {
                    // Refresh library
                    if (window.S3CSMediaLibrary) {
                        // Reload current path (which is what we uploaded to)
                        var currentPath = $form.find('input[name="s3cs_edd_path"]').val();

                        // Wait for content to be loaded before showing notice
                        $(document).one('s3cs_edd_content_loaded', function () {
                            // Create success notice HTML
                            var filename = response.data.filename;
                            var path = response.data.path;

                            if (path.charAt(0) === '/') {
                                path = path.substring(1);
                            }

                            var successHtml =
                                '<div class="s3cs-notice success">' +
                                '<h4>' + (response.data.message || 'Upload Successful') + '</h4>' +
                                '<p>File <strong>' + filename + '</strong> uploaded successfully.</p>' +
                                '<p>' +
                                '<button type="button" class="button button-primary save-s3cs-file" ' +
                                'data-s3cs-filename="' + filename + '" ' +
                                'data-s3cs-link="' + (response.data.s3cs_link || path) + '">' +
                                'Use this file' +
                                '</button>' +
                                '</p>' +
                                '</div>';

                            // Inject notice after the upload section (or before table if upload section hidden)
                            var $uploadSection = $('#s3cs-upload-section');
                            if ($uploadSection.length) {
                                $uploadSection.after(successHtml);
                            } else {
                                // Fallback: prepend to container
                                $('#s3cs-modal-container').prepend(successHtml);
                            }
                        });

                        window.S3CSMediaLibrary.load(currentPath);
                    }

                    // Reset form
                    $fileInput.val('');
                    // Remove existing notices
                    $('.s3cs-notice, .s3cs-no-search-results').remove();
                } else {
                    var errorMsg = 'Unknown error';
                    if (response.data) {
                        if (typeof response.data === 'string') {
                            errorMsg = response.data;
                        } else if (typeof response.data === 'object') {
                            if (response.data.message) {
                                errorMsg = response.data.message;
                            } else if (Array.isArray(response.data) && response.data.length > 0) {
                                errorMsg = response.data[0];
                            } else {
                                var values = Object.values(response.data);
                                if (values.length > 0) {
                                    errorMsg = values.join(', ');
                                }
                            }
                        }
                    }
                    showUploadError('Upload Error: ' + errorMsg);
                }
            },
            error: function (xhr, status, error) {
                var errorDetails = '';
                if (xhr.status) {
                    errorDetails += ' (Status: ' + xhr.status + ')';
                }
                if (xhr.responseText) {
                    var text = xhr.responseText.substring(0, 100);
                    errorDetails += '<br>Response: ' + text.replace(/</g, '&lt;').replace(/>/g, '&gt;');
                }
                showUploadError('Connection error during upload.' + errorDetails);
            },
            complete: function () {
                $btn.prop('disabled', false).val('Upload');
            }
        });
    });
});