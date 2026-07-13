<?php
/**
 * System Logs Content Page
 */
$log_dir = __DIR__ . '/../../logs/';
$log_files = [];

if (is_dir($log_dir)) {
    $files = scandir($log_dir);
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'log') {
            $log_files[] = [
                'name' => $file,
                'path' => $log_dir . $file,
                'size' => filesize($log_dir . $file),
                'modified' => filemtime($log_dir . $file)
            ];
        }
    }
}

// Get recent log entries if available
$recent_logs = [];
$selected_log = $_GET['log'] ?? null;

if ($selected_log && isset($log_files)) {
    foreach ($log_files as $lf) {
        if ($lf['name'] === $selected_log) {
            $log_path = $lf['path'];
            break;
        }
    }
}

if (isset($log_path) && file_exists($log_path)) {
    $lines = file($log_path);
    $recent_logs = array_slice($lines, -100); // Last 100 lines
}
?>

<div class="card">
    <div class="card-content">
        <h5 class="card-title">
            <i class="material-icons">description</i>
            System Logs
        </h5>

        <?php if (empty($log_files)): ?>
            <div class="empty-state">
                <i class="material-icons">folder_open</i>
                <p>Belum ada log file</p>
                <small>Log files akan dibuat otomatis di direktori logs/</small>
            </div>
        <?php else: ?>
            <div class="row">
                <div class="col s12 m4">
                    <h6>Log Files</h6>
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <?php foreach ($log_files as $log): ?>
                            <a href="?page=logs&log=<?php echo urlencode($log['name']); ?>"
                               class="btn <?php echo $selected_log === $log['name'] ? 'btn-primary' : ''; ?>"
                               style="text-align: left; justify-content: space-between; display: flex;">
                                <span><?php echo htmlspecialchars($log['name']); ?></span>
                                <small><?php echo number_format($log['size'] / 1024, 1); ?> KB</small>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="col s12 m8">
                    <?php if ($selected_log): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                            <h6><?php echo htmlspecialchars($selected_log); ?></h6>
                            <button class="btn btn-sm" onclick="location.reload()">
                                <i class="material-icons tiny">refresh</i> Refresh
                            </button>
                        </div>

                        <div style="background: #1a1a2e; color: #00ff00; padding: 1rem; border-radius: 8px; max-height: 500px; overflow-y: auto; font-family: 'Courier New', monospace; font-size: 0.875rem;">
                            <?php if (empty($recent_logs)): ?>
                                <p style="color: #666;">Log file kosong</p>
                            <?php else: ?>
                                <?php foreach ($recent_logs as $line): ?>
                                    <div style="white-space: pre-wrap; word-break: break-all;"><?php echo htmlspecialchars($line); ?></div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="material-icons">insert_drive_file</i>
                            <p>Pilih log file untuk melihat isinya</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.btn {
    display: flex !important;
    align-items: center !important;
}
</style>
