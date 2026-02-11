/**
 * S3 Media Library JavaScript (AJAX Version)
 */
window.S3CSMediaLibrary = (function ($) {
    var $container;

    // Initialize events using delegation
    function initEvents() {
        $container = $('#s3cs-modal-container');

        // Folder Navigation
        $(document).on('click', '.s3cs-folder-row a, .s3cs-breadcrumb-nav a', function (e) {
            e.preventDefault();
            var path = $(this).data('path');
            if (path !== undefined) {
                loadLibrary(path);
            }
        });

        // File Selection
        $(document).on('click', '.save-s3cs-file', function (e) {
            e.preventDefault();
            var filename = $(this).data('s3cs-filename');
            // Ensure we use the prefix from correct variable
            var fileurl = s3cs_edd_url_prefix + $(this).data('s3cs-link');
            selectFile(filename, fileurl);
        });

        // Search
        $(document).on('input search', '#s3cs-file-search', function () {
            var searchTerm = $(this).val().toLowerCase();
            var $fileRows = $('.s3cs-files-table tbody tr');
            var visibleCount = 0;

            $fileRows.each(function () {
                var $row = $(this);

                var fileName = $row.find('.file-name').text().toLowerCase();

                if (fileName.indexOf(searchTerm) !== -1) {
                    $row.show();
                    visibleCount++;
                } else {
                    $row.hide();
                }
            });

            // Show/hide "no results" message
            var $noResults = $('.s3cs-no-search-results');
            if (visibleCount === 0 && searchTerm.length > 0) {
                if ($noResults.length === 0) {
                    $('.s3cs-files-table').after('<div class="s3cs-no-search-results" style="padding: 20px; text-align: center; color: #666; font-style: italic;">No files found matching your search.</div>');
                } else {
                    $noResults.show();
                }
            } else {
                $noResults.hide();
            }
        });

        // Keyboard shortcut for search
        $(document).on('keydown', function (e) {
            if ($('#s3cs-modal-overlay').is(':visible') && (e.ctrlKey || e.metaKey) && e.keyCode === 70) {
                e.preventDefault();
                $('#s3cs-file-search').focus();
            }
        });

        // Toggle upload form
        $(document).on('click', '#s3cs-toggle-upload', function () {
            $('#s3cs-upload-section').slideToggle(200);
        });
    }

    // Helper to show notice
    function showError(message) {
        $('.s3cs-notice').remove();
        var errorHtml = '<div class="s3cs-notice warning"><p>' + message + '</p></div>';
        if ($('.s3cs-files-table').length) {
            $('.s3cs-files-table').before(errorHtml);
        } else {
            $('#s3cs-modal-container').prepend(errorHtml);
        }
    }

    // Load library content via AJAX
    function loadLibrary(path) {
        $container = $('#s3cs-modal-container'); // Refresh ref

        if (path && typeof path === 'string' && path.indexOf('?') !== -1) {
            try {
                var urlObj = new URL(path, window.location.origin);
                var params = new URLSearchParams(urlObj.search);
                if (params.has('path')) {
                    path = decodeURIComponent(params.get('path'));
                } else {
                    path = ''; // Default to root
                }
            } catch (e) {
                if (path.indexOf('path=') !== -1) {
                    var match = path.match(/path=([^&]*)/);
                    if (match) {
                        path = decodeURIComponent(match[1]);
                    }
                } else {
                    path = '';
                }
            }
        }

        // Check if container is visible (navigation mode)
        if ($container.is(':visible')) {
            // Remove notices
            $container.find('.s3cs-notice, .s3cs-no-search-results').remove();

            // If table exists, just replace tbody content with skeleton
            var $table = $container.find('.s3cs-files-table');
            if ($table.length && window.S3CSModal) {
                $table.addClass('s3cs-skeleton-table');
                $table.find('tbody').html(S3CSModal.getSkeletonRows());
            }
        }

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 's3cs_edd_get_library',
                path: path,
                _wpnonce: s3cs_browse_button.nonce
            },
            success: function (response) {
                if (response.success) {
                    $container.html(response.data.html);
                    // Update upload path hidden input
                    $('input[name="s3cs_edd_path"]').val(path);

                    // Notify modal to hide skeleton if it was initial load
                    $(document).trigger('s3cs_edd_content_loaded');
                } else {
                    showError('Error: ' + (response.data || 'Unknown error'));
                }
            },
            error: function () {
                showError('Ajax connection error');
            }
        });
    }

    function selectFile(filename, fileurl) {
        if (window.s3cs_current_name_input && window.s3cs_current_url_input) {
            $(window.s3cs_current_name_input).val(filename);
            $(window.s3cs_current_url_input).val(fileurl);

            // Close modal
            if (window.S3CSModal) {
                window.S3CSModal.close();
            }
        } else {
            alert(s3cs_edd_i18n.file_selected_error);
        }
    }

    // Auto-init on script load
    $(document).ready(function () {
        initEvents();
    });

    return {
        load: loadLibrary,
        reload: function () {
            loadLibrary('');
        }
    };

})(jQuery);
