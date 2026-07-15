<?php
require_once 'config/config.php';
try {
    $pdo = getDbConnection();
    $sql = "ALTER TABLE activity_logs ADD COLUMN username VARCHAR(50) DEFAULT 'System' AFTER timestamp";
    $pdo->exec($sql);
    echo "Column username added successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
