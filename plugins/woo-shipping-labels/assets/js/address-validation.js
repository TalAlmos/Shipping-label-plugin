jQuery(document).ready(function($) {
    // Cache DOM elements
    const validateButtons = $('.wsl-validate-address');
    
    // Add event listeners
    validateButtons.on('click', handleValidationClick);
    
    /**
     * Handle validation button click
     */
    function handleValidationClick(e) {
        e.preventDefault();
        
        const button = $(this);
        const addressType = button.data('address-type');
        const resultContainer = addressType === 'from' ? 
            $('.wsl-from-validation-result') : 
            $('.wsl-to-validation-result');
        
        // Show loading indicator
        button.addClass('loading');
        button.prop('disabled', true);
        resultContainer.html('<span class="wsl-loading">Validating address...</span>');
        
        // Get address data based on type
        let addressData;
        if (addressType === 'from') {
            addressData = getFromAddress();
        } else {
            addressData = getToAddress();
        }
        
        // Validate address
        validateAddress(addressData, resultContainer, button);
    }
    
    /**
     * Get "Ship From" address data
     */
    function getFromAddress() {
        return {
            address_1: $('#from_address_line1').val(),
            address_2: $('#from_address_line2').val(),
            city: $('#from_city').val(),
            state: $('#from_state').val(),
            postcode: $('#from_postcode').val(),
            country: $('#from_country').val()
        };
    }
    
    /**
     * Get "Ship To" address data
     */
    function getToAddress() {
        return {
            address_1: $('#to_address_line1').val(),
            address_2: $('#to_address_line2').val(),
            city: $('#to_city').val(),
            state: $('#to_state').val(),
            postcode: $('#to_postcode').val(),
            country: $('#to_country').val()
        };
    }
    
    /**
     * Validate address via AJAX
     */
    function validateAddress(addressData, resultContainer, button) {
        $.ajax({
            url: wsl_params.ajax_url,
            type: 'POST',
            data: {
                action: 'wsl_validate_address',
                nonce: wsl_params.nonce,
                ...addressData
            },
            success: function(response) {
                button.removeClass('loading');
                button.prop('disabled', false);
                
                if (response.success) {
                    handleValidationSuccess(response.data, resultContainer);
                } else {
                    handleValidationError(response.data, resultContainer);
                }
            },
            error: function() {
                button.removeClass('loading');
                button.prop('disabled', false);
                resultContainer.html(
                    '<div class="wsl-validation-error">' +
                    '<span class="dashicons dashicons-no"></span> ' +
                    'Server error while validating address.' +
                    '</div>'
                );
            }
        });
    }
    
    /**
     * Handle successful validation
     */
    function handleValidationSuccess(data, resultContainer) {
        // Check if we have a validated address
        if (!data.validated || !data.validated.address) {
            // Handle failed validation
            resultContainer.html(
                '<div class="wsl-validation-error">' +
                    '<span class="dashicons dashicons-no"></span> ' +
                    'Address validation failed' +
                '</div>'
            );
            return;
        }
        
        // Extract address data
        const address = data.validated.address;
        const classification = address.classification || '';
        
        // Check for corrected fields
        const corrections = [];
        if (data.validated.corrected_fields) {
            corrections.push(...data.validated.corrected_fields);
        }
        
        // Build the validation result HTML
        let html = '<div class="wsl-validation-success">' +
            '<span class="dashicons dashicons-yes"></span> ' +
            'Address validated successfully' +
            '</div>';
        
        // Show address classification if available
        if (classification) {
            html += '<div class="wsl-address-classification">' +
                '<span class="classification-label">Classification:</span> ' +
                '<span class="classification-value">' + classification + '</span>' +
            '</div>';
        }
        
        // Show standardized address
        html += '<div class="wsl-standardized-address">' +
            '<h4>Standardized Address:</h4>' +
            '<div class="standardized-address-content">';
        
        // Format the address properly
        if (address.street_lines && address.street_lines.length > 0) {
            address.street_lines.forEach(function(line) {
                html += '<p>' + line + '</p>';
            });
        }
        
        // Format city, state, zip in single line (USPS style)
        let cityStateZip = '';
        if (address.city) {
            cityStateZip += address.city;
        }
        if (address.state) {
            cityStateZip += (cityStateZip ? ', ' : '') + address.state;
        }
        if (address.postal_code) {
            cityStateZip += ' ' + address.postal_code;
        }
        
        if (cityStateZip) {
            html += '<p>' + cityStateZip + '</p>';
        }
        
        if (address.country) {
            html += '<p>' + address.country + '</p>';
        }
        
        html += '</div></div>';
        
        // Show corrections if any
        if (corrections.length > 0) {
            html += '<div class="wsl-address-corrections">' +
                '<h4>Corrections Made:</h4>' +
                '<ul>';
            
            corrections.forEach(function(correction) {
                html += '<li>' + correction + '</li>';
            });
            
            html += '</ul></div>';
        }
        
        // Add "Use Standardized Address" button
        html += '<div class="wsl-address-actions">' +
            '<button type="button" class="button button-primary use-standardized-address" ' +
            'data-address="' + encodeURIComponent(JSON.stringify(address)) + '">' +
            'Use Standardized Address</button>' +
        '</div>';
        
        // Add alerts/issues if any
        if (data.alerts && data.alerts.length > 0) {
            html += '<div class="wsl-validation-alerts">' +
                '<h4>Issues:</h4>' +
                '<ul>';
            
            data.alerts.forEach(function(alert) {
                html += '<li>' + alert.message + '</li>';
            });
            
            html += '</ul></div>';
        }
        
        // Update the container with our HTML
        resultContainer.html(html);
        
        // Add event listener for the "Use Standardized Address" button
        $('.use-standardized-address').on('click', function() {
            const standardizedAddressData = JSON.parse(decodeURIComponent($(this).data('address')));
            const addressType = resultContainer.hasClass('wsl-from-validation-result') ? 'from' : 'to';
            
            // Apply the standardized address to the form fields
            applyStandardizedAddress(standardizedAddressData, addressType);
        });
    }
    
    /**
     * Apply standardized address to form fields
     */
    function applyStandardizedAddress(address, addressType) {
        const prefix = addressType === 'from' ? 'from_' : 'to_';
        
        // Update address line fields
        if (address.street_lines && address.street_lines.length > 0) {
            $('#' + prefix + 'address_line1').val(address.street_lines[0] || '');
            
            if (address.street_lines.length > 1) {
                $('#' + prefix + 'address_line2').val(address.street_lines[1] || '');
            }
        }
        
        // Update city, state, postal code, country
        if (address.city) {
            $('#' + prefix + 'city').val(address.city);
        }
        
        if (address.state) {
            $('#' + prefix + 'state').val(address.state);
        }
        
        if (address.postal_code) {
            $('#' + prefix + 'postcode').val(address.postal_code);
        }
        
        if (address.country) {
            $('#' + prefix + 'country').val(address.country);
        }
        
        // Trigger a click on the save button to update the display
        $('.save-address[data-target="ship-' + addressType + '-edit"]').click();
        
        // Show confirmation message
        const resultContainer = $('.wsl-' + addressType + '-validation-result');
        
        resultContainer.append(
            '<div class="wsl-address-updated">' +
                '<span class="dashicons dashicons-yes-alt"></span> ' +
                'Address updated successfully' +
            '</div>'
        );
    }
    
    /**
     * Handle validation error
     */
    function handleValidationError(data, resultContainer) {
        resultContainer.html(
            '<div class="wsl-validation-error">' +
            '<span class="dashicons dashicons-no"></span> ' +
            data.message +
            '</div>'
        );
    }
    
    /**
     * Format standardized address for display
     */
    function formatStandardizedAddress(address) {
        let formatted = '';
        
        if (address.street_lines && address.street_lines.length > 0) {
            address.street_lines.forEach(function(line) {
                formatted += line + '<br>';
            });
        }
        
        // Create city-state-zip line
        let locationLine = '';
        
        // Add city if available
        if (address.city) {
            locationLine += address.city;
        }
        
        // Add state if available
        if (address.state) {
            locationLine += (locationLine ? ', ' : '') + address.state;
        }
        
        // Add postal code if available
        if (address.postal_code) {
            locationLine += ' ' + address.postal_code;
        }
        
        // Add the location line if we have any location information
        if (locationLine) {
            formatted += locationLine + '<br>';
        }
        
        // Add country
        if (address.country) {
            formatted += address.country;
        }
        
        return formatted;
    }
}); 