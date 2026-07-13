<?php
/**
 * Devices Content Page
 */
try {
    $pdo = getDbConnection();

    // Get all devices with latest metrics
    $stmt = $pdo->query("
        SELECT
            d.*,
            (SELECT COUNT(*) FROM alerts a WHERE a.device_id = d.device_id AND a.status = 'open') AS open_alerts,
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
        ORDER BY d.last_seen DESC
    ");
    $devices = $stmt->fetchAll();

    // Get stats
    $stats = ['total' => count($devices)];
    foreach ($devices as $d) {
        $stats[$d['status']] = ($stats[$d['status']] ?? 0) + 1;
    }

} catch (PDOException $e) {
    $db_error = $e->getMessage();
}
?>

<!-- Page Actions -->
<div class="card">
    <div class="card-content">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h5 style="margin: 0;">Daftar Perangkat</h5>
                <small class="text-muted">Total: <?php echo $stats['total'] ?? 0; ?> perangkat terdaftar</small>
            </div>
            <div>
                <a href="../test_client.php" target="_blank" class="btn btn-primary">
                    <i class="material-icons left">add</i>
                    Test Perangkat Baru
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Filter Tabs -->
<div class="card">
    <div class="card-content">
        <div class="filter-tabs">
            <button class="filter-tab active" data-filter="all">Semua (<?php echo $stats['total'] ?? 0; ?>)</button>
            <button class="filter-tab" data-filter="online">Online (<?php echo $stats['online'] ?? 0; ?>)</button>
            <button class="filter-tab" data-filter="offline">Offline (<?php echo $stats['offline'] ?? 0; ?>)</button>
            <button class="filter-tab" data-filter="warning">Warning (<?php echo $stats['warning'] ?? 0; ?>)</button>
            <button class="filter-tab" data-filter="critical">Critical (<?php echo $stats['critical'] ?? 0; ?>)</button>
        </div>
    </div>
</div>

<!-- Devices Table -->
<div class="card">
    <div class="card-content">
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Hostname / Device ID</th>
                        <th>IP Address</th>
                        <th>Status</th>
                        <th>CPU</th>
                        <th>Memory</th>
                        <th>Disk</th>
                        <th>Storage Health</th>
                        <th>Last Seen</th>
                        <th>Alerts</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody id="devices-table-body">
                    <?php if (empty($devices)): ?>
                        <tr>
                            <td colspan="10" class="center-align">
                                <div class="empty-state">
                                    <i class="material-icons">devices</i>
                                    <p>Belum ada perangkat terdaftar</p>
                                    <small>
                                        Gunakan agent Python untuk mengirim data metrik.<br>
                                        <a href="../test_client.php" target="_blank">Test API Client</a>
                                    </small>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                         <?php foreach ($devices as $device): ?>
                             <tr data-status="<?php echo $device['status']; ?>">
                                 <td>
                                     <div style="font-weight: 500;"><?php echo htmlspecialchars($device['hostname']); ?></div>
                                     <div style="font-size: 0.75rem; color: #636e72;">
                                         ID: <?php echo htmlspecialchars(substr($device['device_id'], 0, 8)); ?>...
                                     </div>
                                 </td>
                                <td>
                                    <span style="font-family: monospace; background: #f5f5f5; padding: 4px 8px; border-radius: 4px;">
                                        <?php echo htmlspecialchars($device['ip_address']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $device['status']; ?>">
                                        <?php echo ucfirst($device['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <div style="flex: 1; max-width: 60px; height: 6px; background: #e0e0e0; border-radius: 3px; overflow: hidden;">
                                            <div style="width: <?php echo min($device['cpu_usage'] ?? 0, 100); ?>%; height: 100%; background: <?php echo getMetricColor($device['cpu_usage'] ?? 0); ?>;"></div>
                                        </div>
                                        <span style="font-size: 0.85rem;"><?php echo number_format($device['cpu_usage'] ?? 0, 1); ?>%</span>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $mem_percent = $device['memory_total'] > 0 ?
                                        (($device['memory_used'] / $device['memory_total']) * 100) : 0;
                                    ?>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <div style="flex: 1; max-width: 60px; height: 6px; background: #e0e0e0; border-radius: 3px; overflow: hidden;">
                                            <div style="width: <?php echo min($mem_percent, 100); ?>%; height: 100%; background: <?php echo getMetricColor($mem_percent); ?>;"></div>
                                        </div>
                                        <span style="font-size: 0.85rem;"><?php echo number_format($mem_percent, 1); ?>%</span>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    // Parse all_disks from additional_info JSON
                                    $all_disks = [];
                                    if (!empty($device['additional_info'])) {
                                        $additional_info = json_decode($device['additional_info'], true);
                                        if (isset($additional_info['all_disks']) && is_array($additional_info['all_disks'])) {
                                            $all_disks = $additional_info['all_disks'];
                                        }
                                    }
                                    
                                    if (!empty($all_disks)):
                                    ?>
                                        <div style="display: flex; align-items: center; gap: 6px; font-size: 0.75rem;">
                                            <?php 
                                            $counter = 0;
                                            foreach ($all_disks as $disk_key => $disk): 
                                                $disk_name = strtoupper(str_replace('_', ':', $disk_key));
                                                $used_gb = number_format(round($disk['used'] / (1024**3), 2), 2);
                                                $free_gb = number_format(round($disk['free'] / (1024**3), 2), 2);
                                                $total_gb = number_format(round($disk['total'] / (1024**3), 2), 2);
                                                $percent = round($disk['percent'], 1);
                                                $color = getMetricColor($percent);
                                                $counter++;
                                                if ($counter > 2) break; // Show max 2 disks in table
                                            ?>
                                                <div style="display: flex; align-items: center; gap: 4px;">
                                                    <span style="width: 30px; text-align: right;"><strong><?php echo $disk_name; ?></strong></span>
                                                    <span style="width: 6px; height: 6px; border-radius: 50%; background: <?php echo $color; ?>;"></span>
                                                    <span style="color: #636e72; font-family: monospace;"><?php echo $percent; ?>%</span>
                                                </div>
                                            <?php endforeach; ?>
                                            <?php if (count($all_disks) > 2): ?>
                                                <span style="color: #636e72; font-style: italic;">+<?php echo count($all_disks) - 2; ?> more</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <div style="flex: 1; max-width: 60px; height: 6px; background: #e0e0e0; border-radius: 3px; overflow: hidden;">
                                                <div style="width: <?php echo min($device['disk_usage'] ?? 0, 100); ?>%; height: 100%; background: <?php echo getMetricColor($device['disk_usage'] ?? 0); ?>;"></div>
                                            </div>
                                            <span style="font-size: 0.85rem;"><?php echo number_format($device['disk_usage'] ?? 0, 1); ?>%</span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge <?php
                                        echo $device['storage_health'] === 'healthy' ? 'online' :
                                            ($device['storage_health'] === 'warning' ? 'warning' : 'critical');
                                    ?>">
                                        <?php echo ucfirst($device['storage_health'] ?? 'Unknown'); ?>
                                    </span>
                                </td>
                                <td>
                                    <small style="color: #636e72; font-size: 0.85rem;"><?php echo date('M j, H:i', strtotime($device['last_seen'])); ?></small>
                                </td>
                                <td>
                                    <?php if ($device['open_alerts'] > 0): ?>
                                        <span class="status-badge critical"><?php echo $device['open_alerts']; ?></span>
                                    <?php else: ?>
                                        <span style="color: #bdc3c7;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="?page=devices&device_id=<?php echo htmlspecialchars($device['device_id']); ?>"
                                       class="btn btn-sm btn-primary" title="Lihat Detail">
                                        <i class="material-icons tiny">visibility</i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.filter-tabs {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.filter-tab {
    padding: 8px 16px;
    border: 1px solid #e0e0e0;
    border-radius: 20px;
    background: white;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 0.875rem;
}

.filter-tab:hover {
    background: #f5f5f5;
}

.filter-tab.active {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-color: transparent;
}

.data-table td {
    vertical-align: middle;
}

.data-table th:nth-child(1),
.data-table td:nth-child(1) {
    width: 20%;
    min-width: 180px;
}

.data-table th:nth-child(2),
.data-table td:nth-child(2) {
    width: 12%;
    min-width: 120px;
    text-align: center;
}

.data-table th:nth-child(3),
.data-table td:nth-child(3) {
    width: 10%;
    min-width: 80px;
    text-align: center;
}

.data-table th:nth-child(4),
.data-table td:nth-child(4),
.data-table th:nth-child(5),
.data-table td:nth-child(5),
.data-table th:nth-child(6),
.data-table td:nth-child(6) {
    width: 18%;
    min-width: 180px;
    text-align: left;
}

.data-table th:nth-child(7),
.data-table td:nth-child(7) {
    width: 10%;
    min-width: 100px;
    text-align: center;
}

.data-table th:nth-child(8),
.data-table td:nth-child(8) {
    width: 12%;
    min-width: 120px;
    text-align: center;
}

.data-table th:nth-child(9),
.data-table td:nth-child(9) {
    width: 8%;
    min-width: 60px;
    text-align: center;
}

.data-table th:nth-child(10),
.data-table td:nth-child(10) {
    width: 8%;
    min-width: 60px;
    text-align: center;
}
</style>

<script>
// Filter functionality
document.querySelectorAll('.filter-tab').forEach(tab => {
    tab.addEventListener('click', function() {
        document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
        this.classList.add('active');

        const filter = this.dataset.filter;
        const rows = document.querySelectorAll('#devices-table-body tr');

        rows.forEach(row => {
            if (filter === 'all' || row.dataset.status === filter) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
});

function getMetricColor(value) {
    if (value >= 90) return '#ff5252';
    if (value >= 75) return '#ffab00';
    if (value >= 50) return '#ffca28';
    return '#00c851';
}
</script>

<?php
function getMetricColor($value) {
    if ($value >= 90) return '#ff5252';
    if ($value >= 75) return '#ffab00';
    if ($value >= 50) return '#ffca28';
    return '#00c851';
}
?>
