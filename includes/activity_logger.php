<?php
/**
 * Activity Logger Class
 * Tracks all user activities for audit purposes
 */

require_once __DIR__ . '/../config/database.php';

class ActivityLogger {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    /**
     * Log an activity
     * @param int $userId User ID
     * @param string $action Action performed
     * @param string $module Module/section
     * @param string $details Additional details
     * @param string $ipAddress User's IP address
     */
    public function log($userId, $action, $module, $details = '', $ipAddress = null) {
        if ($ipAddress === null) {
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        }
        
        try {
            $stmt = $this->db->prepare("
                INSERT INTO activity_log (user_id, action, module, details, ip_address, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$userId, $action, $module, $details, $ipAddress]);
        } catch (PDOException $e) {
            // Silently fail - logging should not break the app
            error_log('Activity logging failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Log user login
     */
    public function logLogin($userId, $username) {
        $this->log($userId, 'LOGIN', 'Authentication', "User logged in: $username");
    }
    
    /**
     * Log user logout
     */
    public function logLogout($userId) {
        $this->log($userId, 'LOGOUT', 'Authentication', 'User logged out');
    }
    
    /**
     * Log failed login attempt
     */
    public function logFailedLogin($username, $reason = 'Invalid credentials') {
        $this->log(0, 'LOGIN_FAILED', 'Authentication', "Failed login attempt: $username - $reason");
    }
    
    /**
     * Log report creation
     */
    public function logReportCreated($userId, $reportId, $diseaseName) {
        $this->log($userId, 'CREATE', 'Reports', "Created report #$reportId for: $diseaseName");
    }
    
    /**
     * Log report analysis
     */
    public function logReportAnalyzed($userId, $reportId, $severity) {
        $this->log($userId, 'ANALYZE', 'Reports', "Analyzed report #$reportId - Severity: $severity");
    }
    
    /**
     * Log recommendation created
     */
    public function logRecommendationCreated($userId, $reportId) {
        $this->log($userId, 'CREATE', 'Recommendations', "Created recommendation for report #$reportId");
    }
    
    /**
     * Log data export
     */
    public function logExport($userId, $exportType, $format) {
        $this->log($userId, 'EXPORT', 'Reports', "Exported $exportType as $format");
    }
    
    /**
     * Log user created
     */
    public function logUserCreated($adminId, $newUserId, $username, $role) {
        $this->log($adminId, 'CREATE', 'Users', "Created user: $username (ID: $newUserId, Role: $role)");
    }
    
    /**
     * Log user updated
     */
    public function logUserUpdated($adminId, $userId, $changes) {
        $this->log($adminId, 'UPDATE', 'Users', "Updated user ID: $userId - Changes: $changes");
    }
    
    /**
     * Get activities for a user
     */
    public function getUserActivities($userId, $limit = 50) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM activity_log 
                WHERE user_id = ? 
                ORDER BY created_at DESC 
                LIMIT ?
            ");
            $stmt->execute([$userId, $limit]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Get all activities with filters
     */
    public function getActivities($filters = [], $limit = 100) {
        $where = [];
        $params = [];
        
        if (!empty($filters['user_id'])) {
            $where[] = 'user_id = ?';
            $params[] = $filters['user_id'];
        }
        
        if (!empty($filters['action'])) {
            $where[] = 'action = ?';
            $params[] = $filters['action'];
        }
        
        if (!empty($filters['module'])) {
            $where[] = 'module = ?';
            $params[] = $filters['module'];
        }
        
        if (!empty($filters['date_from'])) {
            $where[] = 'DATE(created_at) >= ?';
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = 'DATE(created_at) <= ?';
            $params[] = $filters['date_to'];
        }
        
        $sql = "SELECT * FROM activity_log";
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= " ORDER BY created_at DESC LIMIT ?";
        $params[] = $limit;
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Get activity statistics
     */
    public function getStats($dateFrom = null, $dateTo = null) {
        $stats = [
            'total_activities' => 0,
            'logins_today' => 0,
            'reports_created' => 0,
            'analyses_done' => 0,
            'active_users' => 0
        ];
        
        try {
            // Total activities
            $sql = "SELECT COUNT(*) as count FROM activity_log";
            $params = [];
            if ($dateFrom && $dateTo) {
                $sql .= " WHERE DATE(created_at) BETWEEN ? AND ?";
                $params = [$dateFrom, $dateTo];
            }
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $stats['total_activities'] = $stmt->fetch()['count'];
            
            // Logins today
            $stmt = $this->db->query("
                SELECT COUNT(*) as count 
                FROM activity_log 
                WHERE action = 'LOGIN' AND DATE(created_at) = CURDATE()
            ");
            $stats['logins_today'] = $stmt->fetch()['count'];
            
            // Reports created
            $stmt = $this->db->query("
                SELECT COUNT(*) as count 
                FROM activity_log 
                WHERE action = 'CREATE' AND module = 'Reports'
            ");
            $stats['reports_created'] = $stmt->fetch()['count'];
            
            // Analyses done
            $stmt = $this->db->query("
                SELECT COUNT(*) as count 
                FROM activity_log 
                WHERE action = 'ANALYZE'
            ");
            $stats['analyses_done'] = $stmt->fetch()['count'];
            
            // Active users (last 7 days)
            $stmt = $this->db->query("
                SELECT COUNT(DISTINCT user_id) as count 
                FROM activity_log 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");
            $stats['active_users'] = $stmt->fetch()['count'];
            
        } catch (PDOException $e) {
            // Return partial stats on error
        }
        
        return $stats;
    }
}

// Helper function for easy logging
function logActivity($userId, $action, $module, $details = '') {
    $logger = new ActivityLogger();
    $logger->log($userId, $action, $module, $details);
}
