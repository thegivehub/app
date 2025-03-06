<?php
// lib/AddressManager.php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/AddressValidator.php';
require_once __DIR__ . '/Auth.php';

class AddressManager {
    private $db;
    private $auth;
    private $collection;
    private $validator;
    
    public function __construct() {
        $this->db = new Database();
        $this->auth = new Auth();
        $this->collection = $this->db->getCollection('addresses');
        $this->validator = new AddressValidator();
        
        // Create indexes if they don't exist
        $this->setupCollection();
    }
    
    /**
     * Set up the collection and indexes
     */
    private function setupCollection() {
        $result = $this->collection->listIndexes();
        
        // Check if we need to create indexes
        if (!$result['success'] || empty($result['indexes'])) {
            $this->collection->createIndexes([
                [
                    'key' => ['userId' => 1]
                ],
                [
                    'key' => ['userId' => 1, 'isDefault' => 1]
                ],
                [
                    'key' => ['userId' => 1, 'type' => 1]
                ]
            ]);
        }
    }
    
    /**
     * Get user ID from token
     */
    private function getUserId() {
        $userId = $this->auth->getCurrentUser()['_id'] ?? null;
        
        if (!$userId) {
            throw new Exception('Authentication required');
        }
        
        return $userId;
    }
    
    /**
     * List all addresses for the current user
     */
    public function listAddresses() {
        try {
            $userId = $this->getUserId();
            
            $addresses = $this->collection->find([
                'userId' => $userId
            ], [
                'sort' => ['isDefault' => -1, 'created' => -1]
            ]);
            
            return [
                'success' => true,
                'addresses' => $addresses
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get a single address by ID
     */
    public function getAddress($id) {
        try {
            $userId = $this->getUserId();
            
            $address = $this->collection->findOne([
                '_id' => new MongoDB\BSON\ObjectId($id),
                'userId' => $userId
            ]);
            
            if (!$address) {
                throw new Exception('Address not found');
            }
            
            return [
                'success' => true,
                'address' => $address
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Add a new address
     */
    public function addAddress($data) {
        try {
            $userId = $this->getUserId();
            
            // Validate the address
            $validationResult = $this->validator->validate([
                'street' => $data['street'] ?? '',
                'unit' => $data['unit'] ?? '',
                'city' => $data['city'] ?? '',
                'state' => $data['state'] ?? '',
                'zip' => $data['zip'] ?? '',
                'country' => $data['country'] ?? ''
            ]);
            
            if (!$validationResult['valid']) {
                return [
                    'success' => false,
                    'errors' => $validationResult['errors'],
                    'suggestions' => $validationResult['suggestions'] ?? []
                ];
            }
            
            // Prepare address document
            $addressData = [
                'userId' => $userId,
                'type' => $data['type'] ?? 'home',
                'street' => $validationResult['normalized']['street'],
                'unit' => $validationResult['normalized']['unit'] ?? '',
                'city' => $validationResult['normalized']['city'],
                'state' => $validationResult['normalized']['state'] ?? '',
                'zip' => $validationResult['normalized']['zip'] ?? '',
                'country' => $validationResult['normalized']['country'],
                'isDefault' => isset($data['isDefault']) && $data['isDefault'] ? true : false,
                'created' => new MongoDB\BSON\UTCDateTime(),
                'updated' => new MongoDB\BSON\UTCDateTime()
            ];
            
            // If this is set as default, update any existing default addresses
            if ($addressData['isDefault']) {
                $this->collection->updateMany(
                    ['userId' => $userId, 'isDefault' => true],
                    ['$set' => ['isDefault' => false]]
                );
            }
            // If no addresses exist, make this the default
            else {
                $existingAddresses = $this->collection->count(['userId' => $userId]);
                if ($existingAddresses === 0) {
                    $addressData['isDefault'] = true;
                }
            }
            
            // Insert the new address
            $result = $this->collection->insertOne($addressData);
            
            if (!$result['success']) {
                throw new Exception('Failed to add address');
            }
            
            return [
                'success' => true,
                'message' => 'Address added successfully',
                'addressId' => $result['id']
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Update an existing address
     */
    public function updateAddress($data) {
        try {
            if (empty($data['_id'])) {
                throw new Exception('Address ID is required');
            }
            
            $userId = $this->getUserId();
            $addressId = new MongoDB\BSON\ObjectId($data['_id']);
            
            // Verify the address belongs to this user
            $existingAddress = $this->collection->findOne([
                '_id' => $addressId,
                'userId' => $userId
            ]);
            
            if (!$existingAddress) {
                throw new Exception('Address not found');
            }
            
            // Validate the updated address
            $validationResult = $this->validator->validate([
                'street' => $data['street'] ?? '',
                'unit' => $data['unit'] ?? '',
                'city' => $data['city'] ?? '',
                'state' => $data['state'] ?? '',
                'zip' => $data['zip'] ?? '',
                'country' => $data['country'] ?? ''
            ]);
            
            if (!$validationResult['valid']) {
                return [
                    'success' => false,
                    'errors' => $validationResult['errors'],
                    'suggestions' => $validationResult['suggestions'] ?? []
                ];
            }
            
            // Prepare update data
            $updateData = [
                'type' => $data['type'] ?? $existingAddress['type'],
                'street' => $validationResult['normalized']['street'],
                'unit' => $validationResult['normalized']['unit'] ?? '',
                'city' => $validationResult['normalized']['city'],
                'state' => $validationResult['normalized']['state'] ?? '',
                'zip' => $validationResult['normalized']['zip'] ?? '',
                'country' => $validationResult['normalized']['country'],
                'isDefault' => isset($data['isDefault']) && $data['isDefault'] ? true : false,
                'updated' => new MongoDB\BSON\UTCDateTime()
            ];
            
            // If this is being set as default, update any existing default addresses
            if ($updateData['isDefault'] && !$existingAddress['isDefault']) {
                $this->collection->updateMany(
                    ['userId' => $userId, 'isDefault' => true],
                    ['$set' => ['isDefault' => false]]
                );
            }
            
            // Update the address
            $result = $this->collection->updateOne(
                ['_id' => $addressId],
                ['$set' => $updateData]
            );
            
            if (!$result['success']) {
                throw new Exception('Failed to update address');
            }
            
            return [
                'success' => true,
                'message' => 'Address updated successfully'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Delete an address
     */
    public function deleteAddress($id) {
        try {
            $userId = $this->getUserId();
            $addressId = new MongoDB\BSON\ObjectId($id);
            
            // Verify the address belongs to this user and check if it's default
            $address = $this->collection->findOne([
                '_id' => $addressId,
                'userId' => $userId
            ]);
            
            if (!$address) {
                throw new Exception('Address not found');
            }
            
            // Delete the address
            $result = $this->collection->deleteOne([
                '_id' => $addressId
            ]);
            
            if (!$result['success']) {
                throw new Exception('Failed to delete address');
            }
            
            // If deleted address was the default, set another address as default
            if ($address['isDefault']) {
                $addresses = $this->collection->find(
                    ['userId' => $userId],
                    ['sort' => ['created' => -1], 'limit' => 1]
                );
                
                if (!empty($addresses)) {
                    $this->collection->updateOne(
                        ['_id' => new MongoDB\BSON\ObjectId($addresses[0]['_id'])],
                        ['$set' => ['isDefault' => true]]
                    );
                }
            }
            
            return [
                'success' => true,
                'message' => 'Address deleted successfully'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
