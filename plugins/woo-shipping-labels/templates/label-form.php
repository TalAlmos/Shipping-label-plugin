<?php
/**
 * Template for the label creation form
 * 
 * @var WC_Order $order The WooCommerce order
 */

if (!defined('WPINC')) {
    die;
}
?>

<div class="wsl-label-form">
    <h2><?php _e('Shipping Label Information', 'woo-shipping-labels'); ?></h2>

    <!-- New wrapper to place addresses side by side -->
    <div class="wsl-addresses-wrapper">
        <div class="ship-from">
            <h3>
                <?php _e('Ship From Address', 'woo-shipping-labels'); ?>
                <button type="button" class="button edit-address" data-target="ship-from-display" data-form="ship-from-edit"><?php _e('Edit', 'woo-shipping-labels'); ?></button>
            </h3>
            <div id="ship-from-display" class="address-display">
            <?php
                // Retrieve the store's business name and output as Bold
                $business_name = get_bloginfo('name');
                echo '<p><strong>' . esc_html($business_name) . '</strong></p>';

                // Get individual address fields from WooCommerce settings
                $store_address_line1 = get_option('woocommerce_store_address');
                $store_address_line2 = get_option('woocommerce_store_address_2');
                $store_city          = get_option('woocommerce_store_city');
                $store_postcode      = get_option('woocommerce_store_postcode');
                $default_country     = get_option('woocommerce_default_country'); // May be in format "US:CA" (Country:State)

                // Split state and country if a colon exists
                $store_country = '';
                $store_state   = '';
                if (strpos($default_country, ':') !== false) {
                    list($store_country, $store_state) = explode(':', $default_country);
                } else {
                    $store_country = $default_country;
                }

                // Output each address component in individual lines as Italic if not empty
                if ($store_address_line1) {
                    echo '<p><em>' . esc_html($store_address_line1) . '</em></p>';
                }
                if ($store_address_line2) {
                    echo '<p><em>' . esc_html($store_address_line2) . '</em></p>';
                }
                if ($store_city) {
                    echo '<p><em>' . esc_html($store_city) . '</em></p>';
                }
                if ($store_postcode) {
                    echo '<p><em>' . esc_html($store_postcode) . '</em></p>';
                }
                if ($store_state) {
                    echo '<p><em>' . esc_html($store_state) . '</em></p>';
                }
                if ($store_country) {
                    echo '<p><em>' . esc_html($store_country) . '</em></p>';
                }
            ?>
            </div>
            <div id="ship-from-edit" class="address-edit" style="display:none;">
                <div class="wsl-field">
                    <label for="from_business_name"><?php _e('Business Name:', 'woo-shipping-labels'); ?></label>
                    <input type="text" id="from_business_name" name="from_business_name" value="<?php echo esc_attr($business_name); ?>">
                </div>
                <div class="wsl-field">
                    <label for="from_address_line1"><?php _e('Address Line 1:', 'woo-shipping-labels'); ?></label>
                    <input type="text" id="from_address_line1" name="from_address_line1" value="<?php echo esc_attr($store_address_line1); ?>">
                </div>
                <div class="wsl-field">
                    <label for="from_address_line2"><?php _e('Address Line 2:', 'woo-shipping-labels'); ?></label>
                    <input type="text" id="from_address_line2" name="from_address_line2" value="<?php echo esc_attr($store_address_line2); ?>">
                </div>
                <div class="wsl-field">
                    <label for="from_city"><?php _e('City:', 'woo-shipping-labels'); ?></label>
                    <input type="text" id="from_city" name="from_city" value="<?php echo esc_attr($store_city); ?>">
                </div>
                <div class="wsl-field">
                    <label for="from_postcode"><?php _e('Zip/Postal Code:', 'woo-shipping-labels'); ?></label>
                    <input type="text" id="from_postcode" name="from_postcode" value="<?php echo esc_attr($store_postcode); ?>">
                </div>
                <div class="wsl-field">
                    <label for="from_state"><?php _e('State/Province:', 'woo-shipping-labels'); ?></label>
                    <input type="text" id="from_state" name="from_state" value="<?php echo esc_attr($store_state); ?>">
                </div>
                <div class="wsl-field">
                    <label for="from_country"><?php _e('Country:', 'woo-shipping-labels'); ?></label>
                    <input type="text" id="from_country" name="from_country" value="<?php echo esc_attr($store_country); ?>">
                </div>
                <div class="wsl-field-actions">
                    <button type="button" class="button save-address" data-target="ship-from-edit" data-display="ship-from-display"><?php _e('Save', 'woo-shipping-labels'); ?></button>
                    <button type="button" class="button cancel-edit" data-target="ship-from-edit" data-display="ship-from-display"><?php _e('Cancel', 'woo-shipping-labels'); ?></button>
                </div>
            </div>
        </div>
        <div class="ship-to">
            <h3>
                <?php _e('Ship To Address', 'woo-shipping-labels'); ?>
                <button type="button" class="button edit-address" data-target="ship-to-display" data-form="ship-to-edit"><?php _e('Edit', 'woo-shipping-labels'); ?></button>
            </h3>
            <div id="ship-to-display" class="address-display">
            <?php 
                // Retrieve order shipping details and display them line by line
                // Instead of the shipping company, we display the Customer Name
                $first_name = $order->get_shipping_first_name();
                $last_name = $order->get_shipping_last_name();
                
                // If shipping name is empty, use billing name
                if (empty($first_name) && empty($last_name)) {
                    $first_name = $order->get_billing_first_name();
                    $last_name = $order->get_billing_last_name();
                }
                
                $customer_name = trim($first_name . ' ' . $last_name);
                
                // Always show a name - use "Customer" as fallback if no name is available
                if (!empty($customer_name)) {
                    echo '<p><strong>' . esc_html($customer_name) . '</strong></p>';
                } else {
                    echo '<p><strong>' . __('Customer', 'woo-shipping-labels') . '</strong></p>';
                }

                $shipping_address1 = $order->get_shipping_address_1();
                $shipping_address2 = $order->get_shipping_address_2();
                $shipping_city     = $order->get_shipping_city();
                $shipping_postcode = $order->get_shipping_postcode();
                $shipping_state    = $order->get_shipping_state();
                $shipping_country  = $order->get_shipping_country();

                if ($shipping_address1) {
                    echo '<p><em>' . esc_html($shipping_address1) . '</em></p>';
                }
                if ($shipping_address2) {
                    echo '<p><em>' . esc_html($shipping_address2) . '</em></p>';
                }
                if ($shipping_city) {
                    echo '<p><em>' . esc_html($shipping_city) . '</em></p>';
                }
                if ($shipping_postcode) {
                    echo '<p><em>' . esc_html($shipping_postcode) . '</em></p>';
                }
                if ($shipping_state) {
                    echo '<p><em>' . esc_html($shipping_state) . '</em></p>';
                }
                if ($shipping_country) {
                    echo '<p><em>' . esc_html($shipping_country) . '</em></p>';
                }
            ?>
            </div>
            <div id="ship-to-edit" class="address-edit" style="display:none;">
                <div class="wsl-field">
                    <label for="to_name"><?php _e('Customer Name:', 'woo-shipping-labels'); ?></label>
                    <input type="text" id="to_name" name="to_name" value="<?php echo esc_attr($customer_name); ?>">
                </div>
                <div class="wsl-field">
                    <label for="to_address_line1"><?php _e('Address Line 1:', 'woo-shipping-labels'); ?></label>
                    <input type="text" id="to_address_line1" name="to_address_line1" value="<?php echo esc_attr($shipping_address1); ?>">
                </div>
                <div class="wsl-field">
                    <label for="to_address_line2"><?php _e('Address Line 2:', 'woo-shipping-labels'); ?></label>
                    <input type="text" id="to_address_line2" name="to_address_line2" value="<?php echo esc_attr($shipping_address2); ?>">
                </div>
                <div class="wsl-field">
                    <label for="to_city"><?php _e('City:', 'woo-shipping-labels'); ?></label>
                    <input type="text" id="to_city" name="to_city" value="<?php echo esc_attr($shipping_city); ?>">
                </div>
                <div class="wsl-field">
                    <label for="to_postcode"><?php _e('Zip/Postal Code:', 'woo-shipping-labels'); ?></label>
                    <input type="text" id="to_postcode" name="to_postcode" value="<?php echo esc_attr($shipping_postcode); ?>">
                </div>
                <div class="wsl-field">
                    <label for="to_state"><?php _e('State/Province:', 'woo-shipping-labels'); ?></label>
                    <input type="text" id="to_state" name="to_state" value="<?php echo esc_attr($shipping_state); ?>">
                </div>
                <div class="wsl-field">
                    <label for="to_country"><?php _e('Country:', 'woo-shipping-labels'); ?></label>
                    <input type="text" id="to_country" name="to_country" value="<?php echo esc_attr($shipping_country); ?>">
                </div>
                <div class="wsl-field-actions">
                    <button type="button" class="button save-address" data-target="ship-to-edit" data-display="ship-to-display"><?php _e('Save', 'woo-shipping-labels'); ?></button>
                    <button type="button" class="button cancel-edit" data-target="ship-to-edit" data-display="ship-to-display"><?php _e('Cancel', 'woo-shipping-labels'); ?></button>
                </div>
            </div>
        </div>
    </div>
    
    <form method="post" action="">
        <h2><?php _e('Package Details', 'woo-shipping-labels'); ?></h2>
        
        <div class="wsl-package-selection">
            <label for="package_type"><?php _e('Package Type:', 'woo-shipping-labels'); ?></label>
            <select name="package_type" id="package_type">
                <option value="small_box"><?php _e('Small Box (30x20x10cm, max 5kg)', 'woo-shipping-labels'); ?></option>
                <option value="large_box"><?php _e('Large Box (50x50x30cm, max 20kg)', 'woo-shipping-labels'); ?></option>
                <option value="custom"><?php _e('Custom Package', 'woo-shipping-labels'); ?></option>
            </select>
        </div>
        
        <div class="wsl-field">
            <label for="package_weight"><?php _e('Package Weight (kg):', 'woo-shipping-labels'); ?></label>
            <input type="number" name="package_weight" id="package_weight" step="0.01" min="0.01" required>
        </div>
        
        <div class="wsl-field">
            <label for="goods_description"><?php _e('Description Of Goods:', 'woo-shipping-labels'); ?></label>
            <textarea name="goods_description" id="goods_description" rows="3" required></textarea>
        </div>
        
        <div class="wsl-custom-package" style="display:none;">
            <h3><?php _e('Custom Package Dimensions', 'woo-shipping-labels'); ?></h3>
            
            <div class="wsl-field">
                <label for="package_length"><?php _e('Length (cm):', 'woo-shipping-labels'); ?></label>
                <input type="number" name="package_length" id="package_length" step="0.1" min="0.1">
            </div>
            
            <div class="wsl-field">
                <label for="package_width"><?php _e('Width (cm):', 'woo-shipping-labels'); ?></label>
                <input type="number" name="package_width" id="package_width" step="0.1" min="0.1">
            </div>
            
            <div class="wsl-field">
                <label for="package_height"><?php _e('Height (cm):', 'woo-shipping-labels'); ?></label>
                <input type="number" name="package_height" id="package_height" step="0.1" min="0.1">
            </div>
        </div>
        
        <h2><?php _e('Shipping Options', 'woo-shipping-labels'); ?></h2>
        <p><?php _e('Shipping options will appear after package details are entered.', 'woo-shipping-labels'); ?></p>
        
        <div class="wsl-shipping-options" style="display:none;">
            <!-- This will be populated via AJAX after package details are entered -->
        </div>
        
        <p>
            <input type="hidden" name="order_id" value="<?php echo $order->get_id(); ?>">
            <button type="button" class="button button-primary" id="wsl-calculate-rates"><?php _e('Calculate Shipping Options', 'woo-shipping-labels'); ?></button>
            <button type="submit" class="button button-primary" id="wsl-generate-label" style="display:none;"><?php _e('Generate Label', 'woo-shipping-labels'); ?></button>
        </p>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // Toggle custom package fields based on selection
    $('#package_type').on('change', function() {
        if ($(this).val() === 'custom') {
            $('.wsl-custom-package').show();
        } else {
            $('.wsl-custom-package').hide();
        }
    });
    
    // For demo purposes - in real implementation this would call AJAX
    $('#wsl-calculate-rates').on('click', function() {
        // This is just a placeholder for demonstration
        $('.wsl-shipping-options').html(
            '<table class="wsl-rates-table">' +
            '<thead><tr><th>Select</th><th>Carrier</th><th>Service</th><th>Transit Time</th><th>Cost</th></tr></thead>' +
            '<tbody>' +
            '<tr><td><input type="radio" name="shipping_option" value="fedex_ground"></td><td><img src="<?php echo esc_url(WSL_PLUGIN_URL); ?>assets/images/FedEx.png" alt="FedEx" class="carrier-logo"></td><td>Ground</td><td>3-5 days</td><td>$12.50</td></tr>' +
            '<tr><td><input type="radio" name="shipping_option" value="ups_ground"></td><td><img src="<?php echo esc_url(WSL_PLUGIN_URL); ?>assets/images/ups.png" alt="UPS" class="carrier-logo"></td><td>Ground</td><td>3-5 days</td><td>$13.25</td></tr>' +
            '<tr><td><input type="radio" name="shipping_option" value="dhl_express"></td><td><img src="<?php echo esc_url(WSL_PLUGIN_URL); ?>assets/images/DHL.png" alt="DHL" class="carrier-logo"></td><td>Express</td><td>2-3 days</td><td>$18.75</td></tr>' +
            '</tbody></table>'
        ).show();
        
        $('#wsl-generate-label').show();
    });

    // Address editing functionality
    
    // Show edit form and hide address display
    $('.edit-address').on('click', function() {
        var displayId = $(this).data('target');
        var formId = $(this).data('form');
        
        $('#' + displayId).hide();
        $('#' + formId).show();
    });
    
    // Cancel edit - hide form and show display
    $('.cancel-edit').on('click', function() {
        var formId = $(this).data('target');
        var displayId = $(this).data('display');
        
        $('#' + formId).hide();
        $('#' + displayId).show();
    });
    
    // Save address - update the display with form values and hide form
    $('.save-address').on('click', function() {
        var formId = $(this).data('target');
        var displayId = $(this).data('display');
        var $form = $('#' + formId);
        var $display = $('#' + displayId);
        var html = '';
        
        // Ship From address
        if (formId === 'ship-from-edit') {
            var businessName = $('#from_business_name').val();
            var addressLine1 = $('#from_address_line1').val();
            var addressLine2 = $('#from_address_line2').val();
            var city = $('#from_city').val();
            var postcode = $('#from_postcode').val();
            var state = $('#from_state').val();
            var country = $('#from_country').val();
            
            html += '<p><strong>' + businessName + '</strong></p>';
            if (addressLine1) html += '<p><em>' + addressLine1 + '</em></p>';
            if (addressLine2) html += '<p><em>' + addressLine2 + '</em></p>';
            if (city) html += '<p><em>' + city + '</em></p>';
            if (postcode) html += '<p><em>' + postcode + '</em></p>';
            if (state) html += '<p><em>' + state + '</em></p>';
            if (country) html += '<p><em>' + country + '</em></p>';
        }
        
        // Ship To address
        if (formId === 'ship-to-edit') {
            var customerName = $('#to_name').val();
            var toAddressLine1 = $('#to_address_line1').val();
            var toAddressLine2 = $('#to_address_line2').val();
            var toCity = $('#to_city').val();
            var toPostcode = $('#to_postcode').val();
            var toState = $('#to_state').val();
            var toCountry = $('#to_country').val();
            
            html += '<p><strong>' + customerName + '</strong></p>';
            if (toAddressLine1) html += '<p><em>' + toAddressLine1 + '</em></p>';
            if (toAddressLine2) html += '<p><em>' + toAddressLine2 + '</em></p>';
            if (toCity) html += '<p><em>' + toCity + '</em></p>';
            if (toPostcode) html += '<p><em>' + toPostcode + '</em></p>';
            if (toState) html += '<p><em>' + toState + '</em></p>';
            if (toCountry) html += '<p><em>' + toCountry + '</em></p>';
        }
        
        $display.html(html);
        $form.hide();
        $display.show();
    });
});
</script>