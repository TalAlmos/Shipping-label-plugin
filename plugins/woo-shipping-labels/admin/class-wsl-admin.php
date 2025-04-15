/**
 * Register the package management page
 */
/*
public function register_package_management_page() {
    add_submenu_page(
        'wsl-settings', // Parent slug - this must be your main plugin menu page slug
        __('Package Management', 'woo-shipping-labels'), // Page title
        __('Packages', 'woo-shipping-labels'), // Menu title
        'manage_woocommerce', // Capability
        'woo-shipping-labels-packages', // Menu slug
        array($this, 'display_package_management_page') // Function to display the page
    );
}

/**
 * Display the package management page
 */
/*
public function display_package_management_page() {
    require_once plugin_dir_path(dirname(__FILE__)) . 'admin/partials/packages.php';
}
*/

public function __construct() {
    // Existing code...
    
    // Existing code...
}

/**
 * Register the stylesheets for the admin area.
 */
public function enqueue_styles($hook) {
    // Existing code...
}

/**
 * Register the JavaScript for the admin area.
 */
public function enqueue_scripts($hook) {
    // Existing code...
}

public function add_menu_pages() {
    // Main menu page
    add_menu_page(
        __('Shipping Labels', 'woo-shipping-labels'),
        __('Shipping Labels', 'woo-shipping-labels'),
        'manage_woocommerce',
        'wsl-settings', // This slug must match the parent slug in register_package_management_page
        array($this, 'render_main_page'),
        'dashicons-shipping',
        56
    );
    
    // Other submenu pages...
}

/**
 * Process and save settings
 */
public function process_settings() {
    // Check if we're processing settings
    if (!isset($_POST['wsl_save_settings'])) {
        return;
    }
    
    // Verify nonce
    if (!isset($_POST['wsl_settings_nonce']) || !wp_verify_nonce($_POST['wsl_settings_nonce'], 'wsl_save_settings')) {
        add_settings_error('wsl_settings', 'invalid_nonce', __('Security check failed. Please try again.', 'woo-shipping-labels'));
        return;
    }
    
    // ... existing settings processing code ...
    
    // Process currencies
    if (isset($_POST['wsl_currencies'])) {
        $currencies = array();
        $default_currency = isset($_POST['wsl_default_currency']) ? sanitize_text_field($_POST['wsl_default_currency']) : 'USD';
        
        foreach ($_POST['wsl_currencies'] as $code => $data) {
            $code = sanitize_text_field($code);
            
            $currencies[$code] = array(
                'name' => isset($data['name']) ? sanitize_text_field($data['name']) : '',
                'enabled' => isset($data['enabled']) && $data['enabled'] == '1',
                'default' => ($code === $default_currency),
            );
        }
        
        // Ensure at least one currency is enabled and default is set
        $has_enabled = false;
        foreach ($currencies as $code => $data) {
            if ($data['enabled']) {
                $has_enabled = true;
                break;
            }
        }
        
        if (!$has_enabled) {
            // If no currency is enabled, enable USD by default
            $currencies['USD']['enabled'] = true;
            $currencies['USD']['default'] = true;
        }
        
        // Ensure the default currency is enabled
        if (isset($currencies[$default_currency])) {
            $currencies[$default_currency]['enabled'] = true;
        }
        
        update_option('wsl_currencies', $currencies);
    }
    
    // ... rest of your settings save code ...
} 