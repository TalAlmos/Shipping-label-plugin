(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Open add currency dialog
        $('.add-new-currency').on('click', function() {
            $('#add-currency-dialog').show();
        });
        
        // Open edit currency dialog
        $('.wsl-currency-table').on('click', '.edit-currency', function() {
            var row = $(this).closest('tr');
            var code = row.data('code');
            var name = row.find('td:nth-child(2)').text();
            var enabled = row.find('input[type="checkbox"]').prop('checked');
            
            $('#edit-currency-code').val(code);
            $('#edit-currency-name').val(name);
            $('#edit-currency-enabled').prop('checked', enabled);
            
            $('#edit-currency-dialog').show();
        });
        
        // Close dialogs on cancel
        $('.cancel-currency').on('click', function() {
            $(this).closest('.wsl-dialog').hide();
        });
        
        // Add new currency
        $('.save-currency').on('click', function() {
            var code = $('#new-currency-code').val().toUpperCase();
            var name = $('#new-currency-name').val();
            var enabled = $('#new-currency-enabled').prop('checked');
            
            if (!code || code.length !== 3) {
                alert(wsl_currency.currency_code_invalid);
                return;
            }
            
            if (!name) {
                alert(wsl_currency.currency_name_required);
                return;
            }
            
            // Check if currency already exists
            if ($('.wsl-currency-table tr[data-code="' + code + '"]').length > 0) {
                alert(wsl_currency.currency_exists);
                return;
            }
            
            // Add new row to table
            var newRow = '<tr data-code="' + code + '">';
            newRow += '<td>' + code + '<input type="hidden" name="wsl_currencies[' + code + '][code]" value="' + code + '"></td>';
            newRow += '<td>' + name + '<input type="hidden" name="wsl_currencies[' + code + '][name]" value="' + name + '"></td>';
            newRow += '<td><input type="checkbox" name="wsl_currencies[' + code + '][enabled]" value="1" ' + (enabled ? 'checked' : '') + '></td>';
            newRow += '<td><input type="radio" name="wsl_currency_default" value="' + code + '"></td>';
            newRow += '<td><button type="button" class="button button-small edit-currency">' + wsl_currency.edit_text + '</button> ';
            newRow += '<button type="button" class="button button-small delete-currency">' + wsl_currency.delete_text + '</button></td>';
            newRow += '</tr>';
            
            $('.wsl-currency-table tbody').append(newRow);
            
            // Reset and close dialog
            $('#new-currency-code').val('');
            $('#new-currency-name').val('');
            $('#add-currency-dialog').hide();
        });
        
        // Update currency
        $('.update-currency').on('click', function() {
            var code = $('#edit-currency-code').val();
            var name = $('#edit-currency-name').val();
            var enabled = $('#edit-currency-enabled').prop('checked');
            
            if (!name) {
                alert(wsl_currency.currency_name_required);
                return;
            }
            
            var row = $('.wsl-currency-table tr[data-code="' + code + '"]');
            
            // Update row
            row.find('td:nth-child(2)').text(name);
            row.find('input[name="wsl_currencies[' + code + '][name]"]').val(name);
            row.find('input[type="checkbox"]').prop('checked', enabled);
            
            // Close dialog
            $('#edit-currency-dialog').hide();
        });
        
        // Delete currency
        $('.wsl-currency-table').on('click', '.delete-currency', function() {
            if (confirm(wsl_currency.delete_confirm)) {
                $(this).closest('tr').remove();
                
                // If last row was deleted, ensure we have at least one default currency
                if ($('.wsl-currency-table tbody tr').length === 0) {
                    var defaultRow = '<tr data-code="USD">';
                    defaultRow += '<td>USD<input type="hidden" name="wsl_currencies[USD][code]" value="USD"></td>';
                    defaultRow += '<td>US Dollar<input type="hidden" name="wsl_currencies[USD][name]" value="US Dollar"></td>';
                    defaultRow += '<td><input type="checkbox" name="wsl_currencies[USD][enabled]" value="1" checked></td>';
                    defaultRow += '<td><input type="radio" name="wsl_currency_default" value="USD" checked></td>';
                    defaultRow += '<td><button type="button" class="button button-small edit-currency">' + wsl_currency.edit_text + '</button></td>';
                    defaultRow += '</tr>';
                    
                    $('.wsl-currency-table tbody').append(defaultRow);
                }
                
                // Ensure we have one default currency selected
                if ($('input[name="wsl_currency_default"]:checked').length === 0) {
                    $('.wsl-currency-table tbody tr:first-child').find('input[name="wsl_currency_default"]').prop('checked', true);
                }
            }
        });
    });
    
})(jQuery);