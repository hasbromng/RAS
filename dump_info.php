<?php
require 'config/config.php';
$pdo = getDbConnection();
$stmt = $pdo->query('SELECT additional_info FROM metrics ORDER BY timestamp DESC LIMIT 1');
$row = $stmt->fetch();
$info = json_decode($row['additional_info'], true);
print_r(array_keys($info));
echo "\n==== GATEWAY ====\n";
print_r($info['default_gateway'] ?? 'no default_gateway');
echo "\n==== DNS ====\n";
print_r($info['dns_servers'] ?? 'no dns_servers');
