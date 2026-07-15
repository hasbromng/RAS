<?php
/**
 * Dashboard Content Page
 */
try {
    $pdo = getDbConnection();

    // --- FIX: Paksa update status offline sebelum query stats.
    // Gunakan NOW() MySQL murni (bukan date() PHP) untuk menghindari timezone mismatch.
    $offline_minutes = getSetting($pdo, 'device_offline_minutes', 5);
    $pdo->prepare("
        UPDATE devices
        SET status = 'offline'
        WHERE last_seen < DATE_SUB(NOW(), INTERVAL ? MINUTE)
        AND status != 'offline'
    ")->execute([$offline_minutes]);
    // --- END FIX

    // Get detailed stats (setelah offline update, angka ini sudah akurat)
    $device_stats = ['total' => 0, 'online' => 0, 'offline' => 0, 'warning' => 0, 'critical' => 0];
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM devices GROUP BY status");
    while ($row = $stmt->fetch()) {
        $device_stats[$row['status']] = (int)$row['count'];
        $device_stats['total'] += (int)$row['count'];
    }

    $alert_stats = ['open' => 0, 'critical' => 0, 'warning' => 0, 'resolved' => 0];
    $stmt = $pdo->query("SELECT severity, status, COUNT(*) as count FROM alerts GROUP BY severity, status");
    while ($row = $stmt->fetch()) {
        $alert_stats[$row['status']] = ($alert_stats[$row['status']] ?? 0) + (int)$row['count'];
        $alert_stats[$row['severity']] = ($alert_stats[$row['severity']] ?? 0) + (int)$row['count'];
    }

    // Get average metrics
    $avg_metrics = $pdo->query("
        SELECT
            AVG(cpu_usage) as avg_cpu,
            AVG(disk_usage) as avg_disk,
            AVG(memory_used / memory_total * 100) as avg_memory
        FROM v_latest_metrics
    ")->fetch();

    // Get recent devices
    $stmt = $pdo->query("
        SELECT
            d.device_id,
            d.hostname,
            d.ip_address,
            d.status,
            d.last_seen,
            m.cpu_usage,
            m.memory_used,
            m.memory_total,
            m.disk_used,
            m.disk_total,
            m.disk_usage,
            (SELECT COUNT(*) FROM alerts a WHERE a.device_id = d.device_id AND a.status = 'open') as open_alerts
        FROM devices d
        LEFT JOIN v_latest_metrics m ON m.device_id = d.device_id
        ORDER BY d.last_seen DESC
        LIMIT 10
    ");
    $recent_devices = $stmt->fetchAll();

    // Get critical alerts
    $stmt = $pdo->query("
        SELECT
            a.id,
            a.device_id,
            a.alert_type,
            a.severity,
            a.message,
            a.timestamp,
            a.status,
            d.hostname,
            d.ip_address
        FROM alerts a
        INNER JOIN devices d ON d.device_id = a.device_id
        WHERE a.status = 'open'
        ORDER BY a.severity DESC, a.timestamp DESC
        LIMIT 10
    ");
    $critical_alerts = $stmt->fetchAll();

} catch (PDOException $e) {
    $db_error = $e->getMessage();
}
?>

<!-- Stats Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon primary">
            <i class="material-icons">devices</i>
        </div>
        <div class="stat-info">
            <div class="stat-label">Total Perangkat</div>
            <div class="stat-value" id="total-devices"><?php echo $device_stats['total']; ?></div>
            <div class="stat-change">Terdaftar</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon success">
            <i class="material-icons">check_circle</i>
        </div>
        <div class="stat-info">
            <div class="stat-label">Perangkat Online</div>
            <div class="stat-value" id="online-devices"><?php echo $device_stats['online']; ?></div>
            <div class="stat-change positive">
                <i class="material-icons tiny">trending_up</i>
                Aktif
            </div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon warning">
            <i class="material-icons">warning</i>
        </div>
        <div class="stat-info">
            <div class="stat-label">Perangkat Warning</div>
            <div class="stat-value"><?php echo $device_stats['warning']; ?></div>
            <div class="stat-change">Perlu perhatian</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon danger">
            <i class="material-icons">error</i>
        </div>
        <div class="stat-info">
            <div class="stat-label">Alert Kritis</div>
            <div class="stat-value" id="critical-alerts"><?php echo $alert_stats['critical'] ?? 0; ?></div>
            <div class="stat-change negative">Segera tindakan</div>
        </div>
    </div>
</div>

<!-- Charts Section -->
<div class="row">
    <div class="col s12 m6">
        <div class="chart-container">
            <div class="chart-header">
                <h5 class="chart-title">Rata-rata Penggunaan Sumber Daya</h5>
            </div>
            <div class="chart-canvas">
                <canvas id="metricsChart"></canvas>
            </div>
        </div>
    </div>

    <div class="col s12 m6">
        <div class="chart-container">
            <div class="chart-header">
                <h5 class="chart-title">Distribusi Status Perangkat</h5>
            </div>
            <div class="chart-canvas">
                <canvas id="statusChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Recent Devices Table -->
<div class="card">
    <div class="card-content">
        <div class="card-title">
            <i class="material-icons">recent_actors</i>
            Perangkat Terakhir Aktif
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Perangkat</th>
                        <th>Status</th>
                        <th>CPU</th>
                        <th>Memory</th>
                        <th>Disk</th>
                        <th>Alerts</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody id="recent-devices-body">
                    <?php if (empty($recent_devices)): ?>
                        <tr>
                            <td colspan="7" class="center-align">
                                <div class="empty-state">
                                    <i class="material-icons">devices</i>
                                    <p>Belum ada perangkat terdaftar</p>
                                    <small>Gunakan agent Python untuk mengirim data metrik</small>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recent_devices as $device): ?>
                            <tr>
                                <td>
                                    <div class="table-cell-title"><?php echo htmlspecialchars($device['hostname']); ?></div>
                                    <div class="table-cell-subtitle">
                                        <?php echo htmlspecialchars($device['device_id']); ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $device['status']; ?>">
                                        <?php echo $device['status']; ?>
                                    </span>
                                </td>
                                <td><?php echo number_format($device['cpu_usage'] ?? 0, 1); ?>%</td>
                                <td>
                                    <?php
                                    $mem_percent = $device['memory_total'] > 0 ?
                                        (($device['memory_used'] / $device['memory_total']) * 100) : 0;
                                    echo number_format($mem_percent, 1) . '%';
                                    ?>
                                </td>
                                <td><?php echo number_format($device['disk_usage'] ?? 0, 1); ?>%</td>
                                <td>
                                    <?php if ($device['open_alerts'] > 0): ?>
                                        <span class="status-badge critical"><?php echo $device['open_alerts']; ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="?page=devices&device_id=<?php echo urlencode($device['device_id']); ?>"
                                       class="btn btn-sm btn-primary">
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

<!-- Critical Alerts -->
<div class="card">
    <div class="card-content">
        <div class="card-title">
            <i class="material-icons">notifications_active</i>
            Alert Aktif
        </div>
        <div id="alerts-list">
            <?php if (empty($critical_alerts)): ?>
                <div class="empty-state">
                    <i class="material-icons">check_circle</i>
                    <p>Tidak ada alert aktif saat ini</p>
                </div>
            <?php else: ?>
                <?php foreach ($critical_alerts as $alert): ?>
                    <div class="alert-item alert-<?php echo $alert['severity']; ?>">
                        <div class="alert-header">
                            <div>
                                <strong><?php echo htmlspecialchars($alert['hostname']); ?></strong>
                                <span class="status-badge <?php echo $alert['severity']; ?>">
                                    <?php echo strtoupper($alert['alert_type']); ?>
                                </span>
                            </div>
                            <small><?php echo date('M j, H:i', strtotime($alert['timestamp'])); ?></small>
                        </div>
                        <div class="alert-message"><?php echo htmlspecialchars($alert['message']); ?></div>
                        <div class="alert-actions">
                            <button class="btn btn-sm" onclick="AdminPanel.acknowledgeAlert(<?php echo $alert['id']; ?>)">
                                <i class="material-icons tiny">check</i> Acknowledge
                            </button>
                            <a href="?page=devices&device_id=<?php echo urlencode($alert['device_id']); ?>"
                               class="btn btn-sm btn-primary">
                                <i class="material-icons tiny">visibility</i> Lihat Perangkat
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Status distribution chart
const statusCtx = document.getElementById('statusChart');
if (statusCtx) {
    const uiStyles = getComputedStyle(document.body);
    const chartTextColor = uiStyles.getPropertyValue('--text-secondary').trim() || '#64748b';
    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: ['Online', 'Offline', 'Warning', 'Critical'],
            datasets: [{
                data: [
                    <?php echo $device_stats['online']; ?>,
                    <?php echo $device_stats['offline']; ?>,
                    <?php echo $device_stats['warning']; ?>,
                    <?php echo $device_stats['critical']; ?>
                ],
                backgroundColor: ['#00c851', '#33b5e5', '#ffab00', '#ff5252']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: chartTextColor
                    }
                }
            }
        }
    });
}
</script>
