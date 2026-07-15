<?php
/**
 * Devices Content Page
 */
try {
    $pdo = getDbConnection();

    // --- FIX: Paksa update status offline untuk device yang sudah lama tidak kirim data.
    // Gunakan NOW() MySQL murni (bukan date() PHP) untuk menghindari timezone mismatch.
    // PHP pakai Asia/Jakarta (UTC+7), MySQL SYSTEM bisa berbeda timezone.
    $offline_minutes = getSetting($pdo, 'device_offline_minutes', 5);
    $cpu_thresh = getSetting($pdo, 'alert_threshold_cpu', 90);
    $mem_thresh = getSetting($pdo, 'alert_threshold_memory', 90);
    $disk_thresh = getSetting($pdo, 'alert_threshold_disk', 90);

    $pdo->prepare("
        UPDATE devices
        SET status = 'offline'
        WHERE last_seen < DATE_SUB(NOW(), INTERVAL ? MINUTE)
        AND status != 'offline'
    ")->execute([$offline_minutes]);
    // --- END FIX

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

    // Get stats dari status yang sudah dikoreksi
    $stats = ['total' => count($devices)];
    foreach ($devices as $d) {
        $stats[$d['status']] = ($stats[$d['status']] ?? 0) + 1;
    }

} catch (PDOException $e) {
    $db_error = $e->getMessage();
}
?>



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
                                     <div class="table-cell-title"><?php echo htmlspecialchars($device['hostname']); ?></div>
                                     <div class="table-cell-subtitle">
                                         ID: <?php echo htmlspecialchars(substr($device['device_id'], 0, 8)); ?>...
                                     </div>
                                 </td>
                                <td>
                                    <span class="code-pill">
                                        <?php echo htmlspecialchars($device['ip_address']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    $conn_status = ($device['status'] === 'offline') ? 'offline' : 'online';
                                    ?>
                                    <span class="status-badge <?php echo $conn_status; ?>">
                                        <?php echo ucfirst($conn_status); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="metric-inline compact">
                                        <div class="metric-track">
                                            <div class="metric-fill" style="width: <?php echo min($device['cpu_usage'] ?? 0, 100); ?>%; background: <?php echo getMetricColor($device['cpu_usage'] ?? 0, $cpu_thresh); ?>;"></div>
                                        </div>
                                        <span class="metric-value"><?php echo number_format($device['cpu_usage'] ?? 0, 1); ?>%</span>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $mem_percent = $device['memory_total'] > 0 ?
                                        (($device['memory_used'] / $device['memory_total']) * 100) : 0;
                                    ?>
                                    <div class="metric-inline compact">
                                        <div class="metric-track">
                                            <div class="metric-fill" style="width: <?php echo min($mem_percent, 100); ?>%; background: <?php echo getMetricColor($mem_percent, $mem_thresh); ?>;"></div>
                                        </div>
                                        <span class="metric-value"><?php echo number_format($mem_percent, 1); ?>%</span>
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
                                        <div class="disk-inline-list">
                                            <?php 
                                            $counter = 0;
                                            foreach ($all_disks as $disk_key => $disk): 
                                                $disk_name = strtoupper(str_replace('_', ':', $disk_key));
                                                $percent = ($disk['total'] > 0) ? ($disk['used'] / $disk['total']) * 100 : 0;
                                                $color = getMetricColor($percent, $disk_thresh);
                                                $counter++;
                                                if ($counter > 2) break; // Show max 2 disks in table
                                            ?>
                                                <div class="disk-inline-item">
                                                    <span class="disk-inline-label"><?php echo $disk_name; ?></span>
                                                    <span class="disk-inline-dot" style="background: <?php echo $color; ?>;"></span>
                                                    <span class="disk-inline-meta"><?php echo round($percent, 1); ?>%</span>
                                                </div>
                                            <?php endforeach; ?>
                                            <?php if (count($all_disks) > 2): ?>
                                                <span class="table-cell-subtitle">+<?php echo count($all_disks) - 2; ?> more</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="metric-inline compact">
                                            <div class="metric-track">
                                                <div class="metric-fill" style="width: <?php echo min($device['disk_usage'] ?? 0, 100); ?>%; background: <?php echo getMetricColor($device['disk_usage'] ?? 0, $disk_thresh); ?>;"></div>
                                            </div>
                                            <span class="metric-value"><?php echo number_format($device['disk_usage'] ?? 0, 1); ?>%</span>
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
                                    <small class="subtle-text"><?php echo date('M j, H:i', strtotime($device['last_seen'])); ?></small>
                                </td>
                                <td>
                                    <?php if ($device['status'] === 'warning' || $device['status'] === 'critical'): ?>
                                        <span class="status-badge <?php echo $device['status']; ?>" style="margin-bottom: 4px;">
                                            <?php echo strtoupper($device['status']); ?>
                                        </span><br>
                                    <?php endif; ?>
                                    
                                    <?php if ($device['open_alerts'] > 0): ?>
                                        <span class="status-badge critical">
                                            <?php echo $device['open_alerts']; ?> Alert
                                        </span>
                                    <?php elseif ($device['status'] !== 'warning' && $device['status'] !== 'critical'): ?>
                                        <span class="subtle-text">-</span>
                                    <?php endif; ?>
                                </td>
                                 <td>
                                     <div class="compact-actions" style="justify-content: center;">
                                         <a href="?page=devices&device_id=<?php echo htmlspecialchars($device['device_id']); ?>"
                                            class="btn btn-tiny btn-primary" title="Lihat Detail">
                                             <i class="material-icons">visibility</i>
                                         </a>
                                         <button type="button" class="btn btn-tiny btn-success btn-refresh-device" 
                                                 data-id="<?php echo htmlspecialchars($device['device_id']); ?>" 
                                                 title="Audit Seketika">
                                             <i class="material-icons">sync</i>
                                         </button>
                                         <button type="button" class="btn btn-tiny btn-secondary btn-edit-device" 
                                                 data-id="<?php echo htmlspecialchars($device['device_id']); ?>" 
                                                 data-hostname="<?php echo htmlspecialchars($device['hostname']); ?>"
                                                 data-ip="<?php echo htmlspecialchars($device['ip_address']); ?>"
                                                 title="Edit Perangkat">
                                             <i class="material-icons">edit</i>
                                         </button>
                                         <button type="button" class="btn btn-tiny btn-danger btn-delete-device" 
                                                 data-id="<?php echo htmlspecialchars($device['device_id']); ?>" 
                                                 data-hostname="<?php echo htmlspecialchars($device['hostname']); ?>"
                                                 title="Hapus Perangkat">
                                             <i class="material-icons">delete</i>
                                         </button>
                                     </div>
                                 </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="device-cards-mobile">
            <?php if (empty($devices)): ?>
                <div class="empty-state">
                    <i class="material-icons">devices</i>
                    <p>Belum ada perangkat terdaftar</p>
                    <small>
                        Gunakan agent Python untuk mengirim data metrik.<br>
                        <a href="../test_client.php" target="_blank">Test API Client</a>
                    </small>
                </div>
            <?php else: ?>
                <?php foreach ($devices as $device): ?>
                    <?php
                        $conn_status = ($device['status'] === 'offline') ? 'offline' : 'online';
                        $mem_percent = $device['memory_total'] > 0
                            ? (($device['memory_used'] / $device['memory_total']) * 100)
                            : 0;
                        $all_disks = [];
                        if (!empty($device['additional_info'])) {
                            $additional_info = json_decode($device['additional_info'], true);
                            if (isset($additional_info['all_disks']) && is_array($additional_info['all_disks'])) {
                                $all_disks = $additional_info['all_disks'];
                            }
                        }
                    ?>
                    <div class="device-mobile-card" data-status="<?php echo $device['status']; ?>">
                        <div class="device-mobile-head">
                            <div>
                                <div class="device-mobile-host"><?php echo htmlspecialchars($device['hostname']); ?></div>
                                <div class="device-mobile-id">ID: <?php echo htmlspecialchars(substr($device['device_id'], 0, 8)); ?>...</div>
                            </div>
                            <span class="status-badge <?php echo $conn_status; ?>">
                                <?php echo ucfirst($conn_status); ?>
                            </span>
                        </div>

                        <div class="device-mobile-meta">
                            <div><span>IP</span><strong><?php echo htmlspecialchars($device['ip_address']); ?></strong></div>
                            <div><span>Last Seen</span><strong><?php echo date('M j, H:i', strtotime($device['last_seen'])); ?></strong></div>
                            <div><span>CPU</span><strong><?php echo number_format($device['cpu_usage'] ?? 0, 1); ?>%</strong></div>
                            <div><span>Memory</span><strong><?php echo number_format($mem_percent, 1); ?>%</strong></div>
                        </div>

                        <div class="device-mobile-extra">
                            <div>
                                <span>Disk</span>
                                <strong>
                                    <?php if (!empty($all_disks)): ?>
                                        <?php
                                            $diskText = [];
                                            $counter = 0;
                                            foreach ($all_disks as $disk_key => $disk):
                                                $disk_name = strtoupper(str_replace('_', ':', $disk_key));
                                                $percent = round($disk['percent'], 1);
                                                $diskText[] = $disk_name . ' ' . $percent . '%';
                                                $counter++;
                                                if ($counter >= 2) break;
                                            endforeach;
                                            echo htmlspecialchars(implode(' · ', $diskText));
                                            if (count($all_disks) > 2) {
                                                echo ' +' . (count($all_disks) - 2);
                                            }
                                        ?>
                                    <?php else: ?>
                                        <?php echo number_format($device['disk_usage'] ?? 0, 1); ?>%
                                    <?php endif; ?>
                                </strong>
                            </div>
                            <div>
                                <span>Storage</span>
                                <strong><?php echo ucfirst($device['storage_health'] ?? 'Unknown'); ?></strong>
                            </div>
                            <div>
                                <span>Alerts</span>
                                <strong>
                                    <?php if ($device['open_alerts'] > 0): ?>
                                        <span class="status-badge critical"><?php echo $device['open_alerts']; ?> Alert</span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </strong>
                            </div>
                        </div>

                        <div class="device-mobile-actions">
                            <a href="?page=devices&device_id=<?php echo htmlspecialchars($device['device_id']); ?>"
                               class="btn btn-tiny btn-primary" title="Lihat Detail">
                                <i class="material-icons">visibility</i>
                            </a>
                            <button type="button" class="btn btn-tiny btn-success btn-refresh-device"
                                    data-id="<?php echo htmlspecialchars($device['device_id']); ?>"
                                    title="Audit Seketika">
                                <i class="material-icons">sync</i>
                            </button>
                            <button type="button" class="btn btn-tiny btn-secondary btn-edit-device"
                                    data-id="<?php echo htmlspecialchars($device['device_id']); ?>"
                                    data-hostname="<?php echo htmlspecialchars($device['hostname']); ?>"
                                    data-ip="<?php echo htmlspecialchars($device['ip_address']); ?>"
                                    title="Edit Perangkat">
                                <i class="material-icons">edit</i>
                            </button>
                            <button type="button" class="btn btn-tiny btn-danger btn-delete-device"
                                    data-id="<?php echo htmlspecialchars($device['device_id']); ?>"
                                    data-hostname="<?php echo htmlspecialchars($device['hostname']); ?>"
                                    title="Hapus Perangkat">
                                <i class="material-icons">delete</i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
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
    padding: 7px 12px;
    border: 1px solid var(--border-color);
    border-radius: 999px;
    background: var(--bg-surface);
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 0.8rem;
    color: var(--text-secondary);
}

.filter-tab:hover {
    background: var(--bg-surface-2);
}

.filter-tab.active {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-color: transparent;
}

.table-container {
    overflow-x: auto;
    overflow-y: hidden;
    -webkit-overflow-scrolling: touch;
}

.data-table {
    width: max-content;
    min-width: 100%;
    table-layout: auto;
}

.data-table th {
    padding: 8px 10px;
    font-size: 12px;
    white-space: nowrap;
}

.data-table td {
    padding: 8px 10px;
    font-size: 12px;
    vertical-align: middle;
}

.data-table th:nth-child(1),
.data-table td:nth-child(1) {
    width: 210px;
    min-width: 180px;
}

.data-table th:nth-child(2),
.data-table td:nth-child(2) {
    width: 120px;
    min-width: 110px;
    text-align: center;
    white-space: nowrap;
}

.data-table th:nth-child(3),
.data-table td:nth-child(3) {
    width: 84px;
    min-width: 80px;
    text-align: center;
    white-space: nowrap;
}

.data-table th:nth-child(4),
.data-table td:nth-child(4),
.data-table th:nth-child(5),
.data-table td:nth-child(5),
.data-table th:nth-child(6),
.data-table td:nth-child(6) {
    width: 110px;
    min-width: 100px;
    text-align: left;
    white-space: nowrap;
}

.data-table th:nth-child(7),
.data-table td:nth-child(7) {
    width: 120px;
    min-width: 110px;
    text-align: center;
    white-space: nowrap;
}

.data-table th:nth-child(8),
.data-table td:nth-child(8) {
    width: 110px;
    min-width: 100px;
    text-align: center;
    white-space: nowrap;
}

.data-table th:nth-child(9),
.data-table td:nth-child(9) {
    width: 88px;
    min-width: 80px;
    text-align: center;
    white-space: nowrap;
}

.data-table th:nth-child(10),
.data-table td:nth-child(10) {
    width: 132px;
    min-width: 120px;
    text-align: center;
    white-space: nowrap;
}

.data-table td:nth-child(10) > div {
    flex-wrap: nowrap;
    gap: 4px;
}

.device-cards-mobile {
    display: none;
}

.device-mobile-card {
    border: 1px solid var(--border-color);
    border-radius: 10px;
    padding: 8px 10px;
    margin-bottom: 6px;
    background: var(--bg-surface);
}

.device-mobile-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 8px;
    margin-bottom: 6px;
}

.device-mobile-host {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-primary);
    line-height: 1.2;
}

.device-mobile-id {
    font-size: 0.68rem;
    color: var(--text-secondary);
    margin-top: 2px;
}

.device-mobile-meta,
.device-mobile-extra {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 6px 8px;
    margin-bottom: 8px;
}

.device-mobile-meta div,
.device-mobile-extra div {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}

.device-mobile-meta span,
.device-mobile-extra span {
    font-size: 0.65rem;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.4px;
}

.device-mobile-meta strong,
.device-mobile-extra strong {
    font-size: 0.78rem;
    color: var(--text-primary);
    font-weight: 600;
    word-break: break-word;
}

.device-mobile-actions {
    display: flex;
    gap: 4px;
    justify-content: flex-start;
    flex-wrap: wrap;
}

@media (max-width: 768px) {
    .filter-tabs {
        gap: 6px;
    }

    .filter-tab {
        padding: 6px 12px;
        font-size: 0.8rem;
    }

    .data-table {
        display: none;
    }

    .table-container {
        overflow: visible;
    }

    .device-cards-mobile {
        display: block;
    }

    .device-mobile-meta,
    .device-mobile-extra {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .device-mobile-actions {
        justify-content: space-between;
    }

    .device-mobile-actions .btn.btn-tiny {
        flex: 1 1 calc(25% - 4px);
    }

    .btn.btn-tiny {
        padding: 0 5px;
    }
}

@media (max-width: 420px) {
    .device-mobile-meta,
    .device-mobile-extra {
        grid-template-columns: 1fr;
    }

    .device-mobile-actions .btn.btn-tiny {
        flex: 1 1 calc(50% - 4px);
    }
}

/* Custom Modal Styling */
.custom-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(15, 23, 42, 0.4);
    backdrop-filter: blur(4px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    animation: modal-fade-in 0.2s ease;
}

.custom-modal-card {
    background: var(--bg-surface);
    border-radius: 12px;
    width: 90%;
    max-width: 450px;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    overflow: hidden;
    animation: modal-scale-in 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.custom-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    border-bottom: 1px solid var(--border-color);
}

.custom-modal-header h5 {
    margin: 0;
    font-weight: 700;
    font-size: 1.1rem;
    color: var(--text-primary);
}

.custom-modal-close-btn {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: var(--text-secondary);
    transition: color 0.15s;
}

.custom-modal-close-btn:hover {
    color: var(--text-primary);
}

.custom-modal-card form {
    padding: 20px;
    margin: 0;
}

.custom-modal-body {
    padding: 20px;
    text-align: left;
}

.custom-modal-card label {
    display: block;
    margin-bottom: 6px;
    font-weight: 600;
    font-size: 0.85rem;
    color: var(--text-secondary);
    text-align: left;
}

.custom-modal-card input {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    font-size: 0.9rem;
    color: var(--text-primary);
    box-sizing: border-box;
    background: var(--bg-surface);
    margin-bottom: 15px;
}

.custom-modal-card input:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.custom-modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    padding: 16px 20px;
    background: var(--bg-surface-2);
    border-top: 1px solid var(--border-color);
}

@keyframes modal-fade-in {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes modal-scale-in {
    from { transform: scale(0.95); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
}
</style>

<!-- Edit Device Modal -->
<div id="edit-modal" class="custom-modal-overlay" style="display: none;">
    <div class="custom-modal-card">
        <div class="custom-modal-header">
            <h5>Edit Perangkat</h5>
            <button type="button" class="custom-modal-close-btn">&times;</button>
        </div>
        <form id="edit-device-form">
            <input type="hidden" id="edit-device-id" name="device_id">
            <div class="form-group">
                <label for="edit-hostname">Hostname / Alias</label>
                <input type="text" id="edit-hostname" required placeholder="Nama perangkat">
            </div>
            <div class="form-group">
                <label for="edit-ip-address">IP Address</label>
                <input type="text" id="edit-ip-address" required placeholder="IP Address">
            </div>
            <div class="custom-modal-footer">
                <button type="button" class="btn btn-secondary custom-modal-close-btn">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Device Modal -->
<div id="delete-modal" class="custom-modal-overlay" style="display: none;">
    <div class="custom-modal-card">
        <div class="custom-modal-header">
            <h5>Hapus Perangkat</h5>
            <button type="button" class="custom-modal-close-btn">&times;</button>
        </div>
        <div class="custom-modal-body">
            <p>Apakah Anda yakin ingin menghapus perangkat <strong id="delete-device-name"></strong>?</p>
            <p class="danger-text" style="font-size: 0.85rem; margin-top: 10px; display: flex; align-items: center; gap: 4px;">
                <i class="material-icons" style="font-size: 16px;">warning</i>
                Tindakan ini akan menghapus semua metrik dan log alert terkait secara permanen.
            </p>
        </div>
        <div class="custom-modal-footer">
            <button type="button" class="btn btn-secondary custom-modal-close-btn">Batal</button>
            <button type="button" id="confirm-delete-btn" class="btn btn-danger">Hapus</button>
        </div>
    </div>
</div>

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

// Modal Logic
const editModal = document.getElementById('edit-modal');
const deleteModal = document.getElementById('delete-modal');

// Close modals
document.querySelectorAll('.custom-modal-close-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        editModal.style.display = 'none';
        deleteModal.style.display = 'none';
    });
});

[editModal, deleteModal].forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.style.display = 'none';
        }
    });
});

// Open Edit Modal
document.querySelectorAll('.btn-edit-device').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        document.getElementById('edit-device-id').value = this.dataset.id;
        document.getElementById('edit-hostname').value = this.dataset.hostname;
        document.getElementById('edit-ip-address').value = this.dataset.ip;
        editModal.style.display = 'flex';
    });
});

// Submit Edit
document.getElementById('edit-device-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const deviceId = document.getElementById('edit-device-id').value;
    const hostname = document.getElementById('edit-hostname').value;
    const ipAddress = document.getElementById('edit-ip-address').value;

    fetch('api/devices.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            device_id: deviceId,
            hostname: hostname,
            ip_address: ipAddress
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Gagal mengedit perangkat: ' + data.message);
        }
    })
    .catch(err => {
        console.error(err);
        alert('Terjadi kesalahan koneksi.');
    });
});

// Open Delete Modal
let deviceIdToDelete = null;
document.querySelectorAll('.btn-delete-device').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        deviceIdToDelete = this.dataset.id;
        document.getElementById('delete-device-name').textContent = this.dataset.hostname;
        deleteModal.style.display = 'flex';
    });
});

// Confirm Delete
document.getElementById('confirm-delete-btn').addEventListener('click', function() {
    if (!deviceIdToDelete) return;

    fetch(`api/devices.php?id=${deviceIdToDelete}`, {
        method: 'DELETE'
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Gagal menghapus perangkat: ' + data.message);
        }
    })
    .catch(err => {
        console.error(err);
        alert('Terjadi kesalahan koneksi.');
    });
});

// Refresh Device (Audit)
document.querySelectorAll('.btn-refresh-device').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const deviceId = this.dataset.id;
        const icon = this.querySelector('i');
        const originalIcon = icon.textContent;
        
        // Set loading state
        icon.textContent = 'hourglass_empty';
        this.disabled = true;
        this.style.opacity = '0.5';
        
        // Queue command
        fetch('api/commands.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'create', device_id: deviceId, command: 'audit' })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success && data.command_id) {
                // Poll for completion
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
                
                // Timeout after 45 seconds
                setTimeout(() => {
                    clearInterval(pollInterval);
                    icon.textContent = originalIcon;
                    this.disabled = false;
                    this.style.opacity = '1';
                    alert('Timeout (45s): Agent mungkin offline atau proses audit perangkat memakan waktu lebih lama dari perkiraan.');
                }, 45000);
            } else {
                icon.textContent = originalIcon;
                this.disabled = false;
                this.style.opacity = '1';
                alert('Gagal mengirim perintah: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(err => {
            console.error(err);
            icon.textContent = originalIcon;
            this.disabled = false;
            this.style.opacity = '1';
        });
    });
});

function getMetricColor(value, threshold = 90) {
    if (value >= threshold) return '#ff5252';
    if (value >= (threshold - 15)) return '#ffab00';
    if (value >= (threshold - 40)) return '#ffca28';
    return '#00c851';
}
</script>

<?php
function getMetricColor($value, $threshold = 90) {
    if ($value >= $threshold) return '#ff5252';
    if ($value >= ($threshold - 15)) return '#ffab00';
    if ($value >= ($threshold - 40)) return '#ffca28';
    return '#00c851';
}
?>
