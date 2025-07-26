<?php
if (!defined('ABSPATH')) {
    exit;
}

class S3CS_EDDS3CompatibleStorage {
    private $settings;
    private $media_library;
    private $downloader;
    private $uploader;
    
    public function __construct() {
        $this->init();
    }
    
    private function init() {
        add_action('init', array($this, 's3csInit'));
        add_action('admin_notices', array($this, 'showConfigurationNotice'));
        
        // Initialize components
        $this->settings = new S3CS_EDD_S3_Admin_Settings();
        $this->media_library = new S3CS_EDD_S3_Media_Library();
        $this->downloader = new S3CS_EDD_S3_Downloader();
        $this->uploader = new S3CS_EDD_S3_Uploader();
        
        // Register hooks
        add_filter('edd_requested_file', array($this->downloader, 'generateUrl'), 11, 3);
    }
    
    public function s3csInit() {
        // Text domain is loaded in main plugin file
        // This method can be used for other initialization tasks
    }
    
    /**
     * Show admin notice if S3 credentials are not configured
     */
    public function showConfigurationNotice() {
        // Only show on admin pages
        if (!is_admin()) {
            return;
        }
        
        // Don't show on S3 settings page itself
        $current_screen = get_current_screen();
        if ($current_screen && strpos($current_screen->id, 'edd-settings') !== false) {
            return;
        }
        
        $config = new S3CS_EDD_S3_Config();
        $access_key = $config->getAccessKey();
        $secret_key = $config->getSecretKey();
        
        // Show notice if credentials are missing
        if (empty($access_key) || empty($secret_key)) {
            $settings_url = admin_url('edit.php?post_type=download&page=edd-settings&tab=extensions&section=s3cs-settings');
            ?>
            <div class="notice notice-error">
                <p>
                    <strong><?php esc_html_e('Storage for EDD via S3-Compatible:', 'storage-for-edd-via-s3-compatible'); ?></strong>
                    <?php esc_html_e('Access Key and Secret Key are required for S3 storage to work properly.', 'storage-for-edd-via-s3-compatible'); ?>
                    <a href="<?php echo esc_url($settings_url); ?>" class="button button-secondary" style="margin-left: 10px;">
                        <?php esc_html_e('Configure S3 Settings', 'storage-for-edd-via-s3-compatible'); ?>
                    </a>
                </p>
            </div>
            <?php
        }
    }
    
    public function getVersion() {
        return S3CS_EDD_VERSION;
    }
}