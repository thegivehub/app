<?php
require_once __DIR__ . '/db.php';

/**
 * Base Collection Class
 * 
 * Consolidated base class that combines functionality from:
 * - Collection.php
 * - Model.php
 * - MongoModel.php
 * - MongoCollection.php
 * 
 * Features:
 * - Automatic CRUD operations
 * - MongoDB type conversion
 * - Pagination support
 * - Error handling
 * - User context awareness
 * - Query builder methods
 */
abstract class Collection {
    /** @var MongoCollection */
    protected $collection;
    
    /** @var Database */
    protected $db;
    
    /** @var string */
    protected $collectionName;
    
    /** @var array */
    protected $defaultOptions = [
        'limit' => 20,
        'page' => 1
    ];

    /**
     * Initialize collection
     * 
     * @throws Exception If collection initialization fails
     */
    public function __construct() {
        try {
            // Get database instance
            $this->db = Database::getInstance();
            
            // Determine collection name from class if not set
            if (!$this->collectionName) {
                $this->collectionName = $this->getCollectionName();
            }
            
            // Initialize collection
            $this->collection = $this->db->getCollection($this->collectionName);
            
            if (!$this->collection) {
                throw new Exception("Failed to initialize collection: {$this->collectionName}");
            }
        } catch (Exception $e) {
            error_log("Collection initialization error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get standardized collection name from class name
     * 
     * @return string
     */
    protected function getCollectionName(): string {
        $className = strtolower(get_class($this));
        return rtrim($className, 's') . 's';
    }

    /**
     * Create a new document
     * 
     * @param array $data Document data
     * @return MongoDB\InsertOneResult|array MongoDB result object or error array
     */
    public function create($data) {
        try {
            // Add timestamps if not already present
            if (!isset($data['createdAt'])) {
                $data['createdAt'] = new MongoDB\BSON\UTCDateTime();
            }
            if (!isset($data['updatedAt'])) {
                $data['updatedAt'] = new MongoDB\BSON\UTCDateTime();
            }
            
            // Insert document and return MongoDB\InsertOneResult object
            $result = $this->collection->insertOne($data);
            
            // Return the result without checking getInsertedCount to avoid potential errors
            return $result;
        } catch (Exception $e) {
            error_log("Create error in {$this->collectionName}: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Read one or many documents
     * 
     * @param string|null $id Document ID
     * @param array $options Query options
     * @return array|null Document(s) or null
     */
    public function read($id = null, $options = []) {
        try {
            // Merge with default options
            $options = array_merge($this->defaultOptions, $options);
            
            if ($id !== null) {
                return $this->collection->findOne([
                    '_id' => new MongoDB\BSON\ObjectId($id)
                ]);
            }
            
            // Handle pagination
            if (isset($options['page'])) {
                $options['skip'] = ($options['page'] - 1) * $options['limit'];
                unset($options['page']);
            }
            
            return $this->collection->find([], $options);
        } catch (Exception $e) {
            error_log("Read error in {$this->collectionName}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update a document
     * 
     * @param string $id Document ID
     * @param array $data Update data
     * @return array Update result
     */
    public function update($id, $data) {
        try {
            // Add updated timestamp
            $data['updatedAt'] = new MongoDB\BSON\UTCDateTime();
            
            return $this->collection->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($id)],
                ['$set' => $data]
            );
        } catch (Exception $e) {
            error_log("Update error in {$this->collectionName}: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Delete a document
     * 
     * @param string $id Document ID
     * @return array Delete result
     */
    public function delete($id) {
        try {
            return $this->collection->deleteOne([
                '_id' => new MongoDB\BSON\ObjectId($id)
            ]);
        } catch (Exception $e) {
            error_log("Delete error in {$this->collectionName}: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Find documents by criteria
     * 
     * @param array $filter Query filter
     * @param array $options Query options
     * @return array Documents
     */
    public function find($filter = [], $options = []) {
        try {
            $options = array_merge($this->defaultOptions, $options);
            return $this->collection->find($filter, $options);
        } catch (Exception $e) {
            error_log("Find error in {$this->collectionName}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Find a single document
     * 
     * @param array $filter Query filter
     * @return array|null Document or null
     */
    public function findOne($filter) {
        try {
            return $this->collection->findOne($filter);
        } catch (Exception $e) {
            error_log("FindOne error in {$this->collectionName}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get current user's document
     * 
     * @return array|null User's document or null
     */
    public function me() {
        $userId = $this->getUserIdFromToken();
        if (!$userId) return null;

        return $this->collection->findOne([
            'userId' => new MongoDB\BSON\ObjectId($userId)
        ]);
    }

    /**
     * Run aggregation pipeline
     * 
     * @param array $pipeline Aggregation pipeline
     * @return array Results
     */
    public function aggregate($pipeline) {
        try {
            return $this->collection->aggregate($pipeline);
        } catch (Exception $e) {
            error_log("Aggregate error in {$this->collectionName}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Count documents matching filter
     * 
     * @param array $filter Query filter
     * @return int Count
     */
    public function count($filter = []) {
        try {
            return $this->collection->count($filter);
        } catch (Exception $e) {
            error_log("Count error in {$this->collectionName}: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Extract user ID from JWT token
     * 
     * @return string|null User ID or null
     */
    protected function getUserIdFromToken() {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';

        if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return null;
        }

        $token = $matches[1];
        try {
            $decoded = JWT::decode($token, new Key(JWT_SECRET, 'HS256'));
            return $decoded->sub;
        } catch (Exception $e) {
            error_log("JWT decode error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Create an index
     * 
     * @param array $keys Index keys
     * @param array $options Index options
     * @return array Result
     */
    public function createIndex($keys, $options = []) {
        try {
            return $this->collection->createIndex($keys, $options);
        } catch (Exception $e) {
            error_log("CreateIndex error in {$this->collectionName}: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}

