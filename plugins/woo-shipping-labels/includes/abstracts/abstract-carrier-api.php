<?php
/**
 * Abstract base class for carrier API implementations
 */

if (!defined('WPINC')) {
    die;
}

require_once WSL_PLUGIN_DIR . 'includes/interfaces/interface-carrier-api.php';

abstract class WSL_Abstract_Carrier_API implements WSL_Carrier_API {
    /**
     * Carrier identifier
     * 
     * @var string
     */
    protected $carrier_id;
    
    /**
     * Carrier display name
     * 
     * @var string
     */
    protected $carrier_name;
    
    /**
     * Carrier settings
     * 
     * @var array
     */
    protected $settings;
    
    /**
     * Debug mode
     * 
     * @var bool
     */
    protected $debug_mode;
    
    /**
     * Constructor
     * 
     * @param string $carrier_id Carrier identifier
     * @param string $carrier_name Carrier display name
     */
    public function __construct($carrier_id, $carrier_name) {
        $this->carrier_id = $carrier_id;
        $this->carrier_name = $carrier_name;
        $this->debug_mode = defined('WP_DEBUG') && WP_DEBUG;
        
        // Load carrier settings
        $this->load_settings();
    }
    
    /**
     * Load carrier settings
     */
    protected function load_settings() {
        $carrier_settings = get_option('wsl_carrier_settings', array());
        $this->settings = $carrier_settings[$this->carrier_id] ?? array();
    }
    
    /**
     * Get carrier ID
     * 
     * @return string Carrier ID
     */
    public function get_carrier_id() {
        return $this->carrier_id;
    }
    
    /**
     * Get carrier name
     * 
     * @return string Carrier name
     */
    public function get_carrier_name() {
        return $this->carrier_name;
    }
    
    /**
     * Check if carrier is enabled
     * 
     * @return bool True if carrier is enabled
     */
    public function is_enabled() {
        return isset($this->settings['enabled']) && $this->settings['enabled'] === 'yes';
    }
    
    /**
     * Check if carrier settings are valid
     * 
     * @return bool True if settings are valid
     */
    abstract public function has_valid_settings();
    
    /**
     * Log API request/response data
     * 
     * @param string $api_name API name (e.g., 'address_validation', 'rate', 'ship')
     * @param array|string $data Data to log
     * @param string $direction Either 'request' or 'response'
     */
    protected function log_api_data($api_name, $data, $direction = 'request') {
        if (!class_exists('WSL_Debug')) {
            require_once WSL_PLUGIN_DIR . 'includes/class-debug.php';
        }
        
        $api_name = $this->carrier_id . '_' . $api_name;
        WSL_Debug::log_api_data($api_name, $data, $direction);
    }
    
    /**
     * Make an API request
     * 
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @param string $method HTTP method (GET, POST, etc.)
     * @param array $headers HTTP headers
     * @return array|WP_Error Response or error
     */
    protected function make_api_request($endpoint, $data, $method = 'POST', $headers = array()) {
        $args = array(
            'method' => $method,
            'timeout' => 30,
            'headers' => $headers,
        );
        
        if ($method === 'GET') {
            $endpoint = add_query_arg($data, $endpoint);
        } else {
            $args['body'] = is_array($data) ? json_encode($data) : $data;
        }
        
        // Log the request
        $this->log_api_data(basename($endpoint), $data, 'request');
        
        // Make the request
        $response = wp_remote_request($endpoint, $args);
        
        // Log the response
        $this->log_api_data(basename($endpoint), array(
            'raw_response' => $response,
            'body' => is_wp_error($response) ? $response->get_error_message() : json_decode(wp_remote_retrieve_body($response), true)
        ), 'response');
        
        return $response;
    }
    
    /**
     * Format error response
     * 
     * @param string $message Error message
     * @param mixed $data Additional error data
     * @return array Formatted error
     */
    protected function format_error($message, $data = null) {
        return array(
            'success' => false,
            'error' => $message,
            'data' => $data,
        );
    }
    
    /**
     * Format success response
     * 
     * @param mixed $data Response data
     * @param string $message Success message
     * @return array Formatted success
     */
    protected function format_success($data, $message = '') {
        return array(
            'success' => true,
            'data' => $data,
            'message' => $message,
        );
    }
    
    /**
     * Update carrier settings (used for testing connections)
     * 
     * @param array $settings New settings
     */
    public function update_settings($settings) {
        $this->settings = array_merge($this->settings, $settings);
    }
    
    /**
     * Test API connection
     * 
     * @return array Test result
     */
    public function test_connection() {
        // Check if settings are valid
        if (!$this->has_valid_settings()) {
            return $this->format_error(__('Missing required API credentials', 'woo-shipping-labels'));
        }
        
        try {
            // Implement carrier-specific connection test here
            // This should be overridden by child classes
            return $this->format_success('', __('Connection test successful', 'woo-shipping-labels'));
        } catch (Exception $e) {
            return $this->format_error(
                sprintf(__('Connection test failed: %s', 'woo-shipping-labels'), $e->getMessage())
            );
        }
    }
} 