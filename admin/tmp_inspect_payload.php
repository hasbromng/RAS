<?php
require dirname(__DIR__) . '/config/config.php';
$pdo = getDbConnection();
$stmt = $pdo->query('SELECT d.hostname, m.additional_info FROM devices d LEFT JOIN v_latest_metrics m ON m.device_id = d.device_id LIMIT 2');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo '=== ' . $row['hostname'] . " ===\n";
    $info = json_decode($row['additional_info'] ?? '{}', true) ?: [];
    echo 'keys: ' . implode(', ', array_keys($info)) . "\n";
    if (!empty($info['storage_layout'])) {
        echo 'storage_layout: ' . substr(json_encode($info['storage_layout']), 0, 800) . "\n";
    }
    if (!empty($info['system_details'])) {
        echo 'system_details: ' . json_encode($info['system_details']) . "\n";
    }
    if (!empty($info['all_disks'])) {
        $first = reset($info['all_disks']);
        echo 'disk keys: ' . implode(', ', array_keys(is_array($first) ? $first : [])) . "\n";
        if (!empty($first['physical_disk'])) {
            echo 'physical_disk: ' . json_encode($first['physical_disk']) . "\n";
        }
        echo 'disks: ' . substr(json_encode($info['all_disks']), 0, 1000) . "\n";
    }
    if (!empty($info['network_interfaces'])) {
        $name = array_key_first($info['network_interfaces']);
        $ni = $info['network_interfaces'][$name];
        echo 'net sample ' . $name . ': ' . substr(json_encode($ni), 0, 500) . "\n";
    }
    echo "\n";
}
