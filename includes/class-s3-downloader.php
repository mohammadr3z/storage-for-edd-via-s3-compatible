<?php
if (!defined('ABSPATH')) {
    exit;
}

class S3CS_EDD_S3_Downloader {
    private $client;
    private $config;
    
    public function __construct() {
        $this->config = new S3CS_EDD_S3_Config();
        $this->client = new S3CS_EDD_S3_Client();
    }
    
    /**
     * Generate a presigned S3 URL for download.
     */
    public function generateUrl($file, $downloadFiles, $fileKey) {
        if (empty($downloadFiles[$fileKey])) {
            return $file;
        }
        
        $fileData = $downloadFiles[$fileKey];
        $filename = $fileData['file'];
        
        $urlPrefix = $this->config->getUrlPrefix();
        if (strpos($filename, $urlPrefix) !== 0) {
            return $file;
        }
        
        $path = substr($filename, strlen($urlPrefix));
        $client = $this->client->getClient();
        
        if (!$client) {
            return $file;
        }
        
        $expiry_minutes = $this->config->getExpiryMinutes();
        if ($expiry_minutes < 1) $expiry_minutes = 5;
        
        try {
            return $this->createPresignedUrl($path, $expiry_minutes);
        } catch (Exception $e) {
            $this->config->debug('Error generating download URL: ' . $e->getMessage());
            return $file;
        }
    }
    
    /**
     * Create presigned URL for S3 object.
     * @param string $path
     * @param int $expiry_minutes
     * @return string
     */
    private function createPresignedUrl($path, $expiry_minutes) {
        $endpoint = rtrim($this->config->getEndpoint(), '/');
        $bucket = $this->config->getBucket();
        $accessKey = $this->config->getAccessKey();
        $secretKey = $this->config->getSecretKey();
        $region = $this->config->getRegion();
        
        // Clean the path
        $object_key = ltrim($path, '/');
        $expires = $expiry_minutes * 60;
        
        // Time values
        $datetime = new DateTime('UTC');
        $date_timestamp = $datetime->format('Ymd\\THis\\Z');
        $date_ymd = $datetime->format('Ymd');
        
        // Host derivation
        $endpoint_parts = wp_parse_url($endpoint);
        $host = $endpoint_parts['host'];
        
        // Standard path-style S3 URL
        $uri = '/' . $bucket . '/' . $object_key;
        $host_header = $host;
        
        // Request parameters
        $query_params = [
            'X-Amz-Algorithm' => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential' => $accessKey . '/' . $date_ymd . '/' . $region . '/s3/aws4_request',
            'X-Amz-Date' => $date_timestamp,
            'X-Amz-Expires' => $expires,
            'response-content-disposition' => 'attachment; filename="' . basename($object_key) . '"',
            'X-Amz-SignedHeaders' => 'host'
        ];
        
        // Canonical request
        $canonical_uri = rawurlencode($uri);
        $canonical_uri = str_replace('%2F', '/', $canonical_uri);
        $canonical_uri = str_replace('%7E', '~', $canonical_uri);
        
        // Build canonical query string
        ksort($query_params);
        $canonical_query_string = http_build_query($query_params);
        $canonical_query_string = str_replace('+', '%20', $canonical_query_string);
        
        $canonical_headers = 'host:' . $host_header . "\n";
        $signed_headers = 'host';
        
        $payload_hash = 'UNSIGNED-PAYLOAD';
        
        $canonical_request = "GET\n" .
                            $canonical_uri . "\n" .
                            $canonical_query_string . "\n" .
                            $canonical_headers . "\n" .
                            $signed_headers . "\n" .
                            $payload_hash;
        
        // String to sign
        $algorithm = 'AWS4-HMAC-SHA256';
        $credential_scope = $date_ymd . '/' . $region . '/s3/aws4_request';
        $string_to_sign = $algorithm . "\n" .
                         $date_timestamp . "\n" .
                         $credential_scope . "\n" .
                         hash('sha256', $canonical_request);
        
        // Calculate signature
        $k_date = hash_hmac('sha256', $date_ymd, 'AWS4' . $secretKey, true);
        $k_region = hash_hmac('sha256', $region, $k_date, true);
        $k_service = hash_hmac('sha256', 's3', $k_region, true);
        $k_signing = hash_hmac('sha256', 'aws4_request', $k_service, true);
        $signature = hash_hmac('sha256', $string_to_sign, $k_signing);
        
        // Add signature to query parameters
        $query_params['X-Amz-Signature'] = $signature;
        
        // Build the final URL
        $query_string = http_build_query($query_params);
        $query_string = str_replace('+', '%20', $query_string);
        
        $scheme = isset($endpoint_parts['scheme']) ? $endpoint_parts['scheme'] . '://' : 'https://';
        
        return $scheme . $host . $uri . '?' . $query_string;
    }
}