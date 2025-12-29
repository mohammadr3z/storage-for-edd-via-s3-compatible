jQuery(function ($) {
    $('.save-s3cs-file').click(function () {
        var filename = $(this).data('s3cs-filename');
        var fileurl = s3cs_edd_url_prefix + $(this).data('s3cs-link');
        var success = false;

        if (parent.window && parent.window !== window) {
            if (parent.window.edd_filename && parent.window.edd_fileurl) {
                $(parent.window.edd_filename).val(filename);
                $(parent.window.edd_fileurl).val(fileurl);
                success = true;
                try { parent.window.tb_remove(); } catch (e) { parent.window.tb_remove(); }
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

    // Search functionality for S3 files
    $('#s3cs-file-search').on('input', function () {
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

    // Clear search functionality
    $('#s3cs-clear-search').click(function () {
        $('#s3cs-file-search').val('').trigger('input');
    });

    // Add keyboard shortcut for search (Ctrl+F or Cmd+F)
    $(document).keydown(function (e) {
        if ((e.ctrlKey || e.metaKey) && e.keyCode === 70) {
            e.preventDefault();
            $('#s3cs-file-search').focus();
        }
    });

    // Toggle upload form
    $('#s3cs-toggle-upload').click(function () {
        $('#s3cs-upload-section').slideToggle(200);
    });
});
