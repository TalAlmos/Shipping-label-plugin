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
    require_once WSL_PLUGIN_DIR . 'includes/class-debug.php';
    require_once WSL_PLUGIN_DIR . 'includes/class-address.php';
    require_once WSL_PLUGIN_DIR . 'includes/class-admin.php';
    
    // Include the required FedEx API classes
    require_once WSL_PLUGIN_DIR . 'includes/api/class-fedex-auth.php';
    require_once WSL_PLUGIN_DIR . 'includes/api/class-fedex-address-validation.php';
    require_once WSL_PLUGIN_DIR . 'includes/api/class-fedex-ship.php';
    
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
    
    if (!file_exists($labels_dir)) {
        wp_mkdir_p($labels_dir);
    }
    
    // Initialize default currencies if not already set
    if (!get_option('wsl_currencies')) {
        $default_currencies = array(
            'USD' => array('name' => 'US Dollar', 'enabled' => true, 'default' => true),
            'EUR' => array('name' => 'Euro', 'enabled' => true, 'default' => false),
            'GBP' => array('name' => 'British Pound', 'enabled' => true, 'default' => false),
            'CAD' => array('name' => 'Canadian Dollar', 'enabled' => true, 'default' => false),
        );
        update_option('wsl_currencies', $default_currencies);
    }
}
register_activation_hook(__FILE__, 'wsl_activate');