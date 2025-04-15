<?php
/**
 * Address Renderer Class
 *
 * @package WooShippingLabels
 */

if (!defined('WPINC')) {
    die;
}

/**
 * Address Renderer class - handles rendering address forms and displays
 */
class WSL_Address_Renderer {

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
        echo '<div class="wsl-form-field wsl-state-field">';
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
        
        // Action buttons
        echo '<div class="wsl-address-actions">';
        echo '<button type="button" class="button wsl-save-address" data-address-type="' . esc_attr($address_type) . '">' . __('Save Address', 'woo-shipping-labels') . '</button>';
        echo '<button type="button" class="button wsl-cancel-edit" data-address-type="' . esc_attr($address_type) . '">' . __('Cancel', 'woo-shipping-labels') . '</button>';
        echo '</div>';
        
        echo '</div>'; // .wsl-address-edit
        
        // Edit button for display view
        echo '<div class="wsl-address-actions">';
        echo '<button type="button" class="button wsl-edit-address" data-address-type="' . esc_attr($address_type) . '">' . __('Edit Address', 'woo-shipping-labels') . '</button>';
        echo '</div>';
        
        echo '</div>'; // .wsl-address-column
        
        // Add JavaScript for address editing and country/state handling
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Edit address button
            $('.wsl-edit-address').on('click', function() {
                var addressType = $(this).data('address-type');
                $('.wsl-address-display[data-address-type="' + addressType + '"]').hide();
                $('.wsl-address-edit[data-address-type="' + addressType + '"]').show();
                $(this).hide();
            });
            
            // Cancel edit button
            $('.wsl-cancel-edit').on('click', function() {
                var addressType = $(this).data('address-type');
                $('.wsl-address-edit[data-address-type="' + addressType + '"]').hide();
                $('.wsl-address-display[data-address-type="' + addressType + '"]').show();
                $('.wsl-edit-address[data-address-type="' + addressType + '"]').show();
            });
            
            // Save address button
            $('.wsl-save-address').on('click', function() {
                var addressType = $(this).data('address-type');
                // Here you would normally save the address data via AJAX
                // For now, just toggle the display
                $('.wsl-address-edit[data-address-type="' + addressType + '"]').hide();
                $('.wsl-address-display[data-address-type="' + addressType + '"]').show();
                $('.wsl-edit-address[data-address-type="' + addressType + '"]').show();
                
                // TODO: Update the display with the new values
            });
            
            // Country change handler for dynamic state/province field
            $('.wsl-country-select').on('change', function() {
                var country = $(this).val();
                var addressType = $(this).attr('id').replace('_country', '');
                var stateField = $('#' + addressType + '_state');
                var stateWrapper = stateField.closest('.wsl-state-field');
                
                // AJAX call to get states for the selected country
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wsl_get_states',
                        country: country,
                        nonce: '<?php echo wp_create_nonce('wsl_get_states'); ?>'
                    },
                    beforeSend: function() {
                        // Block the state field while loading
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
} 