<?php

class Crypto {
    public $stellar_public_key;
    public $bitcoin_address;
    public $ethereum_address;
    
    public function __construct() {
        // Read .env file
        $env_file = __DIR__ . '/../.env';
        
        if (file_exists($env_file)) {
            $env_vars = parse_ini_file($env_file);
            
            $this->stellar_public_key = $env_vars['STELLAR_PUBLIC_KEY'] ?? null;
            $this->bitcoin_address = $env_vars['BITCOIN_ADDRESS'] ?? null;
            $this->ethereum_address = $env_vars['ETHEREUM_ADDRESS'] ?? null;
        }
    }
    
    public function getSupportedCryptos() {
        $cryptos = [];
        
        if ($this->stellar_public_key) {
            $cryptos[] = [
                'code' => 'XLM',
                'name' => 'Stellar Lumens',
                'address' => $this->stellar_public_key
            ];
        }
        
        if ($this->bitcoin_address) {
            $cryptos[] = [
                'code' => 'BTC',
                'name' => 'Bitcoin',
                'address' => $this->bitcoin_address
            ];
        }
        
        if ($this->ethereum_address) {
            $cryptos[] = [
                'code' => 'ETH',
                'name' => 'Ethereum',
                'address' => $this->ethereum_address
            ];
        }
        
        return $cryptos;
    }
}