<?php
/**
 * Device Detail Page (standalone)
 * Menangani ekspor System Snapshot ke CSV.
 * Jika tidak ada parameter export, redirect ke admin panel.
 */

$device_id = $_GET['id'] ?? $_GET['device_id'] ?? null;
$export    = $_GET['export'] ?? null;

if ($export === 'csv' && $device_id) {
    // Load database connection
    $config_file = __DIR__ . '/../../config/database.php';
    if (!file_exists($config_file)) {
        $config_file = __DIR__ . '/../includes/db.php';
    }
    if (file_exists($config_file)) {
        require_once $config_file;
    } else {
        // Fallback: try includes in admin
        foreach (glob(__DIR__ . '/../includes/*.php') as $f) {
            require_once $f;
        }
    }

    try {
        $pdo = getDbConnection();

        $stmt = $pdo->prepare("
            SELECT d.*,
                m.cpu_usage, m.memory_used, m.memory_total,
                m.disk_used, m.disk_total, m.disk_usage,
                m.storage_health, m.network_status,
                m.timestamp AS last_metric_time,
                (SELECT additional_info FROM metrics m2
                 WHERE m2.device_id = d.device_id
                 ORDER BY m2.timestamp DESC LIMIT 1) AS additional_info
            FROM devices d
            LEFT JOIN v_latest_metrics m ON m.device_id = d.device_id
            WHERE d.device_id = ?
        ");
        $stmt->execute([$device_id]);
        $device = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$device) {
            http_response_code(404);
            exit('Device not found');
        }

        $stmt2 = $pdo->prepare("SELECT * FROM alerts WHERE device_id = ? ORDER BY timestamp DESC LIMIT 50");
        $stmt2->execute([$device_id]);
        $alerts = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        $ai = [];
        if (!empty($device['additional_info'])) {
            $ai = json_decode($device['additional_info'], true) ?: [];
        }

        $sd      = $ai['system_details'] ?? [];
        $sec     = $ai['security'] ?? [];
        $gpus    = $ai['gpu'] ?? [];
        $slots   = $ai['memory_slots'] ?? [];
        $smart   = $ai['storage_smart'] ?? [];
        $disks   = $ai['all_disks'] ?? [];
        $ifaces  = $ai['network_interfaces'] ?? [];
        $users   = $ai['active_users'] ?? [];

        $hostname = $device['hostname'] ?? 'device';
        $date_str = date('Y-m-d');
        $filename = "snapshot_{$hostname}_{$date_str}.csv";

        // Set headers for CSV download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        // BOM for Excel UTF-8
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // ── SYSTEM OVERVIEW ──────────────────────────────────────────────
        fputcsv($out, ['=== SYSTEM SNAPSHOT ===', 'Exported: ' . date('Y-m-d H:i:s')]);
        fputcsv($out, []);
        fputcsv($out, ['RINGKASAN PERANGKAT']);
        fputcsv($out, ['Hostname', $device['hostname'] ?? '']);
        fputcsv($out, ['IP Address', $device['ip_address'] ?? '']);
        fputcsv($out, ['Status', $device['status'] ?? '']);
        fputcsv($out, ['Pengguna Aktif', implode(', ', $users)]);
        fputcsv($out, ['Terakhir Terlihat', $device['last_seen'] ?? '']);
        fputcsv($out, ['First Seen', $device['created_at'] ?? '']);

        // ── OPERATING SYSTEM ─────────────────────────────────────────────
        fputcsv($out, []);
        fputcsv($out, ['SISTEM OPERASI']);
        fputcsv($out, ['OS', $ai['system'] ?? '']);
        fputcsv($out, ['Release', $ai['system_release'] ?? '']);
        fputcsv($out, ['Arsitektur', $ai['system_machine'] ?? '']);
        fputcsv($out, ['Edition', $sd['edition'] ?? '']);
        fputcsv($out, ['Display Version', $sd['display_version'] ?? '']);
        fputcsv($out, ['OS Build', $sd['os_build'] ?? '']);
        fputcsv($out, ['Tanggal Install', $sd['installed_on'] ?? '']);
        $uptime_sec = (int)floatval($ai['uptime_seconds'] ?? 0);
        $days = floor($uptime_sec / 86400);
        $hours = floor(($uptime_sec % 86400) / 3600);
        $mins  = floor(($uptime_sec % 3600) / 60);
        fputcsv($out, ['Uptime', "{$days}h {$hours}j {$mins}m"]);

        // ── HARDWARE ─────────────────────────────────────────────────────
        fputcsv($out, []);
        fputcsv($out, ['HARDWARE']);
        fputcsv($out, ['CPU Model', $ai['cpu_model'] ?? '']);
        fputcsv($out, ['CPU Physical Cores', $ai['cpu_count_physical'] ?? '']);
        fputcsv($out, ['CPU Logical Cores', $ai['cpu_count_logical'] ?? '']);
        fputcsv($out, ['CPU Usage (%)', $device['cpu_usage'] ?? '']);
        fputcsv($out, ['RAM Total', $device['memory_total'] ? round($device['memory_total'] / (1024**3), 2) . ' GB' : '']);
        fputcsv($out, ['RAM Used', $device['memory_used'] ? round($device['memory_used'] / (1024**3), 2) . ' GB' : '']);
        fputcsv($out, ['Motherboard', trim(($sd['motherboard_manufacturer'] ?? '') . ' ' . ($sd['motherboard_product'] ?? ''))]);
        fputcsv($out, ['Motherboard S/N', $sd['motherboard_serial'] ?? '']);
        fputcsv($out, ['BIOS Version', $sd['bios_version'] ?? '']);
        fputcsv($out, ['BIOS Date', $sd['bios_date'] ?? '']);

        // GPU
        if (!empty($gpus)) {
            fputcsv($out, []);
            fputcsv($out, ['GPU', 'VRAM', 'Driver']);
            foreach ($gpus as $g) {
                $vram = $g['vram_bytes'] > 0 ? round($g['vram_bytes'] / (1024**3), 2) . ' GB' : '—';
                fputcsv($out, [$g['name'] ?? '', $vram, $g['driver_version'] ?? '']);
            }
        }

        // Memory Slots
        if (!empty($slots)) {
            fputcsv($out, []);
            fputcsv($out, ['SLOT RAM', 'Manufacturer', 'Kapasitas', 'Speed (MHz)']);
            foreach ($slots as $sl) {
                $cap = $sl['capacity_bytes'] > 0 ? round($sl['capacity_bytes'] / (1024**3), 2) . ' GB' : '—';
                fputcsv($out, [$sl['slot'] ?? '', $sl['manufacturer'] ?? '', $cap, $sl['speed_mhz'] ?? '']);
            }
        }

        // ── STORAGE ──────────────────────────────────────────────────────
        fputcsv($out, []);
        fputcsv($out, ['STORAGE — PARTISI']);
        fputcsv($out, ['Drive', 'Filesystem', 'Total', 'Used', 'Free', 'Usage (%)']);
        foreach ($disks as $dk => $di) {
            $fmt = fn($b) => round($b / (1024**3), 2) . ' GB';
            fputcsv($out, [
                strtoupper(str_replace('_', ':', $dk)),
                $di['fstype'] ?? '',
                $fmt($di['total'] ?? 0),
                $fmt($di['used'] ?? 0),
                $fmt($di['free'] ?? 0),
                $di['percent'] ?? '',
            ]);
        }

        // SMART
        if (!empty($smart)) {
            fputcsv($out, []);
            fputcsv($out, ['SMART / HEALTH', 'Tipe', 'Kapasitas', 'Health', 'Temp (°C)', 'Wear (%)', 'Read Errors']);
            foreach ($smart as $sd2) {
                $cap = $sd2['size_bytes'] > 0 ? round($sd2['size_bytes'] / (1024**3), 2) . ' GB' : '—';
                fputcsv($out, [
                    $sd2['name'] ?? '',
                    $sd2['media_type'] ?? '',
                    $cap,
                    $sd2['health_status'] ?? '',
                    $sd2['temperature_celsius'] ?? '—',
                    $sd2['wear_percent'] ?? '—',
                    $sd2['read_errors_total'] ?? '—',
                ]);
            }
        }

        // ── NETWORK ───────────────────────────────────────────────────────
        fputcsv($out, []);
        fputcsv($out, ['NETWORK']);
        fputcsv($out, ['Interface', 'Status', 'Speed (Mbps)', 'Alamat IP']);
        foreach ($ifaces as $name => $iface) {
            $addrs = [];
            foreach ($iface['addresses'] ?? [] as $addr) {
                if (!empty($addr['address'])) $addrs[] = $addr['address'];
            }
            fputcsv($out, [
                $name,
                !empty($iface['isup']) ? 'UP' : 'DOWN',
                $iface['speed'] ?? '',
                implode('; ', $addrs),
            ]);
        }

        // ── SECURITY ──────────────────────────────────────────────────────
        if (!empty($sec)) {
            fputcsv($out, []);
            fputcsv($out, ['KEAMANAN']);
            fputcsv($out, ['Firewall Profiles Enabled', $sec['firewall_profiles_enabled'] ?? '']);
            fputcsv($out, ['Antivirus Enabled', isset($sec['antivirus_enabled']) ? ($sec['antivirus_enabled'] ? 'Ya' : 'Tidak') : '—']);
            fputcsv($out, ['Real-Time Protection', isset($sec['real_time_protection']) ? ($sec['real_time_protection'] ? 'Ya' : 'Tidak') : '—']);
            fputcsv($out, ['Antivirus Up-to-Date', isset($sec['antivirus_up_to_date']) ? ($sec['antivirus_up_to_date'] ? 'Ya' : 'Tidak') : '—']);
            fputcsv($out, ['Antivirus Signature Age (hari)', $sec['antivirus_signature_age'] ?? '—']);
            fputcsv($out, ['BitLocker Status', $sec['bitlocker_status'] ?? '—']);
            fputcsv($out, ['Last Windows Update', $sec['last_windows_update'] ?? '—']);
        }

        // ── ALERTS ────────────────────────────────────────────────────────
        if (!empty($alerts)) {
            fputcsv($out, []);
            fputcsv($out, ['ALERTS']);
            fputcsv($out, ['Waktu', 'Tipe', 'Severity', 'Status', 'Pesan']);
            foreach ($alerts as $al) {
                fputcsv($out, [
                    $al['timestamp'] ?? '',
                    $al['alert_type'] ?? '',
                    $al['severity'] ?? '',
                    $al['status'] ?? '',
                    $al['message'] ?? '',
                ]);
            }
        }

        fclose($out);
        exit;

    } catch (Exception $e) {
        http_response_code(500);
        exit('Error generating CSV: ' . htmlspecialchars($e->getMessage()));
    }
}

// Default: redirect ke admin panel
if ($device_id) {
    header('Location: ../index.php?page=devices&device_id=' . urlencode($device_id));
} else {
    header('Location: ../index.php?page=devices');
}
exit;
