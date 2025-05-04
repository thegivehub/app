# Address Validation API Documentation

## Overview

The Address Validation API provides a RESTful interface to validate and normalize postal addresses for multiple countries. The service supports addresses in the United States, Canada, United Kingdom, and Australia.

## Endpoint

```
POST https://app.thegivehub.com/api/address/validate
```

## Request Format

Send a POST request with a JSON payload containing the address components.

### Required Fields:
- `street`: Street address including number and name
- `city`: City or locality name
- `country`: Country code (US, CA, UK, AU)

### Optional Fields (may be required for specific countries):
- `state`: State, province, or territory (required for US and CA)
- `zip`: Postal code, ZIP code, or postcode (required for US, CA, and UK)
- `unit`: Apartment, suite, or unit number (optional)

## Response Format

The API returns a JSON object with the following structure:

```json
{
  "valid": true|false,
  "errors": {
    "field_name": "Error message"
  },
  "normalized": {
    "street": "NORMALIZED STREET ADDRESS",
    "unit": "UNIT NUMBER",
    "city": "NORMALIZED CITY",
    "state": "NORMALIZED STATE",
    "zip": "NORMALIZED POSTAL CODE",
    "country": "COUNTRY CODE"
  },
  "score": 0.95,
  "suggestions": []
}
```

- `valid`: Boolean indicating if the address is valid
- `errors`: Object containing validation errors by field (only present when valid is false)
- `normalized`: Object containing the normalized address components
- `score`: Confidence score from 0 to 1 (only present with external API validation)
- `suggestions`: Alternative address suggestions if available (only present when valid is false)

## Examples

### Example 1: Valid US Address

**Request:**
```json
POST /api/address/validate
{
  "street": "1600 Pennsylvania Ave",
  "city": "Washington",
  "state": "DC",
  "zip": "20500",
  "country": "US"
}
```

**Response:**
```json
{
  "valid": true,
  "normalized": {
    "street": "1600 PENNSYLVANIA AVENUE",
    "unit": "",
    "city": "Washington",
    "state": "DC",
    "zip": "20500",
    "country": "US"
  },
  "score": 0.98
}
```

### Example 2: Invalid Canadian Address

**Request:**
```json
POST /api/address/validate
{
  "street": "123 Maple St",
  "city": "Toronto",
  "zip": "M5V2A",
  "country": "CA"
}
```

**Response:**
```json
{
  "valid": false,
  "errors": {
    "state": "Province is required for Canadian addresses",
    "zip": "Invalid postal code format"
  },
  "suggestions": [
    {
      "street": "123 MAPLE STREET",
      "city": "Toronto",
      "state": "ON",
      "zip": "M5V 2A5",
      "country": "CA"
    }
  ]
}
```

### Example 3: Valid UK Address

**Request:**
```json
POST /api/address/validate
{
  "street": "10 Downing St",
  "city": "London",
  "zip": "SW1A 2AA",
  "country": "UK"
}
```

**Response:**
```json
{
  "valid": true,
  "normalized": {
    "street": "10 DOWNING STREET",
    "unit": "",
    "city": "London",
    "state": "",
    "zip": "SW1A 2AA",
    "country": "UK"
  },
  "score": 0.96
}
```

### Example 4: Address with Missing Required Fields

**Request:**
```json
POST /api/address/validate
{
  "street": "",
  "city": "Sydney",
  "country": "AU"
}
```

**Response:**
```json
{
  "valid": false,
  "errors": {
    "street": "Street is required"
  },
  "normalized": null
}
```

## Country-Specific Requirements

### United States (US)
- Required fields: street, city, state, zip, country
- ZIP code format: 5 digits (12345) or 9 digits with hyphen (12345-6789)

### Canada (CA)
- Required fields: street, city, state (province), zip (postal code), country
- Postal code format: A1A 1A1 (letter-number-letter space number-letter-number)

### United Kingdom (UK)
- Required fields: street, city, zip (postcode), country

### Australia (AU)
- Required fields: street, city, country
- State and postal code recommended but not strictly required for validation

## Normalization Features

The API performs the following normalizations:

1. Street names:
   - Expands common abbreviations (St → Street, Ave → Avenue, etc.)

2. City and state names:
   - Proper capitalization (new york → New York)

3. Postal codes:
   - Country-specific formatting (US: 12345 or 12345-6789, CA: A1A 1A1)

4. Country codes:
   - Normalizes various formats to standard codes (USA, United States → US)

## Error Handling

The API returns specific error messages for:
- Missing required fields
- Invalid postal code formats
- Country-specific validation failures

If the external address validation service is unavailable, the API will fall back to basic format validation and return the best possible result.

## Notes

- Addresses in unsupported countries will only receive basic format validation
- For optimal results, provide all fields relevant to the country
- The confidence score indicates the reliability of the address validation
