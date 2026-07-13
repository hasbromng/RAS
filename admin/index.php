<?php
/**
 * RAS Admin Dashboard
 * Remote Assistance Support System - Admin Panel
 */

// Include configuration
require_once __DIR__ . '/../config/config.php';

// Simple session check for authentication (for MVP)
session_start();

// Check if logged in
if (!isset($_SESSION['admin_logged_in']) && !isset($_GET['login'])) {
    // For MVP, allow direct access with simple login form
    // In production, implement proper authentication
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    // Simple authentication for MVP (in production, use proper auth)
    if ($username === 'admin' && $password === 'admin') {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = 'Admin';
        header('Location: index.php');
        exit;
    } else {
        $login_error = 'Invalid credentials';
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
    <style>
        body {
            font-family: 'Inter', 'Roboto', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
        }
        .login-container {
            background: white;
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
    </style>
</head>
<body>
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
</head>
<body>
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
                                <?php if ($device_stats['total'] > 0): ?>
                                <span class="badge"><?php echo $device_stats['total']; ?></span>
                                <?php endif; ?>
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
                        if (isset($_GET['device_id']) && ($_GET['export'] ?? '') === 'csv') {
                            // CSV export — device_detail.php handles output and calls exit
                            include 'pages/device_detail.php';
                        } elseif (isset($_GET['device_id'])) {
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
