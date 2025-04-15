    /**
     * Transform universal shipment data to carrier-specific format
     * 
     * @param array $shipment_data Universal shipment data
     * @param string $carrier Carrier identifier (fedex, ups, dhl, usps)
     * @return array Shipment data in carrier-specific format
     */
    public static function shipment_to_carrier_format($shipment_data, $carrier) {
        switch (strtolower($carrier)) {
            case 'fedex':
                return self::shipment_to_fedex_format($shipment_data);
            
            case 'ups':
                return self::shipment_to_ups_format($shipment_data);
                
            case 'dhl':
                return self::shipment_to_dhl_format($shipment_data);
                
            case 'usps':
                return self::shipment_to_usps_format($shipment_data);
                
            default:
                return $shipment_data;
        }
    }
    
    /**
     * Transform universal shipment data to FedEx format
     * 
     * @param array $shipment_data Universal shipment data
     * @return array Shipment data in FedEx format
     */
    public static function shipment_to_fedex_format($shipment_data) {
        // Transform addresses
        $shipper = self::address_to_fedex_format($shipment_data['from_address']);
        $recipient = self::address_to_fedex_format($shipment_data['to_address']);
        
        // Build the FedEx shipment request
        $fedex_shipment = array(
            'requestedShipment' => array(
                'shipDatestamp' => date('Y-m-d', strtotime($shipment_data['ship_date'] ?? 'now')),
                'totalDeclaredValue' => array(
                    'amount' => $shipment_data['value'] ?? 0,
                    'currency' => $shipment_data['currency'] ?? 'USD',
                ),
                'shipper' => $shipper,
                'recipients' => array($recipient),
                'pickupType' => $shipment_data['pickup_type'] ?? 'REGULAR_PICKUP',
                'serviceType' => $shipment_data['service_type'] ?? 'FEDEX_GROUND',
                'packagingType' => $shipment_data['package_type'] ?? 'YOUR_PACKAGING',
                'labelSpecification' => array(
                    'labelStockType' => $shipment_data['label_type'] ?? 'PAPER_4X6',
                    'imageType' => $shipment_data['image_type'] ?? 'PDF',
                    'labelFormatType' => 'COMMON2D',
                ),
                'requestedPackageLineItems' => array(
                    array(
                        'weight' => array(
                            'units' => $shipment_data['weight_unit'] ?? 'LB',
                            'value' => $shipment_data['weight'] ?? 1,
                        ),
                        'dimensions' => array(
                            'length' => $shipment_data['length'] ?? 10,
                            'width' => $shipment_data['width'] ?? 8,
                            'height' => $shipment_data['height'] ?? 6,
                            'units' => $shipment_data['dimension_unit'] ?? 'IN',
                        ),
                    ),
                ),
            ),
            'accountNumber' => array(
                'value' => $shipment_data['account_number'] ?? '',
            ),
            'labelResponseOptions' => $shipment_data['label_response_type'] ?? 'URL_ONLY',
        );
        
        // Add international shipment data if needed
        if ($shipment_data['is_international']) {
            $fedex_shipment['requestedShipment']['customsClearanceDetail'] = array(
                'dutiesPayment' => array(
                    'paymentType' => $shipment_data['duties_payment_type'] ?? 'SENDER',
                    'payor' => array(
                        'responsibleParty' => array(
                            'accountNumber' => array(
                                'value' => $shipment_data['account_number'] ?? '',
                            ),
                        ),
                    ),
                ),
                'commodities' => array(),
            );
            
            // Add commodities for customs
            if (!empty($shipment_data['items']) && is_array($shipment_data['items'])) {
                foreach ($shipment_data['items'] as $item) {
                    $fedex_shipment['requestedShipment']['customsClearanceDetail']['commodities'][] = array(
                        'description' => $item['description'] ?? 'Merchandise',
                        'quantity' => $item['quantity'] ?? 1,
                        'weight' => array(
                            'units' => $shipment_data['weight_unit'] ?? 'LB',
                            'value' => $item['weight'] ?? 1,
                        ),
                        'unitPrice' => array(
                            'amount' => $item['value'] ?? 10,
                            'currency' => $shipment_data['currency'] ?? 'USD',
                        ),
                        'customsValue' => array(
                            'amount' => ($item['value'] ?? 10) * ($item['quantity'] ?? 1),
                            'currency' => $shipment_data['currency'] ?? 'USD',
                        ),
                        'countryOfManufacture' => $item['origin_country'] ?? $shipment_data['from_address']['country'] ?? 'US',
                    );
                }
            }
        }
        
        return $fedex_shipment;
    }
    
    /**
     * Transform universal shipment data to UPS format
     * 
     * @param array $shipment_data Universal shipment data
     * @return array Shipment data in UPS format
     */
    public static function shipment_to_ups_format($shipment_data) {
        // Placeholder for UPS implementation
        // This is a simplified structure - refer to UPS API docs for complete format
        
        // Transform addresses to UPS format
        $shipper_info = self::address_to_ups_format($shipment_data['from_address']);
        $ship_to_info = self::address_to_ups_format($shipment_data['to_address']);
        
        // Map service type from our generic format to UPS-specific codes
        $service_map = array(
            'ground' => '03', // UPS Ground
            'next_day_air' => '01', // UPS Next Day Air
            'next_day_air_saver' => '13', // UPS Next Day Air Saver
            'second_day_air' => '02', // UPS 2nd Day Air
            'three_day_select' => '12', // UPS 3 Day Select
            'international_standard' => '11', // UPS Standard
            'international_expedited' => '08', // UPS Expedited
            'international_express' => '07', // UPS Express
        );
        
        $service_code = '03'; // Default to UPS Ground
        if (!empty($shipment_data['service_type']) && isset($service_map[$shipment_data['service_type']])) {
            $service_code = $service_map[$shipment_data['service_type']];
        }
        
        // Build the UPS shipment request
        $ups_shipment = array(
            'ShipmentRequest' => array(
                'Request' => array(
                    'RequestOption' => 'validate',
                ),
                'Shipment' => array(
                    'Shipper' => $shipper_info,
                    'ShipTo' => $ship_to_info,
                    'ShipFrom' => $shipper_info, // Use same address for ShipFrom in this example
                    'Service' => array(
                        'Code' => $service_code,
                    ),
                    'Package' => array(
                        'PackagingType' => array(
                            'Code' => $shipment_data['package_type'] ?? '02', // 02 = Customer Packaging
                        ),
                        'PackageWeight' => array(
                            'UnitOfMeasurement' => array(
                                'Code' => ($shipment_data['weight_unit'] ?? 'LB') === 'KG' ? 'KGS' : 'LBS',
                            ),
                            'Weight' => $shipment_data['weight'] ?? 1,
                        ),
                        'Dimensions' => array(
                            'UnitOfMeasurement' => array(
                                'Code' => ($shipment_data['dimension_unit'] ?? 'IN') === 'CM' ? 'CM' : 'IN',
                            ),
                            'Length' => $shipment_data['length'] ?? 10,
                            'Width' => $shipment_data['width'] ?? 8,
                            'Height' => $shipment_data['height'] ?? 6,
                        ),
                    ),
                    'PaymentInformation' => array(
                        'ShipmentCharge' => array(
                            'Type' => '01', // 01 = Transportation
                            'BillShipper' => array(
                                'AccountNumber' => $shipment_data['account_number'] ?? '',
                            ),
                        ),
                    ),
                ),
                'LabelSpecification' => array(
                    'LabelImageFormat' => array(
                        'Code' => $shipment_data['image_type'] ?? 'PDF',
                    ),
                    'LabelStockSize' => array(
                        'Height' => '6',
                        'Width' => '4',
                    ),
                ),
            ),
        );
        
        // Add international shipment details if applicable
        if ($shipment_data['is_international']) {
            $ups_shipment['ShipmentRequest']['Shipment']['InternationalForm'] = array(
                'FormType' => '01', // 01 = Invoice
                'InvoiceDate' => date('Ymd'),
                'CurrencyCode' => $shipment_data['currency'] ?? 'USD',
                'Product' => array(),
            );
            
            // Add products for customs
            if (!empty($shipment_data['items']) && is_array($shipment_data['items'])) {
                foreach ($shipment_data['items'] as $item) {
                    $ups_shipment['ShipmentRequest']['Shipment']['InternationalForm']['Product'][] = array(
                        'Description' => $item['description'] ?? 'Merchandise',
                        'Unit' => array(
                            'Number' => $item['quantity'] ?? 1,
                            'UnitOfMeasurement' => array(
                                'Code' => 'EA', // Each
                            ),
                            'Value' => $item['value'] ?? 10,
                        ),
                        'OriginCountryCode' => $item['origin_country'] ?? $shipment_data['from_address']['country'] ?? 'US',
                    );
                }
            }
        }
        
        return $ups_shipment;
    }
    
    /**
     * Transform universal shipment data to DHL format
     * 
     * @param array $shipment_data Universal shipment data
     * @return array Shipment data in DHL format
     */
    public static function shipment_to_dhl_format($shipment_data) {
        // Placeholder for DHL implementation
        // This will need to be expanded with the actual DHL API requirements
        
        // Transform addresses to DHL format
        $shipper = self::address_to_dhl_format($shipment_data['from_address']);
        $recipient = self::address_to_dhl_format($shipment_data['to_address']);
        
        // Map service type to DHL product codes
        $service_map = array(
            'express' => 'P', // DHL Express
            'priority' => 'D', // DHL Express 9:00
            'standard' => 'T', // DHL Express 12:00
            'economy' => 'K', // DHL Express Worldwide
        );
        
        $service_code = 'P'; // Default to Express
        if (!empty($shipment_data['service_type']) && isset($service_map[$shipment_data['service_type']])) {
            $service_code = $service_map[$shipment_data['service_type']];
        }
        
        // Build DHL shipment request
        $dhl_shipment = array(
            'ShipmentRequest' => array(
                'RequestedShipment' => array(
                    'ShipTimestamp' => date('Y-m-d\TH:i:s\Z'),
                    'PaymentInfo' => 'DAP', // Delivered At Place
                    'ShipType' => 'PL', // Package
                    'Shipper' => $shipper,
                    'Recipient' => $recipient,
                    'Packages' => array(
                        'RequestedPackages' => array(
                            array(
                                'Weight' => $shipment_data['weight'] ?? 1,
                                'Dimensions' => array(
                                    'Length' => $shipment_data['length'] ?? 10,
                                    'Width' => $shipment_data['width'] ?? 8,
                                    'Height' => $shipment_data['height'] ?? 6,
                                ),
                                'CustomerReferences' => $shipment_data['reference'] ?? '',
                            ),
                        ),
                    ),
                    'ShipmentInfo' => array(
                        'ServiceType' => $service_code,
                        'Account' => $shipment_data['account_number'] ?? '',
                    ),
                    'LabelOptions' => array(
                        'PrinterDPI' => '300',
                        'ImageFormat' => $shipment_data['image_type'] ?? 'PDF',
                    ),
                ),
            ),
        );
        
        // Add international shipment data if needed
        if ($shipment_data['is_international']) {
            $dhl_shipment['ShipmentRequest']['RequestedShipment']['InternationalDetail'] = array(
                'Commodities' => array(
                    'Description' => 'Merchandise',
                    'CustomsValue' => $shipment_data['value'] ?? 10,
                    'Content' => 'MERCHANDISE', // or DOCUMENTS, GIFT, SAMPLE, RETURN_MERCHANDISE, etc.
                ),
            );
            
            // Add commodities for customs
            if (!empty($shipment_data['items']) && is_array($shipment_data['items'])) {
                $dhl_shipment['ShipmentRequest']['RequestedShipment']['InternationalDetail']['ExportLineItems'] = array();
                
                foreach ($shipment_data['items'] as $item) {
                    $dhl_shipment['ShipmentRequest']['RequestedShipment']['InternationalDetail']['ExportLineItems'][] = array(
                        'Description' => $item['description'] ?? 'Merchandise',
                        'Quantity' => $item['quantity'] ?? 1,
                        'Value' => $item['value'] ?? 10,
                        'Weight' => array(
                            'Weight' => $item['weight'] ?? 1,
                            'WeightUnit' => ($shipment_data['weight_unit'] ?? 'LB') === 'KG' ? 'KG' : 'LB',
                        ),
                        'ManufacturingCountryCode' => $item['origin_country'] ?? $shipment_data['from_address']['country'] ?? 'US',
                    );
                }
            }
        }
        
        return $dhl_shipment;
    }
    
    /**
     * Transform universal shipment data to USPS format
     * 
     * @param array $shipment_data Universal shipment data
     * @return array Shipment data in USPS format
     */
    public static function shipment_to_usps_format($shipment_data) {
        // Placeholder for USPS implementation
        // This will need to be expanded with the actual USPS API requirements
        
        // Transform addresses to USPS format
        $from_address = self::address_to_usps_format($shipment_data['from_address']);
        $to_address = self::address_to_usps_format($shipment_data['to_address']);
        
        // Map service type to USPS mail class
        $service_map = array(
            'priority' => 'Priority',
            'priority_express' => 'Priority Express',
            'first_class' => 'First Class',
            'ground' => 'Retail Ground',
            'media' => 'Media Mail',
        );
        
        $mail_class = 'Priority'; // Default to Priority Mail
        if (!empty($shipment_data['service_type']) && isset($service_map[$shipment_data['service_type']])) {
            $mail_class = $service_map[$shipment_data['service_type']];
        }
        
        // USPS uses ounces for weight
        $weight_in_oz = 16; // Default to 1lb = 16oz
        if (!empty($shipment_data['weight'])) {
            if (($shipment_data['weight_unit'] ?? 'LB') === 'LB') {
                $weight_in_oz = $shipment_data['weight'] * 16;
            } else {
                // Convert kg to oz (1kg = 35.274oz)
                $weight_in_oz = $shipment_data['weight'] * 35.274;
            }
        }
        
        // Build USPS shipment request
        $usps_shipment = array(
            'API' => 'CreateShipment',
            'XMLInput' => '<?xml version="1.0" encoding="UTF-8" ?>',
            'LabelRequest' => array(
                'FromAddress' => $from_address,
                'ToAddress' => $to_address,
                'WeightInOunces' => (int) $weight_in_oz,
                'ServiceType' => $mail_class,
                'Container' => $shipment_data['package_type'] ?? 'VARIABLE',
                'Size' => 'REGULAR',
                'Width' => $shipment_data['width'] ?? 8,
                'Length' => $shipment_data['length'] ?? 10,
                'Height' => $shipment_data['height'] ?? 6,
                'Machinable' => 'true',
                'ImageType' => $shipment_data['image_type'] ?? 'PDF',
                'LabelDate' => date('Y-m-d'),
                'CustomerRefNo' => $shipment_data['reference'] ?? '',
                'AddressServiceRequested' => 'true', // Validate and standardize address
            ),
        );
        
        // Add international shipment details if applicable
        if ($shipment_data['is_international']) {
            $usps_shipment['LabelRequest']['Container'] = 'RECTANGULAR';
            $usps_shipment['LabelRequest']['GXG'] = array(
                'POBoxFlag' => 'N',
                'GiftFlag' => 'N',
            );
            
            $usps_shipment['LabelRequest']['CustomsInfo'] = array(
                'ContentType' => 'MERCHANDISE',
                'ContentDescription' => 'Merchandise',
                'CustomsItems' => array(
                    'CustomsItem' => array(),
                ),
            );
            
            // Add items for customs
            if (!empty($shipment_data['items']) && is_array($shipment_data['items'])) {
                foreach ($shipment_data['items'] as $item) {
                    $usps_shipment['LabelRequest']['CustomsInfo']['CustomsItems']['CustomsItem'][] = array(
                        'Description' => $item['description'] ?? 'Merchandise',
                        'Quantity' => $item['quantity'] ?? 1,
                        'Value' => $item['value'] ?? 10,
                        'Weight' => $item['weight'] ?? 1,
                        'CountryOfOrigin' => $item['origin_country'] ?? $shipment_data['from_address']['country'] ?? 'US',
                    );
                }
            }
        }
        
        return $usps_shipment;
    }
} 