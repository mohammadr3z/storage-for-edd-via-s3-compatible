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
        return intval(edd_get_option(self::KEY_EXPIRY_MINUTES, 5));
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
        if (defined('WP_DEBUG') && WP_DEBUG) {
            if (is_array($log) || is_object($log)) {
                error_log(print_r($log, true));
            } else {
                error_log($log);
            }
        }
    }
}