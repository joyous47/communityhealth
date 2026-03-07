<?php


require_once __DIR__ . '/../config/database.php';

$database = new Database();
$db = $database->getConnection();

try {
    
    $sql = "CREATE TABLE IF NOT EXISTS activity_log (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        action VARCHAR(50) NOT NULL,
        module VARCHAR(50) NOT NULL,
        details TEXT,
        ip_address VARCHAR(45),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_activity_user (user_id),
        INDEX idx_activity_action (action),
        INDEX idx_activity_module (module),
        INDEX idx_activity_created (created_at)
    )";
    $db->exec($sql);
    echo "activity_log table created successfully!\n";
    
    echo "\nActivity logging tables created successfully!\n";
    echo "Now you can include the activity_logger in your pages:\n";
    echo "require_once 'includes/activity_logger.php';\n";
    echo "logActivity(\$userId, 'ACTION', 'MODULE', 'Details');\n";
    
} catch (PDOException $e) {
    echo "Error creating tables: " . $e->getMessage() . "\n";
}
