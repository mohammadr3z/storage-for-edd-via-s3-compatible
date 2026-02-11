<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * S3 Media Library Integration
 *
 * Adds custom tabs to WordPress media uploader for browsing
 * and uploading files to S3.
 */
class S3CS_EDD_S3_Media_Library
{
    private $client;
    private $config;

    public function __construct()
    {
        $this->config = new S3CS_EDD_S3_Config();
        $this->client = new S3CS_EDD_S3_Client();

        // Enqueue styles
        add_action('admin_enqueue_scripts', array($this, 'enqueueStyles'));

        // Add S3 button to EDD downloadable files (Server-Side)
        add_action('edd_download_file_table_row', array($this, 'renderBrowseButton'), 10, 3);

        // Add scripts for S3 button interaction
        add_action('admin_footer', array($this, 'printAdminScripts'));

        // AJAX Handler for fetching library
        add_action('wp_ajax_s3cs_edd_get_library', array($this, 'ajaxGetLibrary'));
    }

    /**
     * AJAX Handler to get library content
     */
    public function ajaxGetLibrary()
    {
        check_ajax_referer('media-form', '_wpnonce');

        $mediaCapability = apply_filters('s3cs_edd_media_access_cap', 'edit_products');
        if (!current_user_can($mediaCapability)) {
            wp_send_json_error(esc_html__('You do not have permission to access S3 library.', 'storage-for-edd-via-s3-compatible'));
        }

        $path = isset($_REQUEST['path']) ? sanitize_text_field(wp_unslash($_REQUEST['path'])) : '';
        $isPath = isset($_REQUEST['is_path']) && $_REQUEST['is_path'] === '1'; // Actually always true for S3, but keeping for consistency

        // For S3, path IS the identifier, but we validate it
        // Prevent directory traversal
        if (strpos($path, '..') !== false) {
            wp_send_json_error(esc_html__('Invalid path.', 'storage-for-edd-via-s3-compatible'));
        }

        if (empty($path)) {
            $path = '';
        }

        ob_start();
        $this->renderLibraryContent($path);
        $html = ob_get_clean();

        wp_send_json_success(array('html' => $html));
    }

    /**
     * Render the inner content of the library
     * 
     * @param string $path
     */
    private function renderLibraryContent($path)
    {
        // Check if bucket is configured before trying to list files
        if (!$this->config->isConfigured()) {
?>
            <div id="media-items" class="s3cs-media-container">
                <h3 class="media-title"><?php esc_html_e('S3 File Browser', 'storage-for-edd-via-s3-compatible'); ?></h3>

                <div class="s3cs-notice warning">
                    <h4><?php esc_html_e('No S3 bucket selected', 'storage-for-edd-via-s3-compatible'); ?></h4>
                    <p><?php esc_html_e('Please configure your S3 settings and select a bucket before browsing files.', 'storage-for-edd-via-s3-compatible'); ?></p>
                    <p><?php esc_html_e('You need to configure Access Key, Secret Key, Endpoint, and select a bucket to browse S3 files.', 'storage-for-edd-via-s3-compatible'); ?></p>
                    <p>
                        <a href="<?php echo esc_url(admin_url('edit.php?post_type=download&page=edd-settings&tab=extensions&section=s3cs-settings')); ?>" class="button-primary">
                            <?php esc_html_e('Configure S3 Settings', 'storage-for-edd-via-s3-compatible'); ?>
                        </a>
                    </p>
                </div>
            </div>
        <?php
            return;
        }

        // Try to get files with error handling
        try {
            $files = $this->client->listFilesWithFolders($path);
            $connection_error = false;
        } catch (Exception $e) {
            $files = array();
            $connection_error = true;
            $this->config->debug('S3 connection error: ' . $e->getMessage());
        }

        ?>

        <?php
        // Calculate back URL if in subfolder
        $back_url = '';
        if (!empty($path)) {
            $parent_path = dirname($path);
            $parent_path = ($parent_path === '/' || $parent_path === '.') ? '' : $parent_path;
            // Remove success parameters
            $back_url = remove_query_arg(array('s3cs_edd_success', 's3cs_edd_filename', 'error'));
            $back_url = add_query_arg(array(
                'path' => $parent_path,
                '_wpnonce' => wp_create_nonce('media-form')
            ), $back_url);
        }
        ?>
        <div class="s3cs-header-row">
            <h3 class="media-title"><?php esc_html_e('Select a file from S3', 'storage-for-edd-via-s3-compatible'); ?></h3>
            <div class="s3cs-header-buttons">
                <button type="button" class="button button-primary" id="s3cs-toggle-upload">
                    <?php esc_html_e('Upload File', 'storage-for-edd-via-s3-compatible'); ?>
                </button>
            </div>
        </div>

        <?php if ($connection_error) { ?>
            <div class="s3cs-notice warning">
                <h4><?php esc_html_e('Connection Error', 'storage-for-edd-via-s3-compatible'); ?></h4>
                <p><?php esc_html_e('Unable to connect to your S3 storage.', 'storage-for-edd-via-s3-compatible'); ?></p>
                <p><?php esc_html_e('Please check your S3 configuration settings and try again.', 'storage-for-edd-via-s3-compatible'); ?></p>
                <p>
                    <a href="<?php echo esc_url(admin_url('edit.php?post_type=download&page=edd-settings&tab=extensions&section=s3cs-settings')); ?>" class="button-primary">
                        <?php esc_html_e('Check Settings', 'storage-for-edd-via-s3-compatible'); ?>
                    </a>
                </p>
            </div>
        <?php } elseif (!$connection_error) { ?>

            <div class="s3cs-breadcrumb-nav">
                <div class="s3cs-nav-group">
                    <?php if (!empty($back_url)) { ?>
                        <a href="<?php echo esc_url($back_url); ?>" class="s3cs-nav-back" title="<?php esc_attr_e('Go Back', 'storage-for-edd-via-s3-compatible'); ?>" data-path="<?php echo esc_attr($parent_path); ?>">
                            <span class="dashicons dashicons-arrow-left-alt2"></span>
                        </a>
                    <?php } else { ?>
                        <span class="s3cs-nav-back disabled">
                            <span class="dashicons dashicons-arrow-left-alt2"></span>
                        </span>
                    <?php } ?>

                    <div class="s3cs-breadcrumbs">
                        <?php
                        if (!empty($path)) {
                            // Build breadcrumb navigation
                            $path_parts = explode('/', trim($path, '/'));
                            $breadcrumb_path = '';
                            $breadcrumb_links = array();

                            // Root link
                            $root_url = remove_query_arg(array('path', 's3cs_edd_success', 's3cs_edd_filename', 'error'));
                            $root_url = add_query_arg(array('_wpnonce' => wp_create_nonce('media-form')), $root_url);
                            $breadcrumb_links[] = '<a href="' . esc_url($root_url) . '" data-path="">' . esc_html($this->config->getBucket()) . '</a>';

                            // Build path links
                            foreach ($path_parts as $index => $part) {
                                if (!empty($breadcrumb_path)) {
                                    $breadcrumb_path .= '/' . $part;
                                } else {
                                    $breadcrumb_path = $part;
                                }

                                if ($index === count($path_parts) - 1) {
                                    // Current folder - not a link
                                    $breadcrumb_links[] = '<span class="current">' . esc_html($part) . '</span>';
                                } else {
                                    // Parent folder - make it a link
                                    $folder_url = remove_query_arg(array('s3cs_edd_success', 's3cs_edd_filename', 'error'));
                                    $folder_url = add_query_arg(array(
                                        'path' => $breadcrumb_path,
                                        '_wpnonce' => wp_create_nonce('media-form')
                                    ), $folder_url);
                                    $breadcrumb_links[] = '<a href="' . esc_url($folder_url) . '" data-path="' . esc_attr($breadcrumb_path) . '">' . esc_html($part) . '</a>';
                                }
                            }

                            echo wp_kses(implode(' <span class="sep">/</span> ', $breadcrumb_links), array(
                                'a' => array('href' => array(), 'data-path' => array()),
                                'span' => array('class' => array())
                            ));
                        } else {
                            echo '<span class="current">' . esc_html($this->config->getBucket()) . '</span>';
                        }
                        ?>
                    </div>
                </div>

                <?php if (is_array($files) && !empty($files)) { ?>
                    <div class="s3cs-search-inline">
                        <input type="search"
                            id="s3cs-file-search"
                            class="s3cs-search-input"
                            placeholder="<?php esc_attr_e('Search files...', 'storage-for-edd-via-s3-compatible'); ?>">
                    </div>
                <?php } ?>
            </div>

            <?php
            // Upload form integrated into Library
            ?>
            <!-- Upload Form (Hidden by default) -->
            <form enctype="multipart/form-data" method="post" action="#" class="s3cs-upload-form" id="s3cs-upload-section" style="display: none;">
                <?php wp_nonce_field('s3cs_edd_upload', 's3cs_edd_nonce'); ?>
                <input type="hidden" name="action" value="s3cs_edd_ajax_upload" />
                <div class="upload-field">
                    <input type="file"
                        name="s3cs_edd_file"
                        accept=".zip,.rar,.7z,.pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif" />
                </div>
                <p class="description">
                    <?php
                    // translators: %s: Maximum upload file size.
                    printf(esc_html__('Maximum upload file size: %s', 'storage-for-edd-via-s3-compatible'), esc_html(size_format(wp_max_upload_size())));
                    ?>
                </p>
                <input type="submit"
                    class="button-primary"
                    value="<?php esc_attr_e('Upload', 'storage-for-edd-via-s3-compatible'); ?>" />
                <input type="hidden" name="s3cs_edd_path" value="<?php echo esc_attr($path); ?>" />
            </form>

            <?php if (is_array($files) && !empty($files)) { ?>

                <!-- File Display Table -->
                <table class="wp-list-table widefat fixed s3cs-files-table">
                    <thead>
                        <tr>
                            <th class="column-primary" style="width: 40%;"><?php esc_html_e('File Name', 'storage-for-edd-via-s3-compatible'); ?></th>
                            <th class="column-size" style="width: 20%;"><?php esc_html_e('File Size', 'storage-for-edd-via-s3-compatible'); ?></th>
                            <th class="column-date" style="width: 25%;"><?php esc_html_e('Last Modified', 'storage-for-edd-via-s3-compatible'); ?></th>
                            <th class="column-actions" style="width: 15%;"><?php esc_html_e('Actions', 'storage-for-edd-via-s3-compatible'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Sort: folders first, then files
                        usort($files, function ($a, $b) {
                            if ($a['is_folder'] && !$b['is_folder']) return -1;
                            if (!$a['is_folder'] && $b['is_folder']) return 1;
                            return strcasecmp($a['name'], $b['name']);
                        });

                        foreach ($files as $file) {
                            // Handle folders
                            if ($file['is_folder']) {
                                $folder_url = add_query_arg(array(
                                    'path' => $file['path'],
                                    '_wpnonce' => wp_create_nonce('media-form')
                                ));
                        ?>
                                <tr class="s3cs-folder-row">
                                    <td class="column-primary" data-label="<?php esc_attr_e('Folder Name', 'storage-for-edd-via-s3-compatible'); ?>">
                                        <a href="<?php echo esc_url($folder_url); ?>" class="folder-link" data-path="<?php echo esc_attr($file['path']); ?>">
                                            <span class="dashicons dashicons-category"></span>
                                            <span class="file-name"><?php echo esc_html($file['name']); ?></span>
                                        </a>
                                    </td>
                                    <td class="column-size">—</td>
                                    <td class="column-date">—</td>
                                    <td class="column-actions">
                                        <a href="<?php echo esc_url($folder_url); ?>" class="button-secondary button-small folder-link" data-path="<?php echo esc_attr($file['path']); ?>">
                                            <?php esc_html_e('Open', 'storage-for-edd-via-s3-compatible'); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php
                                continue;
                            }

                            // Handle files
                            $file_size = $this->formatFileSize($file['size']);
                            $last_modified = !empty($file['modified']) ? $this->formatHumanDate($file['modified']) : '—';
                            ?>
                            <tr>
                                <td class="column-primary" data-label="<?php esc_attr_e('File Name', 'storage-for-edd-via-s3-compatible'); ?>">
                                    <div class="s3cs-file-display">
                                        <span class="file-name"><?php echo esc_html($file['name']); ?></span>
                                    </div>
                                </td>
                                <td class="column-size" data-label="<?php esc_attr_e('File Size', 'storage-for-edd-via-s3-compatible'); ?>">
                                    <span class="file-size"><?php echo esc_html($file_size); ?></span>
                                </td>
                                <td class="column-date" data-label="<?php esc_attr_e('Last Modified', 'storage-for-edd-via-s3-compatible'); ?>">
                                    <span class="file-date"><?php echo esc_html($last_modified); ?></span>
                                </td>
                                <td class="column-actions" data-label="<?php esc_attr_e('Actions', 'storage-for-edd-via-s3-compatible'); ?>">
                                    <a class="save-s3cs-file button-secondary button-small"
                                        href="javascript:void(0)"
                                        data-s3cs-filename="<?php echo esc_attr($file['name']); ?>"
                                        data-s3cs-link="<?php echo esc_attr(ltrim($file['path'], '/')); ?>">
                                        <?php esc_html_e('Select File', 'storage-for-edd-via-s3-compatible'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            <?php } else { ?>
                <div class="s3cs-notice info" style="margin-top: 15px;">
                    <p><?php esc_html_e('This folder is empty. Use the upload form above to add files.', 'storage-for-edd-via-s3-compatible'); ?></p>
                </div>
            <?php } ?>
        <?php } ?>
        </div>
    <?php
    }

    /**
     * Format file size in human readable format
     * @param int $size
     * @return string
     */
    private function formatFileSize($size)
    {
        if ($size === 0) return '0 B';

        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        $power = floor(log($size, 1024));
        if ($power >= count($units)) {
            $power = count($units) - 1;
        }

        return round($size / pow(1024, $power), 2) . ' ' . $units[$power];
    }

    /**
     * Format date in human readable format
     * @param string $date
     * @return string
     */
    private function formatHumanDate($date)
    {
        try {
            $timestamp = strtotime($date);
            if ($timestamp) {
                return date_i18n('j F Y', $timestamp);
            }
        } catch (Exception $e) {
            // Ignore date formatting errors
        }
        return $date;
    }

    /**
     * Enqueue CSS styles and JS scripts
     */
    public function enqueueStyles()
    {
        // Register styles
        wp_register_style('s3cs-media-library', S3CS_EDD_PLUGIN_URL . 'assets/css/s3-media-library.css', array(), S3CS_EDD_VERSION);
        wp_register_style('s3cs-upload', S3CS_EDD_PLUGIN_URL . 'assets/css/s3-upload.css', array(), S3CS_EDD_VERSION);
        wp_register_style('s3cs-media-container', S3CS_EDD_PLUGIN_URL . 'assets/css/s3-media-container.css', array(), S3CS_EDD_VERSION);
        wp_register_style('s3cs-modal', S3CS_EDD_PLUGIN_URL . 'assets/css/s3-modal.css', array('dashicons'), S3CS_EDD_VERSION);
        wp_register_style('s3cs-browse-button', S3CS_EDD_PLUGIN_URL . 'assets/css/s3-browse-button.css', array(), S3CS_EDD_VERSION);

        // Register scripts
        wp_register_script('s3cs-media-library', S3CS_EDD_PLUGIN_URL . 'assets/js/s3-media-library.js', array('jquery'), S3CS_EDD_VERSION, true);
        wp_register_script('s3cs-upload', S3CS_EDD_PLUGIN_URL . 'assets/js/s3-upload.js', array('jquery'), S3CS_EDD_VERSION, true);
        wp_register_script('s3cs-modal', S3CS_EDD_PLUGIN_URL . 'assets/js/s3-modal.js', array('jquery'), S3CS_EDD_VERSION, true);
        wp_register_script('s3cs-browse-button', S3CS_EDD_PLUGIN_URL . 'assets/js/s3-browse-button.js', array('jquery', 's3cs-modal'), S3CS_EDD_VERSION, true);

        // Localize scripts
        wp_localize_script('s3cs-media-library', 's3cs_edd_i18n', array(
            'file_selected_success' => esc_html__('File selected successfully!', 'storage-for-edd-via-s3-compatible'),
            'file_selected_error' => esc_html__('Error selecting file. Please try again.', 'storage-for-edd-via-s3-compatible')
        ));

        wp_add_inline_script('s3cs-media-library', 'var s3cs_edd_url_prefix = "' . esc_js($this->config->getUrlPrefix()) . '";', 'before');

        wp_localize_script('s3cs-upload', 's3cs_edd_i18n', array(
            'file_size_too_large' => esc_html__('File size too large. Maximum allowed size is', 'storage-for-edd-via-s3-compatible')
        ));

        wp_add_inline_script('s3cs-upload', 'var s3cs_edd_url_prefix = "' . esc_js($this->config->getUrlPrefix()) . '";', 'before');
        wp_add_inline_script('s3cs-upload', 'var s3cs_edd_max_upload_size = ' . wp_json_encode(wp_max_upload_size()) . ';', 'before');
    }

    /**
     * Render Browse S3 button in EDD file row (Server Side)
     */
    public function renderBrowseButton($key, $file, $post_id)
    {
        if (!$this->config->isConfigured()) {
            return;
        }

        // Add hidden input to store connection status/check if needed by JS (optional)
    ?>
        <div class="edd-form-group edd-file-s3-browse">
            <label class="edd-form-group__label edd-repeatable-row-setting-label">&nbsp;</label>
            <div class="edd-form-group__control">
                <button type="button" class="button s3cs_browse_button">
                    <?php esc_html_e('Browse S3', 'storage-for-edd-via-s3-compatible'); ?>
                </button>
            </div>
        </div>
<?php
    }

    /**
     * Add S3 browse button scripts
     */
    public function printAdminScripts()
    {
        global $pagenow, $typenow;

        // Only on EDD download edit pages
        if (!($pagenow === 'post.php' || $pagenow === 'post-new.php') || $typenow !== 'download') {
            return;
        }

        // Only if connected
        if (!$this->config->isConfigured()) {
            return;
        }

        // Enqueue modal assets
        wp_enqueue_style('s3cs-modal');
        wp_enqueue_script('s3cs-modal');

        // Enqueue media library assets (AJAX)
        wp_enqueue_style('s3cs-media-library');
        wp_enqueue_script('s3cs-media-library');
        wp_enqueue_style('s3cs-upload');
        wp_enqueue_script('s3cs-upload');

        // Enqueue browse button assets
        wp_enqueue_style('s3cs-browse-button');
        wp_enqueue_script('s3cs-browse-button');

        // Localize script with dynamic data
        // We use the AJAX handler now, so modal_url can be just a base or empty.
        wp_localize_script('s3cs-browse-button', 's3cs_browse_button', array(
            'modal_title'        => __('S3 Library', 'storage-for-edd-via-s3-compatible'),
            'nonce'              => wp_create_nonce('media-form'),
            'url_prefix'         => $this->config->getUrlPrefix(),
            'i18n_select_file'   => __('Select a file from S3', 'storage-for-edd-via-s3-compatible'),
            'i18n_upload'        => __('Upload File', 'storage-for-edd-via-s3-compatible'),
            'i18n_file_name'     => __('File Name', 'storage-for-edd-via-s3-compatible'),
            'i18n_file_size'     => __('File Size', 'storage-for-edd-via-s3-compatible'),
            'i18n_last_modified' => __('Last Modified', 'storage-for-edd-via-s3-compatible'),
            'i18n_actions'       => __('Actions', 'storage-for-edd-via-s3-compatible'),
            'i18n_search'        => __('Search files...', 'storage-for-edd-via-s3-compatible')
        ));
    }
}
