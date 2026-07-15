-- RAS Database Schema
-- Remote Assistance Support System
-- MySQL/MariaDB Database Schema

-- Create database if not exists
CREATE DATABASE IF NOT EXISTS ras_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ras_db;

-- Drop tables if exists (for fresh installation)
DROP TABLE IF EXISTS alerts;
DROP TABLE IF EXISTS metrics;
DROP TABLE IF EXISTS devices;

-- Devices table: stores registered devices information
CREATE TABLE devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id VARCHAR(100) UNIQUE NOT NULL,
    hostname VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    last_seen DATETIME NOT NULL,
    status ENUM('online', 'offline', 'warning', 'critical') DEFAULT 'offline',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_device_id (device_id),
    INDEX idx_status (status),
    INDEX idx_last_seen (last_seen)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Metrics table: stores device performance metrics
CREATE TABLE metrics (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    device_id VARCHAR(100) NOT NULL,
    timestamp DATETIME NOT NULL,
    cpu_usage DECIMAL(5,2) DEFAULT 0 COMMENT 'CPU usage percentage',
    memory_used BIGINT DEFAULT 0 COMMENT 'Memory used in bytes',
    memory_total BIGINT DEFAULT 0 COMMENT 'Total memory in bytes',
    disk_used BIGINT DEFAULT 0 COMMENT 'Disk used in bytes',
    disk_total BIGINT DEFAULT 0 COMMENT 'Total disk in bytes',
    disk_usage DECIMAL(5,2) DEFAULT 0 COMMENT 'Disk usage percentage',
    storage_health ENUM('healthy', 'warning', 'critical', 'unknown') DEFAULT 'unknown',
    network_status ENUM('good', 'degraded', 'down', 'unknown') DEFAULT 'unknown',
    additional_info JSON COMMENT 'Additional metrics in JSON format',
    FOREIGN KEY (device_id) REFERENCES devices(device_id) ON DELETE CASCADE,
    INDEX idx_device_timestamp (device_id, timestamp),
    INDEX idx_timestamp (timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Alerts table: stores system alerts and incidents
CREATE TABLE alerts (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    device_id VARCHAR(100) NOT NULL,
    timestamp DATETIME NOT NULL,
    alert_type ENUM('cpu', 'memory', 'disk', 'storage', 'network', 'system') NOT NULL,
    severity ENUM('info', 'warning', 'critical') DEFAULT 'info',
    message TEXT NOT NULL,
    status ENUM('open', 'acknowledged', 'resolved') DEFAULT 'open',
    snapshot_data JSON NULL COMMENT 'Process snapshot when alert was triggered',
    resolved_at DATETIME NULL,
    acknowledged_at DATETIME NULL,
    acknowledged_by VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (device_id) REFERENCES devices(device_id) ON DELETE CASCADE,
    INDEX idx_device_status (device_id, status),
    INDEX idx_severity (severity),
    INDEX idx_timestamp (timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Device Commands table: stores commands queued for agents
CREATE TABLE IF NOT EXISTS device_commands (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    device_id VARCHAR(100) NOT NULL,
    command VARCHAR(50) NOT NULL,
    status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    FOREIGN KEY (device_id) REFERENCES devices(device_id) ON DELETE CASCADE,
    INDEX idx_device_status (device_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create view for device summary
CREATE OR REPLACE VIEW v_device_summary AS
SELECT
    d.id,
    d.device_id,
    d.hostname,
    d.ip_address,
    d.status,
    d.last_seen,
    d.created_at,
    d.updated_at,
    (SELECT COUNT(*) FROM alerts a WHERE a.device_id = d.device_id AND a.status = 'open') AS open_alerts,
    (SELECT COUNT(*) FROM alerts a WHERE a.device_id = d.device_id AND a.severity = 'critical' AND a.status = 'open') AS critical_alerts
FROM devices d;

-- Create view for latest metrics
CREATE OR REPLACE VIEW v_latest_metrics AS
SELECT
    m.device_id,
    m.timestamp,
    m.cpu_usage,
    m.memory_used,
    m.memory_total,
    m.disk_used,
    m.disk_total,
    m.disk_usage,
    m.storage_health,
    m.network_status
FROM metrics m
INNER JOIN (
    SELECT device_id, MAX(timestamp) as max_timestamp
    FROM metrics
    GROUP BY device_id
) latest ON m.device_id = latest.device_id AND m.timestamp = latest.max_timestamp;

-- Settings table for system configuration
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT NOT NULL,
    setting_type ENUM('string', 'integer', 'boolean', 'json') DEFAULT 'string',
    description VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default settings
INSERT INTO settings (setting_key, setting_value, setting_type, description) VALUES
('email_enabled', 'false', 'boolean', 'Enable email notifications'),
('email_smtp_host', 'smtp.gmail.com', 'string', 'SMTP server hostname'),
('email_smtp_port', '587', 'integer', 'SMTP server port'),
('email_smtp_secure', 'tls', 'string', 'SMTP security (tls/ssl)'),
('email_from_address', 'noreply@ras.local', 'string', 'From email address'),
('email_from_name', 'RAS Monitor', 'string', 'From email name'),
('email_to_address', '', 'string', 'Recipient email for alerts'),
('alert_threshold_cpu', '90', 'integer', 'CPU alert threshold percentage'),
('alert_threshold_memory', '90', 'integer', 'Memory alert threshold percentage'),
('alert_threshold_disk', '90', 'integer', 'Disk alert threshold percentage'),
('device_offline_minutes', '5', 'integer', 'Minutes before device marked offline'),
('metrics_retention_days', '30', 'integer', 'Days to retain metrics history'),
('dashboard_refresh_seconds', '30', 'integer', 'Dashboard auto-refresh interval');
