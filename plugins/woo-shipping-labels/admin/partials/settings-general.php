<table class="form-table">
    <tr valign="top">
        <th scope="row"><?php _e('Package Management', 'woo-shipping-labels'); ?></th>
        <td>
            <a href="<?php echo esc_url(admin_url('admin.php?page=wsl-settings&tab=packages')); ?>" class="button">
                <?php _e('Manage Packages', 'woo-shipping-labels'); ?>
            </a>
            <p class="description">
                <?php _e('Create and manage your shipping packages for different carriers.', 'woo-shipping-labels'); ?>
            </p>
        </td>
    </tr>
</table>

<!-- Currency Management Section -->
<div class="wsl-setting-section">
    <h3><?php _e('Currency Management', 'woo-shipping-labels'); ?></h3>
    
    <!-- Currency Table -->
    <div class="wsl-currency-table-container">
        <table class="wp-list-table widefat fixed striped wsl-currency-table">
            <thead>
                <tr>
                    <th><?php _e('Currency Code', 'woo-shipping-labels'); ?></th>
                    <th><?php _e('Currency Name', 'woo-shipping-labels'); ?></th>
                    <th><?php _e('Enabled', 'woo-shipping-labels'); ?></th>
                    <th><?php _e('Default', 'woo-shipping-labels'); ?></th>
                    <th><?php _e('Actions', 'woo-shipping-labels'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Get stored currencies or use defaults
                $wsl_currencies = get_option('wsl_currencies', array(
                    'USD' => array('name' => 'US Dollar', 'enabled' => true, 'default' => true),
                    'EUR' => array('name' => 'Euro', 'enabled' => true, 'default' => false),
                    'GBP' => array('name' => 'British Pound', 'enabled' => true, 'default' => false),
                    'CAD' => array('name' => 'Canadian Dollar', 'enabled' => true, 'default' => false),
                ));
                
                foreach ($wsl_currencies as $code => $currency) :
                ?>
                <tr id="currency-<?php echo esc_attr($code); ?>">
                    <td><?php echo esc_html($code); ?></td>
                    <td><?php echo esc_html($currency['name']); ?></td>
                    <td>
                        <input type="checkbox" 
                               name="wsl_currencies[<?php echo esc_attr($code); ?>][enabled]" 
                               value="1" 
                               <?php checked(isset($currency['enabled']) && $currency['enabled']); ?>>
                    </td>
                    <td>
                        <input type="radio" 
                               name="wsl_default_currency" 
                               value="<?php echo esc_attr($code); ?>" 
                               <?php checked(isset($currency['default']) && $currency['default']); ?>>
                    </td>
                    <td>
                        <button type="button" class="button wsl-edit-currency" 
                                data-code="<?php echo esc_attr($code); ?>" 
                                data-name="<?php echo esc_attr($currency['name']); ?>">
                            <?php _e('Edit', 'woo-shipping-labels'); ?>
                        </button>
                        <button type="button" class="button wsl-delete-currency" 
                                data-code="<?php echo esc_attr($code); ?>">
                            <?php _e('Delete', 'woo-shipping-labels'); ?>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="wsl-add-currency-container">
            <h4><?php _e('Add New Currency', 'woo-shipping-labels'); ?></h4>
            <div class="wsl-add-currency-form">
                <input type="text" id="wsl_new_currency_code" placeholder="Currency Code (e.g., JPY)" maxlength="3">
                <input type="text" id="wsl_new_currency_name" placeholder="Currency Name (e.g., Japanese Yen)">
                <button type="button" class="button button-secondary" id="wsl_add_currency_btn">
                    <?php _e('Add Currency', 'woo-shipping-labels'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Include JavaScript for currency management -->
<script>
jQuery(document).ready(function($) {
    // Add new currency
    $('#wsl_add_currency_btn').on('click', function() {
        const code = $('#wsl_new_currency_code').val().toUpperCase();
        const name = $('#wsl_new_currency_name').val();
        
        // Basic validation
        if (!code || code.length !== 3) {
            alert('Please enter a valid 3-letter currency code');
            return;
        }
        
        if (!name) {
            alert('Please enter a currency name');
            return;
        }
        
        // Check if currency already exists
        if ($('#currency-' + code).length > 0) {
            alert('This currency code already exists');
            return;
        }
        
        // Add new row to table
        const newRow = `
            <tr id="currency-${code}">
                <td>${code}</td>
                <td>${name}</td>
                <td>
                    <input type="checkbox" name="wsl_currencies[${code}][enabled]" value="1" checked>
                </td>
                <td>
                    <input type="radio" name="wsl_default_currency" value="${code}">
                </td>
                <td>
                    <button type="button" class="button wsl-edit-currency" data-code="${code}" data-name="${name}">
                        Edit
                    </button>
                    <button type="button" class="button wsl-delete-currency" data-code="${code}">
                        Delete
                    </button>
                </td>
            </tr>
        `;
        
        $('.wsl-currency-table tbody').append(newRow);
        
        // Clear form fields
        $('#wsl_new_currency_code').val('');
        $('#wsl_new_currency_name').val('');
    });
    
    // Edit currency (delegate for dynamically added elements)
    $(document).on('click', '.wsl-edit-currency', function() {
        const code = $(this).data('code');
        const name = $(this).data('name');
        
        // Simple prompt-based editing
        const newName = prompt('Edit currency name:', name);
        
        if (newName && newName !== name) {
            // Update the name in the table
            $(`#currency-${code} td:nth-child(2)`).text(newName);
            
            // Update the data attribute
            $(this).data('name', newName);
            
            // Add a hidden input to save the updated name
            if ($(`input[name="wsl_currencies[${code}][name]"]`).length) {
                $(`input[name="wsl_currencies[${code}][name]"]`).val(newName);
            } else {
                $(`#currency-${code}`).append(`
                    <input type="hidden" name="wsl_currencies[${code}][name]" value="${newName}">
                `);
            }
        }
    });
    
    // Delete currency
    $(document).on('click', '.wsl-delete-currency', function() {
        if (confirm('Are you sure you want to delete this currency?')) {
            const code = $(this).data('code');
            
            // Check if this is the default currency
            if ($(`#currency-${code} input[name="wsl_default_currency"]`).is(':checked')) {
                alert('You cannot delete the default currency. Please set another currency as default first.');
                return;
            }
            
            // Remove the row
            $(`#currency-${code}`).remove();
        }
    });
});
</script>

<!-- Add some styling -->
<style>
.wsl-currency-table-container {
    margin: 20px 0;
}

.wsl-add-currency-container {
    margin-top: 20px;
    padding: 15px;
    background: #f9f9f9;
    border: 1px solid #e5e5e5;
    border-radius: 4px;
}

.wsl-add-currency-form {
    display: flex;
    gap: 10px;
    align-items: center;
}

.wsl-add-currency-form input {
    padding: 6px 8px;
    width: 200px;
}
</style> 