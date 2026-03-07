<?php
require_once __DIR__ . '/../config/database.php';

$database = new Database();
$db = $database->getConnection();

try {
    $sql = "CREATE TABLE IF NOT EXISTS sms_log (
        id INT PRIMARY KEY AUTO_INCREMENT,
        phone_number VARCHAR(20) NOT NULL,
        message TEXT NOT NULL,
        direction ENUM('incoming', 'outgoing') NOT NULL,
        status ENUM('pending', 'sent', 'delivered', 'failed', 'processed', 'invalid', 'error') DEFAULT 'pending',
        report_id INT,
        error_message TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_sms_phone (phone_number),
        INDEX idx_sms_direction (direction),
        INDEX idx_sms_status (status),
        INDEX idx_sms_report (report_id)
    )";
    $db->exec($sql);
    echo "sms_log table created successfully!\n";
    
    $sql = "CREATE TABLE IF NOT EXISTS sms_reports (
        id INT PRIMARY KEY AUTO_INCREMENT,
        report_id INT,
        phone_number VARCHAR(20) NOT NULL,
        symptoms TEXT,
        location VARCHAR(255),
        severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
        status ENUM('received', 'acknowledged', 'investigated', 'resolved') DEFAULT 'received',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_sms_reports_phone (phone_number),
        INDEX idx_sms_reports_status (status)
    )";
    $db->exec($sql);
    echo "sms_reports table created successfully!\n";
    
    $sql = "CREATE TABLE IF NOT EXISTS sms_subscriptions (
        id INT PRIMARY KEY AUTO_INCREMENT,
        phone_number VARCHAR(20) NOT NULL UNIQUE,
        subscription_type ENUM('alerts', 'updates', 'both') DEFAULT 'both',
        preferred_language ENUM('en', 'sw') DEFAULT 'en',
        county VARCHAR(100),
        active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_subscriptions_phone (phone_number),
        INDEX idx_subscriptions_type (subscription_type),
        INDEX idx_subscriptions_county (county)
    )";
    $db->exec($sql);
    echo "sms_subscriptions table created successfully!\n";
    
    echo "\nAll SMS tables created successfully!\n";
    echo "Now configure your SMS provider in config/sms_config.php\n";
    echo "Then set up the webhook URL in your Africa's Talking/Twilio dashboard:\n";
    echo "URL: https://yourdomain.com/chmews/api/sms_incoming.php\n";
    
} catch (PDOException $e) {
    echo "Error creating tables: " . $e->getMessage() . "\n";
}
