<?php
if (!defined('ABSPATH')) {
    exit;
}

class S3CS_EDD_S3_Client {
    private $httpClient = null;
    private $config;
    
    public function __construct() {
        $this->config = new S3CS_EDD_S3_Config();
    }
    
    /**
     * Get HTTP client instance.
     * @return GuzzleHttp\Client|null
     */
    public function getClient() {
        if ($this->httpClient !== null) {
            return $this->httpClient;
        }

        if (!$this->config->isConfiguredForBucketList()) {
            return null;
        }

        try {
            // Advanced settings for full compatibility
            $clientOptions = [
                'base_uri' => $this->config->getEndpoint(),
                'timeout'  => 30,
                'connect_timeout' => 15,
                // Enable SSL verification for security
                'verify' => true, 
                'http_errors' => false,
                'allow_redirects' => true,
                'curl' => [
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_MAXREDIRS => 3,
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_SSL_VERIFYHOST => 2,
                    CURLOPT_USERAGENT => 'storage-for-edd-via-s3-compatible/1.0',
                    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4, // IPv4 only
                    CURLOPT_TCP_NODELAY => true,
                    CURLOPT_FRESH_CONNECT => false,
                    CURLOPT_FORBID_REUSE => false
                ]
            ];
            
            $this->httpClient = new \GuzzleHttp\Client($clientOptions);
            
            return $this->httpClient;
        } catch (Exception $e) {
            $this->config->debug('Error creating HTTP client: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get list of available S3 buckets.
     * @return array
     */
    public function getBucketsList() {
        $client = $this->getClient();
        if (!$client) {
            return array();
        }
        
        if (!$this->config->isConfiguredForBucketList()) {
            return array();
        }
        
        $endpoint = $this->config->getEndpoint();
        
        // Try different methods
        $response = $this->tryMultipleAuthMethods($client, $endpoint, 'GET', '/', '');
        
        if (!$response) {
            $this->config->debug('All authentication methods failed');
            return array();
        }
        
        try {
            $statusCode = $response->getStatusCode();
            if ($statusCode !== 200) {
                $this->config->debug('Non-200 response: ' . $statusCode);
                return array();
            }
            
            $responseBody = $response->getBody()->getContents();
            
            // Attempt to parse XML with error handling
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($responseBody);
            if ($xml === false) {
                $errors = libxml_get_errors();
                libxml_clear_errors();
                $errorMsg = 'XML parsing error: ';
                foreach ($errors as $error) {
                    $errorMsg .= $error->message . ' ';
                }
                $this->config->debug($errorMsg);
                $this->config->debug('Response status: ' . $response->getStatusCode() . ' - XML parsing failed');
                
                // Attempt to parse JSON (some services return JSON)
                $json = json_decode($responseBody, true);
                if ($json && isset($json['buckets'])) {
                    libxml_use_internal_errors(false);
                    return array_column($json['buckets'], 'name');
                }
                libxml_use_internal_errors(false);
                return array();
            }
            libxml_use_internal_errors(false);
            
            $buckets = [];
            
            // Support for different XML formats
            if (isset($xml->Buckets->Bucket)) {
                foreach ($xml->Buckets->Bucket as $bucket) {
                    $buckets[] = (string)$bucket->Name;
                }
            } elseif (isset($xml->bucket)) {
                // Different format for some services
                foreach ($xml->bucket as $bucket) {
                    $buckets[] = (string)$bucket->name;
                }
            } elseif (isset($xml->ListAllMyBucketsResult->Buckets->Bucket)) {
                // Full AWS format
                foreach ($xml->ListAllMyBucketsResult->Buckets->Bucket as $bucket) {
                    $buckets[] = (string)$bucket->Name;
                }
            }
            
            $this->config->debug('Found ' . count($buckets) . ' buckets');
            return $buckets;
            
        } catch (Exception $e) {
            $this->config->debug('Error processing response: ' . $e->getMessage());
            return array();
        }
    }
    
    /**
     * List files in the S3 bucket for the given prefix.
     * @param string $prefix
     * @return array
     */
    public function listFiles($prefix = '') {
        $client = $this->getClient();
        $bucket = $this->config->getBucket();
        $files = array();
        
        if (!$client || !$bucket) {
            return $files;
        }
        
        try {
            $endpoint = $this->config->getEndpoint();
            $accessKey = $this->config->getAccessKey();
            $secretKey = $this->config->getSecretKey();
            $region = $this->config->getRegion();
            
            // Ensure prefix format is correct
            $prefix = ltrim($prefix, '/');
            
            // Create authorization header
            $date = gmdate('Ymd\\THis\\Z');
            $shortDate = gmdate('Ymd');
            $service = 's3';
            
            // Create canonical request
            $method = 'GET';
            $canonicalUri = "/$bucket";
            $canonicalQueryString = 'list-type=2';
            if (!empty($prefix)) {
                $canonicalQueryString .= '&prefix=' . urlencode($prefix);
            }
            
            $canonicalHeaders = "host:" . wp_parse_url($endpoint, PHP_URL_HOST) . "\nx-amz-content-sha256:e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855\nx-amz-date:$date\n";
            $signedHeaders = 'host;x-amz-content-sha256;x-amz-date';
            $payloadHash = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';
            
            $canonicalRequest = "$method\n$canonicalUri\n$canonicalQueryString\n$canonicalHeaders\n$signedHeaders\n$payloadHash";
            
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
            
            // Create correct URL for request
            $requestUrl = rtrim($endpoint, '/') . "/$bucket?list-type=2";
            if (!empty($prefix)) {
                $requestUrl .= '&prefix=' . urlencode($prefix);
            }
            
            // List objects
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
                return [];
            }
            libxml_use_internal_errors(false);
            
            if (isset($xml->Contents)) {
                foreach ($xml->Contents as $object) {
                    $files[] = (string)$object->Key;
                }
            }
        } catch (Exception $e) {
            $this->config->debug('Error listing S3 files: ' . $e->getMessage());
        }
        return $files;
    }
    
    /**
     * Try different authentication methods for compatibility
     */
    private function tryMultipleAuthMethods($client, $endpoint, $method = 'GET', $uri = '/', $queryString = '') {
        $accessKey = $this->config->getAccessKey();
        $secretKey = $this->config->getSecretKey();
        $region = $this->config->getRegion();
        
        // Method 1: AWS Signature V4 (Standard)
        try {
            return $this->makeRequestWithV4Auth($client, $endpoint, $method, $uri, $queryString);
        } catch (Exception $e) {
            $this->config->debug('V4 Auth failed: ' . $e->getMessage());
        }
        
        // Method 2: Simple Authorization Header (For simpler services)
        try {
            return $this->makeRequestWithSimpleAuth($client, $endpoint, $method, $uri, $queryString);
        } catch (Exception $e) {
            $this->config->debug('Simple Auth failed: ' . $e->getMessage());
        }
        
        // No fallback to unauthenticated requests for security
        return false;
    }
    
    private function makeRequestWithV4Auth($client, $endpoint, $method, $uri, $queryString) {
        $accessKey = $this->config->getAccessKey();
        $secretKey = $this->config->getSecretKey();
        $region = $this->config->getRegion();
        
        $date = gmdate('Ymd\\THis\\Z');
        $shortDate = gmdate('Ymd');
        $service = 's3';
        
        $host = wp_parse_url($endpoint, PHP_URL_HOST);
        $canonicalHeaders = "host:$host\nx-amz-content-sha256:e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855\nx-amz-date:$date\n";
        $signedHeaders = 'host;x-amz-content-sha256;x-amz-date';
        $payloadHash = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';
        
        $canonicalRequest = "$method\n$uri\n$queryString\n$canonicalHeaders\n$signedHeaders\n$payloadHash";
        
        $algorithm = 'AWS4-HMAC-SHA256';
        $credentialScope = "$shortDate/$region/$service/aws4_request";
        $stringToSign = "$algorithm\n$date\n$credentialScope\n" . hash('sha256', $canonicalRequest);
        
        $kSecret = 'AWS4' . $secretKey;
        $kDate = hash_hmac('sha256', $shortDate, $kSecret, true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);
        
        $authorization = "$algorithm Credential=$accessKey/$credentialScope, SignedHeaders=$signedHeaders, Signature=$signature";
        
        $requestUrl = rtrim($endpoint, '/') . $uri;
        if (!empty($queryString)) {
            $requestUrl .= '?' . $queryString;
        }
        
        return $client->request($method, $requestUrl, [
            'headers' => [
                'Host' => $host,
                'X-Amz-Content-SHA256' => $payloadHash,
                'X-Amz-Date' => $date,
                'Authorization' => $authorization
            ]
        ]);
    }
    
    private function makeRequestWithSimpleAuth($client, $endpoint, $method, $uri, $queryString) {
        $accessKey = $this->config->getAccessKey();
        $secretKey = $this->config->getSecretKey();
        
        $requestUrl = rtrim($endpoint, '/') . $uri;
        if (!empty($queryString)) {
            $requestUrl .= '?' . $queryString;
        }
        
        return $client->request($method, $requestUrl, [
            'headers' => [
                'Authorization' => 'AWS ' . $accessKey . ':' . base64_encode(hash_hmac('sha1', $requestUrl, $secretKey, true))
            ]
        ]);
    }
    

}