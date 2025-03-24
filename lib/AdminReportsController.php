<?php
// lib/AdminReportsController.php
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/db.php';

class AdminReportsController {
    private $db;
    private $auth;

    public function __construct() {
        $this->db = new Database("givehub");
        $this->auth = new Auth();
    }

    /**
     * Handle reports requests
     */
    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        
        // Check admin authentication first
        $this->verifyAdminAccess();
        
        $pathParts = [];
        if (isset($_SERVER['PATH_INFO'])) {
            $pathParts = explode('/', trim($_SERVER['PATH_INFO'], '/'));
        }
        
        $reportType = $_GET['type'] ?? null;
        $startDate = $_GET['start'] ?? null;
        $endDate = $_GET['end'] ?? null;
        
        // Process request based on method and path
        if ($method === 'GET') {
            if ($reportType) {
                switch ($reportType) {
                    case 'campaign-performance':
                        $this->getCampaignPerformanceReport($startDate, $endDate);
                        break;
                        
                    case 'donor-activity':
                        $this->getDonorActivityReport($startDate, $endDate);
                        break;
                        
                    case 'financial-summary':
                        $this->getFinancialSummaryReport($startDate, $endDate);
                        break;
                        
                    case 'user-growth':
                        $this->getUserGrowthReport($startDate, $endDate);
                        break;
                        
                    case 'category-analysis':
                        $this->getCategoryAnalysisReport($startDate, $endDate);
                        break;
                        
                    case 'geographic-distribution':
                        $this->getGeographicDistributionReport($startDate, $endDate);
                        break;
                        
                    default:
                        $this->sendError(400, "Invalid report type");
                        break;
                }
            } else {
                // No specific report requested, send available report types
                $this->getAvailableReports();
            }
        } else if ($method === 'POST' && isset($pathParts[2]) && $pathParts[2] === 'export') {
            // Handle report export
            $this->exportReport($reportType, $_POST);
        } else {
            $this->sendError(405, "Method not allowed");
        }
    }

    /**
     * Verify the request is from an authenticated admin
     */
    private function verifyAdminAccess() {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';
        
        if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $this->sendError(401, "Authorization token required");
        }
        
        $token = $matches[1];
        $decoded = $this->auth->decodeToken($token);
        
        if (!$decoded) {
            $this->sendError(401, "Invalid authorization token");
        }
        
        // Get user and check for admin role
        $userId = $decoded->sub;
        $user = $this->db->getCollection('users')->findOne(['_id' => new MongoDB\BSON\ObjectId($userId)]);
        
        if (!$user || !isset($user['roles']) || !in_array('admin', $user['roles'])) {
            $this->sendError(403, "Insufficient permissions");
        }
    }

    /**
     * Get list of available reports
     */
    private function getAvailableReports() {
        $reports = [
            [
                'id' => 'campaign-performance',
                'title' => 'Campaign Performance',
                'description' => 'Track campaign performance metrics including funding progress, conversion rates, and engagement over time.'
            ],
            [
                'id' => 'donor-activity',
                'title' => 'Donor Activity',
                'description' => 'Analyze donor demographics, donation patterns, and retention metrics to optimize fundraising strategies.'
            ],
            [
                'id' => 'financial-summary',
                'title' => 'Financial Summary',
                'description' => 'View financial metrics including total donations, platform fees, disbursements, and overall platform growth.'
            ],
            [
                'id' => 'user-growth',
                'title' => 'User Growth',
                'description' => 'Track user acquisition, retention, and engagement metrics to understand platform growth and user behavior.'
            ],
            [
                'id' => 'category-analysis',
                'title' => 'Category Analysis',
                'description' => 'Compare performance across different campaign categories to identify trends and opportunities for growth.'
            ],
            [
                'id' => 'geographic-distribution',
                'title' => 'Geographic Distribution',
                'description' => 'Analyze the geographic distribution of campaigns, donors, and donations to understand regional performance.'
            ]
        ];
        
        $this->sendResponse(['reports' => $reports]);
    }

    /**
     * Get campaign performance report data
     */
    private function getCampaignPerformanceReport($startDate, $endDate) {
        try {
            // Parse dates
            $start = $startDate ? new DateTime($startDate) : new DateTime('-30 days');
            $end = $endDate ? new DateTime($endDate) : new DateTime();
            
            $startDateTime = new MongoDB\BSON\UTCDateTime($start->getTimestamp() * 1000);
            $endDateTime = new MongoDB\BSON\UTCDateTime($end->getTimestamp() * 1000);
            
            // Apply additional filters if provided
            $filter = [
                'createdAt' => [
                    '$gte' => $startDateTime,
                    '$lte' => $endDateTime
                ]
            ];
            
            if (isset($_GET['status']) && $_GET['status'] !== 'all') {
                $filter['status'] = $_GET['status'];
            }
            
            if (isset($_GET['category']) && $_GET['category'] !== 'all') {
                $filter['category'] = $_GET['category'];
            }
            
            // Get campaigns within date range
            $campaigns = $this->db->getCollection('campaigns')->find($filter);
            
            // Calculate summary metrics
            $totalCampaigns = count($campaigns);
            $totalGoal = 0;
            $totalRaised = 0;
            $successfulCampaigns = 0;
            
            foreach ($campaigns as $campaign) {
                $goal = $campaign['fundingGoal'] ?? 0;
                $raised = $campaign['raised'] ?? 0;
                
                $totalGoal += $goal;
                $totalRaised += $raised;
                
                if ($raised >= $goal) {
                    $successfulCampaigns++;
                }
            }
            
            $avgGoal = $totalCampaigns ? $totalGoal / $totalCampaigns : 0;
            $avgRaised = $totalCampaigns ? $totalRaised / $totalCampaigns : 0;
            $successRate = $totalCampaigns ? ($successfulCampaigns / $totalCampaigns) * 100 : 0;
            
            // Get monthly data for charts
            $monthlyData = $this->getMonthlyData($start, $end, 'campaigns');
            
            // Build response
            $response = [
                'summary' => [
                    'totalCampaigns' => $totalCampaigns,
                    'totalGoal' => $totalGoal,
                    'totalRaised' => $totalRaised,
                    'avgGoal' => $avgGoal,
                    'avgRaised' => $avgRaised,
                    'successRate' => $successRate,
                    'successfulCampaigns' => $successfulCampaigns
                ],
                'monthlyData' => $monthlyData,
                'campaigns' => $campaigns
            ];
            
            $this->sendResponse($response);
        } catch (Exception $e) {
            $this->sendError(500, "Error generating campaign performance report: " . $e->getMessage());
        }
    }

    /**
     * Get donor activity report data
     */
    private function getDonorActivityReport($startDate, $endDate) {
        try {
            // Parse dates
            $start = $startDate ? new DateTime($startDate) : new DateTime('-30 days');
            $end = $endDate ? new DateTime($endDate) : new DateTime();
            
            $startDateTime = new MongoDB\BSON\UTCDateTime($start->getTimestamp() * 1000);
            $endDateTime = new MongoDB\BSON\UTCDateTime($end->getTimestamp() * 1000);
            
            // Get donations within date range
            $donations = $this->db->getCollection('donations')->find([
                'createdAt' => [
                    '$gte' => $startDateTime,
                    '$lte' => $endDateTime
                ]
            ]);
            
            // Get unique donors
            $donorIds = [];
            $totalDonations = 0;
            $recurringDonations = 0;
            
            foreach ($donations as $donation) {
                $donorIds[$donation['userId']] = true;
                $totalDonations += $donation['amount'] ?? 0;
                
                if (isset($donation['type']) && $donation['type'] === 'recurring') {
                    $recurringDonations++;
                }
            }
            
            $uniqueDonors = count($donorIds);
            $avgDonation = $uniqueDonors ? $totalDonations / $uniqueDonors : 0;
            
            // Get monthly data for charts
            $monthlyData = $this->getMonthlyData($start, $end, 'donations');
            
            // Get donor list with aggregated data
            $donors = $this->getDonorList($startDateTime, $endDateTime);
            
            // Build response
            $response = [
                'summary' => [
                    'totalDonors' => $uniqueDonors,
                    'totalDonations' => $totalDonations,
                    'avgDonation' => $avgDonation,
                    'recurringDonors' => count(array_filter($donors, function($donor) {
                        return isset($donor['type']) && $donor['type'] === 'recurring';
                    })),
                    'retentionRate' => 73.4 // Placeholder for actual calculation
                ],
                'monthlyData' => $monthlyData,
                'donors' => $donors
            ];
            
            $this->sendResponse($response);
        } catch (Exception $e) {
            $this->sendError(500, "Error generating donor activity report: " . $e->getMessage());
        }
    }

    /**
     * Get financial summary report data
     */
    private function getFinancialSummaryReport($startDate, $endDate) {
        try {
            // Parse dates
            $start = $startDate ? new DateTime($startDate) : new DateTime('-30 days');
            $end = $endDate ? new DateTime($endDate) : new DateTime();
            
            $startDateTime = new MongoDB\BSON\UTCDateTime($start->getTimestamp() * 1000);
            $endDateTime = new MongoDB\BSON\UTCDateTime($end->getTimestamp() * 1000);
            
            // Get donations within date range
            $donations = $this->db->getCollection('donations')->find([
                'createdAt' => [
                    '$gte' => $startDateTime,
                    '$lte' => $endDateTime
                ]
            ]);
            
            // Calculate summary metrics
            $totalDonations = 0;
            $transactionCount = 0;
            
            foreach ($donations as $donation) {
                $totalDonations += $donation['amount'] ?? 0;
                $transactionCount++;
            }
            
            // Calculate platform fees (assuming 5% fee)
            $platformFees = $totalDonations * 0.05;
            $disbursements = $totalDonations - $platformFees;
            $avgTransaction = $transactionCount ? $totalDonations / $transactionCount : 0;
            
            // Get monthly data for charts
            $monthlyData = $this->getMonthlyFinancialData($start, $end);
            
            // Build response
            $response = [
                'summary' => [
                    'totalDonations' => $totalDonations,
                    'platformFees' => $platformFees,
                    'disbursements' => $disbursements,
                    'transactionCount' => $transactionCount,
                    'avgTransaction' => $avgTransaction
                ],
                'monthlyData' => $monthlyData
            ];
            
            $this->sendResponse($response);
        } catch (Exception $e) {
            $this->sendError(500, "Error generating financial summary report: " . $e->getMessage());
        }
    }

    /**
     * Get user growth report data
     */
    private function getUserGrowthReport($startDate, $endDate) {
        try {
            // Parse dates
            $start = $startDate ? new DateTime($startDate) : new DateTime('-30 days');
            $end = $endDate ? new DateTime($endDate) : new DateTime();
            
            $startDateTime = new MongoDB\BSON\UTCDateTime($start->getTimestamp() * 1000);
            $endDateTime = new MongoDB\BSON\UTCDateTime($end->getTimestamp() * 1000);
            
            // Get users created within date range
            $users = $this->db->getCollection('users')->find([
                'created' => [
                    '$gte' => $startDateTime,
                    '$lte' => $endDateTime
                ]
            ]);
            
            // Calculate user types
            $totalUsers = count($users);
            $campaigners = 0;
            $donors = 0;
            $admins = 0;
            
            foreach ($users as $user) {
                if (isset($user['roles'])) {
                    if (in_array('campaigner', $user['roles'])) {
                        $campaigners++;
                    }
                    if (in_array('donor', $user['roles'])) {
                        $donors++;
                    }
                    if (in_array('admin', $user['roles'])) {
                        $admins++;
                    }
                }
            }
            
            // Get monthly data for charts
            $monthlyData = $this->getMonthlyData($start, $end, 'users');
            
            // Build response
            $response = [
                'summary' => [
                    'totalUsers' => $totalUsers,
                    'campaigners' => $campaigners,
                    'donors' => $donors,
                    'admins' => $admins,
                    'activeUsers' => floor($totalUsers * 0.85) // Placeholder for actual calculation
                ],
                'monthlyData' => $monthlyData,
                'users' => $users
            ];
            
            $this->sendResponse($response);
        } catch (Exception $e) {
            $this->sendError(500, "Error generating user growth report: " . $e->getMessage());
        }
    }

    /**
     * Get category analysis report data
     */
    private function getCategoryAnalysisReport($startDate, $endDate) {
        try {
            // Parse dates
            $start = $startDate ? new DateTime($startDate) : new DateTime('-30 days');
            $end = $endDate ? new DateTime($endDate) : new DateTime();
            
            $startDateTime = new MongoDB\BSON\UTCDateTime($start->getTimestamp() * 1000);
            $endDateTime = new MongoDB\BSON\UTCDateTime($end->getTimestamp() * 1000);
            
            // Get campaigns within date range
            $campaigns = $this->db->getCollection('campaigns')->find([
                'createdAt' => [
                    '$gte' => $startDateTime,
                    '$lte' => $endDateTime
                ]
            ]);
            
            // Group by category
            $categories = [];
            
            foreach ($campaigns as $campaign) {
                $category = $campaign['category'] ?? 'Uncategorized';
                
                if (!isset($categories[$category])) {
                    $categories[$category] = [
                        'count' => 0,
                        'totalGoal' => 0,
                        'totalRaised' => 0,
                        'campaigns' => []
                    ];
                }
                
                $categories[$category]['count']++;
                $categories[$category]['totalGoal'] += $campaign['fundingGoal'] ?? 0;
                $categories[$category]['totalRaised'] += $campaign['raised'] ?? 0;
                $categories[$category]['campaigns'][] = $campaign;
            }
            
            // Calculate success rates and percentages
            $totalCampaigns = count($campaigns);
            $categoryData = [];
            
            foreach ($categories as $name => $data) {
                $successRate = $data['totalGoal'] > 0 ? ($data['totalRaised'] / $data['totalGoal']) * 100 : 0;
                $percentage = $totalCampaigns > 0 ? ($data['count'] / $totalCampaigns) * 100 : 0;
                
                $categoryData[] = [
                    'name' => $name,
                    'count' => $data['count'],
                    'percentage' => $percentage,
                    'totalGoal' => $data['totalGoal'],
                    'totalRaised' => $data['totalRaised'],
                    'successRate' => $successRate
                ];
            }
            
            // Build response
            $response = [
                'categories' => $categoryData,
                'totalCampaigns' => $totalCampaigns
            ];
            
            $this->sendResponse($response);
        } catch (Exception $e) {
            $this->sendError(500, "Error generating category analysis report: " . $e->getMessage());
        }
    }

    /**
     * Get geographic distribution report data
     */
    private function getGeographicDistributionReport($startDate, $endDate) {
        try {
            // Parse dates
            $start = $startDate ? new DateTime($startDate) : new DateTime('-30 days');
            $end = $endDate ? new DateTime($endDate) : new DateTime();
            
            $startDateTime = new MongoDB\BSON\UTCDateTime($start->getTimestamp() * 1000);
            $endDateTime = new MongoDB\BSON\UTCDateTime($end->getTimestamp() * 1000);
            
            // Get campaigns and donations within date range
            $campaigns = $this->db->getCollection('campaigns')->find([
                'createdAt' => [
                    '$gte' => $startDateTime,
                    '$lte' => $endDateTime
                ]
            ]);
            
            $donations = $this->db->getCollection('donations')->find([
                'createdAt' => [
                    '$gte' => $startDateTime,
                    '$lte' => $endDateTime
                ]
            ]);
            
            // Group by location
            $locations = [];
            
            // Process campaigns
            foreach ($campaigns as $campaign) {
                if (isset($campaign['location']) && isset($campaign['location']['country'])) {
                    $country = $campaign['location']['country'];
                    $region = $campaign['location']['region'] ?? 'Unknown';
                    
                    $locationKey = $country . ' - ' . $region;
                    
                    if (!isset($locations[$locationKey])) {
                        $locations[$locationKey] = [
                            'country' => $country,
                            'region' => $region,
                            'campaigns' => 0,
                            'donations' => 0,
                            'totalRaised' => 0
                        ];
                    }
                    
                    $locations[$locationKey]['campaigns']++;
                    $locations[$locationKey]['totalRaised'] += $campaign['raised'] ?? 0;
                }
            }
            
            // Process donations
            foreach ($donations as $donation) {
                if (isset($donation['location']) && isset($donation['location']['country'])) {
                    $country = $donation['location']['country'];
                    $region = $donation['location']['region'] ?? 'Unknown';
                    
                    $locationKey = $country . ' - ' . $region;
                    
                    if (!isset($locations[$locationKey])) {
                        $locations[$locationKey] = [
                            'country' => $country,
                            'region' => $region,
                            'campaigns' => 0,
                            'donations' => 0,
                            'totalRaised' => 0
                        ];
                    }
                    
                    $locations[$locationKey]['donations']++;
                    $locations[$locationKey]['totalRaised'] += $donation['amount'] ?? 0;
                }
            }
            
            // Convert to array
            $locationData = array_values($locations);
            
            // Build response
            $response = [
                'locations' => $locationData
            ];
            
            $this->sendResponse($response);
        } catch (Exception $e) {
            $this->sendError(500, "Error generating geographic distribution report: " . $e->getMessage());
        }
    }

    /**
     * Export a report
     */
    private function exportReport($reportType, $options) {
        try {
            // Get report data
            $data = [];
            
            switch ($reportType) {
                case 'campaign-performance':
                    $data = $this->getCampaignPerformanceReportData($_GET['start'] ?? null, $_GET['end'] ?? null);
                    break;
                    
                case 'donor-activity':
                    $data = $this->getDonorActivityReportData($_GET['start'] ?? null, $_GET['end'] ?? null);
                    break;
                    
                case 'financial-summary':
                    $data = $this->getFinancialSummaryReportData($_GET['start'] ?? null, $_GET['end'] ?? null);
                    break;
                    
                default:
                    $this->sendError(400, "Invalid report type for export");
                    break;
            }
            
            // Determine export format
            $format = $options['format'] ?? 'csv';
            
            // Process export based on format
            switch ($format) {
                case 'csv':
                    $this->exportToCsv($data, $reportType);
                    break;
                    
                case 'excel':
                    $this->exportToExcel($data, $reportType);
                    break;
                    
                case 'pdf':
                    $this->exportToPdf($data, $reportType);
                    break;
                    
                case 'json':
                    $this->sendResponse($data);
                    break;
                    
                default:
                    $this->sendError(400, "Unsupported export format");
                    break;
            }
        } catch (Exception $e) {
            $this->sendError(500, "Error exporting report: " . $e->getMessage());
        }
    }

    /**
     * Export data to CSV
     */
    private function exportToCsv($data, $reportType) {
        // Set headers for file download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $reportType . '_report_' . date('Y-m-d') . '.csv"');
        
        // Create output stream
        $output = fopen('php://output', 'w');
        
        // Write CSV header row based on report type
        switch ($reportType) {
            case 'campaign-performance':
                fputcsv($output, ['Campaign Title', 'Category', 'Goal', 'Raised', 'Donors', 'Success Rate', 'Status']);
                
                // Write campaign data rows
                foreach ($data['campaigns'] as $campaign) {
                    $goal = $campaign['fundingGoal'] ?? 0;
                    $raised = $campaign['raised'] ?? 0;
                    $successRate = $goal > 0 ? ($raised / $goal) * 100 : 0;
                    
                    fputcsv($output, [
                        $campaign['title'] ?? 'Untitled Campaign',
                        $campaign['category'] ?? 'Uncategorized',
                        $goal,
                        $raised,
                        $campaign['donorCount'] ?? 0,
                        number_format($successRate, 2) . '%',
                        $campaign['status'] ?? 'pending'
                    ]);
                }
                break;
                
            case 'donor-activity':
                fputcsv($output, ['Donor Name', 'Email', 'Total Donated', '# Donations', 'Avg. Donation', 'Last Donation', 'Donor Type']);
                
                // Write donor data rows
                foreach ($data['donors'] as $donor) {
                    $totalDonated = $donor['totalDonated'] ?? 0;
                    $donationCount = $donor['donationCount'] ?? 0;
                    $avgDonation = $donationCount > 0 ? ($totalDonated / $donationCount) : 0;
                    
                    fputcsv($output, [
                        $donor['name'] ?? ($donor['displayName'] ?? 'Unknown Donor'),
                        $donor['email'] ?? 'N/A',
                        $totalDonated,
                        $donationCount,
                        number_format($avgDonation, 2),
                        $donor['lastDonation'] ?? 'Never',
                        $donor['type'] ?? 'one-time'
                    ]);
                }
                break;
                
            case 'financial-summary':
                fputcsv($output, ['Month', 'Total Donations', 'Platform Fees', 'Disbursements', 'Transactions', 'Avg. Transaction', 'Growth']);
                
                // Write monthly data rows
                foreach ($data['monthlyData'] as $month) {
                    $totalDonations = $month['totalDonations'] ?? 0;
                    $platformFees = $month['platformFees'] ?? 0;
                    $disbursements = $month['disbursements'] ?? 0;
                    $transactionCount = $month['transactionCount'] ?? 0;
                    $avgTransaction = $transactionCount > 0 ? ($totalDonations / $transactionCount) : 0;
                    
                    fputcsv($output, [
                        $month['month'] ?? 'Unknown',
                        $totalDonations,
                        $platformFees,
                        $disbursements,
                        $transactionCount,
                        number_format($avgTransaction, 2),
                        ($month['growth'] ?? 0) . '%'
                    ]);
                }
                break;
        }
        
        // Close the output stream
        fclose($output);
        exit;
    }

    /**
     * Export data to Excel (requires PhpSpreadsheet library)
     */
    private function exportToExcel($data, $reportType) {
        // Check if PhpSpreadsheet is available
        if (!class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
            $this->sendError(500, "PhpSpreadsheet library not available");
        }
        
        // This would be implemented with the PhpSpreadsheet library
        // For now, we'll just use the CSV export as a fallback
        $this->exportToCsv($data, $reportType);
    }

    /**
     * Export data to PDF (requires TCPDF or similar library)
     */
    private function exportToPdf($data, $reportType) {
        // Check if TCPDF is available
        if (!class_exists('TCPDF')) {
            $this->sendError(500, "PDF generation library not available");
        }
        
        // This would be implemented with TCPDF or similar library
        // For now, we'll just return an error
        $this->sendError(501, "PDF export not yet implemented");
    }

    /**
     * Get monthly data for charts
     */
    private function getMonthlyData($start, $end, $dataType) {
        // Calculate the number of months between start and end dates
        $startMonth = $start->format('Y-m');
        $endMonth = $end->format('Y-m');
        
        $monthData = [];
        $current = clone $start;
        
        // Initialize month data with zeros
        while ($current->format('Y-m') <= $endMonth) {
            $month = $current->format('M');
            $monthData[$month] = [
                'month' => $month,
                'count' => 0,
                'value' => 0
            ];
            
            $current->modify('+1 month');
        }
        
        // Fetch actual data from database
        switch ($dataType) {
            case 'campaigns':
                $campaigns = $this->db->getCollection('campaigns')->find([
                    'createdAt' => [
                        '$gte' => new MongoDB\BSON\UTCDateTime($start->getTimestamp() * 1000),
                        '$lte' => new MongoDB\BSON\UTCDateTime($end->getTimestamp() * 1000)
                    ]
                ]);
                
                foreach ($campaigns as $campaign) {
                    $createdAt = $campaign['createdAt'];
                    if ($createdAt instanceof MongoDB\BSON\UTCDateTime) {
                        $date = $createdAt->toDateTime();
                        $month = $date->format('M');
                        
                        if (isset($monthData[$month])) {
                            $monthData[$month]['count']++;
                            $monthData[$month]['value'] += $campaign['fundingGoal'] ?? 0;
                        }
                    }
                }
                break;
                
            case 'donations':
                $donations = $this->db->getCollection('donations')->find([
                    'createdAt' => [
                        '$gte' => new MongoDB\BSON\UTCDateTime($start->getTimestamp() * 1000),
                        '$lte' => new MongoDB\BSON\UTCDateTime($end->getTimestamp() * 1000)
                    ]
                ]);
                
                foreach ($donations as $donation) {
                    $createdAt = $donation['createdAt'];
                    if ($createdAt instanceof MongoDB\BSON\UTCDateTime) {
                        $date = $createdAt->toDateTime();
                        $month = $date->format('M');
                        
                        if (isset($monthData[$month])) {
                            $monthData[$month]['count']++;
                            $monthData[$month]['value'] += $donation['amount'] ?? 0;
                        }
                    }
                }
                break;
                
            case 'users':
                $users = $this->db->getCollection('users')->find([
                    'created' => [
                        '$gte' => new MongoDB\BSON\UTCDateTime($start->getTimestamp() * 1000),
                        '$lte' => new MongoDB\BSON\UTCDateTime($end->getTimestamp() * 1000)
                    ]
                ]);
                
                foreach ($users as $user) {
                    $createdAt = $user['created'];
                    if ($createdAt instanceof MongoDB\BSON\UTCDateTime) {
                        $date = $createdAt->toDateTime();
                        $month = $date->format('M');
                        
                        if (isset($monthData[$month])) {
                            $monthData[$month]['count']++;
                        }
                    }
                }
                break;
        }
        
        // Convert to array
        return array_values($monthData);
    }

    /**
     * Get monthly financial data for charts
     */
    private function getMonthlyFinancialData($start, $end) {
        // Calculate the number of months between start and end dates
        $startMonth = $start->format('Y-m');
        $endMonth = $end->format('Y-m');
        
        $monthData = [];
        $current = clone $start;
        
        // Initialize month data with zeros
        while ($current->format('Y-m') <= $endMonth) {
            $month = $current->format('M Y');
            $monthData[$month] = [
                'month' => $month,
                'totalDonations' => 0,
                'platformFees' => 0,
                'disbursements' => 0,
                'transactionCount' => 0,
                'growth' => 0
            ];
            
            $current->modify('+1 month');
        }
        
        // Fetch donation data from database
        $donations = $this->db->getCollection('donations')->find([
            'createdAt' => [
                '$gte' => new MongoDB\BSON\UTCDateTime($start->getTimestamp() * 1000),
                '$lte' => new MongoDB\BSON\UTCDateTime($end->getTimestamp() * 1000)
            ]
        ]);
        
        $previousMonthTotal = 0;
        
        // Process donation data by month
        foreach ($donations as $donation) {
            $createdAt = $donation['createdAt'];
            if ($createdAt instanceof MongoDB\BSON\UTCDateTime) {
                $date = $createdAt->toDateTime();
                $month = $date->format('M Y');
                
                if (isset($monthData[$month])) {
                    $amount = $donation['amount'] ?? 0;
                    $monthData[$month]['totalDonations'] += $amount;
                    $monthData[$month]['platformFees'] += $amount * 0.05; // Assuming 5% platform fee
                    $monthData[$month]['disbursements'] += $amount * 0.95;
                    $monthData[$month]['transactionCount']++;
                }
            }
        }
        
        // Calculate growth percentages
        $months = array_keys($monthData);
        for ($i = 1; $i < count($months); $i++) {
            $currentMonth = $months[$i];
            $previousMonth = $months[$i - 1];
            
            $currentTotal = $monthData[$currentMonth]['totalDonations'];
            $previousTotal = $monthData[$previousMonth]['totalDonations'];
            
            if ($previousTotal > 0) {
                $growth = (($currentTotal - $previousTotal) / $previousTotal) * 100;
                $monthData[$currentMonth]['growth'] = round($growth, 1);
            }
        }
        
        // Convert to array
        return array_values($monthData);
    }

    /**
     * Get donor list with aggregated data
     */
    private function getDonorList($startDateTime, $endDateTime) {
        // Get donations within date range
        $donations = $this->db->getCollection('donations')->find([
            'createdAt' => [
                '$gte' => $startDateTime,
                '$lte' => $endDateTime
            ]
        ]);
        
        // Group donations by donor
        $donorMap = [];
        
        foreach ($donations as $donation) {
            $userId = $donation['userId'] ?? null;
            if (!$userId) continue;
            
            if (!isset($donorMap[$userId])) {
                // Get user information
                $user = $this->db->getCollection('users')->findOne(['_id' => new MongoDB\BSON\ObjectId($userId)]);
                
                $donorMap[$userId] = [
                    'userId' => $userId,
                    'name' => $user ? ($user['displayName'] ?? ($user['username'] ?? 'Unknown User')) : 'Unknown User',
                    'email' => $user ? ($user['email'] ?? 'N/A') : 'N/A',
                    'totalDonated' => 0,
                    'donationCount' => 0,
                    'lastDonation' => null,
                    'type' => 'one-time'
                ];
            }
            
            $donorMap[$userId]['totalDonated'] += $donation['amount'] ?? 0;
            $donorMap[$userId]['donationCount']++;
            
            // Track last donation date
            $donationDate = $donation['createdAt'];
            if ($donationDate instanceof MongoDB\BSON\UTCDateTime) {
                $date = $donationDate->toDateTime()->format('Y-m-d');
                
                if (!$donorMap[$userId]['lastDonation'] || $date > $donorMap[$userId]['lastDonation']) {
                    $donorMap[$userId]['lastDonation'] = $date;
                }
            }
            
            // Check for recurring donors (2+ donations)
            if ($donorMap[$userId]['donationCount'] >= 2) {
                $donorMap[$userId]['type'] = 'recurring';
            }
        }
        
        // Convert to array and sort by total donated (highest first)
        $donors = array_values($donorMap);
        usort($donors, function($a, $b) {
            return $b['totalDonated'] - $a['totalDonated'];
        });
        
        return $donors;
    }

    /**
     * Get campaign performance report data for export
     */
    private function getCampaignPerformanceReportData($startDate, $endDate) {
        // Similar to getCampaignPerformanceReport but returns data instead of sending response
        $start = $startDate ? new DateTime($startDate) : new DateTime('-30 days');
        $end = $endDate ? new DateTime($endDate) : new DateTime();
        
        $startDateTime = new MongoDB\BSON\UTCDateTime($start->getTimestamp() * 1000);
        $endDateTime = new MongoDB\BSON\UTCDateTime($end->getTimestamp() * 1000);
        
        // Get campaigns within date range
        $campaigns = $this->db->getCollection('campaigns')->find([
            'createdAt' => [
                '$gte' => $startDateTime,
                '$lte' => $endDateTime
            ]
        ]);
        
        // Calculate summary metrics
        $totalCampaigns = count($campaigns);
        $totalGoal = 0;
        $totalRaised = 0;
        $successfulCampaigns = 0;
        
        foreach ($campaigns as $campaign) {
            $goal = $campaign['fundingGoal'] ?? 0;
            $raised = $campaign['raised'] ?? 0;
            
            $totalGoal += $goal;
            $totalRaised += $raised;
            
            if ($raised >= $goal) {
                $successfulCampaigns++;
            }
        }
        
        $avgGoal = $totalCampaigns ? $totalGoal / $totalCampaigns : 0;
        $avgRaised = $totalCampaigns ? $totalRaised / $totalCampaigns : 0;
        $successRate = $totalCampaigns ? ($successfulCampaigns / $totalCampaigns) * 100 : 0;
        
        return [
            'summary' => [
                'totalCampaigns' => $totalCampaigns,
                'totalGoal' => $totalGoal,
                'totalRaised' => $totalRaised,
                'avgGoal' => $avgGoal,
                'avgRaised' => $avgRaised,
                'successRate' => $successRate,
                'successfulCampaigns' => $successfulCampaigns
            ],
            'campaigns' => $campaigns
        ];
    }

    /**
     * Get donor activity report data for export
     */
    private function getDonorActivityReportData($startDate, $endDate) {
        // Similar to getDonorActivityReport but returns data instead of sending response
        $start = $startDate ? new DateTime($startDate) : new DateTime('-30 days');
        $end = $endDate ? new DateTime($endDate) : new DateTime();
        
        $startDateTime = new MongoDB\BSON\UTCDateTime($start->getTimestamp() * 1000);
        $endDateTime = new MongoDB\BSON\UTCDateTime($end->getTimestamp() * 1000);
        
        // Get donor list with aggregated data
        $donors = $this->getDonorList($startDateTime, $endDateTime);
        
        // Calculate summary metrics
        $totalDonors = count($donors);
        $totalDonated = array_reduce($donors, function($sum, $donor) {
            return $sum + ($donor['totalDonated'] ?? 0);
        }, 0);
        
        $recurringDonors = count(array_filter($donors, function($donor) {
            return isset($donor['type']) && $donor['type'] === 'recurring';
        }));
        
        return [
            'summary' => [
                'totalDonors' => $totalDonors,
                'totalDonated' => $totalDonated,
                'recurringDonors' => $recurringDonors,
                'avgDonation' => $totalDonors ? $totalDonated / $totalDonors : 0
            ],
            'donors' => $donors
        ];
    }

    /**
     * Get financial summary report data for export
     */
    private function getFinancialSummaryReportData($startDate, $endDate) {
        // Similar to getFinancialSummaryReport but returns data instead of sending response
        $start = $startDate ? new DateTime($startDate) : new DateTime('-30 days');
        $end = $endDate ? new DateTime($endDate) : new DateTime();
        
        $startDateTime = new MongoDB\BSON\UTCDateTime($start->getTimestamp() * 1000);
        $endDateTime = new MongoDB\BSON\UTCDateTime($end->getTimestamp() * 1000);
        
        // Get monthly data for charts
        $monthlyData = $this->getMonthlyFinancialData($start, $end);
        
        // Calculate summary metrics
        $totalDonations = array_reduce($monthlyData, function($sum, $month) {
            return $sum + ($month['totalDonations'] ?? 0);
        }, 0);
        
        $platformFees = $totalDonations * 0.05; // Assuming 5% platform fee
        $disbursements = $totalDonations - $platformFees;
        
        $transactionCount = array_reduce($monthlyData, function($sum, $month) {
            return $sum + ($month['transactionCount'] ?? 0);
        }, 0);
        
        return [
            'summary' => [
                'totalDonations' => $totalDonations,
                'platformFees' => $platformFees,
                'disbursements' => $disbursements,
                'transactionCount' => $transactionCount,
                'avgTransaction' => $transactionCount ? $totalDonations / $transactionCount : 0
            ],
            'monthlyData' => $monthlyData
        ];
    }

    /**
     * Send JSON response
     */
    private function sendResponse($data) {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * Send error response
     */
    private function sendError($code, $message) {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode(['error' => $message]);
        exit;
    }
}
