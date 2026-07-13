<?php
/**
 * Test Client for RAS Dashboard API
 * Simple script to test the metrics endpoint
 */

// Read API key from config
require_once __DIR__ . '/../config/config.php';

// Get API key
$api_key = defined('API_KEY') ? API_KEY : 'test-key';

// Test data
$test_data = [
    'device_id' => 'test-device-' . time(),
    'hostname' => 'test-server',
    'ip_address' => '192.168.1.100',
    'cpu_usage' => rand(30, 95),
    'memory_used' => rand(4000000000, 16000000000),
    'memory_total' => 17179869184,
    'disk_used' => rand(200000000000, 900000000000),
    'disk_total' => 1073741824000,
    'disk_usage' => rand(20, 90),
    'storage_health' => 'healthy',
    'network_status' => 'good'
];

// API endpoint
$api_url = 'http://localhost/RAS/admin/api/metrics.php';

// Send request
$ch = curl_init($api_url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($test_data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-API-Key: ' . $api_key,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Display result
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Test Client</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background: #f5f5f5;
            padding: 2rem;
        }
        .test-container {
            max-width: 800px;
            margin: 0 auto;
        }
        .card {
            margin-bottom: 1rem;
        }
        .status-success {
            color: #4caf50;
        }
        .status-error {
            color: #f44336;
        }
        pre {
            background: #f5f5f5;
            padding: 1rem;
            border-radius: 4px;
            overflow-x: auto;
        }
        .code-block {
            background: #263238;
            color: #aed581;
            padding: 1rem;
            border-radius: 4px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="test-container">
        <h1>RAS Dashboard API Test Client</h1>

        <div class="card">
            <div class="card-content">
                <span class="card-title">Request Sent</span>
                <p><strong>URL:</strong> <?php echo htmlspecialchars($api_url); ?></p>
                <p><strong>Method:</strong> POST</p>
                <p><strong>API Key:</strong> <?php echo htmlspecialchars($api_key); ?></p>
                <p><strong>Data Sent:</strong></p>
                <div class="code-block"><?php echo htmlspecialchars(json_encode($test_data, JSON_PRETTY_PRINT)); ?></div>
            </div>
        </div>

        <div class="card">
            <div class="card-content">
                <span class="card-title">Response</span>
                <p><strong>HTTP Status:</strong>
                    <?php if ($http_code == 200): ?>
                        <span class="status-success"><?php echo $http_code; ?> OK</span>
                    <?php else: ?>
                        <span class="status-error"><?php echo $http_code; ?> Error</span>
                    <?php endif; ?>
                </p>
                <p><strong>Response Body:</strong></p>
                <pre><?php echo htmlspecialchars($response); ?></pre>
            </div>
        </div>

        <div class="card">
            <div class="card-content">
                <span class="card-title">Quick Actions</span>
                <p>
                    <a href="index.php" class="btn waves-effect waves-light">
                        <i class="material-icons left">dashboard</i>
                        Go to Dashboard
                    </a>
                    <button onclick="location.reload()" class="btn waves-effect waves-light">
                        <i class="material-icons left">refresh</i>
                        Send Another Test
                    </button>
                </p>
            </div>
        </div>

        <div class="card">
            <div class="card-content">
                <span class="card-title">Python Agent Example</span>
                <p>Here's how to send data from your Python agent:</p>
                <div class="code-block" style="color: white;">
import requests
import json

api_url = "http://localhost/RAS/admin/api/metrics.php"
api_key = "<?php echo $api_key; ?>"

data = {
    "device_id": "device-001",
    "hostname": "server-01",
    "ip_address": "192.168.1.100",
    "cpu_usage": 45.5,
    "memory_used": 8589934592,
    "memory_total": 17179869184,
    "disk_used": 536870912000,
    "disk_total": 1073741824000,
    "disk_usage": 50.0,
    "storage_health": "healthy",
    "network_status": "good"
}

headers = {
    "X-API-Key": api_key,
    "Content-Type": "application/json"
}

response = requests.post(api_url, json=data, headers=headers)
print(response.json())
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
</body>
</html>
