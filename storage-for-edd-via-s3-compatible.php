<?php
/**
 * Plugin Name: Storage for EDD via S3-Compatible
 * Description: Enable secure cloud storage and delivery of your digital products through S3-compatible services for Easy Digital Downloads.
 * Version: 1.0.9
 * Author: mohammadr3z
 * Requires Plugins: easy-digital-downloads
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: storage-for-edd-via-s3-compatible
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check for Composer autoload (required for Guzzle)
$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

// Define plugin constants
if (!defined('S3CS_EDD_PLUGIN_DIR')) {
    define('S3CS_EDD_PLUGIN_DIR', plugin_dir_path(__FILE__));
}
if (!defined('S3CS_EDD_PLUGIN_URL')) {
    define('S3CS_EDD_PLUGIN_URL', plugin_dir_url(__FILE__));
}
if (!defined('S3CS_EDD_VERSION')) {
    define('S3CS_EDD_VERSION', '1.0.9');
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
add_action('plugins_loaded', function() {
    new S3CS_EDDS3CompatibleStorage();
});