<?php
/**
 * Carrier Manager - Handles loading and managing carrier implementations
 */

if (!defined('WPINC')) {
    die;
}

class WSL_Carrier_Manager {
    /**
     * Registered carriers
     * 
     * @var array
     */
    private $carriers = array();
    
    /**
     * Available carrier options
     * 
     * @var array
     */
    private $carrier_options = array();
    
    /**
     * Singleton instance
     * 
     * @var WSL_Carrier_Manager
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
     * 
     * @return WSL_Carrier_Manager
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_carriers();
    }
    
    /**
     * Initialize available carriers
     */
    private function init_carriers() {
        // Define the available carriers
        $this->carrier_options = array(
            'fedex' => 'FedEx',
            'ups' => 'UPS',
            'dhl' => 'DHL',
            'usps' => 'USPS',
        );
        
        // Only load the enabled carriers
        $enabled_carriers = get_option('wsl_enabled_carriers', array('fedex'));
        
        // Load the carrier classes
        foreach ($enabled_carriers as $carrier_id) {
            $this->load_carrier($carrier_id);
        }
    }
    
    /**
     * Load a carrier API
     * 
     * @param string $carrier_id Carrier identifier
     * @return bool True if carrier was loaded
     */
    public function load_carrier($carrier_id) {
        // Skip if already loaded
        if (isset($this->carriers[$carrier_id])) {
            return true;
        }
        
        // Check if this is a valid carrier
        if (!isset($this->carrier_options[$carrier_id])) {
            return false;
        }
        
        // Load the carrier class
        $class_name = 'WSL_' . ucfirst($carrier_id) . '_API';
        $class_file = WSL_PLUGIN_DIR . 'includes/carriers/class-' . $carrier_id . '-api.php';
        
        if (file_exists($class_file)) {
            require_once $class_file;
            
            if (class_exists($class_name)) {
                $this->carriers[$carrier_id] = new $class_name();
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get a specific carrier API
     * 
     * @param string $carrier_id Carrier identifier
     * @return WSL_Carrier_API|null Carrier API or null if not found
     */
    public function get_carrier($carrier_id) {
        if (isset($this->carriers[$carrier_id])) {
            return $this->carriers[$carrier_id];
        }
        
        // Try to load the carrier
        if ($this->load_carrier($carrier_id)) {
            return $this->carriers[$carrier_id];
        }
        
        return null;
    }
    
    /**
     * Get all registered carriers
     * 
     * @param bool $only_enabled Only return enabled carriers
     * @return array Carrier API instances
     */
    public function get_carriers($only_enabled = true) {
        if (!$only_enabled) {
            return $this->carriers;
        }
        
        // Filter to only enabled carriers
        return array_filter($this->carriers, function($carrier) {
            return $carrier->is_enabled();
        });
    }
    
    /**
     * Get available carrier options
     * 
     * @return array Carrier options
     */
    public function get_carrier_options() {
        return $this->carrier_options;
    }
    
    /**
     * Validate an address using the specified carrier
     * 
     * @param array $address Universal address format
     * @param string $carrier_id Carrier identifier
     * @return array Standardized validation result
     */
    public function validate_address($address, $carrier_id = 'fedex') {
        $carrier = $this->get_carrier($carrier_id);
        
        if (!$carrier) {
            // No carrier, use basic validation
            return WSL_Address::validate_address($address, '');
        }
        
        return $carrier->validate_address($address);
    }
    
    /**
     * Calculate shipping rates using the specified carrier
     * 
     * @param array $shipment_data Universal shipment data
     * @param string $carrier_id Carrier identifier
     * @return array Rate calculation result
     */
    public function calculate_rates($shipment_data, $carrier_id = 'fedex') {
        $carrier = $this->get_carrier($carrier_id);
        
        if (!$carrier) {
            return array(
                'success' => false,
                'error' => sprintf(__('Carrier "%s" not available', 'woo-shipping-labels'), $carrier_id),
            );
        }
        
        return $carrier->calculate_rates($shipment_data);
    }
    
    /**
     * Create a shipping label using the specified carrier
     * 
     * @param array $shipment_data Universal shipment data
     * @param string $carrier_id Carrier identifier
     * @return array Label creation result
     */
    public function create_label($shipment_data, $carrier_id = 'fedex') {
        $carrier = $this->get_carrier($carrier_id);
        
        if (!$carrier) {
            return array(
                'success' => false,
                'error' => sprintf(__('Carrier "%s" not available', 'woo-shipping-labels'), $carrier_id),
            );
        }
        
        return $carrier->create_label($shipment_data);
    }
    
    /**
     * Track a shipment using the specified carrier
     * 
     * @param string $tracking_number Tracking number
     * @param string $carrier_id Carrier identifier
     * @return array Tracking result
     */
    public function track_shipment($tracking_number, $carrier_id = 'fedex') {
        $carrier = $this->get_carrier($carrier_id);
        
        if (!$carrier) {
            return array(
                'success' => false,
                'error' => sprintf(__('Carrier "%s" not available', 'woo-shipping-labels'), $carrier_id),
            );
        }
        
        return $carrier->track_shipment($tracking_number);
    }
    
    /**
     * Detect carrier from tracking number
     * 
     * @param string $tracking_number Tracking number
     * @return string|null Detected carrier ID or null if unknown
     */
    public function detect_carrier_from_tracking($tracking_number) {
        $tracking_number = strtoupper(trim($tracking_number));
        
        // FedEx - Typically 12 or 15 digits, may start with 'FDX'
        if (preg_match('/^(FDX)?(\d{12}|\d{15})$/', $tracking_number)) {
            return 'fedex';
        }
        
        // UPS - 1Z followed by 16 digits/letters
        if (preg_match('/^1Z[0-9A-Z]{16}$/', $tracking_number)) {
            return 'ups';
        }
        
        // USPS - Various formats
        if (preg_match('/^(9[4-5]\d{20}|92\d{18}|94\d{18}|E\D{1}\d{9}\D{2}|[A-Z]{2}\d{9}US)$/', $tracking_number)) {
            return 'usps';
        }
        
        // DHL - Usually 10 digits
        if (preg_match('/^\d{10}$/', $tracking_number)) {
            return 'dhl';
        }
        
        // Unknown carrier
        return null;
    }
    
    /**
     * Get tracking URL for a carrier
     * 
     * @param string $tracking_number Tracking number
     * @param string $carrier_id Carrier identifier
     * @return string Tracking URL
     */
    public function get_tracking_url($tracking_number, $carrier_id = null) {
        // Detect carrier if not provided
        if (!$carrier_id) {
            $carrier_id = $this->detect_carrier_from_tracking($tracking_number);
        }
        
        // Default tracking URLs
        $tracking_urls = array(
            'fedex' => 'https://www.fedex.com/apps/fedextrack/?tracknumbers=' . $tracking_number,
            'ups' => 'https://www.ups.com/track?tracknum=' . $tracking_number,
            'usps' => 'https://tools.usps.com/go/TrackConfirmAction?tLabels=' . $tracking_number,
            'dhl' => 'https://www.dhl.com/en/express/tracking.html?AWB=' . $tracking_number,
        );
        
        // Return URL for specified carrier, or default to FedEx if carrier is unknown
        return isset($tracking_urls[$carrier_id]) ? $tracking_urls[$carrier_id] : $tracking_urls['fedex'];
    }
} 