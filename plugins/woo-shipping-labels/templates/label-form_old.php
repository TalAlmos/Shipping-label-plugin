<!-- TEMPLATES LABEL FORM IS BEING LOADED -->
<?php
/**
 * Template for the label creation form
 * 
 * @var WC_Order $order The WooCommerce order
 */

if (!defined('WPINC')) {
    die;
}

// Get address data
$from_address = WSL_Address::get_store_address();
$to_address = WSL_Address::get_order_address($order, 'shipping');

?>

<div class="wsl-label-form">
    <h2><?php _e('Shipping Label Information', 'woo-shipping-labels'); ?></h2>

    <!-- New wrapper to place addresses side by side -->
    <div class="wsl-addresses-wrapper">
        <?php 
        // Use the admin class to render addresses consistently
        $admin = new WSL_Admin();
        echo $admin->render_address_section('from', $from_address);
        echo $admin->render_address_section('to', $to_address);
        ?>
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

<div id="label-result" style="display: none; margin-top: 20px;"></div>

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
            '<tr><td><input type="radio" name="shipping_option" value="fedex_ground"></td><td><img src="' + wsl_ajax.plugin_url + 'assets/images/FedEx.png" alt="FedEx" class="carrier-logo"></td><td>Ground</td><td>3-5 days</td><td>$12.50</td></tr>' +
            '<tr><td><input type="radio" name="shipping_option" value="ups_ground"></td><td><img src="' + wsl_ajax.plugin_url + 'assets/images/ups.png" alt="UPS" class="carrier-logo"></td><td>Ground</td><td>3-5 days</td><td>$13.25</td></tr>' +
            '<tr><td><input type="radio" name="shipping_option" value="dhl_express"></td><td><img src="' + wsl_ajax.plugin_url + 'assets/images/DHL.png" alt="DHL" class="carrier-logo"></td><td>Express</td><td>2-3 days</td><td>$18.75</td></tr>' +
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
        var addressType = formId === 'ship-from-edit' ? 'from' : 'to';
        
        // Collect address data from form fields
        var addressData = collectAddressData(addressType);
        
        // Send to server to format properly
        $.ajax({
            url: wsl_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wsl_format_address',
                security: wsl_ajax.nonce,
                address_data: addressData
            },
            success: function(response) {
                if (response.success) {
                    $display.html(response.data.formatted_address);
                    $form.hide();
                    $display.show();
                }
            }
        });
    });
    
    // Validate address
    $('.wsl-validate-address').on('click', function() {
        var addressType = $(this).data('address-type');
        var $resultContainer = $('.wsl-' + addressType + '-validation-result');
        
        // Show loading message
        $resultContainer.html('<div class="notice notice-info"><p>' + wsl_ajax.i18n.validating + '</p></div>');
        
        // Collect address data
        var addressData = collectAddressData(addressType);
        
        // Call validation AJAX endpoint
        $.ajax({
            url: wsl_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wsl_validate_address',
                security: wsl_ajax.nonce,
                address_type: addressType,
                address_data: addressData
            },
            success: function(response) {
                if (response.is_valid) {
                    $resultContainer.html(
                        '<div class="notice notice-success"><p>' + 
                        wsl_ajax.i18n.valid_address + 
                        '</p></div>'
                    );
                } else {
                    var messages = '';
                    if (response.messages && response.messages.length) {
                        messages = '<ul>';
                        response.messages.forEach(function(message) {
                            messages += '<li>' + escapeHtml(message) + '</li>';
                        });
                        messages += '</ul>';
                    }
                    
                    $resultContainer.html(
                        '<div class="notice notice-warning"><p>' + 
                        wsl_ajax.i18n.invalid_address + 
                        '</p>' + messages + '</div>'
                    );
                }
            },
            error: function() {
                $resultContainer.html(
                    '<div class="notice notice-error"><p>' + 
                    wsl_ajax.i18n.error + 
                    '</p></div>'
                );
            }
        });
    });
    
    // Helper to collect address data from form fields
    function collectAddressData(addressType) {
        var prefix = addressType === 'from' ? 'from_' : 'to_';
        
        return {
            name: $('#' + prefix + 'name').val(),
            address_1: $('#' + prefix + 'address_1').val(),
            address_2: $('#' + prefix + 'address_2').val(),
            city: $('#' + prefix + 'city').val(),
            postcode: $('#' + prefix + 'postcode').val(),
            state: $('#' + prefix + 'state').val(),
            country: $('#' + prefix + 'country').val()
        };
    }
    
    // Helper function to safely escape HTML
    function escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
});
</script>