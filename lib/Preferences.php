<?php
class Preferences extends Collection {
    protected $auth;
    protected $collection;

    public function __construct() {
        parent::__construct();
        $this->auth = new Auth();
        
        if (!$this->collection) {
            $db = new Database();
            $this->collection = $db->getCollection('preferences');
        }
    }

    public function me() {
        try {
            $headers = getallheaders();
            $authHeader = $headers['Authorization'] ?? '';
            
            if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                return ['error' => 'Authorization required'];
            }
            
            $token = $matches[1];
            $decoded = $this->auth->decodeToken($token);
            if (!$decoded) {
                return ['error' => 'Invalid token'];
            }

            $userId = $decoded->sub;
            
            // Try to find existing preferences
            $preferences = $this->collection->findOne([
                'userId' => new MongoDB\BSON\ObjectId($userId)
            ]);

            // If no preferences exist, create default ones
            if (!$preferences) {
                $defaultPreferences = [
                    'userId' => new MongoDB\BSON\ObjectId($userId),
                    'emailNotifications' => [
                        'campaignUpdates' => true,
                        'newDonations' => true,
                        'milestones' => true,
                        'marketing' => false
                    ],
                    'language' => 'en',
                    'currency' => 'USD',
                    'created' => new MongoDB\BSON\UTCDateTime(),
                    'updated' => new MongoDB\BSON\UTCDateTime()
                ];

                $result = $this->collection->insertOne($defaultPreferences);
                if (!$result['success']) {
                    return ['error' => 'Failed to create preferences'];
                }

                return $defaultPreferences;
            }

            return $preferences;
            
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
