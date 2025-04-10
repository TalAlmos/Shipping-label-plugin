<?php
/**
 * FedEx API Configuration
 * 
 * IMPORTANT: This file contains sensitive information and should not be committed to version control.
 * In production, these values should be moved to the plugin settings.
 */

if (!defined('WPINC')) {
    die;
}

return array(
    // API Endpoints
    'endpoints' => array(
        'auth' => array(
            'production' => 'https://apis.fedex.com/oauth/token',
            'sandbox' => 'https://apis-sandbox.fedex.com/oauth/token',
        ),
        'address_validation' => array(
            'production' => 'https://apis.fedex.com/address/v1/addresses/resolve',
            'sandbox' => 'https://apis-sandbox.fedex.com/address/v1/addresses/resolve',
        ),
        'rate' => array(
            'production' => 'https://apis.fedex.com/rate/v1/rates/quotes',
            'sandbox' => 'https://apis-sandbox.fedex.com/rate/v1/rates/quotes',
        ),
    ),
    
    // Authentication credentials
    'credentials' => array(
        'client_id' => 'l7348984d9f3994612b75584a7e2eb5f95',     // Your FedEx API Key
        'client_secret' => 'ca7b1db3c5d44f06bbc5fbda921a6e05', // Your FedEx API Secret
        'account_number' => '208209676', // Your FedEx Account Number
    ),
    
    // Environment setting ('sandbox' or 'production')
    'environment' => 'sandbox',
    
    // Other settings
    'timeout' => 30, // API request timeout in seconds
); 