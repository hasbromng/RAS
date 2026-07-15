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
        LIMIT 300
    ");
    $alerts = $stmt->fetchAll();

    // Group alerts by device
    $grouped_alerts = [];
    foreach ($alerts as $alert) {
        $dev_id = $alert['device_id'];
        if (!isset($grouped_alerts[$dev_id])) {
            $grouped_alerts[$dev_id] = [
                'device_id' => $dev_id,
                'hostname' => $alert['hostname'],
                'ip_address' => $alert['ip_address'],
                'total' => 0,
                'open' => 0,
                'worst_severity' => 'info',
                'alerts' => []
            ];
        }
        
        $grouped_alerts[$dev_id]['total']++;
        if ($alert['status'] === 'open') {
            $grouped_alerts[$dev_id]['open']++;
        }
        
        // Update worst severity
        $severities = ['info' => 1, 'warning' => 2, 'critical' => 3];
        $current_worst = $severities[$grouped_alerts[$dev_id]['worst_severity']];
        $this_sev = $severities[$alert['severity']] ?? 1;
        if ($this_sev > $current_worst && $alert['status'] === 'open') {
            $grouped_alerts[$dev_id]['worst_severity'] = $alert['severity'];
        }
        
        $grouped_alerts[$dev_id]['alerts'][] = $alert;
    }

} catch (PDOException $e) {
    $db_error = $e->getMessage();
}

function getAlertSeverityColor($severity) {
    switch ($severity) {
        case 'critical':
            return '#e74c3c';
        case 'warning':
            return '#f39c12';
        default:
            return '#3498db';
    }
}
?>

<!-- Alert Statistics -->
<div class="stats-grid alerts-stats-grid">
    <div class="stat-card alerts-stat-card">
        <div class="stat-icon primary alerts-stat-icon">
            <i class="material-icons">notifications</i>
        </div>
        <div class="stat-info alerts-stat-info">
            <div class="stat-label alerts-stat-label">Total Alerts</div>
            <div class="stat-value alerts-stat-value"><?php echo $stats['total']; ?></div>
        </div>
    </div>

    <div class="stat-card alerts-stat-card">
        <div class="stat-icon danger alerts-stat-icon">
            <i class="material-icons">error</i>
        </div>
        <div class="stat-info alerts-stat-info">
            <div class="stat-label alerts-stat-label">Open Alerts</div>
            <div class="stat-value alerts-stat-value"><?php echo $stats['open']; ?></div>
        </div>
    </div>

    <div class="stat-card alerts-stat-card">
        <div class="stat-icon warning alerts-stat-icon">
            <i class="material-icons">pending</i>
        </div>
        <div class="stat-info alerts-stat-info">
            <div class="stat-label alerts-stat-label">Acknowledged</div>
            <div class="stat-value alerts-stat-value"><?php echo $stats['acknowledged']; ?></div>
        </div>
    </div>

    <div class="stat-card alerts-stat-card">
        <div class="stat-icon success alerts-stat-icon">
            <i class="material-icons">check_circle</i>
        </div>
        <div class="stat-info alerts-stat-info">
            <div class="stat-label alerts-stat-label">Resolved</div>
            <div class="stat-value alerts-stat-value"><?php echo $stats['resolved']; ?></div>
        </div>
    </div>
</div>

<!-- Mode Switch & Refresh -->
<div class="card alerts-toolbar-card">
    <div class="card-content alerts-toolbar-content">
        <div class="page-toolbar alerts-toolbar">
            <div class="filter-tabs">
                <button class="filter-tab active filter-tab-sm" onclick="switchMode('group')" id="btn-mode-group">
                    <i class="material-icons left">view_agenda</i> Group by Device
                </button>
                <button class="filter-tab filter-tab-sm" onclick="switchMode('list')" id="btn-mode-list">
                    <i class="material-icons left">view_list</i> Flat List
                </button>
            </div>
            <button class="btn btn-primary btn-sm alerts-refresh-btn" onclick="AdminPanel.loadAlertsData()">
                <i class="material-icons left">refresh</i>
                Refresh
            </button>
        </div>
    </div>
</div>

<!-- Group Mode View -->
<div id="view-group" style="display: block;">
    <?php if (empty($grouped_alerts)): ?>
        <div class="card"><div class="card-content center-align"><div class="empty-state"><i class="material-icons">notifications_none</i><p>Belum ada alert tercatat</p></div></div></div>
    <?php else: ?>
        <?php foreach ($grouped_alerts as $dev_id => $group): ?>
            <div class="card alerts-device-card" style="--alert-group-accent: <?php echo getAlertSeverityColor($group['worst_severity']); ?>;">
                <div class="card-content alerts-device-head" onclick="toggleDeviceAlerts('<?php echo htmlspecialchars($dev_id); ?>')">
                    <div class="alerts-device-main">
                        <div>
                            <i class="material-icons alerts-device-icon">computer</i>
                        </div>
                        <div>
                            <div class="alerts-device-title"><?php echo htmlspecialchars($group['hostname']); ?></div>
                            <div class="alerts-device-subtitle"><?php echo htmlspecialchars($group['ip_address']); ?></div>
                        </div>
                    </div>
                    <div class="alerts-device-meta">
                        <div class="alerts-device-counts">
                            <div class="alerts-device-open <?php echo $group['open'] > 0 ? 'has-open' : ''; ?>"><?php echo $group['open']; ?> Open Alerts</div>
                            <div class="alerts-device-total"><?php echo $group['total']; ?> Total</div>
                        </div>
                        <i class="material-icons alerts-device-chevron" id="icon-<?php echo htmlspecialchars($dev_id); ?>">expand_more</i>
                    </div>
                </div>
                
                <div id="alerts-<?php echo htmlspecialchars($dev_id); ?>" class="alerts-device-body" style="display: none;">
                    <!-- List alerts for this device -->
                    <?php renderAlertsTable($group['alerts'], false, 'group'); ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- List Mode View -->
<div id="view-list" style="display: none;">
    <div class="card">
        <div class="card-content alerts-list-content">
            <!-- Filter Actions for List -->
            <div class="alerts-list-filters">
                <div class="filter-tabs" id="list-filters">
                    <button class="filter-tab active" data-filter="all">Semua</button>
                    <button class="filter-tab" data-filter="open">Open</button>
                    <button class="filter-tab" data-filter="acknowledged">Acknowledged</button>
                    <button class="filter-tab" data-filter="resolved">Resolved</button>
                    <button class="filter-tab" data-filter="critical">Critical Only</button>
                </div>
            </div>
            
            <div class="table-container">
                <?php renderAlertsTable($alerts, true, 'list'); ?>
            </div>
        </div>
    </div>
</div>

<?php
function renderAlertsTable($alertsList, $showDevice = false, $idPrefix = 'list') {
    if (empty($alertsList)) {
        echo '<div class="empty-state"><p>Belum ada alert</p></div>';
        return;
    }
    ?>
    <table class="data-table alerts-table">
        <thead>
            <tr>
                <th>Waktu</th>
                <?php if ($showDevice): ?><th>Perangkat</th><?php endif; ?>
                <th>Tipe</th>
                <th>Severity</th>
                <th>Pesan</th>
                <th class="alerts-table-snapshot-col">Snapshot</th>
            </tr>
        </thead>
        <tbody class="alerts-table-body">
            <?php foreach ($alertsList as $alert): ?>
                <tr data-status="<?php echo $alert['status']; ?>" data-severity="<?php echo $alert['severity']; ?>">
                    <td class="alerts-table-time">
                        <div class="table-cell-title"><?php echo date('M j, Y', strtotime($alert['timestamp'])); ?></div>
                        <small class="table-cell-subtitle"><?php echo date('H:i:s', strtotime($alert['timestamp'])); ?></small>
                    </td>
                    <?php if ($showDevice): ?>
                    <td>
                        <div class="table-cell-title"><?php echo htmlspecialchars($alert['hostname']); ?></div>
                        <small class="table-cell-subtitle"><?php echo htmlspecialchars($alert['ip_address']); ?></small>
                    </td>
                    <?php endif; ?>
                    <td>
                        <span class="status-badge offline alerts-inline-badge">
                            <?php echo strtoupper($alert['alert_type']); ?>
                        </span>
                    </td>
                    <td>
                        <span class="status-badge <?php echo $alert['severity']; ?> alerts-inline-badge">
                            <?php echo ucfirst($alert['severity']); ?>
                        </span>
                    </td>
                    <td>
                        <div class="alerts-message-ellipsis" title="<?php echo htmlspecialchars($alert['message']); ?>">
                            <?php echo htmlspecialchars($alert['message']); ?>
                        </div>
                    </td>
                    <td class="alerts-table-snapshot">
                        <?php 
                        $snapshot = null;
                        if (!empty($alert['snapshot_data'])) {
                            $snapshot = json_decode($alert['snapshot_data'], true);
                        }
                        if ($snapshot && (empty($snapshot['top_cpu']) === false || empty($snapshot['top_memory']) === false)): 
                        ?>
                            <button type="button" class="btn btn-tiny btn-outline alerts-snapshot-btn" onclick="document.getElementById('snap-row-<?php echo $idPrefix . '-' . $alert['id']; ?>').style.display = document.getElementById('snap-row-<?php echo $idPrefix . '-' . $alert['id']; ?>').style.display === 'none' ? 'table-row' : 'none'; event.stopPropagation();">
                                <i class="material-icons">camera_alt</i>
                                <span>Lihat</span>
                            </button>
                        <?php else: ?>
                            <span class="subtle-text">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php if ($snapshot && (empty($snapshot['top_cpu']) === false || empty($snapshot['top_memory']) === false)): ?>
                <tr id="snap-row-<?php echo $idPrefix . '-' . $alert['id']; ?>" class="alerts-snapshot-row" style="display: none;">
                    <td colspan="<?php echo $showDevice ? '6' : '5'; ?>" class="alerts-snapshot-cell">
                        <div class="alerts-snapshot-panel">
                            <div class="alerts-snapshot-grid">
                                <?php if (!empty($snapshot['top_cpu'])): ?>
                                <div>
                                    <strong class="alerts-snapshot-title">Top CPU</strong>
                                    <table class="alerts-snapshot-table">
                                        <?php foreach($snapshot['top_cpu'] as $proc): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($proc['name']); ?></td>
                                                <td class="alerts-snapshot-value danger"><?php echo $proc['cpu_percent']; ?>%</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </table>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($snapshot['top_memory'])): ?>
                                <div>
                                    <strong class="alerts-snapshot-title">Top Memory</strong>
                                    <table class="alerts-snapshot-table">
                                        <?php foreach($snapshot['top_memory'] as $proc): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($proc['name']); ?></td>
                                                <td class="alerts-snapshot-value info">
                                                    <?php echo isset($proc['memory_mb']) ? $proc['memory_mb'] . ' MB' : $proc['memory_percent'] . '%'; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </table>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}
?>

<style>
.alerts-stats-grid {
    grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
    gap: 8px;
    margin-bottom: 8px;
}

.alerts-stat-card {
    padding: 8px 10px;
    min-height: 56px;
}

.alerts-stat-icon {
    width: 34px;
    height: 34px;
    margin-right: 8px;
}

.alerts-stat-icon .material-icons {
    font-size: 18px;
}

.alerts-stat-info {
    line-height: 1.1;
}

.alerts-stat-label {
    font-size: 0.72rem;
    margin-bottom: 2px;
}

.alerts-stat-value {
    font-size: 1.1rem;
}

.alerts-toolbar-card {
    margin-bottom: 6px;
}

.alerts-toolbar-content {
    padding: 6px 8px;
}

.alerts-toolbar {
    gap: 6px;
    margin-bottom: 0;
}

.filter-tab-sm {
    min-height: 28px;
    padding-top: 4px;
    padding-bottom: 4px;
}

.filter-tab-sm .material-icons {
    font-size: 14px;
}

.alerts-refresh-btn {
    min-height: 28px;
    padding: 0 8px;
    font-size: 0.72rem;
}

.alerts-refresh-btn .material-icons {
    font-size: 14px;
}

.alerts-device-card {
    margin-bottom: 4px;
    border-radius: 8px;
    overflow: hidden;
    border-left: 4px solid var(--alert-group-accent);
}

.alerts-device-head {
    padding: 6px 10px;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    background: var(--bg-surface-2);
}

.alerts-device-main,
.alerts-device-meta {
    display: flex;
    align-items: center;
    gap: 10px;
}

.alerts-device-icon {
    font-size: 22px;
    color: var(--text-secondary);
}

.alerts-device-title {
    font-weight: 600;
    font-size: 0.92rem;
    color: var(--text-primary);
    line-height: 1.2;
}

.alerts-device-subtitle,
.alerts-device-total {
    font-size: 0.75rem;
    color: var(--text-secondary);
}

.alerts-device-counts {
    text-align: right;
}

.alerts-device-open {
    font-size: 0.92rem;
    font-weight: 600;
    color: var(--text-secondary);
    line-height: 1.2;
}

.alerts-device-open.has-open {
    color: var(--danger-color);
}

.alerts-device-chevron {
    color: var(--text-muted);
}

.alerts-device-body {
    border-top: 1px solid var(--border-color);
    padding: 2px 6px;
    background: var(--bg-surface);
}

.alerts-list-content {
    padding: 8px 10px;
}

.alerts-list-filters {
    margin-bottom: 8px;
}

.alerts-table {
    font-size: 0.8rem;
}

.alerts-table th,
.alerts-table td {
    padding: 6px 8px;
}

.alerts-table-snapshot-col,
.alerts-table-snapshot {
    width: 80px;
    text-align: center;
}

.alerts-table-time {
    white-space: nowrap;
}

.alerts-inline-badge {
    padding: 1px 4px;
    font-size: 0.7rem;
}

.alerts-message-ellipsis {
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.alerts-snapshot-btn {
    padding: 1px 5px;
    font-size: 0.7rem;
    height: 24px;
    min-height: 24px;
    line-height: 1;
    gap: 3px;
}

.alerts-snapshot-btn .material-icons {
    font-size: 13px;
}

.alerts-snapshot-row {
    background: var(--bg-surface-2);
}

.alerts-snapshot-cell {
    padding: 0 !important;
}

.alerts-snapshot-panel {
    padding: 12px;
    border-bottom: 1px solid var(--border-color);
}

.alerts-snapshot-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
    text-align: left;
}

.alerts-snapshot-title {
    display: block;
    margin-bottom: 4px;
    font-size: 0.75rem;
    color: var(--text-secondary);
    text-transform: uppercase;
}

.alerts-snapshot-table {
    width: 100%;
    font-size: 0.75rem;
    margin-top: 4px;
    border-collapse: collapse;
}

.alerts-snapshot-table td {
    padding: 3px 0;
    border-bottom: 1px solid var(--border-color);
}

.alerts-snapshot-table td:last-child {
    text-align: right;
    font-family: monospace;
    font-weight: 600;
}

.alerts-snapshot-value.danger {
    color: #ef4444;
}

.alerts-snapshot-value.info {
    color: #3b82f6;
}

html[data-theme="dark"] .status-badge {
    border: 1px solid var(--border-color);
}

@media (max-width: 768px) {
    .alerts-snapshot-grid {
        grid-template-columns: 1fr;
        gap: 12px;
    }

    .alerts-device-head {
        flex-direction: column;
        align-items: flex-start;
    }

    .alerts-device-meta {
        width: 100%;
        justify-content: space-between;
    }
}
</style>

<script>
function switchMode(mode) {
    document.getElementById('btn-mode-group').classList.remove('active');
    document.getElementById('btn-mode-list').classList.remove('active');
    document.getElementById('btn-mode-' + mode).classList.add('active');
    
    document.getElementById('view-group').style.display = (mode === 'group') ? 'block' : 'none';
    document.getElementById('view-list').style.display = (mode === 'list') ? 'block' : 'none';
}

function toggleDeviceAlerts(deviceId) {
    const el = document.getElementById('alerts-' + deviceId);
    const icon = document.getElementById('icon-' + deviceId);
    if (el.style.display === 'none') {
        el.style.display = 'block';
        icon.textContent = 'expand_less';
    } else {
        el.style.display = 'none';
        icon.textContent = 'expand_more';
    }
}

// Filter functionality for list view
document.querySelectorAll('#list-filters .filter-tab').forEach(tab => {
    tab.addEventListener('click', function() {
        document.querySelectorAll('#list-filters .filter-tab').forEach(t => t.classList.remove('active'));
        this.classList.add('active');

        const filter = this.dataset.filter;
        const rows = document.querySelectorAll('#view-list .alerts-table-body tr');

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
