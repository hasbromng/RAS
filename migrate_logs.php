<?php
require_once 'config/config.php';
try {
    $pdo = getDbConnection();
    $sql = "CREATE TABLE IF NOT EXISTS activity_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        action VARCHAR(100) NOT NULL,
        description TEXT NOT NULL,
        level VARCHAR(20) DEFAULT 'INFO',
        ip_address VARCHAR(45) NULL
    )";
    $pdo->exec($sql);
    echo "Table activity_logs created successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
