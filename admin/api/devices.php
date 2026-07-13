<?php
/**
 * API Endpoint: Devices
 * Provides device information for dashboard
 *
 * GET /admin/api/devices.php - List all devices
 * GET /admin/api/devices.php?id={device_id} - Get specific device
 */

// Include configuration
require_once __DIR__ . '/../../config/config.php';

// Set headers
header('Content-Type: application/json');

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJsonResponse([
        'success' => false,
        'message' => 'Method not allowed. Use GET.'
    ], 405);
}

try {
    $pdo = getDbConnection();

    $device_id = isset($_GET['id']) ? $_GET['id'] : null;

    if ($device_id) {
        // Get specific device
        $stmt = $pdo->prepare("
            SELECT
                d.*,
                (SELECT COUNT(*) FROM alerts a WHERE a.device_id = d.device_id AND a.status = 'open') AS open_alerts,
                (SELECT COUNT(*) FROM alerts a WHERE a.device_id = d.device_id AND a.severity = 'critical' AND a.status = 'open') AS critical_alerts,
                m.cpu_usage,
                m.memory_used,
                m.memory_total,
                m.disk_used,
                m.disk_total,
                m.disk_usage,
                m.storage_health,
                m.network_status,
                m.timestamp AS last_metric_time
            FROM devices d
            LEFT JOIN v_latest_metrics m ON m.device_id = d.device_id
            WHERE d.device_id = ?
        ");
        $stmt->execute([$device_id]);
        $device = $stmt->fetch();

        if (!$device) {
            sendJsonResponse([
                'success' => false,
                'message' => 'Device not found.'
            ], 404);
        }

        // Get recent metrics for this device
        $metrics_stmt = $pdo->prepare("
            SELECT * FROM metrics
            WHERE device_id = ?
            ORDER BY timestamp DESC
            LIMIT 100
        ");
        $metrics_stmt->execute([$device_id]);
        $device['metrics'] = $metrics_stmt->fetchAll();

        // Get recent alerts for this device
        $alerts_stmt = $pdo->prepare("
            SELECT * FROM alerts
            WHERE device_id = ?
            ORDER BY timestamp DESC
            LIMIT 20
        ");
        $alerts_stmt->execute([$device_id]);
        $device['alerts'] = $alerts_stmt->fetchAll();

        sendJsonResponse([
            'success' => true,
            'data' => $device
        ]);

    } else {
        // List all devices
        $stmt = $pdo->query("
            SELECT
                d.*,
                (SELECT COUNT(*) FROM alerts a WHERE a.device_id = d.device_id AND a.status = 'open') AS open_alerts,
                (SELECT COUNT(*) FROM alerts a WHERE a.device_id = d.device_id AND a.severity = 'critical' AND a.status = 'open') AS critical_alerts,
                m.cpu_usage,
                m.memory_used,
                m.memory_total,
                m.disk_used,
                m.disk_total,
                m.disk_usage,
                m.storage_health,
                m.network_status,
                m.timestamp AS last_metric_time
            FROM devices d
            LEFT JOIN v_latest_metrics m ON m.device_id = d.device_id
            ORDER BY d.last_seen DESC
        ");

        $devices = $stmt->fetchAll();

        // Get summary statistics
        $stats = [
            'total' => count($devices),
            'online' => 0,
            'offline' => 0,
            'warning' => 0,
            'critical' => 0
        ];

        foreach ($devices as $device) {
            $stats[$device['status']]++;
        }

        sendJsonResponse([
            'success' => true,
            'data' => $devices,
            'stats' => $stats
        ]);
    }

} catch (PDOException $e) {
    logMessage("Database error in devices.php: " . $e->getMessage(), 'ERROR');

    sendJsonResponse([
        'success' => false,
        'message' => 'Database error occurred.'
    ], 500);
}
