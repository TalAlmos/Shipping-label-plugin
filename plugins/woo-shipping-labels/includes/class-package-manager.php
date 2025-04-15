<?php
/**
 * Package Manager - Handles loading and managing package types
 */

if (!defined('WPINC')) {
    die;
}

class WSL_Package_Manager {
    /**
     * Registered package types
     * 
     * @var array
     */
    private $package_types = array();
    
    /**
     * Singleton instance
     * 
     * @var WSL_Package_Manager
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
     * 
     * @return WSL_Package_Manager
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
        $this->init_package_types();
    }
    
    /**
     * Initialize available package types
     */
    private function init_package_types() {
        // Initialize with default package types
        $this->package_types = array(
            'user' => array(
                'name' => __('Custom Package', 'woo-shipping-labels'),
                'type' => 'user',
                'dimensions' => array(
                    'length' => 0,
                    'width' => 0,
                    'height' => 0,
                ),
                'weight' => 0,
                'max_weight' => 0,
            ),
        );
        
        // Load saved package types from the database
        $saved_packages = get_option('wsl_package_types', array());
        if (is_array($saved_packages) && !empty($saved_packages)) {
            foreach ($saved_packages as $package_id => $package) {
                if (!isset($this->package_types[$package_id])) {
                    $this->package_types[$package_id] = $package;
                }
            }
        }
        
        // Add carrier-specific package types
        $this->add_fedex_packages();
        $this->add_ups_packages();
        $this->add_usps_packages();
        
        // Allow other plugins to add their package types
        $this->package_types = apply_filters('wsl_package_types', $this->package_types);
    }
    
    /**
     * Add FedEx standard package types
     */
    private function add_fedex_packages() {
        $fedex_packages = array(
            'fedex_envelope' => array(
                'name' => __('FedEx Envelope', 'woo-shipping-labels'),
                'type' => 'fedex',
                'dimensions' => array(
                    'length' => 12.5,
                    'width' => 9.5,
                    'height' => 0.25,
                ),
                'weight' => 0.5,
                'max_weight' => 1.0,
            ),
            'fedex_pak' => array(
                'name' => __('FedEx Pak', 'woo-shipping-labels'),
                'type' => 'fedex',
                'dimensions' => array(
                    'length' => 15.5,
                    'width' => 12,
                    'height' => 0.8,
                ),
                'weight' => 0.5,
                'max_weight' => 5.5,
            ),
            'fedex_box_small' => array(
                'name' => __('FedEx Box Small', 'woo-shipping-labels'),
                'type' => 'fedex',
                'dimensions' => array(
                    'length' => 12.25,
                    'width' => 10.9,
                    'height' => 1.5,
                ),
                'weight' => 0.5,
                'max_weight' => 20,
            ),
            'fedex_box_medium' => array(
                'name' => __('FedEx Box Medium', 'woo-shipping-labels'),
                'type' => 'fedex',
                'dimensions' => array(
                    'length' => 13.25,
                    'width' => 11.5,
                    'height' => 2.38,
                ),
                'weight' => 0.5,
                'max_weight' => 20,
            ),
            'fedex_box_large' => array(
                'name' => __('FedEx Box Large', 'woo-shipping-labels'),
                'type' => 'fedex',
                'dimensions' => array(
                    'length' => 17.88,
                    'width' => 12.38,
                    'height' => 3,
                ),
                'weight' => 0.5,
                'max_weight' => 20,
            ),
            'fedex_tube' => array(
                'name' => __('FedEx Tube', 'woo-shipping-labels'),
                'type' => 'fedex',
                'dimensions' => array(
                    'length' => 38,
                    'width' => 6,
                    'height' => 6,
                ),
                'weight' => 0.5,
                'max_weight' => 20,
            ),
        );
        
        $this->package_types = array_merge($this->package_types, $fedex_packages);
    }
    
    /**
     * Add UPS standard package types
     */
    private function add_ups_packages() {
        $ups_packages = array(
            'ups_letter' => array(
                'name' => __('UPS Letter', 'woo-shipping-labels'),
                'type' => 'ups',
                'dimensions' => array(
                    'length' => 12.5,
                    'width' => 9.5,
                    'height' => 0.25,
                ),
                'weight' => 0.5,
                'max_weight' => 1.0,
            ),
            'ups_pak' => array(
                'name' => __('UPS Pak', 'woo-shipping-labels'),
                'type' => 'ups',
                'dimensions' => array(
                    'length' => 16,
                    'width' => 13,
                    'height' => 1,
                ),
                'weight' => 0.5,
                'max_weight' => 10,
            ),
            'ups_box_small' => array(
                'name' => __('UPS Box Small', 'woo-shipping-labels'),
                'type' => 'ups',
                'dimensions' => array(
                    'length' => 13,
                    'width' => 11,
                    'height' => 2,
                ),
                'weight' => 0.5,
                'max_weight' => 30,
            ),
            'ups_box_medium' => array(
                'name' => __('UPS Box Medium', 'woo-shipping-labels'),
                'type' => 'ups',
                'dimensions' => array(
                    'length' => 16,
                    'width' => 13,
                    'height' => 2,
                ),
                'weight' => 0.5,
                'max_weight' => 30,
            ),
            'ups_box_large' => array(
                'name' => __('UPS Box Large', 'woo-shipping-labels'),
                'type' => 'ups',
                'dimensions' => array(
                    'length' => 18,
                    'width' => 13,
                    'height' => 3,
                ),
                'weight' => 0.5,
                'max_weight' => 30,
            ),
        );
        
        $this->package_types = array_merge($this->package_types, $ups_packages);
    }
    
    /**
     * Add USPS standard package types
     */
    private function add_usps_packages() {
        $usps_packages = array(
            'usps_flat_rate_envelope' => array(
                'name' => __('USPS Flat Rate Envelope', 'woo-shipping-labels'),
                'type' => 'usps',
                'dimensions' => array(
                    'length' => 12.5,
                    'width' => 9.5,
                    'height' => 0.5,
                ),
                'weight' => 0.5,
                'max_weight' => 4,
            ),
            'usps_flat_rate_legal_envelope' => array(
                'name' => __('USPS Flat Rate Legal Envelope', 'woo-shipping-labels'),
                'type' => 'usps',
                'dimensions' => array(
                    'length' => 15,
                    'width' => 9.5,
                    'height' => 0.5,
                ),
                'weight' => 0.5,
                'max_weight' => 4,
            ),
            'usps_flat_rate_padded_envelope' => array(
                'name' => __('USPS Flat Rate Padded Envelope', 'woo-shipping-labels'),
                'type' => 'usps',
                'dimensions' => array(
                    'length' => 12.5,
                    'width' => 9.5,
                    'height' => 1,
                ),
                'weight' => 0.5,
                'max_weight' => 4,
            ),
            'usps_small_flat_rate_box' => array(
                'name' => __('USPS Small Flat Rate Box', 'woo-shipping-labels'),
                'type' => 'usps',
                'dimensions' => array(
                    'length' => 8.75,
                    'width' => 5.375,
                    'height' => 1.625,
                ),
                'weight' => 0.5,
                'max_weight' => 4,
            ),
            'usps_medium_flat_rate_box1' => array(
                'name' => __('USPS Medium Flat Rate Box 1', 'woo-shipping-labels'),
                'type' => 'usps',
                'dimensions' => array(
                    'length' => 11.25,
                    'width' => 8.75,
                    'height' => 6,
                ),
                'weight' => 0.5,
                'max_weight' => 20,
            ),
            'usps_medium_flat_rate_box2' => array(
                'name' => __('USPS Medium Flat Rate Box 2', 'woo-shipping-labels'),
                'type' => 'usps',
                'dimensions' => array(
                    'length' => 14,
                    'width' => 12,
                    'height' => 3.5,
                ),
                'weight' => 0.5,
                'max_weight' => 20,
            ),
            'usps_large_flat_rate_box' => array(
                'name' => __('USPS Large Flat Rate Box', 'woo-shipping-labels'),
                'type' => 'usps',
                'dimensions' => array(
                    'length' => 12.25,
                    'width' => 12.25,
                    'height' => 6,
                ),
                'weight' => 0.5,
                'max_weight' => 20,
            ),
        );
        
        $this->package_types = array_merge($this->package_types, $usps_packages);
    }
    
    /**
     * Get all package types
     * 
     * @param string $filter_by Filter package types by carrier
     * @return array Package types
     */
    public function get_package_types($filter_by = '') {
        if (empty($filter_by)) {
            return $this->package_types;
        }
        
        return array_filter($this->package_types, function($package) use ($filter_by) {
            return $package['type'] === $filter_by || $package['type'] === 'user';
        });
    }
    
    /**
     * Get a specific package type
     * 
     * @param string $package_id Package identifier
     * @return array|null Package data or null if not found
     */
    public function get_package_type($package_id) {
        return isset($this->package_types[$package_id]) ? $this->package_types[$package_id] : null;
    }
    
    /**
     * Add a custom package type
     * 
     * @param string $package_id Package identifier
     * @param array $package_data Package data
     * @return bool True if package was added
     */
    public function add_package_type($package_id, $package_data) {
        if (isset($this->package_types[$package_id])) {
            return false;
        }
        
        $this->package_types[$package_id] = $package_data;
        
        // Save to database
        $saved_packages = get_option('wsl_package_types', array());
        $saved_packages[$package_id] = $package_data;
        update_option('wsl_package_types', $saved_packages);
        
        return true;
    }
    
    /**
     * Update a custom package type
     * 
     * @param string $package_id Package identifier
     * @param array $package_data Package data
     * @return bool True if package was updated
     */
    public function update_package_type($package_id, $package_data) {
        if (!isset($this->package_types[$package_id])) {
            return false;
        }
        
        $this->package_types[$package_id] = $package_data;
        
        // Only save user-defined packages to the database
        if ($package_data['type'] === 'user') {
            $saved_packages = get_option('wsl_package_types', array());
            $saved_packages[$package_id] = $package_data;
            update_option('wsl_package_types', $saved_packages);
        }
        
        return true;
    }
    
    /**
     * Delete a custom package type
     * 
     * @param string $package_id Package identifier
     * @return bool True if package was deleted
     */
    public function delete_package_type($package_id) {
        if (!isset($this->package_types[$package_id])) {
            return false;
        }
        
        // Only allow deleting user-defined packages
        if ($this->package_types[$package_id]['type'] !== 'user') {
            return false;
        }
        
        unset($this->package_types[$package_id]);
        
        // Save to database
        $saved_packages = get_option('wsl_package_types', array());
        unset($saved_packages[$package_id]);
        update_option('wsl_package_types', $saved_packages);
        
        return true;
    }
    
    /**
     * Get package dimensions for a specific package type
     * 
     * @param string $package_id Package identifier
     * @return array|null Package dimensions or null if not found
     */
    public function get_package_dimensions($package_id) {
        if (!isset($this->package_types[$package_id])) {
            return null;
        }
        
        return $this->package_types[$package_id]['dimensions'] ?? null;
    }
    
    /**
     * Get only user-defined custom packages
     * 
     * @return array User-defined package types
     */
    public function get_user_packages() {
        return array_filter($this->package_types, function($package) {
            return $package['type'] === 'user';
        });
    }
    
    /**
     * Get packages for a specific carrier
     * 
     * @param string $carrier_id Carrier identifier
     * @return array Carrier-specific package types
     */
    public function get_carrier_packages($carrier_id) {
        // Filter packages by carrier
        $packages = array_filter($this->package_types, function($package) use ($carrier_id) {
            return $package['type'] === $carrier_id;
        });
        
        // Format packages to match the expected structure in package-selection.php
        $formatted_packages = array();
        foreach($packages as $package_id => $package_data) {
            $formatted_packages[$package_id] = array(
                'id' => $package_id,
                'name' => $package_data['name'],
                'type' => $package_data['type'],
                'length' => $package_data['dimensions']['length'],
                'width' => $package_data['dimensions']['width'],
                'height' => $package_data['dimensions']['height'],
                'weight' => $package_data['weight'],
                'max_weight' => $package_data['max_weight'],
                'dim_unit' => 'in', // Default to inches
                'weight_unit' => 'lb', // Default to pounds
            );
        }
        
        return $formatted_packages;
    }
} 