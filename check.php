<?php
require 'config/config.php';
$pdo = getDbConnection();
$stmt = $pdo->query('SELECT additional_info, timestamp FROM metrics ORDER BY timestamp DESC LIMIT 1');
$row = $stmt->fetch();
$info = json_decode($row['additional_info'], true);
echo "Timestamp: " . $row['timestamp'] . "\n";
print_r($info['storage_smart'] ?? []);
