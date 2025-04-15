<?php
/**
 * Interface for carrier API implementations
 */

if (!defined('WPINC')) {
    die;
}

interface WSL_Carrier_API {
    /**
     * Validate an address
     * 
     * @param array $address Universal address format
     * @return array Standardized validation result
     */
    public function validate_address($address);
    
    /**
     * Calculate shipping rates
     * 
     * @param array $shipment_data Universal shipment data
     * @return array Rate calculation result
     */
    public function calculate_rates($shipment_data);
    
    /**
     * Create a shipping label
     * 
     * @param array $shipment_data Universal shipment data
     * @return array Label creation result
     */
    public function create_label($shipment_data);
    
    /**
     * Track a shipment
     * 
     * @param string $tracking_number Tracking number
     * @return array Tracking result
     */
    public function track_shipment($tracking_number);
    
    /**
     * Get carrier authentication token
     * 
     * @return string|bool Authentication token or false on failure
     */
    public function get_auth_token();
    
    /**
     * Get carrier services
     * 
     * @return array List of available services
     */
    public function get_services();
    
    /**
     * Get carrier package types
     * 
     * @return array List of available package types
     */
    public function get_package_types();
} 