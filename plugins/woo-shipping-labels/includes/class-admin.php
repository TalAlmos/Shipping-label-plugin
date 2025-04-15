<?php
/**
 * Admin functionality for the shipping label plugin
 */

if (!defined('WPINC')) {
    die;
}

class WSL_Admin {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Make sure this action is hooked early in the constructor
        add_action('wp_ajax_wsl_get_carrier_services', array($this, 'ajax_get_carrier_services'));
        add_action('wp_ajax_wsl_get_states', array($this, 'ajax_get_states'));
        
        // Add settings page
        add_action('admin_menu', array($this, 'add_menu_pages'));
        
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // Register admin assets - KEEP ONLY THIS ONE for styles and scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Add AJAX handlers
        add_action('wp_ajax_wsl_test_carrier_connection', array($this, 'ajax_test_carrier_connection'));
        add_action('wp_ajax_wsl_get_states', array($this, 'ajax_get_states'));
        
        // COMMENTED OUT: redundant style enqueuing
        // add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
    }
    
    public function init() {
        // Add filter hook for order actions
        add_filter('woocommerce_admin_order_actions', array($this, 'add_ship_button'), 100, 2);
        
        // Add admin menu pages
        add_action('admin_menu', array($this, 'add_menu_pages'));
        
        // Register admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Register AJAX handlers
        add_action('wp_ajax_wsl_validate_address', array($this, 'ajax_validate_address'));
        add_action('wp_ajax_wsl_format_address', array($this, 'ajax_format_address'));
        add_action('wp_ajax_wsl_calculate_shipping_rates', array($this, 'ajax_calculate_shipping_rates'));
        add_action('wp_ajax_wsl_generate_fedex_label', array($this, 'ajax_generate_fedex_label'));
        add_action('wp_ajax_wsl_test_carrier_connection', array($this, 'ajax_test_carrier_connection'));
        add_action('wp_ajax_wsl_get_states', array($this, 'ajax_get_states'));
    }
    
    public function add_ship_button($actions, $order) {
        $actions['ship'] = array(
            'url'    => admin_url('admin.php?page=wsl-create-label&order_id=' . $order->get_id()),
            'name'   => __('Ship', 'woo-shipping-labels'),
            'action' => 'ship-label'
        );
        return $actions;
    }
    
    /**
     * Add all admin menu pages and submenus
     * CONSOLIDATED MENU REGISTRATION FUNCTION
     */
    public function add_menu_pages() {
        // Main menu page
        add_menu_page(
            __('Shipping Labels', 'woo-shipping-labels'),
            __('Shipping Labels', 'woo-shipping-labels'),
            'manage_woocommerce',
            'wsl-shipping-labels', // Keep the original slug
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
        
        // REMOVE ANY ADDITIONAL MENU ITEMS FROM THE OLD METHOD
        // Only register FedEx test page for administrators
        if (current_user_can('manage_options')) {
        add_submenu_page(
                'tools.php',                      // Parent menu (Tools)
                'FedEx Ship API Test',            // Page title
                'FedEx Ship API Test',            // Menu title
                'manage_options',                 // Capability required
                'wsl-fedex-ship-test',            // Menu slug
                array($this, 'render_fedex_ship_test_page')   // Callback function
            );
        }
    }
    
    public function enqueue_admin_assets($hook) {
        // Only enqueue on our settings page
        if ($hook !== 'toplevel_page_wsl-settings' && $hook !== 'woo-shipping-labels_page_wsl-settings') {
            return;
        }
        
        // Enqueue Select2 for multiselect
        wp_enqueue_style('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css', array(), '4.0.13');
        wp_enqueue_script('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js', array('jquery'), '4.0.13', true);
        
        // Enqueue our admin styles
        wp_enqueue_style('wsl-admin-css', plugin_dir_url(dirname(__FILE__)) . 'assets/css/admin.css', array(), WSL_VERSION);
        
        // Enqueue our admin scripts
        wp_enqueue_script('wsl-admin-js', plugin_dir_url(dirname(__FILE__)) . 'assets/js/admin.js', array('jquery'), WSL_VERSION, true);
        
        // Load currency management assets for settings page
        if ($hook === 'toplevel_page_wsl-shipping-labels' || $hook === 'shipping-labels_page_wsl-settings') {
            wp_enqueue_style('wsl-currency-css', WSL_PLUGIN_URL . 'assets/css/currency-management.css', array(), WSL_VERSION);
            wp_enqueue_script('wsl-currency-js', WSL_PLUGIN_URL . 'assets/js/currency-management.js', array('jquery'), WSL_VERSION, true);
            
            // Add i18n for the currency management
            wp_localize_script('wsl-currency-js', 'wsl_currency', array(
                'currency_code_invalid' => __('Currency code must be 3 uppercase letters.', 'woo-shipping-labels'),
                'currency_name_required' => __('Currency name is required.', 'woo-shipping-labels'),
                'currency_exists' => __('This currency already exists.', 'woo-shipping-labels'),
                'delete_confirm' => __('Are you sure you want to delete this currency?', 'woo-shipping-labels'),
                'edit_text' => __('Edit', 'woo-shipping-labels'),
                'delete_text' => __('Delete', 'woo-shipping-labels')
            ));
        }
        
        // Load package management assets if on the packages tab
        if (isset($_GET['tab']) && $_GET['tab'] === 'packages') {
            wp_enqueue_style('wsl-packages-css', WSL_PLUGIN_URL . 'assets/css/packages.css', array(), WSL_VERSION);
            wp_enqueue_script('wsl-packages-js', WSL_PLUGIN_URL . 'assets/js/packages.js', array('jquery'), WSL_VERSION, true);
        }
        
        // Add AJAX data
        wp_localize_script('wsl-admin-js', 'wsl_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'plugin_url' => WSL_PLUGIN_URL,
            'nonce'    => wp_create_nonce('wsl_ajax_nonce'),
            'i18n'     => array(
                'validating'     => __('Validating address...', 'woo-shipping-labels'),
                'valid_address'  => __('Address is valid', 'woo-shipping-labels'),
                'invalid_address' => __('Address validation found issues', 'woo-shipping-labels'),
                'error'          => __('Error validating address', 'woo-shipping-labels'),
                'calculating_rates' => __('Calculating shipping rates...', 'woo-shipping-labels'),
                'package_validation_error' => __('Please fix the following issues with your package:', 'woo-shipping-labels'),
                'weight_required' => __('Weight is required', 'woo-shipping-labels'),
                'description_required' => __('Description of goods is required', 'woo-shipping-labels'),
                'length_required' => __('Length is required for custom packages', 'woo-shipping-labels'),
                'width_required' => __('Width is required for custom packages', 'woo-shipping-labels'),
                'height_required' => __('Height is required for custom packages', 'woo-shipping-labels'),
                'country_required' => __('From and To countries are required', 'woo-shipping-labels'),
                'no_shipping_options' => __('No shipping options available for this route. Please check your country mappings.', 'woo-shipping-labels'),
                'rates_error' => __('Error retrieving shipping rates. Please try again.', 'woo-shipping-labels'),
            )
        ));
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
                include plugin_dir_path(dirname(__FILE__)) . 'admin/partials/label-form.php';
            } else {
                echo '<div class="notice notice-error"><p>' . __('Order not found.', 'woo-shipping-labels') . '</p></div>';
            }
        } else {
            echo '<div class="notice notice-error"><p>' . __('No order specified.', 'woo-shipping-labels') . '</p></div>';
        }
        
        echo '</div>';
    }
    
    public function render_settings_page() {
        // Check if settings were submitted
        if (isset($_POST['wsl_settings_nonce'])) {
            $this->save_settings();
        }
        
        // Define tabs
        $tabs = array(
            'general'   => __('General', 'woo-shipping-labels'),
            'carriers'  => __('Carriers', 'woo-shipping-labels'),
            'packages'  => __('Packages', 'woo-shipping-labels'),
            'countries' => __('Countries', 'woo-shipping-labels'),
        );
        
        // Get current tab
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
        
        // Display settings header
        echo '<div class="wrap wsl-settings-page">';
        echo '<h1>' . __('Shipping Labels Settings', 'woo-shipping-labels') . '</h1>';
        
        // Display tabs
        echo '<nav class="nav-tab-wrapper woo-nav-tab-wrapper">';
        foreach ($tabs as $tab_id => $tab_name) {
            $active_class = ($current_tab === $tab_id) ? 'nav-tab-active' : '';
            $tab_url = add_query_arg(array('page' => 'wsl-settings', 'tab' => $tab_id), admin_url('admin.php'));
            echo '<a href="' . esc_url($tab_url) . '" class="nav-tab ' . esc_attr($active_class) . '">' . esc_html($tab_name) . '</a>';
        }
        echo '</nav>';
        
        // Start settings form
        echo '<form method="post" action="" enctype="multipart/form-data">';
        wp_nonce_field('wsl_save_settings', 'wsl_settings_nonce');
        
        // Display current tab content
        echo '<div class="wsl-settings-tab">';
        
        switch ($current_tab) {
            case 'carriers':
                $this->render_carriers_tab();
                break;
            
            case 'packages':
                $this->render_packages_tab();
                break;
            
            case 'countries':
                $this->render_countries_tab();
                break;
            
            default: // store_info
                $this->render_general_tab();
                break;
        }
        
        echo '</div>'; // .wsl-settings-tab
        
        // Submit button
        echo '<p class="submit">';
        echo '<input type="submit" name="submit" id="submit" class="button button-primary" value="' . esc_attr__('Save Changes', 'woo-shipping-labels') . '">';
        echo '</p>';
        
        echo '</form>';
        echo '</div>'; // .wrap
    }

    /**
     * Render the Carriers tab content
     */
    private function render_carriers_tab() {
        // Get carrier manager instance
        include_once plugin_dir_path(__FILE__) . 'class-carrier-manager.php';
        $carrier_manager = WSL_Carrier_Manager::get_instance();
        $carrier_options = $carrier_manager->get_carrier_options();
        
        // Get saved carrier settings
        $carrier_settings = get_option('wsl_carrier_settings', array());
        $enabled_carriers = get_option('wsl_enabled_carriers', array('fedex'));
        
        // Get current carrier subtab
        $current_carrier = isset($_GET['carrier']) && isset($carrier_options[$_GET['carrier']]) 
            ? sanitize_text_field($_GET['carrier']) 
            : 'fedex';
        
        // Start carriers page content
        echo '<div class="wsl-carriers-settings">';
        
        // Carrier selector tabs
        echo '<div class="wsl-carrier-tabs nav-tab-wrapper">';
        foreach ($carrier_options as $carrier_id => $carrier_name) {
            $active_class = ($carrier_id === $current_carrier) ? 'nav-tab-active' : '';
            $tab_url = add_query_arg(array(
                'page' => 'wsl-settings',
                'tab' => 'carriers',
                'carrier' => $carrier_id
            ), admin_url('admin.php'));
            
            echo '<a href="' . esc_url($tab_url) . '" class="nav-tab ' . esc_attr($active_class) . '">';
            echo '<span class="wsl-carrier-icon wsl-icon-' . esc_attr($carrier_id) . '"></span>';
            echo esc_html($carrier_name);
            echo '</a>';
        }
        echo '</div>'; // .wsl-carrier-tabs
        
        // Carrier settings form
        echo '<div class="wsl-carrier-settings-form">';
        
        // Get current carrier's settings
        $settings = isset($carrier_settings[$current_carrier]) ? $carrier_settings[$current_carrier] : array();
        $is_enabled = in_array($current_carrier, $enabled_carriers);
        
        // Hidden field to identify carrier being updated
        echo '<input type="hidden" name="wsl_settings[current_carrier]" value="' . esc_attr($current_carrier) . '">';
        
        // Enable/disable carrier
        echo '<div class="wsl-form-field wsl-form-field-toggle">';
        echo '<label for="wsl_settings_' . esc_attr($current_carrier) . '_enabled">' . __('Enable', 'woo-shipping-labels') . ' ' . esc_html($carrier_options[$current_carrier]) . '</label>';
        echo '<div class="wsl-toggle-switch">';
        echo '<input type="checkbox" id="wsl_settings_' . esc_attr($current_carrier) . '_enabled" name="wsl_enabled_carriers[]" value="' . esc_attr($current_carrier) . '" ' . checked($is_enabled, true, false) . '>';
        echo '<span class="wsl-toggle-slider"></span>';
        echo '</div>';
        echo '<p class="description">' . sprintf(__('Enable %s integration for shipping labels', 'woo-shipping-labels'), esc_html($carrier_options[$current_carrier])) . '</p>';
        echo '</div>';
        
        // Display carrier-specific settings
        $this->render_carrier_specific_settings($current_carrier, $settings);
        
        // Test connection button
        echo '<div class="wsl-form-field wsl-form-field-test-connection">';
        echo '<button type="button" class="button button-secondary wsl-test-connection" data-carrier="' . esc_attr($current_carrier) . '">' . __('Test Connection', 'woo-shipping-labels') . '</button>';
        echo '<span class="wsl-test-result"></span>';
        echo '</div>';
        
        echo '</div>'; // .wsl-carrier-settings-form
        
        echo '</div>'; // .wsl-carriers-settings
        
        // Add JS for carrier settings functionality
        $this->add_carriers_tab_scripts();
    }

    /**
     * Render carrier-specific settings
     * 
     * @param string $carrier_id Carrier identifier
     * @param array $settings Current settings for the carrier
     */
    private function render_carrier_specific_settings($carrier_id, $settings) {
        // Common fields
        $common_fields = array(
            'test_mode' => array(
                'label' => __('Test Mode', 'woo-shipping-labels'),
                'type' => 'checkbox',
                'description' => __('Use testing API endpoints instead of production', 'woo-shipping-labels'),
                'default' => 'yes'
            )
        );
        
        // Carrier-specific fields
        $carrier_fields = array();
        
        // FedEx settings
        if ($carrier_id === 'fedex') {
            $carrier_fields = array(
                'account_number' => array(
                    'label' => __('Account Number', 'woo-shipping-labels'),
                    'type' => 'text',
                    'description' => __('Your FedEx account number', 'woo-shipping-labels'),
                    'required' => true
                ),
                'meter_number' => array(
                    'label' => __('Meter Number', 'woo-shipping-labels'),
                    'type' => 'text',
                    'description' => __('Your FedEx meter number', 'woo-shipping-labels'),
                    'required' => true
                ),
                'key' => array(
                    'label' => __('API Key', 'woo-shipping-labels'),
                    'type' => 'text',
                    'description' => __('Your FedEx API key', 'woo-shipping-labels'),
                    'required' => true
                ),
                'password' => array(
                    'label' => __('API Password', 'woo-shipping-labels'),
                    'type' => 'password',
                    'description' => __('Your FedEx API password', 'woo-shipping-labels'),
                    'required' => true
                ),
                'services' => array(
                    'label' => __('Services', 'woo-shipping-labels'),
                    'type' => 'multiselect',
                    'options' => array(
                        'FEDEX_GROUND' => __('FedEx Ground', 'woo-shipping-labels'),
                        'FEDEX_EXPRESS_SAVER' => __('FedEx Express Saver', 'woo-shipping-labels'),
                        'FEDEX_2_DAY' => __('FedEx 2Day', 'woo-shipping-labels'),
                        'PRIORITY_OVERNIGHT' => __('FedEx Priority Overnight', 'woo-shipping-labels'),
                        'STANDARD_OVERNIGHT' => __('FedEx Standard Overnight', 'woo-shipping-labels'),
                        'FIRST_OVERNIGHT' => __('FedEx First Overnight', 'woo-shipping-labels'),
                        'INTERNATIONAL_ECONOMY' => __('FedEx International Economy', 'woo-shipping-labels'),
                        'INTERNATIONAL_PRIORITY' => __('FedEx International Priority', 'woo-shipping-labels')
                    ),
                    'description' => __('Select the FedEx services you want to offer', 'woo-shipping-labels'),
                    'default' => array('FEDEX_GROUND')
                )
            );
        }
        
        // UPS settings
        else if ($carrier_id === 'ups') {
            $carrier_fields = array(
                'account_number' => array(
                    'label' => __('Account Number', 'woo-shipping-labels'),
                    'type' => 'text',
                    'description' => __('Your UPS account number', 'woo-shipping-labels'),
                    'required' => true
                ),
                'user_id' => array(
                    'label' => __('API User ID', 'woo-shipping-labels'),
                    'type' => 'text',
                    'description' => __('Your UPS API user ID', 'woo-shipping-labels'),
                    'required' => true
                ),
                'password' => array(
                    'label' => __('API Password', 'woo-shipping-labels'),
                    'type' => 'password',
                    'description' => __('Your UPS API password', 'woo-shipping-labels'),
                    'required' => true
                ),
                'access_license' => array(
                    'label' => __('Access License Number', 'woo-shipping-labels'),
                    'type' => 'text',
                    'description' => __('Your UPS access license number', 'woo-shipping-labels'),
                    'required' => true
                ),
                'services' => array(
                    'label' => __('Services', 'woo-shipping-labels'),
                    'type' => 'multiselect',
                    'options' => array(
                        '03' => __('UPS Ground', 'woo-shipping-labels'),
                        '02' => __('UPS 2nd Day Air', 'woo-shipping-labels'),
                        '01' => __('UPS Next Day Air', 'woo-shipping-labels'),
                        '12' => __('UPS 3 Day Select', 'woo-shipping-labels'),
                        '13' => __('UPS Next Day Air Saver', 'woo-shipping-labels'),
                        '14' => __('UPS Next Day Air Early', 'woo-shipping-labels'),
                        '11' => __('UPS Standard', 'woo-shipping-labels'),
                        '08' => __('UPS Worldwide Expedited', 'woo-shipping-labels'),
                        '07' => __('UPS Worldwide Express', 'woo-shipping-labels'),
                        '65' => __('UPS Worldwide Saver', 'woo-shipping-labels')
                    ),
                    'description' => __('Select the UPS services you want to offer', 'woo-shipping-labels'),
                    'default' => array('03')
                )
            );
        }
        
        // USPS settings
        else if ($carrier_id === 'usps') {
            $carrier_fields = array(
                'user_id' => array(
                    'label' => __('Web Tools User ID', 'woo-shipping-labels'),
                    'type' => 'text',
                    'description' => __('Your USPS Web Tools User ID', 'woo-shipping-labels'),
                    'required' => true
                ),
                'services' => array(
                    'label' => __('Services', 'woo-shipping-labels'),
                    'type' => 'multiselect',
                    'options' => array(
                        'PRIORITY' => __('Priority Mail', 'woo-shipping-labels'),
                        'PRIORITY_EXPRESS' => __('Priority Mail Express', 'woo-shipping-labels'),
                        'FIRST_CLASS' => __('First-Class Mail', 'woo-shipping-labels'),
                        'RETAIL_GROUND' => __('USPS Retail Ground', 'woo-shipping-labels'),
                        'MEDIA_MAIL' => __('Media Mail', 'woo-shipping-labels'),
                        'LIBRARY_MAIL' => __('Library Mail', 'woo-shipping-labels'),
                        'INTERNATIONAL_PRIORITY' => __('Priority Mail International', 'woo-shipping-labels'),
                        'INTERNATIONAL_EXPRESS' => __('Priority Mail Express International', 'woo-shipping-labels'),
                        'INTERNATIONAL_FIRST' => __('First-Class Package International', 'woo-shipping-labels')
                    ),
                    'description' => __('Select the USPS services you want to offer', 'woo-shipping-labels'),
                    'default' => array('PRIORITY')
                )
            );
        }
        
        // DHL settings
        else if ($carrier_id === 'dhl') {
            $carrier_fields = array(
                'site_id' => array(
                    'label' => __('Site ID', 'woo-shipping-labels'),
                    'type' => 'text',
                    'description' => __('Your DHL Site ID', 'woo-shipping-labels'),
                    'required' => true
                ),
                'password' => array(
                    'label' => __('Password', 'woo-shipping-labels'),
                    'type' => 'password',
                    'description' => __('Your DHL API password', 'woo-shipping-labels'),
                    'required' => true
                ),
                'account_number' => array(
                    'label' => __('Account Number', 'woo-shipping-labels'),
                    'type' => 'text',
                    'description' => __('Your DHL account number', 'woo-shipping-labels'),
                    'required' => true
                ),
                'services' => array(
                    'label' => __('Services', 'woo-shipping-labels'),
                    'type' => 'multiselect',
                    'options' => array(
                        'EXPRESS_WORLDWIDE' => __('DHL Express Worldwide', 'woo-shipping-labels'),
                        'EXPRESS_9:00' => __('DHL Express 9:00', 'woo-shipping-labels'),
                        'EXPRESS_10:30' => __('DHL Express 10:30', 'woo-shipping-labels'),
                        'EXPRESS_12:00' => __('DHL Express 12:00', 'woo-shipping-labels'),
                        'EXPRESS_ENVELOPE' => __('DHL Express Envelope', 'woo-shipping-labels'),
                        'EXPRESS_EASY' => __('DHL Express Easy', 'woo-shipping-labels'),
                        'ECONOMY_SELECT' => __('DHL Economy Select', 'woo-shipping-labels')
                    ),
                    'description' => __('Select the DHL services you want to offer', 'woo-shipping-labels'),
                    'default' => array('EXPRESS_WORLDWIDE')
                )
            );
        }
        
        // Merge common and carrier-specific fields
        $fields = array_merge($common_fields, $carrier_fields);
        
        // Add section heading
        echo '<h3>' . sprintf(__('%s Settings', 'woo-shipping-labels'), esc_html(ucfirst($carrier_id))) . '</h3>';
        echo '<div class="wsl-carrier-settings-section">';
        
        // Render fields
        foreach ($fields as $field_id => $field) {
            $field_name = "wsl_settings[{$carrier_id}][{$field_id}]";
            $field_id_attr = "wsl_settings_{$carrier_id}_{$field_id}";
            $field_value = isset($settings[$field_id]) ? $settings[$field_id] : (isset($field['default']) ? $field['default'] : '');
            $required = isset($field['required']) && $field['required'] ? 'required' : '';
            
            echo '<div class="wsl-form-field wsl-form-field-' . esc_attr($field['type']) . '">';
            echo '<label for="' . esc_attr($field_id_attr) . '">' . esc_html($field['label']) . '</label>';
            
            // Render different field types
            switch ($field['type']) {
                case 'text':
                case 'password':
                    echo '<input type="' . esc_attr($field['type']) . '" id="' . esc_attr($field_id_attr) . '" name="' . esc_attr($field_name) . '" value="' . esc_attr($field_value) . '" ' . $required . '>';
                    break;
                
                case 'checkbox':
                    $checked = ($field_value === 'yes') ? 'checked' : '';
                    echo '<input type="checkbox" id="' . esc_attr($field_id_attr) . '" name="' . esc_attr($field_name) . '" value="yes" ' . $checked . '>';
                    break;
                
                case 'select':
                    echo '<select id="' . esc_attr($field_id_attr) . '" name="' . esc_attr($field_name) . '" ' . $required . '>';
                    foreach ($field['options'] as $option_value => $option_label) {
                        $selected = ($field_value == $option_value) ? 'selected' : '';
                        echo '<option value="' . esc_attr($option_value) . '" ' . $selected . '>' . esc_html($option_label) . '</option>';
                    }
                    echo '</select>';
                    break;
                
                case 'multiselect':
                    // Convert string to array if needed
                    if (!is_array($field_value)) {
                        $field_value = empty($field_value) ? array() : explode(',', $field_value);
                    }
                    
                    echo '<select id="' . esc_attr($field_id_attr) . '" name="' . esc_attr($field_name) . '[]" multiple="multiple" class="wsl-multiselect" ' . $required . '>';
                    foreach ($field['options'] as $option_value => $option_label) {
                        $selected = in_array($option_value, $field_value) ? 'selected' : '';
                        echo '<option value="' . esc_attr($option_value) . '" ' . $selected . '>' . esc_html($option_label) . '</option>';
                    }
                    echo '</select>';
                    break;
            }
            
            // Field description
            if (isset($field['description'])) {
                echo '<p class="description">' . esc_html($field['description']) . '</p>';
            }
            
            echo '</div>'; // .wsl-form-field
        }
        
        echo '</div>'; // .wsl-carrier-settings-section
    }

    /**
     * Add JavaScript for the carriers tab
     */
    private function add_carriers_tab_scripts() {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Initialize multiselect
            $('.wsl-multiselect').select2({
                width: '100%',
                placeholder: '<?php _e('Select options', 'woo-shipping-labels'); ?>'
            });
            
            // Test connection button
            $('.wsl-test-connection').on('click', function() {
                var button = $(this);
                var resultSpan = button.siblings('.wsl-test-result');
                var carrier = button.data('carrier');
                
                // Disable button and show loading state
                button.prop('disabled', true);
                button.text('<?php _e('Testing...', 'woo-shipping-labels'); ?>');
                resultSpan.html('');
                
                // Collect the carrier settings
                var settings = {};
                $('[name^="wsl_settings[' + carrier + ']"]').each(function() {
                    var fieldName = $(this).attr('name').match(/\[([^\]]+)\]$/)[1];
                    settings[fieldName] = $(this).val();
                });
                
                // Make AJAX request to test connection
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wsl_test_carrier_connection',
                        carrier: carrier,
                        settings: settings,
                        nonce: '<?php echo wp_create_nonce('wsl_test_carrier_connection'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            resultSpan.html('<span class="wsl-success">' + response.data.message + '</span>');
                        } else {
                            resultSpan.html('<span class="wsl-error">' + response.data.message + '</span>');
                        }
                        
                        // Reset button state
                        button.prop('disabled', false);
                        button.text('<?php _e('Test Connection', 'woo-shipping-labels'); ?>');
                    },
                    error: function() {
                        resultSpan.html('<span class="wsl-error"><?php _e('Connection error. Please try again.', 'woo-shipping-labels'); ?></span>');
                        
                        // Reset button state
                        button.prop('disabled', false);
                        button.text('<?php _e('Test Connection', 'woo-shipping-labels'); ?>');
                    }
                });
            });
        });
        </script>
        <style type="text/css">
        .wsl-carrier-tabs {
            margin-bottom: 20px;
        }
        
        .wsl-carrier-icon {
            display: inline-block;
            vertical-align: middle;
            margin-right: 5px;
            width: 24px;
            height: 24px;
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
        }
        
        .wsl-icon-fedex {
            background-image: url('<?php echo plugin_dir_url(dirname(__FILE__)) . 'assets/images/fedex.png'; ?>');
        }
        
        .wsl-icon-ups {
            background-image: url('<?php echo plugin_dir_url(dirname(__FILE__)) . 'assets/images/ups.png'; ?>');
        }
        
        .wsl-icon-usps {
            background-image: url('<?php echo plugin_dir_url(dirname(__FILE__)) . 'assets/images/usps.png'; ?>');
        }
        
        .wsl-icon-dhl {
            background-image: url('<?php echo plugin_dir_url(dirname(__FILE__)) . 'assets/images/dhl.png'; ?>');
        }
        
        .wsl-carrier-settings-form {
            background: #fff;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
            padding: 20px;
        }
        
        .wsl-form-field {
            margin-bottom: 15px;
        }
        
        .wsl-form-field label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .wsl-form-field input[type="text"],
        .wsl-form-field input[type="password"],
        .wsl-form-field select {
            width: 100%;
            max-width: 400px;
        }
        
        .wsl-form-field-toggle {
            display: flex;
            align-items: center;
        }
        
        .wsl-form-field-toggle label {
            margin-right: 10px;
            margin-bottom: 0;
        }
        
        .wsl-toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }
        
        .wsl-toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .wsl-toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 24px;
        }
        
        .wsl-toggle-slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .wsl-toggle-slider {
            background-color: #2196F3;
        }
        
        input:checked + .wsl-toggle-slider:before {
            transform: translateX(26px);
        }
        
        .wsl-form-field-test-connection {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .wsl-test-result {
            display: inline-block;
            margin-left: 10px;
        }
        
        .wsl-success {
            color: #46b450;
        }
        
        .wsl-error {
            color: #dc3232;
        }
        
        .wsl-carrier-settings-section {
            margin-top: 15px;
        }
        </style>
        <?php
    }

    /**
     * Process carrier settings on save
     *
     * @param array $carrier_settings The carrier settings from the form
     * @return array Processed carrier settings
     */
    private function process_carrier_settings($carrier_settings) {
        $enabled_carriers = isset($_POST['wsl_enabled_carriers']) ? 
            array_map('sanitize_text_field', $_POST['wsl_enabled_carriers']) : array();
        
        // Update the enabled carriers option
        update_option('wsl_enabled_carriers', $enabled_carriers);
        
        // Process multiselect fields
        foreach ($carrier_settings as $carrier_id => &$settings) {
            if (isset($settings['services']) && is_array($settings['services'])) {
                $settings['services'] = implode(',', $settings['services']);
            }
        }
        
        return $carrier_settings;
    }

    /**
     * AJAX handler for address validation
     */
    public function ajax_validate_address() {
        // Check nonce for security
        check_ajax_referer('wsl_ajax_nonce', 'security');
        
        $address_type = isset($_POST['address_type']) ? sanitize_text_field($_POST['address_type']) : '';
        $address_data = isset($_POST['address_data']) ? $_POST['address_data'] : array();
        
        // Sanitize all address fields
        $sanitized_address = array();
        foreach ($address_data as $key => $value) {
            $sanitized_address[$key] = sanitize_text_field($value);
        }
        
        // Validate the address
        $validation_result = WSL_Address::validate_address($sanitized_address);
        
        // Return the result
        wp_send_json($validation_result);
    }
    
    /**
     * AJAX handler for formatting addresses
     */
    public function ajax_format_address() {
        // Check nonce for security
        check_ajax_referer('wsl_ajax_nonce', 'security');
        
        $address_data = isset($_POST['address_data']) ? $_POST['address_data'] : array();
        
        // Sanitize all address fields
        $sanitized_address = array();
        foreach ($address_data as $key => $value) {
            $sanitized_address[$key] = sanitize_text_field($value);
        }
        
        // Format the address as HTML
        $formatted_address = WSL_Address::format_address($sanitized_address);
        
        wp_send_json_success(array(
            'formatted_address' => $formatted_address
        ));
    }
    
    /**
     * AJAX handler for calculating shipping rates based on country mappings
     */
    public function ajax_calculate_shipping_rates() {
        // Check nonce for security
        check_ajax_referer('wsl_ajax_nonce', 'security');
        
        // Get package and address data
        $package_data = isset($_POST['package_data']) ? $_POST['package_data'] : array();
        $from_country = isset($_POST['from_country']) ? sanitize_text_field($_POST['from_country']) : '';
        $to_country = isset($_POST['to_country']) ? sanitize_text_field($_POST['to_country']) : '';
        
        // Normalize country codes to ensure consistent format (2-letter ISO codes)
        $from_country = WSL_Address::normalize_country_code($from_country);
        $to_country = WSL_Address::normalize_country_code($to_country);
        
        // Get country mappings from settings
        $country_mappings = get_option('wsl_country_carrier_services', array());
        $carrier_settings = get_option('wsl_carrier_settings', array());
        
        // Enhanced logging to troubleshoot the country code issues:
        WSL_Debug::log_api_data('country_code_debug', array(
            'raw_from_country' => $_POST['from_country'] ?? '',
            'raw_to_country' => $_POST['to_country'] ?? '',
            'normalized_from' => $from_country,
            'normalized_to' => $to_country,
            'country_mapping_keys' => array_map(function($m) {
                return [
                    'from' => $m['from_country'] ?? '',
                    'to' => $m['to_country'] ?? ''
                ];
            }, $country_mappings)
        ), 'request');
        
        // Available carriers with their logos and base rates
        $available_carriers = array(
            'fedex' => array(
                'name' => 'FedEx',
                'logo' => 'FedEx.png',
                'services' => array(
                    'ground' => array('name' => 'Ground', 'transit' => '3-5 days', 'base_rate' => 12.50),
                    'express' => array('name' => 'Express', 'transit' => '2 days', 'base_rate' => 18.25),
                    'priority' => array('name' => 'Priority Overnight', 'transit' => '1 day', 'base_rate' => 24.50),
                    'international_economy' => array('name' => 'International Economy', 'transit' => '4-6 days', 'base_rate' => 42.75),
                    'international_priority' => array('name' => 'International Priority', 'transit' => '2-3 days', 'base_rate' => 68.00),
                )
            ),
            'ups' => array(
                'name' => 'UPS',
                'logo' => 'ups.png',
                'services' => array(
                    'ground' => array('name' => 'Ground', 'transit' => '3-5 days', 'base_rate' => 13.25),
                    'next_day_air' => array('name' => 'Next Day Air', 'transit' => '1 day', 'base_rate' => 26.50),
                    'next_day_air_saver' => array('name' => 'Next Day Air Saver', 'transit' => '1 day (afternoon)', 'base_rate' => 22.75),
                    'worldwide_expedited' => array('name' => 'Worldwide Expedited', 'transit' => '3-5 days', 'base_rate' => 49.99),
                    'standard' => array('name' => 'Standard', 'transit' => '5-7 days', 'base_rate' => 32.50),
                )
            ),
            'dhl' => array(
                'name' => 'DHL',
                'logo' => 'DHL.png',
                'services' => array(
                    'express' => array('name' => 'Express', 'transit' => '1-2 days', 'base_rate' => 18.75),
                    'express_easy' => array('name' => 'Express Easy', 'transit' => '2-3 days', 'base_rate' => 16.25),
                    'economy_select' => array('name' => 'Economy Select', 'transit' => '3-5 days', 'base_rate' => 14.50),
                    'domestic' => array('name' => 'Domestic', 'transit' => '1-2 days', 'base_rate' => 9.99),
                )
            )
        );

        $matching_mapping = null;
        $available_shipping_options = array();
        
        // Find a mapping that matches our from/to countries
        foreach ($country_mappings as $mapping_id => $mapping) {
            $mapping_from = isset($mapping['from_country']) ? WSL_Address::normalize_country_code($mapping['from_country']) : '';
            $mapping_to = isset($mapping['to_country']) ? WSL_Address::normalize_country_code($mapping['to_country']) : '';
            
            // Direct comparison with normalized codes - no need for additional string manipulation
            if ($mapping_from === $from_country && $mapping_to === $to_country) {
                $matching_mapping = $mapping;
                break;
            }
            
            // Continue with wildcards as before with normalized codes
            if (($mapping_from === 'ANY' || $mapping_from === '*') && $mapping_to === $to_country) {
                $matching_mapping = $mapping;
            }
            
            if ($mapping_from === $from_country && ($mapping_to === 'ANY' || $mapping_to === '*')) {
                $matching_mapping = $mapping;
            }
            
            // Most general case - ANY to ANY
            if (($mapping_from === 'ANY' || $mapping_from === '*') && 
                ($mapping_to === 'ANY' || $mapping_to === '*') && 
                !$matching_mapping) {
                $matching_mapping = $mapping;
            }
        }
        
        // If we found a matching mapping, build available shipping options
        if ($matching_mapping) {
            $carriers = $matching_mapping['carriers'] ?? array();
            
            foreach ($carriers as $carrier_id) {
                $services = $matching_mapping['services'][$carrier_id] ?? array();
                
                foreach ($services as $service_id) {
                    // Check if this service is enabled in carrier settings
                    $service_key = $carrier_id . '_' . $service_id . '_enabled';
                    if (!isset($carrier_settings[$service_key]) || $carrier_settings[$service_key] != 1) {
                        continue; // Skip disabled services
                    }
                    
                    // Get markup percentage for this service
                    $markup_key = $carrier_id . '_' . $service_id . '_markup';
                    $markup_percent = isset($carrier_settings[$markup_key]) ? (float)$carrier_settings[$markup_key] : 0;
                    
                    // Get base rate for this service
                    $base_rate = $available_carriers[$carrier_id]['services'][$service_id]['base_rate'] ?? 0;
                    
                    // Apply markup
                    $rate = $base_rate * (1 + ($markup_percent / 100));
                    
                    // Calculate package-specific rates (weight surcharges, etc.)
                    if (!empty($package_data['weight'])) {
                        $weight = (float)$package_data['weight'];
                        if ($weight > 10) {
                            $rate += ($weight - 10) * 0.50; // $0.50 per kg over 10kg
                        }
                    }
                    
                    // Add to available options
                    $available_shipping_options[] = array(
                        'carrier_id' => $carrier_id,
                        'service_id' => $service_id,
                        'carrier_name' => $available_carriers[$carrier_id]['name'],
                        'carrier_logo' => $available_carriers[$carrier_id]['logo'],
                        'service_name' => $available_carriers[$carrier_id]['services'][$service_id]['name'],
                        'transit_time' => $available_carriers[$carrier_id]['services'][$service_id]['transit'],
                        'rate' => number_format($rate, 2)
                    );
                }
            }
        }
        
        // If debug mode is on, log this request
        if (isset($carrier_settings['debug_mode']) && $carrier_settings['debug_mode'] == 1) {
            WSL_Debug::log_api_data('rate_calculation', array(
                'from_country' => $from_country,
                'to_country' => $to_country,
                'package_data' => $package_data,
                'matching_mapping' => $matching_mapping,
                'available_shipping_options' => $available_shipping_options
            ), 'request');
        }
        
        // Return the shipping options
        wp_send_json_success(array(
            'shipping_options' => $available_shipping_options
        ));
    }
    
    /**
     * AJAX handler for generating a FedEx shipping label
     */
    public function ajax_generate_fedex_label() {
        // Check nonce for security
        check_ajax_referer('wsl_ajax_nonce', 'security');
        
        // Get shipment data
        $from_address = isset($_POST['from_address']) ? $_POST['from_address'] : array();
        $to_address = isset($_POST['to_address']) ? $_POST['to_address'] : array();
        $package_data = isset($_POST['package_data']) ? $_POST['package_data'] : array();
        $selected_service = isset($_POST['service']) ? sanitize_text_field($_POST['service']) : '';
        
        // Sanitize all inputs
        $from_address = $this->sanitize_recursive($from_address);
        $to_address = $this->sanitize_recursive($to_address);
        $package_data = $this->sanitize_recursive($package_data);
        
        // Split selected service into carrier and service
        $service_parts = explode('_', $selected_service, 2);
        if (count($service_parts) !== 2 || $service_parts[0] !== 'fedex') {
            wp_send_json_error(array(
                'message' => __('Invalid service selected', 'woo-shipping-labels')
            ));
            return;
        }
        
        $service_id = $service_parts[1];
        
        // Convert internal service ID to FedEx service code
        $service_code = WSL_FedEx_Ship::get_service_code($service_id);
        
        // Initialize FedEx Ship API
        $fedex_ship = new WSL_FedEx_Ship();
        
        // Prepare package data format
        $formatted_package = array(
            'weight' => $package_data['weight'],
            'weight_unit' => 'LB',
            'length' => $package_data['length'] ?? null,
            'width' => $package_data['width'] ?? null,
            'height' => $package_data['height'] ?? null,
            'dimension_unit' => 'IN'
        );
        
        // Check if this is an international shipment
        $is_international = $from_address['country'] !== $to_address['country'];
        
        // Prepare customs data for international shipments
        $customs_data = null;
        if ($is_international) {
            $customs_value = isset($_POST['customs_value']) ? floatval($_POST['customs_value']) : 100;
            
            $customs_data = array(
                'currency' => 'USD',
                'total_value' => $customs_value,
                'items' => array(
                    array(
                        'description' => 'Merchandise',
                        'quantity' => 1,
                        'value' => $customs_value,
                        'weight' => $package_data['weight'],
                        'country_of_origin' => $from_address['country'],
                        'harmonized_code' => '000000' // Default HS code
                    )
                )
            );
        }
        
        // Build shipment data
        $shipment_data = array(
            'from_address' => $from_address,
            'to_address' => $to_address,
            'package' => $formatted_package,
            'service_type' => $service_code,
            'package_type' => 'YOUR_PACKAGING',
            'signature_option' => 'DIRECT', // Options: DIRECT, INDIRECT, ADULT, or empty for no signature
        );
        
        // Add customs data for international shipments
        if ($is_international && $customs_data) {
            $shipment_data['customs'] = $customs_data;
        }
        
        // Create the label
        $label_result = $fedex_ship->create_shipment($shipment_data);
        
        if ($label_result['success']) {
            // If label was created successfully, save to the order if order_id is provided
            $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
            
            if ($order_id > 0) {
                $order = wc_get_order($order_id);
                if ($order) {
                    // Save tracking info to order meta
                    $order->update_meta_data('_wsl_tracking_number', $label_result['tracking_number']);
                    $order->update_meta_data('_wsl_shipping_label_path', $label_result['label_path']);
                    $order->update_meta_data('_wsl_shipping_label_url', $label_result['label_url']);
                    $order->update_meta_data('_wsl_shipping_service', $label_result['service_type']);
                    $order->update_meta_data('_wsl_shipping_carrier', 'fedex');
                    $order->save();
                    
                    // Add order note
                    $order->add_order_note(
                        sprintf(
                            __('Shipping label generated. Carrier: FedEx, Service: %s, Tracking number: %s', 'woo-shipping-labels'),
                            WSL_FedEx_Ship::get_service_name($label_result['service_type']),
                            $label_result['tracking_number']
                        )
                    );
                }
            }
            
            wp_send_json_success($label_result);
        } else {
            wp_send_json_error($label_result);
        }
    }
    
    /**
     * Display the FedEx Ship API test page
     */
    public function render_fedex_ship_test_page() {
        // Ensure user has sufficient permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        // Include necessary files
        require_once WSL_PLUGIN_DIR . 'includes/api/class-fedex-auth.php';
        require_once WSL_PLUGIN_DIR . 'includes/api/class-fedex-ship.php';
        require_once WSL_PLUGIN_DIR . 'includes/class-debug.php';
        
        // Test data - domestic shipment
        $test_data = array(
            'from_address' => array(
                'name' => 'John Smith',
                'company' => 'Test Sender',
                'address_1' => '123 Shipper St',
                'address_2' => '',
                'city' => 'Memphis',
                'state' => 'TN',
                'postcode' => '38116',
                'country' => 'US',
                'phone' => '5555555555',
                'email' => 'test@example.com'
            ),
            'to_address' => array(
                'name' => 'Jane Doe',
                'company' => 'Test Recipient',
                'address_1' => '456 Recipient Ave',
                'address_2' => '',
                'city' => 'Atlanta',
                'state' => 'GA',
                'postcode' => '30339',
                'country' => 'US',
                'phone' => '5555555555',
                'email' => 'recipient@example.com',
                'residential' => true
            ),
            'package' => array(
                'weight' => 5,
                'weight_unit' => 'LB',
                'length' => 12,
                'width' => 10,
                'height' => 8,
                'dimension_unit' => 'IN'
            ),
            'service_type' => 'FEDEX_GROUND',
            'package_type' => 'YOUR_PACKAGING'
        );
        
        // Sample file path
        $sample_file_path = ABSPATH . 'wp-content/samples/ship-request/ship_request.json';
        $sample_file_exists = file_exists($sample_file_path);
        
        // Create instance of FedEx Ship API handler
        $fedex_ship = new WSL_FedEx_Ship();
        
        // Display test page
        echo '<div class="wrap">';
        echo '<h1>FedEx Ship API Test</h1>';
        
        if ($sample_file_exists) {
            echo '<div class="notice notice-info">';
            echo '<p>Sample request file found at: <code>' . esc_html($sample_file_path) . '</code></p>';
            echo '</div>';
        } else {
            echo '<div class="notice notice-warning">';
            echo '<p>Sample request file not found at: <code>' . esc_html($sample_file_path) . '</code></p>';
            echo '</div>';
        }
        
        echo '<form method="post">';
        echo '<p>';
        
        if ($sample_file_exists) {
            echo '<label><input type="radio" name="payload_source" value="sample" checked> Use sample file</label><br>';
            echo '<label><input type="radio" name="payload_source" value="generated"> Use generated payload</label><br><br>';
        }
        
        echo '<label><input type="checkbox" name="debug_mode" value="1"> Show debug info only (don\'t send API request)</label><br><br>';
        echo '<input type="submit" name="test_fedex_ship" value="Create Test Label" class="button button-primary">';
        echo '</p>';
        echo '</form>';
        
        // Process test if button clicked
        if (isset($_POST['test_fedex_ship'])) {
            // Check if debug mode is enabled
            $debug_mode = isset($_POST['debug_mode']) && $_POST['debug_mode'] == '1';
            
            // Determine if we should use the sample file
            $use_sample = $sample_file_exists && 
                        (!isset($_POST['payload_source']) || $_POST['payload_source'] === 'sample');
            
            if ($use_sample) {
                // Load and display the sample payload
                $payload = $fedex_ship->load_sample_payload($sample_file_path);
                
                echo '<h2>Sample Request Payload:</h2>';
                echo '<pre style="background:#f5f5f5;padding:15px;overflow:auto;max-height:400px;">';
                echo htmlspecialchars(json_encode($payload, JSON_PRETTY_PRINT));
                echo '</pre>';
                
                // Only send the request if not in debug mode
                if (!$debug_mode) {
                    echo '<h2>API Response:</h2>';
                    echo '<pre>';
                    $result = $fedex_ship->create_shipment_from_sample($sample_file_path);
                    var_dump($result);
                    echo '</pre>';
                    
                    if (isset($result['success']) && $result['success'] && isset($result['label_url'])) {
                        echo '<h3>Label Generated</h3>';
                        echo '<p><a href="' . esc_url($result['label_url']) . '" target="_blank">View Label</a></p>';
                        echo '<p>Tracking Number: ' . esc_html($result['tracking_number']) . '</p>';
                    }
                }
            } else {
                // Use the generated payload
                $payload = $fedex_ship->prepare_test_payload($test_data);
                
                echo '<h2>Generated Request Payload:</h2>';
                echo '<pre style="background:#f5f5f5;padding:15px;overflow:auto;max-height:400px;">';
                echo htmlspecialchars(json_encode($payload, JSON_PRETTY_PRINT));
                echo '</pre>';
                
                // Only send the request if not in debug mode
                if (!$debug_mode) {
                    echo '<h2>API Response:</h2>';
                    echo '<pre>';
                    $result = $fedex_ship->create_shipment($test_data);
                    var_dump($result);
                    echo '</pre>';
                    
                    if (isset($result['success']) && $result['success'] && isset($result['label_url'])) {
                        echo '<h3>Label Generated</h3>';
                        echo '<p><a href="' . esc_url($result['label_url']) . '" target="_blank">View Label</a></p>';
                        echo '<p>Tracking Number: ' . esc_html($result['tracking_number']) . '</p>';
                    }
                }
            }
        }
        
        echo '</div>';
    }
    
    /**
     * AJAX handler for testing carrier connections
     */
    public function ajax_test_carrier_connection() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wsl_test_carrier_connection')) {
            wp_send_json_error(array('message' => __('Security check failed', 'woo-shipping-labels')));
        }
        
        // Verify user capabilities
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action', 'woo-shipping-labels')));
        }
        
        // Get the carrier and settings
        $carrier_id = isset($_POST['carrier']) ? sanitize_text_field($_POST['carrier']) : '';
        $settings = isset($_POST['settings']) ? $this->sanitize_recursive($_POST['settings']) : array();
        
        if (empty($carrier_id)) {
            wp_send_json_error(array('message' => __('Carrier not specified', 'woo-shipping-labels')));
        }
        
        // Include carrier manager
        include_once plugin_dir_path(__FILE__) . 'class-carrier-manager.php';
        $carrier_manager = WSL_Carrier_Manager::get_instance();
        
        // Load the carrier
        $carrier = $carrier_manager->get_carrier($carrier_id);
        
        if (!$carrier) {
            wp_send_json_error(array('message' => sprintf(__('Carrier %s is not available', 'woo-shipping-labels'), $carrier_id)));
        }
        
        // Temporarily apply the test settings
        $carrier->update_settings($settings);
        
        // Test the connection
        $test_result = $carrier->test_connection();
        
        if ($test_result['success']) {
            wp_send_json_success(array('message' => $test_result['message']));
        } else {
            wp_send_json_error(array('message' => $test_result['error']));
        }
    }
    
    /**
     * Recursively sanitize an array of values
     *
     * @param mixed $data Data to sanitize
     * @return mixed Sanitized data
     */
    private function sanitize_recursive($data) {
        if (!is_array($data)) {
            return sanitize_text_field($data);
        }
        
        $sanitized = array();
        foreach ($data as $key => $value) {
            $sanitized[$key] = is_array($value) ? $this->sanitize_recursive($value) : sanitize_text_field($value);
        }
        
        return $sanitized;
    }

    /**
     * Render general tab content
     */
    private function render_general_tab() {
        // Get saved settings
        $general_settings = get_option('wsl_general_settings', array(
            'default_weight_unit' => 'lb',
            'default_dimension_unit' => 'in',
            'default_shipment_type' => 'package',
            'store_name' => get_bloginfo('name'),
            'store_phone' => '',
            'store_email' => get_bloginfo('admin_email'),
        ));
        
        // Default address
        $default_address = get_option('wsl_store_address', array(
            'address_1' => WC()->countries->get_base_address(),
            'address_2' => WC()->countries->get_base_address_2(),
            'city' => WC()->countries->get_base_city(),
            'state' => WC()->countries->get_base_state(),
            'postcode' => WC()->countries->get_base_postcode(),
            'country' => WC()->countries->get_base_country(),
            'company' => get_bloginfo('name'),
        ));
        
        // Define general tab sections/submenus
        $sections = array(
            'store_info' => __('Store Information', 'woo-shipping-labels'),
            'store_address' => __('Store Address', 'woo-shipping-labels'),
            'default_units' => __('Default Units', 'woo-shipping-labels'),
            'currencies' => __('Currency Management', 'woo-shipping-labels'),
        );
        
        // Get current section
        $current_section = isset($_GET['section']) ? sanitize_text_field($_GET['section']) : 'store_info';
        
        // Start general tab content
        echo '<div class="wsl-general-settings">';
        
        // Display submenu navigation
        echo '<div class="wsl-general-subnav">';
        echo '<ul class="subsubsub">';
        $total_sections = count($sections);
        $i = 0;
        foreach ($sections as $section_id => $section_label) {
            $i++;
            $section_url = add_query_arg(array(
                'page' => 'wsl-settings',
                'tab' => 'general',
                'section' => $section_id
            ), admin_url('admin.php'));
            
            $active_class = ($current_section === $section_id) ? 'current' : '';
            $separator = ($i < $total_sections) ? ' | ' : '';
            
            echo '<li>';
            echo '<a href="' . esc_url($section_url) . '" class="' . esc_attr($active_class) . '">' . 
                 esc_html($section_label) . '</a>' . $separator;
            echo '</li>';
        }
        echo '</ul>';
        echo '</div>'; // .wsl-general-subnav
        
        // Display current section content
        echo '<div class="wsl-section-content">';
        
        switch ($current_section) {
            case 'store_address':
                $this->render_store_address_section($default_address);
                break;
                
            case 'default_units':
                $this->render_default_units_section($general_settings);
                break;
                
            case 'currencies':
                $this->render_currencies_section();
                break;
                
            default: // store_info
                $this->render_store_info_section($general_settings);
                break;
        }
        
        echo '</div>'; // .wsl-section-content
        echo '</div>'; // .wsl-general-settings
        
        // Add some custom styling for the submenu
        ?>
        <style>
        .wsl-general-subnav {
            margin-bottom: 20px;
            border-bottom: 1px solid #ccd0d4;
        }
        .wsl-section-content {
            background: #fff;
            padding: 20px;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        .wsl-settings-section h2:first-child {
            margin-top: 0;
        }
        </style>
        <?php
    }

    /**
     * Render Store Information section
     * 
     * @param array $general_settings General settings array
     */
    private function render_store_info_section($general_settings) {
        echo '<div class="wsl-settings-section">';
        echo '<h2>' . __('Store Information', 'woo-shipping-labels') . '</h2>';
        echo '<p class="description">' . __('Set your store details for shipping labels and documents.', 'woo-shipping-labels') . '</p>';
        
        // Store Name
        echo '<div class="wsl-form-field">';
        echo '<label for="wsl_settings_store_name">' . __('Store Name', 'woo-shipping-labels') . '</label>';
        echo '<input type="text" id="wsl_settings_store_name" name="wsl_general_settings[store_name]" value="' . esc_attr($general_settings['store_name']) . '">';
        echo '<p class="description">' . __('Your store name for shipping labels', 'woo-shipping-labels') . '</p>';
        echo '</div>';
        
        // Store Phone
        echo '<div class="wsl-form-field">';
        echo '<label for="wsl_settings_store_phone">' . __('Store Phone', 'woo-shipping-labels') . '</label>';
        echo '<input type="text" id="wsl_settings_store_phone" name="wsl_general_settings[store_phone]" value="' . esc_attr($general_settings['store_phone']) . '">';
        echo '<p class="description">' . __('Your store phone for shipping labels', 'woo-shipping-labels') . '</p>';
        echo '</div>';
        
        // Store Email
        echo '<div class="wsl-form-field">';
        echo '<label for="wsl_settings_store_email">' . __('Store Email', 'woo-shipping-labels') . '</label>';
        echo '<input type="email" id="wsl_settings_store_email" name="wsl_general_settings[store_email]" value="' . esc_attr($general_settings['store_email']) . '">';
        echo '<p class="description">' . __('Your store email for shipping labels', 'woo-shipping-labels') . '</p>';
        echo '</div>';
        
        echo '</div>'; // .wsl-settings-section
    }

    /**
     * Render Store Address section
     * 
     * @param array $default_address Default address array
     */
    private function render_store_address_section($default_address) {
        echo '<div class="wsl-settings-section">';
        echo '<h2>' . __('Store Address', 'woo-shipping-labels') . '</h2>';
        echo '<p class="description">' . __('This address will be used as the default "Ship From" address on your labels.', 'woo-shipping-labels') . '</p>';
        
        // Company
        echo '<div class="wsl-form-field">';
        echo '<label for="wsl_settings_company">' . __('Company', 'woo-shipping-labels') . '</label>';
        echo '<input type="text" id="wsl_settings_company" name="wsl_store_address[company]" value="' . esc_attr($default_address['company']) . '">';
        echo '</div>';
        
        // Address Line 1
        echo '<div class="wsl-form-field">';
        echo '<label for="wsl_settings_address_1">' . __('Address Line 1', 'woo-shipping-labels') . '</label>';
        echo '<input type="text" id="wsl_settings_address_1" name="wsl_store_address[address_1]" value="' . esc_attr($default_address['address_1']) . '">';
        echo '</div>';
        
        // Address Line 2
        echo '<div class="wsl-form-field">';
        echo '<label for="wsl_settings_address_2">' . __('Address Line 2', 'woo-shipping-labels') . ' <span class="optional">(' . __('optional', 'woo-shipping-labels') . ')</span></label>';
        echo '<input type="text" id="wsl_settings_address_2" name="wsl_store_address[address_2]" value="' . esc_attr($default_address['address_2']) . '">';
        echo '</div>';
        
        // City
        echo '<div class="wsl-form-field">';
        echo '<label for="wsl_settings_city">' . __('City', 'woo-shipping-labels') . '</label>';
        echo '<input type="text" id="wsl_settings_city" name="wsl_store_address[city]" value="' . esc_attr($default_address['city']) . '">';
        echo '</div>';
        
        // State/Province
        echo '<div class="wsl-form-field">';
        echo '<label for="wsl_settings_state">' . __('State/Province', 'woo-shipping-labels') . '</label>';
        echo '<input type="text" id="wsl_settings_state" name="wsl_store_address[state]" value="' . esc_attr($default_address['state']) . '">';
        echo '</div>';
        
        // Postal Code
        echo '<div class="wsl-form-field">';
        echo '<label for="wsl_settings_postcode">' . __('Postal Code', 'woo-shipping-labels') . '</label>';
        echo '<input type="text" id="wsl_settings_postcode" name="wsl_store_address[postcode]" value="' . esc_attr($default_address['postcode']) . '">';
        echo '</div>';
        
        // Country
        echo '<div class="wsl-form-field">';
        echo '<label for="wsl_settings_country">' . __('Country', 'woo-shipping-labels') . '</label>';
        echo '<select id="wsl_settings_country" name="wsl_store_address[country]">';
        
        $countries = WC()->countries->get_countries();
        foreach ($countries as $code => $name) {
            echo '<option value="' . esc_attr($code) . '" ' . selected($code, $default_address['country'], false) . '>' . esc_html($name) . '</option>';
        }
        
        echo '</select>';
        echo '</div>';
        
        echo '</div>'; // .wsl-settings-section
    }

    /**
     * Render Default Units section
     * 
     * @param array $general_settings General settings array
     */
    private function render_default_units_section($general_settings) {
        echo '<div class="wsl-settings-section">';
        echo '<h2>' . __('Default Units', 'woo-shipping-labels') . '</h2>';
        echo '<p class="description">' . __('Configure default units for your shipping labels.', 'woo-shipping-labels') . '</p>';
        
        // Weight Unit
        echo '<div class="wsl-form-field">';
        echo '<label for="wsl_settings_weight_unit">' . __('Weight Unit', 'woo-shipping-labels') . '</label>';
        echo '<select id="wsl_settings_weight_unit" name="wsl_general_settings[default_weight_unit]">';
        echo '<option value="lb" ' . selected('lb', $general_settings['default_weight_unit'], false) . '>' . __('Pounds (lb)', 'woo-shipping-labels') . '</option>';
        echo '<option value="kg" ' . selected('kg', $general_settings['default_weight_unit'], false) . '>' . __('Kilograms (kg)', 'woo-shipping-labels') . '</option>';
        echo '<option value="oz" ' . selected('oz', $general_settings['default_weight_unit'], false) . '>' . __('Ounces (oz)', 'woo-shipping-labels') . '</option>';
        echo '<option value="g" ' . selected('g', $general_settings['default_weight_unit'], false) . '>' . __('Grams (g)', 'woo-shipping-labels') . '</option>';
        echo '</select>';
        echo '<p class="description">' . __('Default unit for package weights', 'woo-shipping-labels') . '</p>';
        echo '</div>';
        
        // Dimension Unit
        echo '<div class="wsl-form-field">';
        echo '<label for="wsl_settings_dimension_unit">' . __('Dimension Unit', 'woo-shipping-labels') . '</label>';
        echo '<select id="wsl_settings_dimension_unit" name="wsl_general_settings[default_dimension_unit]">';
        echo '<option value="in" ' . selected('in', $general_settings['default_dimension_unit'], false) . '>' . __('Inches (in)', 'woo-shipping-labels') . '</option>';
        echo '<option value="cm" ' . selected('cm', $general_settings['default_dimension_unit'], false) . '>' . __('Centimeters (cm)', 'woo-shipping-labels') . '</option>';
        echo '<option value="mm" ' . selected('mm', $general_settings['default_dimension_unit'], false) . '>' . __('Millimeters (mm)', 'woo-shipping-labels') . '</option>';
        echo '</select>';
        echo '<p class="description">' . __('Default unit for package dimensions', 'woo-shipping-labels') . '</p>';
        echo '</div>';
        
        // Default Shipment Type
        echo '<div class="wsl-form-field">';
        echo '<label for="wsl_settings_shipment_type">' . __('Default Shipment Type', 'woo-shipping-labels') . '</label>';
        echo '<select id="wsl_settings_shipment_type" name="wsl_general_settings[default_shipment_type]">';
        echo '<option value="package" ' . selected('package', $general_settings['default_shipment_type'], false) . '>' . __('Package', 'woo-shipping-labels') . '</option>';
        echo '<option value="envelope" ' . selected('envelope', $general_settings['default_shipment_type'], false) . '>' . __('Envelope', 'woo-shipping-labels') . '</option>';
        echo '<option value="letter" ' . selected('letter', $general_settings['default_shipment_type'], false) . '>' . __('Letter', 'woo-shipping-labels') . '</option>';
        echo '</select>';
        echo '<p class="description">' . __('Default type of shipment', 'woo-shipping-labels') . '</p>';
        echo '</div>';
        
        echo '</div>'; // .wsl-settings-section
    }

    /**
     * Render Currency Management section
     */
    private function render_currencies_section() {
        // Get saved currencies
        $currencies = get_option('wsl_currencies', array());
        
        echo '<div class="wsl-settings-section wsl-currencies-section">';
        echo '<h2>' . __('Currency Management', 'woo-shipping-labels') . '</h2>';
        echo '<p class="description">' . __('Configure the currencies available for shipping labels', 'woo-shipping-labels') . '</p>';
        
        // Currency Table
        echo '<table class="widefat wsl-currency-table">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . __('Code', 'woo-shipping-labels') . '</th>';
        echo '<th>' . __('Name', 'woo-shipping-labels') . '</th>';
        echo '<th>' . __('Enabled', 'woo-shipping-labels') . '</th>';
        echo '<th>' . __('Default', 'woo-shipping-labels') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody id="wsl-currencies-list">';
        
        // Display all currencies
        if (!empty($currencies)) {
            foreach ($currencies as $code => $currency) {
                echo '<tr data-code="' . esc_attr($code) . '">';
                echo '<td>';
                echo '<input type="hidden" name="wsl_currencies[' . esc_attr($code) . '][code]" value="' . esc_attr($code) . '">';
                echo esc_html($code);
                echo '</td>';
                echo '<td>';
                echo '<input type="text" name="wsl_currencies[' . esc_attr($code) . '][name]" value="' . esc_attr($currency['name']) . '">';
                echo '</td>';
                echo '<td>';
                echo '<input type="checkbox" name="wsl_currencies[' . esc_attr($code) . '][enabled]" value="1" ' . checked(isset($currency['enabled']) && $currency['enabled'], true, false) . '>';
                echo '</td>';
                echo '<td>';
                echo '<input type="radio" name="wsl_default_currency" value="' . esc_attr($code) . '" ' . checked(isset($currency['default']) && $currency['default'], true, false) . '>';
                echo '</td>';
                echo '</tr>';
            }
        } else {
            // If no currencies exist, add some common ones
            $default_currencies = array(
                'USD' => array('name' => 'US Dollar', 'default' => true),
                'EUR' => array('name' => 'Euro'),
                'GBP' => array('name' => 'British Pound'),
                'CAD' => array('name' => 'Canadian Dollar'),
                'AUD' => array('name' => 'Australian Dollar'),
            );
            
            foreach ($default_currencies as $code => $currency) {
                $is_default = isset($currency['default']) && $currency['default'];
                
                echo '<tr data-code="' . esc_attr($code) . '">';
                echo '<td>';
                echo '<input type="hidden" name="wsl_currencies[' . esc_attr($code) . '][code]" value="' . esc_attr($code) . '">';
                echo esc_html($code);
                echo '</td>';
                echo '<td>';
                echo '<input type="text" name="wsl_currencies[' . esc_attr($code) . '][name]" value="' . esc_attr($currency['name']) . '">';
                echo '</td>';
                echo '<td>';
                echo '<input type="checkbox" name="wsl_currencies[' . esc_attr($code) . '][enabled]" value="1" ' . checked($is_default, true, false) . '>';
                echo '</td>';
                echo '<td>';
                echo '<input type="radio" name="wsl_default_currency" value="' . esc_attr($code) . '" ' . checked($is_default, true, false) . '>';
                echo '</td>';
                echo '</tr>';
            }
        }
        
        echo '</tbody>';
        echo '</table>';
        
        // Add Currency button
        echo '<div class="wsl-currency-actions">';
        echo '<button type="button" class="button wsl-add-currency">' . __('Add Currency', 'woo-shipping-labels') . '</button>';
        echo '</div>';
        
        // Add Currency Modal
        echo '<div id="wsl-add-currency-modal" class="wsl-modal">';
        echo '<div class="wsl-modal-content">';
        echo '<div class="wsl-modal-header">';
        echo '<span class="wsl-modal-close">&times;</span>';
        echo '<h2>' . __('Add New Currency', 'woo-shipping-labels') . '</h2>';
        echo '</div>';
        echo '<div class="wsl-modal-body">';
        
        // Currency Code
        echo '<div class="wsl-form-field">';
        echo '<label for="wsl-new-currency-code">' . __('Currency Code', 'woo-shipping-labels') . '</label>';
        echo '<input type="text" id="wsl-new-currency-code" maxlength="3" placeholder="USD" />';
        echo '<p class="description">' . __('3-letter code (e.g., USD, EUR, GBP)', 'woo-shipping-labels') . '</p>';
        echo '</div>';
        
        // Currency Name
        echo '<div class="wsl-form-field">';
        echo '<label for="wsl-new-currency-name">' . __('Currency Name', 'woo-shipping-labels') . '</label>';
        echo '<input type="text" id="wsl-new-currency-name" placeholder="United States Dollar" />';
        echo '<p class="description">' . __('Full name of the currency', 'woo-shipping-labels') . '</p>';
        echo '</div>';
        
        // Enable Currency
        echo '<div class="wsl-form-field">';
        echo '<label for="wsl-new-currency-enabled">' . __('Enable Currency', 'woo-shipping-labels') . '</label>';
        echo '<input type="checkbox" id="wsl-new-currency-enabled" value="1" />';
        echo '<p class="description">' . __('Enable this currency for shipping labels', 'woo-shipping-labels') . '</p>';
        echo '</div>';
        
        // Default Currency
        echo '<div class="wsl-form-field">';
        echo '<label for="wsl-new-currency-default">' . __('Default Currency', 'woo-shipping-labels') . '</label>';
        echo '<input type="checkbox" id="wsl-new-currency-default" value="1" />';
        echo '<p class="description">' . __('Set as default currency for shipping labels', 'woo-shipping-labels') . '</p>';
        echo '</div>';
        
        echo '<div class="wsl-error-message"></div>';
        
        echo '</div>'; // .wsl-modal-body
        echo '<div class="wsl-modal-footer">';
        echo '<button type="button" class="button button-primary wsl-save-currency">' . __('Add Currency', 'woo-shipping-labels') . '</button>';
        echo '<button type="button" class="button wsl-cancel-currency">' . __('Cancel', 'woo-shipping-labels') . '</button>';
        echo '</div>'; // .wsl-modal-footer
        echo '</div>'; // .wsl-modal-content
        echo '</div>'; // #wsl-add-currency-modal
        
        // JavaScript for the modal and "Add Currency" functionality
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Open modal when "Add Currency" button is clicked
            $('.wsl-add-currency').on('click', function() {
                // Reset form fields
                $('#wsl-new-currency-code').val('');
                $('#wsl-new-currency-name').val('');
                $('#wsl-new-currency-enabled').prop('checked', false);
                $('#wsl-new-currency-default').prop('checked', false);
                $('.wsl-error-message').hide().html('');
                
                // Open modal
                $('#wsl-add-currency-modal').show();
            });
            
            // Close modal when "X" is clicked
            $('.wsl-modal-close').on('click', function() {
                $('#wsl-add-currency-modal').hide();
            });
            
            // Close modal when "Cancel" button is clicked
            $('.wsl-cancel-currency').on('click', function() {
                $('#wsl-add-currency-modal').hide();
            });
            
            // Close modal when clicking outside of it
            $(window).on('click', function(event) {
                if ($(event.target).is('#wsl-add-currency-modal')) {
                    $('#wsl-add-currency-modal').hide();
                }
            });
            
            // Save currency when "Add Currency" button in modal is clicked
            $('.wsl-save-currency').on('click', function() {
                // Get form values
                var code = $('#wsl-new-currency-code').val().toUpperCase();
                var name = $('#wsl-new-currency-name').val();
                var enabled = $('#wsl-new-currency-enabled').is(':checked');
                var isDefault = $('#wsl-new-currency-default').is(':checked');
                
                // Validate form
                var errors = [];
                
                if (!code) {
                    errors.push('Currency code is required');
                } else if (code.length !== 3) {
                    errors.push('Currency code must be exactly 3 letters');
                }
                
                if (!name) {
                    errors.push('Currency name is required');
                }
                
                // Check if currency already exists
                if ($('#wsl-currencies-list tr[data-code="' + code + '"]').length) {
                    errors.push('This currency already exists');
                }
                
                // Display errors if any
                if (errors.length > 0) {
                    var errorHtml = '<ul>';
                    $.each(errors, function(index, error) {
                        errorHtml += '<li>' + error + '</li>';
                    });
                    errorHtml += '</ul>';
                    
                    $('.wsl-error-message').html(errorHtml).show();
                    return;
                }
                
                // Add a new row to the table
                var newRow = '<tr data-code="' + code + '">';
                newRow += '<td>';
                newRow += '<input type="hidden" name="wsl_currencies[' + code + '][code]" value="' + code + '">';
                newRow += code;
                newRow += '</td>';
                newRow += '<td>';
                newRow += '<input type="text" name="wsl_currencies[' + code + '][name]" value="' + name + '">';
                newRow += '</td>';
                newRow += '<td>';
                newRow += '<input type="checkbox" name="wsl_currencies[' + code + '][enabled]" value="1"' + (enabled ? ' checked' : '') + '>';
                newRow += '</td>';
                newRow += '<td>';
                
                // If setting as default, uncheck all other defaults
                if (isDefault) {
                    $('#wsl-currencies-list input[name="wsl_default_currency"]').prop('checked', false);
                    newRow += '<input type="radio" name="wsl_default_currency" value="' + code + '" checked>';
                } else {
                    newRow += '<input type="radio" name="wsl_default_currency" value="' + code + '">';
                }
                
                newRow += '</td>';
                newRow += '</tr>';
                
                $('#wsl-currencies-list').append(newRow);
                
                // Close modal
                $('#wsl-add-currency-modal').hide();
            });
            
            // Force uppercase for currency code input
            $('#wsl-new-currency-code').on('input', function() {
                this.value = this.value.toUpperCase();
            });
        });
        </script>
        <?php
        
        echo '</div>'; // .wsl-settings-section
    }

    /**
     * Update save_settings to handle currency settings
     */
    private function save_settings() {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }
        
        // Verify nonce for security
        if (!isset($_POST['wsl_settings_nonce']) || !wp_verify_nonce($_POST['wsl_settings_nonce'], 'wsl_save_settings')) {
            add_settings_error(
                'wsl_settings', 
                'nonce_error', 
                __('Security check failed. Settings not saved.', 'woo-shipping-labels'), 
                'error'
            );
            return;
        }
        
        // Initialize log for save operation
        $log = array(
            'time_started' => current_time('mysql'),
            'user_id' => get_current_user_id(),
            'events' => array(),
            'errors' => array(),
            'success' => false,
        );
        
        // Save general settings
        if (isset($_POST['wsl_general_settings'])) {
            update_option('wsl_general_settings', $this->sanitize_recursive($_POST['wsl_general_settings']));
            $log['events'][] = 'Saved general settings';
        }
        
        // Save store address
        if (isset($_POST['wsl_store_address'])) {
            update_option('wsl_store_address', $this->sanitize_recursive($_POST['wsl_store_address']));
            $log['events'][] = 'Saved store address';
        }
        
        // Save carrier settings
        if (isset($_POST['wsl_settings'])) {
            $carrier_settings = $this->process_carrier_settings($_POST['wsl_settings']);
            update_option('wsl_carrier_settings', $this->sanitize_recursive($carrier_settings));
            $log['events'][] = 'Saved carrier settings';
        }
        
        // Save country mappings
        if (isset($_POST['mappings'])) {
            update_option('wsl_country_carrier_services', $this->sanitize_recursive($_POST['mappings']));
            $log['events'][] = 'Saved country mappings';
        }
        
        // Save currencies
        if (isset($_POST['wsl_save_currencies']) || isset($_POST['wsl_currencies'])) {
            $this->save_currencies();
            $log['events'][] = 'Processed currency settings';
        }
        
        // Complete the log
        $log['time_completed'] = current_time('mysql');
        $log['time_elapsed'] = time() - strtotime($log['time_started']);
        $log['success'] = true;
        
        // Save the log
        update_option('wsl_settings_save_log', $log);
        
        add_settings_error('wsl_settings', 'settings_updated', __('Settings saved successfully.', 'woo-shipping-labels'), 'updated');
    }

    /**
     * Save currencies when form is submitted
     */
    private function save_currencies() {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }
        
        // Initialize currencies array
        $currencies = array();
        
        // Process submitted currencies
        if (isset($_POST['wsl_currencies']) && is_array($_POST['wsl_currencies'])) {
            foreach ($_POST['wsl_currencies'] as $code => $data) {
                // Sanitize and validate
                $code = sanitize_text_field($code);
                
                if (empty($code) || strlen($code) !== 3) {
                    continue;
                }
                
                // Create currency entry
                $currencies[$code] = array(
                    'code' => $code,
                    'name' => sanitize_text_field($data['name']),
                    'enabled' => isset($data['enabled']) && $data['enabled'] ? true : false,
                    'default' => false, // Will set default below
                );
            }
        }
        
        // Set default currency
        $default_currency = isset($_POST['wsl_default_currency']) ? sanitize_text_field($_POST['wsl_default_currency']) : 'USD';
        
        if (isset($currencies[$default_currency])) {
            $currencies[$default_currency]['default'] = true;
            $currencies[$default_currency]['enabled'] = true; // Default currency must be enabled
        } else {
            // If selected default doesn't exist, use USD or first currency
            if (isset($currencies['USD'])) {
                $currencies['USD']['default'] = true;
                $currencies['USD']['enabled'] = true;
            } else if (!empty($currencies)) {
                $first_key = array_key_first($currencies);
                $currencies[$first_key]['default'] = true;
                $currencies[$first_key]['enabled'] = true;
            }
        }
        
        // Save currencies to database
        if (!empty($currencies)) {
            update_option('wsl_currencies', $currencies);
        }
    }

    /**
     * Render address section for the label creation form
     * 
     * @param string $address_type The address type (from/to)
     * @param array  $address The address data
     * @param bool   $editable Whether the address is editable
     * @return string The HTML for the address section
     */
    public function render_address_section($address_type, $address = array(), $editable = true) {
        // Default empty address if none provided
        if (empty($address)) {
            $address = array(
                'name' => '',
                'company' => '',
                'address_1' => '',
                'address_2' => '',
                'city' => '',
                'state' => '',
                'postcode' => '',
                'country' => WC()->countries->get_base_country(),
                'phone' => '',
                'email' => '',
            );
        }
        
        // Get section title based on address type
        $section_title = ($address_type === 'from') ? __('Ship From', 'woo-shipping-labels') : __('Ship To', 'woo-shipping-labels');
        
        // Get countries and states
        $countries_obj = new WC_Countries();
        $countries = $countries_obj->get_countries();
        $states = $countries_obj->get_states();
        
        // Start output buffering to return HTML
        ob_start();
        
        // Create the address column
        echo '<div class="wsl-address-column">';
        echo '<h4>' . esc_html($section_title) . '</h4>';
        
        // Display version of address (shown by default)
        echo '<div class="wsl-address-display" data-address-type="' . esc_attr($address_type) . '">';
        // Format the address for display
        echo $this->format_address_html($address);
        echo '</div>';
        
        // Editable form for address (hidden by default)
        echo '<div class="wsl-address-edit" data-address-type="' . esc_attr($address_type) . '" style="display:none;">';
        
        // Full Name
        echo '<div class="wsl-form-field">';
        echo '<label for="' . esc_attr($address_type) . '_name">' . __('Full Name', 'woo-shipping-labels') . '</label>';
        echo '<input type="text" id="' . esc_attr($address_type) . '_name" name="' . esc_attr($address_type) . '_name" value="' . esc_attr($address['name']) . '">';
        echo '</div>';
        
        // Company
        echo '<div class="wsl-form-field">';
        echo '<label for="' . esc_attr($address_type) . '_company">' . __('Company', 'woo-shipping-labels') . '</label>';
        echo '<input type="text" id="' . esc_attr($address_type) . '_company" name="' . esc_attr($address_type) . '_company" value="' . esc_attr($address['company']) . '">';
        echo '</div>';
        
        // Address Line 1
        echo '<div class="wsl-form-field">';
        echo '<label for="' . esc_attr($address_type) . '_address_1">' . __('Address Line 1', 'woo-shipping-labels') . '</label>';
        echo '<input type="text" id="' . esc_attr($address_type) . '_address_1" name="' . esc_attr($address_type) . '_address_1" value="' . esc_attr($address['address_1']) . '">';
        echo '</div>';
        
        // Address Line 2
        echo '<div class="wsl-form-field">';
        echo '<label for="' . esc_attr($address_type) . '_address_2">' . __('Address Line 2', 'woo-shipping-labels') . ' <span class="optional">(' . __('optional', 'woo-shipping-labels') . ')</span></label>';
        echo '<input type="text" id="' . esc_attr($address_type) . '_address_2" name="' . esc_attr($address_type) . '_address_2" value="' . esc_attr($address['address_2']) . '">';
        echo '</div>';
        
        // City
        echo '<div class="wsl-form-field">';
        echo '<label for="' . esc_attr($address_type) . '_city">' . __('City', 'woo-shipping-labels') . '</label>';
        echo '<input type="text" id="' . esc_attr($address_type) . '_city" name="' . esc_attr($address_type) . '_city" value="' . esc_attr($address['city']) . '">';
        echo '</div>';
        
        // Country / State row
        echo '<div class="wsl-form-row">';
        
        // Country
        echo '<div class="wsl-form-field">';
        echo '<label for="' . esc_attr($address_type) . '_country">' . __('Country', 'woo-shipping-labels') . '</label>';
        
        echo '<select id="' . esc_attr($address_type) . '_country" name="' . esc_attr($address_type) . '_country" class="wsl-country-select">';
        foreach ($countries as $code => $name) {
            echo '<option value="' . esc_attr($code) . '" ' . selected($address['country'], $code, false) . '>' . esc_html($name) . '</option>';
        }
        echo '</select>';
        
        echo '</div>';
        
        // State
        echo '<div class="wsl-form-field">';
        echo '<label for="' . esc_attr($address_type) . '_state">' . __('State/Province', 'woo-shipping-labels') . '</label>';
        
        $current_states = isset($states[$address['country']]) ? $states[$address['country']] : array();
        
        if (!empty($current_states)) {
            echo '<select id="' . esc_attr($address_type) . '_state" name="' . esc_attr($address_type) . '_state">';
            foreach ($current_states as $code => $name) {
                echo '<option value="' . esc_attr($code) . '" ' . selected($address['state'], $code, false) . '>' . esc_html($name) . '</option>';
            }
            echo '</select>';
        } else {
            echo '<input type="text" id="' . esc_attr($address_type) . '_state" name="' . esc_attr($address_type) . '_state" value="' . esc_attr($address['state']) . '">';
        }
        
        echo '</div>';
        echo '</div>'; // .wsl-form-row
        
        // Postcode / Zip
        echo '<div class="wsl-form-field">';
        echo '<label for="' . esc_attr($address_type) . '_postcode">' . __('Postal Code', 'woo-shipping-labels') . '</label>';
        echo '<input type="text" id="' . esc_attr($address_type) . '_postcode" name="' . esc_attr($address_type) . '_postcode" value="' . esc_attr($address['postcode']) . '">';
        echo '</div>';
        
        // Phone
        echo '<div class="wsl-form-field">';
        echo '<label for="' . esc_attr($address_type) . '_phone">' . __('Phone', 'woo-shipping-labels') . '</label>';
        echo '<input type="tel" id="' . esc_attr($address_type) . '_phone" name="' . esc_attr($address_type) . '_phone" value="' . esc_attr($address['phone']) . '">';
        echo '</div>';
        
        // Email
        echo '<div class="wsl-form-field">';
        echo '<label for="' . esc_attr($address_type) . '_email">' . __('Email', 'woo-shipping-labels') . '</label>';
        echo '<input type="email" id="' . esc_attr($address_type) . '_email" name="' . esc_attr($address_type) . '_email" value="' . esc_attr($address['email']) . '">';
        echo '</div>';
        
        // Address Actions
        echo '<div class="wsl-address-actions">';
        echo '<button type="button" class="button wsl-save-address-btn" data-address-type="' . esc_attr($address_type) . '">' . __('Save Address', 'woo-shipping-labels') . '</button>';
        echo '<button type="button" class="button wsl-validate-address-btn" data-address-type="' . esc_attr($address_type) . '">' . __('Validate', 'woo-shipping-labels') . '</button>';
        echo '<button type="button" class="button wsl-cancel-edit-btn" data-address-type="' . esc_attr($address_type) . '">' . __('Cancel', 'woo-shipping-labels') . '</button>';
        echo '</div>';
        
        echo '</div>'; // .wsl-address-edit
        
        // Address Actions (for display mode)
        echo '<div class="wsl-address-actions">';
        echo '<button type="button" class="button wsl-edit-address-btn" data-address-type="' . esc_attr($address_type) . '">' . __('Edit Address', 'woo-shipping-labels') . '</button>';
        echo '</div>';
        
        echo '</div>'; // .wsl-address-column
        
        // Add script for country/state handling
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Country to state handling
            $('#<?php echo esc_js($address_type); ?>_country').on('change', function() {
                var country = $(this).val();
                var stateField = $('#<?php echo esc_js($address_type); ?>_state');
                var stateWrapper = stateField.closest('.wsl-form-field');
                
                // AJAX to get states for country
                $.ajax({
                    url: ajaxurl,
                    data: {
                        action: 'wsl_get_states',
                        country: country,
                        nonce: '<?php echo wp_create_nonce('wsl_get_states'); ?>'
                    },
                    type: 'POST',
                    dataType: 'json',
                    beforeSend: function() {
                        stateWrapper.block({
                            message: null,
                            overlayCSS: {
                                background: '#fff',
                                opacity: 0.6
                            }
                        });
                    },
                    success: function(response) {
                        if (response.success) {
                            var states = response.data;
                            
                            if ($.isEmptyObject(states)) {
                                // If no states, show text input
                                var input = $('<input type="text" />').attr({
                                    id: stateField.attr('id'),
                                    name: stateField.attr('name'),
                                    value: ''
                                });
                                stateField.replaceWith(input);
                            } else {
                                // If states exist, show select dropdown
                                var select = $('<select></select>').attr({
                                    id: stateField.attr('id'),
                                    name: stateField.attr('name')
                                });
                                
                                $.each(states, function(code, name) {
                                    select.append($('<option></option>').attr('value', code).text(name));
                                });
                                
                                stateField.replaceWith(select);
                            }
                        }
                        stateWrapper.unblock();
                    },
                    error: function() {
                        stateWrapper.unblock();
                    }
                });
            });
        });
        </script>
        <?php
        
        // Return the buffered HTML
        return ob_get_clean();
    }

    /**
     * Format address as HTML for display
     * 
     * @param array $address Address data
     * @return string Formatted address HTML
     */
    private function format_address_html($address) {
        $html = '';
        
        // Name
        if (!empty($address['name'])) {
            $html .= '<p>' . esc_html($address['name']) . '</p>';
        }
        
        // Company
        if (!empty($address['company'])) {
            $html .= '<p>' . esc_html($address['company']) . '</p>';
        }
        
        // Address line 1
        if (!empty($address['address_1'])) {
            $html .= '<p>' . esc_html($address['address_1']);
            
            // Address line 2 (if exists)
            if (!empty($address['address_2'])) {
                $html .= '<br>' . esc_html($address['address_2']);
            }
            
            $html .= '</p>';
        }
        
        // City, State ZIP
        $city_state_zip = '';
        if (!empty($address['city'])) {
            $city_state_zip .= $address['city'];
        }
        
        if (!empty($address['state'])) {
            if (!empty($city_state_zip)) {
                $city_state_zip .= ', ';
            }
            $city_state_zip .= $address['state'];
        }
        
        if (!empty($address['postcode'])) {
            if (!empty($city_state_zip)) {
                $city_state_zip .= ' ';
            }
            $city_state_zip .= $address['postcode'];
        }
        
        if (!empty($city_state_zip)) {
            $html .= '<p>' . esc_html($city_state_zip) . '</p>';
        }
        
        // Country
        if (!empty($address['country'])) {
            $countries = WC()->countries->get_countries();
            $country_name = isset($countries[$address['country']]) ? $countries[$address['country']] : $address['country'];
            $html .= '<p>' . esc_html($country_name) . '</p>';
        }
        
        // Phone
        if (!empty($address['phone'])) {
            $html .= '<p><strong>' . __('Phone:', 'woo-shipping-labels') . '</strong> ' . esc_html($address['phone']) . '</p>';
        }
        
        // Email
        if (!empty($address['email'])) {
            $html .= '<p><strong>' . __('Email:', 'woo-shipping-labels') . '</strong> ' . esc_html($address['email']) . '</p>';
        }
        
        return $html;
    }

    /**
     * AJAX handler for getting states for a country
     */
    public function ajax_get_states() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wsl_get_states')) {
            wp_send_json_error(array('message' => __('Security check failed', 'woo-shipping-labels')));
        }
        
        // Get country
        $country = isset($_POST['country']) ? sanitize_text_field($_POST['country']) : '';
        
        if (empty($country)) {
            wp_send_json_error(array('message' => __('Country is required', 'woo-shipping-labels')));
        }
        
        // Get states for country
        $countries_obj = new WC_Countries();
        $states = $countries_obj->get_states($country);
        
        wp_send_json_success($states);
    }

    /**
     * Register settings for the plugin
     */
    public function register_settings() {
        // Register general settings
        register_setting('wsl_general_settings', 'wsl_general_settings');
        
        // Register store address
        register_setting('wsl_store_address', 'wsl_store_address');
        
        // Register carrier settings
        register_setting('wsl_carrier_settings', 'wsl_carrier_settings');
        
        // Register country mappings
        register_setting('wsl_country_carrier_services', 'wsl_country_carrier_services');
        
        // Register currencies
        register_setting('wsl_currencies', 'wsl_currencies');
    }

    /**
     * Render the currencies management page
     */
    public function render_currencies_page() {
        // Verify user has permission
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'woo-shipping-labels'));
        }
        
        // Handle form submission
        if (isset($_POST['wsl_save_currencies']) || isset($_POST['wsl_currencies'])) {
            $this->save_currencies();
        }
        
        // Get saved currencies
        $currencies = get_option('wsl_currencies', array());
        
        // Page container
        echo '<div class="wrap wsl-currencies-page">';
        echo '<h1>' . __('Currency Management', 'woo-shipping-labels') . '</h1>';
        
        echo '<form method="post" action="">';
        
        // Currency Table
        echo '<table class="widefat wsl-currency-table">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . __('Code', 'woo-shipping-labels') . '</th>';
        echo '<th>' . __('Name', 'woo-shipping-labels') . '</th>';
        echo '<th>' . __('Enabled', 'woo-shipping-labels') . '</th>';
        echo '<th>' . __('Default', 'woo-shipping-labels') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody id="wsl-currencies-list">';
        
        // Display all currencies
        if (!empty($currencies)) {
            foreach ($currencies as $code => $currency) {
                echo '<tr data-code="' . esc_attr($code) . '">';
                echo '<td>';
                echo '<input type="hidden" name="wsl_currencies[' . esc_attr($code) . '][code]" value="' . esc_attr($code) . '">';
                echo esc_html($code);
                echo '</td>';
                echo '<td>';
                echo '<input type="text" name="wsl_currencies[' . esc_attr($code) . '][name]" value="' . esc_attr($currency['name']) . '">';
                echo '</td>';
                echo '<td>';
                echo '<input type="checkbox" name="wsl_currencies[' . esc_attr($code) . '][enabled]" value="1" ' . checked(isset($currency['enabled']) && $currency['enabled'], true, false) . '>';
                echo '</td>';
                echo '<td>';
                echo '<input type="radio" name="wsl_default_currency" value="' . esc_attr($code) . '" ' . checked(isset($currency['default']) && $currency['default'], true, false) . '>';
                echo '</td>';
                echo '</tr>';
            }
        } else {
            // If no currencies exist, add some common ones
            $default_currencies = array(
                'USD' => array('name' => 'US Dollar', 'default' => true),
                'EUR' => array('name' => 'Euro'),
                'GBP' => array('name' => 'British Pound'),
                'CAD' => array('name' => 'Canadian Dollar'),
                'AUD' => array('name' => 'Australian Dollar'),
            );
            
            foreach ($default_currencies as $code => $currency) {
                $is_default = isset($currency['default']) && $currency['default'];
                
                echo '<tr data-code="' . esc_attr($code) . '">';
                echo '<td>';
                echo '<input type="hidden" name="wsl_currencies[' . esc_attr($code) . '][code]" value="' . esc_attr($code) . '">';
                echo esc_html($code);
                echo '</td>';
                echo '<td>';
                echo '<input type="text" name="wsl_currencies[' . esc_attr($code) . '][name]" value="' . esc_attr($currency['name']) . '">';
                echo '</td>';
                echo '<td>';
                echo '<input type="checkbox" name="wsl_currencies[' . esc_attr($code) . '][enabled]" value="1" ' . checked($is_default, true, false) . '>';
                echo '</td>';
                echo '<td>';
                echo '<input type="radio" name="wsl_default_currency" value="' . esc_attr($code) . '" ' . checked($is_default, true, false) . '>';
                echo '</td>';
                echo '</tr>';
            }
        }
        
        echo '</tbody>';
        echo '</table>';
        
        // Add Currency button
        echo '<div class="wsl-currency-actions">';
        echo '<button type="button" class="button wsl-add-currency">' . __('Add Currency', 'woo-shipping-labels') . '</button>';
        echo '</div>';
        
        echo '<p class="submit">';
        echo '<input type="submit" name="submit" id="submit" class="button button-primary" value="' . __('Save Changes', 'woo-shipping-labels') . '">';
        echo '</p>';
        
        echo '</form>';
        
        // Add Currency Modal
        echo '<div id="wsl-add-currency-modal" class="wsl-modal">';
        echo '<div class="wsl-modal-content">';
        echo '<div class="wsl-modal-header">';
        echo '<span class="wsl-modal-close">&times;</span>';
        echo '<h2>' . __('Add New Currency', 'woo-shipping-labels') . '</h2>';
        echo '</div>';
        echo '<div class="wsl-modal-body">';
        
        // Currency Code
        echo '<div class="wsl-form-field">';
        echo '<label for="wsl-new-currency-code">' . __('Currency Code', 'woo-shipping-labels') . '</label>';
        echo '<input type="text" id="wsl-new-currency-code" maxlength="3" placeholder="USD" />';
        echo '<p class="description">' . __('3-letter code (e.g., USD, EUR, GBP)', 'woo-shipping-labels') . '</p>';
        echo '</div>';
        
        // Currency Name
        echo '<div class="wsl-form-field">';
        echo '<label for="wsl-new-currency-name">' . __('Currency Name', 'woo-shipping-labels') . '</label>';
        echo '<input type="text" id="wsl-new-currency-name" placeholder="United States Dollar" />';
        echo '<p class="description">' . __('Full name of the currency', 'woo-shipping-labels') . '</p>';
        echo '</div>';
        
        // Enable Currency
        echo '<div class="wsl-form-field">';
        echo '<label for="wsl-new-currency-enabled">' . __('Enable Currency', 'woo-shipping-labels') . '</label>';
        echo '<input type="checkbox" id="wsl-new-currency-enabled" value="1" />';
        echo '<p class="description">' . __('Enable this currency for shipping labels', 'woo-shipping-labels') . '</p>';
        echo '</div>';
        
        // Default Currency
        echo '<div class="wsl-form-field">';
        echo '<label for="wsl-new-currency-default">' . __('Default Currency', 'woo-shipping-labels') . '</label>';
        echo '<input type="checkbox" id="wsl-new-currency-default" value="1" />';
        echo '<p class="description">' . __('Set as default currency for shipping labels', 'woo-shipping-labels') . '</p>';
        echo '</div>';
        
        echo '<div class="wsl-error-message"></div>';
        
        echo '</div>'; // .wsl-modal-body
        echo '<div class="wsl-modal-footer">';
        echo '<button type="button" class="button button-primary wsl-save-currency">' . __('Add Currency', 'woo-shipping-labels') . '</button>';
        echo '<button type="button" class="button wsl-cancel-currency">' . __('Cancel', 'woo-shipping-labels') . '</button>';
        echo '</div>'; // .wsl-modal-footer
        echo '</div>'; // .wsl-modal-content
        echo '</div>'; // #wsl-add-currency-modal
        
        // REMOVED: Inline styles for currency table and modal - now in admin-tab-currencies.css
        
        // Add JavaScript for the modal and "Add Currency" functionality
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Open modal when "Add Currency" button is clicked
            $('.wsl-add-currency').on('click', function() {
                // Reset form fields
                $('#wsl-new-currency-code').val('');
                $('#wsl-new-currency-name').val('');
                $('#wsl-new-currency-enabled').prop('checked', false);
                $('#wsl-new-currency-default').prop('checked', false);
                $('.wsl-error-message').hide().html('');
                
                // Open modal
                $('#wsl-add-currency-modal').show();
            });
            
            // Close modal when "X" is clicked
            $('.wsl-modal-close').on('click', function() {
                $('#wsl-add-currency-modal').hide();
            });
            
            // Close modal when "Cancel" button is clicked
            $('.wsl-cancel-currency').on('click', function() {
                $('#wsl-add-currency-modal').hide();
            });
            
            // Close modal when clicking outside of it
            $(window).on('click', function(event) {
                if ($(event.target).is('#wsl-add-currency-modal')) {
                    $('#wsl-add-currency-modal').hide();
                }
            });
            
            // Save currency when "Add Currency" button in modal is clicked
            $('.wsl-save-currency').on('click', function() {
                // Get form values
                var code = $('#wsl-new-currency-code').val().toUpperCase();
                var name = $('#wsl-new-currency-name').val();
                var enabled = $('#wsl-new-currency-enabled').is(':checked');
                var isDefault = $('#wsl-new-currency-default').is(':checked');
                
                // Validate form
                var errors = [];
                
                if (!code) {
                    errors.push('Currency code is required');
                } else if (code.length !== 3) {
                    errors.push('Currency code must be exactly 3 letters');
                }
                
                if (!name) {
                    errors.push('Currency name is required');
                }
                
                // Check if currency already exists
                if ($('#wsl-currencies-list tr[data-code="' + code + '"]').length) {
                    errors.push('This currency already exists');
                }
                
                // Display errors if any
                if (errors.length > 0) {
                    var errorHtml = '<ul>';
                    $.each(errors, function(index, error) {
                        errorHtml += '<li>' + error + '</li>';
                    });
                    errorHtml += '</ul>';
                    
                    $('.wsl-error-message').html(errorHtml).show();
                    return;
                }
                
                // Add a new row to the table
                var newRow = '<tr data-code="' + code + '">';
                newRow += '<td>';
                newRow += '<input type="hidden" name="wsl_currencies[' + code + '][code]" value="' + code + '">';
                newRow += code;
                newRow += '</td>';
                newRow += '<td>';
                newRow += '<input type="text" name="wsl_currencies[' + code + '][name]" value="' + name + '">';
                newRow += '</td>';
                newRow += '<td>';
                newRow += '<input type="checkbox" name="wsl_currencies[' + code + '][enabled]" value="1"' + (enabled ? ' checked' : '') + '>';
                newRow += '</td>';
                newRow += '<td>';
                
                // If setting as default, uncheck all other defaults
                if (isDefault) {
                    $('#wsl-currencies-list input[name="wsl_default_currency"]').prop('checked', false);
                    newRow += '<input type="radio" name="wsl_default_currency" value="' + code + '" checked>';
                } else {
                    newRow += '<input type="radio" name="wsl_default_currency" value="' + code + '">';
                }
                
                newRow += '</td>';
                newRow += '</tr>';
                
                $('#wsl-currencies-list').append(newRow);
                
                // Close modal
                $('#wsl-add-currency-modal').hide();
            });
            
            // Force uppercase for currency code input
            $('#wsl-new-currency-code').on('input', function() {
                this.value = this.value.toUpperCase();
            });
        });
        </script>
        <?php
        
        echo '</div>'; // .wrap
    }

    /**
     * Render packages tab content
     */
    private function render_packages_tab() {
        // Get saved packages
        $saved_packages = get_option('wsl_packages', array());
        
        // Define default package types
        $package_types = array(
            'box' => __('Box', 'woo-shipping-labels'),
            'envelope' => __('Envelope', 'woo-shipping-labels'),
            'pak' => __('Pak', 'woo-shipping-labels'),
            'tube' => __('Tube', 'woo-shipping-labels'),
            'custom' => __('Custom', 'woo-shipping-labels'),
        );
        
        // Get dimension and weight units
        $general_settings = get_option('wsl_general_settings', array(
            'default_weight_unit' => 'lb',
            'default_dimension_unit' => 'in',
        ));
        
        $dimension_unit = $general_settings['default_dimension_unit'];
        $weight_unit = $general_settings['default_weight_unit'];
        
        // Display the packages tab
        ?>
        <div class="wsl-packages-tab">
            <h2><?php _e('Package Presets', 'woo-shipping-labels'); ?></h2>
            <p class="description">
                <?php _e('Create package presets to quickly select common box sizes when generating labels.', 'woo-shipping-labels'); ?>
            </p>
            
            <table class="widefat wsl-packages-table">
                <thead>
                    <tr>
                        <th><?php _e('Name', 'woo-shipping-labels'); ?></th>
                        <th><?php _e('Type', 'woo-shipping-labels'); ?></th>
                        <th><?php _e('Length', 'woo-shipping-labels'); ?> (<?php echo esc_html($dimension_unit); ?>)</th>
                        <th><?php _e('Width', 'woo-shipping-labels'); ?> (<?php echo esc_html($dimension_unit); ?>)</th>
                        <th><?php _e('Height', 'woo-shipping-labels'); ?> (<?php echo esc_html($dimension_unit); ?>)</th>
                        <th><?php _e('Max Weight', 'woo-shipping-labels'); ?> (<?php echo esc_html($weight_unit); ?>)</th>
                        <th><?php _e('Enabled', 'woo-shipping-labels'); ?></th>
                        <th><?php _e('Actions', 'woo-shipping-labels'); ?></th>
                    </tr>
                </thead>
                <tbody id="wsl-packages-list">
                    <?php
                    // Display existing packages
                    if (!empty($saved_packages)) {
                        foreach ($saved_packages as $package_id => $package) {
                            $this->render_package_row($package_id, $package, $package_types);
                        }
                    } else {
                        // If no packages exist, add some common ones
                        $default_packages = array(
                            'small_box' => array(
                                'name' => __('Small Box', 'woo-shipping-labels'),
                                'type' => 'box',
                                'length' => 8,
                                'width' => 6,
                                'height' => 4,
                                'max_weight' => 20,
                                'enabled' => true,
                            ),
                            'medium_box' => array(
                                'name' => __('Medium Box', 'woo-shipping-labels'),
                                'type' => 'box',
                                'length' => 12,
                                'width' => 10,
                                'height' => 8,
                                'max_weight' => 40,
                                'enabled' => true,
                            ),
                            'large_box' => array(
                                'name' => __('Large Box', 'woo-shipping-labels'),
                                'type' => 'box',
                                'length' => 16,
                                'width' => 14,
                                'height' => 10,
                                'max_weight' => 70,
                                'enabled' => true,
                            ),
                            'envelope' => array(
                                'name' => __('Letter Envelope', 'woo-shipping-labels'),
                                'type' => 'envelope',
                                'length' => 12,
                                'width' => 9,
                                'height' => 0.5,
                                'max_weight' => 1,
                                'enabled' => true,
                            ),
                        );
                        
                        foreach ($default_packages as $package_id => $package) {
                            $this->render_package_row($package_id, $package, $package_types);
                        }
                    }
                    ?>
                </tbody>
            </table>
            
            <div class="wsl-package-actions">
                <button type="button" class="button wsl-add-package"><?php _e('Add Package', 'woo-shipping-labels'); ?></button>
            </div>
            
            <!-- Package row template for JavaScript -->
            <script type="text/html" id="tmpl-wsl-package-row">
                <tr data-id="{{ data.id }}">
                    <td>
                        <input type="hidden" name="wsl_packages[{{ data.id }}][id]" value="{{ data.id }}">
                        <input type="text" name="wsl_packages[{{ data.id }}][name]" value="{{ data.name }}" required>
                    </td>
                    <td>
                        <select name="wsl_packages[{{ data.id }}][type]">
                            <?php foreach ($package_types as $type_id => $type_name) : ?>
                                <option value="<?php echo esc_attr($type_id); ?>">
                                    <?php echo esc_html($type_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <input type="number" step="0.01" min="0" name="wsl_packages[{{ data.id }}][length]" value="{{ data.length }}" required>
                    </td>
                    <td>
                        <input type="number" step="0.01" min="0" name="wsl_packages[{{ data.id }}][width]" value="{{ data.width }}" required>
                    </td>
                    <td>
                        <input type="number" step="0.01" min="0" name="wsl_packages[{{ data.id }}][height]" value="{{ data.height }}" required>
                    </td>
                    <td>
                        <input type="number" step="0.01" min="0" name="wsl_packages[{{ data.id }}][max_weight]" value="{{ data.max_weight }}" required>
                    </td>
                    <td>
                        <input type="checkbox" name="wsl_packages[{{ data.id }}][enabled]" value="1" <# if (data.enabled) { #>checked<# } #>>
                    </td>
                    <td>
                        <button type="button" class="button wsl-remove-package"><?php _e('Remove', 'woo-shipping-labels'); ?></button>
                    </td>
                </tr>
            </script>

            <!-- Add JavaScript for the package management -->
            <script type="text/javascript">
                jQuery(document).ready(function($) {
                    // Add new package
                    $('.wsl-add-package').on('click', function() {
                        // Generate a unique ID
                        var newId = 'package_' + Math.random().toString(36).substr(2, 9);
                        
                        // Create new package data
                        var templateData = {
                            id: newId,
                            name: '',
                            type: 'box',
                            length: '',
                            width: '',
                            height: '',
                            max_weight: '',
                            enabled: true
                        };
                        
                        // Get the template
                        var template = wp.template('wsl-package-row');
                        
                        // Add the new row
                        $('#wsl-packages-list').append(template(templateData));
                    });
                    
                    // Remove package
                    $(document).on('click', '.wsl-remove-package', function() {
                        $(this).closest('tr').remove();
                    });
                });
            </script>
            
            <style>
                .wsl-packages-table {
                    margin-top: 15px;
                }
                .wsl-packages-table input[type="text"],
                .wsl-packages-table select {
                    width: 100%;
                }
                .wsl-packages-table input[type="number"] {
                    width: 80px;
                }
                .wsl-package-actions {
                    margin-top: 10px;
                }
            </style>
        </div>
        <?php
    }

    /**
     * Render a single package row
     * 
     * @param string $package_id The package ID
     * @param array $package The package data
     * @param array $package_types Available package types
     */
    private function render_package_row($package_id, $package, $package_types) {
        ?>
        <tr data-id="<?php echo esc_attr($package_id); ?>">
            <td>
                <input type="hidden" name="wsl_packages[<?php echo esc_attr($package_id); ?>][id]" value="<?php echo esc_attr($package_id); ?>">
                <input type="text" name="wsl_packages[<?php echo esc_attr($package_id); ?>][name]" value="<?php echo esc_attr($package['name']); ?>" required>
            </td>
            <td>
                <select name="wsl_packages[<?php echo esc_attr($package_id); ?>][type]">
                    <?php foreach ($package_types as $type_id => $type_name) : ?>
                        <option value="<?php echo esc_attr($type_id); ?>" <?php selected($package['type'], $type_id); ?>>
                            <?php echo esc_html($type_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td>
                <input type="number" step="0.01" min="0" name="wsl_packages[<?php echo esc_attr($package_id); ?>][length]" value="<?php echo esc_attr($package['length']); ?>" required>
            </td>
            <td>
                <input type="number" step="0.01" min="0" name="wsl_packages[<?php echo esc_attr($package_id); ?>][width]" value="<?php echo esc_attr($package['width']); ?>" required>
            </td>
            <td>
                <input type="number" step="0.01" min="0" name="wsl_packages[<?php echo esc_attr($package_id); ?>][height]" value="<?php echo esc_attr($package['height']); ?>" required>
            </td>
            <td>
                <input type="number" step="0.01" min="0" name="wsl_packages[<?php echo esc_attr($package_id); ?>][max_weight]" value="<?php echo esc_attr($package['max_weight']); ?>" required>
            </td>
            <td>
                <input type="checkbox" name="wsl_packages[<?php echo esc_attr($package_id); ?>][enabled]" value="1" <?php checked(isset($package['enabled']) && $package['enabled']); ?>>
            </td>
            <td>
                <button type="button" class="button wsl-remove-package"><?php _e('Remove', 'woo-shipping-labels'); ?></button>
            </td>
        </tr>
        <?php
    }

    /**
     * Render countries tab content
     */
    private function render_countries_tab() {
        // Get WooCommerce countries
        $countries_obj = new WC_Countries();
        $countries = $countries_obj->get_countries();
        
        // Get enabled carriers - FIX THE CARRIER DETECTION
        $carrier_settings = get_option('wsl_carrier_settings', array());
        $enabled_carriers = array();
        
        // Debug output to console - you can remove this after fixing
        echo '<script>console.log("Carrier settings:", ' . json_encode($carrier_settings) . ');</script>';
        
        // More flexible carrier detection
        foreach ($carrier_settings as $carrier_id => $settings) {
            // Check multiple possible formats for "enabled" status
            if (
                // Standard format with enabled as a boolean/value in settings array
                (isset($settings['enabled']) && $settings['enabled']) ||
                // Alternative format where settings might be boolean itself
                (is_bool($settings) && $settings === true) ||
                // Format where enabled might be a string "1" or "yes"
                (isset($settings['enabled']) && in_array($settings['enabled'], array('1', 'yes', 'true'), true))
            ) {
                $enabled_carriers[$carrier_id] = $this->get_carrier_name($carrier_id);
            }
        }
        
        // If still no carriers detected, add a fallback for testing
        if (empty($enabled_carriers)) {
            // Check if any carriers exist at all
            $all_carriers = array('usps', 'ups', 'fedex', 'dhl');
            foreach ($all_carriers as $carrier_id) {
                if (isset($carrier_settings[$carrier_id])) {
                    $enabled_carriers[$carrier_id] = $this->get_carrier_name($carrier_id);
                }
            }
            
            // Last resort - force enable FedEx for testing
            if (empty($enabled_carriers) && isset($carrier_settings['fedex'])) {
                $enabled_carriers['fedex'] = $this->get_carrier_name('fedex');
            }
        }
        
        // Debug output - number of enabled carriers
        echo '<script>console.log("Enabled carriers:", ' . json_encode($enabled_carriers) . ');</script>';
        
        // Get saved country carrier mappings
        $mappings = get_option('wsl_country_carrier_services', array());
        
        // Display the countries tab
        ?>
        <div class="wsl-countries-tab">
            <h2><?php _e('Country Shipping Options', 'woo-shipping-labels'); ?></h2>
            <p class="description">
                <?php _e('Configure which carriers and services are available for each country.', 'woo-shipping-labels'); ?>
            </p>
            
            <div class="wsl-country-selector">
                <label for="wsl-country-select"><?php _e('Select Country:', 'woo-shipping-labels'); ?></label>
                <select id="wsl-country-select">
                    <option value=""><?php _e('-- Select Country --', 'woo-shipping-labels'); ?></option>
                    <?php foreach ($countries as $code => $name) : ?>
                        <option value="<?php echo esc_attr($code); ?>"><?php echo esc_html($name); ?></option>
                    <?php endforeach; ?>
                </select>
                
                <button type="button" class="button" id="wsl-add-country-mapping"><?php _e('Add Country', 'woo-shipping-labels'); ?></button>
            </div>
            
            <div id="wsl-country-mappings-container">
                <?php if (empty($mappings)) : ?>
                    <div class="wsl-empty-notice">
                        <p><?php _e('No country mappings configured yet. Select a country above to begin.', 'woo-shipping-labels'); ?></p>
                    </div>
                <?php else : ?>
                    <?php foreach ($mappings as $country_code => $carrier_services) : ?>
                        <?php 
                        $country_name = isset($countries[$country_code]) ? $countries[$country_code] : $country_code;
                        $this->render_country_mapping_card($country_code, $country_name, $carrier_services, $enabled_carriers);
                        ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- JavaScript for the country mappings functionality -->
            <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Add country mapping
                $('#wsl-add-country-mapping').on('click', function() {
                    var countrySelect = $('#wsl-country-select');
                    var countryCode = countrySelect.val();
                    var countryName = countrySelect.find('option:selected').text();
                    
                    if (!countryCode) {
                        alert('<?php echo esc_js(__('Please select a country.', 'woo-shipping-labels')); ?>');
                        return;
                    }
                    
                    // Check if country already exists
                    if ($('.wsl-country-card[data-country="' + countryCode + '"]').length) {
                        alert('<?php echo esc_js(__('This country is already configured.', 'woo-shipping-labels')); ?>');
                        return;
                    }
                    
                    // Create country card HTML directly (not using wp.template)
                    var html = '<div class="wsl-country-card" data-country="' + countryCode + '">' +
                        '<div class="wsl-country-card-header">' +
                        '<h3>' + countryName + '</h3>' +
                        '<button type="button" class="wsl-remove-country-mapping button-link" data-country="' + countryCode + '">' +
                        '<span class="dashicons dashicons-trash"></span>' +
                        '</button>' +
                        '</div>' +
                        '<div class="wsl-country-carrier-services">';
                    
                    <?php if (empty($enabled_carriers)) : ?>
                    html += '<p class="wsl-no-carriers-notice">' +
                        '<?php echo esc_js(__('No carriers are enabled. Enable carriers in the Carriers tab.', 'woo-shipping-labels')); ?>' +
                        '</p>';
                    <?php else : ?>
                        <?php foreach ($enabled_carriers as $carrier_id => $carrier_name) : ?>
                        html += '<div class="wsl-carrier-option">' +
                            '<label>' +
                            '<input type="checkbox" name="mappings[' + countryCode + '][<?php echo esc_attr($carrier_id); ?>][enabled]" value="1" class="wsl-carrier-toggle">' +
                            '<?php echo esc_js($carrier_name); ?>' +
                            '</label>' +
                            '<div class="wsl-carrier-services" style="display: none;">' +
                            '<h4><?php echo esc_js(__('Available Services', 'woo-shipping-labels')); ?></h4>' +
                            '<div class="wsl-service-options">' +
                            '<div class="wsl-loading-services"><?php echo esc_js(__('Loading services...', 'woo-shipping-labels')); ?></div>' +
                            '</div>' +
                            '</div>' +
                            '</div>';
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    html += '</div></div>';
                    
                    // Add the HTML to the container
                    $('#wsl-country-mappings-container').append(html);
                    
                    // Reset the select
                    countrySelect.val('');
                    
                    // Remove empty notice if it exists
                    $('.wsl-empty-notice').remove();
                });
                
                // Remove country mapping
                $(document).on('click', '.wsl-remove-country-mapping', function() {
                    if (confirm('<?php echo esc_js(__('Are you sure you want to remove this country mapping?', 'woo-shipping-labels')); ?>')) {
                        $(this).closest('.wsl-country-card').remove();
                        
                        // Show empty notice if no countries remain
                        if ($('.wsl-country-card').length === 0) {
                            $('#wsl-country-mappings-container').html(
                                '<div class="wsl-empty-notice">' +
                                '<p><?php echo esc_js(__('No country mappings configured yet. Select a country above to begin.', 'woo-shipping-labels')); ?></p>' +
                                '</div>'
                            );
                        }
                    }
                });
                
                // Toggle carrier services
                $(document).on('change', '.wsl-carrier-toggle', function() {
                    var servicesContainer = $(this).closest('.wsl-carrier-option').find('.wsl-carrier-services');
                    
                    if ($(this).is(':checked')) {
                        servicesContainer.slideDown();
                        
                        // Load services if not already loaded
                        var serviceOptions = servicesContainer.find('.wsl-service-options');
                        if (serviceOptions.find('input').length === 0) {
                            var countryCode = $(this).closest('.wsl-country-card').data('country');
                            var carrierId = $(this).attr('name').match(/mappings\[(.*?)\]\[(.*?)\]/)[2];
                            
                            loadCarrierServices(countryCode, carrierId, serviceOptions);
                        }
                    } else {
                        servicesContainer.slideUp();
                    }
                });
                
                // Function to load carrier services via AJAX
                function loadCarrierServices(countryCode, carrierId, container) {
                    $.ajax({
                        url: ajaxurl,
                        data: {
                            action: 'wsl_get_carrier_services',
                            country: countryCode,
                            carrier: carrierId,
                            nonce: '<?php echo wp_create_nonce('wsl_get_carrier_services'); ?>'
                        },
                        type: 'POST',
                        dataType: 'json',
                        beforeSend: function() {
                            container.html('<div class="wsl-loading-services"><?php echo esc_js(__('Loading services...', 'woo-shipping-labels')); ?></div>');
                        },
                        success: function(response) {
                            if (response.success && response.data) {
                                var services = response.data;
                                var html = '';
                                
                                if ($.isEmptyObject(services)) {
                                    html = '<p><?php echo esc_js(__('No services available for this carrier.', 'woo-shipping-labels')); ?></p>';
                                } else {
                                    for (var serviceId in services) {
                                        if (services.hasOwnProperty(serviceId)) {
                                            html += '<label>' +
                                                '<input type="checkbox" name="mappings[' + countryCode + '][' + carrierId + '][services][' + serviceId + ']" value="1">' +
                                                services[serviceId] +
                                                '</label><br>';
                                        }
                                    }
                                }
                                
                                container.html(html);
                            } else {
                                container.html('<p><?php echo esc_js(__('Error loading services.', 'woo-shipping-labels')); ?></p>');
                            }
                        },
                        error: function() {
                            container.html('<p><?php echo esc_js(__('Error loading services.', 'woo-shipping-labels')); ?></p>');
                        }
                    });
                }
            });
            </script>
        </div>
        <?php
    }

    /**
     * Render a country mapping card
     * 
     * @param string $country_code The country code
     * @param string $country_name The country name
     * @param array $carrier_services The carrier services mapping
     * @param array $enabled_carriers List of enabled carriers
     */
    private function render_country_mapping_card($country_code, $country_name, $carrier_services, $enabled_carriers) {
        ?>
        <div class="wsl-country-card" data-country="<?php echo esc_attr($country_code); ?>">
            <div class="wsl-country-card-header">
                <h3><?php echo esc_html($country_name); ?></h3>
                <button type="button" class="wsl-remove-country-mapping button-link" data-country="<?php echo esc_attr($country_code); ?>">
                    <span class="dashicons dashicons-trash"></span>
                </button>
            </div>
            
            <div class="wsl-country-carrier-services">
                <?php foreach ($enabled_carriers as $carrier_id => $carrier_name) : ?>
                    <?php 
                    $carrier_enabled = isset($carrier_services[$carrier_id]['enabled']) && $carrier_services[$carrier_id]['enabled'];
                    $carrier_service_list = isset($carrier_services[$carrier_id]['services']) ? $carrier_services[$carrier_id]['services'] : array();
                    ?>
                    <div class="wsl-carrier-option">
                        <label>
                            <input type="checkbox" name="mappings[<?php echo esc_attr($country_code); ?>][<?php echo esc_attr($carrier_id); ?>][enabled]" 
                                   value="1" class="wsl-carrier-toggle" <?php checked($carrier_enabled); ?>>
                            <?php echo esc_html($carrier_name); ?>
                        </label>
                        
                        <div class="wsl-carrier-services" style="display: <?php echo $carrier_enabled ? 'block' : 'none'; ?>;">
                            <h4><?php _e('Available Services', 'woo-shipping-labels'); ?></h4>
                            <div class="wsl-service-options">
                                <?php 
                                // Get services for this carrier
                                $services = $this->get_carrier_services($carrier_id, $country_code);
                                
                                if (empty($services)) {
                                    echo '<p>' . __('No services available for this carrier.', 'woo-shipping-labels') . '</p>';
                                } else {
                                    foreach ($services as $service_id => $service_name) {
                                        $service_enabled = isset($carrier_service_list[$service_id]) && $carrier_service_list[$service_id];
                                        ?>
                                        <label>
                                            <input type="checkbox" name="mappings[<?php echo esc_attr($country_code); ?>][<?php echo esc_attr($carrier_id); ?>][services][<?php echo esc_attr($service_id); ?>]" 
                                                   value="1" <?php checked($service_enabled); ?>>
                                            <?php echo esc_html($service_name); ?>
                                        </label><br>
                                        <?php
                                    }
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <?php if (empty($enabled_carriers)) : ?>
                    <p class="wsl-no-carriers-notice">
                        <?php _e('No carriers are enabled. Enable carriers in the Carriers tab.', 'woo-shipping-labels'); ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Get carrier name
     *
     * @param string $carrier_id The carrier ID
     * @return string The carrier name
     */
    private function get_carrier_name($carrier_id) {
        $carriers = array(
            'usps' => __('USPS', 'woo-shipping-labels'),
            'ups' => __('UPS', 'woo-shipping-labels'),
            'fedex' => __('FedEx', 'woo-shipping-labels'),
            'dhl' => __('DHL', 'woo-shipping-labels'),
        );
        
        return isset($carriers[$carrier_id]) ? $carriers[$carrier_id] : ucfirst($carrier_id);
    }

    /**
     * Get services for a carrier
     *
     * @param string $carrier_id The carrier ID
     * @param string $country_code The country code
     * @return array List of services
     */
    private function get_carrier_services($carrier_id, $country_code) {
        // Get carrier settings to check which services are enabled
        $carrier_settings = get_option('wsl_carrier_settings', array());
        $enabled_services = array();
        
        // Check if this carrier has enabled services
        if (isset($carrier_settings[$carrier_id]['services'])) {
            // Get the enabled services for this carrier
            foreach ($carrier_settings[$carrier_id]['services'] as $service_id => $service_data) {
                // Service might be stored as array with 'enabled' key or directly as boolean
                if ((is_array($service_data) && !empty($service_data['enabled'])) || 
                    (!is_array($service_data) && $service_data)) {
                    $enabled_services[$service_id] = true;
                }
            }
        }
        
        // Log the enabled services for debugging
        error_log('Enabled services for ' . $carrier_id . ': ' . json_encode($enabled_services));
        
        // Define all available services by carrier and filter by country
        $all_services = array();
        
        switch ($carrier_id) {
            case 'usps':
                if ($country_code === 'US') {
                    $all_services = array(
                        'priority' => __('Priority Mail', 'woo-shipping-labels'),
                        'express' => __('Priority Mail Express', 'woo-shipping-labels'),
                        'first_class' => __('First Class Mail', 'woo-shipping-labels'),
                        'parcel_select' => __('Parcel Select', 'woo-shipping-labels'),
                        'media_mail' => __('Media Mail', 'woo-shipping-labels'),
                    );
                } else {
                    $all_services = array(
                        'priority_international' => __('Priority Mail International', 'woo-shipping-labels'),
                        'express_international' => __('Priority Mail Express International', 'woo-shipping-labels'),
                        'first_class_international' => __('First Class Package International', 'woo-shipping-labels'),
                    );
                }
                break;
                
            case 'ups':
                $all_services = array(
                    'ground' => __('Ground', 'woo-shipping-labels'),
                    'next_day_air' => __('Next Day Air', 'woo-shipping-labels'),
                    'next_day_air_saver' => __('Next Day Air Saver', 'woo-shipping-labels'),
                    '2nd_day_air' => __('2nd Day Air', 'woo-shipping-labels'),
                    '3_day_select' => __('3 Day Select', 'woo-shipping-labels'),
                );
                
                if ($country_code !== 'US') {
                    $all_services['worldwide_expedited'] = __('Worldwide Expedited', 'woo-shipping-labels');
                    $all_services['worldwide_express'] = __('Worldwide Express', 'woo-shipping-labels');
                }
                break;
                
            case 'fedex':
                $all_services = array(
                    'ground' => __('Ground', 'woo-shipping-labels'),
                    'priority_overnight' => __('Priority Overnight', 'woo-shipping-labels'),
                    'standard_overnight' => __('Standard Overnight', 'woo-shipping-labels'),
                    '2day' => __('2Day', 'woo-shipping-labels'),
                    'express_saver' => __('Express Saver', 'woo-shipping-labels'),
                );
                
                if ($country_code !== 'US') {
                    $all_services['international_economy'] = __('International Economy', 'woo-shipping-labels');
                    $all_services['international_priority'] = __('International Priority', 'woo-shipping-labels');
                }
                break;
                
            case 'dhl':
                $all_services = array(
                    'express_easy' => __('Express Easy', 'woo-shipping-labels'),
                    'express_worldwide' => __('Express Worldwide', 'woo-shipping-labels'),
                    'economy_select' => __('Economy Select', 'woo-shipping-labels'),
                );
                break;
        }
        
        // Filter services to only include enabled ones
        $filtered_services = array();
        
        // If no services are specifically enabled, return all services (fallback)
        if (empty($enabled_services)) {
            error_log('No explicitly enabled services found for ' . $carrier_id . '. Using all services as fallback.');
            return $all_services;
        }
        
        // Only include services that are enabled in carrier settings
        foreach ($all_services as $service_id => $service_name) {
            if (isset($enabled_services[$service_id])) {
                $filtered_services[$service_id] = $service_name;
            }
        }
        
        error_log('Filtered services for ' . $carrier_id . ': ' . json_encode($filtered_services));
        
        return $filtered_services;
    }

    /**
     * AJAX handler for getting carrier services
     */
    public function ajax_get_carrier_services() {
        $country = isset($_POST['country']) ? sanitize_text_field($_POST['country']) : '';
        $carrier = isset($_POST['carrier']) ? sanitize_text_field($_POST['carrier']) : '';
        
        // Log incoming request
        error_log("AJAX request received: country=$country, carrier=$carrier");
        
        // Get carrier settings
        $carrier_settings = get_option('wsl_carrier_settings', array());
        $enabled_services = array();
        $filtered_services = array();
        
        // Log the carrier settings we're working with
        error_log("Carrier settings: " . json_encode($carrier_settings));
        
        // Check which services are enabled for this carrier
        if (isset($carrier_settings[$carrier]['services'])) {
            foreach ($carrier_settings[$carrier]['services'] as $service_id => $service_data) {
                if ((is_array($service_data) && !empty($service_data['enabled'])) || 
                    (!is_array($service_data) && $service_data)) {
                    $enabled_services[$service_id] = true;
                }
            }
        } else {
            error_log("No services configuration found for carrier: $carrier");
        }
        
        // Define service names based on carrier
        $all_services = array();
        
        if ($carrier == 'fedex') {
            // Define all FedEx services
            $all_services = array(
                'ground' => __('FedEx Ground', 'woo-shipping-labels'),
                'priority_overnight' => __('FedEx Priority Overnight', 'woo-shipping-labels'),
                'standard_overnight' => __('FedEx Standard Overnight', 'woo-shipping-labels'),
                '2day' => __('FedEx 2Day', 'woo-shipping-labels'),
                'express_saver' => __('FedEx Express Saver', 'woo-shipping-labels'),
                'international_economy' => __('FedEx International Economy', 'woo-shipping-labels'),
                'international_priority' => __('FedEx International Priority', 'woo-shipping-labels')
            );
            
            // For non-US countries like Israel, only show international services
            if ($country != 'US') {
                // Keep only international services
                $domestic = array('ground', 'priority_overnight', 'standard_overnight', '2day', 'express_saver');
                foreach ($domestic as $service) {
                    unset($all_services[$service]);
                }
            }
        }
        
        // If no services have been explicitly enabled, show all applicable services
        if (empty($enabled_services)) {
            error_log("No enabled services found for $carrier, using all applicable services");
            $filtered_services = $all_services;
        } else {
            // Filter to only include services that are both applicable to the country and enabled
            foreach ($all_services as $service_id => $service_name) {
                if (isset($enabled_services[$service_id])) {
                    $filtered_services[$service_id] = $service_name;
                }
            }
        }
        
        error_log("Returning filtered services: " . json_encode($filtered_services));
        
        wp_send_json_success($filtered_services);
        exit;
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        // Get base URL for plugin
        $base_url = plugin_dir_url(dirname(__FILE__));
        
        // Load common CSS on all admin pages
        wp_enqueue_style(
            'wsl-admin-common',
            $base_url . 'admin/css/admin-common.css',
            array(),
            filemtime(plugin_dir_path(dirname(__FILE__)) . 'admin/css/admin-common.css')
        );
        
        // Only load specific CSS for our plugin pages
        if (strpos($hook, 'wsl-shipping-labels') !== false || 
            strpos($hook, 'wsl-create-label') !== false || 
            strpos($hook, 'wsl-settings') !== false) {
            
            // Determine the current tab
            $current_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';
            
            // Load tab-specific CSS
            switch ($current_tab) {
                case 'general':
                    wp_enqueue_style(
                        'wsl-admin-tab-general',
                        $base_url . 'admin/css/admin-tab-general.css',
                        array('wsl-admin-common'),
                        filemtime(plugin_dir_path(dirname(__FILE__)) . 'admin/css/admin-tab-general.css')
                    );
                    break;
                    
                case 'currencies':
                    wp_enqueue_style(
                        'wsl-admin-tab-currencies',
                        $base_url . 'admin/css/admin-tab-currencies.css',
                        array('wsl-admin-common'),
                        filemtime(plugin_dir_path(dirname(__FILE__)) . 'admin/css/admin-tab-currencies.css')
                    );
                    break;
                    
                case 'packages':
                    wp_enqueue_style(
                        'wsl-admin-tab-packages',
                        $base_url . 'admin/css/admin-tab-packages.css',
                        array('wsl-admin-common'),
                        filemtime(plugin_dir_path(dirname(__FILE__)) . 'admin/css/admin-tab-packages.css')
                    );
                    break;
                    
                case 'countries':
                    wp_enqueue_style(
                        'wsl-admin-tab-countries',
                        $base_url . 'admin/css/admin-tab-countries.css',
                        array('wsl-admin-common'),
                        filemtime(plugin_dir_path(dirname(__FILE__)) . 'admin/css/admin-tab-countries.css')
                    );
                    break;
            }
            
            // Enqueue scripts needed for all tabs
            wp_enqueue_script('jquery');
            wp_enqueue_script('jquery-ui-core');
            wp_enqueue_script('jquery-ui-tabs');
        }
        
        // Redirect from old currencies page
        if ($hook == 'shipping-labels_page_wsl-currencies') {
            wp_redirect(admin_url('admin.php?page=wsl-settings&tab=currencies'));
            exit;
        }
    }
}