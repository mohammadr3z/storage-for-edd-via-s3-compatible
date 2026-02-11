/**
 * S3CS Modal JS
 */
var S3CSModal = (function ($) {
    var $modal, $overlay, $container, $closeBtn, $skeleton;

    // Skeleton rows - shared with S3CSMediaLibrary
    var skeletonRowsHtml =
        '<tr><td><div class="s3cs-skeleton-cell" style="width: 70%;"></div></td><td><div class="s3cs-skeleton-cell" style="width: 60%;"></div></td><td><div class="s3cs-skeleton-cell" style="width: 80%;"></div></td><td><div class="s3cs-skeleton-cell" style="width: 70%;"></div></td></tr>' +
        '<tr><td><div class="s3cs-skeleton-cell" style="width: 55%;"></div></td><td><div class="s3cs-skeleton-cell" style="width: 50%;"></div></td><td><div class="s3cs-skeleton-cell" style="width: 75%;"></div></td><td><div class="s3cs-skeleton-cell" style="width: 70%;"></div></td></tr>' +
        '<tr><td><div class="s3cs-skeleton-cell" style="width: 80%;"></div></td><td><div class="s3cs-skeleton-cell" style="width: 45%;"></div></td><td><div class="s3cs-skeleton-cell" style="width: 70%;"></div></td><td><div class="s3cs-skeleton-cell" style="width: 70%;"></div></td></tr>' +
        '<tr><td><div class="s3cs-skeleton-cell" style="width: 65%;"></div></td><td><div class="s3cs-skeleton-cell" style="width: 55%;"></div></td><td><div class="s3cs-skeleton-cell" style="width: 85%;"></div></td><td><div class="s3cs-skeleton-cell" style="width: 70%;"></div></td></tr>';

    function init() {
        if ($('#s3cs-modal-overlay').length) {
            return;
        }

        // Skeleton HTML structure - uses real table with skeleton rows
        var skeletonHtml =
            '<div class="s3cs-skeleton-loader">' +
            '<div class="s3cs-header-row">' +
            '<h3 class="media-title">' + (typeof s3cs_browse_button !== 'undefined' && s3cs_browse_button.i18n_select_file || 'Select a file from S3') + '</h3>' +
            '<div class="s3cs-header-buttons">' +
            '<button type="button" class="button button-primary" id="s3cs-toggle-upload">' + (typeof s3cs_browse_button !== 'undefined' && s3cs_browse_button.i18n_upload || 'Upload File') + '</button>' +
            '</div>' +
            '</div>' +
            '<div class="s3cs-breadcrumb-nav s3cs-skeleton-breadcrumb">' +
            '<div class="s3cs-nav-group">' +
            '<span class="s3cs-nav-back disabled"><span class="dashicons dashicons-arrow-left-alt2"></span></span>' +
            '<div class="s3cs-breadcrumbs"><div class="s3cs-skeleton-cell" style="width: 120px; height: 18px;"></div></div>' +
            '</div>' +
            '<div class="s3cs-search-inline"><input type="search" class="s3cs-search-input" placeholder="' + (typeof s3cs_browse_button !== 'undefined' && s3cs_browse_button.i18n_search || 'Search files...') + '" disabled></div>' +
            '</div>' +
            '<table class="wp-list-table widefat fixed s3cs-files-table">' +
            '<thead><tr>' +
            '<th class="column-primary" style="width: 40%;">' + (typeof s3cs_browse_button !== 'undefined' && s3cs_browse_button.i18n_file_name || 'File Name') + '</th>' +
            '<th class="column-size" style="width: 20%;">' + (typeof s3cs_browse_button !== 'undefined' && s3cs_browse_button.i18n_file_size || 'File Size') + '</th>' +
            '<th class="column-date" style="width: 25%;">' + (typeof s3cs_browse_button !== 'undefined' && s3cs_browse_button.i18n_last_modified || 'Last Modified') + '</th>' +
            '<th class="column-actions" style="width: 15%;">' + (typeof s3cs_browse_button !== 'undefined' && s3cs_browse_button.i18n_actions || 'Actions') + '</th>' +
            '</tr></thead>' +
            '<tbody>' + skeletonRowsHtml + '</tbody></table>' +
            '</div>';

        // Create DOM structure with skeleton
        var html =
            '<div id="s3cs-modal-overlay" class="s3cs-modal-overlay">' +
            '<div class="s3cs-modal">' +
            '<div class="s3cs-modal-header">' +
            '<h1 class="s3cs-modal-title"></h1>' +
            '<button type="button" class="s3cs-modal-close">' +
            '<span class="dashicons dashicons-no-alt"></span>' +
            '</button>' +
            '</div>' +
            '<div class="s3cs-modal-content">' +
            skeletonHtml +
            '<div id="s3cs-modal-container" class="s3cs-modal-container hidden"></div>' +
            '</div>' +
            '</div>' +
            '</div>';

        $('body').append(html);

        $overlay = $('#s3cs-modal-overlay');
        $modal = $overlay.find('.s3cs-modal');
        $container = $overlay.find('#s3cs-modal-container');
        $title = $overlay.find('.s3cs-modal-title');
        $closeBtn = $overlay.find('.s3cs-modal-close');
        $skeleton = $overlay.find('.s3cs-skeleton-loader');

        // Event listeners
        $closeBtn.on('click', close);
        $overlay.on('click', function (e) {
            if ($(e.target).is($overlay)) {
                close();
            }
        });

        // Close on Escape key
        $(document).on('keydown', function (e) {
            if (e.keyCode === 27 && $overlay.hasClass('open')) {
                close();
            }
        });

        // Global event for content loaded
        $(document).on('s3cs_edd_content_loaded', function () {
            $skeleton.addClass('hidden');
            $container.removeClass('hidden');
        });
    }

    function open(url, title, isPath) {
        init();
        $title.text(title || 'Select File');

        // Reset state: show skeleton, hide container
        $skeleton.removeClass('hidden');
        $container.addClass('hidden');

        $overlay.addClass('open');
        $('body').css('overflow', 'hidden');

        // Trigger library load
        if (window.S3CSMediaLibrary) {
            window.S3CSMediaLibrary.load(url || '', isPath);
        }
    }

    function close() {
        if ($overlay) {
            $overlay.removeClass('open');
            $container.empty().addClass('hidden');
            $skeleton.removeClass('hidden');
            $('body').css('overflow', '');
        }
    }

    return {
        open: open,
        close: close,
        getSkeletonRows: function () { return skeletonRowsHtml; }
    };

})(jQuery);
