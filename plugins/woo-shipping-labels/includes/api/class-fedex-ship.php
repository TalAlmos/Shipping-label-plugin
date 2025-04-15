<?php
/**
 * FedEx Ship API Integration
 */

if (!defined('WPINC')) {
    die;
}

// Include the debug helper class
require_once WSL_PLUGIN_DIR . 'includes/class-debug.php';

class WSL_FedEx_Ship {
    private $auth;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->auth = new WSL_FedEx_Auth();
    }
    
    /**
     * Create a shipping label
     * 
     * @param array $shipment_data Shipment details
     * @return array Response with label data or error information
     */
    public function create_shipment($shipment_data) {
        // Get authentication token
        $token = $this->auth->get_token();
        if (empty($token)) {
            return array(
                'success' => false,
                'error' => 'Failed to obtain authentication token',
            );
        }
        
        // Get API endpoint
        $endpoint = $this->auth->get_endpoint('ship');
        if (empty($endpoint)) {
            return array(
                'success' => false,
                'error' => 'Invalid API endpoint configuration',
            );
        }
        
        // Validate shipment data
        $validation_result = $this->validate_shipment_data($shipment_data);
        if (!$validation_result['valid']) {
            return array(
                'success' => false,
                'error' => 'Invalid shipment data: ' . $validation_result['message'],
            );
        }
        
        // Prepare request payload
        $payload = $this->prepare_payload($shipment_data);
        
        // Debug: Log the request payload
        WSL_Debug::log_api_data('fedex_shipment', $payload, 'request');
        
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
        WSL_Debug::log_api_data('fedex_shipment', array(
            'raw_response' => $response,
            'body' => is_wp_error($response) ? $response->get_error_message() : json_decode(wp_remote_retrieve_body($response), true)
        ), 'response');
        
        // Process the response
        return $this->process_response($response);
    }
    
    /**
     * Validate shipment data
     * 
     * @param array $data Shipment data
     * @return array Validation result
     */
    private function validate_shipment_data($data) {
        // Required fields
        $required_fields = array(
            'from_address' => array('name', 'address_1', 'city', 'state', 'postcode', 'country'),
            'to_address' => array('name', 'address_1', 'city', 'state', 'postcode', 'country'),
            'package' => array('weight', 'weight_unit'),
            'service_type' => null,
        );
        
        foreach ($required_fields as $section => $fields) {
            if (empty($data[$section])) {
                return array(
                    'valid' => false,
                    'message' => "Missing {$section} information"
                );
            }
            
            if ($fields) {
                foreach ($fields as $field) {
                    if (empty($data[$section][$field])) {
                        return array(
                            'valid' => false,
                            'message' => "Missing {$section}.{$field} value"
                        );
                    }
                }
            }
        }
        
        // If this is an international shipment, validate customs details
        if ($this->is_international_shipment($data['from_address']['country'], $data['to_address']['country'])) {
            if (empty($data['customs'])) {
                return array(
                    'valid' => false,
                    'message' => 'International shipments require customs information'
                );
            }
            
            // Validate customs items
            if (empty($data['customs']['items']) || !is_array($data['customs']['items']) || count($data['customs']['items']) < 1) {
                return array(
                    'valid' => false,
                    'message' => 'International shipments require at least one customs item'
                );
            }
            
            foreach ($data['customs']['items'] as $index => $item) {
                $required_item_fields = array('description', 'quantity', 'value', 'weight', 'country_of_origin', 'harmonized_code');
                foreach ($required_item_fields as $field) {
                    if (empty($item[$field])) {
                        return array(
                            'valid' => false,
                            'message' => "Missing customs item #{$index} {$field}"
                        );
                    }
                }
            }
        }
        
        return array('valid' => true);
    }
    
    /**
     * Check if shipment is international
     * 
     * @param string $from_country From country code
     * @param string $to_country To country code
     * @return bool True if international
     */
    private function is_international_shipment($from_country, $to_country) {
        return $from_country !== $to_country;
    }
    
    /**
     * Prepare the API request payload
     * 
     * @param array $shipment_data Our simplified shipment data
     * @return array Complete FedEx API request payload
     */
    private function prepare_payload($shipment_data) {
        // Get FedEx config
        $config = include(WSL_PLUGIN_DIR . 'includes/api/fedex-config.php');
        $account_number = $config['credentials']['account_number'];
        
        // Extract shipping addresses
        $from_address = $shipment_data['from_address'];
        $to_address = $shipment_data['to_address'];
        $package = $shipment_data['package'];
        
        // Format street lines
        $from_street_lines = array($from_address['address_1']);
        if (!empty($from_address['address_2'])) {
            $from_street_lines[] = $from_address['address_2'];
        }
        
        $to_street_lines = array($to_address['address_1']);
        if (!empty($to_address['address_2'])) {
            $to_street_lines[] = $to_address['address_2'];
        }
        
        // Determine if this is a residential delivery
        $residential_delivery = isset($to_address['residential']) ? 
                                $to_address['residential'] : true;
        
        // Build the payload
        $payload = array(
            'mergeLabelDocOption' => 'LABELS_ONLY',
            'requestedShipment' => array(
                'shipDatestamp' => date('Y-m-d'),
                'totalDeclaredValue' => array(
                    'amount' => isset($shipment_data['declared_value']) ? 
                              $shipment_data['declared_value'] : $package['weight'] * 10,
                    'currency' => 'USD'
                ),
                'shipper' => array(
                    'address' => array(
                        'streetLines' => $from_street_lines,
                        'city' => $from_address['city'],
                        'stateOrProvinceCode' => $from_address['state'],
                        'postalCode' => $from_address['postcode'],
                        'countryCode' => $from_address['country'],
                        'residential' => false
                    ),
                    'contact' => array(
                        'personName' => $from_address['name'],
                        'emailAddress' => isset($from_address['email']) ? $from_address['email'] : '',
                        'phoneNumber' => isset($from_address['phone']) ? $from_address['phone'] : '',
                        'companyName' => isset($from_address['company']) ? $from_address['company'] : ''
                    )
                ),
                'recipients' => array(
                    array(
                        'address' => array(
                            'streetLines' => $to_street_lines,
                            'city' => $to_address['city'],
                            'stateOrProvinceCode' => $to_address['state'],
                            'postalCode' => $to_address['postcode'], 
                            'countryCode' => $to_address['country'],
                            'residential' => $residential_delivery
                        ),
                        'contact' => array(
                            'personName' => $to_address['name'],
                            'emailAddress' => isset($to_address['email']) ? $to_address['email'] : '',
                            'phoneNumber' => isset($to_address['phone']) ? $to_address['phone'] : '',
                            'companyName' => isset($to_address['company']) ? $to_address['company'] : ''
                        )
                    )
                ),
                'pickupType' => 'USE_SCHEDULED_PICKUP',
                'serviceType' => $shipment_data['service_type'],
                'packagingType' => isset($shipment_data['package_type']) ? 
                                  $shipment_data['package_type'] : 'YOUR_PACKAGING',
                'totalWeight' => array(
                    'units' => $package['weight_unit'],
                    'value' => $package['weight']
                ),
                'shippingChargesPayment' => array(
                    'paymentType' => 'SENDER',
                    'payor' => array(
                        'responsibleParty' => array(
                            'accountNumber' => array(
                                'value' => $account_number
                            )
                        )
                    )
                ),
                'labelSpecification' => array(
                    'labelFormatType' => 'COMMON2D',
                    'imageType' => 'PDF',
                    'labelStockType' => 'PAPER_85X11_TOP_HALF_LABEL'
                ),
                'requestedPackageLineItems' => array(
                    array(
                        'weight' => array(
                            'units' => $package['weight_unit'],
                            'value' => $package['weight']
                        )
                    )
                )
            ),
            'labelResponseOptions' => 'URL_AND_DOCUMENT',
            'accountNumber' => array(
                'value' => $account_number
            ),
            'shipAction' => 'CONFIRM'
        );
        
        // Add dimensions if provided
        if (!empty($package['length']) && !empty($package['width']) && !empty($package['height'])) {
            $payload['requestedShipment']['requestedPackageLineItems'][0]['dimensions'] = array(
                'length' => $package['length'],
                'width' => $package['width'],
                'height' => $package['height'],
                'units' => $package['dimension_unit']
            );
        }
        
        // Add customs details for international shipments
        if (isset($shipment_data['customs']) && $from_address['country'] !== $to_address['country']) {
            $customs = $shipment_data['customs'];
            
            $payload['requestedShipment']['customsClearanceDetail'] = array(
                'dutiesPayment' => array(
                    'paymentType' => 'SENDER',
                    'payor' => array(
                        'responsibleParty' => array(
                            'accountNumber' => array(
                                'value' => $account_number
                            )
                        )
                    )
                ),
                'commodities' => array(
                    array(
                        'description' => $customs['items'][0]['description'],
                        'countryOfManufacture' => $customs['items'][0]['country_of_origin'],
                        'quantity' => $customs['items'][0]['quantity'],
                        'quantityUnits' => 'EA',
                        'weight' => array(
                            'units' => $package['weight_unit'],
                            'value' => $customs['items'][0]['weight']
                        ),
                        'customsValue' => array(
                            'currency' => $customs['currency'],
                            'amount' => $customs['total_value']
                        )
                    )
                )
            );
        }
        
        return $payload;
    }
    
    /**
     * Format address for FedEx API
     * 
     * @param array $address Address data
     * @return array Formatted address
     */
    private function format_address($address) {
        $street_lines = array($address['address_1']);
        if (!empty($address['address_2'])) {
            $street_lines[] = $address['address_2'];
        }
        
        $formatted = array(
            'contact' => array(
                'personName' => $address['name'],
                'phoneNumber' => $address['phone'] ?? '0000000000',
                'emailAddress' => $address['email'] ?? ''
            ),
            'address' => array(
                'streetLines' => $street_lines,
                'city' => $address['city'],
                'stateOrProvinceCode' => $address['state'],
                'postalCode' => $address['postcode'],
                'countryCode' => $address['country'],
                'residential' => $address['residential'] ?? false
            )
        );
        
        if (!empty($address['company'])) {
            $formatted['contact']['companyName'] = $address['company'];
        }
        
        return $formatted;
    }
    
    /**
     * Process the API response
     * 
     * @param array|WP_Error $response API response or error
     * @return array Formatted response with success/error status
     */
    private function process_response($response) {
        // Log the response
        WSL_Debug::log_api_data('fedex_shipment', $response, 'response');
        
        // Check for WP_Error
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => $response->get_error_message()
            );
        }
        
        // Check response code
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code < 200 || $response_code >= 300) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            return array(
                'success' => false,
                'error' => 'API error: ' . $response_code,
                'response' => $body
            );
        }
        
        // Parse response body
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        // Extract label URL and tracking number
        $label_url = '';
        $tracking_number = '';
        
        // New response format handling
        if (isset($body['output']['transactionShipments'][0]['pieceResponses'][0]['packageDocuments'][0]['url'])) {
            $label_url = $body['output']['transactionShipments'][0]['pieceResponses'][0]['packageDocuments'][0]['url'];
            $tracking_number = $body['output']['transactionShipments'][0]['masterTrackingNumber'] ?? '';
            
            // Download and save the label
            $label_result = $this->download_and_save_label($label_url, $tracking_number);
            
            if (!$label_result['success']) {
                return array(
                    'success' => false,
                    'error' => $label_result['error'],
                    'fedex_url' => $label_url, // Include the original URL for debugging
                    'tracking_number' => $tracking_number,
                    'response' => $body
                );
            }
            
            return array(
                'success' => true,
                'label_url' => $label_result['file_url'], // Use our local URL
                'tracking_number' => $tracking_number,
                'response' => $body
            );
        }
        
        // Old response format handling (kept for compatibility)
        if (isset($body['output']['label_url'])) {
            $label_url = $body['output']['label_url'];
            $tracking_number = $body['output']['tracking_number'] ?? '';
            
            // Download and save the label
            $label_result = $this->download_and_save_label($label_url, $tracking_number);
            
            if (!$label_result['success']) {
                return array(
                    'success' => false,
                    'error' => $label_result['error'],
                    'fedex_url' => $label_url, // Include the original URL for debugging
                    'tracking_number' => $tracking_number,
                    'response' => $body
                );
            }
            
            return array(
                'success' => true,
                'label_url' => $label_result['file_url'], // Use our local URL
                'tracking_number' => $tracking_number,
                'response' => $body
            );
        }
        
        // If we reach here, we couldn't find the label data
        return array(
            'success' => false,
            'error' => 'No label data in response',
            'response' => $body
        );
    }
    
    /**
     * Convert internal service ID to FedEx service code
     * 
     * @param string $internal_service_id Internal service ID
     * @return string FedEx service code
     */
    public static function get_service_code($internal_service_id) {
        $service_mapping = array(
            'priority' => 'PRIORITY_OVERNIGHT',
            'standard' => 'STANDARD_OVERNIGHT',
            'express' => 'FEDEX_EXPRESS_SAVER',
            'ground' => 'FEDEX_GROUND',
            'international_priority' => 'INTERNATIONAL_PRIORITY',
            'international_economy' => 'INTERNATIONAL_ECONOMY',
            'international_first' => 'INTERNATIONAL_FIRST',
        );
        
        return isset($service_mapping[$internal_service_id]) ? 
               $service_mapping[$internal_service_id] : 'FEDEX_GROUND';
    }
    
    /**
     * Get FedEx service name from service code
     * 
     * @param string $service_code FedEx service code
     * @return string Service name
     */
    public static function get_service_name($service_code) {
        $service_names = array(
            'PRIORITY_OVERNIGHT' => 'FedEx Priority Overnight',
            'STANDARD_OVERNIGHT' => 'FedEx Standard Overnight',
            'FEDEX_2_DAY' => 'FedEx 2Day',
            'FEDEX_EXPRESS_SAVER' => 'FedEx Express Saver',
            'FEDEX_GROUND' => 'FedEx Ground',
            'INTERNATIONAL_PRIORITY' => 'FedEx International Priority',
            'INTERNATIONAL_ECONOMY' => 'FedEx International Economy',
            'INTERNATIONAL_FIRST' => 'FedEx International First',
        );
        
        return isset($service_names[$service_code]) ? 
               $service_names[$service_code] : $service_code;
    }
    
    /**
     * Prepare test payload without sending it
     * 
     * @param array $shipment_data Shipment details
     * @return array Request payload
     */
    public function prepare_test_payload($shipment_data) {
        return $this->prepare_payload($shipment_data);
    }
    
    /**
     * Load sample request payload from file
     * 
     * @param string $file_path Path to the sample JSON file
     * @return array|false The JSON data as an array or false on failure
     */
    public function load_sample_payload($file_path = null) {
        if ($file_path === null) {
            $file_path = ABSPATH . 'wp-content/samples/ship-request/ship_request.json';
        }
        
        if (!file_exists($file_path)) {
            return false;
        }
        
        $json_content = file_get_contents($file_path);
        if ($json_content === false) {
            return false;
        }
        
        $data = json_decode($json_content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }
        
        // Update account number from our config
        $config = include(WSL_PLUGIN_DIR . 'includes/api/fedex-config.php');
        if (isset($data['accountNumber']['value'])) {
            $data['accountNumber']['value'] = $config['credentials']['account_number'];
        }
        
        if (isset($data['requestedShipment']['shippingChargesPayment']['payor']['responsibleParty']['accountNumber']['value'])) {
            $data['requestedShipment']['shippingChargesPayment']['payor']['responsibleParty']['accountNumber']['value'] = 
                $config['credentials']['account_number'];
        }
        
        return $data;
    }
    
    /**
     * Create shipment using a sample payload file instead of generated payload
     * 
     * @param string $sample_file_path Path to the sample JSON file
     * @return array Response with label data or error information
     */
    public function create_shipment_from_sample($sample_file_path = null) {
        // Load the sample payload
        $payload = $this->load_sample_payload($sample_file_path);
        if ($payload === false) {
            return array(
                'success' => false,
                'error' => 'Failed to load sample payload file'
            );
        }
        
        // Get authentication token
        $token = $this->auth->get_token();
        if (empty($token)) {
            return array(
                'success' => false,
                'error' => 'Failed to obtain authentication token'
            );
        }
        
        // Send the request to FedEx API
        $endpoint = $this->auth->get_endpoint('ship');
        if (empty($endpoint)) {
            return array(
                'success' => false, 
                'error' => 'Invalid API endpoint configuration'
            );
        }
        
        $headers = array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
            'X-locale' => 'en_US'
        );
        
        // Log the request data
        WSL_Debug::log_api_data('fedex_shipment', array(
            'endpoint' => $endpoint,
            'headers' => $headers,
            'payload' => $payload
        ), 'request');
        
        // Make the API request
        $response = wp_remote_post(
            $endpoint,
            array(
                'headers' => $headers,
                'body' => json_encode($payload),
                'timeout' => 30,
            )
        );
        
        // Process the response
        return $this->process_response($response);
    }
    
    /**
     * Downloads a label from FedEx and saves it to the server
     * 
     * @param string $label_url The FedEx label URL
     * @param string $tracking_number The tracking number to use in the filename
     * @return array Information about the saved label file or error
     */
    private function download_and_save_label($label_url, $tracking_number) {
        // Get authentication token
        $token = $this->auth->get_token();
        if (empty($token)) {
            return [
                'success' => false,
                'error' => 'Failed to obtain authentication token for label download'
            ];
        }
        
        // Set up the request to download the label
        $response = wp_remote_get(
            $label_url,
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'X-locale' => 'en_US'
                ],
                'timeout' => 30,
            ]
        );
        
        // Check for errors
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => 'Failed to download label: ' . $response->get_error_message()
            ];
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            return [
                'success' => false,
                'error' => 'Failed to download label. Status code: ' . $status_code
            ];
        }
        
        // Get label content
        $label_content = wp_remote_retrieve_body($response);
        
        // Save the label to a file
        $upload_dir = wp_upload_dir();
        $labels_dir = $upload_dir['basedir'] . '/shipping-labels';
        
        // Create directory if it doesn't exist
        if (!file_exists($labels_dir)) {
            wp_mkdir_p($labels_dir);
        }
        
        // Create filename with timestamp to avoid duplicates
        $timestamp = date('Ymd_His');
        $filename = "fedex_{$tracking_number}_{$timestamp}.pdf";
        $filepath = $labels_dir . '/' . $filename;
        
        // Save the label
        $saved = file_put_contents($filepath, $label_content);
        if ($saved === false) {
            return [
                'success' => false,
                'error' => 'Failed to save label file to server'
            ];
        }
        
        // Return success with file information
        return [
            'success' => true,
            'file_path' => $filepath,
            'file_url' => $upload_dir['baseurl'] . '/shipping-labels/' . $filename
        ];
    }
} 