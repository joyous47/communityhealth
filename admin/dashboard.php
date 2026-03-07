<?php
require_once '../includes/header.php';
require_once '../config/database.php';

requireRole('admin', '../auth/login.php');

$user = getCurrentUser();
$user_id = $user['id'];

$db = getDB();


try {
    $stmt = $db->prepare("SELECT username, email, created_at, preferred_language FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_details = $stmt->fetch();
    
    if (!$user_details) {
        $user_details = [
            'username' => $user['username'],
            'email' => '',
            'created_at' => null,
            'preferred_language' => 'en'
        ];
    }
} catch(PDOException $e) {
    $user_details = [
        'username' => $user['username'],
        'email' => '',
        'created_at' => null,
        'preferred_language' => 'en'
    ];
}

try {
    $stmt = $db->prepare("SELECT COUNT(*) as total_users FROM users");
    $stmt->execute();
    $total_users = $stmt->fetch()['total_users'];
    
    $stmt = $db->prepare("SELECT role, COUNT(*) as count FROM users GROUP BY role");
    $stmt->execute();
    $users_by_role = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $stmt = $db->prepare("SELECT COUNT(*) as total_reports FROM reports");
    $stmt->execute();
    $total_reports = $stmt->fetch()['total_reports'];
    
    $stmt = $db->prepare("SELECT status, COUNT(*) as count FROM reports GROUP BY status");
    $stmt->execute();
    $reports_by_status = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $stmt = $db->prepare("SELECT COUNT(*) as analyses_to_review FROM analyses WHERE sent_to_admin = TRUE");
    $stmt->execute();
    $analyses_to_review = $stmt->fetch()['analyses_to_review'];
    
    $stmt = $db->prepare("SELECT COUNT(*) as total_analyses FROM analyses");
    $stmt->execute();
    $total_analyses = $stmt->fetch()['total_analyses'];
    
    $stmt = $db->prepare("SELECT COUNT(*) as total_recommendations FROM recommendations");
    $stmt->execute();
    $total_recommendations = $stmt->fetch()['total_recommendations'];
    
    $stmt = $db->prepare("SELECT AVG(response_time_hours) as avg_response_time FROM analytics WHERE response_time_hours IS NOT NULL");
    $stmt->execute();
    $avg_response_time = $stmt->fetch()['avg_response_time'];
    
    $stmt = $db->prepare("SELECT a.*, r.disease_name, r.location, 
                         u.username as health_worker_name,
                         u2.username as citizen_name
                         FROM analyses a
                         JOIN reports r ON a.report_id = r.id
                         JOIN users u ON a.health_worker_id = u.id
                         JOIN users u2 ON r.citizen_id = u2.id
                         WHERE a.sent_to_admin = TRUE
                         ORDER BY a.analyzed_at DESC
                         LIMIT 5");
    $stmt->execute();
    $recent_sent_analyses = $stmt->fetchAll();
    
    $stmt = $db->prepare("SELECT disease_name, COUNT(*) as count 
                         FROM reports 
                         GROUP BY disease_name 
                         ORDER BY count DESC 
                         LIMIT 5");
    $stmt->execute();
    $top_diseases = $stmt->fetchAll();
    
    $stmt = $db->prepare("SELECT DATE(created_at) as date, COUNT(*) as count 
                         FROM reports 
                         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                         GROUP BY DATE(created_at) 
                         ORDER BY date ASC");
    $stmt->execute();
    $recent_activity = $stmt->fetchAll();
    
    $stmt = $db->prepare("SELECT COUNT(*) as total_visualizations FROM visualizations");
    $stmt->execute();
    $total_visualizations = $stmt->fetch()['total_visualizations'];
    
    $stmt = $db->prepare("SELECT v.*, u.username as admin_name 
                         FROM visualizations v
                         JOIN users u ON v.admin_id = u.id
                         ORDER BY v.created_at DESC
                         LIMIT 3");
    $stmt->execute();
    $recent_visualizations = $stmt->fetchAll();
    
    
    $stmt = $db->prepare("SELECT COUNT(*) as active_outbreaks FROM outbreaks WHERE status = 'active'");
    $stmt->execute();
    $active_outbreaks = $stmt->fetch()['active_outbreaks'];
    
    $stmt = $db->prepare("SELECT o.*, l.location_name, l.latitude, l.longitude 
                         FROM outbreaks o 
                         LEFT JOIN locations l ON o.location_id = l.location_id 
                         WHERE o.status IN ('active', 'investigating')
                         ORDER BY o.alert_date DESC
                         LIMIT 5");
    $stmt->execute();
    $recent_outbreaks = $stmt->fetchAll();
    
    
    $stmt = $db->prepare("SELECT COUNT(*) as critical_reports FROM reports WHERE severity = 'critical' AND status != 'resolved'");
    $stmt->execute();
    $critical_reports = $stmt->fetch()['critical_reports'];
    
} catch(PDOException $e) {
    $_SESSION['error_message'] = "Error loading dashboard data: " . $e->getMessage();
    $total_users = $total_reports = $analyses_to_review = $total_analyses = $total_recommendations = 0;
    $users_by_role = $reports_by_status = [];
    $avg_response_time = 0;
    $recent_sent_analyses = $top_diseases = $recent_activity = $recent_visualizations = [];
    $total_visualizations = 0;
    $active_outbreaks = 0;
    $recent_outbreaks = [];
    $critical_reports = 0;
}


function formatMemberSince($date) {
    if (empty($date)) {
        return 'Recently joined';
    }
    return formatDate($date, 'F Y');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard </title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <style>
        .dashboard-header {
            background: linear-gradient(135deg, #4da8da, #0077be);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .dashboard-header h2 {
            margin-bottom: 10px;
            font-size: 1.8rem;
        }
        
        .dashboard-header p {
            opacity: 0.9;
            margin-bottom: 0;
        }
        
        .welcome-message {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .user-info-card {
            background-color: rgba(255, 255, 255, 0.1);
            padding: 15px 20px;
            border-radius: 8px;
            backdrop-filter: blur(10px);
            min-width: 250px;
        }
        
        .user-info-card h3 {
            margin-bottom: 10px;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .user-info-card p {
            margin: 5px 0;
            font-size: 0.9rem;
            opacity: 0.9;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .user-info-card i {
            width: 18px;
            text-align: center;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            text-align: center;
            transition: transform 0.3s;
            border-top: 4px solid #4da8da;
            color: #000;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card.users {
            border-top-color: #4da8da;
        }
        
        .stat-card.reports {
            border-top-color: #2ecc71;
        }
        
        .stat-card.analyses {
            border-top-color: #9b59b6;
        }
        
        .stat-card.recommendations {
            border-top-color: #f39c12;
        }
        
        .stat-card.response-time {
            border-top-color: #e74c3c;
        }
        
        .stat-card.visualizations {
            border-top-color: #1abc9c;
        }
        
        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
            color: #4da8da;
        }
        
        .stat-card.users .stat-icon { color: #4da8da; }
        .stat-card.reports .stat-icon { color: #2ecc71; }
        .stat-card.analyses .stat-icon { color: #9b59b6; }
        .stat-card.recommendations .stat-icon { color: #f39c12; }
        .stat-card.response-time .stat-icon { color: #e74c3c; }
        .stat-card.visualizations .stat-icon { color: #1abc9c; }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 10px;
            color: #000;
        }
        
        .stat-label {
            color: #666;
            font-size: 1rem;
        }
        
        .stat-subtext {
            font-size: 0.85rem;
            color: #999;
            margin-top: 5px;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .action-card {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            transition: all 0.3s;
            border: 2px solid transparent;
            color: #000;
        }
        
        .action-card:hover {
            transform: translateY(-5px);
            border-color: #4da8da;
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }
        
        .action-icon {
            font-size: 3rem;
            margin-bottom: 20px;
            color: #4da8da;
        }
        
        .action-card h4 {
            color: #000;
            margin-bottom: 15px;
            font-size: 1.2rem;
        }
        
        .action-card p {
            color: #666;
            margin-bottom: 20px;
            font-size: 0.95rem;
        }
        
        .action-btn {
            display: inline-block;
            padding: 10px 25px;
            background-color: #4da8da;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 500;
            transition: background-color 0.3s;
        }
        
        .action-btn:hover {
            background-color: #0077be;
            color: white;
            text-decoration: none;
        }
        
        .dashboard-section {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            color: #333;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f1f1f1;
        }
        
        .section-header h3 {
            color: #000;
            margin: 0;
            font-size: 1.4rem;
        }
        
        .view-all-link {
            color: #4da8da;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.95rem;
        }
        
        .view-all-link:hover {
            text-decoration: underline;
        }
        
        .analyses-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .analyses-table th {
            background-color: #f8f9fa;
            color: #000;
            font-weight: 600;
            text-align: left;
            padding: 15px;
            border-bottom: 2px solid #e9ecef;
        }
        
        .analyses-table td {
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
            vertical-align: middle;
            color: #333;
        }
        
        .analyses-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .analysis-id {
            font-weight: 600;
            color: #000;
        }
        
        .disease-name {
            font-weight: 500;
            color: #000;
        }
        
        .health-worker-info {
            font-size: 0.9rem;
        }
        
        .health-worker-name {
            font-weight: 500;
            color: #000;
        }
        
        .top-diseases {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .disease-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #4da8da;
        }
        
        .disease-name-text {
            font-weight: 500;
            color: #000;
        }
        
        .disease-count {
            background-color: #4da8da;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .activity-chart {
            height: 200px;
            margin-top: 20px;
            position: relative;
        }
        
        .activity-bar {
            display: flex;
            align-items: flex-end;
            gap: 10px;
            height: 150px;
            padding: 0 20px;
        }
        
        .activity-day {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            height: 100%;
        }
        
        .activity-count {
            background-color: #4da8da;
            width: 30px;
            border-radius: 5px 5px 0 0;
            transition: height 0.5s ease;
        }
        
        .activity-day-label {
            margin-top: 10px;
            font-size: 0.8rem;
            color: #666;
            text-align: center;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }
        
        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            color: #ccc;
        }
        
        .empty-state h4 {
            color: #000;
            margin-bottom: 10px;
        }
        
        .visualization-card {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #1abc9c;
        }
        
        .visualization-card h4 {
            color: #000;
            margin: 0 0 10px 0;
            font-size: 1rem;
        }
        
        .visualization-card p {
            color: #666;
            margin: 0 0 10px 0;
            font-size: 0.9rem;
        }
        
        .visualization-meta {
            display: flex;
            justify-content: space-between;
            font-size: 0.85rem;
            color: #999;
        }
        
        .system-overview {
            background-color: #f8f9fa;
            border: 2px solid #4da8da;
            color: #333;
        }
        
        .system-overview h4 {
            color: #000;
        }
        
        body {
            background-color: #f5f5f5;
        }
        
        .container {
            background: white;
            padding: 20px;
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .quick-actions {
                grid-template-columns: 1fr;
            }
            
            .analyses-table {
                display: block;
                overflow-x: auto;
            }
            
            .welcome-message {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .container {
                padding: 10px;
            }
            
            .user-info-card {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="dashboard-header">
            <div class="welcome-message">
                <div>
                    <h2>Welcome, Admin <?php echo htmlspecialchars($user_details['username']); ?>! 👨‍💼</h2>
                    <p>Monitor system activity, review critical cases, and manage the community health system.</p>
                </div>
                <div class="user-info-card">
                    <h3><i class="fas fa-user-shield"></i> Administrator Account</h3>
                    <p><i class="fas fa-calendar-alt"></i> Member since: <?php echo formatMemberSince($user_details['created_at']); ?></p>
                    <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user_details['email'] ?: 'No email provided'); ?></p>
                    <?php if (!empty($user_details['preferred_language'])): ?>
                    <p><i class="fas fa-language"></i> Language: <?php echo strtoupper($user_details['preferred_language']); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="quick-actions">
            <div class="action-card">
                <div class="action-icon">
                    <i class="fas fa-search"></i>
                </div>
                <h4>Review Analyses</h4>
                <p>Review critical analyses sent by health workers</p>
                <a href="view_analyses.php" class="action-btn">View Analyses</a>
            </div>
            
            <div class="action-card">
                <div class="action-icon">
                    <i class="fas fa-chart-pie"></i>
                </div>
                <h4>Create Visualizations</h4>
                <p>Create data visualizations for disease trends</p>
                <a href="create_visualization.php" class="action-btn">Create Visualizations</a>
            </div>
            
            <div class="action-card">
                <div class="action-icon">
                    <i class="fas fa-users-cog"></i>
                </div>
                <h4>Manage Users</h4>
                <p>View, edit, and manage system users</p>
                <a href="manage_users.php" class="action-btn">Manage Users</a>
            </div>
            
            <div class="action-card">
                <div class="action-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <h4>System Analytics</h4>
                <p>View detailed system analytics and metrics</p>
                <a href="analytics.php" class="action-btn">View Analytics</a>
            </div>
            
            <div class="action-card" style="border-color: #ef4444;">
                <div class="action-icon" style="color: #ef4444;">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h4>Outbreak Tracking</h4>
                <p>Track and manage disease outbreaks</p>
                <a href="outbreak_tracking.php" class="action-btn" style="background: #ef4444;">View Outbreaks</a>
            </div>
            
            <div class="action-card" style="border-color: #f97316;">
                <div class="action-icon" style="color: #f97316;">
                    <i class="fas fa-bell"></i>
                </div>
                <h4>Alert Management</h4>
                <p>Create alerts from severe reports</p>
                <a href="alert_management.php" class="action-btn" style="background: #f97316;">Manage Alerts</a>
            </div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card users">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-number"><?php echo $total_users; ?></div>
                <div class="stat-label">Total Users</div>
                <div class="stat-subtext">
                    <?php echo ($users_by_role['citizen'] ?? 0) . ' Citizens, ' . 
                           ($users_by_role['health_worker'] ?? 0) . ' Health Workers, ' . 
                           ($users_by_role['admin'] ?? 0) . ' Admins'; ?>
                </div>
            </div>
            
            <div class="stat-card reports">
                <div class="stat-icon">
                    <i class="fas fa-file-medical"></i>
                </div>
                <div class="stat-number"><?php echo $total_reports; ?></div>
                <div class="stat-label">Total Reports</div>
                <div class="stat-subtext">
                    <?php echo ($reports_by_status['pending'] ?? 0) . ' Pending, ' . 
                           ($reports_by_status['analyzed'] ?? 0) . ' Analyzed, ' . 
                           ($reports_by_status['completed'] ?? 0) . ' Completed'; ?>
                </div>
            </div>
            
            <div class="stat-card analyses">
                <div class="stat-icon">
                    <i class="fas fa-stethoscope"></i>
                </div>
                <div class="stat-number"><?php echo $analyses_to_review; ?></div>
                <div class="stat-label">Analyses to Review</div>
                <div class="stat-subtext">
                    <?php echo $total_analyses; ?> total analyses
                </div>
            </div>
            
            <div class="stat-card recommendations">
                <div class="stat-icon">
                    <i class="fas fa-comment-medical"></i>
                </div>
                <div class="stat-number"><?php echo $total_recommendations; ?></div>
                <div class="stat-label">Recommendations</div>
                <div class="stat-subtext">
                    Health advice provided to citizens
                </div>
            </div>
            
            <div class="stat-card response-time">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-number">
                    <?php echo $avg_response_time ? number_format($avg_response_time, 1) : 'N/A'; ?>
                </div>
                <div class="stat-label">Avg Response Time</div>
                <div class="stat-subtext">
                    Hours from report to analysis
                </div>
            </div>
            
            <div class="stat-card visualizations">
                <div class="stat-icon">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <div class="stat-number"><?php echo $total_visualizations; ?></div>
                <div class="stat-label">Visualizations</div>
                <div class="stat-subtext">
                    Data charts created
                </div>
            </div>
            
            <div class="stat-card" style="border-top-color: #ef4444;">
                <div class="stat-icon" style="color: #ef4444;">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-number" style="color: #ef4444;"><?php echo $active_outbreaks; ?></div>
                <div class="stat-label">Active Outbreaks</div>
                <div class="stat-subtext">
                    <?php echo $critical_reports; ?> critical reports
                </div>
            </div>
        </div>
        
        <!-- Disease & Outbreak Map -->
        <div class="dashboard-section" style="margin-bottom: 30px;">
            <div class="section-header">
                <h3><i class="fas fa-map-marked-alt" style="color: #4da8da;"></i> Disease & Outbreak Map</h3>
                <a href="outbreak_tracking.php" class="view-all-link">View Outbreak Tracking →</a>
            </div>
            <p style="color: #666; margin-bottom: 15px;">Geographic distribution of disease reports and active outbreaks across Kenya</p>
            
            <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                <select id="diseaseFilter" style="padding: 8px; border: 1px solid #4da8da; border-radius: 4px; background: white; color: #333;">
                    <option value="all">All Diseases</option>
                    <option value="cholera">Cholera</option>
                    <option value="malaria">Malaria</option>
                    <option value="typhoid">Typhoid</option>
                    <option value="dengue">Dengue</option>
                    <option value="covid">COVID-19</option>
                </select>
                <select id="dateFilter" style="padding: 8px; border: 1px solid #4da8da; border-radius: 4px; background: white; color: #333;">
                    <option value="7">Last 7 Days</option>
                    <option value="30" selected>Last 30 Days</option>
                    <option value="90">Last 90 Days</option>
                    <option value="365">Last Year</option>
                </select>
                <button onclick="updateAdminMap()" style="padding: 8px 15px; background: #4da8da; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    <i class="fas fa-sync-alt"></i> Update
                </button>
            </div>
            
            <div id="adminMap" style="height: 400px; width: 100%; border-radius: 8px; border: 2px solid #4da8da;">
                <div style="height: 100%; display: flex; align-items: center; justify-content: center; background: #f0f9ff; color: #4da8da;">
                    <div style="text-align: center;">
                        <i class="fas fa-map-marked-alt" style="font-size: 48px; margin-bottom: 15px;"></i>
                        <p>Loading map...</p>
                    </div>
                </div>
            </div>
            
            <div style="display: flex; gap: 20px; margin-top: 15px; flex-wrap: wrap;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 20px; height: 20px; background: rgba(77, 168, 218, 0.3); border-radius: 50%;"></div>
                    <span style="color: #666;">Low Activity</span>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 20px; height: 20px; background: rgba(77, 168, 218, 0.6); border-radius: 50%;"></div>
                    <span style="color: #666;">Medium Activity</span>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 20px; height: 20px; background: rgba(77, 168, 218, 0.9); border-radius: 50%;"></div>
                    <span style="color: #666;">High Activity</span>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 20px; height: 20px; background: #ef4444; border-radius: 50%;"></div>
                    <span style="color: #666;">Outbreak Alert</span>
                </div>
            </div>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(500px, 1fr)); gap: 30px; margin-bottom: 30px;">
            <div class="dashboard-section">
                <div class="section-header">
                    <h3>Recent Analyses for Review</h3>
                    <a href="view_analyses.php" class="view-all-link">View All Analyses →</a>
                </div>
                
                <?php if (!empty($recent_sent_analyses)): ?>
                    <div class="table-responsive">
                        <table class="analyses-table">
                            <thead>
                                <tr>
                                    <th>Analysis ID</th>
                                    <th>Disease</th>
                                    <th>Health Worker</th>
                                    <th>Citizen</th>
                                    <th>Severity</th>
                                    <th>Sent</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_sent_analyses as $analysis): ?>
                                    <tr>
                                        <td class="analysis-id">#<?php echo $analysis['id']; ?></td>
                                        <td>
                                            <div class="disease-name"><?php echo htmlspecialchars($analysis['disease_name']); ?></div>
                                        </td>
                                        <td>
                                            <div class="health-worker-info">
                                                <div class="health-worker-name"><?php echo htmlspecialchars($analysis['health_worker_name']); ?></div>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($analysis['citizen_name']); ?></td>
                                        <td><?php echo getSeverityBadge($analysis['severity_level']); ?></td>
                                        <td><?php echo timeAgo($analysis['analyzed_at']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h4>No Analyses to Review</h4>
                        <p>No analyses have been sent for review yet.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="dashboard-section">
                <div class="section-header">
                    <h3>Most Reported Diseases</h3>
                    <a href="analytics.php" class="view-all-link">View Full Analytics →</a>
                </div>
                
                <?php if (!empty($top_diseases)): ?>
                    <div class="top-diseases">
                        <?php foreach ($top_diseases as $disease): ?>
                            <div class="disease-item">
                                <span class="disease-name-text"><?php echo htmlspecialchars($disease['disease_name']); ?></span>
                                <span class="disease-count"><?php echo $disease['count']; ?> report<?php echo $disease['count'] > 1 ? 's' : ''; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state" style="padding: 20px;">
                        <p>No disease statistics available.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(500px, 1fr)); gap: 30px; margin-bottom: 30px;">
            <div class="dashboard-section">
                <div class="section-header">
                    <h3>Recent System Activity (7 Days)</h3>
                </div>
                
                <?php if (!empty($recent_activity)): 
                    $max_count = 0;
                    foreach ($recent_activity as $activity) {
                        if ($activity['count'] > $max_count) {
                            $max_count = $activity['count'];
                        }
                    }
                ?>
                    <div class="activity-chart">
                        <div class="activity-bar">
                            <?php foreach ($recent_activity as $activity): 
                                $height = $max_count > 0 ? ($activity['count'] / $max_count * 100) : 20;
                                $date = new DateTime($activity['date']);
                                $day_name = $date->format('D');
                                $day_date = $date->format('m/d');
                            ?>
                                <div class="activity-day">
                                    <div class="activity-count" style="height: <?php echo $height; ?>%; background-color: #4da8da;">
                                        <div style="position: absolute; top: -25px; left: 0; right: 0; text-align: center; font-weight: bold; color: #000;">
                                            <?php echo $activity['count']; ?>
                                        </div>
                                    </div>
                                    <div class="activity-day-label">
                                        <?php echo $day_name; ?><br>
                                        <?php echo $day_date; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="empty-state" style="padding: 20px;">
                        <p>No recent activity data available.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="dashboard-section">
                <div class="section-header">
                    <h3>Recent Visualizations</h3>
                    <a href="create_visualization.php" class="view-all-link">Create New →</a>
                </div>
                
                <?php if (!empty($recent_visualizations)): ?>
                    <div style="display: flex; flex-direction: column; gap: 15px;">
                        <?php foreach ($recent_visualizations as $viz): ?>
                            <div class="visualization-card">
                                <h4><?php echo htmlspecialchars($viz['disease_name']); ?></h4>
                                <p>Created by <?php echo htmlspecialchars($viz['admin_name']); ?></p>
                                <div class="visualization-meta">
                                    <span><?php echo timeAgo($viz['created_at']); ?></span>
                                    <span>ID: #<?php echo $viz['id']; ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state" style="padding: 20px;">
                        <p>No visualizations created yet.</p>
                        <a href="create_visualization.php" class="action-btn" style="margin-top: 15px; display: inline-block;">
                            <i class="fas fa-plus"></i> Create First Visualization
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="dashboard-section system-overview">
            <div class="section-header" style="border-bottom-color: #dfe6e9;">
                <h3 style="color: #000;">
                    <i class="fas fa-tachometer-alt"></i> System Overview
                </h3>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                <div>
                    <h4 style="color: #000; margin-bottom: 10px; font-size: 1rem;">
                        <i class="fas fa-server" style="color: #4da8da;"></i> Database Status
                    </h4>
                    <p style="margin: 0; color: #666; font-size: 0.9rem;">
                        <?php echo $total_users + $total_reports + $total_analyses + $total_recommendations; ?> total records across all tables
                    </p>
                </div>
                
                <div>
                    <h4 style="color: #000; margin-bottom: 10px; font-size: 1rem;">
                        <i class="fas fa-shield-alt" style="color: #2ecc71;"></i> System Security
                    </h4>
                    <p style="margin: 0; color: #666; font-size: 0.9rem;">
                        All passwords encrypted, SQL injection protected, XSS protected
                    </p>
                </div>
                
                <div>
                    <h4 style="color: #000; margin-bottom: 10px; font-size: 1rem;">
                        <i class="fas fa-chart-line" style="color: #9b59b6;"></i> Performance
                    </h4>
                    <p style="margin: 0; color: #666; font-size: 0.9rem;">
                        Average response time: <?php echo $avg_response_time ? number_format($avg_response_time, 1) . ' hours' : 'N/A'; ?>
                    </p>
                </div>
                
                <div>
                    <h4 style="color: #000; margin-bottom: 10px; font-size: 1rem;">
                        <i class="fas fa-users" style="color: #f39c12;"></i> User Engagement
                    </h4>
                    <p style="margin: 0; color: #666; font-size: 0.9rem;">
                        <?php echo $total_reports; ?> reports from <?php echo $users_by_role['citizen'] ?? 0; ?> citizens
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const statCards = document.querySelectorAll('.stat-card');
            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry, index) => {
                    if (entry.isIntersecting) {
                        setTimeout(() => {
                            entry.target.style.opacity = '1';
                            entry.target.style.transform = 'translateY(0)';
                        }, index * 100);
                    }
                });
            });
            
            statCards.forEach(card => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                observer.observe(card);
            });
            
            const activityBars = document.querySelectorAll('.activity-count');
            activityBars.forEach((bar, index) => {
                setTimeout(() => {
                    const currentHeight = bar.style.height;
                    bar.style.height = '0%';
                    
                    setTimeout(() => {
                        bar.style.transition = 'height 1s ease';
                        bar.style.height = currentHeight;
                    }, 100);
                }, index * 200);
            });
            
            function refreshDashboard() {
                if (window.location.pathname.includes('admin/dashboard.php')) {
                    fetch('../includes/refresh_dashboard_stats.php')
                        .then(response => response.json())
                        .then(data => {
                            if (data.total_users !== undefined) {
                                updateStat('users', data.total_users);
                            }
                            if (data.total_reports !== undefined) {
                                updateStat('reports', data.total_reports);
                            }
                            if (data.analyses_to_review !== undefined) {
                                updateStat('analyses', data.analyses_to_review);
                            }
                        })
                        .catch(err => console.log('Dashboard refresh failed:', err));
                }
            }
            
            function updateStat(type, newValue) {
                const statElement = document.querySelector(`.stat-card.${type} .stat-number`);
                if (statElement) {
                    const oldValue = parseInt(statElement.textContent);
                    if (oldValue !== newValue) {
                        statElement.style.color = '#e74c3c';
                        statElement.style.transform = 'scale(1.2)';
                        
                        setTimeout(() => {
                            statElement.textContent = newValue;
                            statElement.style.color = '';
                            statElement.style.transform = '';
                        }, 300);
                    }
                }
            }
            
            setInterval(refreshDashboard, 60000);
            
            const tooltips = document.querySelectorAll('[data-toggle="tooltip"]');
            tooltips.forEach(element => {
                element.addEventListener('mouseenter', function() {
                    const tooltip = document.createElement('div');
                    tooltip.className = 'custom-tooltip';
                    tooltip.textContent = this.getAttribute('title');
                    tooltip.style.position = 'absolute';
                    tooltip.style.backgroundColor = '#333';
                    tooltip.style.color = 'white';
                    tooltip.style.padding = '5px 10px';
                    tooltip.style.borderRadius = '4px';
                    tooltip.style.fontSize = '12px';
                    tooltip.style.zIndex = '1000';
                    
                    document.body.appendChild(tooltip);
                    
                    const rect = this.getBoundingClientRect();
                    tooltip.style.left = (rect.left + window.scrollX) + 'px';
                    tooltip.style.top = (rect.top + window.scrollY - tooltip.offsetHeight - 5) + 'px';
                    
                    this._tooltip = tooltip;
                });
                
                element.addEventListener('mouseleave', function() {
                    if (this._tooltip) {
                        this._tooltip.remove();
                        this._tooltip = null;
                    }
                });
            });
            
            const chartContainers = document.querySelectorAll('.chart-container');
            chartContainers.forEach(container => {
                const canvas = container.querySelector('canvas');
                if (canvas) {
                    canvas.style.backgroundColor = '#f8f9fa';
                    canvas.style.borderRadius = '5px';
                }
            });
            
            const exportBtn = document.getElementById('exportDashboard');
            if (exportBtn) {
                exportBtn.addEventListener('click', function() {
                    const exportType = confirm('Export dashboard data as CSV? Click OK for CSV, Cancel for PDF.');
                    
                    if (exportType) {
                        window.location.href = 'export_dashboard.php?format=csv';
                    } else {
                        window.location.href = 'export_dashboard.php?format=pdf';
                    }
                });
            }
        });
        
        // Disease Map JavaScript
        var adminMap = null;
        var adminMarkersLayer = null;
        
        document.addEventListener('DOMContentLoaded', function() {
            initAdminMap();
        });
        
        function initAdminMap() {
            var mapElement = document.getElementById('adminMap');
            if (mapElement && typeof L !== 'undefined') {
                try {
                    adminMap = L.map('adminMap', {
                        center: [-1.2864, 36.8172],
                        zoom: 6,
                        zoomControl: true,
                        attributionControl: true
                    });
                    
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                    }).addTo(adminMap);
                    
                    updateAdminMap();
                } catch (e) {
                    console.error('Error initializing map:', e);
                }
            }
        }
        
        function updateAdminMap() {
            if (!adminMap) return;
            
            var disease = document.getElementById('diseaseFilter').value;
            var days = document.getElementById('dateFilter').value;
            
            fetch(`../public/api/get_heatmap_data.php?disease=${disease}&days=${days}`)
                .then(response => response.json())
                .then(data => {
                    if (adminMarkersLayer) {
                        adminMap.removeLayer(adminMarkersLayer);
                        adminMarkersLayer = null;
                    }
                    
                    if (data.points && data.points.length > 0) {
                        adminMarkersLayer = L.layerGroup().addTo(adminMap);
                        
                        data.points.forEach(function(p) {
                            var color = p.intensity > 2 ? '#dc2626' : (p.intensity > 1 ? '#f97316' : '#4da8da');
                            var radius = Math.min(p.intensity * 3, 20);
                            
                            var marker = L.circleMarker([p.lat, p.lng], {
                                radius: radius,
                                fillColor: color,
                                color: '#ffffff',
                                weight: 2,
                                opacity: 1,
                                fillOpacity: 0.7
                            });
                            var popupContent = '<strong>Disease: ' + (p.disease || 'Unknown') + '</strong><br>';
                            popupContent += 'Cases: ' + p.intensity + '<br>';
                            if (p.severity) popupContent += 'Severity: ' + p.severity + '<br>';
                            if (p.location) popupContent += 'Location: ' + p.location + '<br>';
                            popupContent += 'Lat: ' + p.lat.toFixed(4) + ', Lng: ' + p.lng.toFixed(4);
                            marker.bindPopup(popupContent);
                            adminMarkersLayer.addLayer(marker);
                        });
                    }
                    
                    // Display outbreaks
                    if (data.outbreaks && data.outbreaks.length > 0) {
                        if (!adminMarkersLayer) {
                            adminMarkersLayer = L.layerGroup().addTo(adminMap);
                        }
                        data.outbreaks.forEach(function(o) {
                            var affectedArea = L.circle([o.lat, o.lng], {
                                radius: o.radius * 1000,
                                fillColor: '#ef4444',
                                fillOpacity: 0.15,
                                color: '#ef4444',
                                weight: 2,
                                opacity: 0.6
                            }).addTo(adminMap);
                            
                            affectedArea.bindPopup('<div style="min-width:150px;">' +
                                '<strong style="color:#ef4444;">OUTBREAK ALERT</strong><hr>' +
                                '<strong>Disease:</strong> ' + o.disease + '<br>' +
                                '<strong>Location:</strong> ' + (o.location || 'Unknown') + '<br>' +
                                '<strong>Affected Radius:</strong> ' + o.radius + ' km<br>' +
                                '<strong>Confirmed Cases:</strong> ' + o.cases_confirmed + '<br>' +
                                '<strong>Alert Date:</strong> ' + o.alert_date + '</div>');
                            
                            var centerMarker = L.circleMarker([o.lat, o.lng], {
                                radius: 8,
                                fillColor: '#ef4444',
                                color: '#ffffff',
                                weight: 2,
                                opacity: 1,
                                fillOpacity: 0.9
                            }).addTo(adminMap);
                            
                            centerMarker.bindPopup('<div style="min-width:150px;">' +
                                '<strong style="color:#ef4444;">Outbreak Center</strong><hr>' +
                                '<strong>Disease:</strong> ' + o.disease + '<br>' +
                                '<strong>Location:</strong> ' + (o.location || 'Unknown') + '</div>');
                        });
                    }
                    
                    // Fit bounds
                    var allMarkers = [];
                    if (data.points) {
                        data.points.forEach(function(p) { allMarkers.push([p.lat, p.lng]); });
                    }
                    if (data.outbreaks) {
                        data.outbreaks.forEach(function(o) { allMarkers.push([o.lat, o.lng]); });
                    }
                    if (allMarkers.length > 0) {
                        var bounds = L.latLngBounds(allMarkers);
                        adminMap.fitBounds(bounds, {padding: [50, 50]});
                    }
                });
        }
    </script>
</body>
</html>