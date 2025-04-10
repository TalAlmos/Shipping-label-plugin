<?php
/**
 * AJAX handlers for address validation
 */

if (!defined('WPINC')) {
    die;
}

/**
 * Hook the AJAX handlers
 */
function wsl_init_address_validation_ajax() {
    add_action('wp_ajax_wsl_validate_address', 'wsl_ajax_validate_address');
}
add_action('init', 'wsl_init_address_validation_ajax');

/**
 * AJAX handler for address validation
 */
function wsl_ajax_validate_address() {
    // Check nonce
    if (!check_ajax_referer('wsl_address_validation', 'nonce', false)) {
        wp_send_json_error(array(
            'message' => 'Security check failed'
        ));
    }
    
    // Get address data from request
    $address = array(
        'address_1' => sanitize_text_field($_POST['address_1'] ?? ''),
        'address_2' => sanitize_text_field($_POST['address_2'] ?? ''),
        'city' => sanitize_text_field($_POST['city'] ?? ''),
        'state' => sanitize_text_field($_POST['state'] ?? ''),
        'postcode' => sanitize_text_field($_POST['postcode'] ?? ''),
        'country' => sanitize_text_field($_POST['country'] ?? ''),
    );
    
    // Validate required fields
    if (empty($address['address_1']) || empty($address['city']) || 
        empty($address['state']) || empty($address['postcode'])) {
        wp_send_json_error(array(
            'message' => 'Required address fields missing'
        ));
    }
    
    // Load FedEx classes
    require_once WSL_PLUGIN_DIR . 'includes/api/class-fedex-auth.php';
    require_once WSL_PLUGIN_DIR . 'includes/api/class-fedex-address-validation.php';
    
    // Validate address
    $validator = new WSL_FedEx_Address_Validation();
    $result = $validator->validate_address($address);
    
    if (!$result['success']) {
        wp_send_json_error(array(
            'message' => $result['error'] ?? 'Address validation failed'
        ));
    }
    
    // If successful, return the validated address
    wp_send_json_success(array(
        'validated' => $result['addresses'][0] ?? array(),
        'alerts' => $result['alerts'] ?? array(),
    ));
} 