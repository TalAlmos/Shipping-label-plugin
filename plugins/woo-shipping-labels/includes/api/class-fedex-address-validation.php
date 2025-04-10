<?php
/**
 * FedEx Address Validation API Integration
 */

if (!defined('WPINC')) {
    die;
}

// Include the debug helper class
require_once WSL_PLUGIN_DIR . 'includes/class-debug.php';

class WSL_FedEx_Address_Validation {
    private $auth;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->auth = new WSL_FedEx_Auth();
    }
    
    /**
     * Validate address using the FedEx API
     * 
     * @param array $address Address data
     * @return array Response with validation results
     */
    public function validate_address($address) {
        // Get authentication token
        $token = $this->auth->get_token();
        if (empty($token)) {
            return array(
                'success' => false,
                'error' => 'Failed to obtain authentication token',
            );
        }
        
        // Get API endpoint
        $endpoint = $this->auth->get_endpoint('address_validation');
        if (empty($endpoint)) {
            return array(
                'success' => false,
                'error' => 'Invalid API endpoint configuration',
            );
        }
        
        // Prepare request payload
        $payload = $this->prepare_payload($address);
        
        // Debug: Log the request payload
        WSL_Debug::log_api_data('address_validation', $payload, 'request');
        
        // Make API request
        $response = wp_remote_post(
            $endpoint,
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'X-locale' => 'en_US',
                    'Content-Type' => 'application/json',
                ),
                'body' => json_encode($payload),
                'timeout' => 30,
            )
        );
        
        // Debug: Log the API response
        WSL_Debug::log_api_data('address_validation', array(
            'raw_response' => $response,
            'body' => is_wp_error($response) ? $response->get_error_message() : json_decode(wp_remote_retrieve_body($response), true)
        ), 'response');
        
        // Process the response
        return $this->process_response($response);
    }
    
    /**
     * Prepare payload for address validation request
     * 
     * @param array $address Address data
     * @return array Formatted payload
     */
    private function prepare_payload($address) {
        // Extract address components with some checks for missing values
        $street_lines = array();
        if (!empty($address['address_1'])) {
            $street_lines[] = $address['address_1'];
        }
        if (!empty($address['address_2'])) {
            $street_lines[] = $address['address_2'];
        }
        
        return array(
            'inEffectAsOfTimestamp' => date('Y-m-d'),
            'validateAddressControlParameters' => array(
                'includeResolutionTokens' => true,
            ),
            'addressesToValidate' => array(
                array(
                    'address' => array(
                        'streetLines' => $street_lines,
                        'city' => !empty($address['city']) ? $address['city'] : '',
                        'stateOrProvinceCode' => !empty($address['state']) ? $address['state'] : '',
                        'postalCode' => !empty($address['postcode']) ? $address['postcode'] : '',
                        'countryCode' => !empty($address['country']) ? $address['country'] : 'US',
                    ),
                    'clientReferenceId' => 'Order_' . (!empty($address['order_id']) ? $address['order_id'] : time()),
                ),
            ),
        );
    }
    
    /**
     * Process API response
     * 
     * @param mixed $response Response from wp_remote_post
     * @return array Processed result
     */
    private function process_response($response) {
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => $response->get_error_message(),
            );
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        // Check if we got a successful status code
        if ($status_code !== 200 || empty($body)) {
            return array(
                'success' => false,
                'error' => 'API error: ' . ($status_code ?? 'Unknown status'),
                'response' => $body,
            );
        }
        
        // If we have a successful response, extract alerts if any
        $alerts = array();
        if (!empty($body['output']['alerts'])) {
            foreach ($body['output']['alerts'] as $alert) {
                $alerts[] = array(
                    'code' => $alert['code'] ?? '',
                    'message' => $alert['message'] ?? '',
                    'type' => $alert['alertType'] ?? '',
                );
            }
        }
        
        // Format the validated addresses
        $validated_addresses = array();
        if (!empty($body['output']['resolvedAddresses'])) {
            foreach ($body['output']['resolvedAddresses'] as $resolved) {
                $validated_addresses[] = $this->format_resolved_address($resolved);
            }
        }
        
        return array(
            'success' => true,
            'transaction_id' => $body['transactionId'] ?? '',
            'addresses' => $validated_addresses,
            'alerts' => $alerts,
            'raw_response' => $body,
        );
    }
    
    /**
     * Format the resolved address from the API response
     * 
     * @param array $resolved Resolved address data from API
     * @return array Formatted address
     */
    private function format_resolved_address($resolved) {
        $formatted = array();
        
        // Default status to invalid unless we find a valid address
        $formatted['status'] = 'invalid';
        
        // Check if we have a valid address
        if (!empty($resolved) && isset($resolved['attributes']) && 
            in_array('deliverable', $resolved['attributes'])) {
            $formatted['status'] = 'valid';
        }
        
        if (!empty($resolved)) {
            $formatted['address'] = array();
            
            if (!empty($resolved['streetLinesToken']) && is_array($resolved['streetLinesToken'])) {
                $formatted['address']['street_lines'] = array();
                foreach ($resolved['streetLinesToken'] as $line) {
                    $formatted['address']['street_lines'][] = $line;
                }
            } elseif (!empty($resolved['streetLines']) && is_array($resolved['streetLines'])) {
                $formatted['address']['street_lines'] = $resolved['streetLines'];
            }
            
            if (!empty($resolved['city'])) {
                $formatted['address']['city'] = $resolved['city'];
            }
            
            if (!empty($resolved['stateOrProvinceCode'])) {
                $formatted['address']['state'] = $resolved['stateOrProvinceCode'];
            }
            
            if (!empty($resolved['postalCode'])) {
                $formatted['address']['postal_code'] = $resolved['postalCode'];
            }
            
            if (!empty($resolved['countryCode'])) {
                $formatted['address']['country'] = $resolved['countryCode'];
            }
            
            // Add additional postal code details if available
            if (!empty($resolved['parsedPostalCode'])) {
                $formatted['address']['postal_details'] = $resolved['parsedPostalCode'];
            }
            
            // Add classification if available
            if (!empty($resolved['classification'])) {
                $formatted['address']['classification'] = $resolved['classification'];
            }
        }
        
        return $formatted;
    }
    
    /**
     * Test validation with sample data
     * 
     * @return array API response
     */
    public function test_with_sample() {
        // Sample address data from the FedEx example
        $sample_payload = array(
            'inEffectAsOfTimestamp' => '2019-09-06',
            'validateAddressControlParameters' => array(
                'includeResolutionTokens' => true
            ),
            'addressesToValidate' => array(
                array(
                    'address' => array(
                        'streetLines' => array(
                            '7372 PARKRIDGE BLVD',
                            'APT 286',
                            '2903 sprank'
                        ),
                        'city' => 'IRVING',
                        'stateOrProvinceCode' => 'TX',
                        'postalCode' => '75063-8659',
                        'countryCode' => 'US'
                    ),
                    'clientReferenceId' => 'None'
                )
            )
        );
        
        // Get authentication token
        $token = $this->auth->get_token();
        if (empty($token)) {
            return array(
                'success' => false,
                'error' => 'Failed to obtain authentication token',
            );
        }
        
        // Get API endpoint
        $endpoint = $this->auth->get_endpoint('address_validation');
        if (empty($endpoint)) {
            return array(
                'success' => false,
                'error' => 'Invalid API endpoint configuration',
            );
        }
        
        // Debug: Log the sample request
        WSL_Debug::log_api_data('address_validation_sample', $sample_payload, 'request');
        
        // Make API request with sample data
        $response = wp_remote_post(
            $endpoint,
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'X-locale' => 'en_US',
                    'Content-Type' => 'application/json',
                ),
                'body' => json_encode($sample_payload),
                'timeout' => 30,
            )
        );
        
        // Debug: Log the sample response
        WSL_Debug::log_api_data('address_validation_sample', array(
            'raw_response' => $response,
            'body' => is_wp_error($response) ? $response->get_error_message() : json_decode(wp_remote_retrieve_body($response), true)
        ), 'response');
        
        // Process the response
        return $this->process_response($response);
    }
} 