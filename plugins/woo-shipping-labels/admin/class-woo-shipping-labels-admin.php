/**
 * Class constructor
 */
public function __construct() {
    // Register all the hooks here
    add_action('admin_init', array($this, 'register_ajax_handlers'));
}

/**
 * Register AJAX handlers
 */
public function register_ajax_handlers() {
    // Basic test endpoint
    add_action('wp_ajax_wsl_test_ajax', function() {
        wp_send_json_success(array('message' => 'Test endpoint works!'));
    });
    
    // Main rates calculation endpoint - direct implementation for troubleshooting
    add_action('wp_ajax_wsl_calculate_rates', function() {
        // Debug information
        error_log('WSL AJAX: wsl_calculate_rates action triggered');
        error_log('POST data: ' . print_r($_POST, true));
        
        // Generate demo rates without any validation for testing
        $plugin_url = plugin_dir_url(dirname(__FILE__));
        $weight = isset($_POST['package_weight']) ? floatval($_POST['package_weight']) : 1.0;
        $base_rate = $weight * 2.5;
        
        $rates = array(
            array(
                'id' => 'fedex_ground',
                'carrier_id' => 'fedex',
                'carrier_name' => 'FedEx',
                'carrier_logo' => $plugin_url . 'assets/images/fedex-logo.png',
                'service_id' => 'ground',
                'service_name' => 'Ground',
                'transit_time' => '3-5 business days',
                'cost' => round($base_rate, 2)
            ),
            array(
                'id' => 'ups_ground',
                'carrier_id' => 'ups',
                'carrier_name' => 'UPS',
                'carrier_logo' => $plugin_url . 'assets/images/ups-logo.png',
                'service_id' => 'ground',
                'service_name' => 'Ground',
                'transit_time' => '3-5 business days',
                'cost' => round($base_rate * 1.05, 2)
            ),
            array(
                'id' => 'usps_priority',
                'carrier_id' => 'usps',
                'carrier_name' => 'USPS',
                'carrier_logo' => $plugin_url . 'assets/images/usps-logo.png',
                'service_id' => 'priority',
                'service_name' => 'Priority Mail',
                'transit_time' => '1-3 business days',
                'cost' => round($base_rate * 0.9, 2)
            )
        );
        
        // Log success
        error_log('WSL AJAX: Returning ' . count($rates) . ' rates');
        
        // Send response
        wp_send_json_success(array(
            'rates' => $rates,
            'message' => 'Demo rates generated successfully'
        ));
    });
} 