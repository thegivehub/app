<?php
/**
 * AdminKycController
 * Handles KYC administration functionality for identity verification
 */
class AdminKycController {
    private $db;
    private $adminAuth;
    private $documents;
    
    /**
     * Initialize controller
     */
    public function __construct() {
        $this->db = new Database();
        $this->adminAuth = new AdminAuth();
        $this->documents = new Documents();
    }
    
    /**
     * Handle incoming API requests based on action
     */
    public function handleRequest() {
        // Get the request path parts
        $pathInfo = $_SERVER['PATH_INFO'] ?? '';
        $pathParts = array_values(array_filter(explode('/', $pathInfo)));
        
        // Extract the action (should be the 3rd part: /admin/kyc/ACTION)
        $action = isset($pathParts[2]) ? $pathParts[2] : '';
        
        // Get request method
        $method = $_SERVER['REQUEST_METHOD'];
        
        switch ($action) {
            case 'report':
                if ($method === 'GET') {
                    $this->getVerificationReport();
                } else {
                    $this->sendError(405, 'Method not allowed');
                }
                break;
                
            case 'override':
                if ($method === 'POST') {
                    $this->overrideVerification();
                } else {
                    $this->sendError(405, 'Method not allowed');
                }
                break;
                
            case 'details':
                if ($method === 'GET') {
                    $this->getDocumentDetails();
                } else {
                    $this->sendError(405, 'Method not allowed');
                }
                break;
                
            default:
                $this->sendError(404, 'KYC admin endpoint not found');
                break;
        }
    }
    
    /**
     * Get verification report with optional filters
     */
    private function getVerificationReport() {
        // Verify admin authentication
        if (!$this->adminAuth->verifyAdminToken()) {
            $this->sendError(401, 'Administrator authentication required');
        }
        
        // Get filter parameters
        $status = $_GET['status'] ?? '';
        $startDate = $_GET['startDate'] ?? null;
        $endDate = $_GET['endDate'] ?? null;
        
        // Get report from Documents collection
        $report = $this->documents->getVerificationReport($status, $startDate, $endDate);
        
        // Return the report
        $this->sendResponse(200, $report);
    }
    
    /**
     * Override verification status
     */
    private function overrideVerification() {
        // Verify admin authentication
        if (!$this->adminAuth->verifyAdminToken()) {
            $this->sendError(401, 'Administrator authentication required');
        }
        
        // Parse request body
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validate required fields
        if (!isset($data['userId']) || !isset($data['status']) || !isset($data['reason'])) {
            $this->sendError(400, 'Missing required fields: userId, status, and reason are required');
        }
        
        // Process override
        $result = $this->documents->adminOverrideVerification(
            $data['userId'],
            $data['status'],
            $data['reason']
        );
        
        // Return result
        $this->sendResponse(200, $result);
    }
    
    /**
     * Get detailed information about a verification document
     */
    private function getDocumentDetails() {
        // Verify admin authentication
        if (!$this->adminAuth->verifyAdminToken()) {
            $this->sendError(401, 'Administrator authentication required');
        }
        
        // Get document ID from query string
        $documentId = $_GET['id'] ?? '';
        
        if (empty($documentId)) {
            $this->sendError(400, 'Document ID is required');
        }
        
        // Get document details
        $details = $this->documents->getDocumentDetailsForAdmin($documentId);
        
        // Return details
        $this->sendResponse(200, $details);
    }
    
    /**
     * Send error response
     * @param int $code HTTP status code
     * @param string $message Error message
     */
    private function sendError($code, $message) {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => $message
        ]);
        exit;
    }
    
    /**
     * Send success response
     * @param int $code HTTP status code
     * @param mixed $data Response data
     */
    private function sendResponse($code, $data) {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
} 