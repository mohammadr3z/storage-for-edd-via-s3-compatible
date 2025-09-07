<?php
if (!defined('ABSPATH')) {
    exit;
}

class S3CS_EDD_S3_Uploader {
    private $client;
    private $config;
    
    public function __construct() {
        $this->config = new S3CS_EDD_S3_Config();
        $this->client = new S3CS_EDD_S3_Client();
        
        // Register upload handler for admin-post.php
        add_action('admin_post_s3cs_upload', array($this, 'performFileUpload'));
    }
    
    /**
     * Handle file upload to S3.
     */
    public function performFileUpload() {
        if (!is_admin()) {
            return;
        }
        
        $uploadCapability = apply_filters('s3cs_edd_upload_cap', 'edit_products');
        if (!current_user_can($uploadCapability)) {
            wp_die(esc_html__('You do not have permission to upload files to S3.', 'storage-for-edd-via-s3-compatible'));
        }
        
        if (!$this->validateUpload()) {
            return;
        }
        
        $path = filter_input(INPUT_POST, 's3cs_edd_path', FILTER_SANITIZE_URL);
        if ($path && substr($path, -1) !== '/') {
            $path .= '/';
        }
        
        // Check and sanitize file name
        $filename = '';
        if (isset($_FILES['s3cs_edd_file']['name']) && !empty($_FILES['s3cs_edd_file']['name'])) {
            $filename = $path . sanitize_file_name($_FILES['s3cs_edd_file']['name']);
        } else {
            wp_die(esc_html__('No file selected for upload.', 'storage-for-edd-via-s3-compatible'), esc_html__('Error', 'storage-for-edd-via-s3-compatible'), array('back_link' => true));
        }
        
        $client = $this->client->getClient();
        $bucket = $this->config->getBucket();
        
        if (!$client || !$bucket) {
            wp_die(esc_html__('S3 configuration is incomplete.', 'storage-for-edd-via-s3-compatible'), esc_html__('Error', 'storage-for-edd-via-s3-compatible'), array('back_link' => true));
        }
        
        try {
            $endpoint = $this->config->getEndpoint();
            $accessKey = $this->config->getAccessKey();
            $secretKey = $this->config->getSecretKey();
            $region = $this->config->getRegion();
            
            // Create authorization header
            $date = gmdate('Ymd\\THis\\Z');
            $shortDate = gmdate('Ymd');
            $service = 's3';
            
            // Calculate content hash with security check
            $fileContent = '';
            if (isset($_FILES['s3cs_edd_file']['tmp_name']) &&
            is_uploaded_file($_FILES['s3cs_edd_file']['tmp_name']) &&
            is_readable($_FILES['s3cs_edd_file']['tmp_name'])) {
                $fileContent = file_get_contents($_FILES['s3cs_edd_file']['tmp_name']);
                if ($fileContent === false) {
                    wp_die(esc_html__('Unable to read uploaded file.', 'storage-for-edd-via-s3-compatible'), esc_html__('Error', 'storage-for-edd-via-s3-compatible'), array('back_link' => true));
                }
            } else {
                wp_die(esc_html__('Invalid file upload.', 'storage-for-edd-via-s3-compatible'), esc_html__('Error', 'storage-for-edd-via-s3-compatible'), array('back_link' => true));
            }
            
            $contentHash = hash('sha256', $fileContent);
            
            // Create canonical request
            $method = 'PUT';
            $canonicalUri = "/$bucket/$filename";
            $canonicalQueryString = '';
            $canonicalHeaders = "content-length:" . strlen($fileContent) . "\nhost:" . wp_parse_url($endpoint, PHP_URL_HOST) . "\nx-amz-content-sha256:$contentHash\nx-amz-date:$date\n";
            $signedHeaders = 'content-length;host;x-amz-content-sha256;x-amz-date';
            
            $canonicalRequest = "$method\n$canonicalUri\n$canonicalQueryString\n$canonicalHeaders\n$signedHeaders\n$contentHash";
            
            // Create string to sign
            $algorithm = 'AWS4-HMAC-SHA256';
            $credentialScope = "$shortDate/$region/$service/aws4_request";
            $stringToSign = "$algorithm\n$date\n$credentialScope\n" . hash('sha256', $canonicalRequest);
            
            // Calculate signature
            $kSecret = 'AWS4' . $secretKey;
            $kDate = hash_hmac('sha256', $shortDate, $kSecret, true);
            $kRegion = hash_hmac('sha256', $region, $kDate, true);
            $kService = hash_hmac('sha256', $service, $kRegion, true);
            $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
            $signature = hash_hmac('sha256', $stringToSign, $kSigning);
            
            // Create authorization header
            $authorization = "$algorithm Credential=$accessKey/$credentialScope, SignedHeaders=$signedHeaders, Signature=$signature";
            
            // Upload file
            $response = $client->request('PUT', "/$bucket/$filename", [
                'headers' => [
                    'Content-Length' => strlen($fileContent),
                    'Host' => wp_parse_url($endpoint, PHP_URL_HOST),
                    'X-Amz-Content-SHA256' => $contentHash,
                    'X-Amz-Date' => $date,
                    'Authorization' => $authorization
                ],
                'body' => $fileContent
            ]);
            
            // Create secure redirect URL
            $referer = wp_get_referer();
            if (!$referer) {
                $referer = admin_url('admin.php?page=edd-settings&tab=extensions&section=s3cs-settings');
            }
            
            $redirectURL = add_query_arg(
                array(
                    's3cs_edd_success'  => '1',
                    's3cs_edd_filename' => rawurlencode($filename),
                ),
                $referer
            );
            wp_safe_redirect(esc_url_raw($redirectURL));
            exit;
        } catch (Exception $e) {
            $this->config->debug('File upload error: ' . $e->getMessage());
            wp_die(esc_html__('An error occurred while attempting to upload your file.', 'storage-for-edd-via-s3-compatible'), esc_html__('Error', 'storage-for-edd-via-s3-compatible'), array('back_link' => true));
        }
    }
    
    /**
     * Validate file upload.
     * @return bool
     */
    private function validateUpload() {
        // Check nonce first
        if (!isset($_POST['s3cs_edd_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['s3cs_edd_nonce'])), 's3cs_edd_upload')) {
            wp_die(esc_html__('Security check failed.', 'storage-for-edd-via-s3-compatible'), esc_html__('Error', 'storage-for-edd-via-s3-compatible'), array('back_link' => true));
            return false;
        }
        
        // Check for file existence and its components
        if (!isset($_FILES['s3cs_edd_file']) ||
            !isset($_FILES['s3cs_edd_file']['name']) ||
            !isset($_FILES['s3cs_edd_file']['tmp_name']) ||
            !isset($_FILES['s3cs_edd_file']['size']) ||
            empty($_FILES['s3cs_edd_file']['name'])) {
            wp_die(esc_html__('Please select a file to upload.', 'storage-for-edd-via-s3-compatible'), esc_html__('Error', 'storage-for-edd-via-s3-compatible'), array('back_link' => true));
            return false;
        }
        
        // Check uploaded file security
        if (!is_uploaded_file($_FILES['s3cs_edd_file']['tmp_name'])) {
            wp_die(esc_html__('Invalid file upload.', 'storage-for-edd-via-s3-compatible'), esc_html__('Error', 'storage-for-edd-via-s3-compatible'), array('back_link' => true));
            return false;
        }
        
        // Validate file type
        if (!$this->isAllowedFileType($_FILES['s3cs_edd_file']['name'])) {
            wp_die(esc_html__('File type not allowed. Only safe file types are permitted.', 'storage-for-edd-via-s3-compatible'), esc_html__('Error', 'storage-for-edd-via-s3-compatible'), array('back_link' => true));
            return false;
        }
        
        // Check and sanitize file size
        $fileSize = absint($_FILES['s3cs_edd_file']['size']);
        $maxSize = wp_max_upload_size();
        if ($fileSize > $maxSize || $fileSize <= 0) {
            wp_die(
                // translators: %s: Maximum upload file size.
                sprintf(esc_html__('File size too large. Maximum allowed size is %s', 'storage-for-edd-via-s3-compatible'), esc_html(size_format($maxSize))),
                esc_html__('Error', 'storage-for-edd-via-s3-compatible'),
                array('back_link' => true)
            );
            return false;
        }
        
        return true;
    }
    
    /**
     * Check if file type is allowed (simple extension-based validation)
     * @param string $filename
     * @return bool
     */
    private function isAllowedFileType($filename) {
        // Get file extension
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        // Allowed safe extensions for digital products
        $allowedExtensions = array(
            'zip', 'rar', '7z', 'tar', 'gz',
            'pdf', 'doc', 'docx', 'txt', 'rtf',
            'jpg', 'jpeg', 'png', 'gif', 'webp',
            'mp3', 'wav', 'ogg', 'flac', 'm4a',
            'mp4', 'avi', 'mov', 'wmv', 'flv', 'webm',
            'epub', 'mobi', 'azw', 'azw3',
            'xls', 'xlsx', 'csv',
            'ppt', 'pptx',
            'css', 'js', 'json', 'xml'
        );
        
        // Check if extension is in allowed list
        if (!in_array($extension, $allowedExtensions, true)) {
            return false;
        }
        
        // Block dangerous file patterns
        $dangerousPatterns = array(
            '.php', '.phtml', '.asp', '.aspx', '.jsp', '.cgi', '.pl', '.py',
            '.exe', '.com', '.bat', '.cmd', '.scr', '.vbs', '.jar',
            '.sh', '.bash', '.zsh', '.fish', '.htaccess', '.htpasswd'
        );
        
        $lowerFilename = strtolower($filename);
        foreach ($dangerousPatterns as $pattern) {
            if (strpos($lowerFilename, $pattern) !== false) {
                return false;
            }
        }
        
        return true;
    }
}