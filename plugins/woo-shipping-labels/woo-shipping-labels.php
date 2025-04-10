<?php
/**
 * Plugin Name: WooCommerce Shipping Labels
 * Description: Generate shipping labels for WooCommerce orders via FedEx, UPS, and DHL APIs
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: woo-shipping-labels
 * Requires WooCommerce: 5.0
 * WC requires at least: 5.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('WSL_VERSION', '1.0.0');
define('WSL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WSL_PLUGIN_URL', plugin_dir_url(__FILE__));

// Check if WooCommerce is active
function wsl_check_woocommerce() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'wsl_woocommerce_missing_notice');
        return false;
    }
    return true;
}

// Admin notice for missing WooCommerce
function wsl_woocommerce_missing_notice() {
    ?>
    <div class="error">
        <p><?php _e('WooCommerce Shipping Labels requires WooCommerce to be installed and active.', 'woo-shipping-labels'); ?></p>
    </div>
    <?php
}

// Plugin initialization
function wsl_init() {
    if (!wsl_check_woocommerce()) {
        return;
    }
    
    // Include required files
    require_once WSL_PLUGIN_DIR . 'includes/class-admin.php';
    
    // Include API test file
    require_once WSL_PLUGIN_DIR . 'includes/api/test-address-validation.php';
    
    // Initialize plugin components
    $admin = new WSL_Admin();
    $admin->init();
}
add_action('plugins_loaded', 'wsl_init');

// Activation hook
function wsl_activate() {
    // Create required directories
    $upload_dir = wp_upload_dir();
    $labels_dir = $upload_dir['basedir'] . '/labels';
    $debug_dir = $upload_dir['basedir'] . '/wsl-debug';
    $debug_post_dir = $debug_dir . '/post';
    $debug_response_dir = $debug_dir . '/response';
    
    if (!file_exists($labels_dir)) {
        wp_mkdir_p($labels_dir);
    }
    
    if (!file_exists($debug_dir)) {
        wp_mkdir_p($debug_dir);
    }
    
    if (!file_exists($debug_post_dir)) {
        wp_mkdir_p($debug_post_dir);
    }
    
    if (!file_exists($debug_response_dir)) {
        wp_mkdir_p($debug_response_dir);
    }
}
register_activation_hook(__FILE__, 'wsl_activate');

// Add this function to enqueue scripts and styles
function wsl_enqueue_admin_scripts($hook) {
    // Check if we're on any shipping label related page - more inclusive approach
    if (strpos($hook, 'wsl') !== false || 
        strpos($hook, 'shipping-label') !== false || 
        isset($_GET['page']) && (strpos($_GET['page'], 'wsl') !== false || 
        strpos($_GET['page'], 'shipping-label') !== false)) {
        
        // Enqueue scripts
        wp_enqueue_script(
            'wsl-address-validation',
            WSL_PLUGIN_URL . 'assets/js/address-validation.js',
            array('jquery'),
            WSL_VERSION,
            true
        );
        
        // Localize script for AJAX
        wp_localize_script(
            'wsl-address-validation',
            'wsl_params',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wsl_address_validation'),
            )
        );
        
        // Enqueue styles
        wp_enqueue_style(
            'wsl-address-validation',
            WSL_PLUGIN_URL . 'assets/css/address-validation.css',
            array(),
            WSL_VERSION
        );
        
        // Dashicons are needed for the validation UI
        wp_enqueue_style('dashicons');
    }
}
add_action('admin_enqueue_scripts', 'wsl_enqueue_admin_scripts');

// Make sure the AJAX handler is loaded
require_once WSL_PLUGIN_DIR . 'includes/ajax/address-validation.php';

// Add a test function for debugging (remove in production)
function wsl_test_debug() {
    // Only run when specifically requested
    if (!isset($_GET['test_wsl_debug']) || !current_user_can('manage_options')) {
        return;
    }
    
    // Include the debug class
    require_once WSL_PLUGIN_DIR . 'includes/class-debug.php';
    
    // Run the test
    $result = WSL_Debug::test_debug_system();
    
    // Output result
    echo '<div class="wrap">';
    echo '<h1>WSL Debug Test Results</h1>';
    echo '<pre>';
    print_r($result);
    echo '</pre>';
    
    // Check if files were created
    $upload_dir = wp_upload_dir();
    $debug_dir = $upload_dir['basedir'] . '/wsl-debug';
    
    echo '<h2>Files in debug directory:</h2>';
    echo '<pre>';
    if (file_exists($debug_dir)) {
        $files = scandir($debug_dir);
        print_r($files);
        
        echo "\n\nPost directory contents:\n";
        $post_dir = $debug_dir . '/post';
        if (file_exists($post_dir)) {
            print_r(scandir($post_dir));
        } else {
            echo "Post directory doesn't exist!";
        }
        
        echo "\n\nResponse directory contents:\n";
        $response_dir = $debug_dir . '/response';
        if (file_exists($response_dir)) {
            print_r(scandir($response_dir));
        } else {
            echo "Response directory doesn't exist!";
        }
    } else {
        echo "Debug directory doesn't exist!";
    }
    echo '</pre>';
    echo '</div>';
    
    exit;
}
add_action('admin_init', 'wsl_test_debug');