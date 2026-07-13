<?php
/**
 * Alerts Content Page
 */
try {
    $pdo = getDbConnection();

    // Get alert statistics
    $stats = ['total' => 0, 'open' => 0, 'acknowledged' => 0, 'resolved' => 0];
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM alerts GROUP BY status");
    while ($row = $stmt->fetch()) {
        $stats[$row['status']] = (int)$row['count'];
        $stats['total'] += (int)$row['count'];
    }

    // Get recent alerts
    $stmt = $pdo->query("
        SELECT
            a.*,
            d.hostname,
            d.ip_address
        FROM alerts a
        INNER JOIN devices d ON d.device_id = a.device_id
        ORDER BY a.timestamp DESC
        LIMIT 100
    ");
    $alerts = $stmt->fetchAll();

} catch (PDOException $e) {
    $db_error = $e->getMessage();
}
?>

<!-- Alert Statistics -->
<div class="stats-grid" style="grid-template-columns: repeat(4, 1fr);">
    <div class="stat-card">
        <div class="stat-icon primary">
            <i class="material-icons">notifications</i>
        </div>
        <div class="stat-info">
            <div class="stat-label">Total Alerts</div>
            <div class="stat-value"><?php echo $stats['total']; ?></div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon danger">
            <i class="material-icons">error</i>
        </div>
        <div class="stat-info">
            <div class="stat-label">Open Alerts</div>
            <div class="stat-value"><?php echo $stats['open']; ?></div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon warning">
            <i class="material-icons">pending</i>
        </div>
        <div class="stat-info">
            <div class="stat-label">Acknowledged</div>
            <div class="stat-value"><?php echo $stats['acknowledged']; ?></div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon success">
            <i class="material-icons">check_circle</i>
        </div>
        <div class="stat-info">
            <div class="stat-label">Resolved</div>
            <div class="stat-value"><?php echo $stats['resolved']; ?></div>
        </div>
    </div>
</div>

<!-- Filter Actions -->
<div class="card">
    <div class="card-content">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div class="filter-tabs">
                <button class="filter-tab active" data-filter="all">Semua</button>
                <button class="filter-tab" data-filter="open">Open</button>
                <button class="filter-tab" data-filter="acknowledged">Acknowledged</button>
                <button class="filter-tab" data-filter="resolved">Resolved</button>
                <button class="filter-tab" data-filter="critical">Critical Only</button>
            </div>
            <button class="btn btn-primary" onclick="AdminPanel.loadAlertsData()">
                <i class="material-icons left">refresh</i>
                Refresh
            </button>
        </div>
    </div>
</div>

<!-- Alerts Table -->
<div class="card">
    <div class="card-content">
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Perangkat</th>
                        <th>Tipe Alert</th>
                        <th>Severity</th>
                        <th>Pesan</th>
                        <th>Status</th>
                        <th>Acknowledged</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody id="alerts-table-body">
                    <?php if (empty($alerts)): ?>
                        <tr>
                            <td colspan="8" class="center-align">
                                <div class="empty-state">
                                    <i class="material-icons">notifications_none</i>
                                    <p>Belum ada alert tercatat</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($alerts as $alert): ?>
                            <tr data-status="<?php echo $alert['status']; ?>" data-severity="<?php echo $alert['severity']; ?>">
                                <td>
                                    <div><?php echo date('M j, Y', strtotime($alert['timestamp'])); ?></div>
                                    <small><?php echo date('H:i:s', strtotime($alert['timestamp'])); ?></small>
                                </td>
                                <td>
                                    <div style="font-weight: 500;"><?php echo htmlspecialchars($alert['hostname']); ?></div>
                                    <small style="color: #636e72;"><?php echo htmlspecialchars($alert['ip_address']); ?></small>
                                </td>
                                <td>
                                    <span class="status-badge" style="background: #e3f2fd; color: #0277bd;">
                                        <?php echo strtoupper($alert['alert_type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $alert['severity']; ?>">
                                        <?php echo ucfirst($alert['severity']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="max-width: 300px;"><?php echo htmlspecialchars($alert['message']); ?></div>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $alert['status']; ?>">
                                        <?php echo ucfirst($alert['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($alert['acknowledged_at']): ?>
                                        <div><?php echo date('M j, H:i', strtotime($alert['acknowledged_at'])); ?></div>
                                        <small>by <?php echo htmlspecialchars($alert['acknowledged_by'] ?? 'System'); ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($alert['status'] === 'open'): ?>
                                        <button class="btn btn-sm" onclick="AdminPanel.acknowledgeAlert(<?php echo $alert['id']; ?>)">
                                            <i class="material-icons tiny">check</i> Ack
                                        </button>
                                        <button class="btn btn-sm btn-primary" onclick="AdminPanel.resolveAlert(<?php echo $alert['id']; ?>)">
                                            <i class="material-icons tiny">done_all</i> Resolve
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted">
                                            <?php echo $alert['status'] === 'resolved' ? 'Resolved' : 'Acknowledged'; ?>
                                        </span>
                                    <?php endif; ?>
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
</style>

<script>
// Filter functionality
document.querySelectorAll('.filter-tab').forEach(tab => {
    tab.addEventListener('click', function() {
        document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
        this.classList.add('active');

        const filter = this.dataset.filter;
        const rows = document.querySelectorAll('#alerts-table-body tr');

        rows.forEach(row => {
            let show = false;
            if (filter === 'all') {
                show = true;
            } else if (filter === 'critical') {
                show = row.dataset.severity === 'critical';
            } else {
                show = row.dataset.status === filter;
            }
            row.style.display = show ? '' : 'none';
        });
    });
});
</script>
