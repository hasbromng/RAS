<?php
/**
 * System Logs Content Page
 */

try {
    $pdo = getDbConnection();
    
    // Pagination
    $page_num = isset($_GET['p']) ? (int)$_GET['p'] : 1;
    if ($page_num < 1) $page_num = 1;
    $limit = 25;
    $offset = ($page_num - 1) * $limit;
    
    // Total count
    $stmt = $pdo->query("SELECT COUNT(*) FROM activity_logs");
    $total_logs = $stmt->fetchColumn();
    $total_pages = ceil($total_logs / $limit);
    
    // Fetch logs
    $stmt = $pdo->prepare("SELECT * FROM activity_logs ORDER BY timestamp DESC LIMIT ? OFFSET ?");
    $stmt->execute([$limit, $offset]);
    $activity_logs = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $db_error = $e->getMessage();
    $activity_logs = [];
}
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
    <div>
        <h2 class="page-title" style="margin: 0; display: flex; align-items: center; gap: 8px;">
            <i class="material-icons" style="font-size: 28px; color: var(--primary-color);">manage_history</i>
            Activity Logs
        </h2>
        <p class="text-secondary" style="margin: 4px 0 0 0; font-size: 14px;">Catatan aktivitas sistem dan riwayat tindakan krusial.</p>
    </div>
    <div>
        <button class="btn btn-outline" onclick="location.reload()" style="display: inline-flex; align-items: center; gap: 6px;">
            <i class="material-icons" style="font-size: 16px;">refresh</i>
            Refresh
        </button>
    </div>
</div>

<?php if (isset($db_error)): ?>
    <div class="alert alert-danger">
        <i class="material-icons tiny">error</i>
        Gagal memuat log aktivitas: <?php echo htmlspecialchars($db_error); ?>
    </div>
<?php endif; ?>

<div class="surface-panel" style="padding: 0; overflow: hidden;">
    <?php if (empty($activity_logs)): ?>
        <div style="padding: 60px 20px; text-align: center;">
            <i class="material-icons" style="font-size: 48px; color: var(--text-muted); margin-bottom: 16px;">history</i>
            <h3 style="margin: 0 0 8px 0; font-size: 16px; color: var(--text-primary);">Belum Ada Aktivitas</h3>
            <p style="margin: 0; color: var(--text-secondary); font-size: 13px;">Belum ada log aktivitas yang tercatat di dalam database.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 160px;">Waktu</th>
                        <th style="width: 140px;">Pengguna / IP</th>
                        <th style="width: 160px;">Aksi / Modul</th>
                        <th style="width: 100px;">Level</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activity_logs as $log): ?>
                        <tr>
                            <td class="text-secondary" style="font-size: 13px;">
                                <?php echo date('Y-m-d H:i:s', strtotime($log['timestamp'])); ?>
                            </td>
                            <td>
                                <div style="font-weight: 600; font-size: 13px; color: var(--text-primary);">
                                    <i class="material-icons" style="font-size: 14px; vertical-align: middle; margin-right: 4px; color: var(--text-secondary);">person</i>
                                    <?php echo htmlspecialchars($log['username'] ?? 'System'); ?>
                                </div>
                                <div style="font-family: monospace; font-size: 11px; color: var(--text-muted); margin-top: 4px;">
                                    <?php echo htmlspecialchars($log['ip_address'] ?? '-'); ?>
                                </div>
                            </td>
                            <td>
                                <span style="font-weight: 500; font-size: 13px; color: var(--text-primary);">
                                    <?php echo htmlspecialchars($log['action']); ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                $level = strtoupper($log['level']);
                                $color = 'var(--text-secondary)';
                                $bg = 'var(--bg-surface-3)';
                                
                                if ($level === 'CRITICAL' || $level === 'ERROR') {
                                    $color = '#ef4444';
                                    $bg = 'rgba(239, 68, 68, 0.1)';
                                } elseif ($level === 'WARNING') {
                                    $color = '#f59e0b';
                                    $bg = 'rgba(245, 158, 11, 0.1)';
                                } elseif ($level === 'INFO') {
                                    $color = '#3b82f6';
                                    $bg = 'rgba(59, 130, 246, 0.1)';
                                }
                                ?>
                                <span style="font-size: 11px; padding: 3px 8px; border-radius: 12px; background: <?php echo $bg; ?>; color: <?php echo $color; ?>; font-weight: 600;">
                                    <?php echo htmlspecialchars($level); ?>
                                </span>
                            </td>
                            <td style="font-size: 13px; color: var(--text-secondary);">
                                <?php echo htmlspecialchars($log['description']); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($total_pages > 1): ?>
            <div style="padding: 16px; border-top: 1px solid var(--border-color); display: flex; justify-content: center;">
                <div style="display: flex; gap: 4px;">
                    <?php if ($page_num > 1): ?>
                        <a href="?page=logs&p=<?php echo $page_num - 1; ?>" class="btn btn-tiny btn-outline"><i class="material-icons tiny">chevron_left</i></a>
                    <?php endif; ?>
                    
                    <span style="display: inline-flex; align-items: center; padding: 0 12px; font-size: 13px; color: var(--text-secondary);">
                        Halaman <?php echo $page_num; ?> dari <?php echo $total_pages; ?>
                    </span>
                    
                    <?php if ($page_num < $total_pages): ?>
                        <a href="?page=logs&p=<?php echo $page_num + 1; ?>" class="btn btn-tiny btn-outline"><i class="material-icons tiny">chevron_right</i></a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
