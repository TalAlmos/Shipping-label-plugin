<?php
// Ensure all required class files are loaded
include_once plugin_dir_path(__FILE__) . '../../includes/class-carrier-manager.php';
include_once plugin_dir_path(__FILE__) . '../../includes/class-package-manager.php';
include_once plugin_dir_path(__FILE__) . '../../includes/class-address-renderer.php';
?>
<!-- ADMIN PARTIALS LABEL FORM IS BEING LOADED -->
<?php
/**
 * Shipping label creation form
 * This is a partial file that includes the package selection component
 */

if (!defined('WPINC')) {
    die;
}

// Get necessary data for the form
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
$order = wc_get_order($order_id);

if (!$order) {
    _e('Order not found.', 'woo-shipping-labels');
    return;
}

// Initialize base shipment data
$shipment_data = array(
    'carrier' => 'fedex', // Default carrier
    'package_type' => 'user', // Default package type
    'service_type' => '', // To be populated based on carrier
);

// Load address data for from/to addresses
$address_manager = new WSL_Address();
$from_address = $address_manager->get_store_address();
$to_address = $address_manager->get_order_address($order, 'shipping');

// Create nonce for AJAX actions
$ajax_nonce = wp_create_nonce('wsl_ajax');
?>

<div class="wsl-label-form-container">
    <h2><?php _e('Create Shipping Label', 'woo-shipping-labels'); ?></h2>
    
    <form id="wsl_label_form" method="post">
        <?php wp_nonce_field('wsl_create_label'); ?>
        <input type="hidden" name="order_id" value="<?php echo esc_attr($order_id); ?>">
        
        <!-- Address Information -->
        <div class="wsl-form-section wsl-addresses">
            <h3><?php _e('Address Information', 'woo-shipping-labels'); ?></h3>
            <div class="wsl-addresses-wrapper">
                <?php
                // Instantiate Address Renderer to use render_address_section
                $address_renderer = new WSL_Address_Renderer();
                echo $address_renderer->render_address_section('from', $from_address, true); // 'from' address, editable
                echo $address_renderer->render_address_section('to', $to_address, true);   // 'to' address, editable
                ?>
            </div>
        </div>
        
        <!-- Carrier selection -->
        <div class="wsl-form-section">
            <h3><?php _e('Carrier', 'woo-shipping-labels'); ?></h3>
            
            <div class="wsl-form-row">
                <div class="wsl-form-field">
                    <label for="wsl_carrier"><?php _e('Shipping Carrier', 'woo-shipping-labels'); ?></label>
                    <select id="wsl_carrier" name="carrier">
                        <?php
                        // Get carrier manager
                        $carrier_manager = WSL_Carrier_Manager::get_instance();
                        $carriers = $carrier_manager->get_carrier_options();
                        
                        foreach ($carriers as $carrier_id => $carrier_name) {
                            echo '<option value="' . esc_attr($carrier_id) . '" ' . selected($shipment_data['carrier'], $carrier_id, false) . '>' . esc_html($carrier_name) . '</option>';
                        }
                        ?>
                    </select>
                </div>
            </div>
        </div>
        
        <!-- Package Selection -->
        <div class="wsl-form-section">
            <h3><?php _e('Packages and Shipment Details', 'woo-shipping-labels'); ?></h3>
            
            <!-- Ship Date -->
            <div class="wsl-form-row">
                <div class="wsl-form-field">
                    <label for="wsl_ship_date"><?php _e('Ship Date', 'woo-shipping-labels'); ?></label>
                    <div class="wsl-date-input-wrapper">
                        <input type="date" id="wsl_ship_date" name="ship_date" class="wsl-date-input" 
                               value="<?php echo esc_attr(date('Y-m-d')); ?>" 
                               min="<?php echo esc_attr(date('Y-m-d')); ?>">
                        <span class="wsl-calendar-icon"></span>
                    </div>
                </div>
            </div>
            
            <!-- Weight (existing field from package-selection.php) -->
            <div class="wsl-form-row">
                <div class="wsl-form-field">
                    <label for="wsl_package_weight"><?php _e('Package Weight', 'woo-shipping-labels'); ?></label>
                    <div class="wsl-weight-input-group">
                        <input type="number" step="0.01" min="0.01" id="wsl_package_weight" name="package_weight" value="1.0" required>
                        <select id="wsl_weight_unit" name="weight_unit">
                            <option value="lb" selected><?php _e('lb', 'woo-shipping-labels'); ?></option>
                            <option value="kg"><?php _e('kg', 'woo-shipping-labels'); ?></option>
                            <option value="oz"><?php _e('oz', 'woo-shipping-labels'); ?></option>
                            <option value="g"><?php _e('g', 'woo-shipping-labels'); ?></option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Declared Value (updated to only use DB values) -->
            <div class="wsl-form-row">
                <div class="wsl-form-field">
                    <label for="wsl_declared_value"><?php _e('Declared Value', 'woo-shipping-labels'); ?></label>
                    <div class="wsl-currency-input-group">
                        <input type="number" step="0.01" min="0" id="wsl_declared_value" name="declared_value" 
                            value="<?php echo esc_attr($order ? $order->get_total() : '0.00'); ?>">
                        <select id="wsl_currency" name="currency">
                            <?php
                            // Get stored currencies from settings without defaults
                            $wsl_currencies = get_option('wsl_currencies', array());
                            
                            // Find the default currency
                            $default_currency = ''; // No fallback default
                            foreach ($wsl_currencies as $code => $data) {
                                if (isset($data['default']) && $data['default'] === true) {
                                    $default_currency = $code;
                                    break;
                                }
                            }
                            
                            // If no default found, use the first enabled currency
                            if (empty($default_currency)) {
                                foreach ($wsl_currencies as $code => $data) {
                                    if (isset($data['enabled']) && $data['enabled'] === true) {
                                        $default_currency = $code;
                                        break;
                                    }
                                }
                            }
                            
                            // Only show enabled currencies and select the default
                            foreach ($wsl_currencies as $code => $data) {
                                // Only include if explicitly enabled
                                if (isset($data['enabled']) && $data['enabled'] === true) {
                                    printf(
                                        '<option value="%s" %s>%s - %s</option>',
                                        esc_attr($code),
                                        selected($code, $default_currency, false),
                                        esc_html($code),
                                        esc_html($data['name'])
                                    );
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <p class="description"><?php _e('Value for customs/insurance purposes', 'woo-shipping-labels'); ?></p>
                </div>
            </div>
            
            <?php 
            // Include the package selection form (for Package Type and Carrier Package)
            include WSL_PLUGIN_DIR . 'admin/partials/package-selection.php'; 
            ?>
        </div>
        
        <!-- Service Selection -->
        <div class="wsl-form-section">
            <h3><?php _e('Shipping Service', 'woo-shipping-labels'); ?></h3>
            
            <div class="wsl-form-row">
                <div class="wsl-form-field">
                    <label for="wsl_service_type"><?php _e('Service Type', 'woo-shipping-labels'); ?></label>
                    <select id="wsl_service_type" name="service_type">
                        <option value=""><?php _e('-- Select a service --', 'woo-shipping-labels'); ?></option>
                        <!-- Service options will be loaded via AJAX based on carrier -->
                    </select>
                    
                    <div id="wsl_service_loading" style="display:none;">
                        <?php _e('Loading available services...', 'woo-shipping-labels'); ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Form Actions -->
        <div class="wsl-form-actions">
            <button type="button" id="wsl_calculate_rates" class="button button-secondary"><?php _e('Calculate Rates', 'woo-shipping-labels'); ?></button>
            <button type="submit" id="wsl_create_label" class="button button-primary"><?php _e('Create Label', 'woo-shipping-labels'); ?></button>
        </div>
    </form>
</div>

<script>
// Initialize global variables
var wsl_ajax = {
    ajax_url: '<?php echo esc_js(admin_url('admin-ajax.php')); ?>',
    nonce: '<?php echo esc_js($ajax_nonce); ?>'
};

var wsl_i18n = {
    select_package: '<?php echo esc_js(__('-- Select a package --', 'woo-shipping-labels')); ?>',
    select_service: '<?php echo esc_js(__('-- Select a service --', 'woo-shipping-labels')); ?>'
};

jQuery(document).ready(function($) {
    // Initialize address edit/validate functionality
    $('.wsl-edit-address-btn').on('click', function(e) {
        e.preventDefault();
        
        // Get the address type (from/to)
        const addressType = $(this).data('address-type');
        
        // Toggle visibility of display and edit forms
        $(`.wsl-address-display[data-address-type="${addressType}"]`).hide();
        $(`.wsl-address-edit[data-address-type="${addressType}"]`).show();
    });
    
    // Handle cancel button clicks
    $('.wsl-cancel-edit-btn').on('click', function(e) {
        e.preventDefault();
        
        // Get the address type (from/to)
        const addressType = $(this).data('address-type');
        
        // Hide edit form and show display
        $(`.wsl-address-edit[data-address-type="${addressType}"]`).hide();
        $(`.wsl-address-display[data-address-type="${addressType}"]`).show();
    });
    
    // Handle save address button clicks
    $('.wsl-save-address-btn').on('click', function(e) {
        e.preventDefault();
        
        const addressType = $(this).data('address-type');
        const $form = $(`.wsl-address-edit[data-address-type="${addressType}"]`);
        const formData = $form.find(':input').serialize();
        
        // Show loading state
        $(this).prop('disabled', true).text('Saving...');
        
        // Make AJAX request to save address
        $.ajax({
            url: wsl_ajax.ajax_url,
            type: 'POST',
            data: formData + '&action=wsl_save_address&address_type=' + addressType + '&order_id=' + $('input[name="order_id"]').val() + '&nonce=' + wsl_ajax.nonce,
            success: function(response) {
                if (response.success) {
                    // Refresh the address display with updated data
                    $(`.wsl-address-display[data-address-type="${addressType}"]`).html(response.data.formatted_address);
                    
                    // Hide edit form and show display
                    $(`.wsl-address-edit[data-address-type="${addressType}"]`).hide();
                    $(`.wsl-address-display[data-address-type="${addressType}"]`).show();
                    
                    // Show success message
                    alert(response.data.message || 'Address saved successfully');
                } else {
                    alert(response.data.message || 'Error saving address');
                }
            },
            error: function() {
                alert('Server error. Please try again.');
            },
            complete: function() {
                // Re-enable the save button
                $('.wsl-save-address-btn[data-address-type="' + addressType + '"]').prop('disabled', false).text('Save Address');
            }
        });
    });
    
    // Handle validate address button clicks
    $('.wsl-validate-address-btn').on('click', function(e) {
        e.preventDefault();
        
        const addressType = $(this).data('address-type');
        const $form = $(`.wsl-address-edit[data-address-type="${addressType}"]`);
        const formData = $form.find(':input').serialize();
        
        // Show loading state
        $(this).prop('disabled', true).text('Validating...');
        
        // Make AJAX request to validate address
        $.ajax({
            url: wsl_ajax.ajax_url,
            type: 'POST',
            data: formData + '&action=wsl_validate_address&carrier=' + $('#wsl_carrier').val() + '&nonce=' + wsl_ajax.nonce,
            success: function(response) {
                if (response.success) {
                    if (response.data.is_valid) {
                        // Use standardized address if available
                        if (response.data.standardized_address) {
                            // Update form fields with standardized values
                            $.each(response.data.standardized_address, function(field, value) {
                                $form.find(`[name="${addressType}_${field}"]`).val(value);
                            });
                            
                            alert('Address validated and standardized successfully.');
                        } else {
                            alert('Address is valid.');
                        }
                    } else {
                        // Show validation errors
                        alert('Address validation failed:\n' + response.data.messages.join('\n'));
                    }
                } else {
                    alert(response.data.message || 'Error validating address');
                }
            },
            error: function() {
                alert('Server error. Please try again.');
            },
            complete: function() {
                // Re-enable the validate button
                $('.wsl-validate-address-btn[data-address-type="' + addressType + '"]').prop('disabled', false).text('Validate');
            }
        });
    });
    
    // Calculate rates when carrier, package, or weight changes
    $('#wsl_carrier, .wsl-package-select, #wsl_package_weight, #wsl_weight_unit').on('change', function() {
        calculateRates();
    });
    
    // Calculate rates button
    $('#wsl_calculate_rates').on('click', function(e) {
        e.preventDefault();
        calculateRates();
    });
    
    // Calculate shipping rates
    function calculateRates() {
        const formData = $('#wsl_label_form').serialize();
        
        // Show loading state
        $('#wsl_service_loading').show();
        $('#wsl_service_type').hide();
        
        // Make AJAX request
        $.ajax({
            url: wsl_ajax.ajax_url,
            type: 'POST',
            data: formData + '&action=wsl_calculate_rates&nonce=' + wsl_ajax.nonce,
            success: function(response) {
                // Hide loading state
                $('#wsl_service_loading').hide();
                $('#wsl_service_type').show();
                
                if (response.success) {
                    // Populate service options
                    const $serviceSelect = $('#wsl_service_type');
                    $serviceSelect.empty();
                    
                    // Add default option
                    $serviceSelect.append(`<option value="">${wsl_i18n.select_service}</option>`);
                    
                    // Add service options
                    if (response.data.services.length > 0) {
                        $.each(response.data.services, function(i, service) {
                            $serviceSelect.append(`<option value="${service.id}">${service.name} - $${service.rate.toFixed(2)}</option>`);
                        });
                    } else {
                        $serviceSelect.append(`<option value="" disabled>${response.data.message || 'No services available'}</option>`);
                    }
                } else {
                    alert(response.data.message || 'Error calculating rates');
                }
            },
            error: function() {
                // Hide loading state
                $('#wsl_service_loading').hide();
                $('#wsl_service_type').show();
                
                alert('Server error. Please try again.');
            }
        });
    }
    
    // Form submission
    $('#wsl_label_form').on('submit', function(e) {
        e.preventDefault();
        
        // Basic validation
        if (!$('#wsl_service_type').val()) {
            alert('Please select a shipping service');
            return;
        }
        
        if (!$('#wsl_package_weight').val()) {
            alert('Please enter the package weight');
            return;
        }
        
        // Submit form via AJAX
        $.ajax({
            url: wsl_ajax.ajax_url,
            type: 'POST',
            data: $(this).serialize() + '&action=wsl_create_label&nonce=' + wsl_ajax.nonce,
            success: function(response) {
                if (response.success) {
                    // Handle successful label creation
                    if (response.data.label_url) {
                        // Open label in new window or redirect
                        window.open(response.data.label_url, '_blank');
                    }
                    
                    // Show success message
                    alert(response.data.message || 'Label created successfully');
                    
                    // Reload the page to show the new label
                    window.location.reload();
                } else {
                    alert(response.data.message || 'Error creating label');
                }
            },
            error: function() {
                alert('Server error. Please try again.');
            }
        });
    });
});
</script>

<style>
.wsl-label-form-container {
    margin: 20px 0;
}

.wsl-form-section {
    margin-bottom: 30px;
    padding: 15px;
    background: #fff;
    border: 1px solid #e5e5e5;
    border-radius: 4px;
}

.wsl-addresses {
    display: flex;
    flex-wrap: wrap;
}

.wsl-address-column {
    flex: 1;
    min-width: 300px;
    padding: 0 15px;
}

.wsl-address-display {
    padding: 10px;
    border: 1px solid #eee;
    border-radius: 4px;
    background: #f9f9f9;
}

.wsl-address-actions {
    margin-top: 10px;
}

.wsl-form-actions {
    margin-top: 20px;
}

.wsl-form-actions button {
    margin-right: 10px;
}

.wsl-currency-input-group {
    display: flex;
    align-items: center;
}

.wsl-currency-input-group input {
    flex: 1;
    border-top-right-radius: 0;
    border-bottom-right-radius: 0;
}

.wsl-currency-input-group select {
    width: auto;
    min-width: 150px;
    border-top-left-radius: 0;
    border-bottom-left-radius: 0;
    margin-left: -1px;
}

/* General input styling */
.wsl-form-field input[type="number"],
.wsl-form-field input[type="text"],
.wsl-form-field input[type="date"],
.wsl-form-field select {
    width: 100%;
    max-width: 400px;
    padding: 8px 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

/* Weight input group styling */
.wsl-weight-input-group {
    display: flex;
    align-items: center;
    max-width: 400px;
}

.wsl-weight-input-group input {
    flex: 1;
    border-top-right-radius: 0;
    border-bottom-right-radius: 0;
}

.wsl-weight-input-group select {
    width: 80px;
    border-top-left-radius: 0;
    border-bottom-left-radius: 0;
    margin-left: -1px;
}
</style> 