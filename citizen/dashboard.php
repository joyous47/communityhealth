<?php
// ============================================
// CITIZEN DASHBOARD
// ============================================
// Main dashboard for citizens
// ============================================

// Include header which starts session
require_once '../includes/header.php';
require_once '../config/database.php';

// Check if user is logged in and is a citizen
requireRole('citizen', '../auth/login.php');

// Get current user
$user = getCurrentUser();
$user_id = $user['id'];

// Get database connection
$db = getDB();

// Get user details from database (including created_at)
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

// Get statistics for dashboard
try {
    // Total reports by citizen
    $stmt = $db->prepare("SELECT COUNT(*) as total_reports FROM reports WHERE citizen_id = ?");
    $stmt->execute([$user_id]);
    $total_reports = $stmt->fetch()['total_reports'];
    
    // Pending reports
    $stmt = $db->prepare("SELECT COUNT(*) as pending_reports FROM reports WHERE citizen_id = ? AND status = 'pending'");
    $stmt->execute([$user_id]);
    $pending_reports = $stmt->fetch()['pending_reports'];
    
    // Analyzed reports
    $stmt = $db->prepare("SELECT COUNT(*) as analyzed_reports FROM reports WHERE citizen_id = ? AND status = 'analyzed'");
    $stmt->execute([$user_id]);
    $analyzed_reports = $stmt->fetch()['analyzed_reports'];
    
    // Completed reports
    $stmt = $db->prepare("SELECT COUNT(*) as completed_reports FROM reports WHERE citizen_id = ? AND status = 'completed'");
    $stmt->execute([$user_id]);
    $completed_reports = $stmt->fetch()['completed_reports'];
    
    // Recent reports (last 5)
    $stmt = $db->prepare("SELECT r.*, 
                         (SELECT COUNT(*) FROM analyses WHERE report_id = r.id) as analysis_count,
                         (SELECT COUNT(*) FROM recommendations rec 
                          JOIN analyses a ON rec.analysis_id = a.id 
                          WHERE a.report_id = r.id) as recommendation_count
                         FROM reports r 
                         WHERE r.citizen_id = ? 
                         ORDER BY r.created_at DESC 
                         LIMIT 5");
    $stmt->execute([$user_id]);
    $recent_reports = $stmt->fetchAll();
    
    // Get recommendations count
    $stmt = $db->prepare("SELECT COUNT(*) as total_recommendations 
                         FROM recommendations rec 
                         JOIN analyses a ON rec.analysis_id = a.id 
                         JOIN reports r ON a.report_id = r.id 
                         WHERE r.citizen_id = ?");
    $stmt->execute([$user_id]);
    $total_recommendations = $stmt->fetch()['total_recommendations'];
    
    // Get most common diseases reported
    $stmt = $db->prepare("SELECT disease_name, COUNT(*) as count 
                         FROM reports 
                         WHERE citizen_id = ? 
                         GROUP BY disease_name 
                         ORDER BY count DESC 
                         LIMIT 5");
    $stmt->execute([$user_id]);
    $common_diseases = $stmt->fetchAll();
    
} catch(PDOException $e) {
    $_SESSION['error_message'] = "Error loading dashboard data: " . $e->getMessage();
    $total_reports = $pending_reports = $analyzed_reports = $completed_reports = 0;
    $recent_reports = $common_diseases = [];
    $total_recommendations = 0;
}

// Helper function to format member since date
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
    <title>Citizen Dashboard - Disease Surveillance System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <style>
        /* Citizen Dashboard Specific Styles */
        .dashboard-header {
            background: linear-gradient(135deg, #3498db, #2980b9);
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
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
            border-top: 4px solid #3498db;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card.pending {
            border-top-color: #f39c12;
        }
        
        .stat-card.analyzed {
            border-top-color: #3498db;
        }
        
        .stat-card.completed {
            border-top-color: #2ecc71;
        }
        
        .stat-card.recommendations {
            border-top-color: #9b59b6;
        }
        
        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
            color: #3498db;
        }
        
        .stat-card.pending .stat-icon { color: #f39c12; }
        .stat-card.analyzed .stat-icon { color: #3498db; }
        .stat-card.completed .stat-icon { color: #2ecc71; }
        .stat-card.recommendations .stat-icon { color: #9b59b6; }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 10px;
            color: #2c3e50;
        }
        
        .stat-label {
            color: #7f8c8d;
            font-size: 1rem;
        }
        
        .dashboard-section {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
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
            color: #2c3e50;
            margin: 0;
            font-size: 1.4rem;
        }
        
        .view-all-link {
            color: #3498db;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.95rem;
        }
        
        .view-all-link:hover {
            text-decoration: underline;
        }
        
        .reports-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .reports-table th {
            background-color: #f8f9fa;
            color: #2c3e50;
            font-weight: 600;
            text-align: left;
            padding: 15px;
            border-bottom: 2px solid #e9ecef;
        }
        
        .reports-table td {
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
            vertical-align: middle;
        }
        
        .reports-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .disease-badge {
            display: inline-block;
            padding: 4px 10px;
            background-color: #e3f2fd;
            color: #1976d2;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
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
        }
        
        .action-card:hover {
            transform: translateY(-5px);
            border-color: #3498db;
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }
        
        .action-icon {
            font-size: 3rem;
            margin-bottom: 20px;
            color: #3498db;
        }
        
        .action-card h4 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-size: 1.2rem;
        }
        
        .action-card p {
            color: #7f8c8d;
            margin-bottom: 20px;
            font-size: 0.95rem;
        }
        
        .action-btn {
            display: inline-block;
            padding: 10px 25px;
            background-color: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 500;
            transition: background-color 0.3s;
        }
        
        .action-btn:hover {
            background-color: #2980b9;
            color: white;
            text-decoration: none;
        }
        
        .common-diseases {
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
            border-left: 4px solid #3498db;
        }
        
        .disease-name {
            font-weight: 500;
            color: #2c3e50;
        }
        
        .disease-count {
            background-color: #3498db;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #7f8c8d;
        }
        
        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            color: #bdc3c7;
        }
        
        .empty-state h4 {
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .health-tips {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .tip-card {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 8px;
            transition: transform 0.3s;
        }
        
        .tip-card:hover {
            transform: translateX(5px);
        }
        
        .tip-icon {
            width: 45px;
            height: 45px;
            background-color: #e3f2fd;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #3498db;
            font-size: 1.3rem;
        }
        
        .tip-content h4 {
            margin: 0 0 5px 0;
            color: #2c3e50;
            font-size: 1rem;
        }
        
        .tip-content p {
            margin: 0;
            color: #7f8c8d;
            font-size: 0.9rem;
        }
        
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .badge-info {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        
        .badge-success {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        
        .badge-warning {
            background-color: #fff3e0;
            color: #f57c00;
        }
        
        .text-muted {
            color: #95a5a6;
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .quick-actions {
                grid-template-columns: 1fr;
            }
            
            .reports-table {
                display: block;
                overflow-x: auto;
            }
            
            .welcome-message {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .user-info-card {
                width: 100%;
            }
            
            .health-tips {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <div class="welcome-message">
                <div>
                    <h2>Welcome back, <?php echo htmlspecialchars($user_details['username']); ?>! 👋</h2>
                    <p>Monitor your disease reports and health recommendations in one place.</p>
                </div>
                <div class="user-info-card">
                    <h3><i class="fas fa-user-circle"></i> Citizen Account</h3>
                    <p><i class="fas fa-calendar-alt"></i> Member since: <?php echo formatMemberSince($user_details['created_at']); ?></p>
                    <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user_details['email'] ?: 'No email provided'); ?></p>
                    <?php if (!empty($user_details['preferred_language'])): ?>
                    <p><i class="fas fa-language"></i> Language: <?php echo strtoupper($user_details['preferred_language']); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="quick-actions">
            <div class="action-card">
                <div class="action-icon">
                    <i class="fas fa-plus-circle"></i>
                </div>
                <h4>Submit New Report</h4>
                <p>Report a disease or health concern in your area</p>
                <a href="create_report.php" class="action-btn">Create Report</a>
            </div>
            
            <div class="action-card">
                <div class="action-icon">
                    <i class="fas fa-list-alt"></i>
                </div>
                <h4>View My Reports</h4>
                <p>Check the status of your submitted reports</p>
                <a href="view_reports.php" class="action-btn">View Reports</a>
            </div>
            
            <div class="action-card">
                <div class="action-icon">
                    <i class="fas fa-comment-medical"></i>
                </div>
                <h4>Health Recommendations</h4>
                <p>View recommendations from health professionals</p>
                <a href="view_recommendations.php" class="action-btn">View Recommendations</a>
            </div>
            
            <div class="action-card">
                <div class="action-icon">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <h4>Public Dashboard</h4>
                <p>View disease trends in your community</p>
                <a href="../public/public_dashboard.php" class="action-btn">View Trends</a>
            </div>
        </div>
        
        <!-- Statistics Overview -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-file-medical"></i>
                </div>
                <div class="stat-number"><?php echo $total_reports; ?></div>
                <div class="stat-label">Total Reports</div>
            </div>
            
            <div class="stat-card pending">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-number"><?php echo $pending_reports; ?></div>
                <div class="stat-label">Pending Review</div>
            </div>
            
            <div class="stat-card analyzed">
                <div class="stat-icon">
                    <i class="fas fa-search"></i>
                </div>
                <div class="stat-number"><?php echo $analyzed_reports; ?></div>
                <div class="stat-label">Being Analyzed</div>
            </div>
            
            <div class="stat-card completed">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-number"><?php echo $completed_reports; ?></div>
                <div class="stat-label">Completed</div>
            </div>
            
            <div class="stat-card recommendations">
                <div class="stat-icon">
                    <i class="fas fa-comment-medical"></i>
                </div>
                <div class="stat-number"><?php echo $total_recommendations; ?></div>
                <div class="stat-label">Recommendations</div>
            </div>
        </div>
        
        <!-- Disease & Outbreak Map -->
        <div class="dashboard-section">
            <div class="section-header">
                <h3><i class="fas fa-map-marked-alt" style="color: #3498db;"></i> Disease & Outbreak Map</h3>
                <a href="../public/heatmap.php" class="view-all-link">View Full Map →</a>
            </div>
            <p style="color: #7f8c8d; margin-bottom: 15px;">Geographic distribution of disease reports and active outbreaks across Kenya</p>
            
            <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                <select id="diseaseFilter" style="padding: 8px; border: 1px solid #3498db; border-radius: 4px; background: white; color: #2c3e50;">
                    <option value="all">All Diseases</option>
                    <option value="cholera">Cholera</option>
                    <option value="malaria">Malaria</option>
                    <option value="typhoid">Typhoid</option>
                    <option value="dengue">Dengue</option>
                    <option value="covid">COVID-19</option>
                </select>
                <select id="dateFilter" style="padding: 8px; border: 1px solid #3498db; border-radius: 4px; background: white; color: #2c3e50;">
                    <option value="7">Last 7 Days</option>
                    <option value="30" selected>Last 30 Days</option>
                    <option value="90">Last 90 Days</option>
                    <option value="365">Last Year</option>
                </select>
                <button onclick="updateCitizenMap()" style="padding: 8px 15px; background: #3498db; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    <i class="fas fa-sync-alt"></i> Update
                </button>
            </div>
            
            <div id="citizenMap" style="height: 400px; width: 100%; border-radius: 8px; border: 2px solid #3498db;">
                <div style="height: 100%; display: flex; align-items: center; justify-content: center; background: #f0f9ff; color: #3498db;">
                    <div style="text-align: center;">
                        <i class="fas fa-map-marked-alt" style="font-size: 48px; margin-bottom: 15px;"></i>
                        <p>Loading map...</p>
                    </div>
                </div>
            </div>
            
            <div style="display: flex; gap: 20px; margin-top: 15px; flex-wrap: wrap;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 20px; height: 20px; background: rgba(52, 152, 219, 0.3); border-radius: 50%;"></div>
                    <span style="color: #7f8c8d;">Low Activity</span>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 20px; height: 20px; background: rgba(52, 152, 219, 0.6); border-radius: 50%;"></div>
                    <span style="color: #7f8c8d;">Medium Activity</span>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 20px; height: 20px; background: rgba(52, 152, 219, 0.9); border-radius: 50%;"></div>
                    <span style="color: #7f8c8d;">High Activity</span>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 20px; height: 20px; background: #e74c3c; border-radius: 50%;"></div>
                    <span style="color: #7f8c8d;">Outbreak Alert</span>
                </div>
            </div>
        </div>
        
        <!-- Recent Reports -->
        <div class="dashboard-section">
            <div class="section-header">
                <h3>Recent Reports</h3>
                <a href="view_reports.php" class="view-all-link">View All Reports →</a>
            </div>
            
            <?php if (!empty($recent_reports)): ?>
                <div class="table-responsive">
                    <table class="reports-table">
                        <thead>
                            <tr>
                                <th>Disease</th>
                                <th>Location</th>
                                <th>Status</th>
                                <th>Submitted</th>
                                <th>Analyses</th>
                                <th>Recommendations</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_reports as $report): ?>
                                <tr>
                                    <td>
                                        <span class="disease-badge"><?php echo htmlspecialchars($report['disease_name']); ?></span>
                                    </td>
                                    <td><i class="fas fa-map-marker-alt" style="color: #e74c3c; margin-right: 5px;"></i><?php echo htmlspecialchars($report['location']); ?></td>
                                    <td><?php echo getStatusBadge($report['status']); ?></td>
                                    <td><i class="fas fa-clock" style="color: #7f8c8d; margin-right: 5px;"></i><?php echo timeAgo($report['created_at']); ?></td>
                                    <td>
                                        <?php if ($report['analysis_count'] > 0): ?>
                                            <span class="badge badge-info"><?php echo $report['analysis_count']; ?> analysis</span>
                                        <?php else: ?>
                                            <span class="text-muted">None</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($report['recommendation_count'] > 0): ?>
                                            <span class="badge badge-success"><?php echo $report['recommendation_count']; ?> recommendations</span>
                                        <?php else: ?>
                                            <span class="text-muted">None</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="fas fa-file-medical"></i>
                    </div>
                    <h4>No Reports Yet</h4>
                    <p>You haven't submitted any disease reports yet.</p>
                    <a href="create_report.php" class="action-btn" style="margin-top: 15px;">Submit Your First Report</a>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Common Diseases & Quick Stats -->
        <div class="dashboard-section">
            <div class="section-header">
                <h3>Your Most Reported Diseases</h3>
            </div>
            
            <?php if (!empty($common_diseases)): ?>
                <div class="common-diseases">
                    <?php foreach ($common_diseases as $disease): ?>
                        <div class="disease-item">
                            <span class="disease-name"><?php echo htmlspecialchars($disease['disease_name']); ?></span>
                            <span class="disease-count"><?php echo $disease['count']; ?> report<?php echo $disease['count'] > 1 ? 's' : ''; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <p>No disease statistics available yet.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Health Tips Section -->
        <div class="dashboard-section">
            <div class="section-header">
                <h3>Health Tips & Information</h3>
            </div>
            
            <div class="health-tips">
                <div class="tip-card">
                    <div class="tip-icon">
                        <i class="fas fa-hands-wash"></i>
                    </div>
                    <div class="tip-content">
                        <h4>Practice Good Hygiene</h4>
                        <p>Wash hands frequently with soap and water for at least 20 seconds.</p>
                    </div>
                </div>
                
                <div class="tip-card">
                    <div class="tip-icon">
                        <i class="fas fa-head-side-mask"></i>
                    </div>
                    <div class="tip-content">
                        <h4>Use Protective Equipment</h4>
                        <p>Wear masks in crowded areas to prevent disease transmission.</p>
                    </div>
                </div>
                
                <div class="tip-card">
                    <div class="tip-icon">
                        <i class="fas fa-syringe"></i>
                    </div>
                    <div class="tip-content">
                        <h4>Stay Vaccinated</h4>
                        <p>Keep your vaccinations up to date as recommended by health authorities.</p>
                    </div>
                </div>
                
                <div class="tip-card">
                    <div class="tip-icon">
                        <i class="fas fa-home"></i>
                    </div>
                    <div class="tip-content">
                        <h4>Stay Home When Sick</h4>
                        <p>If you experience symptoms, stay home to prevent spreading illness.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    
    <script>
        // Dashboard specific JavaScript
        document.addEventListener('DOMContentLoaded', function() {
            // Animate stat cards on scroll
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
            
            // Update last active time
            function updateLastActive() {
                fetch('../includes/update_last_active.php')
                    .catch(err => console.log('Activity update failed:', err));
            }
            
            // Update every 5 minutes
            setInterval(updateLastActive, 300000);
            updateLastActive(); // Initial update
        });
        
        // Disease Map JavaScript
        var citizenMap = null;
        var citizenMarkersLayer = null;
        
        document.addEventListener('DOMContentLoaded', function() {
            initCitizenMap();
        });
        
        function initCitizenMap() {
            var mapElement = document.getElementById('citizenMap');
            if (mapElement && typeof L !== 'undefined') {
                try {
                    citizenMap = L.map('citizenMap', {
                        center: [-1.2864, 36.8172],
                        zoom: 6,
                        zoomControl: true,
                        attributionControl: true
                    });
                    
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                    }).addTo(citizenMap);
                    
                    updateCitizenMap();
                } catch (e) {
                    console.error('Error initializing map:', e);
                }
            }
        }
        
        function updateCitizenMap() {
            if (!citizenMap) return;
            
            var disease = document.getElementById('diseaseFilter').value;
            var days = document.getElementById('dateFilter').value;
            
            fetch(`../public/api/get_heatmap_data.php?disease=${disease}&days=${days}`)
                .then(response => response.json())
                .then(data => {
                    if (citizenMarkersLayer) {
                        citizenMap.removeLayer(citizenMarkersLayer);
                        citizenMarkersLayer = null;
                    }
                    
                    if (data.points && data.points.length > 0) {
                        citizenMarkersLayer = L.layerGroup().addTo(citizenMap);
                        
                        data.points.forEach(function(p) {
                            var color = p.intensity > 2 ? '#dc2626' : (p.intensity > 1 ? '#f97316' : '#3498db');
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
                            citizenMarkersLayer.addLayer(marker);
                        });
                    }
                    
                    // Display outbreaks
                    if (data.outbreaks && data.outbreaks.length > 0) {
                        if (!citizenMarkersLayer) {
                            citizenMarkersLayer = L.layerGroup().addTo(citizenMap);
                        }
                        data.outbreaks.forEach(function(o) {
                            var affectedArea = L.circle([o.lat, o.lng], {
                                radius: o.radius * 1000,
                                fillColor: '#e74c3c',
                                fillOpacity: 0.15,
                                color: '#e74c3c',
                                weight: 2,
                                opacity: 0.6
                            }).addTo(citizenMap);
                            
                            affectedArea.bindPopup('<div style="min-width:150px;">' +
                                '<strong style="color:#e74c3c;">OUTBREAK ALERT</strong><hr>' +
                                '<strong>Disease:</strong> ' + o.disease + '<br>' +
                                '<strong>Location:</strong> ' + (o.location || 'Unknown') + '<br>' +
                                '<strong>Affected Radius:</strong> ' + o.radius + ' km<br>' +
                                '<strong>Confirmed Cases:</strong> ' + o.cases_confirmed + '<br>' +
                                '<strong>Alert Date:</strong> ' + o.alert_date + '</div>');
                            
                            var centerMarker = L.circleMarker([o.lat, o.lng], {
                                radius: 8,
                                fillColor: '#e74c3c',
                                color: '#ffffff',
                                weight: 2,
                                opacity: 1,
                                fillOpacity: 0.9
                            }).addTo(citizenMap);
                            
                            centerMarker.bindPopup('<div style="min-width:150px;">' +
                                '<strong style="color:#e74c3c;">Outbreak Center</strong><hr>' +
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
                        citizenMap.fitBounds(bounds, {padding: [50, 50]});
                    }
                });
        }
    </script>
</body>
</html>