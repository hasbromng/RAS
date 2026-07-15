<?php
require_once 'config/config.php';
try {
    $pdo = getDbConnection();
    
    // Check if users table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    $exists = $stmt->fetch();
    
    if (!$exists) {
        $sql = "CREATE TABLE users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            email VARCHAR(100) NULL,
            role VARCHAR(50) DEFAULT 'Administrator',
            status VARCHAR(20) DEFAULT 'active',
            last_login DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )";
        $pdo->exec($sql);
        
        // insert default admin
        $hash = password_hash('admin', PASSWORD_DEFAULT);
        $pdo->exec("INSERT INTO users (username, password, email, role, status) VALUES ('admin', '$hash', 'admin@ras.local', 'Administrator', 'active')");
        echo "Table users created and default admin inserted.\n";
    } else {
        echo "Table users already exists.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
