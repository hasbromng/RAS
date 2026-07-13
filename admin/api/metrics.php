<?php
/**
 * API Endpoint: Metrics
 * Receives and stores device metrics from Python agent
 *
 * POST /admin/api/metrics.php
 */

// Include configuration
require_once __DIR__ . '/../../config/config.php';

// Set headers
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse([
        'success' => false,
        'message' => 'Method not allowed. Use POST.'
    ], 405);
}

// Validate API key
if (!validateApiKey()) {
    logMessage("Unauthorized API access attempt from " . $_SERVER['REMOTE_ADDR'], 'WARNING');
    sendJsonResponse([
        'success' => false,
        'message' => 'Unauthorized. Invalid API key.'
    ], 401);
}

// Get and decode JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    logMessage("Invalid JSON input: " . json_last_error_msg(), 'WARNING');
    sendJsonResponse([
        'success' => false,
        'message' => 'Invalid JSON input.'
    ], 400);
}

// Validate required fields
$required_fields = ['device_id', 'hostname', 'ip_address'];
foreach ($required_fields as $field) {
    if (empty($data[$field])) {
        sendJsonResponse([
            'success' => false,
            'message' => "Missing required field: {$field}"
        ], 400);
    }
}

try {
    $pdo = getDbConnection();
    $pdo->beginTransaction();

    // Get alert thresholds
    $cpu_threshold = getSetting($pdo, 'alert_threshold_cpu', 90);
    $memory_threshold = getSetting($pdo, 'alert_threshold_memory', 90);
    $disk_threshold = getSetting($pdo, 'alert_threshold_disk', 85);
    $offline_minutes = getSetting($pdo, 'device_offline_minutes', 5);

    // Prepare device data
    $device_id = $data['device_id'];
    $hostname = $data['hostname'];
    $ip_address = $data['ip_address'];
    $now = date('Y-m-d H:i:s');

    // Determine device status
    $status = 'online';
    $alerts_to_create = [];

    if (isset($data['cpu_usage']) && $data['cpu_usage'] >= $cpu_threshold) {
        $status = 'critical';
        $alerts_to_create[] = [
            'type' => 'cpu',
            'severity' => 'critical',
            'message' => "CPU usage critical: {$data['cpu_usage']}%"
        ];
    } elseif (isset($data['cpu_usage']) && $data['cpu_usage'] >= ($cpu_threshold - 10)) {
        $status = ($status === 'online') ? 'warning' : $status;
        $alerts_to_create[] = [
            'type' => 'cpu',
            'severity' => 'warning',
            'message' => "CPU usage high: {$data['cpu_usage']}%"
        ];
    }

    if (isset($data['memory_used'], $data['memory_total']) && $data['memory_total'] > 0) {
        $memory_percent = ($data['memory_used'] / $data['memory_total']) * 100;
        if ($memory_percent >= $memory_threshold) {
            $status = 'critical';
            $alerts_to_create[] = [
                'type' => 'memory',
                'severity' => 'critical',
                'message' => "Memory usage critical: " . round($memory_percent, 2) . "%"
            ];
        } elseif ($memory_percent >= ($memory_threshold - 10)) {
            $status = ($status === 'online') ? 'warning' : $status;
            $alerts_to_create[] = [
                'type' => 'memory',
                'severity' => 'warning',
                'message' => "Memory usage high: " . round($memory_percent, 2) . "%"
            ];
        }
    }

    if (isset($data['disk_usage']) && $data['disk_usage'] >= $disk_threshold) {
        $status = 'critical';
        $alerts_to_create[] = [
            'type' => 'disk',
            'severity' => 'critical',
            'message' => "Disk usage critical: {$data['disk_usage']}%"
        ];
    } elseif (isset($data['disk_usage']) && $data['disk_usage'] >= ($disk_threshold - 10)) {
        $status = ($status === 'online') ? 'warning' : $status;
        $alerts_to_create[] = [
            'type' => 'disk',
            'severity' => 'warning',
            'message' => "Disk usage high: {$data['disk_usage']}%"
        ];
    }

    if (isset($data['storage_health']) && $data['storage_health'] === 'critical') {
        $status = 'critical';
        $alerts_to_create[] = [
            'type' => 'storage',
            'severity' => 'critical',
            'message' => "Storage health critical"
        ];
    }

    // Upsert device
    $stmt = $pdo->prepare("
        INSERT INTO devices (device_id, hostname, ip_address, last_seen, status)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        hostname = VALUES(hostname),
        ip_address = VALUES(ip_address),
        last_seen = VALUES(last_seen),
        status = VALUES(status),
        updated_at = CURRENT_TIMESTAMP
    ");
    $stmt->execute([$device_id, $hostname, $ip_address, $now, $status]);

    // Insert metrics
    $stmt = $pdo->prepare("
        INSERT INTO metrics (
            device_id, timestamp, cpu_usage, memory_used, memory_total,
            disk_used, disk_total, disk_usage, storage_health, network_status,
            additional_info
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $additional_info = null;
    if (isset($data['additional_info'])) {
        $additional_info = json_encode($data['additional_info']);
    }

    $stmt->execute([
        $device_id,
        $now,
        $data['cpu_usage'] ?? 0,
        $data['memory_used'] ?? 0,
        $data['memory_total'] ?? 0,
        $data['disk_used'] ?? 0,
        $data['disk_total'] ?? 0,
        $data['disk_usage'] ?? 0,
        $data['storage_health'] ?? 'unknown',
        $data['network_status'] ?? 'unknown',
        $additional_info
    ]);

    // Create alerts
    foreach ($alerts_to_create as $alert) {
        // Check if similar alert exists in last hour
        $check_stmt = $pdo->prepare("
            SELECT id FROM alerts
            WHERE device_id = ?
            AND alert_type = ?
            AND severity = ?
            AND timestamp > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            AND status = 'open'
            LIMIT 1
        ");
        $check_stmt->execute([$device_id, $alert['type'], $alert['severity']]);

        if (!$check_stmt->fetch()) {
            // Create new alert
            $alert_stmt = $pdo->prepare("
                INSERT INTO alerts (device_id, timestamp, alert_type, severity, message)
                VALUES (?, ?, ?, ?, ?)
            ");
            $alert_stmt->execute([
                $device_id,
                $now,
                $alert['type'],
                $alert['severity'],
                $alert['message']
            ]);

            // Send email notification if critical
            if ($alert['severity'] === 'critical') {
                sendEmailNotification($pdo, $device_id, $hostname, $alert);
            }
        }
    }

    // Update offline devices
    $offline_stmt = $pdo->prepare("
        UPDATE devices
        SET status = 'offline'
        WHERE last_seen < DATE_SUB(NOW(), INTERVAL ? MINUTE)
        AND status != 'offline'
    ");
    $offline_stmt->execute([$offline_minutes]);

    $pdo->commit();

    logMessage("Metrics received from device: {$device_id} ({$hostname})", 'INFO');

    sendJsonResponse([
        'success' => true,
        'message' => 'Metrics received successfully',
        'device_id' => $device_id,
        'status' => $status,
        'alerts_created' => count($alerts_to_create)
    ]);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    logMessage("Database error in metrics.php: " . $e->getMessage(), 'ERROR');

    sendJsonResponse([
        'success' => false,
        'message' => 'Database error occurred.'
    ], 500);
} catch (Exception $e) {
    logMessage("Error in metrics.php: " . $e->getMessage(), 'ERROR');

    sendJsonResponse([
        'success' => false,
        'message' => 'An error occurred.'
    ], 500);
}

/**
 * Send email notification for critical alerts
 *
 * @param PDO $pdo Database connection
 * @param string $device_id Device identifier
 * @param string $hostname Device hostname
 * @param array $alert Alert details
 */
function sendEmailNotification($pdo, $device_id, $hostname, $alert) {
    $email_enabled = getSetting($pdo, 'email_enabled', false);

    if (!$email_enabled) {
        return;
    }

    $to_address = getSetting($pdo, 'email_to_address', '');
    if (empty($to_address)) {
        return;
    }

    $from_address = getSetting($pdo, 'email_from_address', 'noreply@ras.local');
    $from_name = getSetting($pdo, 'email_from_name', 'RAS Monitor');

    $subject = "[CRITICAL] {$alert['severity']} Alert - {$hostname}";
    $message = "Critical alert detected:\n\n";
    $message .= "Device: {$hostname} ({$device_id})\n";
    $message .= "Type: {$alert['type']}\n";
    $message .= "Severity: {$alert['severity']}\n";
    $message .= "Message: {$alert['message']}\n";
    $message .= "\nTime: " . date('Y-m-d H:i:s') . "\n";

    $headers = "From: {$from_name} <{$from_address}>\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    // Store email in log instead of sending directly
    logMessage("Email notification: To={$to_address}, Subject={$subject}", 'INFO');

    // In production, use PHPMailer or similar for proper SMTP support
    // mail($to_address, $subject, $message, $headers);
}
