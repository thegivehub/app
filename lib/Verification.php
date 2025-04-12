<?php

require_once __DIR__ . '/Collection.php';

/**
 * Verification Collection
 * Handles verification-related database operations
 */
class Verification extends Collection {
    protected $collectionName = 'verifications';

    /**
     * Count documents matching filter
     * 
     * @param array $filter Query filter
     * @return int Count
     */
    public function count($filter = []) {
        return parent::count($filter);
    }

    /**
     * Get list of verifications with optional filtering
     */
    public function list($params = null) {
        $query = [];
        $options = [
            'sort' => ['timestamp' => -1],
            'limit' => isset($params['limit']) ? (int)$params['limit'] : 10,
            'skip' => isset($params['offset']) ? (int)$params['offset'] : 0
        ];

        // Add status filter if provided
        if (isset($params['status']) && $params['status'] !== 'all') {
            $query['status'] = strtoupper($params['status']);
        }

        // Get total count for pagination
        $total = $this->count($query);

        // Get verifications
        $verifications = $this->find($query, $options);

        // Add face comparison results if available
        foreach ($verifications as &$verification) {
            if (isset($verification['verificationResult'])) {
                $verification['faceComparison'] = [
                    'similarity' => isset($verification['verificationResult']['similarity']) 
                        ? round($verification['verificationResult']['similarity'] * 100, 1) 
                        : null,
                    'confidence' => isset($verification['verificationResult']['confidence']) 
                        ? round($verification['verificationResult']['confidence'] * 100, 1) 
                        : null,
                    'liveness' => isset($verification['verificationResult']['liveness']) 
                        ? round($verification['verificationResult']['liveness'] * 100, 1) 
                        : null
                ];
            }
        }

        return [
            'verifications' => $verifications,
            'total' => $total
        ];
    }

    /**
     * Get detailed verification information
     */
    public function details($id) {
        $verification = $this->read($id);
        if (!$verification) {
            throw new Exception('Verification not found');
        }

        // Add face comparison results if available
        if (isset($verification['verificationResult'])) {
            $verification['faceComparison'] = [
                'similarity' => isset($verification['verificationResult']['similarity']) 
                    ? round($verification['verificationResult']['similarity'] * 100, 1) 
                    : null,
                'confidence' => isset($verification['verificationResult']['confidence']) 
                    ? round($verification['verificationResult']['confidence'] * 100, 1) 
                    : null,
                'liveness' => isset($verification['verificationResult']['liveness']) 
                    ? round($verification['verificationResult']['liveness'] * 100, 1) 
                    : null
            ];
        }

        return $verification;
    }

    /**
     * Review a verification
     */
    public function review($id, $data) {
        if (!isset($data['action']) || !in_array($data['action'], ['APPROVED', 'REJECTED'])) {
            throw new Exception('Invalid action');
        }

        // Get current user ID from session
        $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
        if (!$userId) {
            throw new Exception('Not authenticated');
        }

        // Update verification status
        $update = [
            'status' => $data['action'],
            'reviewedAt' => new MongoDB\BSON\UTCDateTime(),
            'reviewedBy' => $userId,
            'reviewNotes' => isset($data['notes']) ? $data['notes'] : null
        ];

        $result = $this->update($id, ['$set' => $update]);
        if (!$result) {
            throw new Exception('Failed to update verification');
        }

        // Add audit log
        $auditLog = [
            'action' => 'VERIFICATION_REVIEW',
            'verificationId' => new MongoDB\BSON\ObjectId($id),
            'adminId' => $userId,
            'status' => $data['action'],
            'notes' => isset($data['notes']) ? $data['notes'] : null,
            'timestamp' => new MongoDB\BSON\UTCDateTime()
        ];

        $db = $this->getDb();
        $db->audit_logs->insertOne($auditLog);

        return ['success' => true];
    }

    /**
     * Get verification statistics
     * 
     * @return array Statistics by status
     */
    public function stats() {
        try {
            $pipeline = [
                [
                    '$group' => [
                        '_id' => '$status',
                        'count' => ['$sum' => 1]
                    ]
                ]
            ];

            $results = $this->aggregate($pipeline);
            
            // Initialize default stats
            $stats = [
                'pending' => 0,
                'approved' => 0,
                'rejected' => 0
            ];

            // Update stats with actual counts
            foreach ($results as $result) {
                $status = strtolower($result['_id']);
                if (isset($stats[$status])) {
                    $stats[$status] = (int)$result['count'];
                }
            }

            return $stats;
        } catch (Exception $e) {
            error_log("Error getting verification stats: " . $e->getMessage());
            return [
                'pending' => 0,
                'approved' => 0,
                'rejected' => 0
            ];
        }
    }
} 
