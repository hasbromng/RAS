<?php
/**
 * API Endpoint: Dashboard
 * Provides summary statistics and dashboard data
 *
 * GET /admin/api/dashboard.php - Get dashboard summary
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

    // Get device statistics
    $device_stats = [
        'total' => 0,
        'online' => 0,
        'offline' => 0,
        'warning' => 0,
        'critical' => 0
    ];

    $stmt = $pdo->query("SELECT status, COUNT(*) AS count FROM devices GROUP BY status");
    while ($row = $stmt->fetch()) {
        $device_stats[$row['status']] = (int)$row['count'];
        $device_stats['total'] += (int)$row['count'];
    }

    // Get alert statistics (last 24 hours)
    $alert_stats = [
        'total' => 0,
        'open' => 0,
        'acknowledged' => 0,
        'resolved' => 0,
        'critical' => 0,
        'warning' => 0
    ];

    $stmt = $pdo->query("
        SELECT
            status,
            severity,
            COUNT(*) AS count
        FROM alerts
        WHERE timestamp > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        GROUP BY status, severity
    ");

    while ($row = $stmt->fetch()) {
        $alert_stats[$row['status']] = (int)$row['status'] === 'open' ? (int)($row['count'] ?? 0) : $alert_stats[$row['status']];
        $alert_stats[$row['status']] = isset($alert_stats[$row['status']]) ? $alert_stats[$row['status']] + (int)$row['count'] : (int)$row['count'];
        $alert_stats['total'] += (int)$row['count'];
        $alert_stats[$row['severity']] = (int)$row['count'];
    }

    // Get recent devices with latest metrics
    $stmt = $pdo->query("
        SELECT
            d.device_id,
            d.hostname,
            d.ip_address,
            d.status,
            d.last_seen,
            m.cpu_usage,
            m.memory_used,
            m.memory_total,
            m.disk_used,
            m.disk_total,
            m.disk_usage,
            m.storage_health,
            m.network_status,
            (SELECT COUNT(*) FROM alerts a WHERE a.device_id = d.device_id AND a.status = 'open') AS open_alerts
        FROM devices d
        LEFT JOIN v_latest_metrics m ON m.device_id = d.device_id
        ORDER BY d.last_seen DESC
        LIMIT 10
    ");
    $recent_devices = $stmt->fetchAll();

    // Get recent critical alerts
    $stmt = $pdo->query("
        SELECT
            a.id,
            a.device_id,
            a.alert_type,
            a.severity,
            a.message,
            a.timestamp,
            a.status,
            d.hostname,
            d.ip_address
        FROM alerts a
        INNER JOIN devices d ON d.device_id = a.device_id
        WHERE a.status = 'open'
        ORDER BY a.severity DESC, a.timestamp DESC
        LIMIT 10
    ");
    $critical_alerts = $stmt->fetchAll();

    // Get average metrics across all devices
    $avg_metrics = $pdo->query("
        SELECT
            AVG(cpu_usage) AS avg_cpu,
            AVG(disk_usage) AS avg_disk,
            AVG(memory_used / memory_total * 100) AS avg_memory
        FROM v_latest_metrics
    ")->fetch();

    // Get system settings
    $refresh_interval = getSetting($pdo, 'dashboard_refresh_seconds', 30);

    sendJsonResponse([
        'success' => true,
        'data' => [
            'device_stats' => $device_stats,
            'alert_stats' => $alert_stats,
            'recent_devices' => $recent_devices,
            'critical_alerts' => $critical_alerts,
            'average_metrics' => [
                'cpu' => round($avg_metrics['avg_cpu'] ?? 0, 2),
                'memory' => round($avg_metrics['avg_memory'] ?? 0, 2),
                'disk' => round($avg_metrics['avg_disk'] ?? 0, 2)
            ],
            'refresh_interval' => $refresh_interval
        ]
    ]);

} catch (PDOException $e) {
    logMessage("Database error in dashboard.php: " . $e->getMessage(), 'ERROR');

    sendJsonResponse([
        'success' => false,
        'message' => 'Database error occurred.'
    ], 500);
}
