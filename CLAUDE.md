# The Give Hub Development Guide

## Build & Test Commands
- Install PHP dependencies: `composer install`
- Install JS dependencies: `npm install`
- Run all PHP tests: `./vendor/bin/phpunit`
- Run a single test: `./vendor/bin/phpunit --filter TestName`
- Run a specific test file: `./vendor/bin/phpunit tests/SomeTest.php`
- Test DB connection: `php test-mongodb.php`

## Code Style Guidelines
- **PHP**: PascalCase for classes, camelCase for methods/variables
- **JS**: camelCase for functions/variables, namespaced objects
- **Formatting**: 4-space indentation, no trailing whitespace
- **Error Handling**: Use try/catch with structured response objects `{success: bool, error: string}`
- **PHP Imports**: Use `require_once` for files, `use` for namespaced classes
- **JS Imports**: Use ES modules for new code, IIFE pattern for browser code
- **Documentation**: PHPDoc/JSDoc style comments for methods with @param and @return tags
- **Model Structure**: Extend from `Model.php` or `MongoModel.php` base classes
- **Tests**: Place in `/tests` directory, use proper setup/teardown methods
- **CSS**: Use `/css/style.css` as a base stylesheet. Use CSS variables when appropriate. For custom page style requirements, create a new css file, appropriately named (eg: users-style.css), in the `/css` folder
- **Development Patterns**: 
    - Keep all executable code within the 'api' scope with resource class extensions (see below). 
    - All pages should be straight HTML with javascript fetching and rendering any dynamic data from the server api and never PHP generated. 
    - No React and no PHP in HTML files.
    - No transpiling
    - Use/Re-use web components whenever possible
        - Especially for core things such as navigation, authentication, viewers, etc.
        - Always check for web components in the /components directory
        - If no web component exists for your functionality and you think it would be a good fit, ask to create a new web component and create one if agreed

## Environment
- Development uses PHP 8.1
- MongoDB for database storage
- Stellar blockchain integration uses testnet during development

## Development Settings
- **Authentication**: JWT token expiration is disabled in development mode (see `dev_mode` flag in Auth.php)
- To enable token expiration checks (production behavior), set `dev_mode` to false in Auth.php
- Always double check your work and deliver clean, functional and **consistent** code

# The Give Hub API Architecture
The API follows a simple, extendable pattern based on automatic routing:

### Routing Logic
- Requests to `/api.php/[resource]/[action]?[params]` are automatically routed
- The system parses `PATH_INFO` to extract resource and action
- Code: `$parts = explode('/', $_SERVER['PATH_INFO']); $instance = new $parts[1](); $instance->$parts[2]();`

### Endpoint Patterns
- `GET /api.php/resource` - List all resources
- `GET /api.php/resource?id=123` - Get specific resource by ID
- `POST /api.php/resource` - Create new resource
- `PUT /api.php/resource?id=123` - Update resource
- `DELETE /api.php/resource?id=123` - Delete resource
- `GET|POST /api.php/resource/customMethod?param=value` - Execute custom method

### Extending API
1. Create or modify a class file in the `/lib` directory (class name = resource name)
2. Add public methods to expose as API actions
3. Methods are automatically available at `/api.php/[ResourceName]/[methodName]`
4. No API controller modifications needed

### Response Format
- Default: JSON response with data or error message
- Success: `{success: true, data: [...]}` or direct array/object
- Error: `{success: false, error: "Error message"}`

### Authentication
- JWT tokens used for auth
- Tokens passed in Authorization header
- Some endpoints require authentication, others are public

### Example
To add a wallet funding endpoint:
1. Add method to Wallet class: `public function fund($publicKey) {...}`
2. The endpoint is automatically available at `/api.php/wallet/fund`
3. Call with `POST /api.php/wallet/fund` with public key in request body

This explains how the API architecture works through automatic routing, making it easy to extend functionality without modifying the core API
controller.

## Cryptocurrency Wallet Configuration (.env values)
For production, use the following environment variables to configure donation wallets:

### Stellar XLM Wallet
- `STELLAR_PUBLIC_KEY` - Public key for the Stellar donation receiving wallet
- `STELLAR_SECRET_KEY` - Secret key for the Stellar donation wallet (secure backup needed)
- `STELLAR_NETWORK` - Set to 'public' for mainnet, 'testnet' for testnet

### Ethereum Wallet
- `ETHEREUM_ADDRESS` - Ethereum wallet address for receiving donations
- `ETHEREUM_PRIVATE_KEY` - Private key for the Ethereum wallet (secure backup needed)
- `ETHEREUM_NETWORK` - Set to 'mainnet', 'goerli', or other testnet name as needed

### Bitcoin Wallet
- `BITCOIN_ADDRESS` - Bitcoin wallet address for receiving donations
- `BITCOIN_PRIVATE_KEY` - Private key for the Bitcoin wallet (secure backup needed)
- `BITCOIN_NETWORK` - Set to 'mainnet' or 'testnet'

### General Settings
- `DEFAULT_DONATION_CURRENCY` - Default currency for donations ('XLM', 'ETH', or 'BTC')
- `ENABLE_MULTIPLE_CRYPTOCURRENCIES` - Set to 'true' to enable multiple cryptocurrency options

IMPORTANT: Never commit these values directly to the codebase. Always use environment variables.