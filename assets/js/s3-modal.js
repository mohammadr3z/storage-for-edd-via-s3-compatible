/**
 * S3CS Modal JS
 */
var S3CSModal = (function ($) {
    var $modal, $overlay, $iframe, $closeBtn, $skeleton;

    function init() {
        if ($('#s3cs-modal-overlay').length) {
            return;
        }

        // Skeleton HTML structure
        var skeletonHtml =
            '<div class="s3cs-skeleton-loader">' +
            '<div class="s3cs-skeleton-header">' +
            '<div class="s3cs-skeleton-title"></div>' +
            '<div class="s3cs-skeleton-button"></div>' +
            '</div>' +
            '<div class="s3cs-skeleton-breadcrumb">' +
            '<div class="s3cs-skeleton-back-btn"></div>' +
            '<div class="s3cs-skeleton-path"></div>' +
            '<div class="s3cs-skeleton-search"></div>' +
            '</div>' +
            '<div class="s3cs-skeleton-table">' +
            '<div class="s3cs-skeleton-thead">' +
            '<div class="s3cs-skeleton-row">' +
            '<div class="s3cs-skeleton-cell name"></div>' +
            '<div class="s3cs-skeleton-cell size"></div>' +
            '<div class="s3cs-skeleton-cell date"></div>' +
            '<div class="s3cs-skeleton-cell action"></div>' +
            '</div>' +
            '</div>' +
            '<div class="s3cs-skeleton-row">' +
            '<div class="s3cs-skeleton-cell name"></div>' +
            '<div class="s3cs-skeleton-cell size"></div>' +
            '<div class="s3cs-skeleton-cell date"></div>' +
            '<div class="s3cs-skeleton-cell action"></div>' +
            '</div>' +
            '</div>' +
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
            '<iframe class="s3cs-modal-frame loading" src=""></iframe>' +
            '</div>' +
            '</div>' +
            '</div>';

        $('body').append(html);

        $overlay = $('#s3cs-modal-overlay');
        $modal = $overlay.find('.s3cs-modal');
        $iframe = $overlay.find('.s3cs-modal-frame');
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

        // Handle iframe load event
        $iframe.on('load', function () {
            $skeleton.addClass('hidden');
            $iframe.removeClass('loading').addClass('loaded');
        });
    }

    function open(url, title) {
        init();
        $title.text(title || 'Select File');

        // Reset state: show skeleton, hide iframe
        $skeleton.removeClass('hidden');
        $iframe.removeClass('loaded').addClass('loading');

        $iframe.attr('src', url);
        $overlay.addClass('open');
        $('body').css('overflow', 'hidden');
    }

    function close() {
        if ($overlay) {
            $overlay.removeClass('open');
            $iframe.attr('src', '');
            $iframe.removeClass('loaded').addClass('loading');
            $skeleton.removeClass('hidden');
            $('body').css('overflow', '');
        }
    }

    return {
        open: open,
        close: close
    };

})(jQuery);
