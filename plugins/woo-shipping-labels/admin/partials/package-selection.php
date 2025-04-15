<?php
include_once plugin_dir_path(__FILE__) . '../../includes/class-package-manager.php';
?>
<?php
/**
 * Package selection form for label creation
 */

if (!defined('WPINC')) {
    die;
}

// Get package manager and carrier manager
$package_manager = WSL_Package_Manager::get_instance();
$carrier_manager = WSL_Carrier_Manager::get_instance();

// Get the selected carrier
$selected_carrier = isset($shipment_data['carrier']) ? $shipment_data['carrier'] : 'fedex';

// Get user-defined packages
$user_packages = $package_manager->get_user_packages($selected_carrier);

// Default dimension and weight units
$default_dim_unit = get_option('wsl_dimension_unit', 'in');
$default_weight_unit = get_option('wsl_weight_unit', 'lb');

// Default package type
$package_type = isset($shipment_data['package_type']) ? $shipment_data['package_type'] : 'user';
$package_id = isset($shipment_data['package_id']) ? $shipment_data['package_id'] : '';
$custom_dimensions = isset($shipment_data['dimensions']) ? $shipment_data['dimensions'] : array(
    'length' => '',
    'width' => '',
    'height' => '',
    'dim_unit' => $default_dim_unit
);

$package_weight = isset($shipment_data['weight']) ? $shipment_data['weight'] : '';
$weight_unit = isset($shipment_data['weight_unit']) ? $shipment_data['weight_unit'] : $default_weight_unit;
?>

<div class="wsl-package-selection">
    <h3><?php _e('Package Information', 'woo-shipping-labels'); ?></h3>
    
    <div class="wsl-form-row">
        <div class="wsl-form-field">
            <label for="wsl_package_type"><?php _e('Package Type', 'woo-shipping-labels'); ?></label>
            <select id="wsl_package_type" name="package_type" class="wsl-package-type-select">
                <option value="user" <?php selected($package_type, 'user'); ?>><?php _e('Your Packages', 'woo-shipping-labels'); ?></option>
                <option value="carrier" <?php selected($package_type, 'carrier'); ?>><?php _e('Carrier Packages', 'woo-shipping-labels'); ?></option>
                <option value="custom" <?php selected($package_type, 'custom'); ?>><?php _e('Custom Package', 'woo-shipping-labels'); ?></option>
            </select>
        </div>
    </div>
    
    <!-- User-defined packages -->
    <div class="wsl-package-options wsl-user-packages" <?php echo $package_type !== 'user' ? 'style="display:none;"' : ''; ?>>
        <div class="wsl-form-row">
            <div class="wsl-form-field">
                <label for="wsl_user_package"><?php _e('Select Package', 'woo-shipping-labels'); ?></label>
                <select id="wsl_user_package" name="user_package_id" class="wsl-package-select">
                    <option value=""><?php _e('-- Select a package --', 'woo-shipping-labels'); ?></option>
                    <?php foreach ($user_packages as $user_package): ?>
                        <option value="<?php echo esc_attr($user_package['id']); ?>" <?php selected($package_type === 'user' && $package_id == $user_package['id']); ?>>
                            <?php echo esc_html($user_package['name']); ?> 
                            (<?php echo esc_html("{$user_package['length']}x{$user_package['width']}x{$user_package['height']} {$user_package['dim_unit']}"); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <?php if (empty($user_packages)): ?>
                    <p class="wsl-help-text">
                        <?php echo sprintf(
                            __('No packages defined. <a href="%s">Create your first package</a>.', 'woo-shipping-labels'),
                            admin_url('admin.php?page=woo-shipping-labels-packages')
                        ); ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Carrier packages -->
    <div class="wsl-package-options wsl-carrier-packages" <?php echo $package_type !== 'carrier' ? 'style="display:none;"' : ''; ?>>
        <div class="wsl-form-row">
            <div class="wsl-form-field">
                <label for="wsl_carrier_package"><?php _e('Carrier Package', 'woo-shipping-labels'); ?></label>
                <select id="wsl_carrier_package" name="carrier_package_id" class="wsl-package-select">
                    <option value=""><?php _e('-- Select a carrier package --', 'woo-shipping-labels'); ?></option>
                    <?php
                    // Get carrier packages
                    $carrier_packages = $package_manager->get_carrier_packages($selected_carrier);
                    foreach ($carrier_packages as $carrier_package):
                    ?>
                        <option value="<?php echo esc_attr($carrier_package['id']); ?>" <?php selected($package_type === 'carrier' && $package_id == $carrier_package['id']); ?>>
                            <?php echo esc_html($carrier_package['name']); ?>
                            <?php if (!empty($carrier_package['dimensions'])): ?>
                                (<?php echo esc_html($carrier_package['dimensions']); ?>)
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <?php if (empty($carrier_packages)): ?>
                    <p class="wsl-help-text">
                        <?php _e('No carrier packages available. Please select a carrier first.', 'woo-shipping-labels'); ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Custom package dimensions -->
    <div class="wsl-package-options wsl-custom-package" <?php echo $package_type !== 'custom' ? 'style="display:none;"' : ''; ?>>
        <div class="wsl-form-row">
            <div class="wsl-form-field">
                <label for="wsl_custom_length"><?php _e('Length', 'woo-shipping-labels'); ?></label>
                <input type="number" id="wsl_custom_length" name="custom_length" value="<?php echo esc_attr($custom_dimensions['length']); ?>" min="0.1" step="0.1">
            </div>
            
            <div class="wsl-form-field">
                <label for="wsl_custom_width"><?php _e('Width', 'woo-shipping-labels'); ?></label>
                <input type="number" id="wsl_custom_width" name="custom_width" value="<?php echo esc_attr($custom_dimensions['width']); ?>" min="0.1" step="0.1">
            </div>
            
            <div class="wsl-form-field">
                <label for="wsl_custom_height"><?php _e('Height', 'woo-shipping-labels'); ?></label>
                <input type="number" id="wsl_custom_height" name="custom_height" value="<?php echo esc_attr($custom_dimensions['height']); ?>" min="0.1" step="0.1">
            </div>
            
            <div class="wsl-form-field">
                <label for="wsl_custom_dim_unit"><?php _e('Unit', 'woo-shipping-labels'); ?></label>
                <select id="wsl_custom_dim_unit" name="custom_dim_unit">
                    <option value="in" <?php selected($custom_dimensions['dim_unit'], 'in'); ?>><?php _e('Inches (in)', 'woo-shipping-labels'); ?></option>
                    <option value="cm" <?php selected($custom_dimensions['dim_unit'], 'cm'); ?>><?php _e('Centimeters (cm)', 'woo-shipping-labels'); ?></option>
                </select>
            </div>
        </div>
    </div>
    
    <!-- Package weight - required for all package types -->
    <div class="wsl-form-row">
        <div class="wsl-form-field wsl-weight-field">
            <label for="wsl_package_weight"><?php _e('Weight', 'woo-shipping-labels'); ?> <span class="required">*</span></label>
            <div class="wsl-input-group">
                <input type="number" id="wsl_package_weight" name="package_weight" value="<?php echo esc_attr($package_weight); ?>" min="0.1" step="0.1" required>
                <select id="wsl_weight_unit" name="weight_unit">
                    <option value="lb" <?php selected($weight_unit, 'lb'); ?>><?php _e('Pounds (lb)', 'woo-shipping-labels'); ?></option>
                    <option value="kg" <?php selected($weight_unit, 'kg'); ?>><?php _e('Kilograms (kg)', 'woo-shipping-labels'); ?></option>
                    <option value="oz" <?php selected($weight_unit, 'oz'); ?>><?php _e('Ounces (oz)', 'woo-shipping-labels'); ?></option>
                </select>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Handle package type selection
    $('.wsl-package-type-select').on('change', function() {
        const selectedType = $(this).val();
        
        // Hide all package options first
        $('.wsl-package-options').hide();
        
        // Show the selected package option
        $(`.wsl-${selectedType}-packages`).show();
        
        // Special case for custom package
        if (selectedType === 'custom') {
            $('.wsl-custom-package').show();
        }
    });
    
    // Initialize carrier-specific package options when carrier changes
    $('#wsl_carrier').on('change', function() {
        const carrier = $(this).val();
        
        // Update carrier packages via AJAX
        $.ajax({
            url: wsl_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wsl_get_carrier_packages',
                carrier: carrier,
                nonce: wsl_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Update package dropdown
                    const $packageSelect = $('#wsl_carrier_package');
                    $packageSelect.empty();
                    
                    // Add default option
                    $packageSelect.append(`<option value="">${wsl_i18n.select_package}</option>`);
                    
                    // Add carrier packages
                    if (response.data.packages.length > 0) {
                        $.each(response.data.packages, function(i, package) {
                            const dimensions = package.dimensions ? ` (${package.dimensions})` : '';
                            $packageSelect.append(`<option value="${package.id}">${package.name}${dimensions}</option>`);
                        });
                        
                        $('.wsl-carrier-packages .wsl-help-text').hide();
                    } else {
                        $('.wsl-carrier-packages .wsl-help-text').show();
                    }
                }
            }
        });
    });
    
    // Auto-fill dimensions when a package is selected
    $('.wsl-package-select').on('change', function() {
        const packageType = $('#wsl_package_type').val();
        const packageId = $(this).val();
        
        if (!packageId) {
            return;
        }
        
        // Get package dimensions via AJAX
        $.ajax({
            url: wsl_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wsl_get_package_dimensions',
                package_type: packageType,
                package_id: packageId,
                carrier: $('#wsl_carrier').val(),
                nonce: wsl_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Auto-fill weight if empty and max weight is available
                    if ($('#wsl_package_weight').val() === '' && response.data.max_weight > 0) {
                        $('#wsl_package_weight').val(response.data.max_weight);
                        $('#wsl_weight_unit').val(response.data.weight_unit);
                    }
                }
            }
        });
    });
});
</script>

<style>
.wsl-package-selection {
    margin-bottom: 20px;
    padding: 15px;
    background: #f9f9f9;
    border: 1px solid #e5e5e5;
    border-radius: 4px;
}

.wsl-form-row {
    display: flex;
    flex-wrap: wrap;
    margin: 0 -10px 15px;
}

.wsl-form-field {
    flex: 1;
    min-width: 200px;
    padding: 0 10px;
}

.wsl-form-field label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
}

.wsl-form-field input,
.wsl-form-field select {
    width: 100%;
    padding: 8px;
}

.wsl-help-text {
    margin-top: 5px;
    font-size: 12px;
    color: #777;
}

.wsl-input-group {
    display: flex;
}

.wsl-input-group input {
    flex: 1;
    border-right: 0;
    border-top-right-radius: 0;
    border-bottom-right-radius: 0;
}

.wsl-input-group select {
    width: auto;
    border-top-left-radius: 0;
    border-bottom-left-radius: 0;
}

.required {
    color: #d63638;
}
</style> 