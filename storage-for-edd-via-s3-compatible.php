<?php

/**
 * Plugin Name: Storage for EDD via S3-Compatible
 * Description: Enable secure cloud storage and delivery of your digital products through S3-compatible services for Easy Digital Downloads.
 * Version: 1.2.2
 * Author: mohammadr3z
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: storage-for-edd-via-s3-compatible
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check for Composer autoload (required for Guzzle)
// Only load if Guzzle is not already loaded (prevents conflict with other S3 plugins)
if (!class_exists('GuzzleHttp\Client')) {
    if (file_exists(__DIR__ . '/vendor/autoload.php')) {
        require_once __DIR__ . '/vendor/autoload.php';
    }
}

// Define plugin constants
if (!defined('S3CS_EDD_PLUGIN_DIR')) {
    define('S3CS_EDD_PLUGIN_DIR', plugin_dir_path(__FILE__));
}
if (!defined('S3CS_EDD_PLUGIN_URL')) {
    define('S3CS_EDD_PLUGIN_URL', plugin_dir_url(__FILE__));
}
if (!defined('S3CS_EDD_VERSION')) {
    define('S3CS_EDD_VERSION', '1.2.2');
}

// Load plugin classes
require_once S3CS_EDD_PLUGIN_DIR . 'includes/class-s3-config.php';
require_once S3CS_EDD_PLUGIN_DIR . 'includes/class-s3-client.php';
require_once S3CS_EDD_PLUGIN_DIR . 'includes/class-s3-uploader.php';
require_once S3CS_EDD_PLUGIN_DIR . 'includes/class-s3-downloader.php';
require_once S3CS_EDD_PLUGIN_DIR . 'includes/class-admin-settings.php';
require_once S3CS_EDD_PLUGIN_DIR . 'includes/class-media-library.php';
require_once S3CS_EDD_PLUGIN_DIR . 'includes/class-main-plugin.php';

// Initialize plugin on plugins_loaded
add_action('plugins_loaded', function () {
    if (!class_exists('Easy_Digital_Downloads')) {
        add_action('admin_notices', function () {
            ?>
            <div class="notice notice-error">
                <p>
                    <strong><?php esc_html_e('Storage for EDD via S3-Compatible:', 'storage-for-edd-via-s3-compatible'); ?></strong>
                    <?php esc_html_e('Easy Digital Downloads (Free or Pro) is required but not active. Please install and activate it first.', 'storage-for-edd-via-s3-compatible'); ?>
                </p>
            </div>
            <?php
        });
        return;
    }

    new S3CS_EDDS3CompatibleStorage();
});
