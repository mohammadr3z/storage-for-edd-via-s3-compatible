<?php
if (!defined('ABSPATH')) {
    exit;
}

class S3CS_EDD_S3_Uploader
{
    private $client;
    private $config;

    public function __construct()
    {
        $this->config = new S3CS_EDD_S3_Config();
        $this->client = new S3CS_EDD_S3_Client();

        // Register upload handler for admin-post.php
        add_action('admin_post_s3cs_upload', array($this, 'performFileUpload'));
    }

    /**
     * Handle file upload to S3.
     */
    public function performFileUpload()
    {
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
            if (
                isset($_FILES['s3cs_edd_file']['tmp_name']) &&
                is_uploaded_file($_FILES['s3cs_edd_file']['tmp_name']) &&
                is_readable($_FILES['s3cs_edd_file']['tmp_name'])
            ) {
                $fileContent = file_get_contents($_FILES['s3cs_edd_file']['tmp_name']);
                if ($fileContent === false) {
                    wp_die(esc_html__('Unable to read uploaded file.', 'storage-for-edd-via-s3-compatible'), esc_html__('Error', 'storage-for-edd-via-s3-compatible'), array('back_link' => true));
                }
            } else {
                wp_die(esc_html__('Invalid file upload.', 'storage-for-edd-via-s3-compatible'), esc_html__('Error', 'storage-for-edd-via-s3-compatible'), array('back_link' => true));
            }

            $contentHash = hash('sha256', $fileContent);

            // Detect and validate Content-Type
            $filetype = wp_check_filetype_and_ext($_FILES['s3cs_edd_file']['tmp_name'], $_FILES['s3cs_edd_file']['name']);
            $contentType = !empty($filetype['type']) ? $filetype['type'] : 'application/octet-stream';

            // Create canonical request
            $method = 'PUT';
            $canonicalUri = "/$bucket/$filename";
            $canonicalQueryString = '';
            $canonicalHeaders = "content-length:" . strlen($fileContent) . "\ncontent-type:$contentType\nhost:" . wp_parse_url($endpoint, PHP_URL_HOST) . "\nx-amz-content-sha256:$contentHash\nx-amz-date:$date\n";
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

            // Upload file
            $response = $client->request('PUT', "/$bucket/$filename", [
                'headers' => [
                    'Content-Type' => $contentType,
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
    private function validateUpload()
    {
        // Check nonce first
        if (!isset($_POST['s3cs_edd_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['s3cs_edd_nonce'])), 's3cs_edd_upload')) {
            wp_die(esc_html__('Security check failed.', 'storage-for-edd-via-s3-compatible'), esc_html__('Error', 'storage-for-edd-via-s3-compatible'), array('back_link' => true));
            return false;
        }

        // Check for file existence and its components
        if (
            !isset($_FILES['s3cs_edd_file']) ||
            !isset($_FILES['s3cs_edd_file']['name']) ||
            !isset($_FILES['s3cs_edd_file']['tmp_name']) ||
            !isset($_FILES['s3cs_edd_file']['size']) ||
            empty($_FILES['s3cs_edd_file']['name'])
        ) {
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

        // Validate Content-Type (MIME type)
        if (!$this->validateFileContentType($_FILES['s3cs_edd_file'])) {
            wp_die(esc_html__('File content type validation failed. The file may be corrupted or have an incorrect extension.', 'storage-for-edd-via-s3-compatible'), esc_html__('Error', 'storage-for-edd-via-s3-compatible'), array('back_link' => true));
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
