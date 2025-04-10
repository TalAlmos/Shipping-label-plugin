<?php
/**
 * FedEx API Authentication
 */

if (!defined('WPINC')) {
    die;
}

class WSL_FedEx_Auth {
    private $config;
    private $token;
    private $token_expiry;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Load the config file
        $this->config = include(WSL_PLUGIN_DIR . 'includes/api/fedex-config.php');
    }
    
    /**
     * Get authentication token
     * 
     * @return string Valid authentication token
     */
    public function get_token() {
        // Check if we have a valid token already
        if ($this->has_valid_token()) {
            return $this->token;
        }
        
        // Otherwise, get a new token
        return $this->generate_new_token();
    }
    
    /**
     * Check if we have a valid (non-expired) token
     * 
     * @return bool True if valid token exists
     */
    private function has_valid_token() {
        if (empty($this->token) || empty($this->token_expiry)) {
            return false;
        }
        
        // Add a 5-minute buffer to expiry time
        return $this->token_expiry > (time() + 300);
    }
    
    /**
     * Generate a new authentication token
     * 
     * @return string New authentication token
     */
    private function generate_new_token() {
        $environment = $this->config['environment'];
        $endpoint = $this->config['endpoints']['auth'][$environment];
        
        $response = wp_remote_post(
            $endpoint,
            array(
                'headers' => array(
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ),
                'body' => array(
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->config['credentials']['client_id'],
                    'client_secret' => $this->config['credentials']['client_secret'],
                ),
                'timeout' => $this->config['timeout'],
            )
        );
        
        if (is_wp_error($response)) {
            // Log the error
            error_log('FedEx authentication error: ' . $response->get_error_message());
            return '';
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (empty($body) || empty($body['access_token']) || empty($body['expires_in'])) {
            // Log the error
            error_log('FedEx authentication error: Invalid response');
            return '';
        }
        
        // Store the token and its expiry time
        $this->token = $body['access_token'];
        $this->token_expiry = time() + $body['expires_in'];
        
        return $this->token;
    }
    
    /**
     * Get an API endpoint URL
     * 
     * @param string $service The service name (e.g., 'address_validation')
     * @return string The endpoint URL
     */
    public function get_endpoint($service) {
        $environment = $this->config['environment'];
        
        if (!isset($this->config['endpoints'][$service][$environment])) {
            return '';
        }
        
        return $this->config['endpoints'][$service][$environment];
    }
} 