<?php
/**
 * RAS Admin Dashboard
 * Remote Assistance Support System - Admin Panel
 */

// Include configuration
require_once __DIR__ . '/../config/config.php';

// Simple session check for authentication (for MVP)
session_start();

$theme = $_COOKIE['ras_admin_theme'] ?? 'light';
$theme = in_array($theme, ['light', 'dark'], true) ? $theme : 'light';

// Check if logged in
if (!isset($_SESSION['admin_logged_in']) && !isset($_GET['login'])) {
    // For MVP bypass, we auto-set the admin session so logs don't say 'System'
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_username'] = 'admin';
    $_SESSION['admin_user_id'] = 1;
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT id, username, password, status FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] !== 'active') {
                $login_error = 'Akun Anda dinonaktifkan.';
            } else {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_username'] = $user['username'];
                $_SESSION['admin_user_id'] = $user['id'];
                
                // Update last login
                $update = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $update->execute([$user['id']]);
                
                header('Location: index.php');
                exit;
            }
        } else {
            // Fallback for hardcoded admin if DB doesn't match but matches MVP
            if ($username === 'admin' && $password === 'admin') {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_username'] = 'admin';
                $_SESSION['admin_user_id'] = 1;
                header('Location: index.php');
                exit;
            } else {
                $login_error = 'Username atau Password salah';
            }
        }
    } catch (PDOException $e) {
        $login_error = 'Database error';
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php?login=1');
    exit;
}

// Show login form if not logged in
if (!isset($_SESSION['admin_logged_in']) && !isset($_GET['skip_auth'])) {
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - RAS Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css" rel="stylesheet">
    <script>
        (function () {
            var theme = localStorage.getItem('ras_theme') || '<?php echo $theme; ?>';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    <style>
        body {
            font-family: 'Inter', 'Roboto', sans-serif;
            background:
                radial-gradient(circle at top left, rgba(102, 126, 234, 0.18), transparent 30%),
                radial-gradient(circle at bottom right, rgba(118, 75, 162, 0.18), transparent 28%),
                linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            color: #1f2937;
        }
        .login-container {
            background: rgba(255,255,255,0.96);
            border-radius: 16px;
            box-shadow: 0 24px 80px rgba(0,0,0,0.3);
            max-width: 420px;
            width: 90%;
            padding: 40px;
        }
        .login-header {
            text-align: center;
            margin-bottom: 32px;
        }
        .login-header i {
            font-size: 56px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .login-header h2 {
            font-weight: 600;
            margin: 12px 0 8px;
            color: #2d3436;
        }
        .login-header p {
            color: #6c757d;
            margin: 0;
        }
        .input-group {
            margin-bottom: 20px;
        }
        .input-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #2d3436;
            font-size: 14px;
        }
        .input-group input {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.2s ease;
        }
        .input-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.4);
        }
        .error-message {
            background: #ffebee;
            border-left: 4px solid #ff5252;
            padding: 14px 16px;
            margin-bottom: 20px;
            border-radius: 8px;
            color: #c62828;
            font-size: 14px;
        }
        .note {
            background: #f8f9fa;
            padding: 16px;
            border-radius: 10px;
            margin-top: 24px;
            font-size: 13px;
            color: #6c757d;
            border: 1px solid #e9ecef;
        }
        .note strong {
            color: #2d3436;
        }
        .theme-switch {
            position: fixed;
            top: 16px;
            right: 16px;
            z-index: 10;
        }
        .theme-toggle {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.18);
            background: rgba(255,255,255,0.92);
            color: #1f2937;
            cursor: pointer;
            box-shadow: 0 8px 24px rgba(0,0,0,0.16);
        }
        .theme-toggle .theme-label {
            font-size: 13px;
            font-weight: 600;
        }
        .theme-toggle .theme-icon {
            font-size: 18px;
        }
        html[data-theme="dark"] body {
            color: #e5eefb;
        }
        html[data-theme="dark"] .login-container {
            background: rgba(15, 23, 42, 0.94);
            color: #e5eefb;
            border: 1px solid rgba(255,255,255,0.08);
        }
        html[data-theme="dark"] .login-header h2,
        html[data-theme="dark"] .note strong,
        html[data-theme="dark"] .input-group label {
            color: #e5eefb;
        }
        html[data-theme="dark"] .login-header p,
        html[data-theme="dark"] .note,
        html[data-theme="dark"] .center-align a {
            color: #9fb0c7 !important;
        }
        html[data-theme="dark"] .input-group input {
            background: #0f172a;
            color: #e5eefb;
            border-color: #243244;
        }
        html[data-theme="dark"] .note {
            background: #111827;
            border-color: #243244;
        }
    </style>
</head>
<body>
    <div class="theme-switch">
        <button class="theme-toggle" id="themeToggle" type="button">
            <i class="material-icons theme-icon" id="themeIcon">light_mode</i>
            <span class="theme-label" id="themeLabel">Light</span>
        </button>
    </div>
    <div class="login-container">
        <div class="login-header">
            <i class="material-icons">admin_panel_settings</i>
            <h2>RAS Admin</h2>
            <p>Login ke Dashboard Admin</p>
        </div>

        <?php if (isset($login_error)): ?>
            <div class="error-message">
                <i class="material-icons tiny">error</i>
                <?php echo htmlspecialchars($login_error); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="input-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required placeholder="Masukkan username">
            </div>
            <div class="input-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required placeholder="Masukkan password">
            </div>
            <button type="submit" name="login" class="btn-login">
                <i class="material-icons" style="vertical-align: middle; margin-right: 8px;">login</i>
                Login
            </button>
        </form>

        <div class="note center-align">
            <strong>Demo Credentials:</strong><br>
            Username: admin<br>
            Password: admin
        </div>

        <div class="center-align" style="margin-top: 2rem;">
            <a href="?skip_auth=1" style="color: #667eea;">
                <i class="material-icons tiny">home</i>
                Kembali ke Beranda
            </a>
        </div>
    </div>
    <script>
        (function () {
            var btn = document.getElementById('themeToggle');
            var icon = document.getElementById('themeIcon');
            var label = document.getElementById('themeLabel');
            function setTheme(theme) {
                document.documentElement.setAttribute('data-theme', theme);
                localStorage.setItem('ras_theme', theme);
                document.cookie = 'ras_admin_theme=' + theme + '; path=/; max-age=31536000';
                icon.textContent = theme === 'dark' ? 'dark_mode' : 'light_mode';
                label.textContent = theme === 'dark' ? 'Dark' : 'Light';
            }
            setTheme(document.documentElement.getAttribute('data-theme') || 'light');
            btn.addEventListener('click', function () {
                setTheme(document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark');
            });
        })();
    </script>
</body>
</html>
    <?php
    exit;
}

// Get current page
$current_page = $_GET['page'] ?? 'dashboard';

// Load dashboard data
try {
    $pdo = getDbConnection();

    // Get quick stats
    $device_stats = ['total' => 0, 'online' => 0, 'offline' => 0, 'warning' => 0, 'critical' => 0];
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM devices GROUP BY status");
    while ($row = $stmt->fetch()) {
        $device_stats[$row['status']] = (int)$row['count'];
        $device_stats['total'] += (int)$row['count'];
    }

    // Get recent alerts count
    $alert_stats = ['open' => 0, 'critical' => 0];
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM alerts WHERE status = 'open'");
    $alert_stats['open'] = (int)$stmt->fetch()['count'];
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM alerts WHERE severity = 'critical' AND status = 'open'");
    $alert_stats['critical'] = (int)$stmt->fetch()['count'];

    // Get system info
    $system_info = [
        'php_version' => PHP_VERSION,
        'server_time' => date('Y-m-d H:i:s'),
        'db_version' => $pdo->query("SELECT VERSION() as v")->fetch()['v']
    ];

} catch (PDOException $e) {
    $db_error = $e->getMessage();
}

if ($current_page === 'devices' && ($_GET['export'] ?? '') === 'csv' && isset($_GET['device_id'])) {
    include 'pages/device_detail.php';
    exit;
}

if ($current_page === 'devices' && ($_GET['export'] ?? '') === 'log' && isset($_GET['device_id'])) {
    $device_id = $_GET['device_id'];
    $log_days = $_GET['log_days'] ?? '1';
    $format = $_GET['format'] ?? 'txt';
    
    $log_dir = __DIR__ . '/../logs/';
    $files_to_check = [];
    if ($log_days === 'all') {
        $files_to_check = glob($log_dir . 'ras_*.log');
        if (is_array($files_to_check)) {
            rsort($files_to_check);
        } else {
            $files_to_check = [];
        }
    } else {
        $days = (int)$log_days;
        for ($i = 0; $i < $days; $i++) {
            $files_to_check[] = $log_dir . 'ras_' . date('Y-m-d', strtotime("-$i days")) . '.log';
        }
    }
    
    $device_logs = [];
    foreach ($files_to_check as $log_file) {
        if (file_exists($log_file)) {
            $lines = file($log_file);
            if (is_array($lines)) {
                $lines = array_reverse($lines);
                foreach ($lines as $line) {
                    if (strpos($line, $device_id) !== false) {
                        $device_logs[] = trim($line);
                    }
                }
            }
        }
    }

    if ($format === 'csv' || $format === 'xls') {
        if ($format === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="device_' . htmlspecialchars($device_id) . '_logs.csv"');
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, ['Timestamp', 'Level', 'Message']);
        } else {
            header('Content-Type: application/vnd.ms-excel; charset=utf-8');
            header('Content-Disposition: attachment; filename="device_' . htmlspecialchars($device_id) . '_logs.xls"');
            echo "Timestamp\tLevel\tMessage\r\n";
        }
        
        foreach ($device_logs as $line) {
            $ts = ''; $lvl = ''; $msg = $line;
            if (preg_match('/^\[(.*?)\]\s+\[(.*?)\]\s+(.*)$/', $line, $matches)) {
                $ts = $matches[1];
                $lvl = $matches[2];
                $msg = $matches[3];
            }
            if ($format === 'csv') {
                fputcsv($out, [$ts, $lvl, $msg]);
            } else {
                echo $ts . "\t" . $lvl . "\t" . $msg . "\r\n";
            }
        }
        if ($format === 'csv') fclose($out);
    } else {
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="device_' . htmlspecialchars($device_id) . '_logs.txt"');
        if (empty($device_logs)) {
            echo "Tidak ada log untuk rentang waktu yang dipilih.\r\n";
        } else {
            echo implode("\r\n", $device_logs) . "\r\n";
        }
    }
    exit;
}

if ($current_page === 'reports' && ($_GET['export'] ?? '') === 'csv' && isset($pdo) && $pdo instanceof PDO) {
    $report_type = $_GET['type'] ?? 'daily';
    $device_id = $_GET['device'] ?? 'all';
    $date_from = $_GET['from'] ?? date('Y-m-d', strtotime('-7 days'));
    $date_to = $_GET['to'] ?? date('Y-m-d');

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

    $stmt = $pdo->prepare("
        SELECT * FROM metrics
        WHERE {$where_clause}
        ORDER BY timestamp ASC
    ");
    $stmt->execute($params);
    $metrics = $stmt->fetchAll();

    $device_filter = $device_id !== 'all' ? "AND a.device_id = ?" : "";
    $stmt = $pdo->prepare("
        SELECT a.*, d.hostname
        FROM alerts a
        INNER JOIN devices d ON d.device_id = a.device_id
        WHERE a.timestamp >= ? AND a.timestamp <= ? $device_filter
        ORDER BY a.timestamp ASC
    ");
    $alert_params = [$date_from . ' 00:00:00', $date_to . ' 23:59:59'];
    if ($device_id !== 'all') {
        $alert_params[] = $device_id;
    }
    $stmt->execute($alert_params);
    $alerts = $stmt->fetchAll();

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

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="ras_report_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Report Type', 'Device', 'Date From', 'Date To']);
    fputcsv($output, [$report_type, $device_id, $date_from, $date_to]);
    fputcsv($output, []);

    fputcsv($output, ['Summary Statistics']);
    fputcsv($output, ['Total Metrics', $stats['metrics_count']]);
    fputcsv($output, ['Total Alerts', $stats['alerts_count']]);
    fputcsv($output, ['Critical Alerts', $stats['critical_alerts']]);
    fputcsv($output, ['Average CPU', number_format($stats['avg_cpu'], 2) . '%']);
    fputcsv($output, ['Average Memory', number_format($stats['avg_memory'], 2) . '%']);
    fputcsv($output, ['Average Disk', number_format($stats['avg_disk'], 2) . '%']);
    fputcsv($output, []);

    fputcsv($output, ['Alerts']);
    fputcsv($output, ['Time', 'Hostname', 'Type', 'Severity', 'Message', 'Status']);
    foreach ($alerts as $alert) {
        fputcsv($output, [
            $alert['timestamp'],
            $alert['hostname'],
            $alert['alert_type'],
            $alert['severity'],
            $alert['message'],
            $alert['status']
        ]);
    }

    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ucfirst($current_page); ?> - RAS Admin Panel</title>

    <!-- Google Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/admin.css">
    <script>
        (function () {
            var theme = localStorage.getItem('ras_theme') || '<?php echo $theme; ?>';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
</head>
<body data-theme="<?php echo htmlspecialchars($theme); ?>">
    <div class="admin-wrapper">
        <!-- Sidebar -->
        <aside id="sidebar" class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <i class="material-icons">admin_panel_settings</i>
                    <span>RAS Admin</span>
                </div>
            </div>

            <nav class="sidebar-nav">
                <!-- Main Menu -->
                <div class="nav-section">
                    <div class="nav-section-title">Menu Utama</div>
                    <ul>
                        <li class="<?php echo $current_page === 'dashboard' ? 'active' : ''; ?>">
                            <a href="?page=dashboard">
                                <i class="material-icons">dashboard</i>
                                <span>Dashboard</span>
                            </a>
                        </li>
                        <li class="<?php echo $current_page === 'devices' ? 'active' : ''; ?>">
                            <a href="?page=devices">
                                <i class="material-icons">devices</i>
                                <span>Perangkat</span>
                            </a>
                        </li>
                        <li class="<?php echo $current_page === 'alerts' ? 'active' : ''; ?>">
                            <a href="?page=alerts">
                                <i class="material-icons">notifications</i>
                                <span>Alerts</span>
                                <?php if ($alert_stats['open'] > 0): ?>
                                <span class="badge"><?php echo $alert_stats['open']; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Reports & Settings -->
                <div class="nav-section">
                    <div class="nav-section-title">Laporan & Pengaturan</div>
                    <ul>
                        <li class="<?php echo $current_page === 'reports' ? 'active' : ''; ?>">
                            <a href="?page=reports">
                                <i class="material-icons">assessment</i>
                                <span>Laporan</span>
                            </a>
                        </li>
                        <li class="<?php echo $current_page === 'settings' ? 'active' : ''; ?>">
                            <a href="?page=settings">
                                <i class="material-icons">tune</i>
                                <span>Pengaturan</span>
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- System -->
                <div class="nav-section">
                    <div class="nav-section-title">Sistem</div>
                    <ul>
                        <li class="<?php echo $current_page === 'users' ? 'active' : ''; ?>">
                            <a href="?page=users">
                                <i class="material-icons">people</i>
                                <span>Pengguna</span>
                            </a>
                        </li>
                        <li class="<?php echo $current_page === 'logs' ? 'active' : ''; ?>">
                            <a href="?page=logs">
                                <i class="material-icons">description</i>
                                <span>System Logs</span>
                            </a>
                        </li>
                        <li class="<?php echo $current_page === 'help' ? 'active' : ''; ?>">
                            <a href="?page=help">
                                <i class="material-icons">help_outline</i>
                                <span>Bantuan</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <div class="sidebar-footer">
                <div class="user-info">
                    <i class="material-icons">account_circle</i>
                    <div>
                        <div class="user-name"><?php echo $_SESSION['admin_username'] ?? 'Admin'; ?></div>
                        <div class="user-role">Administrator</div>
                    </div>
                </div>
                <a href="?logout=1" class="btn-logout">
                    <i class="material-icons">logout</i>
                    <span>Logout</span>
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Bar -->
            <header class="top-bar">
                <div class="top-bar-left">
                    <button class="menu-toggle" id="menuToggle">
                        <i class="material-icons">menu</i>
                    </button>
                    <h1 class="page-title"><?php echo getPageTitle($current_page); ?></h1>
                </div>
                <div class="top-bar-right">
                    <button class="theme-toggle" id="themeToggle" type="button" title="Toggle theme">
                        <i class="material-icons theme-icon" id="themeIcon">light_mode</i>
                        <span class="theme-label" id="themeLabel">Light</span>
                    </button>
                    <?php if ($current_page === 'devices' && empty($_GET['device_id'])): ?>
                    <a href="?page=help" class="btn btn-sm btn-primary" style="border-radius: 8px; font-size: 13px; padding: 6px 12px; height: 32px; box-shadow: none; display: inline-flex; align-items: center; gap: 6px; text-decoration: none;">
                        <i class="material-icons" style="font-size: 16px;">add</i>
                        <span>Perangkat</span>
                    </a>
                    <?php endif; ?>
                    <div class="quick-stats">
                        <div class="quick-stat">
                            <i class="material-icons tiny">check_circle</i>
                            <span><?php echo $device_stats['online']; ?> Online</span>
                        </div>
                        <div class="quick-stat">
                            <i class="material-icons tiny">error</i>
                            <span><?php echo $alert_stats['critical']; ?> Critical</span>
                        </div>
                    </div>
                    <button class="refresh-btn" id="refreshBtn" title="Refresh">
                        <i class="material-icons">refresh</i>
                    </button>
                </div>
            </header>

            <!-- Page Content -->
            <main class="page-content">
                <?php if (isset($db_error)): ?>
                    <div class="alert alert-danger">
                        <i class="material-icons tiny">error</i>
                        Database Error: <?php echo htmlspecialchars($db_error); ?>
                    </div>
                <?php endif; ?>

                <?php
                // Load page content based on current page
                switch ($current_page) {
                    case 'dashboard':
                        include 'pages/dashboard_content.php';
                        break;
                    case 'devices':
                        if (isset($_GET['device_id'])) {
                            include 'pages/device_detail_content.php';
                        } else {
                            include 'pages/devices_content.php';
                        }
                        break;
                    case 'alerts':
                        include 'pages/alerts_content.php';
                        break;
                    case 'reports':
                        include 'pages/reports_content.php';
                        break;
                    case 'settings':
                        include 'pages/settings_content.php';
                        break;
                    case 'users':
                        include 'pages/users_content.php';
                        break;
                    case 'logs':
                        include 'pages/logs_content.php';
                        break;
                    case 'help':
                        include 'pages/help_content.php';
                        break;
                    default:
                        include 'pages/dashboard_content.php';
                }
                ?>
            </main>
        </div>
    </div>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Custom JS -->
    <script src="assets/js/admin.js"></script>
    <script>
        (function () {
            var btn = document.getElementById('themeToggle');
            if (!btn) return;
            var icon = document.getElementById('themeIcon');
            var label = document.getElementById('themeLabel');
            function setTheme(theme) {
                document.documentElement.setAttribute('data-theme', theme);
                document.body.setAttribute('data-theme', theme);
                localStorage.setItem('ras_theme', theme);
                document.cookie = 'ras_admin_theme=' + theme + '; path=/; max-age=31536000';
                icon.textContent = theme === 'dark' ? 'dark_mode' : 'light_mode';
                label.textContent = theme === 'dark' ? 'Dark' : 'Light';
            }
            setTheme(document.documentElement.getAttribute('data-theme') || 'light');
            btn.addEventListener('click', function () {
                setTheme(document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark');
            });
        })();
    </script>

    <?php
    function getPageTitle($page) {
        if ($page === 'devices' && !empty($_GET['device_id'])) {
            return 'Detail Perangkat';
        }
        $titles = [
            'dashboard' => 'Dashboard Overview',
            'devices' => 'Manajemen Perangkat',
            'alerts' => 'Alerts & Notifikasi',
            'reports' => 'Laporan Performa',
            'settings' => 'Pengaturan Sistem',
            'users' => 'Manajemen Pengguna',
            'logs' => 'System Logs',
            'help' => 'Bantuan & Dokumentasi'
        ];
        return $titles[$page] ?? 'Dashboard';
    }
    ?>
</body>
</html>
