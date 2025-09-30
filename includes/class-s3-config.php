<?php
if (!defined('ABSPATH')) {
    exit;
}

class S3CS_EDD_S3_Config {
    const KEY_ACCESS_KEY = 's3cs_edd_access_key';
const KEY_SECRET_KEY = 's3cs_edd_secret_key';
const KEY_BUCKET = 's3cs_edd_bucket';
const KEY_ENDPOINT = 's3cs_edd_endpoint';
const KEY_REGION = 's3cs_edd_region';
const KEY_EXPIRY_MINUTES = 's3cs_edd_expiry_minutes';
const URL_PREFIX = 'edd-s3cs://';
    
    /**
     * Get the URL prefix for S3 file URLs
     * This method allows developers to customize the URL prefix using the 's3cs_edd_url_prefix' filter
     * 
     * @return string The URL prefix (default: 'edd-s3cs://')
     */
    public function getUrlPrefix() {
        /**
         * Filter the URL prefix for S3 file URLs
         * 
         * @param string $prefix The default URL prefix
         * @return string The filtered URL prefix
         */
        return apply_filters('s3cs_edd_url_prefix', self::URL_PREFIX);
    }
    
    public function getAccessKey() {
        return edd_get_option(self::KEY_ACCESS_KEY);
    }
    
    public function getSecretKey() {
        return edd_get_option(self::KEY_SECRET_KEY);
    }
    
    public function getBucket() {
        return edd_get_option(self::KEY_BUCKET);
    }
    
    public function getEndpoint() {
        $endpoint = edd_get_option(self::KEY_ENDPOINT);
        
        // Ensure endpoint starts with https:// to prevent XML parsing errors
        if (!empty($endpoint) && !preg_match('/^https?:\/\//i', $endpoint)) {
            $endpoint = 'https://' . $endpoint;
        }
        
        return $endpoint;
    }
    
    public function getRegion() {
        return edd_get_option(self::KEY_REGION, 'us-east-1');
    }
    
    public function getExpiryMinutes() {
        return intval(edd_get_option(self::KEY_EXPIRY_MINUTES, 3));
    }
    
    public function isConfigured() {
        return !empty($this->getAccessKey()) && 
               !empty($this->getSecretKey()) && 
               !empty($this->getEndpoint()) &&
               !empty($this->getBucket());
    }
    
    public function isConfiguredForBucketList() {
        return !empty($this->getAccessKey()) && 
               !empty($this->getSecretKey()) && 
               !empty($this->getEndpoint());
    }
    
    public function debug($log) {
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            if (is_array($log) || is_object($log)) {
                // Use WordPress logging with proper sanitization
                $message = wp_json_encode($log, JSON_UNESCAPED_UNICODE);
                if ($message !== false) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                    error_log('[S3CS_EDD] ' . $message);
                }
            } else {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('[S3CS_EDD] ' . sanitize_text_field($log));
            }
        }
    }
}