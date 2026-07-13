<?php
/**
 * API Endpoint: Settings
 * Manages system configuration settings
 *
 * GET /admin/api/settings.php - Get all settings
 * PUT /admin/api/settings.php - Update settings
 */

// Include configuration
require_once __DIR__ . '/../../config/config.php';

// Set headers
header('Content-Type: application/json');

try {
    $pdo = getDbConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {

        // Get all settings
        $stmt = $pdo->query("SELECT * FROM settings ORDER BY setting_key");
        $settings = [];

        while ($row = $stmt->fetch()) {
            $value = $row['setting_value'];
            switch ($row['setting_type']) {
                case 'integer':
                    $value = (int) $value;
                    break;
                case 'boolean':
                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                    break;
                case 'json':
                    $value = json_decode($value, true);
                    break;
            }
            $settings[$row['setting_key']] = [
                'value' => $value,
                'type' => $row['setting_type'],
                'description' => $row['description']
            ];
        }

        sendJsonResponse([
            'success' => true,
            'data' => $settings
        ]);

    } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT' || $_SERVER['REQUEST_METHOD'] === 'POST') {

        // Update settings
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (empty($data)) {
            sendJsonResponse([
                'success' => false,
                'message' => 'No data provided'
            ], 400);
        }

        $updated = [];

        foreach ($data as $key => $value) {
            // Skip sensitive keys
            if (in_array($key, ['setting_key', 'setting_type', 'description'])) {
                continue;
            }

            $type = 'string';

            // Determine type
            if (is_bool($value)) {
                $type = 'boolean';
                $value = $value ? 'true' : 'false';
            } elseif (is_int($value)) {
                $type = 'integer';
                $value = (string) $value;
            } elseif (is_array($value)) {
                $type = 'json';
                $value = json_encode($value);
            } else {
                $value = (string) $value;
            }

            if (setSetting($pdo, $key, $value, $type)) {
                $updated[] = $key;
            }
        }

        logMessage("Settings updated: " . implode(', ', $updated), 'INFO');

        sendJsonResponse([
            'success' => true,
            'message' => 'Settings updated successfully',
            'updated' => $updated
        ]);

    } else {
        sendJsonResponse([
            'success' => false,
            'message' => 'Method not allowed. Use GET or PUT.'
        ], 405);
    }

} catch (PDOException $e) {
    logMessage("Database error in settings.php: " . $e->getMessage(), 'ERROR');

    sendJsonResponse([
        'success' => false,
        'message' => 'Database error occurred.'
    ], 500);
}
