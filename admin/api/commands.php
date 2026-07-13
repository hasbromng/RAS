<?php
/**
 * API Endpoint: Commands
 * Handles device command queuing and fetching
 *
 * GET ?action=fetch&device_id=X (Agent fetches pending command)
 * POST action=create, device_id=X, command=Y (Admin queues command)
 * POST action=complete, command_id=Z (Agent marks complete)
 * POST action=check, command_id=Z (Admin checks status)
 */

require_once __DIR__ . '/../../config/config.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = getDbConnection();

    if ($method === 'GET') {
        $action = $_GET['action'] ?? '';
        if ($action === 'fetch') {
            if (!validateApiKey()) {
                sendJsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
            }
            $device_id = $_GET['device_id'] ?? '';
            if (!$device_id) {
                sendJsonResponse(['success' => false, 'message' => 'Missing device_id'], 400);
            }

            $stmt = $pdo->prepare("SELECT id, command FROM device_commands WHERE device_id = ? AND status = 'pending' ORDER BY created_at ASC LIMIT 1");
            $stmt->execute([$device_id]);
            $command = $stmt->fetch();

            sendJsonResponse(['success' => true, 'command' => $command ?: null]);
        } else {
            sendJsonResponse(['success' => false, 'message' => 'Invalid GET action'], 400);
        }
    }
    elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $req_action = $input['action'] ?? '';

        if ($req_action === 'create') {
            $device_id = $input['device_id'] ?? '';
            $command = $input['command'] ?? 'audit';
            
            if (!$device_id) {
                sendJsonResponse(['success' => false, 'message' => 'Missing device_id'], 400);
            }

            // Remove any existing pending commands of the same type to avoid spam
            $stmt = $pdo->prepare("DELETE FROM device_commands WHERE device_id = ? AND command = ? AND status = 'pending'");
            $stmt->execute([$device_id, $command]);

            $stmt = $pdo->prepare("INSERT INTO device_commands (device_id, command, status) VALUES (?, ?, 'pending')");
            $stmt->execute([$device_id, $command]);
            $command_id = $pdo->lastInsertId();

            sendJsonResponse(['success' => true, 'command_id' => $command_id]);
        }
        elseif ($req_action === 'complete') {
            if (!validateApiKey()) {
                sendJsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
            }
            $command_id = $input['command_id'] ?? '';
            $status = $input['status'] ?? 'completed';

            if (!$command_id) {
                sendJsonResponse(['success' => false, 'message' => 'Missing command_id'], 400);
            }

            $stmt = $pdo->prepare("UPDATE device_commands SET status = ?, completed_at = NOW() WHERE id = ?");
            $stmt->execute([$status, $command_id]);

            sendJsonResponse(['success' => true]);
        }
        elseif ($req_action === 'check') {
            $command_id = $input['command_id'] ?? '';
            if (!$command_id) {
                sendJsonResponse(['success' => false, 'message' => 'Missing command_id'], 400);
            }
            
            $stmt = $pdo->prepare("SELECT status FROM device_commands WHERE id = ?");
            $stmt->execute([$command_id]);
            $res = $stmt->fetch();
            
            sendJsonResponse(['success' => true, 'status' => $res ? $res['status'] : null]);
        }
        else {
            sendJsonResponse(['success' => false, 'message' => 'Invalid POST action'], 400);
        }
    }
    else {
        sendJsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
    }
} catch (Exception $e) {
    error_log("Commands API Error: " . $e->getMessage());
    sendJsonResponse(['success' => false, 'message' => 'Server error'], 500);
}
