<?php
/**
 * Help & Documentation Content Page
 */
?>

<div class="row">
    <div class="col s12 m8">
        <!-- Quick Start -->
        <div class="card">
            <div class="card-content">
                <h5 class="card-title">
                    <i class="material-icons">rocket_launch</i>
                    Quick Start
                </h5>
                <ol class="help-list">
                    <li>
                        <strong>Setup Agent Python</strong><br>
                        Install agent Python pada perangkat yang ingin dimonitor. Lihat dokumentasi <code>MVP_Client_Python_Agent.md</code>
                    </li>
                    <li>
                        <strong>Konfigurasi API Key</strong><br>
                        Gunakan API key yang di-generate saat instalasi untuk menghubungkan agent ke dashboard
                    </li>
                    <li>
                        <strong>Test Koneksi</strong><br>
                        Gunakan <a href="../test_client.php" target="_blank">Test API Client</a> untuk memastikan koneksi berjalan
                    </li>
                    <li>
                        <strong>Monitor Dashboard</strong><br>
                        Lihat data real-time di halaman Dashboard
                    </li>
                </ol>
            </div>
        </div>

        <!-- API Documentation -->
        <div class="card">
            <div class="card-content">
                <h5 class="card-title">
                    <i class="material-icons">api</i>
                    API Endpoint
                </h5>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Endpoint</th>
                                <th>Method</th>
                                <th>Deskripsi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>/api/metrics.php</code></td>
                                <td>POST</td>
                                <td>Kirim data metrik dari agent</td>
                            </tr>
                            <tr>
                                <td><code>/api/devices.php</code></td>
                                <td>GET</td>
                                <td>Daftar semua perangkat</td>
                            </tr>
                            <tr>
                                <td><code>/api/devices.php?id={id}</code></td>
                                <td>GET</td>
                                <td>Detail perangkat tertentu</td>
                            </tr>
                            <tr>
                                <td><code>/api/alerts.php</code></td>
                                <td>GET</td>
                                <td>Daftar semua alert</td>
                            </tr>
                            <tr>
                                <td><code>/api/dashboard.php</code></td>
                                <td>GET</td>
                                <td>Summary dashboard</td>
                            </tr>
                            <tr>
                                <td><code>/api/settings.php</code></td>
                                <td>GET/PUT</td>
                                <td>Konfigurasi sistem</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Alert Thresholds -->
        <div class="card">
            <div class="card-content">
                <h5 class="card-title">
                    <i class="material-icons">tune</i>
                    Alert Thresholds
                </h5>
                <p>Threshold default untuk memicu alert:</p>
                <ul>
                    <li><strong>CPU:</strong> 90% - Critical</li>
                    <li><strong>Memory:</strong> 90% - Critical</li>
                    <li><strong>Disk:</strong> 85% - Critical</li>
                    <li><strong>Offline:</strong> 5 menit tanpa data</li>
                </ul>
                <small class="text-muted">Dapat diubah di halaman Settings</small>
            </div>
        </div>
    </div>

    <div class="col s12 m4">
        <!-- Resources -->
        <div class="card">
            <div class="card-content">
                <h5 class="card-title">
                    <i class="material-icons">folder</i>
                    Dokumentasi
                </h5>
                <div class="help-link-list">
                    <a href="../MVP_Admin_PHP_MySQL.md" target="_blank" class="btn btn-sm help-link-btn">
                        <i class="material-icons left">description</i>
                        Admin Dashboard Specs
                    </a>
                    <a href="../MVP_Client_Python_Agent.md" target="_blank" class="btn btn-sm help-link-btn">
                        <i class="material-icons left">description</i>
                        Python Agent Specs
                    </a>
                    <a href="../IMPLEMENTATION_SUMMARY.md" target="_blank" class="btn btn-sm help-link-btn">
                        <i class="material-icons left">description</i>
                        Implementation Summary
                    </a>
                    <a href="../README.md" target="_blank" class="btn btn-sm btn-primary help-link-btn">
                        <i class="material-icons left">home</i>
                        Main README
                    </a>
                </div>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="card">
            <div class="card-content">
                <h5 class="card-title">
                    <i class="material-icons">link</i>
                    Quick Links
                </h5>
                <div class="help-link-list">
                    <a href="../test_client.php" target="_blank" class="btn btn-sm help-link-btn">
                        <i class="material-icons left">bug_report</i>
                        Test API Client
                    </a>
                    <a href="../install.php" class="btn btn-sm btn-secondary help-link-btn">
                        <i class="material-icons left">settings</i>
                        Re-install System
                    </a>
                    <a href="?page=settings" class="btn btn-sm help-link-btn">
                        <i class="material-icons left">tune</i>
                        System Settings
                    </a>
                </div>
            </div>
        </div>

        <!-- System Info -->
        <div class="card">
            <div class="card-content">
                <h5 class="card-title">
                    <i class="material-icons">info</i>
                    System Info
                </h5>
                <table class="data-table help-info-table">
                    <tbody>
                        <tr>
                            <td>Version</td>
                            <td>1.0.0</td>
                        </tr>
                        <tr>
                            <td>PHP</td>
                            <td><?php echo PHP_VERSION; ?></td>
                        </tr>
                        <tr>
                            <td>Server</td>
                            <td><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
.help-list {
    line-height: 1.9;
    padding-left: 18px;
}

.help-link-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.help-link-btn {
    justify-content: flex-start;
    text-align: left;
}

.help-info-table {
    font-size: 0.875rem;
}

code {
    background: var(--bg-surface-2);
    padding: 4px 8px;
    border-radius: 4px;
    font-family: 'Courier New', monospace;
    color: var(--primary-color);
    font-size: 0.875rem;
    border: 1px solid var(--border-color);
}
</style>
