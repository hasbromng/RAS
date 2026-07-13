<?php
/**
 * RAS Dashboard Installation Script
 * Simple installer for database setup and configuration
 */

// Prevent access if already installed
if (file_exists(__DIR__ . '/config/installed.lock')) {
    die('<h1>Already Installed</h1><p>The RAS Dashboard has already been installed.</p><p>To reinstall, delete <code>config/installed.lock</code> and run this script again.</p><p><a href="index.php">Go to Dashboard</a></p>');
}

// Step tracking
$step = $_GET['step'] ?? 1;
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step == 1) {
        // Database configuration
        $db_host = $_POST['db_host'] ?? 'localhost';
        $db_name = $_POST['db_name'] ?? 'ras_db';
        $db_user = $_POST['db_user'] ?? 'root';
        $db_pass = $_POST['db_pass'] ?? '';

        // Test connection
        try {
            $dsn = "mysql:host=$db_host;charset=utf8mb4";
            $pdo = new PDO($dsn, $db_user, $db_pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Create database if not exists
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `$db_name`");

            // Read and execute schema
            $schema = file_get_contents(__DIR__ . '/../database/ras_schema.sql');
            $pdo->exec($schema);

            // Generate API key
            $api_key = bin2hex(random_bytes(16));

            // Update config file
            $config_content = file_get_contents(__DIR__ . '/../config/config.php');
            $config_content = str_replace("define('DB_HOST', 'localhost')", "define('DB_HOST', '$db_host')", $config_content);
            $config_content = str_replace("define('DB_NAME', 'ras_db')", "define('DB_NAME', '$db_name')", $config_content);
            $config_content = str_replace("define('DB_USER', 'root')", "define('DB_USER', '$db_user')", $config_content);
            $config_content = str_replace("define('DB_PASS', '')", "define('DB_PASS', '$db_pass')", $config_content);
            $config_content = str_replace("define('API_KEY', 'change-this-to-secure-key')", "define('API_KEY', '$api_key')", $config_content);

            file_put_contents(__DIR__ . '/../config/config.php', $config_content);

            // Store session data for next step
            session_start();
            $_SESSION['install_api_key'] = $api_key;

            // Redirect to step 2
            header('Location: install.php?step=2');
            exit;

        } catch (PDOException $e) {
            $error = 'Database connection failed: ' . $e->getMessage();
        }
    } elseif ($step == 2) {
        // Create admin user and finalize
        session_start();

        // Create lock file
        file_put_contents(__DIR__ . '/../config/installed.lock', date('Y-m-d H:i:s'));

        $success = 'Installation completed successfully!';

        // Redirect to dashboard after delay
        header('refresh:3;url=index.php');
    }
}

// Page HTML
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RAS Dashboard Installation</title>
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
        .install-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            max-width: 600px;
            width: 90%;
            padding: 2rem;
        }
        .install-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .install-header i {
            font-size: 4rem;
            color: #667eea;
        }
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
            position: relative;
        }
        .step-indicator::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 2px;
            background: #e0e0e0;
            z-index: 0;
        }
        .step {
            text-align: center;
            position: relative;
            z-index: 1;
            background: white;
            padding: 0 10px;
        }
        .step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 5px;
            font-weight: 500;
        }
        .step.active .step-circle {
            background: #667eea;
            color: white;
        }
        .step.completed .step-circle {
            background: #4caf50;
            color: white;
        }
        .error-message {
            background: #ffebee;
            border-left: 4px solid #f44336;
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 4px;
        }
        .success-message {
            background: #e8f5e9;
            border-left: 4px solid #4caf50;
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 4px;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #333;
        }
        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 4px;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.3);
        }
        .requirements {
            background: #f5f5f5;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1.5rem;
        }
        .requirements ul {
            margin: 0;
            padding-left: 1.5rem;
        }
        .requirements li {
            margin-bottom: 0.5rem;
        }
        .requirements li i {
            font-size: 1rem;
            vertical-align: middle;
        }
    </style>
</head>
<body>
    <div class="install-container">
        <div class="install-header">
            <i class="material-icons">dashboard</i>
            <h1>RAS Dashboard Installation</h1>
            <p>Follow the steps to set up your dashboard</p>
        </div>

        <?php if ($error): ?>
            <div class="error-message">
                <i class="material-icons">error</i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success-message">
                <i class="material-icons">check_circle</i>
                <?php echo htmlspecialchars($success); ?>
            </div>
            <div style="text-align: center;">
                <p>Redirecting to dashboard...</p>
                <p><a href="index.php">Click here if not redirected automatically</a></p>
            </div>
        <?php else: ?>

            <div class="step-indicator">
                <div class="step <?php echo $step >= 1 ? 'active' : ''; ?>">
                    <div class="step-circle"><?php echo $step > 1 ? '✓' : '1'; ?></div>
                    <div>Database</div>
                </div>
                <div class="step <?php echo $step >= 2 ? 'active' : ''; ?>">
                    <div class="step-circle"><?php echo $step > 2 ? '✓' : '2'; ?></div>
                    <div>Complete</div>
                </div>
            </div>

            <?php if ($step == 1): ?>
                <div class="requirements">
                    <strong>Requirements:</strong>
                    <ul>
                        <li><i class="material-icons tiny" style="color: #4caf50;">check_circle</i> PHP 7.4 or higher</li>
                        <li><i class="material-icons tiny" style="color: #4caf50;">check_circle</i> MySQL/MariaDB 5.7 or higher</li>
                        <li><i class="material-icons tiny" style="color: #4caf50;">check_circle</i> PDO PHP Extension</li>
                        <li><i class="material-icons tiny" style="color: #4caf50;">check_circle</i> Write permissions for config/ and logs/</li>
                    </ul>
                </div>

                <form method="POST">
                    <div class="form-group">
                        <label for="db_host">Database Host</label>
                        <input type="text" id="db_host" name="db_host" value="localhost" required>
                    </div>
                    <div class="form-group">
                        <label for="db_name">Database Name</label>
                        <input type="text" id="db_name" name="db_name" value="ras_db" required>
                    </div>
                    <div class="form-group">
                        <label for="db_user">Database Username</label>
                        <input type="text" id="db_user" name="db_user" value="root" required>
                    </div>
                    <div class="form-group">
                        <label for="db_pass">Database Password</label>
                        <input type="password" id="db_pass" name="db_pass" value="">
                        <small style="color: #666;">Leave empty if no password</small>
                    </div>
                    <button type="submit" class="btn-primary" style="width: 100%;">
                        Install Database & Continue
                    </button>
                </form>

            <?php elseif ($step == 2): ?>
                <div style="text-align: center;">
                    <i class="material-icons" style="font-size: 4rem; color: #4caf50;">check_circle</i>
                    <h3>Installation Complete!</h3>
                    <p>Your RAS Dashboard has been successfully installed.</p>

                    <?php if (isset($_SESSION['install_api_key'])): ?>
                        <div style="background: #fff3e0; border-left: 4px solid #ff9800; padding: 1rem; margin: 1.5rem 0; text-align: left; border-radius: 4px;">
                            <strong>Important: Save your API Key</strong>
                            <p style="font-family: monospace; background: white; padding: 0.5rem; margin: 0.5rem 0; word-break: break-all;"><?php echo $_SESSION['install_api_key']; ?></p>
                            <small>This key is required for your Python agents to send metrics.</small>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <button type="submit" class="btn-primary">
                            Go to Dashboard
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
</body>
</html>
