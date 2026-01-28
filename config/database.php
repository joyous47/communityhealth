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
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_users_email (email)
            )";
            $this->conn->exec($sql);
            
            $sql = "CREATE TABLE IF NOT EXISTS reports (
                id INT PRIMARY KEY AUTO_INCREMENT,
                citizen_id INT NOT NULL,
                disease_name VARCHAR(100) NOT NULL,
                symptoms TEXT NOT NULL,
                location VARCHAR(200) NOT NULL,
                status ENUM('pending', 'analyzed', 'completed') DEFAULT 'pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (citizen_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_reports_citizen (citizen_id),
                INDEX idx_reports_status (status),
                INDEX idx_reports_disease (disease_name)
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
            
        } catch(PDOException $e) {
            throw new Exception("Table creation failed: " . $e->getMessage());
        }
    }
    
    private function showDetailedError($error) {
        echo '<div style="background-color: #e7f5ff; color: #212529; padding: 20px; margin: 20px 0; border-radius: 10px; border: 1px solid #339af0;">
                <h3 style="margin-top: 0; color: #339af0;">Database Connection Error</h3>
                <p><strong>Error:</strong> ' . htmlspecialchars($error) . '</p>
                
                <h4 style="color: #212529;">Current Configuration:</h4>
                <table style="width: 100%; border-collapse: collapse; border: 1px solid #339af0;">
                    <tr style="background-color: #e7f5ff;">
                        <th style="padding: 8px; border: 1px solid #339af0; color: #212529;">Setting</th>
                        <th style="padding: 8px; border: 1px solid #339af0; color: #212529;">Value</th>
                    </tr>
                    <tr>
                        <td style="padding: 8px; border: 1px solid #339af0; color: #212529;">Host</td>
                        <td style="padding: 8px; border: 1px solid #339af0; color: #212529;">' . $this->host . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px; border: 1px solid #339af0; color: #212529;">Port</td>
                        <td style="padding: 8px; border: 1px solid #339af0; color: #212529;">' . $this->port . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px; border: 1px solid #339af0; color: #212529;">Database</td>
                        <td style="padding: 8px; border: 1px solid #339af0; color: #212529;">' . $this->db_name . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px; border: 1px solid #339af0; color: #212529;">Username</td>
                        <td style="padding: 8px; border: 1px solid #339af0; color: #212529;">' . $this->username . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px; border: 1px solid #339af0; color: #212529;">Password</td>
                        <td style="padding: 8px; border: 1px solid #339af0; color: #212529;">' . ($this->password ? "Set" : "Empty") . '</td>
                    </tr>
                </table>
                
                <h4 style="color: #212529;">Quick Tests:</h4>
                <ol style="color: #212529;">
                    <li>Open phpMyAdmin: <a href="http://localhost/phpmyadmin/" target="_blank" style="color: #339af0;">http://localhost/phpmyadmin/</a></li>
                    <li>Check if database <code style="background-color: #e7f5ff; padding: 2px 6px; border-radius: 3px; color: #339af0;">' . $this->db_name . '</code> exists</li>
                    <li>If not, create it manually in phpMyAdmin</li>
                    <li>Make sure MySQL is running on port ' . $this->port . ' in XAMPP</li>
                </ol>
              </div>';
    }
    
    public function closeConnection() {
        $this->conn = null;
    }
}

function getDB() {
    static $db = null;
    if ($db === null) {
        $database = new Database();
        $db = $database->getConnection();
    }
    return $db;
}

function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function testDatabaseConnection() {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $stmt = $db->query("SELECT 'Database connected successfully!' as message");
        $result = $stmt->fetch();
        
        echo '<div style="background-color: #e7f5ff; color: #212529; padding: 15px; border-radius: 5px; margin: 20px 0; border: 1px solid #339af0;">
                <h4 style="margin-top: 0; color: #339af0;">✓ Database Connection Successful!</h4>
                <p style="color: #212529;"><strong>Host:</strong> 127.0.0.1:3306<br>
                <strong>Database:</strong> chmwes_db<br>
                <strong>User:</strong> root (no password)</p>
                <p style="color: #212529;"><strong>Message:</strong> ' . $result['message'] . '</p>
              </div>';
        
        $stmt = $db->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (count($tables) > 0) {
            echo '<div style="background-color: #e7f5ff; color: #212529; padding: 15px; border-radius: 5px; margin: 20px 0; border: 1px solid #339af0;">
                    <h4 style="margin-top: 0; color: #339af0;">✓ Database Tables Found:</h4>
                    <ul style="columns: 2; color: #212529;">';
            foreach ($tables as $table) {
                echo '<li>' . htmlspecialchars($table) . '</li>';
            }
            echo '</ul></div>';
        } else {
            echo '<div style="background-color: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin: 20px 0; border: 1px solid #ff922b;">
                    <h4 style="margin-top: 0; color: #212529;">⚠ No Tables Found</h4>
                    <p style="color: #212529;">Tables will be created automatically when needed.</p>
                  </div>';
        }
        
        $database->closeConnection();
        
    } catch(Exception $e) {
        echo '<div style="background-color: #ffe3e3; color: #212529; padding: 15px; border-radius: 5px; margin: 20px 0; border: 1px solid #ff6b6b;">
                <h4 style="margin-top: 0; color: #c92a2a;">✗ Database Connection Failed</h4>
                <p style="color: #212529;"><strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>
                <p style="color: #212529;">Please check your XAMPP MySQL is running on port 3306.</p>
              </div>';
    }
}

function registerUser($username, $email, $password, $role) {
    try {
        $db = getDB();
        
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) {
            return [false, "Email already registered", null];
        }
        
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->rowCount() > 0) {
            return [false, "Username already taken", null];
        }
        
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $db->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$username, $email, $hashed_password, $role]);
        
        $user_id = $db->lastInsertId();
        
        return [true, "Registration successful! Please login.", $user_id];
        
    } catch(PDOException $e) {
        return [false, "Registration failed: " . $e->getMessage(), null];
    }
}

function loginUser($email, $password) {
    try {
        $db = getDB();
        
        $stmt = $db->prepare("SELECT id, username, email, password, role, created_at FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return [false, "Invalid email or password", null];
        }
        
        if (!password_verify($password, $user['password'])) {
            return [false, "Invalid email or password", null];
        }
        
        unset($user['password']);
        
        return [true, "Login successful!", $user];
        
    } catch(PDOException $e) {
        return [false, "Login failed: " . $e->getMessage(), null];
    }
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getCurrentUser() {
    if (isLoggedIn()) {
        return $_SESSION['user'];
    }
    return null;
}

function getCurrentUserRole() {
    $user = getCurrentUser();
    return $user ? $user['role'] : null;
}

function hasRole($role) {
    $userRole = getCurrentUserRole();
    return $userRole === $role;
}

function requireLogin($redirect_to = 'auth/login.php') {
    if (!isLoggedIn()) {
        header("Location: $redirect_to");
        exit();
    }
}

function requireRole($role, $redirect_to = '../index.php') {
    requireLogin();
    if (!hasRole($role)) {
        header("Location: $redirect_to");
        exit();
    }
}

function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?>