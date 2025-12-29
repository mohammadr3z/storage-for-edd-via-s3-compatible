<?php
if (!defined('ABSPATH')) {
    exit;
}

class S3CS_EDD_S3_Config
{
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
    public function getUrlPrefix()
    {
        /**
         * Filter the URL prefix for S3 file URLs
         * 
         * @param string $prefix The default URL prefix
         * @return string The filtered URL prefix
         */
        return apply_filters('s3cs_edd_url_prefix', self::URL_PREFIX);
    }

    public function getAccessKey()
    {
        return edd_get_option(self::KEY_ACCESS_KEY);
    }

    public function getSecretKey()
    {
        return edd_get_option(self::KEY_SECRET_KEY);
    }

    public function getBucket()
    {
        return edd_get_option(self::KEY_BUCKET);
    }

    public function getEndpoint()
    {
        $endpoint = edd_get_option(self::KEY_ENDPOINT);

        if (empty($endpoint)) {
            return '';
        }

        // Ensure endpoint starts with https:// or http://
        if (!preg_match('/^https?:\/\//i', $endpoint)) {
            $endpoint = 'https://' . $endpoint;
        }

        // Parse and validate URL
        $parsed = wp_parse_url($endpoint);

        // Validate URL structure
        if (!$parsed || !isset($parsed['host'])) {
            $this->debug('Invalid endpoint URL: missing host');
            return '';
        }

        // Security: Block private IP addresses and localhost to prevent SSRF
        $host = $parsed['host'];

        // Check if host is an IP address
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            // Block private and reserved IP ranges
            if (!filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                $this->debug('Security: Blocked private/reserved IP address in endpoint');
                return '';
            }
        }

        // Block localhost and local domains
        $blocked_hosts = array('localhost', '127.0.0.1', '0.0.0.0', '::1');
        if (in_array(strtolower($host), $blocked_hosts, true)) {
            $this->debug('Security: Blocked localhost in endpoint');
            return '';
        }

        // Warn if using HTTP instead of HTTPS
        if (isset($parsed['scheme']) && $parsed['scheme'] === 'http') {
            $this->debug('Warning: Using non-HTTPS endpoint - consider using HTTPS for better security');
        }

        // Rebuild clean URL
        $clean_endpoint = $parsed['scheme'] . '://' . $parsed['host'];

        // Add port if specified and not default
        if (isset($parsed['port'])) {
            $default_port = ($parsed['scheme'] === 'https') ? 443 : 80;
            if ($parsed['port'] != $default_port) {
                $clean_endpoint .= ':' . $parsed['port'];
            }
        }

        // Add path if exists
        if (isset($parsed['path']) && $parsed['path'] !== '/') {
            $clean_endpoint .= rtrim($parsed['path'], '/');
        }

        return $clean_endpoint;
    }

    public function getRegion()
    {
        return edd_get_option(self::KEY_REGION, 'us-east-1');
    }

    public function getExpiryMinutes()
    {
        $minutes = intval(edd_get_option(self::KEY_EXPIRY_MINUTES, 3));

        return max(1, min(60, $minutes));
    }

    public function isConfigured()
    {
        return !empty($this->getAccessKey()) &&
            !empty($this->getSecretKey()) &&
            !empty($this->getEndpoint()) &&
            !empty($this->getBucket());
    }

    public function isConfiguredForBucketList()
    {
        return !empty($this->getAccessKey()) &&
            !empty($this->getSecretKey()) &&
            !empty($this->getEndpoint());
    }

    public function debug($log)
    {
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            if (is_array($log) || is_object($log)) {
                // Use WordPress logging with proper sanitization
                $message = wp_json_encode($log, JSON_UNESCAPED_UNICODE);
                if ($message !== false) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                    error_log('[S3CS] ' . $message);
                }
            } else {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('[S3CS] ' . sanitize_text_field($log));
            }
        }
    }
}
