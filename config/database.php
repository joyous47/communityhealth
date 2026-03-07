<?php
class Database {
    private $host = "127.0.0.1";
    private $port = "3306";
    private $db_name = "chmwes_db";
    private $username = "root";
    private $password = "";
    public $conn;
    
    public function getConnection() {
        $this->conn = null;
        
        try {
            $dsn = "mysql:host=" . $this->host . ";port=" . $this->port . ";dbname=" . $this->db_name;
            
            $this->conn = new PDO(
                $dsn,
                $this->username,
                $this->password,
                array(
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                )
            );
            
            return $this->conn;
            
        } catch(PDOException $exception) {
            if (strpos($exception->getMessage(), "Unknown database") !== false) {
                return $this->createDatabaseAndConnect();
            } else {
                $this->showDetailedError($exception->getMessage());
                die();
            }
        }
    }
    
    private function createDatabaseAndConnect() {
        try {
            $dsn = "mysql:host=" . $this->host . ";port=" . $this->port;
            $this->conn = new PDO($dsn, $this->username, $this->password);
            
            $sql = "CREATE DATABASE IF NOT EXISTS `" . $this->db_name . "` 
                    CHARACTER SET utf8mb4 
                    COLLATE utf8mb4_general_ci";
            $this->conn->exec($sql);
            
            $dsn = "mysql:host=" . $this->host . ";port=" . $this->port . ";dbname=" . $this->db_name;
            $this->conn = new PDO(
                $dsn,
                $this->username,
                $this->password,
                array(
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                )
            );
            
            $this->createTables();
            return $this->conn;
            
        } catch(PDOException $e) {
            $this->showDetailedError($e->getMessage());
            die();
        }
    }
    
    private function createTables() {
        try {
            $sql = "CREATE TABLE IF NOT EXISTS users (
                id INT PRIMARY KEY AUTO_INCREMENT,
                username VARCHAR(100) NOT NULL,
                email VARCHAR(100) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                role ENUM('citizen', 'health_worker', 'admin') NOT NULL,
                phone_number VARCHAR(20),
                preferred_language ENUM('en', 'sw') DEFAULT 'en',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_users_email (email),
                INDEX idx_users_role (role)
            )";
            $this->conn->exec($sql);
            
            $sql = "CREATE TABLE IF NOT EXISTS locations (
                location_id INT PRIMARY KEY AUTO_INCREMENT,
                location_name VARCHAR(255) NOT NULL,
                latitude DECIMAL(10,8),
                longitude DECIMAL(11,8),
                county VARCHAR(100),
                sub_county VARCHAR(100),
                ward VARCHAR(100),
                risk_level ENUM('low', 'medium', 'high') DEFAULT 'low',
                population INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_locations_county (county),
                INDEX idx_locations_risk (risk_level)
            )";
            $this->conn->exec($sql);
            
            $sql = "CREATE TABLE IF NOT EXISTS reports (
                id INT PRIMARY KEY AUTO_INCREMENT,
                citizen_id INT NOT NULL,
                disease_name VARCHAR(100) NOT NULL,
                symptoms TEXT NOT NULL,
                location VARCHAR(200) NOT NULL,
                phone_number VARCHAR(20),
                report_source ENUM('web', 'sms', 'mobile_app') DEFAULT 'web',
                severity ENUM('mild', 'moderate', 'severe') DEFAULT 'mild',
                location_id INT,
                latitude DECIMAL(10,8),
                longitude DECIMAL(11,8),
                status ENUM('pending', 'analyzed', 'completed') DEFAULT 'pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (citizen_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (location_id) REFERENCES locations(location_id) ON DELETE SET NULL,
                INDEX idx_reports_citizen (citizen_id),
                INDEX idx_reports_status (status),
                INDEX idx_reports_disease (disease_name),
                INDEX idx_reports_source (report_source),
                INDEX idx_reports_created (created_at)
            )";
            $this->conn->exec($sql);
            
            $sql = "CREATE TABLE IF NOT EXISTS analyses (
                id INT PRIMARY KEY AUTO_INCREMENT,
                report_id INT NOT NULL,
                health_worker_id INT NOT NULL,
                analysis_details TEXT NOT NULL,
                severity_level ENUM('low', 'medium', 'high', 'critical') NOT NULL,
                sent_to_admin BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                analyzed_at TIMESTAMP NULL,
                FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,
                FOREIGN KEY (health_worker_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_analyses_sent_to_admin (sent_to_admin),
                INDEX idx_analyses_report (report_id)
            )";
            $this->conn->exec($sql);
            
            $sql = "CREATE TABLE IF NOT EXISTS recommendations (
                id INT PRIMARY KEY AUTO_INCREMENT,
                analysis_id INT NOT NULL,
                health_worker_id INT NOT NULL,
                disease_name VARCHAR(100) NOT NULL,
                recommendation_text TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (analysis_id) REFERENCES analyses(id) ON DELETE CASCADE,
                FOREIGN KEY (health_worker_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_recommendations_analysis (analysis_id)
            )";
            $this->conn->exec($sql);
            
            $sql = "CREATE TABLE IF NOT EXISTS visualizations (
                id INT PRIMARY KEY AUTO_INCREMENT,
                admin_id INT NOT NULL,
                disease_name VARCHAR(100) NOT NULL,
                affected_locations JSON,
                chart_data JSON,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
            )";
            $this->conn->exec($sql);
            
            $sql = "CREATE TABLE IF NOT EXISTS analytics (
                id INT PRIMARY KEY AUTO_INCREMENT,
                report_id INT NOT NULL,
                response_time_hours DECIMAL(10,2),
                disease_category VARCHAR(100),
                report_hour INT,
                report_day VARCHAR(20),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE
            )";
            $this->conn->exec($sql);
            
            $sql = "CREATE TABLE IF NOT EXISTS outbreaks (
                outbreak_id INT PRIMARY KEY AUTO_INCREMENT,
                location_id INT,
                disease_name VARCHAR(100),
                symptom_type VARCHAR(255),
                first_case_date DATETIME,
                alert_date DATETIME,
                notification_date DATETIME,
                response_date DATETIME,
                cases_confirmed INT DEFAULT 0,
                cases_suspected INT DEFAULT 0,
                status ENUM('active', 'contained', 'resolved') DEFAULT 'active',
                notes TEXT,
                affected_radius_km DECIMAL(10,2) DEFAULT 10.00,
                latitude DECIMAL(10,8),
                longitude DECIMAL(11,8),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (location_id) REFERENCES locations(location_id),
                INDEX idx_outbreaks_status (status),
                INDEX idx_outbreaks_dates (alert_date)
            )";
            $this->conn->exec($sql);
            
            $sql = "CREATE TABLE IF NOT EXISTS alert_history (
                alert_id INT PRIMARY KEY AUTO_INCREMENT,
                outbreak_id INT,
                alert_type VARCHAR(100),
                message TEXT,
                sent_to TEXT,
                sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                acknowledged BOOLEAN DEFAULT FALSE,
                acknowledged_by INT,
                FOREIGN KEY (outbreak_id) REFERENCES outbreaks(outbreak_id),
                FOREIGN KEY (acknowledged_by) REFERENCES users(id),
                INDEX idx_alerts_sent (sent_at),
                INDEX idx_alerts_ack (acknowledged)
            )";
            $this->conn->exec($sql);
            
            $sql = "CREATE TABLE IF NOT EXISTS health_education (
                material_id INT PRIMARY KEY AUTO_INCREMENT,
                title VARCHAR(255) NOT NULL,
                content TEXT NOT NULL,
                language VARCHAR(10) DEFAULT 'en',
                disease_name VARCHAR(100),
                symptom_type VARCHAR(100),
                target_audience VARCHAR(50),
                created_by INT,
                views INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (created_by) REFERENCES users(id),
                INDEX idx_education_language (language),
                INDEX idx_education_disease (disease_name)
            )";
            $this->conn->exec($sql);
            
            $this->insertDefaultLocations();
            
        } catch(PDOException $e) {
            throw new Exception("Table creation failed: " . $e->getMessage());
        }
    }
    
    private function insertDefaultLocations() {
        try {
            $stmt = $this->conn->query("SELECT COUNT(*) FROM locations");
            $count = $stmt->fetchColumn();
            
            if ($count == 0) {
                $sql = "INSERT INTO locations (location_name, latitude, longitude, county, risk_level) VALUES
                    ('Nairobi Central', -1.2864, 36.8172, 'Nairobi', 'medium'),
                    ('Mombasa Island', -4.0435, 39.6682, 'Mombasa', 'high'),
                    ('Kisumu Town', -0.1022, 34.7617, 'Kisumu', 'medium'),
                    ('Nakuru Town', -0.3031, 36.0800, 'Nakuru', 'low'),
                    ('Eldoret Town', 0.5143, 35.2698, 'Uasin Gishu', 'low')";
                $this->conn->exec($sql);
            }
        } catch(PDOException $e) {
        }
    }
    
    private function showDetailedError($error) {
        echo '<div style="background-color: #ffe3e3; padding: 20px; margin: 20px; border-radius: 10px; border: 1px solid #ff6b6b;">
                <h3 style="color: #c92a2a;">Database Connection Error</h3>
                <p><strong>Error:</strong> ' . htmlspecialchars($error) . '</p>
                <p>Please check your XAMPP MySQL is running on port 3306.</p>
              </div>';
    }
}
?>