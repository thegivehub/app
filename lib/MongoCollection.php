<?php
class MongoCollection {
    private $collection;

    public function __construct($collection) {
        $this->collection = $collection;
    }

    private function convertId($document) {
        if (!$document) return null;
        
        // Convert to array if it's a document
        if ($document instanceof MongoDB\Model\BSONDocument) {
            $document = $document->getArrayCopy();
        }
        
        // Convert _id to string
        if (isset($document['_id']) && $document['_id'] instanceof MongoDB\BSON\ObjectId) {
            $document['_id'] = (string)$document['_id'];
        }
        
        // Convert any nested documents or arrays
        foreach ($document as $key => $value) {
            if ($value instanceof MongoDB\Model\BSONDocument || $value instanceof MongoDB\Model\BSONArray) {
                $document[$key] = $this->convertId($value->getArrayCopy());
            } elseif (is_array($value)) {
                $document[$key] = $this->convertId($value);
            } elseif ($value instanceof MongoDB\BSON\ObjectId) {
                $document[$key] = (string)$value;
            } elseif ($value instanceof MongoDB\BSON\UTCDateTime) {
                $document[$key] = $value->toDateTime()->format('c');
            } elseif ($value instanceof MongoDB\BSON\Decimal128) {
                $document[$key] = (string)$value;
            }
        }
        
        return $document;
    }

    public function insertOne($document) {
        try {
            $result = $this->collection->insertOne($document);
            return [
                'success' => true,
                'id' => (string)$result->getInsertedId(),
                'acknowledged' => $result->isAcknowledged()
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function insertMany($documents) {
        try {
            $result = $this->collection->insertMany($documents);
            return [
                'success' => true,
                'insertedCount' => $result->getInsertedCount(),
                'insertedIds' => array_map('strval', $result->getInsertedIds()),
                'acknowledged' => $result->isAcknowledged()
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function findOne($filter = [], $options = []) {
        try {
            if (isset($filter['_id']) && is_string($filter['_id'])) {
                $filter['_id'] = new MongoDB\BSON\ObjectId($filter['_id']);
            }
            $document = $this->collection->findOne($filter, $options);
            return $this->convertId($document);
        } catch (Exception $e) {
            error_log("MongoDB findOne error: " . $e->getMessage());
            return null;
        }
    }

    public function find($filter = [], $options = []) {
        try {
            if (isset($filter['_id']) && is_string($filter['_id'])) {
                $filter['_id'] = new MongoDB\BSON\ObjectId($filter['_id']);
            }
            
            // Handle pagination
            if (isset($options['page']) && isset($options['limit'])) {
                $options['skip'] = ($options['page'] - 1) * $options['limit'];
                unset($options['page']);
            }
            
            $cursor = $this->collection->find($filter, $options);
            $documents = [];
            
            foreach ($cursor as $document) {
                $documents[] = $this->convertId($document);
            }
            
            return $documents;
        } catch (Exception $e) {
            error_log("MongoDB find error: " . $e->getMessage());
            return [];
        }
    }

    public function updateOne($filter, $update, $options = []) {
        try {
            if (isset($filter['_id']) && is_string($filter['_id'])) {
                $filter['_id'] = new MongoDB\BSON\ObjectId($filter['_id']);
            }
            
            // Check if the update already contains operators
            $hasOperators = false;
            foreach ($update as $key => $value) {
                if (strpos($key, '$') === 0) {
                    $hasOperators = true;
                    break;
                }
            }
            
            // If no operators are present, wrap in $set
            if (!$hasOperators) {
                $update = ['$set' => $update];
            }
            
            $result = $this->collection->updateOne($filter, $update, $options);
            return [
                'success' => true,
                'matched' => $result->getMatchedCount(),
                'modified' => $result->getModifiedCount(),
                'upserted' => $result->getUpsertedId() ? (string)$result->getUpsertedId() : null,
                'acknowledged' => $result->isAcknowledged()
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function updateMany($filter, $update, $options = []) {
        try {
            if (isset($filter['_id']) && is_string($filter['_id'])) {
                $filter['_id'] = new MongoDB\BSON\ObjectId($filter['_id']);
            }

            // Handle update operators
            if (!isset($update['$set']) && !isset($update['$unset']) && !isset($update['$inc'])) {
                $update = ['$set' => $update];
            }

            $result = $this->collection->updateMany($filter, $update, $options);
            return [
                'success' => true,
                'matched' => $result->getMatchedCount(),
                'modified' => $result->getModifiedCount(),
                'acknowledged' => $result->isAcknowledged()
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function findOneAndUpdate($filter, $update, $options = []) {
        try {
            if (isset($filter['_id']) && is_string($filter['_id'])) {
                $filter['_id'] = new MongoDB\BSON\ObjectId($filter['_id']);
            }
            
            // Check if update already contains operators
            $hasOperators = false;
            foreach ($update as $key => $value) {
                if (strpos($key, '$') === 0) {
                    $hasOperators = true;
                    break;
                }
            }
            
            // If no operators are present, wrap in $set
            if (!$hasOperators) {
                $update = ['$set' => $update];
            }

            // Default options
            $defaultOptions = [
                'returnDocument' => MongoDB\Operation\FindOneAndUpdate::RETURN_DOCUMENT_AFTER,
                'upsert' => false
            ];

            $options = array_merge($defaultOptions, $options);
            
            $result = $this->collection->findOneAndUpdate($filter, $update, $options);
            return $this->convertId($result);
        } catch (Exception $e) {
            error_log("MongoDB findOneAndUpdate error: " . $e->getMessage());
            return null;
        }
    }

    public function deleteOne($filter) {
        try {
            if (isset($filter['_id']) && is_string($filter['_id'])) {
                $filter['_id'] = new MongoDB\BSON\ObjectId($filter['_id']);
            }
            
            $result = $this->collection->deleteOne($filter);
            return [
                'success' => true,
                'deleted' => $result->getDeletedCount(),
                'acknowledged' => $result->isAcknowledged()
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function deleteMany($filter) {
        try {
            if (isset($filter['_id']) && is_string($filter['_id'])) {
                $filter['_id'] = new MongoDB\BSON\ObjectId($filter['_id']);
            }

            $result = $this->collection->deleteMany($filter);
            return [
                'success' => true,
                'deleted' => $result->getDeletedCount(),
                'acknowledged' => $result->isAcknowledged()
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function aggregate($pipeline) {
        try {
            $cursor = $this->collection->aggregate($pipeline);
            $results = [];
            
            foreach ($cursor as $document) {
                $results[] = $this->convertId($document);
            }
            
            return $results;
        } catch (Exception $e) {
            error_log("MongoDB aggregate error: " . $e->getMessage());
            return [];
        }
    }

    public function count($filter = []) {
        try {
            return $this->collection->countDocuments($filter);
        } catch (Exception $e) {
            error_log("MongoDB count error: " . $e->getMessage());
            return 0;
        }
    }

    public function createIndex($keys, $options = []) {
        try {
            $result = $this->collection->createIndex($keys, $options);
            return [
                'success' => true,
                'indexName' => $result
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function createIndexes($indexes) {
        try {
            $result = $this->collection->createIndexes($indexes);
            return [
                'success' => true,
                'createdIndexes' => $result
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function listIndexes() {
        try {
            $indexes = [];
            $cursor = $this->collection->listIndexes();
            foreach ($cursor as $index) {
                $indexes[] = $this->convertId($index);
            }
            return [
                'success' => true,
                'indexes' => $indexes
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function dropIndex($indexName) {
        try {
            $result = $this->collection->dropIndex($indexName);
            return [
                'success' => true,
                'result' => $result
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function distinct($fieldName, $filter = []) {
        try {
            $result = $this->collection->distinct($fieldName, $filter);
            return $result;
        } catch (Exception $e) {
            error_log("MongoDB distinct error: " . $e->getMessage());
            return [];
        }
    }
}
