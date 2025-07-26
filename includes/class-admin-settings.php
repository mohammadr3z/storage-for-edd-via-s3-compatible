<?php
if (!defined('ABSPATH')) {
    exit;
}

class S3CS_EDD_S3_Admin_Settings {
    private $client;
    private $config;
    
    public function __construct() {
        $this->config = new S3CS_EDD_S3_Config();
        $this->client = new S3CS_EDD_S3_Client();
        
        add_filter('edd_settings_extensions', array($this, 'addSettings'));
        add_filter('edd_settings_sections_extensions', array($this, 'registerS3CSSection'));
        add_action('admin_enqueue_scripts', array($this, 'enqueueAdminScripts'));
    }
    
    public function addSettings($settings) {
        // Check if basic credentials are configured
        $is_configured_for_buckets = $this->config->isConfiguredForBucketList();
        
        // Only try to get buckets if we have complete configuration
        $bucket_options = array('' => __('-- Select Bucket --', 'storage-for-edd-via-s3-compatible'));
        
        if ($is_configured_for_buckets) {
            try {
                $buckets = $this->client->getBucketsList();
                if (is_array($buckets) && !empty($buckets)) {
                    foreach ($buckets as $bucket) {
                        $bucket_options[$bucket] = $bucket;
                    }
                } else {
                    $bucket_options[''] = __('-- No buckets found --', 'storage-for-edd-via-s3-compatible');
                }
            } catch (Exception $e) {
                $bucket_options[''] = __('-- Error loading buckets --', 'storage-for-edd-via-s3-compatible');
            }
        } else {
            $bucket_options[''] = __('-- Save credentials first to load buckets --', 'storage-for-edd-via-s3-compatible');
        }
        
        $s3cs_settings = array(
            array(
                'id' => 's3cs_settings',
                'name' => '<strong>' . __('S3 Storage Settings', 'storage-for-edd-via-s3-compatible') . '</strong>',
                'type' => 'header'
            ),
            array(
                'id' => S3CS_EDD_S3_Config::KEY_ACCESS_KEY,
                'name' => __('Access Key', 'storage-for-edd-via-s3-compatible'),
                'desc' => __('Enter your S3 Access Key', 'storage-for-edd-via-s3-compatible'),
                'type' => 'text',
                'size' => 'regular',
                'class' => 'edd-s3cs-credential'
            ),
            array(
                'id' => S3CS_EDD_S3_Config::KEY_SECRET_KEY,
                'name' => __('Secret Key', 'storage-for-edd-via-s3-compatible'),
                'desc' => __('Enter your S3 Secret Key', 'storage-for-edd-via-s3-compatible'),
                'type' => 'password',
                'size' => 'regular',
                'class' => 'edd-s3cs-credential'
            ),
            array(
                'id' => S3CS_EDD_S3_Config::KEY_ENDPOINT,
                'name' => __('Endpoint URL', 'storage-for-edd-via-s3-compatible'),
                'desc' => __('Enter your S3 compatible endpoint URL (e.g. https://s3.example.com). The URL should start with https:// for proper functionality.', 'storage-for-edd-via-s3-compatible'),
                'type' => 'text',
                'size' => 'regular',
                'class' => 'edd-s3cs-credential',
                'placeholder' => 'https://'
            ),
            array(
                'id' => S3CS_EDD_S3_Config::KEY_BUCKET,
                'name' => __('Bucket Name', 'storage-for-edd-via-s3-compatible'),
                'desc' => $is_configured_for_buckets ? 
                    __('Select your S3 bucket', 'storage-for-edd-via-s3-compatible') : 
                    __('Please save your S3 credentials first to enable bucket selection', 'storage-for-edd-via-s3-compatible'),
                'type' => 'select',
                'options' => $bucket_options,
                'class' => $is_configured_for_buckets ? '' : 'edd-s3cs-bucket-disabled',
                'disabled' => !$is_configured_for_buckets
            ),
            array(
                'id' => S3CS_EDD_S3_Config::KEY_EXPIRY_MINUTES,
                'name' => __('Download Link Expiry (minutes)', 'storage-for-edd-via-s3-compatible'),
                'desc' => __('How many minutes should the download link be valid?', 'storage-for-edd-via-s3-compatible'),
                'type' => 'text',
                'size' => 'small',
                'std'  => '5'
            )
        );
        
        return array_merge($settings, array('s3cs-settings' => $s3cs_settings));
    }
    
    public function registerS3CSSection($sections) {
        $sections['s3cs-settings'] = __('S3 Storage', 'storage-for-edd-via-s3-compatible');
        return $sections;
    }
    
    public function enqueueAdminScripts($hook) {
        if ($hook !== 'download_page_edd-settings') {
            return;
        }
        
        wp_enqueue_script('jquery');
        
        // Register and enqueue admin settings CSS
        wp_register_style('s3cs-admin-settings', S3CS_EDD_PLUGIN_URL . 'assets/css/admin-settings.css', array(), S3CS_EDD_VERSION);
        wp_enqueue_style('s3cs-admin-settings');
        
        // Register and enqueue admin settings JS
        wp_register_script('s3cs-admin-settings', S3CS_EDD_PLUGIN_URL . 'assets/js/admin-settings.js', array('jquery'), S3CS_EDD_VERSION, true);
        wp_enqueue_script('s3cs-admin-settings');
    }
}