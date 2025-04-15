<?php
/**
 * AJAX handlers for package management
 */

if (!defined('WPINC')) {
    die;
}

/**
 * Register AJAX hooks
 */
function wsl_register_package_ajax_handlers() {
    add_action('wp_ajax_wsl_get_carrier_packages', 'wsl_ajax_get_carrier_packages');
    add_action('wp_ajax_wsl_get_package_dimensions', 'wsl_ajax_get_package_dimensions');
}
add_action('init', 'wsl_register_package_ajax_handlers');

/**
 * Get carrier packages
 */
function wsl_ajax_get_carrier_packages() {
    // Check nonce
    if (!check_ajax_referer('wsl_ajax', 'nonce', false)) {
        wp_send_json_error(array('message' => __('Security check failed.', 'woo-shipping-labels')));
    }
    
    // Check permissions
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'woo-shipping-labels')));
    }
    
    // Get carrier from request
    $carrier = isset($_POST['carrier']) ? sanitize_text_field($_POST['carrier']) : '';
    
    if (empty($carrier)) {
        wp_send_json_error(array('message' => __('Carrier is required.', 'woo-shipping-labels')));
    }
    
    // Get package manager
    $package_manager = WSL_Package_Manager::get_instance();
    
    // Get carrier packages
    $packages = $package_manager->get_carrier_packages($carrier);
    
    wp_send_json_success(array(
        'packages' => $packages
    ));
}

/**
 * Get package dimensions
 */
function wsl_ajax_get_package_dimensions() {
    // Check nonce
    if (!check_ajax_referer('wsl_ajax', 'nonce', false)) {
        wp_send_json_error(array('message' => __('Security check failed.', 'woo-shipping-labels')));
    }
    
    // Check permissions
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'woo-shipping-labels')));
    }
    
    // Get parameters from request
    $package_type = isset($_POST['package_type']) ? sanitize_text_field($_POST['package_type']) : '';
    $package_id = isset($_POST['package_id']) ? sanitize_text_field($_POST['package_id']) : '';
    $carrier = isset($_POST['carrier']) ? sanitize_text_field($_POST['carrier']) : '';
    
    if (empty($package_type) || empty($package_id)) {
        wp_send_json_error(array('message' => __('Package type and ID are required.', 'woo-shipping-labels')));
    }
    
    // Get package manager
    $package_manager = WSL_Package_Manager::get_instance();
    
    // Get package dimensions
    $dimensions = $package_manager->get_package_dimensions($package_type, $package_id, array(), $carrier);
    
    wp_send_json_success($dimensions);
} 