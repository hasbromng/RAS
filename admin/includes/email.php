<?php
/**
 * Email Notification Library
 * Simple SMTP email sender for RAS alerts
 */

// Prevent direct access
if (!defined('RAS_INCLUDED')) {
    define('RAS_INCLUDED', true);
    require_once __DIR__ . '/../../config/config.php';
}

/**
 * Email Notification Class
 */
class EmailNotifier {

    private $pdo;
    private $enabled;
    private $smtpHost;
    private $smtpPort;
    private $smtpSecure;
    private $fromAddress;
    private $fromName;
    private $toAddress;

    /**
     * Constructor
     *
     * @param PDO $pdo Database connection
     */
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->loadSettings();
    }

    /**
     * Load email settings from database
     */
    private function loadSettings() {
        $this->enabled = getSetting($this->pdo, 'email_enabled', false);
        $this->smtpHost = getSetting($this->pdo, 'email_smtp_host', 'localhost');
        $this->smtpPort = getSetting($this->pdo, 'email_smtp_port', 25);
        $this->smtpSecure = getSetting($this->pdo, 'email_smtp_secure', 'none');
        $this->fromAddress = getSetting($this->pdo, 'email_from_address', 'noreply@ras.local');
        $this->fromName = getSetting($this->pdo, 'email_from_name', 'RAS Monitor');
        $this->toAddress = getSetting($this->pdo, 'email_to_address', '');
    }

    /**
     * Send critical alert email
     *
     * @param string $device_id Device identifier
     * @param string $hostname Device hostname
     * @param array $alert Alert details
     * @return bool
     */
    public function sendCriticalAlert($device_id, $hostname, $alert) {
        if (!$this->enabled || empty($this->toAddress)) {
            logMessage("Email notification disabled or no recipient set", 'INFO');
            return false;
        }

        $subject = "[CRITICAL] {$alert['type']} Alert - {$hostname}";
        $message = $this->buildAlertMessage($device_id, $hostname, $alert);

        return $this->send($subject, $message);
    }

    /**
     * Send daily summary email
     *
     * @param array $summary Daily summary data
     * @return bool
     */
    public function sendDailySummary($summary) {
        if (!$this->enabled || empty($this->toAddress)) {
            return false;
        }

        $subject = "[RAS] Daily Summary - " . date('Y-m-d');
        $message = $this->buildSummaryMessage($summary);

        return $this->send($subject, $message);
    }

    /**
     * Build alert message
     *
     * @param string $device_id Device identifier
     * @param string $hostname Device hostname
     * @param array $alert Alert details
     * @return string
     */
    private function buildAlertMessage($device_id, $hostname, $alert) {
        $message = "CRITICAL ALERT DETECTED\n";
        $message .= "=====================\n\n";
        $message .= "Device Information:\n";
        $message .= "  Hostname: {$hostname}\n";
        $message .= "  Device ID: {$device_id}\n\n";
        $message .= "Alert Details:\n";
        $message .= "  Type: {$alert['type']}\n";
        $message .= "  Severity: {$alert['severity']}\n";
        $message .= "  Message: {$alert['message']}\n\n";
        $message .= "Time: " . date('Y-m-d H:i:s') . "\n\n";
        $message .= "Please investigate this issue immediately.\n\n";
        $message .= "---\n";
        $message .= "RAS Monitor System\n";
        $message .= "This is an automated message, please do not reply.";

        return $message;
    }

    /**
     * Build summary message
     *
     * @param array $summary Summary data
     * @return string
     */
    private function buildSummaryMessage($summary) {
        $message = "RAS DAILY SUMMARY REPORT\n";
        $message .= "========================\n\n";
        $message .= "Date: " . date('Y-m-d') . "\n\n";

        if (isset($summary['devices'])) {
            $message .= "Device Status:\n";
            $message .= "  Total Devices: {$summary['devices']['total']}\n";
            $message .= "  Online: {$summary['devices']['online']}\n";
            $message .= "  Offline: {$summary['devices']['offline']}\n";
            $message .= "  Warning: {$summary['devices']['warning']}\n";
            $message .= "  Critical: {$summary['devices']['critical']}\n\n";
        }

        if (isset($summary['alerts'])) {
            $message .= "Alert Summary (Last 24 Hours):\n";
            $message .= "  Total Alerts: {$summary['alerts']['total']}\n";
            $message .= "  Open: {$summary['alerts']['open']}\n";
            $message .= "  Resolved: {$summary['alerts']['resolved']}\n";
            $message .= "  Critical: {$summary['alerts']['critical']}\n\n";
        }

        if (isset($summary['top_issues']) && !empty($summary['top_issues'])) {
            $message .= "Top Issues:\n";
            foreach ($summary['top_issues'] as $issue) {
                $message .= "  - {$issue['hostname']}: {$issue['message']}\n";
            }
            $message .= "\n";
        }

        $message .= "---\n";
        $message .= "RAS Monitor System\n";
        $message .= "This is an automated message, please do not reply.";

        return $message;
    }

    /**
     * Send email using PHP mail() or SMTP
     *
     * @param string $subject Email subject
     * @param string $message Email message body
     * @return bool
     */
    private function send($subject, $message) {
        try {
            // For MVP, use PHP's mail() function
            // In production, use PHPMailer or similar for proper SMTP support

            $headers = "From: {$this->fromName} <{$this->fromAddress}>\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

            $result = mail($this->toAddress, $subject, $message, $headers);

            if ($result) {
                logMessage("Email sent successfully to {$this->toAddress}", 'INFO');
            } else {
                logMessage("Failed to send email to {$this->toAddress}", 'ERROR');
            }

            return $result;

        } catch (Exception $e) {
            logMessage("Error sending email: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Test email configuration
     *
     * @return bool
     */
    public function test() {
        if (!$this->enabled || empty($this->toAddress)) {
            return false;
        }

        $subject = "[RAS] Test Email";
        $message = "This is a test email from the RAS Monitor System.\n\n";
        $message .= "If you received this email, your email configuration is working correctly.\n\n";
        $message .= "Time: " . date('Y-m-d H:i:s') . "\n\n";
        $message .= "---\n";
        $message .= "RAS Monitor System";

        return $this->send($subject, $message);
    }

    /**
     * Check if email notifications are enabled
     *
     * @return bool
     */
    public function isEnabled() {
        return $this->enabled && !empty($this->toAddress);
    }
}

/**
 * Send alert email notification (convenience function)
 *
 * @param PDO $pdo Database connection
 * @param string $device_id Device identifier
 * @param string $hostname Device hostname
 * @param array $alert Alert details
 * @return bool
 */
function sendAlertEmail($pdo, $device_id, $hostname, $alert) {
    static $notifier = null;

    if ($notifier === null) {
        $notifier = new EmailNotifier($pdo);
    }

    return $notifier->sendCriticalAlert($device_id, $hostname, $alert);
}
