<?php
// lib/AddressValidator.php
class AddressValidator {
    private $apiKey;
    private $supportedCountries = ['US', 'CA', 'UK', 'AU'];
    
    public function __construct($apiKey = null) {
        // If no API key is provided, we'll use environment variable or fall back to local validation
        $this->apiKey = $apiKey ?: getenv('ADDRESS_VALIDATION_API_KEY');
    }
    
    /**
     * Validate an address
     * 
     * @param array $address Address data including street, city, state, zip, country
     * @return array Validation result with status and normalized address
     */
    public function validate($address) {
        // Basic validation checks
        $errors = $this->basicValidation($address);
        if (!empty($errors)) {
            return [
                'valid' => false,
                'errors' => $errors,
                'normalized' => null
            ];
        }
        
        // If we have API key, use external service for validation
        if ($this->apiKey && !empty($address['country'])) {
            $countryCode = strtoupper($address['country']);
            if (in_array($countryCode, $this->supportedCountries)) {
                return $this->externalValidation($address);
            }
        }
        
        // Fall back to format validation if no API key or unsupported country
        return $this->formatValidation($address);
    }
    
    /**
     * Perform basic validation checks
     * 
     * @param array $address Address data
     * @return array Validation errors
     */
    private function basicValidation($address) {
        $errors = [];
        
        // Check required fields
        $requiredFields = ['street', 'city', 'country'];
        foreach ($requiredFields as $field) {
            if (empty($address[$field])) {
                $errors[$field] = ucfirst($field) . ' is required';
            }
        }
        
        // Country-specific requirements
        if (!empty($address['country'])) {
            $countryCode = strtoupper($address['country']);
            
            switch ($countryCode) {
                case 'US':
                    if (empty($address['state'])) {
                        $errors['state'] = 'State is required for US addresses';
                    }
                    if (empty($address['zip'])) {
                        $errors['zip'] = 'ZIP code is required for US addresses';
                    } elseif (!preg_match('/^\d{5}(-\d{4})?$/', $address['zip'])) {
                        $errors['zip'] = 'Invalid ZIP code format';
                    }
                    break;
                    
                case 'CA':
                    if (empty($address['state'])) {
                        $errors['state'] = 'Province is required for Canadian addresses';
                    }
                    if (empty($address['zip'])) {
                        $errors['zip'] = 'Postal code is required for Canadian addresses';
                    } elseif (!preg_match('/^[A-Za-z]\d[A-Za-z] \d[A-Za-z]\d$/', $address['zip'])) {
                        $errors['zip'] = 'Invalid postal code format';
                    }
                    break;
                    
                case 'UK':
                    if (empty($address['zip'])) {
                        $errors['zip'] = 'Postcode is required for UK addresses';
                    }
                    break;
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate address format without external API
     * 
     * @param array $address Address data
     * @return array Validation result
     */
    private function formatValidation($address) {
        $normalized = $this->normalizeAddress($address);
        
        // Additional format validation logic could be added here
        // For now we just normalize and assume valid if it passed basic validation
        
        return [
            'valid' => true,
            'errors' => [],
            'normalized' => $normalized
        ];
    }
    
    /**
     * Validate address using external API
     * 
     * @param array $address Address data
     * @return array Validation result
     */
    private function externalValidation($address) {
        try {
            // Prepare API request data
            $requestData = [
                'address_line1' => $address['street'],
                'address_line2' => $address['unit'] ?? '',
                'locality' => $address['city'],
                'administrative_area' => $address['state'] ?? '',
                'postal_code' => $address['zip'] ?? '',
                'country_code' => strtoupper($address['country'])
            ];
            
            // Make API request to validation service
            $response = $this->makeApiRequest($requestData);
            
            if ($response['status'] === 'VALID') {
                $components = $response['address_components'];
                
                return [
                    'valid' => true,
                    'score' => $response['validity_score'] ?? 1.0,
                    'normalized' => [
                        'street' => $components['address_line1'] ?? $address['street'],
                        'unit' => $components['address_line2'] ?? ($address['unit'] ?? ''),
                        'city' => $components['locality'] ?? $address['city'],
                        'state' => $components['administrative_area'] ?? ($address['state'] ?? ''),
                        'zip' => $components['postal_code'] ?? ($address['zip'] ?? ''),
                        'country' => $components['country_code'] ?? $address['country']
                    ]
                ];
            } else {
                return [
                    'valid' => false,
                    'errors' => [
                        'address' => 'Invalid address: ' . ($response['error_message'] ?? 'Could not validate')
                    ],
                    'suggestions' => $response['suggestions'] ?? []
                ];
            }
        } catch (Exception $e) {
            error_log('Address validation API error: ' . $e->getMessage());
            
            // Fall back to format validation if API call fails
            return $this->formatValidation($address);
        }
    }
    
    /**
     * Make API request to validation service
     * 
     * @param array $data Address data
     * @return array Response data
     */
    private function makeApiRequest($data) {
        // This is a placeholder. In a real implementation, this would make an HTTP request to the API.
        // Using cURL or Guzzle HTTP client to make the actual API request.
        
        // Example:
        /*
        $ch = curl_init('https://api.address-validator.com/v1/validate');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true);
        */
        
        // For now, simulate a response
        $isValid = !empty($data['address_line1']) && !empty($data['locality']) && !empty($data['country_code']);
        
        if ($isValid) {
            return [
                'status' => 'VALID',
                'validity_score' => 0.95,
                'address_components' => [
                    'address_line1' => strtoupper($data['address_line1']),
                    'address_line2' => strtoupper($data['address_line2']),
                    'locality' => strtoupper($data['locality']),
                    'administrative_area' => strtoupper($data['administrative_area']),
                    'postal_code' => strtoupper($data['postal_code']),
                    'country_code' => strtoupper($data['country_code'])
                ]
            ];
        } else {
            return [
                'status' => 'INVALID',
                'error_message' => 'Missing required address components',
                'suggestions' => []
            ];
        }
    }
    
    /**
     * Normalize address data (formatting, casing, etc.)
     * 
     * @param array $address Raw address data
     * @return array Normalized address data
     */
    public function normalizeAddress($address) {
        $normalized = [];
        
        // Standardize field values
        foreach ($address as $key => $value) {
            $value = trim($value);
            
            switch ($key) {
                case 'street':
                    // Standardize common abbreviations
                    $value = preg_replace('/\bSt\b\.?/i', 'Street', $value);
                    $value = preg_replace('/\bAve\b\.?/i', 'Avenue', $value);
                    $value = preg_replace('/\bRd\b\.?/i', 'Road', $value);
                    $value = preg_replace('/\bBlvd\b\.?/i', 'Boulevard', $value);
                    $normalized[$key] = $value;
                    break;
                    
                case 'city':
                case 'state':
                    $normalized[$key] = ucwords(strtolower($value));
                    break;
                    
                case 'zip':
                    // Format based on country
                    if (!empty($address['country']) && strtoupper($address['country']) === 'CA') {
                        // Format Canadian postal code: A1A 1A1
                        $value = strtoupper($value);
                        if (preg_match('/^([A-Z]\d[A-Z])\s*(\d[A-Z]\d)$/i', $value, $matches)) {
                            $value = $matches[1] . ' ' . $matches[2];
                        }
                    } else if (!empty($address['country']) && strtoupper($address['country']) === 'US') {
                        // Format US ZIP: 12345 or 12345-6789
                        if (preg_match('/^(\d{5})-?(\d{4})?$/', $value, $matches)) {
                            $value = $matches[1];
                            if (!empty($matches[2])) {
                                $value .= '-' . $matches[2];
                            }
                        }
                    } else {
                        // Default format for other countries
                        $value = strtoupper(str_replace(' ', '', $value));
                    }
                    $normalized[$key] = $value;
                    break;
                    
                case 'country':
                    // Normalize country code
                    $countries = [
                        'US' => 'US', 'USA' => 'US', 'UNITED STATES' => 'US',
                        'CA' => 'CA', 'CANADA' => 'CA',
                        'UK' => 'UK', 'GB' => 'UK', 'UNITED KINGDOM' => 'UK',
                        'AU' => 'AU', 'AUSTRALIA' => 'AU'
                    ];
                    
                    $upperValue = strtoupper($value);
                    $normalized[$key] = isset($countries[$upperValue]) ? $countries[$upperValue] : $value;
                    break;
                    
                default:
                    $normalized[$key] = $value;
            }
        }
        
        return $normalized;
    }
}
