<?php
if (!defined('ABSPATH')) {
    exit;
}

class S3CS_EDD_S3_Media_Library {
    private $client;
    private $config;
    
    public function __construct() {
        $this->config = new S3CS_EDD_S3_Config();
        $this->client = new S3CS_EDD_S3_Client();
        
        // Media library integration
        add_filter('media_upload_tabs', array($this, 'addS3Tabs'));
        add_action('media_upload_s3cs_lib', array($this, 'registerS3LibTab'));
        add_action('media_upload_s3cs_upload', array($this, 'registerS3UploadTab'));
        add_action('admin_head', array($this, 'setupAdminJS'));
        
        // Enqueue styles
        add_action('admin_enqueue_scripts', array($this, 'enqueueStyles'));
    }
    
    public function addS3Tabs($default_tabs) {
        if ($this->config->isConfiguredForBucketList()) {
            $default_tabs['s3cs_upload'] = esc_html__('Upload to S3', 'storage-for-edd-via-s3-compatible');
$default_tabs['s3cs_lib'] = esc_html__('S3 Library', 'storage-for-edd-via-s3-compatible');
        }
        return $default_tabs;
    }
    
    public function registerS3LibTab() {
        // Check user capability for accessing S3 media library
        $mediaCapability = apply_filters('s3cs_edd_media_access_cap', 'edit_products');
        if (!current_user_can($mediaCapability)) {
            wp_die(esc_html__('You do not have permission to access S3 media library.', 'storage-for-edd-via-s3-compatible'));
        }
        
        // Check nonce for GET requests with parameters
        if (!empty($_GET) && (isset($_GET['path']) || isset($_GET['_wpnonce']))) {
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'media-form')) {
                wp_die(esc_html__('Security check failed.', 'storage-for-edd-via-s3-compatible'));
            }
        }
        
        if (!empty($_POST)) {
            // Nonce check for security
            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'media-form')) {
                wp_die(esc_html__('Security check failed.', 'storage-for-edd-via-s3-compatible'));
            }
            
            $error = media_upload_form_handler();
            if (is_string($error)) {
                return $error;
            }
        }
        wp_iframe(array($this, 'renderS3LibTab'));
    }
    
    public function renderS3LibTab() {
        media_upload_header();
        wp_enqueue_style('media');
        // Enqueue S3 styles and scripts
        wp_enqueue_style('s3cs-media-library');
        wp_enqueue_style('s3cs-media-container');
        wp_enqueue_script('s3cs-media-library');
        $path = $this->get_path();
        
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
            $files = $this->client->listFiles($path);
            $connection_error = false;
        } catch (Exception $e) {
            $files = false;
            $connection_error = true;
            // Log detailed error for debugging
            $this->config->debug('S3 connection error: ' . $e->getMessage());
        }
        
        ?>


        <div style="width: inherit;" id="media-items">
            <h3 class="media-title"><?php esc_html_e('Select a file from S3', 'storage-for-edd-via-s3-compatible'); ?></h3>
            
            <?php if ($connection_error) { ?>
                <div class="s3cs-notice warning">
                    <h4><?php esc_html_e('Connection Error', 'storage-for-edd-via-s3-compatible'); ?></h4>
                    <p><?php esc_html_e('Unable to connect to your S3 storage.', 'storage-for-edd-via-s3-compatible'); ?></p>
                    <p><?php esc_html_e('Please check your S3 configuration settings and try again.', 'storage-for-edd-via-s3-compatible'); ?></p>
                    <p>
                        <a href="<?php echo esc_url(admin_url('edit.php?post_type=download&page=edd-settings&tab=extensions&section=s3cs-settings')); ?>" class="button-primary">
                            <?php esc_html_e('Check S3 Settings', 'storage-for-edd-via-s3-compatible'); ?>
                        </a>
                    </p>
                </div>
            <?php } elseif (is_array($files) && !empty($files)) { ?>
                <p class="description" style="margin-bottom: 15px;">
                    <?php 
                    if (!empty($path)) {
                        // translators: %s: Directory path being browsed.
                        printf(esc_html__('Browsing: %s', 'storage-for-edd-via-s3-compatible'), '<strong>' . esc_html($path) . '</strong>');
                    } else {
                        // translators: %s: S3 bucket name being browsed.
                        printf(esc_html__('Browsing bucket: %s', 'storage-for-edd-via-s3-compatible'), '<strong>' . esc_html($this->config->getBucket()) . '</strong>');
                    }
                    ?>
                </p>
                
                <!-- Search Input -->
                <div class="s3cs-search-container" style="margin-bottom: 15px;">
                    <input type="text" 
                           id="s3cs-file-search" 
                           class="s3cs-search-input" 
                           placeholder="<?php esc_attr_e('Search files...', 'storage-for-edd-via-s3-compatible'); ?>" 
                           style="width: 300px; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px;">
                    <button type="button" 
                            id="s3cs-clear-search" 
                            class="button" 
                            style="margin-left: 10px;">
                        <?php esc_html_e('Clear', 'storage-for-edd-via-s3-compatible'); ?>
                    </button>
                </div>
                
                <!-- File Display Table -->
                <table class="wp-list-table widefat fixed s3cs-files-table">
                    <thead>
                        <tr>
                            <th class="column-primary" style="width: 40%;"><?php esc_html_e('File Name', 'storage-for-edd-via-s3-compatible'); ?></th>
                            <th style="width: 20%;"><?php esc_html_e('File Size', 'storage-for-edd-via-s3-compatible'); ?></th>
                            <th style="width: 25%;"><?php esc_html_e('Last Modified', 'storage-for-edd-via-s3-compatible'); ?></th>
                            <th style="width: 15%;"><?php esc_html_e('Actions', 'storage-for-edd-via-s3-compatible'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $file_details = $this->getFileDetails($files);
                        foreach ($files as $file) {
                            if (substr($file, -1) === '/') continue; // skip folders for now
                            
                            $filename = basename($file);
                            $folder_path = dirname($file) !== '.' ? dirname($file) . '/' : '';
                            $display_name = $folder_path . $filename;
                            
                            // Get file details
                            $details = isset($file_details[$file]) ? $file_details[$file] : array();
                            $file_size = isset($details['size']) ? $this->formatFileSize($details['size']) : esc_html__('Unknown', 'storage-for-edd-via-s3-compatible');
                            $last_modified = isset($details['last_modified']) ? $this->formatHumanDate($details['last_modified']) : esc_html__('Unknown', 'storage-for-edd-via-s3-compatible');
                            ?>
                            <tr>
                                <td class="column-primary" data-label="<?php esc_attr_e('File Name', 'storage-for-edd-via-s3-compatible'); ?>">
                                    <div class="s3cs-file-display">
                                        <span class="file-name"><?php echo esc_html($filename); ?></span>
                                        <?php if (!empty($folder_path)) { ?>
                                            <span class="file-path"><?php echo esc_html($folder_path); ?></span>
                                        <?php } ?>
                                    </div>
                                </td>
                                <td data-label="<?php esc_attr_e('File Size', 'storage-for-edd-via-s3-compatible'); ?>">
                                    <span class="file-size"><?php echo esc_html($file_size); ?></span>
                                </td>
                                <td data-label="<?php esc_attr_e('Last Modified', 'storage-for-edd-via-s3-compatible'); ?>">
                                    <span class="file-date"><?php echo esc_html($last_modified); ?></span>
                                </td>
                                <td data-label="<?php esc_attr_e('Actions', 'storage-for-edd-via-s3-compatible'); ?>">
                                    <a class="save-s3cs-file button-secondary button-small" 
                                       href="javascript:void(0)" 
                                       data-s3cs-filename="<?php echo esc_attr($filename); ?>"
                                       data-s3cs-link="<?php echo esc_attr($file); ?>">
                                        <?php esc_html_e('Select File', 'storage-for-edd-via-s3-compatible'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            <?php } else { ?>
                <div class="s3cs-notice warning">
                    <h4><?php esc_html_e('No files found', 'storage-for-edd-via-s3-compatible'); ?></h4>
                    <p>
                        <?php 
                        if (!empty($path)) {
                            // translators: %s: Directory path where no files were found.
                            printf(esc_html__('No files found in the path: %s', 'storage-for-edd-via-s3-compatible'), '<span class="s3cs-path">' . esc_html($path) . '</span>');
                        } else {
                            // translators: %s: S3 bucket name where no files were found.
                            printf(esc_html__('No files found in bucket: %s', 'storage-for-edd-via-s3-compatible'), '<strong>' . esc_html($this->config->getBucket()) . '</strong>');
                        }
                        ?>
                    </p>
                    <div class="s3cs-notice-details">
                        <p><strong><?php esc_html_e('Possible reasons:', 'storage-for-edd-via-s3-compatible'); ?></strong></p>
                        <ul>
                            <li><?php esc_html_e('The bucket or path is empty', 'storage-for-edd-via-s3-compatible'); ?></li>
                            <li><?php esc_html_e('Your S3 credentials lack proper read permissions', 'storage-for-edd-via-s3-compatible'); ?></li>
                            <li><?php esc_html_e('The bucket name or endpoint URL is incorrect', 'storage-for-edd-via-s3-compatible'); ?></li>
                        </ul>
                    </div>
                    <p>
                        <a href="<?php echo esc_url(admin_url('edit.php?post_type=download&page=edd-settings&tab=extensions&section=s3cs-settings')); ?>" class="button-primary">
                            <?php esc_html_e('Check S3 Settings', 'storage-for-edd-via-s3-compatible'); ?>
                        </a>
                    </p>
                </div>

            <?php } ?>
        </div>
        <?php
    }
    
    public function registerS3UploadTab() {
        // Check user capability for uploading to S3
        $uploadCapability = apply_filters('s3cs_edd_upload_cap', 'edit_products');
        if (!current_user_can($uploadCapability)) {
            wp_die(esc_html__('You do not have permission to upload files to S3.', 'storage-for-edd-via-s3-compatible'));
        }
        
        // Check nonce for GET requests with parameters
        if (!empty($_GET) && (isset($_GET['path']) || isset($_GET['_wpnonce']))) {
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'media-form')) {
                wp_die(esc_html__('Security check failed.', 'storage-for-edd-via-s3-compatible'));
            }
        }
        
        if (!empty($_POST)) {
            // Nonce check for security
            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'media-form')) {
                wp_die(esc_html__('Security check failed.', 'storage-for-edd-via-s3-compatible'));
            }
            
            $error = media_upload_form_handler();
            if (is_string($error)) {
                return $error;
            }
        }
        wp_iframe(array($this, 'renderS3UploadTab'));
    }
    
    public function renderS3UploadTab() {
        wp_enqueue_style('media');
        // Enqueue S3 styles and scripts
        wp_enqueue_style('s3cs-upload');
        wp_enqueue_style('s3cs-media-container');
        wp_enqueue_script('s3cs-upload');
        $path = $this->get_path();
        ?>



        <div id="media-items" class="s3cs-media-container">
            <h3 class="media-title"><?php esc_html_e('Upload to S3', 'storage-for-edd-via-s3-compatible'); ?></h3>
            <div class="description">
                <?php 
                if (!empty($path)) {
                    // translators: %s: Directory path where files are being uploaded.
                    printf(esc_html__('Uploading to directory: %s', 'storage-for-edd-via-s3-compatible'), '<strong>' . esc_html($path) . '</strong>');
                } else {
                    // translators: %s: S3 bucket name where files are being uploaded.
                    printf(esc_html__('Uploading to bucket: %s', 'storage-for-edd-via-s3-compatible'), '<strong>' . esc_html($this->config->getBucket()) . '</strong>');
                }
                ?>
            </div>
            <?php
            $successFlag = filter_input(INPUT_GET, 's3cs_edd_success', FILTER_SANITIZE_NUMBER_INT);
            $errorMsg = filter_input(INPUT_GET, 'error', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            if ($errorMsg) {
                // Log detailed error for debugging
                $this->config->debug('Upload error: ' . $errorMsg);
                ?>
                <div class="edd_errors s3cs-notice warning">
                    <h4><?php esc_html_e('Error', 'storage-for-edd-via-s3-compatible'); ?></h4>
                    <p class="edd_error"><?php esc_html_e('An error occurred during the upload process. Please try again.', 'storage-for-edd-via-s3-compatible'); ?></p>
                </div>
                <?php
            }

            if (!empty($successFlag) && '1' == $successFlag) {
                $savedPathAndFilename = filter_input(INPUT_GET, 's3cs_edd_filename', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                $savedPathAndFilename = sanitize_text_field($savedPathAndFilename);
                $lastSlashPos = strrpos($savedPathAndFilename, '/');
                $savedFilename = $lastSlashPos !== false ? substr($savedPathAndFilename, $lastSlashPos + 1) : $savedPathAndFilename;
                ?>
                <div class="edd_errors s3cs-notice success">
                    <h4><?php esc_html_e('Upload Successful', 'storage-for-edd-via-s3-compatible'); ?></h4>
                    <p class="edd_success">
                        <?php
                        // translators: %s: File name.
                        printf(esc_html__('File %s uploaded successfully!', 'storage-for-edd-via-s3-compatible'), '<strong>' . esc_html($savedFilename) . '</strong>');
                        ?>
                    </p>
                    <?php if ($lastSlashPos !== false) { ?>
                        <p class="s3cs-upload-path">
                            <?php
                            // translators: %s: File path.
                            printf(esc_html__('Path: %s', 'storage-for-edd-via-s3-compatible'), '<span class="s3cs-path">' . esc_html(substr($savedPathAndFilename, 0, $lastSlashPos + 1)) . '</span>');
                            ?>
                        </p>
                    <?php } ?>
                    <p>
                        <a href="javascript:void(0)" 
                           id="s3cs_edd_save_link" 
                           class="button-primary"
                           data-s3-fn="<?php echo esc_attr($savedFilename); ?>" 
                           data-s3-path="<?php echo esc_attr($savedPathAndFilename); ?>">
                            <?php esc_html_e('Use this file in your Download', 'storage-for-edd-via-s3-compatible'); ?>
                        </a>
                    </p>
                </div>
                <?php
            }
            ?>
            <form enctype="multipart/form-data" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="s3cs-upload-form">
                <?php wp_nonce_field('s3cs_edd_upload', 's3cs_edd_nonce'); ?>
                <input type="hidden" name="action" value="s3cs_upload" />
                <div class="upload-field">
                    <input type="file" 
                           name="s3cs_edd_file" 
                           accept=".zip,.rar,.7z,.pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif"/>
                </div>
                <p class="description">
                    <?php
                    // translators: %s: Maximum upload file size.
                    printf(esc_html__('Maximum upload file size: %s', 'storage-for-edd-via-s3-compatible'), esc_html(size_format(wp_max_upload_size())));
                    ?>
                </p>
                <div class="upload-actions">
                    <input type="submit" 
                           class="button-primary" 
                           value="<?php esc_attr_e('Upload to S3', 'storage-for-edd-via-s3-compatible'); ?>"/>
                </div>
                <input type="hidden" name="s3cs_edd_path" value="<?php echo esc_attr($path); ?>" />
            </form>
        </div>
        <?php
    }
    
    public function setupAdminJS() {
        // Enqueue the admin upload buttons script
        wp_enqueue_script('s3cs-admin-upload-buttons');
    }
    
    /**
     * Get current path from GET param.
     * @return string
     */
    private function get_path() {
        // Additional security check - verify nonce if path parameter is present
        if (!empty($_GET['path'])) {
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'media-form')) {
                wp_die(esc_html__('Security check failed.', 'storage-for-edd-via-s3-compatible'));
            }
        }
        return !empty($_GET['path']) ? sanitize_text_field(wp_unslash($_GET['path'])) : '';
    }
    
    /**
     * Get detailed information about files from S3
     * @param array $files
     * @return array
     */
    private function getFileDetails($files) {
        $client = $this->client->getClient();
        $bucket = $this->config->getBucket();
        $details = array();
        
        if (!$client || !$bucket || empty($files)) {
            return $details;
        }
        
        try {
            $endpoint = $this->config->getEndpoint();
            $accessKey = $this->config->getAccessKey();
            $secretKey = $this->config->getSecretKey();
            $region = $this->config->getRegion();
            
            // Create authorization for list objects v2 with detailed info
            $date = gmdate('Ymd\THis\Z');
            $shortDate = gmdate('Ymd');
            $service = 's3';
            
            $method = 'GET';
            $canonicalUri = "/" . $bucket;
            $canonicalQueryString = 'list-type=2';
            
            $canonicalHeaders = "host:" . wp_parse_url($endpoint, PHP_URL_HOST) . "\nx-amz-content-sha256:e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855\nx-amz-date:$date\n";
            $signedHeaders = 'host;x-amz-content-sha256;x-amz-date';
            $payloadHash = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';
            
            $canonicalRequest = "$method\n$canonicalUri\n$canonicalQueryString\n$canonicalHeaders\n$signedHeaders\n$payloadHash";
            
            $algorithm = 'AWS4-HMAC-SHA256';
            $credentialScope = "$shortDate/$region/$service/aws4_request";
            $stringToSign = "$algorithm\n$date\n$credentialScope\n" . hash('sha256', $canonicalRequest);
            
            $kSecret = 'AWS4' . $secretKey;
            $kDate = hash_hmac('sha256', $shortDate, $kSecret, true);
            $kRegion = hash_hmac('sha256', $region, $kDate, true);
            $kService = hash_hmac('sha256', $service, $kRegion, true);
            $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
            $signature = hash_hmac('sha256', $stringToSign, $kSigning);
            
            $authorization = "$algorithm Credential=$accessKey/$credentialScope, SignedHeaders=$signedHeaders, Signature=$signature";
            
            $requestUrl = rtrim($endpoint, '/') . "/$bucket?list-type=2";
            
            $response = $client->request('GET', $requestUrl, [
                'headers' => [
                    'Host' => wp_parse_url($endpoint, PHP_URL_HOST),
                    'X-Amz-Content-SHA256' => $payloadHash,
                    'X-Amz-Date' => $date,
                    'Authorization' => $authorization
                ]
            ]);
            
            // Get response content
            $responseContent = $response->getBody()->getContents();
            
            // Suppress warnings and check if content is valid XML
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($responseContent);
            
            // If XML parsing failed, log the error
            if ($xml === false) {
                $errors = libxml_get_errors();
                libxml_clear_errors();
                $errorMsg = 'XML parsing error: ';
                foreach ($errors as $error) {
                    $errorMsg .= $error->message . ' ';
                }
                $this->config->debug($errorMsg);
                $this->config->debug('Response status: ' . $response->getStatusCode() . ' - XML parsing failed');
                libxml_use_internal_errors(false);
                return $details;
            }
            libxml_use_internal_errors(false);
            
            if (isset($xml->Contents)) {
                foreach ($xml->Contents as $object) {
                    $key = (string)$object->Key;
                    $details[$key] = array(
                        'size' => (int)$object->Size,
                        'last_modified' => (string)$object->LastModified,
                        'owner' => isset($object->Owner->DisplayName) ? (string)$object->Owner->DisplayName : esc_html__('Unknown', 'storage-for-edd-via-s3-compatible')
                    );
                }
            }
        } catch (Exception $e) {
            $this->config->debug('Error getting file details: ' . $e->getMessage());
        }
        
        return $details;
    }
    
    /**
     * Format file size in human readable format
     * @param int $size
     * @return string
     */
    private function formatFileSize($size) {
        if ($size == 0) return '0 B';
        
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        $power = floor(log($size, 1024));
        
        return round($size / pow(1024, $power), 2) . ' ' . $units[$power];
    }
    
    /**
      * Format date in human readable format
      * @param string $date
      * @return string
      */
    private function formatHumanDate($date) {
        try {
            $timestamp = strtotime($date);
            if ($timestamp) {
                // Always display absolute date format (e.g., "March 2, 2024")
                return date_i18n('j F Y', $timestamp);
            }
        } catch (Exception $e) {
            // Ignore date formatting errors
        }
        return $date;
    }
    
    /**
     * Enqueue CSS styles and JS scripts for S3 media library
     */
    public function enqueueStyles() {
        // Register styles - we'll enqueue them directly in the tabs where needed
        wp_register_style('s3cs-media-library', S3CS_EDD_PLUGIN_URL . 'assets/css/s3-media-library.css', array(), S3CS_EDD_VERSION);
        wp_register_style('s3cs-upload', S3CS_EDD_PLUGIN_URL . 'assets/css/s3-upload.css', array(), S3CS_EDD_VERSION);
        wp_register_style('s3cs-media-container', S3CS_EDD_PLUGIN_URL . 'assets/css/s3-media-container.css', array(), S3CS_EDD_VERSION);
        
        // Register scripts
        wp_register_script('s3cs-media-library', S3CS_EDD_PLUGIN_URL . 'assets/js/s3-media-library.js', array('jquery'), S3CS_EDD_VERSION, true);
        wp_register_script('s3cs-upload', S3CS_EDD_PLUGIN_URL . 'assets/js/s3-upload.js', array('jquery'), S3CS_EDD_VERSION, true);
        wp_register_script('s3cs-admin-upload-buttons', S3CS_EDD_PLUGIN_URL . 'assets/js/admin-upload-buttons.js', array('jquery'), S3CS_EDD_VERSION, true);
        
        // Localize scripts
        wp_localize_script('s3cs-media-library', 's3cs_edd_i18n', array(
            'file_selected_success' => esc_html__('File selected successfully!', 'storage-for-edd-via-s3-compatible'),
            'file_selected_error' => esc_html__('Error selecting file. Please try again.', 'storage-for-edd-via-s3-compatible')
        ));
        
        wp_add_inline_script('s3cs-media-library', 'var s3cs_edd_url_prefix = "' . esc_js($this->config->getUrlPrefix()) . '";', 'before');
        
        wp_localize_script('s3cs-upload', 's3cs_edd_i18n', array(
            'file_size_too_large' => esc_html__('File size too large. Maximum allowed size is', 'storage-for-edd-via-s3-compatible')
        ));
        
        // Add URL prefix as inline script
        wp_add_inline_script('s3cs-upload', 'var s3cs_edd_url_prefix = "' . esc_js($this->config->getUrlPrefix()) . '";', 'before');
        // Add max upload size as inline script
        wp_add_inline_script('s3cs-upload', 'var s3cs_edd_max_upload_size = ' . wp_max_upload_size() . ';', 'before');
        
        // Add URL prefix as inline script
        wp_add_inline_script('s3cs-admin-upload-buttons', 'var s3cs_edd_url_prefix = "' . esc_js($this->config->getUrlPrefix()) . '";', 'before');
    }
}