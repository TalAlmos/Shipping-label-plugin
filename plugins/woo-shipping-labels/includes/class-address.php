<?php
/**
 * Address handling functionality for the shipping label plugin
 */

if (!defined('WPINC')) {
    die;
}

class WSL_Address {
    
    /**
     * Get store address from WooCommerce settings
     * 
     * @return array Universal address format
     */
    public static function get_store_address() {
        $store_address = array(
            'name'       => get_bloginfo('name'),
            'company'    => get_bloginfo('name'),
            'attention'  => '',
            'phone'      => get_option('woocommerce_store_phone', ''),
            'email'      => get_option('woocommerce_store_email', get_option('admin_email')),
            'address_1'  => get_option('woocommerce_store_address', ''),
            'address_2'  => get_option('woocommerce_store_address_2', ''),
            'address_3'  => '',
            'city'       => get_option('woocommerce_store_city', ''),
            'postcode'   => get_option('woocommerce_store_postcode', ''),
            'country'    => '',
            'state'      => '',
            'residential' => false,
            'address_type' => 'commercial',
            'validation_status' => '',
            'carrier_data' => array(),
        );
        
        // Split country and state if needed
        $default_country = get_option('woocommerce_default_country', '');
        if (strpos($default_country, ':') !== false) {
            list($country, $state) = explode(':', $default_country);
            $store_address['country'] = $country;
            $store_address['state'] = $state;
        } else {
            $store_address['country'] = $default_country;
        }
        
        return $store_address;
    }
    
    /**
     * Get enhanced order address with all fields potentially needed by any carrier
     * 
     * @param WC_Order $order The order
     * @param string $type 'shipping' or 'billing'
     * @return array Universal address format
     */
    public static function get_order_address(WC_Order $order, $type = 'shipping') {
        // If shipping address is empty, fall back to billing
        if ($type === 'shipping' && !$order->get_shipping_address_1()) {
            $type = 'billing';
        }
        
        // Initialize the universal address structure
        $address = array(
            'name'       => '',
            'company'    => '',
            'attention'  => '',
            'phone'      => '',
            'email'      => '',
            'address_1'  => '',
            'address_2'  => '',
            'address_3'  => '',
            'city'       => '',
            'state'      => '',
            'postcode'   => '',
            'country'    => '',
            'residential' => true,
            'address_type' => 'residential',
            'validation_status' => '',
            'carrier_data' => array(),
        );
        
        // Fill in the address data from the order
        if ($type === 'shipping') {
            $first_name = $order->get_shipping_first_name();
            $last_name = $order->get_shipping_last_name();
            
            $address['name'] = trim($first_name . ' ' . $last_name);
            $address['company'] = $order->get_shipping_company();
            $address['address_1'] = $order->get_shipping_address_1();
            $address['address_2'] = $order->get_shipping_address_2();
            $address['city'] = $order->get_shipping_city();
            $address['state'] = $order->get_shipping_state();
            $address['postcode'] = $order->get_shipping_postcode();
            $address['country'] = $order->get_shipping_country();
            
            // For shipping address, try to get phone from order
            // WC 5.6+ has shipping phone, older versions don't
            $address['phone'] = method_exists($order, 'get_shipping_phone') ? 
                $order->get_shipping_phone() : $order->get_billing_phone();
                
            // Always use billing email as WC doesn't have shipping email
            $address['email'] = $order->get_billing_email();
        } else {
            $address['name'] = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
            $address['company'] = $order->get_billing_company();
            $address['address_1'] = $order->get_billing_address_1();
            $address['address_2'] = $order->get_billing_address_2();
            $address['city'] = $order->get_billing_city();
            $address['state'] = $order->get_billing_state();
            $address['postcode'] = $order->get_billing_postcode();
            $address['country'] = $order->get_billing_country();
            $address['phone'] = $order->get_billing_phone();
            $address['email'] = $order->get_billing_email();
        }
        
        // If we have a company, consider it a commercial address
        if (!empty($address['company'])) {
            $address['residential'] = false;
            $address['address_type'] = 'commercial';
        }
        
        // Use "Customer" as fallback if no name is available
        if (empty($address['name'])) {
            $address['name'] = __('Customer', 'woo-shipping-labels');
        }
        
        return $address;
    }
    
    /**
     * Format an address for display
     * 
     * @param array $address Address data
     * @param bool $as_html Whether to return HTML or array
     * @return string|array Formatted address as HTML or array
     */
    public static function format_address($address, $as_html = true) {
        if (!is_array($address)) {
            return $as_html ? '' : array();
        }
        
        $html_output = '';
        $array_output = array();
        
        // Add name as a header
        if (!empty($address['name'])) {
            $html_output .= '<p class="wsl-address-name"><strong>' . esc_html($address['name']) . '</strong></p>';
            $array_output['name'] = $address['name'];
        }
        
        // Add company if available
        if (!empty($address['company'])) {
            $html_output .= '<p class="wsl-address-company">' . esc_html($address['company']) . '</p>';
            $array_output['company'] = $address['company'];
        }
        
        // Add attention line if available
        if (!empty($address['attention'])) {
            $html_output .= '<p class="wsl-address-attention">Attn: ' . esc_html($address['attention']) . '</p>';
            $array_output['attention'] = $address['attention'];
        }
        
        // Add address lines
        if (!empty($address['address_1'])) {
            $html_output .= '<p class="wsl-address-line"><em>' . esc_html($address['address_1']) . '</em></p>';
            $array_output['address_1'] = $address['address_1'];
        }
        
        if (!empty($address['address_2'])) {
            $html_output .= '<p class="wsl-address-line"><em>' . esc_html($address['address_2']) . '</em></p>';
            $array_output['address_2'] = $address['address_2'];
        }
        
        if (!empty($address['address_3'])) {
            $html_output .= '<p class="wsl-address-line"><em>' . esc_html($address['address_3']) . '</em></p>';
            $array_output['address_3'] = $address['address_3'];
        }
        
        // City, state, postcode line
        $location_line = '';
        $location_parts = array();
        
        if (!empty($address['city'])) {
            $location_line .= esc_html($address['city']);
            $location_parts[] = $address['city'];
        }
        
        if (!empty($address['state'])) {
            $location_line .= (!empty($location_line) ? ', ' : '') . esc_html($address['state']);
            $location_parts[] = $address['state'];
        }
        
        if (!empty($address['postcode'])) {
            $location_line .= ' ' . esc_html($address['postcode']);
            $location_parts[] = $address['postcode'];
        }
        
        if (!empty($location_line)) {
            $html_output .= '<p class="wsl-address-line"><em>' . $location_line . '</em></p>';
            $array_output['location'] = implode(', ', $location_parts);
        }
        
        // Country
        if (!empty($address['country'])) {
            $country_name = WC()->countries->get_countries()[$address['country']] ?? $address['country'];
            $html_output .= '<p class="wsl-address-line"><em>' . esc_html($country_name) . '</em></p>';
            $array_output['country'] = $country_name;
        }
        
        // Phone and email (only show in admin context)
        if (is_admin()) {
            if (!empty($address['phone'])) {
                $html_output .= '<p class="wsl-address-phone"><em>' . esc_html($address['phone']) . '</em></p>';
                $array_output['phone'] = $address['phone'];
            }
            
            if (!empty($address['email'])) {
                $html_output .= '<p class="wsl-address-email"><em>' . esc_html($address['email']) . '</em></p>';
                $array_output['email'] = $address['email'];
            }
        }
        
        return $as_html ? $html_output : $array_output;
    }
    
    /**
     * Normalize a country value to ensure we get a valid 2-letter country code
     * 
     * @param string $country_value A country name, code or other identifier
     * @return string The normalized 2-letter country code, or original value if not found
     */
    public static function normalize_country_code($country_value) {
        // If it's empty or already a valid 2-letter code, return it
        if (empty($country_value) || (strlen($country_value) == 2 && ctype_alpha($country_value))) {
            return strtoupper($country_value);
        }
        
        // Common country name variations
        $country_mapping = array(
            // United States variations
            'UNITED STATES' => 'US',
            'UNITED STATES OF AMERICA' => 'US',
            'USA' => 'US',
            'U.S.A.' => 'US',
            'U.S.' => 'US',
            
            // Israel variations
            'ISRAEL' => 'IL',
            
            // United Kingdom variations
            'UNITED KINGDOM' => 'GB',
            'UK' => 'GB',
            'GREAT BRITAIN' => 'GB',
            'ENGLAND' => 'GB',
            
            // Add more common variations as needed
        );
        
        // Normalize the input by converting to uppercase
        $normalized = strtoupper(trim($country_value));
        
        // Check if we have a direct mapping
        if (isset($country_mapping[$normalized])) {
            return $country_mapping[$normalized];
        }
        
        // If we have WooCommerce loaded, check against country list
        if (function_exists('WC')) {
            $wc_countries = WC()->countries->get_countries();
            
            // First check if this is already a valid code
            if (isset($wc_countries[strtoupper($country_value)])) {
                return strtoupper($country_value);
            }
            
            // Otherwise search through country names
            foreach ($wc_countries as $code => $name) {
                if (strtoupper($name) === $normalized) {
                    return $code;
                }
            }
        }
        
        // If we couldn't find a match, return the original value
        return $country_value;
    }
    
    /**
     * Validate address with standardized response format
     * 
     * @param array $address The address to validate
     * @param string $carrier Which carrier to use for validation (defaults to FedEx)
     * @return array Standardized validation result
     */
    public static function validate_address($address, $carrier = 'fedex') {
        // Define our standard result format
        $result = array(
            'success' => false,
            'is_valid' => false,
            'original_address' => $address,
            'standardized_address' => array(),
            'messages' => array(),
            'classification' => '', // residential, commercial, etc.
        );
        
        // If no carrier specified or invalid carrier, just do basic validation
        if (empty($carrier) || !in_array($carrier, array('fedex', 'ups', 'dhl', 'usps'))) {
            return self::perform_basic_validation($address, $result);
        }
        
        // Currently, we only have FedEx implementation
        if ($carrier === 'fedex') {
            return self::validate_with_fedex($address, $result);
        }
        
        // For any other carrier, fall back to basic validation for now
        $result['messages'][] = sprintf(
            __('%s address validation is not yet implemented.', 'woo-shipping-labels'),
            strtoupper($carrier)
        );
        
        return self::perform_basic_validation($address, $result);
    }
    
    /**
     * Validate an address with FedEx and convert to our standard format
     * 
     * @param array $address Address to validate
     * @param array $result Base result structure to fill in
     * @return array Completed result
     */
    private static function validate_with_fedex($address, $result) {
        try {
            // Ensure we have the required classes
            if (!class_exists('WSL_FedEx_Auth') || !class_exists('WSL_FedEx_Address_Validation')) {
                require_once WSL_PLUGIN_DIR . 'includes/api/class-fedex-auth.php';
                require_once WSL_PLUGIN_DIR . 'includes/api/class-fedex-address-validation.php';
            }
            
            // Create validator and validate address
            $validator = new WSL_FedEx_Address_Validation();
            $api_result = $validator->validate_address($address);
            
            // Process API result
            if ($api_result['success']) {
                $result['success'] = true;
                
                // Extract validated address if available
                if (!empty($api_result['addresses']) && is_array($api_result['addresses'])) {
                    $fedex_address = $api_result['addresses'][0]; // Get first address
                    
                    // Set validity based on status
                    $result['is_valid'] = ($fedex_address['status'] === 'valid');
                    
                    // Convert FedEx format to our standardized format
                    if (!empty($fedex_address['address'])) {
                        $std_address = $fedex_address['address'];
                        
                        $result['standardized_address'] = array(
                            'name' => $address['name'] ?? '', // Keep original name
                            'company' => $address['company'] ?? '', // Keep original company
                            'attention' => $address['attention'] ?? '', // Keep original attention
                            'phone' => $address['phone'] ?? '', // Keep original phone
                            'email' => $address['email'] ?? '', // Keep original email
                            'address_1' => !empty($std_address['street_lines']) ? 
                                $std_address['street_lines'][0] : '',
                            'address_2' => (!empty($std_address['street_lines']) && isset($std_address['street_lines'][1])) ? 
                                $std_address['street_lines'][1] : '',
                            'address_3' => (!empty($std_address['street_lines']) && isset($std_address['street_lines'][2])) ? 
                                $std_address['street_lines'][2] : '',
                            'city' => $std_address['city'] ?? '',
                            'state' => $std_address['state'] ?? '',
                            'postcode' => $std_address['postal_code'] ?? '',
                            'country' => $std_address['country'] ?? '',
                            'residential' => ($std_address['classification'] ?? '') === 'RESIDENTIAL',
                            'address_type' => strtolower($std_address['classification'] ?? 'unknown'),
                            'validation_status' => 'validated',
                        );
                        
                        // Set address classification if available
                        if (!empty($std_address['classification'])) {
                            $result['classification'] = $std_address['classification'];
                        }
                    }
                    
                    // Add any messages from FedEx
                    if (!empty($api_result['alerts'])) {
                        foreach ($api_result['alerts'] as $alert) {
                            $result['messages'][] = $alert['message'] ?? 'Unknown alert';
                        }
                    }
                } else {
                    // If no address data but API call was successful
                    $result['is_valid'] = false;
                    $result['messages'][] = __('No valid address suggestions returned.', 'woo-shipping-labels');
                }
            } else {
                // API call failed
                $result['messages'][] = !empty($api_result['error']) ? 
                    $api_result['error'] : __('Address validation failed.', 'woo-shipping-labels');
            }
        } catch (Exception $e) {
            $result['messages'][] = sprintf(
                __('Exception during address validation: %s', 'woo-shipping-labels'),
                $e->getMessage()
            );
        }
        
        return $result;
    }
    
    /**
     * Perform basic client-side address validation when carrier API isn't available
     * 
     * @param array $address Address to validate
     * @param array $result Base result structure to fill in
     * @return array Completed result
     */
    private static function perform_basic_validation($address, $result) {
        $is_valid = true;
        $messages = array();
        
        // Check required fields
        if (empty($address['address_1'])) {
            $is_valid = false;
            $messages[] = __('Address line 1 is required.', 'woo-shipping-labels');
        }
        
        if (empty($address['city'])) {
            $is_valid = false;
            $messages[] = __('City is required.', 'woo-shipping-labels');
        }
        
        if (empty($address['postcode'])) {
            $is_valid = false;
            $messages[] = __('ZIP/Postal code is required.', 'woo-shipping-labels');
        }
        
        if (empty($address['country'])) {
            $is_valid = false;
            $messages[] = __('Country is required.', 'woo-shipping-labels');
        } else {
            // Check if state/province is required for this country
            $countries_requiring_state = array('US', 'CA', 'AU', 'BR', 'IN');
            if (in_array($address['country'], $countries_requiring_state) && empty($address['state'])) {
                $is_valid = false;
                $messages[] = __('State/Province is required for this country.', 'woo-shipping-labels');
            }
        }
        
        // Set result fields
        $result['success'] = true; // The validation function succeeded, even if address is invalid
        $result['is_valid'] = $is_valid;
        $result['standardized_address'] = $address; // Just return the original address
        $result['messages'] = $messages;
        
        return $result;
    }
} 