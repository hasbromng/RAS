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
?>

<!-- Alert Statistics -->
<div class="stats-grid" style="grid-template-columns: repeat(4, 1fr); gap: 6px; margin-bottom: 6px;">
    <div class="stat-card" style="padding: 8px 10px; min-height: 56px;">
        <div class="stat-icon primary" style="width: 34px; height: 34px; margin-right: 8px;">
            <i class="material-icons" style="font-size: 18px;">notifications</i>
        </div>
        <div class="stat-info" style="line-height: 1.1;">
            <div class="stat-label" style="font-size: 0.72rem; margin-bottom: 2px;">Total Alerts</div>
            <div class="stat-value" style="font-size: 1.1rem;"><?php echo $stats['total']; ?></div>
        </div>
    </div>

    <div class="stat-card" style="padding: 8px 10px; min-height: 56px;">
        <div class="stat-icon danger" style="width: 34px; height: 34px; margin-right: 8px;">
            <i class="material-icons" style="font-size: 18px;">error</i>
        </div>
        <div class="stat-info" style="line-height: 1.1;">
            <div class="stat-label" style="font-size: 0.72rem; margin-bottom: 2px;">Open Alerts</div>
            <div class="stat-value" style="font-size: 1.1rem;"><?php echo $stats['open']; ?></div>
        </div>
    </div>

    <div class="stat-card" style="padding: 8px 10px; min-height: 56px;">
        <div class="stat-icon warning" style="width: 34px; height: 34px; margin-right: 8px;">
            <i class="material-icons" style="font-size: 18px;">pending</i>
        </div>
        <div class="stat-info" style="line-height: 1.1;">
            <div class="stat-label" style="font-size: 0.72rem; margin-bottom: 2px;">Acknowledged</div>
            <div class="stat-value" style="font-size: 1.1rem;"><?php echo $stats['acknowledged']; ?></div>
        </div>
    </div>

    <div class="stat-card" style="padding: 8px 10px; min-height: 56px;">
        <div class="stat-icon success" style="width: 34px; height: 34px; margin-right: 8px;">
            <i class="material-icons" style="font-size: 18px;">check_circle</i>
        </div>
        <div class="stat-info" style="line-height: 1.1;">
            <div class="stat-label" style="font-size: 0.72rem; margin-bottom: 2px;">Resolved</div>
            <div class="stat-value" style="font-size: 1.1rem;"><?php echo $stats['resolved']; ?></div>
        </div>
    </div>
</div>

<!-- Mode Switch & Refresh -->
<div class="card" style="margin-bottom: 6px;">
    <div class="card-content" style="padding: 4px 8px;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.3rem;">
            <div class="filter-tabs">
                <button class="filter-tab active filter-tab-sm" onclick="switchMode('group')" id="btn-mode-group">
                    <i class="material-icons left" style="font-size:14px;">view_agenda</i> Group by Device
                </button>
                <button class="filter-tab filter-tab-sm" onclick="switchMode('list')" id="btn-mode-list">
                    <i class="material-icons left" style="font-size:14px;">view_list</i> Flat List
                </button>
            </div>
            <button class="btn btn-primary btn-sm" style="height: 26px; line-height: 26px; padding: 0 8px; font-size: 0.72rem;" onclick="AdminPanel.loadAlertsData()">
                <i class="material-icons left" style="font-size: 14px; margin-right: 4px;">refresh</i>
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
            <div class="card" style="margin-bottom: 4px; border-radius: 6px; overflow: hidden; border-left: 4px solid <?php echo $group['worst_severity'] === 'critical' ? '#e74c3c' : ($group['worst_severity'] === 'warning' ? '#f39c12' : '#3498db'); ?>;">
                <div class="card-content" style="padding: 6px 10px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; background: #fcfcfc;" onclick="toggleDeviceAlerts('<?php echo htmlspecialchars($dev_id); ?>')">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div>
                            <i class="material-icons" style="font-size: 24px; color: #555;">computer</i>
                        </div>
                        <div>
                            <div style="font-weight: 600; font-size: 0.95rem; color: #2c3e50; line-height: 1.2;"><?php echo htmlspecialchars($group['hostname']); ?></div>
                            <div style="font-size: 0.75rem; color: #7f8c8d;"><?php echo htmlspecialchars($group['ip_address']); ?></div>
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div style="text-align: right;">
                            <div style="font-size: 0.95rem; font-weight: 600; color: <?php echo $group['open'] > 0 ? '#e74c3c' : '#7f8c8d'; ?>; line-height: 1.2;"><?php echo $group['open']; ?> Open Alerts</div>
                            <div style="font-size: 0.75rem; color: #95a5a6;"><?php echo $group['total']; ?> Total</div>
                        </div>
                        <i class="material-icons" id="icon-<?php echo htmlspecialchars($dev_id); ?>" style="color: #bdc3c7;">expand_more</i>
                    </div>
                </div>
                
                <div id="alerts-<?php echo htmlspecialchars($dev_id); ?>" style="display: none; border-top: 1px solid #eee; padding: 2px 6px; background: #fff;">
                    <!-- List alerts for this device -->
                    <?php renderAlertsTable($group['alerts']); ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- List Mode View -->
<div id="view-list" style="display: none;">
    <div class="card">
        <div class="card-content" style="padding: 8px 10px;">
            <!-- Filter Actions for List -->
            <div style="margin-bottom: 8px;">
                <div class="filter-tabs" id="list-filters">
                    <button class="filter-tab active" data-filter="all">Semua</button>
                    <button class="filter-tab" data-filter="open">Open</button>
                    <button class="filter-tab" data-filter="acknowledged">Acknowledged</button>
                    <button class="filter-tab" data-filter="resolved">Resolved</button>
                    <button class="filter-tab" data-filter="critical">Critical Only</button>
                </div>
            </div>
            
            <div class="table-container">
                <?php renderAlertsTable($alerts, true); ?>
            </div>
        </div>
    </div>
</div>

<?php
function renderAlertsTable($alertsList, $showDevice = false) {
    if (empty($alertsList)) {
        echo '<div class="empty-state"><p>Belum ada alert</p></div>';
        return;
    }
    ?>
    <table class="data-table" style="font-size: 0.8rem;">
        <thead>
            <tr>
                <th style="padding: 6px 8px;">Waktu</th>
                <?php if ($showDevice): ?><th style="padding: 6px 8px;">Perangkat</th><?php endif; ?>
                <th style="padding: 6px 8px;">Tipe</th>
                <th style="padding: 6px 8px;">Severity</th>
                <th style="padding: 6px 8px;">Pesan</th>
                <th style="padding: 6px 8px; width: 80px; text-align: center;">Snapshot</th>
            </tr>
        </thead>
        <tbody class="alerts-table-body">
            <?php foreach ($alertsList as $alert): ?>
                <tr data-status="<?php echo $alert['status']; ?>" data-severity="<?php echo $alert['severity']; ?>">
                    <td style="padding: 6px 8px; white-space: nowrap;">
                        <div style="font-weight: 500;"><?php echo date('M j, Y', strtotime($alert['timestamp'])); ?></div>
                        <small style="color: #7f8c8d;"><?php echo date('H:i:s', strtotime($alert['timestamp'])); ?></small>
                    </td>
                    <?php if ($showDevice): ?>
                    <td style="padding: 6px 8px;">
                        <div style="font-weight: 500;"><?php echo htmlspecialchars($alert['hostname']); ?></div>
                        <small style="color: #636e72;"><?php echo htmlspecialchars($alert['ip_address']); ?></small>
                    </td>
                    <?php endif; ?>
                    <td style="padding: 6px 8px;">
                        <span class="status-badge" style="background: #e3f2fd; color: #0277bd; padding: 1px 4px; font-size: 0.7rem;">
                            <?php echo strtoupper($alert['alert_type']); ?>
                        </span>
                    </td>
                    <td style="padding: 6px 8px;">
                        <span class="status-badge <?php echo $alert['severity']; ?>" style="padding: 1px 4px; font-size: 0.7rem;">
                            <?php echo ucfirst($alert['severity']); ?>
                        </span>
                    </td>
                    <td style="padding: 6px 8px;">
                        <div style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($alert['message']); ?>">
                            <?php echo htmlspecialchars($alert['message']); ?>
                        </div>
                    </td>
                    <td style="padding: 6px 8px; text-align: center;">
                        <?php 
                        $snapshot = null;
                        if (!empty($alert['snapshot_data'])) {
                            $snapshot = json_decode($alert['snapshot_data'], true);
                        }
                        if ($snapshot && (empty($snapshot['top_cpu']) === false || empty($snapshot['top_memory']) === false)): 
                        ?>
                            <div style="position: relative; display: inline-block;">
                                <button type="button" class="btn btn-tiny" style="background: #f1f5f9; border: 1px solid #cbd5e1; color: #475569; padding: 1px 5px; font-size: 0.7rem; border-radius: 4px; cursor: pointer; display: inline-flex; align-items: center; gap: 3px; height: 24px; line-height: 24px;" onclick="document.getElementById('snapshot-global-<?php echo $alert['id']; ?>').style.display = document.getElementById('snapshot-global-<?php echo $alert['id']; ?>').style.display === 'none' ? 'block' : 'none'; event.stopPropagation();">
                                    <i class="material-icons" style="font-size: 13px;">camera_alt</i>
                                    <span>Lihat</span>
                                </button>
                                
                                <div id="snapshot-global-<?php echo $alert['id']; ?>" style="display: none; position: absolute; z-index: 100; right: 0; top: 100%; margin-top: 4px; width: 450px; background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1);" onclick="event.stopPropagation();">
                                    <div style="display: flex; gap: 16px; text-align: left;">
                                        <?php if (!empty($snapshot['top_cpu'])): ?>
                                        <div style="flex: 1;">
                                            <strong style="font-size: 0.75rem; color: #64748b; text-transform: uppercase;">Top CPU</strong>
                                            <table style="width: 100%; font-size: 0.75rem; margin-top: 4px; border-collapse: collapse;">
                                                <?php foreach($snapshot['top_cpu'] as $proc): ?>
                                                    <tr>
                                                        <td style="padding: 3px 0; border-bottom: 1px solid #f1f5f9;"><?php echo htmlspecialchars($proc['name']); ?></td>
                                                        <td style="padding: 3px 0; border-bottom: 1px solid #f1f5f9; text-align: right; color: #ef4444; font-family: monospace; font-weight: 600;"><?php echo $proc['cpu_percent']; ?>%</td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </table>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($snapshot['top_memory'])): ?>
                                        <div style="flex: 1;">
                                            <strong style="font-size: 0.75rem; color: #64748b; text-transform: uppercase;">Top Memory</strong>
                                            <table style="width: 100%; font-size: 0.75rem; margin-top: 4px; border-collapse: collapse;">
                                                <?php foreach($snapshot['top_memory'] as $proc): ?>
                                                    <tr>
                                                        <td style="padding: 3px 0; border-bottom: 1px solid #f1f5f9;"><?php echo htmlspecialchars($proc['name']); ?></td>
                                                        <td style="padding: 3px 0; border-bottom: 1px solid #f1f5f9; text-align: right; color: #3b82f6; font-family: monospace; font-weight: 600;"><?php echo $proc['memory_percent']; ?>%</td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </table>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <span style="color: #ccc; font-size: 0.75rem;">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}
?>

<style>
.filter-tabs {
    display: flex;
    gap: 4px;
}

.filter-tab {
    padding: 4px 10px;
    border: 1px solid #e0e0e0;
    border-radius: 14px;
    background: white;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 0.75rem;
    display: flex;
    align-items: center;
    line-height: 1;
    min-height: 28px;
}

.filter-tab:hover {
    background: #f5f5f5;
}

.filter-tab.active {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-color: transparent;
}
.filter-tab-sm {
    padding-top: 3px;
    padding-bottom: 3px;
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
