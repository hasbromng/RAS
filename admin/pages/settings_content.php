<?php
/**
 * Settings Content Page
 */
try {
    $pdo = getDbConnection();

    $setting_defaults = [
        'alert_threshold_cpu' => 90,
        'alert_threshold_memory' => 90,
        'alert_threshold_disk' => 90,
        'device_offline_minutes' => 5,
        'dashboard_refresh_seconds' => 30,
    ];

    // Read current settings directly from DB, with a single fallback source.
    $settings = [];
    foreach ($setting_defaults as $key => $default) {
        $settings[$key] = getSetting($pdo, $key, $default);
    }

    // Handle settings update
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $updates = [
            'alert_threshold_cpu' => (int)($_POST['cpu_threshold'] ?? $setting_defaults['alert_threshold_cpu']),
            'alert_threshold_memory' => (int)($_POST['memory_threshold'] ?? $setting_defaults['alert_threshold_memory']),
            'alert_threshold_disk' => (int)($_POST['disk_threshold'] ?? $setting_defaults['alert_threshold_disk']),
            'device_offline_minutes' => (int)($_POST['offline_minutes'] ?? $setting_defaults['device_offline_minutes']),
            'dashboard_refresh_seconds' => (int)($_POST['refresh_interval'] ?? $setting_defaults['dashboard_refresh_seconds'])
        ];

        foreach ($updates as $key => $value) {
            setSetting($pdo, $key, $value, 'integer');
        }

        $success_msg = "Pengaturan berhasil disimpan!";
        $settings = array_merge($settings, $updates);
    }

} catch (PDOException $e) {
    $db_error = $e->getMessage();
}
?>

<!-- Error / Success Messages -->
<?php if (isset($db_error)): ?>
<div class="alert alert-danger">
    <i class="material-icons tiny">error</i>
    <strong>Database Error:</strong> <?php echo htmlspecialchars($db_error); ?>
</div>
<?php endif; ?>

<?php if (isset($success_msg)): ?>
<div class="alert alert-success">
    <i class="material-icons tiny">check_circle</i>
    <?php echo htmlspecialchars($success_msg); ?>
</div>
<?php endif; ?>

<!-- Alert Thresholds -->
<div class="card">
    <div class="card-content">
        <h5 class="card-title">
            <i class="material-icons">tune</i>
            Threshold Alert
        </h5>
        <p class="section-note">
            Tentukan batas untuk memicu alert otomatis
        </p>

        <form method="POST">
            <div class="row">
                <div class="col s12 m6">
                    <div class="form-group">
                        <label>CPU Threshold (%)</label>
                        <input type="number" name="cpu_threshold" value="<?php echo (int)($settings['alert_threshold_cpu'] ?? $setting_defaults['alert_threshold_cpu']); ?>"
                               class="form-control" min="0" max="100" step="5">
                        <small class="text-muted">Alert ketika CPU melebihi nilai ini</small>
                    </div>
                </div>
                <div class="col s12 m6">
                    <div class="form-group">
                        <label>Memory Threshold (%)</label>
                        <input type="number" name="memory_threshold" value="<?php echo (int)($settings['alert_threshold_memory'] ?? $setting_defaults['alert_threshold_memory']); ?>"
                               class="form-control" min="0" max="100" step="5">
                        <small class="text-muted">Alert ketika Memory melebihi nilai ini</small>
                    </div>
                </div>
                <div class="col s12 m6">
                    <div class="form-group">
                        <label>Disk Threshold (%)</label>
                        <input type="number" name="disk_threshold" value="<?php echo (int)($settings['alert_threshold_disk'] ?? $setting_defaults['alert_threshold_disk']); ?>"
                               class="form-control" min="0" max="100" step="5">
                        <small class="text-muted">Alert ketika Disk usage melebihi nilai ini</small>
                    </div>
                </div>
                <div class="col s12 m6">
                    <div class="form-group">
                        <label>Offline Threshold (menit)</label>
                        <input type="number" name="offline_minutes" value="<?php echo (int)($settings['device_offline_minutes'] ?? $setting_defaults['device_offline_minutes']); ?>"
                               class="form-control" min="1" max="60" step="1">
                        <small class="text-muted">Perangkat dianggap offline setelah</small>
                    </div>
                </div>
                <div class="col s12 m6">
                    <div class="form-group">
                        <label>Dashboard Refresh (detik)</label>
                        <input type="number" name="refresh_interval" value="<?php echo (int)($settings['dashboard_refresh_seconds'] ?? $setting_defaults['dashboard_refresh_seconds']); ?>"
                               class="form-control" min="10" max="300" step="10">
                        <small class="text-muted">Interval auto-refresh dashboard</small>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="material-icons left">save</i>
                Simpan Pengaturan
            </button>
        </form>
    </div>
</div>

<!-- Current Settings Display -->
<div class="card">
    <div class="card-content">
        <h5 class="card-title">
            <i class="material-icons">info</i>
            Informasi Sistem
        </h5>
        <div class="table-container">
            <table class="data-table">
                <tbody>
                    <tr>
                        <td><strong>PHP Version</strong></td>
                        <td><?php echo PHP_VERSION; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Server Time</strong></td>
                        <td><?php echo date('Y-m-d H:i:s'); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Application</strong></td>
                        <td>RAS Dashboard v1.0.0</td>
                    </tr>
                    <tr>
                        <td><strong>API Endpoint</strong></td>
                        <td>
                            <code><?php echo APP_URL; ?>/api/</code>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="card">
    <div class="card-content">
        <h5 class="card-title">
            <i class="material-icons">bolt</i>
            Aksi Cepat
        </h5>
        <div class="quick-actions">
            <a href="../test_client.php" target="_blank" class="btn">
                <i class="material-icons left">bug_report</i>
                Test API
            </a>
            <a href="../install.php" class="btn btn-secondary">
                <i class="material-icons left">settings</i>
                Re-install
            </a>
            <a href="?page=reports" class="btn btn-primary">
                <i class="material-icons left">assessment</i>
                Buat Laporan
            </a>
        </div>
    </div>
</div>

<style>
code {
    background: var(--bg-surface-2);
    padding: 4px 8px;
    border-radius: 4px;
    font-family: 'Courier New', monospace;
    color: var(--primary-color);
    border: 1px solid var(--border-color);
}
</style>
