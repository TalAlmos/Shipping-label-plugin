/**
 * Admin JavaScript for WooCommerce Shipping Labels
 */

// This file is currently empty as the JS is included in the template file.
// In a production plugin, you would move the JavaScript from the template to here.

/**
 * WooCommerce Shipping Labels Admin JavaScript
 */
(function($) {
    'use strict';
    
    // Initialize when document is ready
    $(document).ready(function() {
        // Initialize address handling
        initAddressHandling();
        
        // Initialize package type handling
        initPackageTypeHandling();
        
        // Initialize shipping rates calculation
        initShippingRatesCalculation();
        
        // Initialize label generation
        initLabelGeneration();
    });
    
    /**
     * Initialize address editing and validation functionality
     */
    function initAddressHandling() {
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
                    } else {
                        alert(wsl_ajax.i18n.error);
                    }
                },
                error: function() {
                    alert(wsl_ajax.i18n.error);
                }
            });
        });
        
        // Address validation button click handler
        $('.wsl-validate-address').on('click', function() {
            var addressType = $(this).data('address-type');
            validateAddress(addressType);
        });
    }
    
    /**
     * Initialize package type handling
     */
    function initPackageTypeHandling() {
        // Toggle custom package fields based on selection
        $('#package_type').on('change', function() {
            if ($(this).val() === 'custom') {
                $('.wsl-custom-package').show();
            } else {
                $('.wsl-custom-package').hide();
            }
        });
    }
    
    /**
     * Initialize shipping rates calculation
     */
    function initShippingRatesCalculation() {
        $('#wsl-calculate-rates').on('click', function() {
            var packageData = collectPackageData();
            
            // Validate package data
            if (!validatePackageData(packageData)) {
                return;
            }
            
            // Get country values from address forms
            var fromCountry = $('#from_country').val();
            var toCountry = $('#to_country').val();
            
            // Validate country data
            if (!fromCountry || !toCountry) {
                $('.wsl-shipping-options').html(
                    '<div class="notice notice-error"><p>' + 
                    wsl_ajax.i18n.country_required + 
                    '</p></div>'
                ).show();
                return;
            }
            
            // Add loading indicator
            var $ratesContainer = $('.wsl-shipping-options');
            $ratesContainer.html('<p class="wsl-loading">' + wsl_ajax.i18n.calculating_rates + '</p>').show();
            
            // Call the API to get available shipping rates
            $.ajax({
                url: wsl_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wsl_calculate_shipping_rates',
                    security: wsl_ajax.nonce,
                    package_data: packageData,
                    from_country: fromCountry,
                    to_country: toCountry
                },
                success: function(response) {
                    if (response.success) {
                        var options = response.data.shipping_options;
                        
                        if (options.length === 0) {
                            $ratesContainer.html(
                                '<div class="notice notice-warning"><p>' + 
                                wsl_ajax.i18n.no_shipping_options + 
                                '</p></div>'
                            );
                            return;
                        }
                        
                        // Build rates table
                        var tableHtml = 
                            '<table class="wsl-rates-table">' +
                            '<thead><tr><th>Select</th><th>Carrier</th><th>Service</th><th>Transit Time</th><th>Cost</th></tr></thead>' +
                            '<tbody>';
                        
                        // Add each shipping option to the table
                        options.forEach(function(option, index) {
                            var checked = index === 0 ? 'checked' : '';
                            tableHtml += 
                                '<tr>' +
                                '<td><input type="radio" name="shipping_option" value="' + option.carrier_id + '_' + option.service_id + '" ' + checked + '></td>' +
                                '<td><img src="' + wsl_ajax.plugin_url + 'assets/images/' + option.carrier_logo + '" alt="' + option.carrier_name + '" class="carrier-logo"></td>' +
                                '<td>' + escapeHtml(option.service_name) + '</td>' +
                                '<td>' + escapeHtml(option.transit_time) + '</td>' +
                                '<td>$' + escapeHtml(option.rate) + '</td>' +
                                '</tr>';
                        });
                        
                        tableHtml += '</tbody></table>';
                        
                        $ratesContainer.html(tableHtml);
                        $('#wsl-generate-label').show();
                    } else {
                        $ratesContainer.html(
                            '<div class="notice notice-error"><p>' + 
                            wsl_ajax.i18n.rates_error + 
                            '</p></div>'
                        );
                    }
                },
                error: function() {
                    $ratesContainer.html(
                        '<div class="notice notice-error"><p>' + 
                        wsl_ajax.i18n.rates_error + 
                        '</p></div>'
                    );
                }
            });
        });
    }
    
    /**
     * Validate an address
     * 
     * @param {string} addressType 'from' or 'to'
     */
    function validateAddress(addressType) {
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
    }
    
    /**
     * Collect address data from form fields
     * 
     * @param {string} addressType 'from' or 'to'
     * @return {object} Address data
     */
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
    
    /**
     * Collect package data from form fields
     * 
     * @return {object} Package data
     */
    function collectPackageData() {
        var packageType = $('#package_type').val();
        var packageData = {
            type: packageType,
            weight: $('#package_weight').val(),
            description: $('#goods_description').val()
        };
        
        // Add custom dimensions if needed
        if (packageType === 'custom') {
            packageData.length = $('#package_length').val();
            packageData.width = $('#package_width').val();
            packageData.height = $('#package_height').val();
        }
        
        return packageData;
    }
    
    /**
     * Validate package data before requesting rates
     * 
     * @param {object} packageData Package data
     * @return {boolean} Whether data is valid
     */
    function validatePackageData(packageData) {
        var isValid = true;
        var errors = [];
        
        if (!packageData.weight || packageData.weight <= 0) {
            isValid = false;
            errors.push(wsl_ajax.i18n.weight_required);
        }
        
        if (!packageData.description) {
            isValid = false;
            errors.push(wsl_ajax.i18n.description_required);
        }
        
        if (packageData.type === 'custom') {
            if (!packageData.length || packageData.length <= 0) {
                isValid = false;
                errors.push(wsl_ajax.i18n.length_required);
            }
            
            if (!packageData.width || packageData.width <= 0) {
                isValid = false;
                errors.push(wsl_ajax.i18n.width_required);
            }
            
            if (!packageData.height || packageData.height <= 0) {
                isValid = false;
                errors.push(wsl_ajax.i18n.height_required);
            }
        }
        
        if (!isValid) {
            // Display errors
            var errorHtml = '<div class="notice notice-error"><p>' + 
                            wsl_ajax.i18n.package_validation_error + '</p><ul>';
            
            errors.forEach(function(error) {
                errorHtml += '<li>' + escapeHtml(error) + '</li>';
            });
            
            errorHtml += '</ul></div>';
            
            $('.wsl-shipping-options').html(errorHtml).show();
        }
        
        return isValid;
    }
    
    /**
     * Helper function to safely escape HTML
     * 
     * @param {string} str String to escape
     * @return {string} Escaped string
     */
    function escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
    
    // Label generation handler
    function initLabelGeneration() {
        $('#wsl-generate-label').on('click', function(e) {
            e.preventDefault();
            
            // Get selected shipping option
            var selectedService = $('input[name="shipping_option"]:checked').val();
            
            if (!selectedService) {
                alert(wsl_ajax.i18n.no_service_selected);
                return;
            }
            
            // Collect package data
            var packageData = {
                weight: parseFloat($('#package_weight').val()) || 0,
                length: parseFloat($('#package_length').val()) || 0,
                width: parseFloat($('#package_width').val()) || 0,
                height: parseFloat($('#package_height').val()) || 0
            };
            
            // Validate package weight
            if (packageData.weight <= 0) {
                alert(wsl_ajax.i18n.weight_required);
                return;
            }
            
            // Get address data
            var fromAddress = {};
            $('#ship-from-form input, #ship-from-form select').each(function() {
                var field = $(this).attr('name');
                if (field) {
                    var key = field.replace('from_', '');
                    fromAddress[key] = $(this).val();
                }
            });
            
            var toAddress = {};
            $('#ship-to-form input, #ship-to-form select').each(function() {
                var field = $(this).attr('name');
                if (field) {
                    var key = field.replace('to_', '');
                    toAddress[key] = $(this).val();
                }
            });
            
            // For international shipments, get customs value
            var customsValue = '';
            if (fromAddress.country !== toAddress.country) {
                customsValue = prompt(wsl_ajax.i18n.customs_value_prompt, '100');
                if (customsValue === null) {
                    return; // User canceled
                }
            }
            
            // Show generating state
            var $button = $(this);
            $button.prop('disabled', true).text('Generating label...');
            
            // Send AJAX request
            $.ajax({
                url: wsl_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wsl_generate_fedex_label',
                    security: wsl_ajax.nonce,
                    from_address: fromAddress,
                    to_address: toAddress,
                    package_data: packageData,
                    service: selectedService,
                    order_id: $('#order_id').val(),
                    customs_value: customsValue
                },
                success: function(response) {
                    $button.prop('disabled', false).text('Generate Label');
                    
                    if (response.success) {
                        // Show success message with tracking number and label URL
                        var data = response.data;
                        var successHtml = '<div class="notice notice-success">' +
                            '<p>Label generated successfully!</p>' +
                            '<p><strong>Tracking number:</strong> ' + data.tracking_number + '</p>' +
                            '<p><a href="' + data.label_url + '" target="_blank" class="button">View Label</a> ' +
                            '<a href="' + data.label_url + '" download class="button">Download Label</a></p>' +
                            '</div>';
                        
                        $('#label-result').html(successHtml).show();
                        $button.hide(); // Hide the generate button
                    } else {
                        // Show error message
                        var errorMessage = response.data && response.data.error ? response.data.error : 'Failed to generate label';
                        $('#label-result').html('<div class="notice notice-error"><p>' + errorMessage + '</p></div>').show();
                    }
                },
                error: function() {
                    $button.prop('disabled', false).text('Generate Label');
                    $('#label-result').html('<div class="notice notice-error"><p>Server error while generating label</p></div>').show();
                }
            });
        });
    }
    
})(jQuery);