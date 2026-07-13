<?php
/**
 * RAS - Remote Assistance Support System
 * Main index page - redirects to appropriate sections
 */

// Simple router based on URL parameter
$page = $_GET['p'] ?? 'home';

// Page routing
switch ($page) {
    case 'admin':
        // Redirect to admin dashboard
        header('Location: admin/index.php');
        exit;
        break;

    case 'install':
        // Redirect to installer
        header('Location: admin/install.php');
        exit;
        break;

    case 'test':
        // Redirect to test client
        header('Location: admin/test_client.php');
        exit;
        break;

    default:
        // Show landing page
        ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RAS - Remote Assistance Support System</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
        }
        .landing-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
            max-width: 900px;
            width: 90%;
            padding: 3rem;
        }
        .header {
            text-align: center;
            margin-bottom: 3rem;
        }
        .header i {
            font-size: 5rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .header h1 {
            font-size: 2.5rem;
            font-weight: 300;
            margin: 1rem 0;
            color: #333;
        }
        .header p {
            color: #666;
            font-size: 1.1rem;
        }
        .cards-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .card {
            border-radius: 8px;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }
        .card-content {
            text-align: center;
            padding: 2rem !important;
        }
        .card-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        .card-icon.admin { color: #667eea; }
        .card-icon.install { color: #26a69a; }
        .card-icon.docs { color: #ff9800; }
        .card-title {
            font-size: 1.2rem !important;
            font-weight: 500 !important;
            margin-bottom: 0.5rem !important;
        }
        .card-desc {
            color: #666;
            font-size: 0.9rem;
        }
        .status-section {
            background: #f5f5f5;
            border-radius: 8px;
            padding: 1.5rem;
            margin-top: 2rem;
        }
        .status-item {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        .status-item i {
            margin-right: 0.5rem;
        }
        .status-item.valid i { color: #4caf50; }
        .status-item.invalid i { color: #f44336; }
        .footer {
            text-align: center;
            margin-top: 2rem;
            color: #666;
            font-size: 0.9rem;
        }
        .btn-start {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 1rem 3rem;
            border-radius: 30px;
            color: white;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-block;
            text-decoration: none;
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.3);
        }
        .btn-start:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(102, 126, 234, 0.4);
        }
        .hero-btn {
            text-align: center;
            margin: 2rem 0;
        }
    </style>
</head>
<body>
    <div class="landing-container">
        <div class="header">
            <i class="material-icons">support_agent</i>
            <h1>RAS</h1>
            <p>Remote Assistance Support System</p>
        </div>

        <div class="hero-btn">
            <a href="?p=admin" class="btn-start">
                <i class="material-icons left">dashboard</i>
                Buka Dashboard Admin
            </a>
        </div>

        <div class="cards-container">
            <div class="card" onclick="window.location.href='?p=admin'">
                <div class="card-content">
                    <i class="material-icons card-icon admin">dashboard</i>
                    <div class="card-title">Dashboard Admin</div>
                    <div class="card-desc">Monitoring perangkat, alert, dan laporan performa sistem</div>
                </div>
            </div>

            <div class="card" onclick="window.location.href='?p=install'">
                <div class="card-content">
                    <i class="material-icons card-icon install">settings</i>
                    <div class="card-title">Instalasi</div>
                    <div class="card-desc">Setup database dan konfigurasi awal sistem</div>
                </div>
            </div>

            <div class="card" onclick="window.location.href='?p=test'">
                <div class="card-content">
                    <i class="material-icons card-icon docs">bug_report</i>
                    <div class="card-title">Test API</div>
                    <div class="card-desc">Test koneksi API dan kirim data dummy</div>
                </div>
            </div>

            <div class="card" onclick="window.open('MVP_Admin_PHP_MySQL.md')">
                <div class="card-content">
                    <i class="material-icons card-icon docs">description</i>
                    <div class="card-title">Dokumentasi</div>
                    <div class="card-desc">Panduan teknis dan spesifikasi sistem</div>
                </div>
            </div>
        </div>

        <div class="status-section">
            <h6>Status Sistem</h6>
            <?php
            // Check system status
            $checks = [
                'PHP Version' => version_compare(PHP_VERSION, '7.4', '>='),
                'PDO Extension' => extension_loaded('pdo') && extension_loaded('pdo_mysql'),
                'Config File' => file_exists(__DIR__ . '/config/config.php'),
                'Database Schema' => file_exists(__DIR__ . '/database/ras_schema.sql'),
                'Admin Dashboard' => file_exists(__DIR__ . '/admin/index.php'),
                'Installed' => file_exists(__DIR__ . '/config/installed.lock')
            ];

            foreach ($checks as $name => $status) {
                $class = $status ? 'valid' : 'invalid';
                $icon = $status ? 'check_circle' : 'cancel';
                echo "<div class='status-item {$class}'>";
                echo "<i class='material-icons'>{$icon}</i>";
                echo "<span>{$name}: " . ($status ? 'OK' : 'Missing') . "</span>";
                echo "</div>";
            }
            ?>
        </div>

        <div class="footer">
            <p>RAS v1.0.0 - Remote Assistance Support System</p>
            <p>PHP + MySQL Dashboard dengan Material Design</p>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
</body>
</html>
        <?php
        break;
}
?>
