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
        $result = $this->collection->insertOne($document);
        return [
            'id' => (string)$result->getInsertedId(),
            'success' => $result->isAcknowledged()
        ];
    }

    public function findOne($filter = []) {
        if (isset($filter['_id']) && is_string($filter['_id'])) {
            $filter['_id'] = new MongoDB\BSON\ObjectId($filter['_id']);
        }
        $document = $this->collection->findOne($filter);
        return $this->convertId($document);
    }

    public function find($filter = [], $options = []) {
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
    }

    public function updateOne($filter, $update) {
        if (isset($filter['_id']) && is_string($filter['_id'])) {
            $filter['_id'] = new MongoDB\BSON\ObjectId($filter['_id']);
        }
        
        $result = $this->collection->updateOne($filter, ['$set' => $update]);
        return [
            'matched' => $result->getMatchedCount(),
            'modified' => $result->getModifiedCount(),
            'success' => $result->isAcknowledged()
        ];
    }

    public function deleteOne($filter) {
        if (isset($filter['_id']) && is_string($filter['_id'])) {
            $filter['_id'] = new MongoDB\BSON\ObjectId($filter['_id']);
        }
        
        $result = $this->collection->deleteOne($filter);
        return [
            'deleted' => $result->getDeletedCount(),
            'success' => $result->isAcknowledged()
        ];
    }

    public function aggregate($pipeline) {
        $cursor = $this->collection->aggregate($pipeline);
        $results = [];
        
        foreach ($cursor as $document) {
            $results[] = $this->convertId($document);
        }
        
        return $results;
    }

    public function count($filter = []) {
        return $this->collection->countDocuments($filter);
    }
}