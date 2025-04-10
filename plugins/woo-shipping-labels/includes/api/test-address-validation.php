<?php
/**
 * Test function for FedEx Address Validation API
 */

if (!defined('WPINC')) {
    die;
}

/**
 * Run a test of the FedEx Address Validation API
 * 
 * @return void
 */
function wsl_test_fedex_address_validation() {
    // Make sure our required classes are loaded
    require_once WSL_PLUGIN_DIR . 'includes/api/class-fedex-auth.php';
    require_once WSL_PLUGIN_DIR . 'includes/api/class-fedex-address-validation.php';
    
    // Create an instance of the address validation class
    $validator = new WSL_FedEx_Address_Validation();
    
    // Test with the sample data
    $sample_result = $validator->test_with_sample();
    
    // Output the result
    echo '<h2>FedEx Address Validation API Test</h2>';
    echo '<h3>Sample Data Test</h3>';
    
    if ($sample_result['success']) {
        echo '<div class="notice notice-success"><p>Sample test successful!</p></div>';
        
        if (!empty($sample_result['addresses'])) {
            echo '<h4>Validated Addresses:</h4>';
            echo '<pre>';
            print_r($sample_result['addresses']);
            echo '</pre>';
        }
    } else {
        echo '<div class="notice notice-error"><p>Sample test failed: ';
        echo esc_html($sample_result['error'] ?? 'Unknown error');
        echo '</p></div>';
        
        if (!empty($sample_result['response'])) {
            echo '<h4>API Response:</h4>';
            echo '<pre>';
            print_r($sample_result['response']);
            echo '</pre>';
        }
    }
    
    // Test with a real address
    $real_address = array(
        'address_1' => '1600 Amphitheatre Parkway',
        'address_2' => '',
        'city' => 'Mountain View',
        'state' => 'CA',
        'postcode' => '94043',
        'country' => 'US',
        'order_id' => 'test123',
    );
    
    $real_result = $validator->validate_address($real_address);
    
    echo '<h3>Real Address Test</h3>';
    
    if ($real_result['success']) {
        echo '<div class="notice notice-success"><p>Real address test successful!</p></div>';
        
        if (!empty($real_result['addresses'])) {
            echo '<h4>Validated Addresses:</h4>';
            echo '<pre>';
            print_r($real_result['addresses']);
            echo '</pre>';
        }
    } else {
        echo '<div class="notice notice-error"><p>Real address test failed: ';
        echo esc_html($real_result['error'] ?? 'Unknown error');
        echo '</p></div>';
        
        if (!empty($real_result['response'])) {
            echo '<h4>API Response:</h4>';
            echo '<pre>';
            print_r($real_result['response']);
            echo '</pre>';
        }
    }
}

// Add a temporary admin hook to run the test
add_action('admin_notices', 'wsl_display_test_link');

function wsl_display_test_link() {
    // Only show to admins
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Create a nonce for security
    $nonce = wp_create_nonce('wsl_test_fedex');
    
    // Display the test link
    echo '<div class="notice notice-info">';
    echo '<p>FedEx API Testing: <a href="' . admin_url('admin.php?page=wsl-settings&test_fedex=1&_wpnonce=' . $nonce) . '">Run Address Validation Test</a></p>';
    echo '</div>';
}

// Handle the test request
add_action('admin_init', 'wsl_handle_test_request');

function wsl_handle_test_request() {
    // Check if this is a test request
    if (!isset($_GET['test_fedex']) || !isset($_GET['_wpnonce'])) {
        return;
    }
    
    // Verify the nonce
    if (!wp_verify_nonce($_GET['_wpnonce'], 'wsl_test_fedex')) {
        wp_die('Security check failed');
    }
    
    // Make sure user has permission
    if (!current_user_can('manage_options')) {
        wp_die('You do not have permission to run this test');
    }
    
    // Run the test
    wsl_test_fedex_address_validation();
    
    // Stop execution to prevent the rest of the page from loading
    exit;
} 