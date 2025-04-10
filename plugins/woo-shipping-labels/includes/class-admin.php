<?php
/**
 * Admin functionality for the shipping label plugin
 */

if (!defined('WPINC')) {
    die;
}

class WSL_Admin {
    
    public function init() {
        // Remove the old action hook
        // add_action('woocommerce_admin_order_actions_end', array($this, 'add_ship_button'));
        
        // Add filter hook for order actions instead
        add_filter('woocommerce_admin_order_actions', array($this, 'add_ship_button'), 100, 2);
        
        // Add admin menu pages
        add_action('admin_menu', array($this, 'add_menu_pages'));
        
        // Register admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    // Updated callback: now accepts $actions and $order, and returns $actions with the new button added
    public function add_ship_button($actions, $order) {
        $actions['ship'] = array(
            'url'    => admin_url('admin.php?page=wsl-create-label&order_id=' . $order->get_id()),
            'name'   => __('Ship', 'woo-shipping-labels'),
            'action' => 'ship-label' // A CSS class that you can style if necessary
        );
        return $actions;
    }
    
    public function add_menu_pages() {
        // Main menu page
        add_menu_page(
            __('Shipping Labels', 'woo-shipping-labels'),
            __('Shipping Labels', 'woo-shipping-labels'),
            'manage_woocommerce',
            'wsl-shipping-labels',
            array($this, 'render_main_page'),
            'dashicons-shipping',
            56
        );
        
        // Create label page
        add_submenu_page(
            'wsl-shipping-labels',
            __('Create Label', 'woo-shipping-labels'),
            __('Create Label', 'woo-shipping-labels'),
            'manage_woocommerce',
            'wsl-create-label',
            array($this, 'render_create_label_page')
        );
        
        // Settings page
        add_submenu_page(
            'wsl-shipping-labels',
            __('Settings', 'woo-shipping-labels'),
            __('Settings', 'woo-shipping-labels'),
            'manage_woocommerce',
            'wsl-settings',
            array($this, 'render_settings_page')
        );
    }
    
    public function enqueue_admin_assets($hook) {
        // Only load on our plugin pages
        if (strpos($hook, 'wsl-') === false) {
            return;
        }
        
        wp_enqueue_style('wsl-admin-css', WSL_PLUGIN_URL . 'assets/css/admin.css', array(), WSL_VERSION);
        wp_enqueue_script('wsl-admin-js', WSL_PLUGIN_URL . 'assets/css/admin.js', array('jquery'), WSL_VERSION, true);
    }
    
    public function render_main_page() {
        echo '<div class="wrap">';
        echo '<h1>' . __('WooCommerce Shipping Labels', 'woo-shipping-labels') . '</h1>';
        echo '<p>' . __('Manage your shipping labels for WooCommerce orders.', 'woo-shipping-labels') . '</p>';
        echo '</div>';
    }
    
    public function render_create_label_page() {
        $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
        
        echo '<div class="wrap">';
        echo '<h1>' . __('Create Shipping Label', 'woo-shipping-labels') . '</h1>';
        
        if ($order_id > 0) {
            $order = wc_get_order($order_id);
            if ($order) {
                include WSL_PLUGIN_DIR . 'templates/label-form.php';
            } else {
                echo '<div class="notice notice-error"><p>' . __('Order not found.', 'woo-shipping-labels') . '</p></div>';
            }
        } else {
            echo '<div class="notice notice-error"><p>' . __('No order specified.', 'woo-shipping-labels') . '</p></div>';
        }
        
        echo '</div>';
    }
    
    public function render_settings_page() {
        echo '<div class="wrap">';
        echo '<h1>' . __('Shipping Label Settings', 'woo-shipping-labels') . '</h1>';
        
        // Settings form would go here
        
        echo '</div>';
    }

    // Update this in your render_label_form method or wherever you display addresses
    public function render_address_section($order) {
        // Get shipping and billing addresses from order
        $shipping_address = $order->get_address('shipping');
        $billing_address = $order->get_billing();
        
        ?>
        <div class="wsl-addresses">
            <div class="wsl-address-from panel">
                <div class="panel-heading">
                    <h3 class="panel-title"><?php _e('Ship From Address', 'woo-shipping-labels'); ?>
                        <span class="button-group">
                            <button type="button" class="button wsl-validate-address" data-address-type="from">
                                <?php _e('Validate', 'woo-shipping-labels'); ?>
                            </button>
                            <button type="button" class="button edit-address" data-address-type="from">
                                <?php _e('Edit', 'woo-shipping-labels'); ?>
                            </button>
                        </span>
                    </h3>
                </div>
                <div class="panel-body">
                    <div class="wsl-address-data" id="wsl-ship-from">
                        <?php echo $this->format_address(get_option('wsl_from_address', array())); ?>
                    </div>
                    <div class="wsl-validation-result wsl-from-validation-result">
                        <!-- Validation results will be displayed here -->
                    </div>
                </div>
            </div>
            
            <div class="wsl-address-to panel">
                <div class="panel-heading">
                    <h3 class="panel-title"><?php _e('Ship To Address', 'woo-shipping-labels'); ?>
                        <span class="button-group">
                            <button type="button" class="button wsl-validate-address" data-address-type="to">
                                <?php _e('Validate', 'woo-shipping-labels'); ?>
                            </button>
                            <button type="button" class="button edit-address" data-address-type="to">
                                <?php _e('Edit', 'woo-shipping-labels'); ?>
                            </button>
                        </span>
                    </h3>
                </div>
                <div class="panel-body">
                    <div class="wsl-address-data" id="wsl-ship-to">
                        <?php echo $this->format_address($shipping_address); ?>
                    </div>
                    <div class="wsl-validation-result wsl-to-validation-result">
                        <!-- Validation results will be displayed here -->
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    // Helper function to format address for display
    private function format_address($address) {
        $formatted = '';
        
        if (!empty($address['address_1'])) {
            $formatted .= '<div class="address-line">' . esc_html($address['address_1']) . '</div>';
        }
        
        if (!empty($address['address_2'])) {
            $formatted .= '<div class="address-line">' . esc_html($address['address_2']) . '</div>';
        }
        
        $city_line = '';
        if (!empty($address['city'])) {
            $city_line .= esc_html($address['city']);
        }
        
        if (!empty($address['state'])) {
            $city_line .= !empty($city_line) ? ', ' . esc_html($address['state']) : esc_html($address['state']);
        }
        
        if (!empty($address['postcode'])) {
            $city_line .= ' ' . esc_html($address['postcode']);
        }
        
        if (!empty($city_line)) {
            $formatted .= '<div class="address-line">' . $city_line . '</div>';
        }
        
        if (!empty($address['country'])) {
            $formatted .= '<div class="address-line">' . esc_html(WC()->countries->get_countries()[$address['country']]) . '</div>';
        }
        
        return $formatted;
    }
}