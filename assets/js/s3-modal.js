/**
 * S3CS Modal JS
 */
var S3CSModal = (function ($) {
    var $modal, $overlay, $iframe, $closeBtn;

    function init() {
        if ($('#s3cs-modal-overlay').length) {
            return;
        }

        // Create DOM structure
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
            '<iframe class="s3cs-modal-frame" src=""></iframe>' +
            '</div>' +
            '</div>' +
            '</div>';

        $('body').append(html);

        $overlay = $('#s3cs-modal-overlay');
        $modal = $overlay.find('.s3cs-modal');
        $iframe = $overlay.find('.s3cs-modal-frame');
        $title = $overlay.find('.s3cs-modal-title');
        $closeBtn = $overlay.find('.s3cs-modal-close');

        // Event listeners
        $closeBtn.on('click', close);
        $overlay.on('click', function (e) {
            if ($(e.target).is($overlay)) {
                close();
            }
        });

        // Close on Escape key
        $(document).on('keydown', function (e) {
            if (e.keyCode === 27 && $overlay.hasClass('open')) { // ESC
                close();
            }
        });
    }

    function open(url, title) {
        init();
        $title.text(title || 'Select File');
        $iframe.attr('src', url);
        $overlay.addClass('open');
        $('body').css('overflow', 'hidden'); // Prevent body scroll
    }

    function close() {
        if ($overlay) {
            $overlay.removeClass('open');
            $iframe.attr('src', ''); // Stop loading
            $('body').css('overflow', '');
        }
    }

    return {
        open: open,
        close: close
    };

})(jQuery);
