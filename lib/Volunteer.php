<?php
require_once 'Model.php';

class Volunteer extends Model {
    public function __construct() {
        parent::__construct();
        $this->collection = $this->db->getCollection('volunteers');
    }

    public function getProfile($userId) {
        return $this->collection->findOne([
            'userId' => new MongoDB\BSON\ObjectId($userId)
        ]);
    }

    public function updateProfile($userId, $data) {
        return $this->collection->updateOne(
            ['userId' => new MongoDB\BSON\ObjectId($userId)],
            ['$set' => $data],
            ['upsert' => true]
        );
    }

    public function getOpportunities($filters = []) {
        $query = [];
        
        if (isset($filters['skills'])) {
            $query['requiredSkills'] = ['$in' => $filters['skills']];
        }
        
        if (isset($filters['location'])) {
            $query['location'] = $filters['location'];
        }
        
        if (isset($filters['status'])) {
            $query['status'] = $filters['status'];
        }

        return $this->db->getCollection('opportunities')->find($query);
    }

    public function createApplication($data) {
        $data['created'] = new MongoDB\BSON\UTCDateTime();
        $data['status'] = 'pending';
        return $this->db->getCollection('volunteer_applications')->insertOne($data);
    }

    public function getApplications($userId, $status = null) {
        $query = ['userId' => new MongoDB\BSON\ObjectId($userId)];
        if ($status) {
            $query['status'] = $status;
        }
        return $this->db->getCollection('volunteer_applications')->find($query);
    }

    public function updateSchedule($userId, $scheduleData) {
        return $this->collection->updateOne(
            ['userId' => new MongoDB\BSON\ObjectId($userId)],
            ['$set' => ['schedule' => $scheduleData]],
            ['upsert' => true]
        );
    }

    public function logHours($data) {
        $data['created'] = new MongoDB\BSON\UTCDateTime();
        return $this->db->getCollection('volunteer_hours')->insertOne($data);
    }

    public function getHours($userId, $timeframe = 'all') {
        $query = ['userId' => new MongoDB\BSON\ObjectId($userId)];
        
        if ($timeframe !== 'all') {
            $date = new MongoDB\BSON\UTCDateTime();
            switch($timeframe) {
                case 'week':
                    $date->modify('-1 week');
                    break;
                case 'month':
                    $date->modify('-1 month');
                    break;
                case 'year':
                    $date->modify('-1 year');
                    break;
            }
            $query['created'] = ['$gte' => $date];
        }

        return $this->db->getCollection('volunteer_hours')->find($query);
    }

    public function uploadCertification($userId, $certData, $fileData) {
        // Handle file upload
        $filename = uniqid() . '_' . $fileData['name'];
        $uploadDir = __DIR__ . '/../uploads/certifications/';
        
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        if (move_uploaded_file($fileData['tmp_name'], $uploadDir . $filename)) {
            $certData['file'] = $filename;
            $certData['uploaded'] = new MongoDB\BSON\UTCDateTime();
            $certData['userId'] = new MongoDB\BSON\ObjectId($userId);
            
            return $this->db->getCollection('volunteer_certifications')->insertOne($certData);
        }
        
        throw new Exception('Failed to upload certification file');
    }

    public function getStats($userId) {
        $hours = $this->db->getCollection('volunteer_hours')->aggregate([
            ['$match' => ['userId' => new MongoDB\BSON\ObjectId($userId)]],
            ['$group' => ['_id' => null, 'total' => ['$sum' => '$hours']]]
        ])->toArray();

        $projects = $this->db->getCollection('volunteer_applications')->aggregate([
            ['$match' => [
                'userId' => new MongoDB\BSON\ObjectId($userId),
                'status' => 'completed'
            ]],
            ['$group' => ['_id' => null, 'count' => ['$sum' => 1]]]
        ])->toArray();

        $activeApplications = $this->db->getCollection('volunteer_applications')->count([
            'userId' => new MongoDB\BSON\ObjectId($userId),
            'status' => 'pending'
        ]);

        return [
            'hoursContributed' => $hours[0]['total'] ?? 0,
            'projectsCompleted' => $projects[0]['count'] ?? 0,
            'activeApplications' => $activeApplications
        ];
    }
}
