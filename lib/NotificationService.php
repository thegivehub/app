<?php
require_once __DIR__ . '/Mailer.php';
require_once __DIR__ . '/config.php';

use Twilio\Rest\Client;

/**
 * Service for sending notifications across multiple channels
 * (email, SMS, Slack) following the pattern from docs/transaction-system/best-practices.html
 */
class NotificationService {
    private $mailer;
    private $smsClient;
    private $slackWebhook;
    private $smsFrom;

    public function __construct($config = []) {
        $this->mailer = $config['mailer'] ?? new Mailer();

        $sid = $config['twilioSid'] ?? (defined('TWILIO_SID') ? TWILIO_SID : null);
        $token = $config['twilioToken'] ?? (defined('TWILIO_TOKEN') ? TWILIO_TOKEN : null);
        $this->smsFrom = $config['smsFrom'] ?? (defined('SMS_FROM') ? SMS_FROM : null);
        if ($sid && $token) {
            $this->smsClient = new Client($sid, $token);
        }

        $this->slackWebhook = $config['slackWebhook'] ?? (defined('SLACK_WEBHOOK_URL') ? SLACK_WEBHOOK_URL : null);
    }

    /**
     * Send notification message to configured channels
     *
     * @param string $subject Subject or title of the notification
     * @param string $message Main text of the notification
     * @param array $emails List of email addresses to notify
     * @param array $phones List of phone numbers for SMS
     */
    public function send($subject, $message, $emails = [], $phones = []) {
        // Email notification
        if (!empty($emails)) {
            foreach ($emails as $email) {
                try {
                    $this->mailer->sendNotification($email, $subject, $message);
                } catch (\Throwable $e) {
                    error_log('Failed to send email notification: ' . $e->getMessage());
                }
            }
        }

        // SMS notification
        if ($this->smsClient && $this->smsFrom && !empty($phones)) {
            foreach ($phones as $phone) {
                try {
                    $this->smsClient->messages->create($phone, [
                        'from' => $this->smsFrom,
                        'body' => $subject . ' - ' . $message
                    ]);
                } catch (\Throwable $e) {
                    error_log('Failed to send SMS notification: ' . $e->getMessage());
                }
            }
        }

        // Slack notification
        if ($this->slackWebhook) {
            $payload = json_encode(['text' => "*{$subject}*\n{$message}"]);
            $ch = curl_init($this->slackWebhook);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $res = curl_exec($ch);
            if ($res === false) {
                error_log('Failed to send Slack notification: ' . curl_error($ch));
            }
            curl_close($ch);
        }
    }
}
