<?php
// lib/RiskScoringService.php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/JumioService.php';

class RiskScoringService {
    private $db;
    private $usersCollection;
    private $transactionsCollection;

    private $highRiskCountries = [
        'IR', 'KP', 'SY', 'CU', 'SD', 'RU'
    ];

    public function __construct() {
        $this->db = Database::getInstance();
        $this->usersCollection = $this->db->getCollection('users');
        $this->transactionsCollection = $this->db->getCollection('blockchain_transactions');
    }

    /**
     * Calculate and update a user's risk score.
     *
     * @param string $userId MongoDB ID string
     * @return array{success:bool, score?:int, level?:string, error?:string}
     */
    public function calculateRiskScore($userId) {
        try {
            $user = $this->usersCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($userId)]);
            if (!$user) {
                return ['success' => false, 'error' => 'User not found'];
            }

            $score = 0;

            // Factor: high risk country
            $country = $user['personalInfo']['country'] ?? '';
            if ($country && in_array(strtoupper($country), $this->highRiskCountries)) {
                $score += 40;
            }

            // Factor: transaction activity in last 24h
            $since = new MongoDB\BSON\UTCDateTime((time() - 86400) * 1000);
            $txCount = $this->transactionsCollection->count([
                'userId' => $userId,
                'createdAt' => ['$gte' => $since]
            ]);
            if ($txCount > 10) {
                $score += 20;
            }

            // Factor: verification status
            $jumio = new JumioService();
            $verification = $jumio->getVerificationStatus($userId);
            if (!($verification['success'] && $verification['verified'])) {
                $score += 30;
            }

            $score = min(100, $score);
            if ($score >= 70) {
                $level = 'high';
            } elseif ($score >= 40) {
                $level = 'medium';
            } else {
                $level = 'low';
            }

            $this->usersCollection->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($userId)],
                ['$set' => [
                    'riskScore' => $score,
                    'riskLevel' => $level,
                    'updatedAt' => new MongoDB\BSON\UTCDateTime()
                ]]
            );

            return ['success' => true, 'score' => $score, 'level' => $level];
        } catch (Exception $e) {
            error_log('Risk scoring error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
