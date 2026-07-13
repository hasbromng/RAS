<?php
/**
 * Reports Content Page
 */
try {
    $pdo = getDbConnection();

    // Get devices for dropdown
    $stmt = $pdo->query("SELECT device_id, hostname FROM devices ORDER BY hostname");
    $devices = $stmt->fetchAll();

    // Get report parameters
    $report_type = $_GET['type'] ?? 'daily';
    $device_id = $_GET['device'] ?? 'all';
    $date_from = $_GET['from'] ?? date('Y-m-d', strtotime('-7 days'));
    $date_to = $_GET['to'] ?? date('Y-m-d');

    // Build query for metrics
    $where = ["1=1"];
    $params = [];

    if ($device_id !== 'all') {
        $where[] = "device_id = ?";
        $params[] = $device_id;
    }

    $where[] = "timestamp >= ? AND timestamp <= ?";
    $params[] = $date_from . ' 00:00:00';
    $params[] = $date_to . ' 23:59:59';

    $where_clause = implode(' AND ', $where);

    // Get metrics
    $stmt = $pdo->prepare("
        SELECT * FROM metrics
        WHERE {$where_clause}
        ORDER BY timestamp ASC
    ");
    $stmt->execute($params);
    $metrics = $stmt->fetchAll();

    // Get alerts
    $stmt = $pdo->prepare("
        SELECT a.*, d.hostname
        FROM alerts a
        INNER JOIN devices d ON d.device_id = a.device_id
        WHERE a.timestamp >= ? AND a.timestamp <= ?
        " . ($device_id !== 'all' ? "AND a.device_id = ?" : "") . "
        ORDER BY a.timestamp ASC
    ");
    $alert_params = [$date_from . ' 00:00:00', $date_to . ' 23:59:59'];
    if ($device_id !== 'all') {
        $alert_params[] = $device_id;
    }
    $stmt->execute($alert_params);
    $alerts = $stmt->fetchAll();

    // Calculate statistics
    $stats = [
        'metrics_count' => count($metrics),
        'alerts_count' => count($alerts),
        'critical_alerts' => 0,
        'avg_cpu' => 0,
        'avg_memory' => 0,
        'avg_disk' => 0
    ];

    if (!empty($metrics)) {
        $cpu_values = array_column($metrics, 'cpu_usage');
        $disk_values = array_column($metrics, 'disk_usage');
        $mem_values = [];

        foreach ($metrics as $m) {
            if ($m['memory_total'] > 0) {
                $mem_values[] = ($m['memory_used'] / $m['memory_total']) * 100;
            }
        }

        $stats['avg_cpu'] = array_sum($cpu_values) / count($cpu_values);
        $stats['avg_memory'] = !empty($mem_values) ? array_sum($mem_values) / count($mem_values) : 0;
        $stats['avg_disk'] = array_sum($disk_values) / count($disk_values);
    }

    foreach ($alerts as $a) {
        if ($a['severity'] === 'critical') {
            $stats['critical_alerts']++;
        }
    }

} catch (PDOException $e) {
    $db_error = $e->getMessage();
}
?>

<!-- Report Generator -->
<div class="card">
    <div class="card-content">
        <h5 class="card-title">
            <i class="material-icons">assessment</i>
            Generator Laporan
        </h5>
        <form method="GET" action="">
            <input type="hidden" name="page" value="reports">
            <div class="row" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: end;">
                <div style="flex: 1; min-width: 200px;">
                    <label class="form-group" style="margin: 0;">Tipe Laporan</label>
                    <select name="type" class="form-control">
                        <option value="daily" <?php echo $report_type === 'daily' ? 'selected' : ''; ?>>Harian</option>
                        <option value="weekly" <?php echo $report_type === 'weekly' ? 'selected' : ''; ?>>Mingguan</option>
                        <option value="monthly" <?php echo $report_type === 'monthly' ? 'selected' : ''; ?>>Bulanan</option>
                        <option value="custom" <?php echo $report_type === 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                    </select>
                </div>
                <div style="flex: 1; min-width: 200px;">
                    <label class="form-group" style="margin: 0;">Perangkat</label>
                    <select name="device" class="form-control">
                        <option value="all">Semua Perangkat</option>
                        <?php foreach ($devices ?? [] as $dev): ?>
                            <option value="<?php echo $dev['device_id']; ?>"
                                <?php echo $device_id === $dev['device_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dev['hostname']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="flex: 1; min-width: 200px;">
                    <label class="form-group" style="margin: 0;">Dari Tanggal</label>
                    <input type="date" name="from" value="<?php echo htmlspecialchars($date_from); ?>" class="form-control">
                </div>
                <div style="flex: 1; min-width: 200px;">
                    <label class="form-group" style="margin: 0;">Sampai Tanggal</label>
                    <input type="date" name="to" value="<?php echo htmlspecialchars($date_to); ?>" class="form-control">
                </div>
                <div>
                    <button type="submit" class="btn btn-primary">
                        <i class="material-icons left">filter_list</i>
                        Generate
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Export Button -->
<div style="text-align: right; margin-bottom: 1rem;">
    <a href="../pages/reports.php?type=<?php echo $report_type; ?>&device=<?php echo $device_id; ?>&from=<?php echo $date_from; ?>&to=<?php echo $date_to; ?>&export=csv"
       class="btn" style="background: linear-gradient(135deg, #00c851 0%, #00e676 100%);">
        <i class="material-icons left">download</i>
        Export CSV
    </a>
</div>

<!-- Statistics Summary -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon primary">
            <i class="material-icons">show_chart</i>
        </div>
        <div class="stat-info">
            <div class="stat-label">Total Metrics</div>
            <div class="stat-value"><?php echo number_format($stats['metrics_count']); ?></div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon danger">
            <i class="material-icons">error</i>
        </div>
        <div class="stat-info">
            <div class="stat-label">Total Alerts</div>
            <div class="stat-value"><?php echo number_format($stats['alerts_count']); ?></div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon warning">
            <i class="material-icons">warning</i>
        </div>
        <div class="stat-info">
            <div class="stat-label">Critical Alerts</div>
            <div class="stat-value"><?php echo number_format($stats['critical_alerts']); ?></div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon success">
            <i class="material-icons">trending_up</i>
        </div>
        <div class="stat-info">
            <div class="stat-label">Avg CPU</div>
            <div class="stat-value"><?php echo number_format($stats['avg_cpu'], 1); ?>%</div>
        </div>
    </div>
</div>

<!-- Performance Chart -->
<div class="card">
    <div class="card-content">
        <h5 class="card-title">
            <i class="material-icons">timeline</i>
            Performa Sumber Daya
        </h5>
        <div style="height: 400px;">
            <canvas id="performanceChart"></canvas>
        </div>
    </div>
</div>

<!-- Alerts in Period -->
<div class="card">
    <div class="card-content">
        <h5 class="card-title">
            <i class="material-icons">list</i>
            Alerts dalam Periode Ini
        </h5>
        <?php if (empty($alerts)): ?>
            <div class="empty-state">
                <i class="material-icons">check_circle</i>
                <p>Tidak ada alert dalam periode ini</p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Perangkat</th>
                            <th>Tipe</th>
                            <th>Severity</th>
                            <th>Pesan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($alerts, 0, 50) as $alert): ?>
                            <tr>
                                <td><?php echo date('M j, H:i', strtotime($alert['timestamp'])); ?></td>
                                <td><?php echo htmlspecialchars($alert['hostname']); ?></td>
                                <td><?php echo strtoupper($alert['alert_type']); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $alert['severity']; ?>">
                                        <?php echo ucfirst($alert['severity']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($alert['message']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Performance chart
const ctx = document.getElementById('performanceChart');
if (ctx && <?php echo !empty($metrics) ? 'true' : 'false'; ?>) {
    const metricsData = <?php echo json_encode($metrics); ?>;

    // Sample data for chart (take every 10th point to avoid overcrowding)
    const sampledData = metricsData.filter((_, i) => i % 10 === 0);

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: sampledData.map(m => new Date(m.timestamp).toLocaleString('id-ID')),
            datasets: [
                {
                    label: 'CPU %',
                    data: sampledData.map(m => m.cpu_usage),
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.4,
                    pointRadius: 0
                },
                {
                    label: 'Memory %',
                    data: sampledData.map(m => ((m.memory_used / m.memory_total) * 100).toFixed(2)),
                    borderColor: '#00c851',
                    backgroundColor: 'rgba(0, 200, 81, 0.1)',
                    tension: 0.4,
                    pointRadius: 0
                },
                {
                    label: 'Disk %',
                    data: sampledData.map(m => m.disk_usage),
                    borderColor: '#ffab00',
                    backgroundColor: 'rgba(255, 171, 0, 0.1)',
                    tension: 0.4,
                    pointRadius: 0
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                },
                tooltip: {
                    mode: 'index',
                    intersect: false
                }
            },
            scales: {
                x: {
                    ticks: {
                        maxTicksLimit: 10
                    }
                },
                y: {
                    beginAtZero: true,
                    max: 100
                }
            }
        }
    });
}
</script>
