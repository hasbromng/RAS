<?php
/**
 * RAS Configuration File
 * Remote Assistance Support System
 */

// Prevent direct access
if (!defined('RAS_INCLUDED')) {
    define('RAS_INCLUDED', true);
}

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'ras_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Application Configuration
define('APP_NAME', 'RAS Dashboard');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/RAS/admin');
define('API_KEY', 'change-this-to-secure-key');

// Timezone
date_default_timezone_set('Asia/Jakarta');

// Session Configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS

// Error Reporting (Set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log Configuration
define('LOG_PATH', __DIR__ . '/../logs/');
define('LOG_FILE', LOG_PATH . 'ras_' . date('Y-m-d') . '.log');

/**
 * Get database connection
 *
 * @return PDO
 */
function getDbConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        seedDefaultSettings($pdo);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        die("Database connection failed. Please check configuration.");
    }
}

/**
 * Log message to file
 *
 * @param string $message Log message
 * @param string $level Log level (INFO, WARNING, ERROR)
 */
function logMessage($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;

    // Create log directory if not exists
    if (!is_dir(LOG_PATH)) {
        mkdir(LOG_PATH, 0755, true);
    }

    // Append to log file
    file_put_contents(LOG_FILE, $logEntry, FILE_APPEND);
}

/**
 * Get system setting
 *
 * @param PDO $pdo Database connection
 * @param string $key Setting key
 * @param mixed $default Default value if not found
 * @return mixed
 */
function getSetting($pdo, $key, $default = null) {
    try {
        $stmt = $pdo->prepare("SELECT setting_value, setting_type FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();

        if ($result) {
            $value = $result['setting_value'];
            $type = $result['setting_type'];

            switch ($type) {
                case 'integer':
                    return (int) $value;
                case 'boolean':
                    return filter_var($value, FILTER_VALIDATE_BOOLEAN);
                case 'json':
                    return json_decode($value, true);
                default:
                    return $value;
            }
        }

        return $default;
    } catch (PDOException $e) {
        logMessage("Error getting setting {$key}: " . $e->getMessage(), 'ERROR');
        return $default;
    }
}

/**
 * Set system setting
 *
 * @param PDO $pdo Database connection
 * @param string $key Setting key
 * @param mixed $value Setting value
 * @param string $type Value type
 * @param string $description Setting description
 * @return bool
 */
function setSetting($pdo, $key, $value, $type = 'string', $description = null) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO settings (setting_key, setting_value, setting_type, description)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value),
            setting_type = VALUES(setting_type),
            description = VALUES(description),
            updated_at = CURRENT_TIMESTAMP
        ");

        return $stmt->execute([$key, $value, $type, $description]);
    } catch (PDOException $e) {
        logMessage("Error setting setting {$key}: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * Ensure default settings rows exist in the database without overwriting custom values.
 *
 * @param PDO $pdo Database connection
 * @return void
 */
function seedDefaultSettings($pdo) {
    static $seeded = false;
    if ($seeded) {
        return;
    }

    $seeded = true;

    try {
        $defaults = [
            ['email_enabled', 'false', 'boolean', 'Enable email notifications'],
            ['email_smtp_host', 'smtp.gmail.com', 'string', 'SMTP server hostname'],
            ['email_smtp_port', '587', 'integer', 'SMTP server port'],
            ['email_smtp_secure', 'tls', 'string', 'SMTP security (tls/ssl)'],
            ['email_from_address', 'noreply@ras.local', 'string', 'From email address'],
            ['email_from_name', 'RAS Monitor', 'string', 'From email name'],
            ['email_to_address', '', 'string', 'Recipient email for alerts'],
            ['alert_threshold_cpu', '90', 'integer', 'CPU alert threshold percentage'],
            ['alert_threshold_memory', '90', 'integer', 'Memory alert threshold percentage'],
            ['alert_threshold_disk', '90', 'integer', 'Disk alert threshold percentage'],
            ['device_offline_minutes', '5', 'integer', 'Minutes before device marked offline'],
            ['metrics_retention_days', '30', 'integer', 'Days to retain metrics history'],
            ['dashboard_refresh_seconds', '30', 'integer', 'Dashboard auto-refresh interval'],
        ];

        $stmt = $pdo->prepare("
            INSERT IGNORE INTO settings (setting_key, setting_value, setting_type, description)
            VALUES (?, ?, ?, ?)
        ");

        foreach ($defaults as $row) {
            $stmt->execute($row);
        }
    } catch (PDOException $e) {
        logMessage("Error seeding default settings: " . $e->getMessage(), 'ERROR');
    }
}

/**
 * Send JSON response
 *
 * @param array $data Response data
 * @param int $statusCode HTTP status code
 */
function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Validate API key
 *
 * @return bool
 */
function validateApiKey() {
    $headers = getallheaders();
    $providedKey = '';

    // Check in $_SERVER first
    if (isset($_SERVER['HTTP_X_API_KEY'])) {
        $providedKey = $_SERVER['HTTP_X_API_KEY'];
    } else {
        // Fallback to headers with case-insensitive check
        $headers = array_change_key_case($headers, CASE_UPPER);
        if (isset($headers['X-API-KEY'])) {
            $providedKey = $headers['X-API-KEY'];
        } elseif (isset($headers['AUTHORIZATION'])) {
            $auth = $headers['AUTHORIZATION'];
            if (preg_match('/Bearer\s+(.*)$/i', $auth, $matches)) {
                $providedKey = $matches[1];
            }
        } elseif (isset($_GET['api_key'])) {
            $providedKey = $_GET['api_key'];
        }
    }

    return hash_equals(API_KEY, $providedKey);
}
