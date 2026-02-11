<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * S3 Uploader
 *
 * Handles file uploads to S3 from WordPress admin.
 */
class S3CS_EDD_S3_Uploader
{
    private $client;
    private $config;

    public function __construct()
    {
        $this->config = new S3CS_EDD_S3_Config();
        $this->client = new S3CS_EDD_S3_Client();

        // Register upload handler for admin-post.php
        add_action('admin_post_s3cs_edd_upload', array($this, 'performFileUpload'));

        // Register AJAX upload handler
        add_action('wp_ajax_s3cs_edd_ajax_upload', array($this, 'ajaxUpload'));
    }

    /**
     * Handle file upload to S3.
     */
    public function performFileUpload()
    {
        if (!is_admin()) {
            return;
        }

        // Verify Nonce
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is happening right here
        if (!isset($_POST['s3cs_edd_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['s3cs_edd_nonce'])), 's3cs_edd_upload')) {
            wp_die(esc_html__('Security check failed.', 'storage-for-edd-via-s3-compatible'), esc_html__('Error', 'storage-for-edd-via-s3-compatible'), array('back_link' => true));
        }

        $uploadCapability = apply_filters('s3cs_edd_upload_cap', 'edit_products');
        if (!current_user_can($uploadCapability)) {
            wp_die(esc_html__('You do not have permission to upload files to S3.', 'storage-for-edd-via-s3-compatible'));
        }

        if (!$this->validateUpload()) {
            return;
        }

        $path = filter_input(INPUT_POST, 's3cs_edd_path', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        if ($path && substr($path, -1) !== '/') {
            $path .= '/';
        }

        try {
            // Processing upload
            $path_display = $this->processUpload($_FILES['s3cs_edd_file'], $path);

            // Create secure redirect URL
            $referer = wp_get_referer();
            if (!$referer) {
                $referer = admin_url('admin.php?page=edd-settings&tab=extensions&section=s3cs-settings');
            }

            $redirectURL = add_query_arg(
                array(
                    's3cs_edd_success'  => '1',
                    's3cs_edd_filename' => rawurlencode($path_display),
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
     * Handle AJAX file upload.
     */
    public function ajaxUpload()
    {
        check_ajax_referer('s3cs_edd_upload', 's3cs_edd_nonce');

        $uploadCapability = apply_filters('s3cs_edd_upload_cap', 'edit_products');
        if (!current_user_can($uploadCapability)) {
            wp_send_json_error(esc_html__('You do not have permission to upload files to S3.', 'storage-for-edd-via-s3-compatible'));
        }

        // Use checkUploadValidation for better AJAX error handling
        $validation = $this->checkUploadValidation();
        if ($validation !== true) {
            wp_send_json_error($validation);
        }

        $path = isset($_POST['s3cs_edd_path']) ? sanitize_text_field(wp_unslash($_POST['s3cs_edd_path'])) : '';
        if (!empty($path) && substr($path, -1) !== '/') {
            $path .= '/';
        }

        if (!$this->config->isConfigured()) {
            wp_send_json_error(esc_html__('S3 is not configured.', 'storage-for-edd-via-s3-compatible'));
        }

        try {
            $path_display = $this->processUpload($_FILES['s3cs_edd_file'], $path);

            // Return success with file info
            wp_send_json_success(array(
                'message' => esc_html__('File uploaded successfully!', 'storage-for-edd-via-s3-compatible'),
                'filename' => basename($path_display),
                'path' => $path_display,
                // Ensure data keys match what JS expects
                's3cs_link' => ltrim($path_display, '/')
            ));
        } catch (Exception $e) {
            $this->config->debug('AJAX upload error: ' . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Core upload processing logic
     * 
     * @param array $file_array $_FILES item
     * @param string $path Target folder path
     * @return string Uploaded file path (display path)
     * @throws Exception
     */
    private function processUpload($file_array, $path)
    {
        // Check and sanitize file name
        $filename = '';
        if (isset($file_array['name']) && !empty($file_array['name'])) {
            $filename = $path . sanitize_file_name($file_array['name']);
        } else {
            throw new Exception(esc_html__('No file selected.', 'storage-for-edd-via-s3-compatible'));
        }

        $client = $this->client->getClient();
        $bucket = $this->config->getBucket();

        if (!$client || !$bucket) {
            throw new Exception(esc_html__('S3 configuration is incomplete.', 'storage-for-edd-via-s3-compatible'));
        }

        $endpoint = $this->config->getEndpoint();
        $accessKey = $this->config->getAccessKey();
        $secretKey = $this->config->getSecretKey();
        $region = $this->config->getRegion();

        // Create authorization header
        $date = gmdate('Ymd\\THis\\Z');
        $shortDate = gmdate('Ymd');
        $service = 's3';

        // Read file content securely
        // Read file content securely
        // NOTE: We now use streaming upload to avoid loading file into memory
        if (
            !isset($file_array['tmp_name']) ||
            !is_uploaded_file($file_array['tmp_name']) ||
            !is_readable($file_array['tmp_name'])
        ) {
            throw new Exception(esc_html__('Invalid file upload.', 'storage-for-edd-via-s3-compatible'));
        }

        $filePath = $file_array['tmp_name'];

        // Calculate hash efficiently without loading file
        $contentHash = hash_file('sha256', $filePath);
        $fileSize = filesize($filePath);

        if ($contentHash === false || $fileSize === false) {
            throw new Exception(esc_html__('Failed to calculate file hash or size.', 'storage-for-edd-via-s3-compatible'));
        }

        // Detect and validate Content-Type
        $filetype = wp_check_filetype_and_ext($filePath, $file_array['name']);

        // Better MIME type detection
        if (!empty($filetype['type'])) {
            $contentType = $filetype['type'];
        } elseif (function_exists('mime_content_type')) {
            $contentType = mime_content_type($filePath);
        } else {
            $contentType = 'application/octet-stream';
        }

        // Encode filename parts for Canonical URI to match AWS requirements and Guzzle's behavior
        $explodedFilename = explode('/', $filename);
        $encodedFilenameParts = array_map('rawurlencode', $explodedFilename);
        $encodedFilename = implode('/', $encodedFilenameParts);

        // Create canonical request
        $method = 'PUT';
        $canonicalUri = "/$bucket/$encodedFilename";
        $canonicalQueryString = '';
        $canonicalHeaders = "content-length:$fileSize\ncontent-type:$contentType\nhost:" . wp_parse_url($endpoint, PHP_URL_HOST) . "\nx-amz-content-sha256:$contentHash\nx-amz-date:$date\n";
        $signedHeaders = 'content-length;content-type;host;x-amz-content-sha256;x-amz-date';

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

        // Open stream
        $stream = fopen($filePath, 'r');
        if ($stream === false) {
            throw new Exception(esc_html__('Failed to open file stream.', 'storage-for-edd-via-s3-compatible'));
        }

        try {
            // Upload file
            // Use encoded filename to prevent double encoding or mismatches
            $response = $client->request('PUT', "/$bucket/$encodedFilename", [
                'headers' => [
                    'Content-Type' => $contentType,
                    'Content-Length' => $fileSize,
                    'Host' => wp_parse_url($endpoint, PHP_URL_HOST),
                    'X-Amz-Content-SHA256' => $contentHash,
                    'X-Amz-Date' => $date,
                    'Authorization' => $authorization
                ],
                'body' => $stream
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode < 200 || $statusCode >= 300) {
                throw new Exception(sprintf(
                    // translators: %1$s: Status code, %2$s: Reason phrase
                    esc_html__('S3 upload failed with status: %1$s %2$s', 'storage-for-edd-via-s3-compatible'),
                    $statusCode,
                    $response->getReasonPhrase()
                ));
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return $filename;
    }

    /**
     * Helper to return validation result without dying (for AJAX)
     * @return bool|string Returns true on success, or error message string on failure
     */
    private function checkUploadValidation()
    {
        // Check for file existence and its components
        if (
            !isset($_FILES['s3cs_edd_file']) ||
            !isset($_FILES['s3cs_edd_file']['name']) ||
            !isset($_FILES['s3cs_edd_file']['tmp_name']) ||
            !isset($_FILES['s3cs_edd_file']['size']) ||
            empty($_FILES['s3cs_edd_file']['name'])
        ) {
            return esc_html__('Please select a file to upload.', 'storage-for-edd-via-s3-compatible');
        }

        // Check uploaded file security
        if (!is_uploaded_file($_FILES['s3cs_edd_file']['tmp_name'])) {
            return esc_html__('Invalid file upload.', 'storage-for-edd-via-s3-compatible');
        }

        // Validate file type
        if (!$this->isAllowedFileType($_FILES['s3cs_edd_file']['name'])) {
            return esc_html__('File type not allowed. Only safe file types are permitted.', 'storage-for-edd-via-s3-compatible');
        }

        // Validate Content-Type (MIME type)
        if (!$this->validateFileContentType($_FILES['s3cs_edd_file'])) {
            return esc_html__('File content type validation failed. The file may be corrupted or have an incorrect extension.', 'storage-for-edd-via-s3-compatible');
        }

        // Check and sanitize file size
        $fileSize = absint($_FILES['s3cs_edd_file']['size']);
        $maxSize = wp_max_upload_size();
        if ($fileSize > $maxSize || $fileSize <= 0) {
            return sprintf(
                // translators: %s: Maximum upload file size.
                esc_html__('File size too large. Maximum allowed size is %s', 'storage-for-edd-via-s3-compatible'),
                esc_html(size_format($maxSize))
            );
        }

        return true;
    }

    /**
     * Validate file upload (legacy wrapper for non-AJAX calls).
     * @return bool
     */
    private function validateUpload()
    {
        $result = $this->checkUploadValidation();
        if ($result === true) {
            return true;
        }

        wp_die($result, esc_html__('Error', 'storage-for-edd-via-s3-compatible'), array('back_link' => true));
        return false;
    }

    /**
     * Check if file type is allowed (simple extension-based validation)
     * @param string $filename
     * @return bool
     */
    private function isAllowedFileType($filename)
    {
        // Get file extension
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        // Allowed safe extensions for digital products
        $allowedExtensions = array(
            'zip',
            'rar',
            '7z',
            'tar',
            'gz',
            'pdf',
            'doc',
            'docx',
            'txt',
            'rtf',
            'jpg',
            'jpeg',
            'png',
            'gif',
            'webp',
            'mp3',
            'wav',
            'ogg',
            'flac',
            'm4a',
            'mp4',
            'avi',
            'mov',
            'wmv',
            'flv',
            'webm',
            'epub',
            'mobi',
            'azw',
            'azw3',
            'xls',
            'xlsx',
            'csv',
            'ppt',
            'pptx',
            'css',
            'js',
            'json',
            'xml'
        );

        // Check if extension is in allowed list
        if (!in_array($extension, $allowedExtensions, true)) {
            return false;
        }

        // Block dangerous file patterns
        $dangerousPatterns = array(
            '.php',
            '.phtml',
            '.asp',
            '.aspx',
            '.jsp',
            '.cgi',
            '.pl',
            '.py',
            '.exe',
            '.com',
            '.bat',
            '.cmd',
            '.scr',
            '.vbs',
            '.jar',
            '.sh',
            '.bash',
            '.zsh',
            '.fish',
            '.htaccess',
            '.htpasswd'
        );

        $lowerFilename = strtolower($filename);
        foreach ($dangerousPatterns as $pattern) {
            if (strpos($lowerFilename, $pattern) !== false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate file content type (MIME type) matches the file extension
     * @param array $file The uploaded file array from $_FILES
     * @return bool
     */
    private function validateFileContentType($file)
    {
        // Ensure we have the required file information
        if (!isset($file['tmp_name']) || !isset($file['name'])) {
            return false;
        }

        // Use WordPress's built-in function to check file type and extension
        // This function checks both the MIME type and the file extension
        $filetype = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);

        // Check if the file type was detected
        if (!$filetype || !isset($filetype['ext']) || !isset($filetype['type'])) {
            return false;
        }

        // If extension or type is false, the file failed validation
        if (false === $filetype['ext'] || false === $filetype['type']) {
            return false;
        }

        // Additional check: ensure the detected extension matches what we expect
        $actualExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($filetype['ext'] !== $actualExtension) {
            // Extension mismatch - possible file type spoofing
            return false;
        }

        // Validate against allowed MIME types
        $allowedMimeTypes = array(
            // Archives
            'application/zip',
            'application/x-zip-compressed',
            'application/x-rar-compressed',
            'application/x-7z-compressed',
            'application/x-tar',
            'application/gzip',
            'application/x-gzip',
            // Documents
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'text/plain',
            'application/rtf',
            // Images
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif',
            'image/webp',
            // Audio
            'audio/mpeg',
            'audio/mp3',
            'audio/wav',
            'audio/ogg',
            'audio/flac',
            'audio/x-m4a',
            // Video
            'video/mp4',
            'video/mpeg',
            'video/quicktime',
            'video/x-msvideo',
            'video/x-ms-wmv',
            'video/x-flv',
            'video/webm',
            // E-books
            'application/epub+zip',
            'application/x-mobipocket-ebook',
            // Spreadsheets
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/csv',
            // Presentations
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            // Web files
            'text/css',
            'application/javascript',
            'text/javascript',
            'application/json',
            'application/xml',
            'text/xml',
        );

        // Apply filter to allow customization
        $allowedMimeTypes = apply_filters('s3cs_edd_allowed_mime_types', $allowedMimeTypes);

        // Check if the detected MIME type is in our allowed list
        if (!in_array($filetype['type'], $allowedMimeTypes, true)) {
            return false;
        }

        return true;
    }
}
