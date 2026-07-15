<?php
/**
 * API Endpoint: Alerts
 * Provides alert information for dashboard
 *
 * GET /admin/api/alerts.php - List all alerts
 * GET /admin/api/alerts.php?device_id={device_id} - Get alerts for specific device
 * GET /admin/api/alerts.php?severity={severity} - Filter by severity
 * PUT /admin/api/alerts.php - Update alert status (acknowledge/resolve)
 */

// Include configuration
require_once __DIR__ . '/../../config/config.php';

// Set headers
header('Content-Type: application/json');

try {
    $pdo = getDbConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {

        $device_id = isset($_GET['device_id']) ? $_GET['device_id'] : null;
        $severity = isset($_GET['severity']) ? $_GET['severity'] : null;
        $status = isset($_GET['status']) ? $_GET['status'] : null;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;

        // Build query
        $sql = "
            SELECT
                a.*,
                d.hostname,
                d.ip_address
            FROM alerts a
            INNER JOIN devices d ON d.device_id = a.device_id
            WHERE 1=1
        ";

        $params = [];

        if ($device_id) {
            $sql .= " AND a.device_id = ?";
            $params[] = $device_id;
        }

        if ($severity) {
            $sql .= " AND a.severity = ?";
            $params[] = $severity;
        }

        if ($status) {
            $sql .= " AND a.status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY a.timestamp DESC LIMIT " . $limit;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $alerts = $stmt->fetchAll();

        // Get summary counts
        $summary_stmt = $pdo->query("
            SELECT
                severity,
                status,
                COUNT(*) AS count
            FROM alerts
            WHERE timestamp > DATE_SUB(NOW(), INTERVAL 24 HOUR)
            GROUP BY severity, status
        ");
        $summary = $summary_stmt->fetchAll();

        sendJsonResponse([
            'success' => true,
            'data' => $alerts,
            'summary' => $summary
        ]);

    } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT' || $_SERVER['REQUEST_METHOD'] === 'POST') {

        // Update alert status
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (empty($data['alert_id'])) {
            sendJsonResponse([
                'success' => false,
                'message' => 'Missing alert_id'
            ], 400);
        }

        $alert_id = (int)$data['alert_id'];
        $new_status = $data['status'] ?? 'acknowledged';
        $acknowledged_by = $data['acknowledged_by'] ?? 'Admin';

        if (!in_array($new_status, ['acknowledged', 'resolved', 'open'])) {
            sendJsonResponse([
                'success' => false,
                'message' => 'Invalid status. Must be: acknowledged, resolved, or open'
            ], 400);
        }

        $stmt = $pdo->prepare("
            UPDATE alerts
            SET status = ?,
                acknowledged_at = CASE WHEN ? IN ('acknowledged', 'resolved') THEN NOW() ELSE acknowledged_at END,
                resolved_at = CASE WHEN ? = 'resolved' THEN NOW() ELSE resolved_at END,
                acknowledged_by = CASE WHEN ? IN ('acknowledged', 'resolved') THEN ? ELSE acknowledged_by END
            WHERE id = ?
        ");

        $stmt->execute([$new_status, $new_status, $new_status, $new_status, $acknowledged_by, $alert_id]);

        if ($stmt->rowCount() > 0) {
            logActivity($pdo, 'ALERT_UPDATE', "Alert {$alert_id} status updated to {$new_status} by {$acknowledged_by}", 'INFO');

            sendJsonResponse([
                'success' => true,
                'message' => 'Alert status updated successfully'
            ]);
        } else {
            sendJsonResponse([
                'success' => false,
                'message' => 'Alert not found or no changes made'
            ], 404);
        }

    } else {
        sendJsonResponse([
            'success' => false,
            'message' => 'Method not allowed. Use GET or PUT.'
        ], 405);
    }

} catch (PDOException $e) {
    logMessage("Database error in alerts.php: " . $e->getMessage(), 'ERROR');

    sendJsonResponse([
        'success' => false,
        'message' => 'Database error occurred.'
    ], 500);
}
