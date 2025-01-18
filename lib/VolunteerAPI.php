<?php
require_once 'lib/Volunteer.php';

class VolunteerAPI {
    private $volunteer;
    private $auth;

    public function __construct() {
        $this->volunteer = new Volunteer();
        $this->auth = new Auth();
    }

    public function handleRequest($action = null) {
        // Verify authentication for all requests
        $token = apache_request_headers()['Authorization'] ?? null;
        if (!$token) {
            http_response_code(401);
            return ['error' => 'No authentication token provided'];
        }

        $userId = $this->auth->validateToken($token);
        if (!$userId) {
            http_response_code(401);
            return ['error' => 'Invalid authentication token'];
        }

        $method = $_SERVER['REQUEST_METHOD'];

        try {
            switch ($method) {
                case 'GET':
                    return $this->handleGet($action, $userId);
                case 'POST':
                    return $this->handlePost($action, $userId);
                case 'PUT':
                    return $this->handlePut($action, $userId);
                case 'DELETE':
                    return $this->handleDelete($action, $userId);
                default:
                    http_response_code(405);
                    return ['error' => 'Method not allowed'];
            }
        } catch (Exception $e) {
            http_response_code(500);
            return ['error' => $e->getMessage()];
        }
    }

    private function handleGet($action, $userId) {
        switch ($action) {
            case 'me':
            case 'profile':
                return $this->volunteer->getProfile($userId);

            case 'opportunities':
                $filters = [
                    'skills' => $_GET['skills'] ?? null,
                    'location' => $_GET['location'] ?? null,
                    'status' => $_GET['status'] ?? null
                ];
                return $this->volunteer->getOpportunities($filters);

            case 'applications':
                $status = $_GET['status'] ?? null;
                return $this->volunteer->getApplications($userId, $status);

            case 'hours':
                $timeframe = $_GET['timeframe'] ?? 'all';
                return $this->volunteer->getHours($userId, $timeframe);

            case 'stats':
                return $this->volunteer->getStats($userId);

            default:
                http_response_code(404);
                return ['error' => 'Endpoint not found'];
        }
    }

    private function handlePost($action, $userId) {
        $data = json_decode(file_get_contents('php://input'), true);
        
        switch ($action) {
            case 'applications':
                if (!isset($data['opportunityId'])) {
                    http_response_code(400);
                    return ['error' => 'opportunityId is required'];
                }
                $data['userId'] = $userId;
                return $this->volunteer->createApplication($data);

            case 'hours':
                if (!isset($data['hours']) || !isset($data['opportunityId'])) {
                    http_response_code(400);
                    return ['error' => 'hours and opportunityId are required'];
                }
                $data['userId'] = $userId;
                return $this->volunteer->logHours($data);

            case 'certifications':
                if (!isset($_FILES['file']) || !isset($_POST['data'])) {
                    http_response_code(400);
                    return ['error' => 'Both file and certification data are required'];
                }
                $certData = json_decode($_POST['data'], true);
                return $this->volunteer->uploadCertification($userId, $certData, $_FILES['file']);

            default:
                http_response_code(404);
                return ['error' => 'Endpoint not found'];
        }
    }

    private function handlePut($action, $userId) {
        $data = json_decode(file_get_contents('php://input'), true);
        
        switch ($action) {
            case 'profile':
                return $this->volunteer->updateProfile($userId, $data);

            case 'schedule':
                return $this->volunteer->updateSchedule($userId, $data);

            case 'applications/cancel':
                if (!isset($data['applicationId'])) {
                    http_response_code(400);
                    return ['error' => 'applicationId is required'];
                }
                return $this->volunteer->cancelApplication($data['applicationId'], $data['reason'] ?? '');

            default:
                http_response_code(404);
                return ['error' => 'Endpoint not found'];
        }
    }

    private function handleDelete($action, $userId) {
        switch ($action) {
            case 'applications':
                $applicationId = $_GET['id'] ?? null;
                if (!$applicationId) {
                    http_response_code(400);
                    return ['error' => 'Application ID is required'];
                }
                return $this->volunteer->deleteApplication($applicationId);

            default:
                http_response_code(404);
                return ['error' => 'Endpoint not found'];
        }
    }

    private function validateRequiredFields($data, $required) {
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                http_response_code(400);
                throw new Exception("Missing required field: {$field}");
            }
        }
    }
}
