<?php
/**
 * Device Detail Content Page
 * Remote Assistance Support System - Device Detail View (compact single-view)
 */

// Get device ID from URL
$device_id = $_GET['device_id'] ?? $_GET['id'] ?? null;

if (!$device_id) {
    echo '<div class="alert alert-warning">Device ID tidak ditemukan</div>';
    return;
}

try {
    $pdo = getDbConnection();

    // Get device details with latest metrics
    $stmt = $pdo->prepare("
        SELECT
            d.*,
            m.cpu_usage,
            m.memory_used,
            m.memory_total,
            m.disk_used,
            m.disk_total,
            m.disk_usage,
            m.storage_health,
            m.network_status,
            m.timestamp AS last_metric_time,
            (SELECT additional_info FROM metrics m2
             WHERE m2.device_id = d.device_id
             ORDER BY m2.timestamp DESC LIMIT 1) AS additional_info
        FROM devices d
        LEFT JOIN v_latest_metrics m ON m.device_id = d.device_id
        WHERE d.device_id = ?
    ");
    $stmt->execute([$device_id]);
    $device = $stmt->fetch();

    if (!$device) {
        echo '<div class="alert alert-danger">Device tidak ditemukan</div>';
        return;
    }

    // Get recent metrics history
    $stmt = $pdo->prepare("
        SELECT * FROM metrics
        WHERE device_id = ?
        ORDER BY timestamp DESC
        LIMIT 48
    ");
    $stmt->execute([$device_id]);
    $metrics_history = $stmt->fetchAll();

    // Get alerts for this device
    $stmt = $pdo->prepare("
        SELECT * FROM alerts
        WHERE device_id = ?
        ORDER BY timestamp DESC
        LIMIT 20
    ");
    $stmt->execute([$device_id]);
    $alerts = $stmt->fetchAll();

    // Parse additional info
    $additional_info = [];
    if (!empty($device['additional_info'])) {
        $additional_info = json_decode($device['additional_info'], true) ?: [];
    }

} catch (PDOException $e) {
    echo '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    return;
}

// Helper functions
if (!function_exists('formatBytesDetail')) {
    function formatBytesDetail($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max((float)$bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}

if (!function_exists('getMetricColorDetail')) {
    function getMetricColorDetail($value) {
        if ($value >= 90) return '#ef4444';
        if ($value >= 75) return '#f59e0b';
        if ($value >= 50) return '#eab308';
        return '#22c55e';
    }
}

if (!function_exists('getMetricLevelDetail')) {
    function getMetricLevelDetail($value) {
        if ($value >= 90) return 'critical';
        if ($value >= 75) return 'warning';
        if ($value >= 50) return 'moderate';
        return 'good';
    }
}

if (!function_exists('formatRelativeTimeDetail')) {
    function formatRelativeTimeDetail($datetime) {
        if (empty($datetime)) return '—';
        $ts = strtotime($datetime);
        $diff = time() - $ts;
        if ($diff < 60) return $diff . ' dtk lalu';
        if ($diff < 3600) return floor($diff / 60) . ' mnt lalu';
        if ($diff < 86400) return floor($diff / 3600) . ' jam lalu';
        if ($diff < 604800) return floor($diff / 86400) . ' hari lalu';
        return date('d M Y H:i', $ts);
    }
}

if (!function_exists('formatUptimeDetail')) {
    function formatUptimeDetail($seconds) {
        $uptime = floatval($seconds);
        $days = floor($uptime / 86400);
        $hours = floor(($uptime - ($days * 86400)) / 3600);
        $minutes = floor(($uptime - ($days * 86400) - ($hours * 3600)) / 60);
        $parts = [];
        if ($days > 0) $parts[] = $days . 'h';
        if ($hours > 0) $parts[] = $hours . 'j';
        $parts[] = $minutes . 'm';
        return implode(' ', $parts);
    }
}

// Computed values
$cpu = floatval($device['cpu_usage'] ?? 0);
$mem_percent = ($device['memory_total'] > 0)
    ? (($device['memory_used'] / $device['memory_total']) * 100)
    : 0;
$disk_percent = floatval($device['disk_usage'] ?? 0);
$status = $device['status'] ?? 'offline';
$open_alerts = 0;
foreach ($alerts as $a) {
    if (($a['status'] ?? '') === 'open') $open_alerts++;
}

// Active users
$active_users = $additional_info['active_users'] ?? [];
$active_user_str = !empty($active_users) ? implode(', ', array_map('htmlspecialchars', $active_users)) : '—';

// Security info
$security = $additional_info['security'] ?? [];

// GPU info
$gpu_list = $additional_info['gpu'] ?? [];

// Memory slots
$memory_slots = $additional_info['memory_slots'] ?? [];

// Storage SMART
$storage_smart = $additional_info['storage_smart'] ?? [];

// System details (OS)
$sys_details = $additional_info['system_details'] ?? [];

// Chart data (oldest → newest)
$chart_history = array_reverse($metrics_history);
$chart_labels = [];
$chart_cpu = [];
$chart_mem = [];
$chart_disk = [];
foreach ($chart_history as $m) {
    $chart_labels[] = date('H:i', strtotime($m['timestamp']));
    $chart_cpu[] = round(floatval($m['cpu_usage'] ?? 0), 1);
    $mem_t = floatval($m['memory_total'] ?? 0);
    $mem_u = floatval($m['memory_used'] ?? 0);
    $chart_mem[] = $mem_t > 0 ? round(($mem_u / $mem_t) * 100, 1) : 0;
    $chart_disk[] = round(floatval($m['disk_usage'] ?? 0), 1);
}

$status_labels = [
    'online' => 'Online',
    'offline' => 'Offline',
    'warning' => 'Warning',
    'critical' => 'Critical',
];

$storage_raw = strtolower($device['storage_health'] ?? 'unknown');
$network_raw = strtolower($device['network_status'] ?? 'unknown');
$storage_class = in_array($storage_raw, ['healthy', 'good']) ? 'good' : (in_array($storage_raw, ['warning', 'degraded']) ? 'warning' : (in_array($storage_raw, ['critical', 'down']) ? 'critical' : 'unknown'));
$network_class = in_array($network_raw, ['good', 'healthy', 'up']) ? 'good' : (in_array($network_raw, ['degraded', 'warning']) ? 'warning' : (in_array($network_raw, ['down', 'critical']) ? 'critical' : 'unknown'));

$disk_count = !empty($additional_info['all_disks']) ? count($additional_info['all_disks']) : 0;
$net_count = !empty($additional_info['network_interfaces']) ? count($additional_info['network_interfaces']) : 0;
$core_count = !empty($additional_info['cpu_per_core']) && is_array($additional_info['cpu_per_core'])
    ? count($additional_info['cpu_per_core']) : 0;
?>

<link rel="stylesheet" href="assets/css/device-detail.css">

<div class="dd-page">
    <!-- Compact Header -->
    <header class="dd-header dd-status-<?php echo htmlspecialchars($status); ?>">
        <div class="dd-header-left">
            <a href="?page=devices" class="dd-back" title="Kembali ke daftar perangkat">
                <i class="material-icons">arrow_back</i>
            </a>
            <div class="dd-header-icon">
                <i class="material-icons">computer</i>
                <span class="dd-status-dot"></span>
            </div>
            <div class="dd-header-text">
                <div class="dd-header-title-row">
                    <h2 class="dd-hostname"><?php echo htmlspecialchars($device['hostname']); ?></h2>
                    <span class="dd-badge dd-badge-<?php echo htmlspecialchars($status); ?>">
                        <span class="dd-badge-pulse"></span>
                        <?php echo $status_labels[$status] ?? ucfirst($status); ?>
                    </span>
                    <?php if ($open_alerts > 0): ?>
                    <span class="dd-badge dd-badge-alert">
                        <i class="material-icons">notifications_active</i>
                        <?php echo $open_alerts; ?> alert
                    </span>
                    <?php endif; ?>
                </div>
                <div class="dd-header-meta">
                    <span title="IP Address"><i class="material-icons">lan</i><?php echo htmlspecialchars($device['ip_address']); ?></span>
                    <span title="Device ID"><i class="material-icons">fingerprint</i><code><?php echo htmlspecialchars($device['device_id']); ?></code></span>
                    <span title="Terakhir terlihat"><i class="material-icons">schedule</i><?php echo formatRelativeTimeDetail($device['last_seen']); ?></span>
                    <?php if (!empty($additional_info['system'])): ?>
                    <span title="OS"><i class="material-icons">dns</i><?php echo htmlspecialchars($additional_info['system']); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($additional_info['uptime_seconds'])): ?>
                    <span title="Uptime"><i class="material-icons">timer</i><?php echo formatUptimeDetail($additional_info['uptime_seconds']); ?></span>
                    <?php endif; ?>
                    <span title="Pengguna Aktif"><i class="material-icons">person</i><?php echo $active_user_str; ?></span>
                </div>
            </div>
        </div>
        <div class="dd-header-right">
            <div class="dd-header-chip">
                <span class="dd-chip-label">Metrik</span>
                <span class="dd-chip-value"><?php echo $device['last_metric_time'] ? formatRelativeTimeDetail($device['last_metric_time']) : '—'; ?></span>
            </div>
            <div class="dd-header-chip">
                <span class="dd-chip-label">Storage</span>
                <span class="dd-chip-value dd-level-text-<?php echo $storage_class; ?>"><?php echo ucfirst(htmlspecialchars($device['storage_health'] ?? '—')); ?></span>
            </div>
            <div class="dd-header-chip">
                <span class="dd-chip-label">Network</span>
                <span class="dd-chip-value dd-level-text-<?php echo $network_class; ?>"><?php echo ucfirst(htmlspecialchars($device['network_status'] ?? '—')); ?></span>
            </div>
            <button class="dd-export-btn btn-refresh-detail" data-id="<?php echo htmlspecialchars($device['device_id']); ?>" style="margin-right: 8px; background: #e0f2f1; color: #00897b; border: 1px solid #b2dfdb; cursor: pointer;" title="Audit Seketika">
                <i class="material-icons">sync</i>
                <span>Refresh</span>
            </button>
            <a href="?page=devices&device_id=<?php echo urlencode($device_id); ?>&export=csv"
               class="dd-export-btn" title="Ekspor System Snapshot ke CSV" download>
                <i class="material-icons">download</i>
                <span>Ekspor CSV</span>
            </a>
        </div>
    </header>

    <!-- Main dashboard grid — fits one view -->
    <div class="dd-dashboard">

        <!-- LEFT COLUMN: Metrics + Disk + Chart -->
        <div class="dd-col dd-col-main">

            <!-- Resource usage with progress bars -->
            <section class="dd-panel">
                <div class="dd-panel-head">
                    <h3><i class="material-icons">speed</i> Penggunaan Resource</h3>
                </div>
                <div class="dd-panel-body">
                    <div class="dd-metrics">
                        <?php
                        $metrics_rows = [
                            [
                                'key' => 'cpu',
                                'label' => 'CPU',
                                'icon' => 'memory',
                                'value' => $cpu,
                                'detail' => !empty($additional_info['cpu_count_logical'])
                                    ? $additional_info['cpu_count_physical'] . 'P / ' . $additional_info['cpu_count_logical'] . 'L cores'
                                    : 'Prosesor',
                            ],
                            [
                                'key' => 'mem',
                                'label' => 'Memory',
                                'icon' => 'sd_card',
                                'value' => $mem_percent,
                                'detail' => formatBytesDetail($device['memory_used'] ?? 0) . ' / ' . formatBytesDetail($device['memory_total'] ?? 0),
                            ],
                            [
                                'key' => 'disk',
                                'label' => 'Disk',
                                'icon' => 'storage',
                                'value' => $disk_percent,
                                'detail' => formatBytesDetail($device['disk_used'] ?? 0) . ' / ' . formatBytesDetail($device['disk_total'] ?? 0),
                            ],
                        ];
                        foreach ($metrics_rows as $mr):
                            $level = getMetricLevelDetail($mr['value']);
                            $color = getMetricColorDetail($mr['value']);
                        ?>
                        <div class="dd-metric-row">
                            <div class="dd-metric-icon dd-icon-<?php echo $mr['key']; ?>">
                                <i class="material-icons"><?php echo $mr['icon']; ?></i>
                            </div>
                            <div class="dd-metric-body">
                                <div class="dd-metric-top">
                                    <span class="dd-metric-label"><?php echo $mr['label']; ?></span>
                                    <span class="dd-metric-detail"><?php echo htmlspecialchars($mr['detail']); ?></span>
                                    <span class="dd-metric-pct dd-level-text-<?php echo $level; ?>"><?php echo number_format($mr['value'], 1); ?>%</span>
                                </div>
                                <div class="dd-bar">
                                    <div class="dd-bar-fill" style="width: <?php echo min(max($mr['value'], 0), 100); ?>%; background: <?php echo $color; ?>;"></div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                      <!-- Disk partitions — grouped by physical disk -->
            <section class="dd-panel dd-panel-disk">
                <button type="button" class="dd-panel-toggle" data-dd-toggle="disk" aria-expanded="true">
                    <div class="dd-panel-head-inner">
                        <h3><i class="material-icons">folder</i> Disk &amp; Partisi</h3>
                        <span class="dd-panel-count"><?php echo $disk_count; ?> volume</span>
                    </div>
                    <i class="material-icons dd-chevron">expand_less</i>
                </button>
                <div class="dd-panel-collapse" id="dd-collapse-disk">
                    <div class="dd-panel-body">
                        <?php if (!empty($additional_info['all_disks'])): ?>
                        <?php
                        // ── Group partitions by physical disk ────────────────
                        // physical_disk = {disk_number, model, serial_number, drive_letter, size}
                        $phys_groups  = [];   // keyed by disk_number (or '_unknown')
                        $phys_meta    = [];   // meta per group: model, serial, total_size, disk_number

                        foreach ($additional_info['all_disks'] as $disk_key => $disk) {
                            $pd = $disk['physical_disk'] ?? null;
                            if ($pd && isset($pd['disk_number'])) {
                                $gkey = (string)$pd['disk_number'];
                                if (!isset($phys_meta[$gkey])) {
                                    $phys_meta[$gkey] = [
                                        'disk_number' => $pd['disk_number'],
                                        'model'       => $pd['model'] ?? '',
                                        'serial'      => trim($pd['serial_number'] ?? ''),
                                        'total_size'  => 0,
                                    ];
                                }
                                // accumulate total from partitions
                                $phys_meta[$gkey]['total_size'] += floatval($disk['total'] ?? 0);
                            } else {
                                $gkey = '_unknown';
                                if (!isset($phys_meta[$gkey])) {
                                    $phys_meta[$gkey] = [
                                        'disk_number' => null,
                                        'model'       => '',
                                        'serial'      => '',
                                        'total_size'  => 0,
                                    ];
                                }
                                $phys_meta[$gkey]['total_size'] += floatval($disk['total'] ?? 0);
                            }
                            $phys_groups[$gkey][$disk_key] = $disk;
                        }

                        // Sort groups: numbered disks first (by number), _unknown last
                        uksort($phys_groups, function($a, $b) {
                            if ($a === '_unknown') return 1;
                            if ($b === '_unknown') return -1;
                            return (int)$a - (int)$b;
                        });
                        ?>

                        <div class="dd-disk-grouped">
                        <?php foreach ($phys_groups as $gkey => $partitions):
                            $meta       = $phys_meta[$gkey];
                            $disk_num   = $meta['disk_number'];
                            $disk_model = $meta['model'];
                            $disk_serial = $meta['serial'];
                            $disk_total  = $meta['total_size'];
                            $disk_header_id = 'pdisk-' . preg_replace('/[^a-zA-Z0-9]/', '_', $gkey);

                            // Compute total for proportional widths using ACTUAL total (not sum of parts)
                            // Use the largest total partition value or the sum
                            $partition_total = 0;
                            foreach ($partitions as $p) {
                                $partition_total += floatval($p['total'] ?? 0);
                            }
                            if ($partition_total <= 0) $partition_total = 1;
                        ?>
                        <div class="dd-phys-disk-card">
                            <!-- Physical disk header -->
                            <div class="dd-phys-disk-header">
                                <div class="dd-phys-disk-header-left">
                                    <i class="material-icons">storage</i>
                                    <div class="dd-phys-disk-title">
                                        <?php if ($disk_num !== null): ?>
                                        <span class="dd-phys-disk-num">Disk <?php echo (int)$disk_num; ?></span>
                                        <?php else: ?>
                                        <span class="dd-phys-disk-num">Disk</span>
                                        <?php endif; ?>
                                        <?php if ($disk_model): ?>
                                        <span class="dd-phys-disk-model"><?php echo htmlspecialchars($disk_model); ?></span>
                                        <?php endif; ?>
                                        <?php if ($disk_serial): ?>
                                        <span class="dd-phys-disk-serial">S/N: <?php echo htmlspecialchars($disk_serial); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <span class="dd-phys-disk-size"><?php echo formatBytesDetail($disk_total); ?></span>
                            </div>

                            <!-- Partitions — horizontal proportional blocks -->
                            <div class="dd-phys-partition-row">
                                <?php foreach ($partitions as $disk_key => $disk):
                                    $part_letter = strtoupper(str_replace('_', ':', $disk_key));
                                    $part_id     = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string)$disk_key);
                                    $used   = floatval($disk['used']  ?? 0);
                                    $total  = floatval($disk['total'] ?? 0);
                                    $free   = floatval($disk['free']  ?? max($total - $used, 0));
                                    $percent = round($total > 0 ? ($used / $total) * 100 : 0, 1);
                                    $color   = getMetricColorDetail($percent);
                                    $level   = getMetricLevelDetail($percent);
                                    $fstype  = $disk['fstype']    ?? '—';
                                    // Proportional width (% of physical disk total)
                                    $prop_pct = $partition_total > 0 ? round(($total / $partition_total) * 100, 1) : 0;
                                    $prop_pct = max($prop_pct, 8); // minimum 8% so tiny partitions are still visible
                                ?>
                                <div class="dd-partition-block"
                                     style="flex: <?php echo $prop_pct; ?>;"
                                     title="<?php echo htmlspecialchars($part_letter . ' – ' . formatBytesDetail($used) . ' / ' . formatBytesDetail($total)); ?>">
                                     <div class="dd-partition-block-inner" data-dd-detail="disk-<?php echo htmlspecialchars($part_id); ?>">
                                         <div class="dd-partition-top">
                                             <span class="dd-partition-drive"><?php echo htmlspecialchars($part_letter); ?></span>
                                             <span class="dd-partition-fstype"><?php echo htmlspecialchars($fstype); ?></span>
                                         </div>
                                         <div class="dd-partition-size-row">
                                             <span class="dd-partition-free"><?php echo formatBytesDetail($free); ?> bebas</span>
                                             <span class="dd-partition-total dd-muted"><?php echo formatBytesDetail($total); ?></span>
                                         </div>
                                         <div class="dd-partition-usage-bar">
                                             <div class="dd-partition-usage-fill" style="width: <?php echo min($percent, 100); ?>%; background: <?php echo $color; ?>;"></div>
                                         </div>
                                         <div class="dd-partition-pct dd-level-text-<?php echo $level; ?>"><?php echo $percent; ?>%</div>
                                     </div>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Detail popups outside scroll container -->
                            <?php foreach ($partitions as $disk_key => $disk):
                                $part_letter = strtoupper(str_replace('_', ':', $disk_key));
                                $part_id     = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string)$disk_key);
                                $used   = floatval($disk['used']  ?? 0);
                                $total  = floatval($disk['total'] ?? 0);
                                $free   = floatval($disk['free']  ?? max($total - $used, 0));
                                $percent = round($total > 0 ? ($used / $total) * 100 : 0, 1);
                                $level   = getMetricLevelDetail($percent);
                                $fstype  = $disk['fstype']    ?? '—';
                                $devpath = $disk['device']     ?? '—';
                            ?>
                            <div class="dd-detail-pop dd-partition-detail-pop" id="dd-detail-disk-<?php echo htmlspecialchars($part_id); ?>" hidden>
                                <div class="dd-detail-pop-row"><span>Drive</span><strong><?php echo htmlspecialchars($part_letter); ?></strong></div>
                                <div class="dd-detail-pop-row"><span>Filesystem</span><strong><?php echo htmlspecialchars($fstype); ?></strong></div>
                                <div class="dd-detail-pop-row"><span>Device</span><code><?php echo htmlspecialchars($devpath); ?></code></div>
                                <div class="dd-detail-pop-row"><span>Terpakai</span><strong><?php echo formatBytesDetail($used); ?></strong></div>
                                <div class="dd-detail-pop-row"><span>Bebas</span><strong><?php echo formatBytesDetail($free); ?></strong></div>
                                <div class="dd-detail-pop-row"><span>Total</span><strong><?php echo formatBytesDetail($total); ?></strong></div>
                                <div class="dd-detail-pop-row"><span>Penggunaan</span><strong class="dd-level-text-<?php echo $level; ?>"><?php echo $percent; ?>%</strong></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endforeach; ?>
                        </div>

                        <?php else: ?>
                        <div class="dd-empty-sm">
                            <i class="material-icons">storage</i>
                            <span>Data disk detail tidak tersedia</span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
          </section>

            <!-- Performance chart (collapsible) -->
            <section class="dd-panel dd-panel-chart">
                <button type="button" class="dd-panel-toggle" data-dd-toggle="chart" aria-expanded="false">
                    <div class="dd-panel-head-inner">
                        <h3><i class="material-icons">show_chart</i> Tren Performa</h3>
                        <span class="dd-panel-count"><?php echo count($chart_labels); ?> sampel</span>
                    </div>
                    <div class="dd-chart-legend-inline">
                        <span><i style="background:#667eea"></i>CPU</span>
                        <span><i style="background:#764ba2"></i>Mem</span>
                        <span><i style="background:#22c55e"></i>Disk</span>
                    </div>
                    <i class="material-icons dd-chevron">expand_more</i>
                </button>
                <div class="dd-panel-collapse is-collapsed" id="dd-collapse-chart">
                    <div class="dd-panel-body dd-chart-body">
                        <?php if (count($chart_labels) > 0): ?>
                        <canvas id="ddPerfChart" height="90"></canvas>
                        <?php else: ?>
                        <div class="dd-empty-sm">
                            <i class="material-icons">insert_chart</i>
                            <span>Belum ada data metrik</span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </div>

        <!-- RIGHT COLUMN: Network + System + Alerts -->
        <div class="dd-col dd-col-side">

            <!-- Network interfaces -->
            <section class="dd-panel">
                <button type="button" class="dd-panel-toggle" data-dd-toggle="network" aria-expanded="true">
                    <div class="dd-panel-head-inner">
                        <h3><i class="material-icons">router</i> Network</h3>
                        <span class="dd-panel-count"><?php echo $net_count; ?> iface</span>
                    </div>
                    <i class="material-icons dd-chevron">expand_less</i>
                </button>
                <div class="dd-panel-collapse" id="dd-collapse-network">
                    <div class="dd-panel-body dd-panel-body-scroll">
                        <?php if (!empty($additional_info['network_interfaces'])): ?>
                        <div class="dd-net-list">
                            <?php foreach ($additional_info['network_interfaces'] as $iface_name => $iface):
                                $is_up = !empty($iface['isup']);
                                $speed = $iface['speed'] ?? 0;
                                $addresses = [];
                                if (!empty($iface['addresses']) && is_array($iface['addresses'])) {
                                    foreach ($iface['addresses'] as $addr) {
                                        $family = $addr['family'] ?? null;
                                        if ($family !== null) {
                                            $family_int = (int)$family;
                                            if (($family_int === 2 || $family_int === 10 || $family_int === 23 || $family_int === 30 ||
                                                 in_array($family, ['AF_INET', 'AF_INET6', 'AddressFamily.AF_INET', 'AddressFamily.AF_INET6'], true)) &&
                                                !empty($addr['address'])) {
                                                $addresses[] = $addr['address'];
                                            }
                                        }
                                    }
                                }
                                $iface_id = preg_replace('/[^a-zA-Z0-9_-]/', '_', $iface_name);
                            ?>
                            <div class="dd-net-row <?php echo $is_up ? 'is-up' : 'is-down'; ?>">
                                <div class="dd-net-status-dot" title="<?php echo $is_up ? 'UP' : 'DOWN'; ?>"></div>
                                <div class="dd-net-info">
                                    <div class="dd-net-name">
                                        <strong><?php echo htmlspecialchars($iface_name); ?></strong>
                                        <span class="dd-net-speed"><?php echo $speed ? (int)$speed . ' Mbps' : 'N/A'; ?></span>
                                    </div>
                                    <?php if (!empty($addresses)): ?>
                                    <div class="dd-net-ips">
                                        <?php foreach (array_slice($addresses, 0, 2) as $ip): ?>
                                        <code><?php echo htmlspecialchars($ip); ?></code>
                                        <?php endforeach; ?>
                                        <?php if (count($addresses) > 2): ?>
                                        <span class="dd-muted">+<?php echo count($addresses) - 2; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php else: ?>
                                    <div class="dd-net-ips dd-muted">Tidak ada IP</div>
                                    <?php endif; ?>
                                </div>
                                <?php if (count($addresses) > 2 || !empty($iface)): ?>
                                <button type="button" class="dd-detail-btn" data-dd-detail="net-<?php echo htmlspecialchars($iface_id); ?>" title="Detail interface">
                                    <i class="material-icons">info_outline</i>
                                </button>
                                <div class="dd-detail-pop" id="dd-detail-net-<?php echo htmlspecialchars($iface_id); ?>" hidden>
                                    <div class="dd-detail-pop-row"><span>Status</span><strong><?php echo $is_up ? 'UP' : 'DOWN'; ?></strong></div>
                                    <div class="dd-detail-pop-row"><span>Speed</span><strong><?php echo $speed ? (int)$speed . ' Mbps' : 'N/A'; ?></strong></div>
                                    <?php if (!empty($addresses)): ?>
                                    <div class="dd-detail-pop-row dd-detail-pop-stack">
                                        <span>Alamat IP</span>
                                        <?php foreach ($addresses as $ip): ?>
                                        <code><?php echo htmlspecialchars($ip); ?></code>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="dd-empty-sm">
                            <i class="material-icons">router</i>
                            <span>Data network tidak tersedia</span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <!-- System info -->
            <section class="dd-panel">
                <button type="button" class="dd-panel-toggle" data-dd-toggle="system" aria-expanded="false">
                    <div class="dd-panel-head-inner">
                        <h3><i class="material-icons">info</i> Sistem</h3>
                        <?php if ($core_count > 0): ?>
                        <span class="dd-panel-count"><?php echo $core_count; ?> cores</span>
                        <?php endif; ?>
                    </div>
                    <i class="material-icons dd-chevron">expand_more</i>
                </button>
                <div class="dd-panel-collapse is-collapsed" id="dd-collapse-system">
                    <div class="dd-panel-body">
                        <div class="dd-sys-grid">
                            <?php if (!empty($additional_info['system'])): ?>
                            <div class="dd-sys-item">
                                <span class="dd-sys-label">OS</span>
                                <span class="dd-sys-value"><?php echo htmlspecialchars($additional_info['system']); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($additional_info['uptime_seconds'])): ?>
                            <div class="dd-sys-item">
                                <span class="dd-sys-label">Uptime</span>
                                <span class="dd-sys-value"><?php echo formatUptimeDetail($additional_info['uptime_seconds']); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($additional_info['cpu_count_physical'])): ?>
                            <div class="dd-sys-item">
                                <span class="dd-sys-label">CPU</span>
                                <span class="dd-sys-value"><?php echo (int)$additional_info['cpu_count_physical']; ?>P / <?php echo (int)($additional_info['cpu_count_logical'] ?? 0); ?>L</span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($device['memory_total'])): ?>
                            <div class="dd-sys-item">
                                <span class="dd-sys-label">RAM</span>
                                <span class="dd-sys-value"><?php echo formatBytesDetail($device['memory_total']); ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="dd-sys-item">
                                <span class="dd-sys-label">First seen</span>
                                <span class="dd-sys-value"><?php echo date('d M Y H:i', strtotime($device['created_at'])); ?></span>
                            </div>
                            <div class="dd-sys-item">
                                <span class="dd-sys-label">Last seen</span>
                                <span class="dd-sys-value"><?php echo date('d M Y H:i:s', strtotime($device['last_seen'])); ?></span>
                            </div>
                        </div>

                        <?php if (!empty($additional_info['cpu_per_core']) && is_array($additional_info['cpu_per_core'])): ?>
                        <div class="dd-cores">
                            <div class="dd-cores-title">CPU per Core</div>
                            <div class="dd-cores-bars">
                                <?php foreach ($additional_info['cpu_per_core'] as $index => $core_usage):
                                    $core_usage = floatval($core_usage);
                                    $color = getMetricColorDetail($core_usage);
                                ?>
                                <div class="dd-core-row" title="Core <?php echo (int)$index; ?>: <?php echo number_format($core_usage, 1); ?>%">
                                    <span class="dd-core-id">C<?php echo (int)$index; ?></span>
                                    <div class="dd-bar dd-bar-xs">
                                        <div class="dd-bar-fill" style="width: <?php echo min(max($core_usage, 0), 100); ?>%; background: <?php echo $color; ?>;"></div>
                                    </div>
                                    <span class="dd-core-pct"><?php echo number_format($core_usage, 0); ?>%</span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <!-- Hardware Detail: CPU Model, GPU, Memory Slots -->
            <section class="dd-panel">
                <button type="button" class="dd-panel-toggle" data-dd-toggle="hardware" aria-expanded="false">
                    <div class="dd-panel-head-inner">
                        <h3><i class="material-icons">developer_board</i> Hardware</h3>
                        <?php if (!empty($gpu_list)): ?>
                        <span class="dd-panel-count"><?php echo count($gpu_list); ?> GPU</span>
                        <?php endif; ?>
                    </div>
                    <i class="material-icons dd-chevron">expand_more</i>
                </button>
                <div class="dd-panel-collapse is-collapsed" id="dd-collapse-hardware">
                    <div class="dd-panel-body">
                        <div class="dd-sys-grid">
                            <!-- CPU -->
                            <?php if (!empty($additional_info['cpu_model'])): ?>
                            <div class="dd-sys-item dd-sys-item-wide">
                                <span class="dd-sys-label">CPU Model</span>
                                <span class="dd-sys-value"><?php echo htmlspecialchars($additional_info['cpu_model']); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($additional_info['cpu_count_physical'])): ?>
                            <div class="dd-sys-item">
                                <span class="dd-sys-label">Core Fisik</span>
                                <span class="dd-sys-value"><?php echo (int)$additional_info['cpu_count_physical']; ?> Physical / <?php echo (int)($additional_info['cpu_count_logical'] ?? 0); ?> Logical</span>
                            </div>
                            <?php endif; ?>

                            <!-- Motherboard -->
                            <?php if (!empty($sys_details['motherboard_manufacturer'])): ?>
                            <div class="dd-sys-item">
                                <span class="dd-sys-label">Motherboard</span>
                                <span class="dd-sys-value"><?php echo htmlspecialchars($sys_details['motherboard_manufacturer'] . ' ' . ($sys_details['motherboard_product'] ?? '')); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($sys_details['motherboard_serial'])): ?>
                            <div class="dd-sys-item">
                                <span class="dd-sys-label">S/N Motherboard</span>
                                <span class="dd-sys-value"><code><?php echo htmlspecialchars($sys_details['motherboard_serial']); ?></code></span>
                            </div>
                            <?php endif; ?>

                            <!-- BIOS -->
                            <?php if (!empty($sys_details['bios_version'])): ?>
                            <div class="dd-sys-item">
                                <span class="dd-sys-label">BIOS</span>
                                <span class="dd-sys-value"><?php echo htmlspecialchars($sys_details['bios_version']); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($sys_details['bios_date'])): ?>
                            <div class="dd-sys-item">
                                <span class="dd-sys-label">BIOS Date</span>
                                <span class="dd-sys-value"><?php echo htmlspecialchars(substr($sys_details['bios_date'], 0, 10)); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- GPU -->
                        <?php if (!empty($gpu_list)): ?>
                        <div class="dd-hw-section-title">GPU</div>
                        <div class="dd-sys-grid">
                            <?php foreach ($gpu_list as $gpu): ?>
                            <div class="dd-sys-item dd-sys-item-wide">
                                <span class="dd-sys-label"><?php echo htmlspecialchars($gpu['name'] ?? 'GPU'); ?></span>
                                <span class="dd-sys-value">
                                    <?php if (!empty($gpu['vram_bytes']) && $gpu['vram_bytes'] > 0): ?>
                                    <?php echo formatBytesDetail($gpu['vram_bytes']); ?> VRAM
                                    <?php endif; ?>
                                    <?php if (!empty($gpu['driver_version'])): ?>
                                    · Driver <?php echo htmlspecialchars($gpu['driver_version']); ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <!-- Memory Slots -->
                        <?php if (!empty($memory_slots)): ?>
                        <div class="dd-hw-section-title">RAM — Detail Slot (<?php echo count($memory_slots); ?> modul)</div>
                        <div class="dd-mem-slot-list">
                            <?php foreach ($memory_slots as $slot): ?>
                            <div class="dd-mem-slot-row">
                                <div class="dd-mem-slot-icon"><i class="material-icons">memory</i></div>
                                <div class="dd-mem-slot-info">
                                    <span class="dd-mem-slot-label"><?php echo htmlspecialchars($slot['slot'] ?? 'Slot'); ?></span>
                                    <span class="dd-mem-slot-mfr"><?php echo htmlspecialchars($slot['manufacturer'] ?? '—'); ?></span>
                                </div>
                                <div class="dd-mem-slot-spec">
                                    <span class="dd-mem-slot-cap"><?php echo !empty($slot['capacity_bytes']) ? formatBytesDetail($slot['capacity_bytes']) : '—'; ?></span>
                                    <?php if (!empty($slot['speed_mhz'])): ?>
                                    <span class="dd-mem-slot-spd"><?php echo (int)$slot['speed_mhz']; ?> MHz</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <!-- OS Details -->
            <section class="dd-panel">
                <button type="button" class="dd-panel-toggle" data-dd-toggle="os-detail" aria-expanded="false">
                    <div class="dd-panel-head-inner">
                        <h3><i class="material-icons">laptop_windows</i> Sistem Operasi</h3>
                    </div>
                    <i class="material-icons dd-chevron">expand_more</i>
                </button>
                <div class="dd-panel-collapse is-collapsed" id="dd-collapse-os-detail">
                    <div class="dd-panel-body">
                        <div class="dd-sys-grid">
                            <?php if (!empty($additional_info['system'])): ?>
                            <div class="dd-sys-item">
                                <span class="dd-sys-label">OS</span>
                                <span class="dd-sys-value"><?php echo htmlspecialchars($additional_info['system']); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($sys_details['edition'])): ?>
                            <div class="dd-sys-item">
                                <span class="dd-sys-label">Edition</span>
                                <span class="dd-sys-value"><?php echo htmlspecialchars($sys_details['edition']); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($sys_details['display_version'])): ?>
                            <div class="dd-sys-item">
                                <span class="dd-sys-label">Versi</span>
                                <span class="dd-sys-value"><?php echo htmlspecialchars($sys_details['display_version']); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($sys_details['os_build'])): ?>
                            <div class="dd-sys-item">
                                <span class="dd-sys-label">Build</span>
                                <span class="dd-sys-value"><?php echo htmlspecialchars($sys_details['os_build']); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($additional_info['system_release'])): ?>
                            <div class="dd-sys-item">
                                <span class="dd-sys-label">Release</span>
                                <span class="dd-sys-value"><?php echo htmlspecialchars($additional_info['system_release']); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($additional_info['system_machine'])): ?>
                            <div class="dd-sys-item">
                                <span class="dd-sys-label">Arsitektur</span>
                                <span class="dd-sys-value"><?php echo htmlspecialchars($additional_info['system_machine']); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($sys_details['installed_on'])): ?>
                            <div class="dd-sys-item">
                                <span class="dd-sys-label">Tanggal Install</span>
                                <span class="dd-sys-value"><?php echo htmlspecialchars(substr($sys_details['installed_on'], 0, 10)); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($additional_info['uptime_seconds'])): ?>
                            <div class="dd-sys-item">
                                <span class="dd-sys-label">Uptime</span>
                                <span class="dd-sys-value"><?php echo formatUptimeDetail($additional_info['uptime_seconds']); ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="dd-sys-item">
                                <span class="dd-sys-label">First Seen</span>
                                <span class="dd-sys-value"><?php echo date('d M Y H:i', strtotime($device['created_at'])); ?></span>
                            </div>
                            <div class="dd-sys-item">
                                <span class="dd-sys-label">Last Seen</span>
                                <span class="dd-sys-value"><?php echo date('d M Y H:i:s', strtotime($device['last_seen'])); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Security Info -->
            <?php if (!empty($security)): ?>
            <section class="dd-panel">
                <button type="button" class="dd-panel-toggle" data-dd-toggle="security" aria-expanded="false">
                    <div class="dd-panel-head-inner">
                        <h3><i class="material-icons">security</i> Keamanan</h3>
                        <?php
                        $sec_ok = (!empty($security['antivirus_enabled']) && !empty($security['real_time_protection']));
                        $sec_class = $sec_ok ? 'good' : 'warning';
                        ?>
                        <span class="dd-panel-count dd-level-text-<?php echo $sec_class; ?>"><?php echo $sec_ok ? 'Terlindungi' : 'Perhatian'; ?></span>
                    </div>
                    <i class="material-icons dd-chevron">expand_more</i>
                </button>
                <div class="dd-panel-collapse is-collapsed" id="dd-collapse-security">
                    <div class="dd-panel-body">
                        <div class="dd-sys-grid">
                            <?php if (isset($security['firewall_profiles_enabled'])): ?>
                            <div class="dd-sys-item">
                                <span class="dd-sys-label">Firewall Aktif</span>
                                <span class="dd-sys-value dd-level-text-<?php echo intval($security['firewall_profiles_enabled']) > 0 ? 'good' : 'critical'; ?>">
                                    <?php echo intval($security['firewall_profiles_enabled']); ?> profil enabled
                                </span>
                            </div>
                            <?php endif; ?>
                            <?php if (isset($security['antivirus_enabled'])): ?>
                            <div class="dd-sys-item">
                                <span class="dd-sys-label">Antivirus</span>
                                <span class="dd-sys-value dd-level-text-<?php echo $security['antivirus_enabled'] ? 'good' : 'critical'; ?>">
                                    <?php echo $security['antivirus_enabled'] ? 'Aktif' : 'Tidak Aktif'; ?>
                                </span>
                            </div>
                            <?php endif; ?>
                            <?php if (isset($security['real_time_protection'])): ?>
                            <div class="dd-sys-item">
                                <span class="dd-sys-label">Real-Time Protection</span>
                                <span class="dd-sys-value dd-level-text-<?php echo $security['real_time_protection'] ? 'good' : 'critical'; ?>">
                                    <?php echo $security['real_time_protection'] ? 'Aktif' : 'Tidak Aktif'; ?>
                                </span>
                            </div>
                            <?php endif; ?>
                            <?php if (isset($security['antivirus_up_to_date'])): ?>
                            <div class="dd-sys-item">
                                <span class="dd-sys-label">Definisi Virus</span>
                                <span class="dd-sys-value dd-level-text-<?php echo $security['antivirus_up_to_date'] ? 'good' : 'warning'; ?>">
                                    <?php echo $security['antivirus_up_to_date'] ? 'Terbaru' : 'Kadaluarsa'; ?>
                                    <?php if (isset($security['antivirus_signature_age'])): ?>
                                    (<?php echo (int)$security['antivirus_signature_age']; ?> hari)
                                    <?php endif; ?>
                                </span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($security['bitlocker_status'])): ?>
                            <div class="dd-sys-item">
                                <span class="dd-sys-label">BitLocker</span>
                                <span class="dd-sys-value"><?php echo htmlspecialchars($security['bitlocker_status']); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($security['last_windows_update'])): ?>
                            <div class="dd-sys-item">
                                <span class="dd-sys-label">Windows Update Terakhir</span>
                                <span class="dd-sys-value"><?php echo htmlspecialchars(substr($security['last_windows_update'], 0, 10)); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <!-- SMART / Health Storage -->
            <?php if (!empty($storage_smart)): ?>
            <section class="dd-panel">
                <button type="button" class="dd-panel-toggle" data-dd-toggle="smart" aria-expanded="false">
                    <div class="dd-panel-head-inner">
                        <h3><i class="material-icons">health_and_safety</i> SMART / Health Storage</h3>
                        <span class="dd-panel-count"><?php echo count($storage_smart); ?> disk</span>
                    </div>
                    <i class="material-icons dd-chevron">expand_more</i>
                </button>
                <div class="dd-panel-collapse is-collapsed" id="dd-collapse-smart">
                    <div class="dd-panel-body">
                        <?php foreach ($storage_smart as $sd):
                            $health = strtolower($sd['health_status'] ?? 'unknown');
                            $health_class = ($health === 'healthy') ? 'good' : (($health === 'warning') ? 'warning' : (($health === 'unhealthy') ? 'critical' : 'unknown'));
                        ?>
                        <div class="dd-smart-row">
                            <div class="dd-smart-header">
                                <span class="dd-smart-name"><i class="material-icons">storage</i> <?php echo htmlspecialchars($sd['name'] ?? 'Disk'); ?></span>
                                <span class="dd-smart-health dd-level-text-<?php echo $health_class; ?>">
                                    <?php echo htmlspecialchars(ucfirst($sd['health_status'] ?? '—')); ?>
                                </span>
                            </div>
                            <div class="dd-sys-grid dd-smart-grid">
                                <?php if (!empty($sd['media_type'])): ?>
                                <div class="dd-sys-item">
                                    <span class="dd-sys-label">Tipe</span>
                                    <span class="dd-sys-value"><?php echo htmlspecialchars($sd['media_type']); ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($sd['size_bytes'])): ?>
                                <div class="dd-sys-item">
                                    <span class="dd-sys-label">Kapasitas</span>
                                    <span class="dd-sys-value"><?php echo formatBytesDetail($sd['size_bytes']); ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if (isset($sd['temperature_celsius']) && $sd['temperature_celsius'] !== null): ?>
                                <div class="dd-sys-item">
                                    <span class="dd-sys-label">Temperatur</span>
                                    <span class="dd-sys-value dd-level-text-<?php echo intval($sd['temperature_celsius']) >= 60 ? 'critical' : (intval($sd['temperature_celsius']) >= 45 ? 'warning' : 'good'); ?>">
                                        <?php echo (int)$sd['temperature_celsius']; ?>°C
                                    </span>
                                </div>
                                <?php endif; ?>
                                <?php if (isset($sd['wear_percent']) && $sd['wear_percent'] !== null): ?>
                                <div class="dd-sys-item">
                                    <span class="dd-sys-label">Wear</span>
                                    <span class="dd-sys-value"><?php echo (int)$sd['wear_percent']; ?>%</span>
                                </div>
                                <?php endif; ?>
                                <?php if (isset($sd['read_errors_total']) && $sd['read_errors_total'] !== null): ?>
                                <div class="dd-sys-item">
                                    <span class="dd-sys-label">Read Errors</span>
                                    <span class="dd-sys-value <?php echo intval($sd['read_errors_total']) > 0 ? 'dd-level-text-warning' : ''; ?>">
                                        <?php echo (int)$sd['read_errors_total']; ?>
                                    </span>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($sd['operational_status'])): ?>
                                <div class="dd-sys-item">
                                    <span class="dd-sys-label">Status Operasional</span>
                                    <span class="dd-sys-value"><?php echo htmlspecialchars($sd['operational_status']); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <section class="dd-panel dd-panel-alerts">
                <button type="button" class="dd-panel-toggle" data-dd-toggle="alerts" aria-expanded="true">
                    <div class="dd-panel-head-inner">
                        <h3><i class="material-icons">notifications_active</i> Alert</h3>
                        <span class="dd-panel-count">
                            <?php echo count($alerts); ?> total
                            <?php if ($open_alerts > 0): ?>
                            · <strong class="dd-text-warn"><?php echo $open_alerts; ?> open</strong>
                            <?php endif; ?>
                        </span>
                    </div>
                    <i class="material-icons dd-chevron">expand_less</i>
                </button>
                <div class="dd-panel-collapse" id="dd-collapse-alerts">
                    <div class="dd-panel-body dd-panel-body-scroll dd-alerts-body">
                        <?php if (!empty($alerts)): ?>
                        <div class="dd-alert-list">
                            <?php foreach ($alerts as $idx => $alert):
                                $sev = $alert['severity'] ?? 'info';
                                $astatus = $alert['status'] ?? 'open';
                                $msg = $alert['message'] ?? '';
                                $msg_len = function_exists('mb_strlen') ? mb_strlen($msg) : strlen($msg);
                                $msg_short = $msg_len > 80
                                    ? (function_exists('mb_substr') ? mb_substr($msg, 0, 80) : substr($msg, 0, 80)) . '…'
                                    : $msg;
                                $has_more = $msg_len > 80;
                            ?>
                            <div class="dd-alert-row sev-<?php echo htmlspecialchars($sev); ?> status-<?php echo htmlspecialchars($astatus); ?>">
                                <div class="dd-alert-sev" title="<?php echo htmlspecialchars(ucfirst($sev)); ?>"></div>
                                <div class="dd-alert-content">
                                    <div class="dd-alert-top">
                                        <span class="dd-alert-type"><?php echo htmlspecialchars(ucfirst($alert['alert_type'] ?? 'system')); ?></span>
                                        <span class="dd-alert-time"><?php echo formatRelativeTimeDetail($alert['timestamp']); ?></span>
                                        <span class="dd-alert-status status-<?php echo htmlspecialchars($astatus); ?>"><?php echo htmlspecialchars(ucfirst($astatus)); ?></span>
                                    </div>
                                    <p class="dd-alert-msg" data-full="<?php echo htmlspecialchars($msg); ?>">
                                        <?php echo htmlspecialchars($msg_short); ?>
                                    </p>
                                    <?php if ($has_more): ?>
                                    <button type="button" class="dd-alert-more" data-dd-alert-expand="<?php echo (int)$idx; ?>">
                                        Selengkapnya
                                    </button>
                                    <?php endif; ?>
                                    
                                    <?php 
                                    $snapshot = null;
                                    if (!empty($alert['snapshot_data'])) {
                                        $snapshot = json_decode($alert['snapshot_data'], true);
                                    }
                                    if ($snapshot && (empty($snapshot['top_cpu']) === false || empty($snapshot['top_memory']) === false)): 
                                    ?>
                                        <div style="margin-top: 8px;">
                                            <button type="button" class="btn btn-tiny btn-outline" style="background: white; border: 1px solid #cbd5e1; color: #475569; padding: 2px 8px; font-size: 0.75rem; border-radius: 4px; cursor: pointer; display: flex; align-items: center; gap: 4px;" onclick="document.getElementById('snapshot-<?php echo $alert['id']; ?>').style.display = document.getElementById('snapshot-<?php echo $alert['id']; ?>').style.display === 'none' ? 'block' : 'none';">
                                                <i class="material-icons" style="font-size: 14px;">camera_alt</i> Snapshot Proses
                                            </button>
                                            <div id="snapshot-<?php echo $alert['id']; ?>" style="display: none; margin-top: 8px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px;">
                                                <div style="display: flex; gap: 16px;">
                                                    <?php if (!empty($snapshot['top_cpu'])): ?>
                                                    <div style="flex: 1;">
                                                        <strong style="font-size: 0.75rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Top CPU</strong>
                                                        <table style="width: 100%; font-size: 0.8rem; margin-top: 4px; border-collapse: collapse;">
                                                            <?php foreach($snapshot['top_cpu'] as $proc): ?>
                                                                <tr>
                                                                    <td style="padding: 3px 0; color: #334155; border-bottom: 1px solid #f1f5f9;"><?php echo htmlspecialchars($proc['name']); ?></td>
                                                                    <td style="padding: 3px 0; color: #ef4444; border-bottom: 1px solid #f1f5f9; text-align: right; font-family: monospace; font-weight: 600;"><?php echo $proc['cpu_percent']; ?>%</td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </table>
                                                    </div>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (!empty($snapshot['top_memory'])): ?>
                                                    <div style="flex: 1;">
                                                        <strong style="font-size: 0.75rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Top Memory</strong>
                                                        <table style="width: 100%; font-size: 0.8rem; margin-top: 4px; border-collapse: collapse;">
                                                            <?php foreach($snapshot['top_memory'] as $proc): ?>
                                                                <tr>
                                                                    <td style="padding: 3px 0; color: #334155; border-bottom: 1px solid #f1f5f9;"><?php echo htmlspecialchars($proc['name']); ?></td>
                                                                    <td style="padding: 3px 0; color: #3b82f6; border-bottom: 1px solid #f1f5f9; text-align: right; font-family: monospace; font-weight: 600;"><?php echo $proc['memory_percent']; ?>%</td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </table>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="dd-empty-sm success">
                            <i class="material-icons">check_circle</i>
                            <span>Tidak ada alert — sistem normal</span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>

<script>
(function () {
    // Panel collapse toggles
    document.querySelectorAll('[data-dd-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var key = btn.getAttribute('data-dd-toggle');
            var panel = document.getElementById('dd-collapse-' + key);
            if (!panel) return;
            var collapsed = panel.classList.toggle('is-collapsed');
            btn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            var chevron = btn.querySelector('.dd-chevron');
            if (chevron) chevron.textContent = collapsed ? 'expand_more' : 'expand_less';
            // Resize chart when opening
            if (key === 'chart' && !collapsed && window.ddPerfChartInstance) {
                setTimeout(function () { window.ddPerfChartInstance.resize(); }, 50);
            }
        });
    });

    // Detail popovers (open detail per item)
    document.querySelectorAll('[data-dd-detail]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            var id = 'dd-detail-' + btn.getAttribute('data-dd-detail');
            var pop = document.getElementById(id);
            if (!pop) return;
            var wasHidden = pop.hasAttribute('hidden');
            document.querySelectorAll('.dd-detail-pop').forEach(function (p) {
                p.setAttribute('hidden', '');
            });
            document.querySelectorAll('.dd-detail-btn.is-active').forEach(function (b) {
                b.classList.remove('is-active');
            });
            if (wasHidden) {
                pop.removeAttribute('hidden');
                btn.classList.add('is-active');
            }
        });
    });

    document.querySelectorAll('.dd-detail-pop').forEach(function (pop) {
        pop.addEventListener('click', function (e) {
            e.stopPropagation();
        });
    });

    document.addEventListener('click', function () {
        document.querySelectorAll('.dd-detail-pop').forEach(function (p) {
            p.setAttribute('hidden', '');
        });
        document.querySelectorAll('.dd-detail-btn.is-active').forEach(function (b) {
            b.classList.remove('is-active');
        });
    });

    // Alert message expand
    document.querySelectorAll('[data-dd-alert-expand]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var row = btn.closest('.dd-alert-row');
            if (!row) return;
            var msg = row.querySelector('.dd-alert-msg');
            if (!msg) return;
            var full = msg.getAttribute('data-full') || '';
            var expanded = btn.getAttribute('data-expanded') === '1';
            if (expanded) {
                msg.textContent = full.length > 80 ? full.substring(0, 80) + '…' : full;
                btn.textContent = 'Selengkapnya';
                btn.setAttribute('data-expanded', '0');
            } else {
                msg.textContent = full;
                btn.textContent = 'Sembunyikan';
                btn.setAttribute('data-expanded', '1');
            }
        });
    });
})();
</script>

<?php if (count($chart_labels) > 0): ?>
<script>
(function () {
    const ctx = document.getElementById('ddPerfChart');
    if (!ctx || typeof Chart === 'undefined') return;

    const labels = <?php echo json_encode($chart_labels); ?>;
    const cpu = <?php echo json_encode($chart_cpu); ?>;
    const mem = <?php echo json_encode($chart_mem); ?>;
    const disk = <?php echo json_encode($chart_disk); ?>;

    window.ddPerfChartInstance = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'CPU %',
                    data: cpu,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.10)',
                    borderWidth: 2,
                    tension: 0.35,
                    fill: true,
                    pointRadius: labels.length > 24 ? 0 : 2,
                    pointHoverRadius: 4
                },
                {
                    label: 'Memory %',
                    data: mem,
                    borderColor: '#764ba2',
                    backgroundColor: 'rgba(118, 75, 162, 0.06)',
                    borderWidth: 2,
                    tension: 0.35,
                    fill: true,
                    pointRadius: labels.length > 24 ? 0 : 2,
                    pointHoverRadius: 4
                },
                {
                    label: 'Disk %',
                    data: disk,
                    borderColor: '#22c55e',
                    backgroundColor: 'rgba(34, 197, 94, 0.05)',
                    borderWidth: 2,
                    tension: 0.35,
                    fill: true,
                    pointRadius: labels.length > 24 ? 0 : 2,
                    pointHoverRadius: 4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(30, 30, 47, 0.92)',
                    titleFont: { size: 11, weight: '600' },
                    bodyFont: { size: 11 },
                    padding: 10,
                    cornerRadius: 8,
                    callbacks: {
                        label: function (c) {
                            return ' ' + c.dataset.label + ': ' + c.parsed.y + '%';
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 8, color: '#94a3b8', font: { size: 10 } }
                },
                y: {
                    min: 0,
                    max: 100,
                    grid: { color: 'rgba(0,0,0,0.04)' },
                    ticks: {
                        color: '#94a3b8',
                        font: { size: 10 },
                        callback: function (v) { return v + '%'; }
                    }
                }
            }
        }
    });
})();

// Refresh Device (Audit) Logic
document.querySelectorAll('.btn-refresh-detail').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        const deviceId = this.dataset.id;
        const icon = this.querySelector('i');
        const span = this.querySelector('span');
        const originalText = span.textContent;
        const originalIcon = icon.textContent;
        
        icon.textContent = 'hourglass_empty';
        span.textContent = 'Meminta...';
        this.disabled = true;
        this.style.opacity = '0.7';
        
        fetch('api/commands.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'create', device_id: deviceId, command: 'audit' })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success && data.command_id) {
                let pollInterval = setInterval(() => {
                    fetch('api/commands.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'check', command_id: data.command_id })
                    })
                    .then(res => res.json())
                    .then(checkData => {
                        if (checkData.success && checkData.status === 'completed') {
                            clearInterval(pollInterval);
                            location.reload();
                        }
                    });
                }, 2000);
                
                setTimeout(() => {
                    clearInterval(pollInterval);
                    icon.textContent = originalIcon;
                    span.textContent = originalText;
                    this.disabled = false;
                    this.style.opacity = '1';
                    alert('Timeout (45s): Agent mungkin offline atau proses audit memakan waktu lebih lama.');
                }, 45000);
            } else {
                icon.textContent = originalIcon;
                span.textContent = originalText;
                this.disabled = false;
                this.style.opacity = '1';
                alert('Gagal mengirim perintah.');
            }
        })
        .catch(err => {
            console.error(err);
            icon.textContent = originalIcon;
            span.textContent = originalText;
            this.disabled = false;
            this.style.opacity = '1';
        });
    });
});
</script>
<?php endif; ?>
