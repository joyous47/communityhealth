<?php
require_once '../includes/header.php';
require_once '../config/database.php';

requireRole('health_worker', '../auth/login.php');

$user = getCurrentUser();
$user_id = $user['id'];

$db = getDB();

try {
    $stmt = $db->prepare("SELECT COUNT(*) as pending_reports FROM reports WHERE status = 'pending'");
    $stmt->execute();
    $pending_reports = $stmt->fetch()['pending_reports'];
    
    $stmt = $db->prepare("SELECT COUNT(DISTINCT report_id) as analyzed_reports FROM analyses WHERE health_worker_id = ?");
    $stmt->execute([$user_id]);
    $analyzed_reports = $stmt->fetch()['analyzed_reports'];
    
    $stmt = $db->prepare("SELECT COUNT(*) as total_recommendations FROM recommendations WHERE health_worker_id = ?");
    $stmt->execute([$user_id]);
    $total_recommendations = $stmt->fetch()['total_recommendations'];
    
    $stmt = $db->prepare("SELECT COUNT(*) as sent_to_admin FROM analyses WHERE health_worker_id = ? AND sent_to_admin = TRUE");
    $stmt->execute([$user_id]);
    $sent_to_admin = $stmt->fetch()['sent_to_admin'];
    
    $stmt = $db->prepare("SELECT r.*, u.username as citizen_name 
                         FROM reports r 
                         JOIN users u ON r.citizen_id = u.id 
                         WHERE r.status = 'pending' 
                         ORDER BY r.created_at DESC 
                         LIMIT 5");
    $stmt->execute();
    $recent_pending = $stmt->fetchAll();
    
    $stmt = $db->prepare("SELECT a.*, r.disease_name, r.location, r.created_at as report_date 
                         FROM analyses a 
                         JOIN reports r ON a.report_id = r.id 
                         WHERE a.health_worker_id = ? 
                         ORDER BY a.analyzed_at DESC 
                         LIMIT 5");
    $stmt->execute([$user_id]);
    $recent_analyses = $stmt->fetchAll();
    
    $stmt = $db->prepare("SELECT disease_name, COUNT(*) as count 
                         FROM reports 
                         WHERE status = 'pending' 
                         GROUP BY disease_name 
                         ORDER BY count DESC 
                         LIMIT 5");
    $stmt->execute();
    $common_diseases = $stmt->fetchAll();
    
    $stmt = $db->prepare("SELECT severity_level, COUNT(*) as count 
                         FROM analyses 
                         WHERE health_worker_id = ? 
                         GROUP BY severity_level 
                         ORDER BY FIELD(severity_level, 'critical', 'high', 'medium', 'low')");
    $stmt->execute([$user_id]);
    $severity_counts = $stmt->fetchAll();
    
} catch(PDOException $e) {
    $_SESSION['error_message'] = "Error loading dashboard data: " . $e->getMessage();
    $pending_reports = $analyzed_reports = $total_recommendations = $sent_to_admin = 0;
    $recent_pending = $recent_analyses = $common_diseases = $severity_counts = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Worker Dashboard  System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <style>
        body {
            background: #ffffff;
            color: #000000;
        }
        
        .dashboard-header {
            background: #0ea5e9;
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .dashboard-header h2 {
            margin-bottom: 10px;
            font-size: 1.8rem;
            color: white;
        }
        
        .dashboard-header p {
            opacity: 0.9;
            margin-bottom: 0;
            color: white;
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
        }
        
        .user-info-card h3 {
            margin-bottom: 5px;
            font-size: 1.2rem;
            color: white;
        }
        
        .user-info-card p {
            margin: 0;
            font-size: 0.9rem;
            opacity: 0.9;
            color: white;
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
            border-top: 4px solid #0ea5e9;
            border: 1px solid #e2e8f0;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card.pending {
            border-top-color: #000000;
        }
        
        .stat-card.analyzed {
            border-top-color: #0ea5e9;
        }
        
        .stat-card.recommendations {
            border-top-color: #0ea5e9;
        }
        
        .stat-card.admin {
            border-top-color: #000000;
        }
        
        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
            color: #0ea5e9;
        }
        
        .stat-card.pending .stat-icon { color: #000000; }
        .stat-card.analyzed .stat-icon { color: #0ea5e9; }
        .stat-card.recommendations .stat-icon { color: #0ea5e9; }
        .stat-card.admin .stat-icon { color: #000000; }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 10px;
            color: #000000;
        }
        
        .stat-label {
            color: #64748b;
            font-size: 1rem;
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
            border: 1px solid #e2e8f0;
        }
        
        .action-card:hover {
            transform: translateY(-5px);
            border-color: #0ea5e9;
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }
        
        .action-icon {
            font-size: 3rem;
            margin-bottom: 20px;
            color: #0ea5e9;
        }
        
        .action-card h4 {
            color: #000000;
            margin-bottom: 15px;
            font-size: 1.2rem;
        }
        
        .action-card p {
            color: #64748b;
            margin-bottom: 20px;
            font-size: 0.95rem;
        }
        
        .action-btn {
            display: inline-block;
            padding: 10px 25px;
            background-color: #0ea5e9;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 500;
            transition: background-color 0.3s;
        }
        
        .action-btn:hover {
            background-color: #0284c7;
            color: white;
            text-decoration: none;
        }
        
        .dashboard-section {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border: 1px solid #e2e8f0;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f1f5f9;
        }
        
        .section-header h3 {
            color: #000000;
            margin: 0;
            font-size: 1.4rem;
        }
        
        .view-all-link {
            color: #0ea5e9;
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
            background-color: #f1f5f9;
            color: #000000;
            font-weight: 600;
            text-align: left;
            padding: 15px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .reports-table td {
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
            color: #000000;
        }
        
        .reports-table tr:hover {
            background-color: #f8fafc;
        }
        
        .urgency-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .urgency-high {
            background-color: rgba(14, 165, 233, 0.1);
            color: #000000;
            border: 1px solid rgba(14, 165, 233, 0.2);
        }
        
        .urgency-medium {
            background-color: rgba(14, 165, 233, 0.1);
            color: #000000;
            border: 1px solid rgba(14, 165, 233, 0.2);
        }
        
        .urgency-low {
            background-color: rgba(14, 165, 233, 0.1);
            color: #000000;
            border: 1px solid rgba(14, 165, 233, 0.2);
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
            background-color: #f1f5f9;
            border-radius: 8px;
            border-left: 4px solid #0ea5e9;
        }
        
        .disease-name {
            font-weight: 500;
            color: #000000;
        }
        
        .disease-count {
            background-color: #0ea5e9;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .severity-chart {
            display: flex;
            align-items: flex-end;
            gap: 10px;
            height: 200px;
            margin-top: 20px;
        }
        
        .severity-bar {
            flex: 1;
            background-color: #0ea5e9;
            border-radius: 5px 5px 0 0;
            position: relative;
            min-height: 20px;
        }
        
        .severity-bar.critical { background-color: #000000; }
        .severity-bar.high { background-color: #0ea5e9; }
        .severity-bar.medium { background-color: #0ea5e9; }
        .severity-bar.low { background-color: #0ea5e9; }
        
        .severity-label {
            position: absolute;
            bottom: -25px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 0.9rem;
            color: #64748b;
        }
        
        .severity-value {
            position: absolute;
            top: -25px;
            left: 0;
            right: 0;
            text-align: center;
            font-weight: bold;
            color: #000000;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #64748b;
        }
        
        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            color: #cbd5e1;
        }
        
        .empty-state h4 {
            color: #000000;
            margin-bottom: 10px;
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
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="dashboard-header">
            <div class="welcome-message">
                <div>
                    <h2>Welcome, Dr. <?php echo htmlspecialchars($user['username']); ?>! 🩺</h2>
                    <p>Monitor and analyze disease reports from citizens in your community.</p>
                </div>
                <div class="user-info-card">
                    <h3>Health Worker Account</h3>
                    <p>Member since <?php echo formatDate($user['created_at'], 'F Y'); ?></p>
                </div>
            </div>
        </div>
        
        <div class="quick-actions">
            <div class="action-card">
                <div class="action-icon">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <h4>Review Reports</h4>
                <p>Analyze pending disease reports from citizens</p>
                <a href="view_reports.php" class="action-btn">View Reports Queue</a>
            </div>
            
            <div class="action-card">
                <div class="action-icon">
                    <i class="fas fa-comment-medical"></i>
                </div>
                <h4>Create Recommendations</h4>
                <p>Provide health advice based on your analysis</p>
                <a href="create_recommendation.php" class="action-btn">Create Recommendations</a>
            </div>
            
            <div class="action-card">
                <div class="action-icon">
                    <i class="fas fa-paper-plane"></i>
                </div>
                <h4>Send to Admin</h4>
                <p>Forward critical cases to administrators</p>
                <a href="send_to_admin.php" class="action-btn">Send Reports</a>
            </div>
            
            <div class="action-card">
                <div class="action-icon">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <h4>Public Dashboard</h4>
                <p>View community disease trends</p>
                <a href="../public/public_dashboard.php" class="action-btn">View Trends</a>
            </div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card pending">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-number"><?php echo $pending_reports; ?></div>
                <div class="stat-label">Pending Reports</div>
            </div>
            
            <div class="stat-card analyzed">
                <div class="stat-icon">
                    <i class="fas fa-search"></i>
                </div>
                <div class="stat-number"><?php echo $analyzed_reports; ?></div>
                <div class="stat-label">Reports Analyzed</div>
            </div>
            
            <div class="stat-card recommendations">
                <div class="stat-icon">
                    <i class="fas fa-comment-medical"></i>
                </div>
                <div class="stat-number"><?php echo $total_recommendations; ?></div>
                <div class="stat-label">Recommendations</div>
            </div>
            
            <div class="stat-card admin">
                <div class="stat-icon">
                    <i class="fas fa-paper-plane"></i>
                </div>
                <div class="stat-number"><?php echo $sent_to_admin; ?></div>
                <div class="stat-label">Sent to Admin</div>
            </div>
        </div>
        
        <!-- Disease & Outbreak Map -->
        <div class="dashboard-section">
            <div class="section-header">
                <h3><i class="fas fa-map-marked-alt" style="color: #0ea5e9;"></i> Disease & Outbreak Map</h3>
                <a href="outbreak_tracking.php" class="view-all-link">View Outbreak Tracking →</a>
            </div>
            <p style="color: #666; margin-bottom: 15px;">Geographic distribution of disease reports and active outbreaks across Kenya</p>
            
            <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                <select id="diseaseFilter" style="padding: 8px; border: 1px solid #0ea5e9; border-radius: 4px; background: white; color: #1e293b;">
                    <option value="all">All Diseases</option>
                    <option value="cholera">Cholera</option>
                    <option value="malaria">Malaria</option>
                    <option value="typhoid">Typhoid</option>
                    <option value="dengue">Dengue</option>
                    <option value="covid">COVID-19</option>
                </select>
                <select id="dateFilter" style="padding: 8px; border: 1px solid #0ea5e9; border-radius: 4px; background: white; color: #1e293b;">
                    <option value="7">Last 7 Days</option>
                    <option value="30" selected>Last 30 Days</option>
                    <option value="90">Last 90 Days</option>
                    <option value="365">Last Year</option>
                </select>
                <button onclick="updateHWMap()" style="padding: 8px 15px; background: #0ea5e9; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    <i class="fas fa-sync-alt"></i> Update
                </button>
            </div>
            
            <div id="hwMap" style="height: 400px; width: 100%; border-radius: 8px; border: 2px solid #0ea5e9;">
                <div style="height: 100%; display: flex; align-items: center; justify-content: center; background: #f0f9ff; color: #0ea5e9;">
                    <div style="text-align: center;">
                        <i class="fas fa-map-marked-alt" style="font-size: 48px; margin-bottom: 15px;"></i>
                        <p>Loading map...</p>
                    </div>
                </div>
            </div>
            
            <div style="display: flex; gap: 20px; margin-top: 15px; flex-wrap: wrap;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 20px; height: 20px; background: rgba(14, 165, 233, 0.3); border-radius: 50%;"></div>
                    <span style="color: #666;">Low Activity</span>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 20px; height: 20px; background: rgba(14, 165, 233, 0.6); border-radius: 50%;"></div>
                    <span style="color: #666;">Medium Activity</span>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 20px; height: 20px; background: rgba(14, 165, 233, 0.9); border-radius: 50%;"></div>
                    <span style="color: #666;">High Activity</span>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 20px; height: 20px; background: #ef4444; border-radius: 50%;"></div>
                    <span style="color: #666;">Outbreak Alert</span>
                </div>
            </div>
        </div>
        
        <div class="dashboard-section">
            <div class="section-header">
                <h3>Recent Pending Reports</h3>
                <a href="view_reports.php" class="view-all-link">View All Pending Reports →</a>
            </div>
            
            <?php if (!empty($recent_pending)): ?>
                <div class="table-responsive">
                    <table class="reports-table">
                        <thead>
                            <tr>
                                <th>Report ID</th>
                                <th>Disease</th>
                                <th>Citizen</th>
                                <th>Location</th>
                                <th>Submitted</th>
                                <th>Urgency</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_pending as $report): 
                                $urgency = 'low';
                                $symptoms_lower = strtolower($report['symptoms']);
                                $disease_lower = strtolower($report['disease_name']);
                                
                                if (strpos($symptoms_lower, 'emergency') !== false || 
                                    strpos($symptoms_lower, 'severe') !== false ||
                                    strpos($disease_lower, 'covid') !== false ||
                                    strpos($disease_lower, 'critical') !== false) {
                                    $urgency = 'high';
                                } elseif (strpos($symptoms_lower, 'fever') !== false && 
                                         strpos($symptoms_lower, 'cough') !== false) {
                                    $urgency = 'medium';
                                }
                            ?>
                                <tr>
                                    <td>#<?php echo $report['id']; ?></td>
                                    <td>
                                        <span class="disease-badge"><?php echo htmlspecialchars($report['disease_name']); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($report['citizen_name']); ?></td>
                                    <td><?php echo htmlspecialchars($report['location']); ?></td>
                                    <td><?php echo timeAgo($report['created_at']); ?></td>
                                    <td>
                                        <span class="urgency-badge urgency-<?php echo $urgency; ?>">
                                            <?php echo ucfirst($urgency); ?> Priority
                                        </span>
                                    </td>
                                    <td>
                                        <a href="analyze_report.php?id=<?php echo $report['id']; ?>" 
                                           class="action-btn" 
                                           style="padding: 6px 12px; font-size: 0.85rem;">
                                            <i class="fas fa-stethoscope"></i> Analyze
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <h4>No Pending Reports</h4>
                    <p>All reports have been analyzed. Great work!</p>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="dashboard-section">
            <div class="section-header">
                <h3>Your Recent Analyses</h3>
                <a href="#" class="view-all-link">View All Analyses →</a>
            </div>
            
            <?php if (!empty($recent_analyses)): ?>
                <div class="table-responsive">
                    <table class="reports-table">
                        <thead>
                            <tr>
                                <th>Report ID</th>
                                <th>Disease</th>
                                <th>Location</th>
                                <th>Severity</th>
                                <th>Analyzed</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_analyses as $analysis): ?>
                                <tr>
                                    <td>#<?php echo $analysis['report_id']; ?></td>
                                    <td><?php echo htmlspecialchars($analysis['disease_name']); ?></td>
                                    <td><?php echo htmlspecialchars($analysis['location']); ?></td>
                                    <td><?php echo getSeverityBadge($analysis['severity_level']); ?></td>
                                    <td><?php echo timeAgo($analysis['analyzed_at']); ?></td>
                                    <td>
                                        <?php if ($analysis['sent_to_admin']): ?>
                                            <span class="badge badge-info">Sent to Admin</span>
                                        <?php else: ?>
                                            <span class="badge badge-success">Analyzed</span>
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
                        <i class="fas fa-search"></i>
                    </div>
                    <h4>No Analyses Yet</h4>
                    <p>You haven't analyzed any reports yet.</p>
                    <a href="view_reports.php" class="action-btn" style="margin-top: 15px; display: inline-block;">
                        Start Analyzing Reports
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 30px; margin-bottom: 30px;">
            <div class="dashboard-section">
                <div class="section-header">
                    <h3>Most Common Diseases (Pending)</h3>
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
                    <div class="empty-state" style="padding: 20px;">
                        <p>No disease statistics available.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="dashboard-section">
                <div class="section-header">
                    <h3>Your Severity Assessments</h3>
                </div>
                
                <?php if (!empty($severity_counts)): 
                    $max_count = 0;
                    foreach ($severity_counts as $severity) {
                        if ($severity['count'] > $max_count) {
                            $max_count = $severity['count'];
                        }
                    }
                ?>
                    <div class="severity-chart">
                        <?php foreach ($severity_counts as $severity): 
                            $height = $max_count > 0 ? ($severity['count'] / $max_count * 150) : 50;
                        ?>
                            <div class="severity-bar <?php echo $severity['severity_level']; ?>" 
                                 style="height: <?php echo $height; ?>px;">
                                <div class="severity-value"><?php echo $severity['count']; ?></div>
                                <div class="severity-label"><?php echo ucfirst($severity['severity_level']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state" style="padding: 20px;">
                        <p>No severity assessments yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="dashboard-section" style="background-color: #f0f9ff; border: 2px solid #0ea5e9;">
            <div class="section-header" style="border-bottom-color: #bae6fd;">
                <h3 style="color: #0ea5e9;">
                    <i class="fas fa-lightbulb"></i> Quick Reference Guide
                </h3>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                <div>
                    <h4 style="color: #000000; margin-bottom: 10px; font-size: 1rem;">
                        <span class="badge badge-lightblue" style="margin-right: 8px;">Critical</span>
                    </h4>
                    <p style="margin: 0; color: #64748b; font-size: 0.9rem;">
                        Life-threatening conditions requiring immediate attention. Send to admin.
                    </p>
                </div>
                
                <div>
                    <h4 style="color: #000000; margin-bottom: 10px; font-size: 1rem;">
                        <span class="badge badge-lightblue" style="margin-right: 8px;">High</span>
                    </h4>
                    <p style="margin: 0; color: #64748b; font-size: 0.9rem;">
                        Serious conditions requiring prompt analysis and recommendations.
                    </p>
                </div>
                
                <div>
                    <h4 style="color: #000000; margin-bottom: 10px; font-size: 1rem;">
                        <span class="badge badge-lightblue" style="margin-right: 8px;">Medium</span>
                    </h4>
                    <p style="margin: 0; color: #64748b; font-size: 0.9rem;">
                        Moderate conditions that should be analyzed within 24 hours.
                    </p>
                </div>
                
                <div>
                    <h4 style="color: #000000; margin-bottom: 10px; font-size: 1rem;">
                        <span class="badge badge-lightblue" style="margin-right: 8px;">Low</span>
                    </h4>
                    <p style="margin: 0; color: #64748b; font-size: 0.9rem;">
                        Mild conditions that can be analyzed within 48 hours.
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
            
            function refreshPendingCount() {
                fetch('../includes/get_pending_count.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.pending_count !== undefined) {
                            const pendingElement = document.querySelector('.stat-card.pending .stat-number');
                            if (pendingElement) {
                                const oldCount = parseInt(pendingElement.textContent);
                                const newCount = data.pending_count;
                                
                                if (oldCount !== newCount) {
                                    pendingElement.style.color = '#000000';
                                    pendingElement.style.transform = 'scale(1.2)';
                                    
                                    setTimeout(() => {
                                        pendingElement.textContent = newCount;
                                        pendingElement.style.color = '';
                                        pendingElement.style.transform = '';
                                    }, 300);
                                }
                            }
                        }
                    })
                    .catch(err => console.log('Refresh failed:', err));
            }
            
            setInterval(refreshPendingCount, 30000);
            
            const urgentButtons = document.querySelectorAll('.mark-urgent-btn');
            urgentButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const reportId = this.getAttribute('data-report-id');
                    
                    if (confirm('Mark this report as urgent?')) {
                        fetch('mark_urgent.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({ report_id: reportId })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                this.closest('tr').querySelector('.urgency-badge').className = 'urgency-badge urgency-high';
                                this.closest('tr').querySelector('.urgency-badge').textContent = 'High Priority';
                                this.remove();
                            }
                        });
                    }
                });
            });
            
            const tooltips = document.querySelectorAll('[data-toggle="tooltip"]');
            tooltips.forEach(element => {
                element.addEventListener('mouseenter', function() {
                    const tooltip = document.createElement('div');
                    tooltip.className = 'custom-tooltip';
                    tooltip.textContent = this.getAttribute('title');
                    tooltip.style.position = 'absolute';
                    tooltip.style.backgroundColor = '#000000';
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
        });
        
        // Disease Map JavaScript
        var hwMap = null;
        var hwMarkersLayer = null;
        
        document.addEventListener('DOMContentLoaded', function() {
            initHWMap();
        });
        
        function initHWMap() {
            var mapElement = document.getElementById('hwMap');
            if (mapElement && typeof L !== 'undefined') {
                try {
                    hwMap = L.map('hwMap', {
                        center: [-1.2864, 36.8172],
                        zoom: 6,
                        zoomControl: true,
                        attributionControl: true
                    });
                    
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                    }).addTo(hwMap);
                    
                    updateHWMap();
                } catch (e) {
                    console.error('Error initializing map:', e);
                }
            }
        }
        
        function updateHWMap() {
            if (!hwMap) return;
            
            var disease = document.getElementById('diseaseFilter').value;
            var days = document.getElementById('dateFilter').value;
            
            fetch(`../public/api/get_heatmap_data.php?disease=${disease}&days=${days}`)
                .then(response => response.json())
                .then(data => {
                    if (hwMarkersLayer) {
                        hwMap.removeLayer(hwMarkersLayer);
                        hwMarkersLayer = null;
                    }
                    
                    if (data.points && data.points.length > 0) {
                        hwMarkersLayer = L.layerGroup().addTo(hwMap);
                        
                        data.points.forEach(function(p) {
                            var color = p.intensity > 2 ? '#dc2626' : (p.intensity > 1 ? '#f97316' : '#0ea5e9');
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
                            hwMarkersLayer.addLayer(marker);
                        });
                    }
                    
                    // Display outbreaks
                    if (data.outbreaks && data.outbreaks.length > 0) {
                        if (!hwMarkersLayer) {
                            hwMarkersLayer = L.layerGroup().addTo(hwMap);
                        }
                        data.outbreaks.forEach(function(o) {
                            var affectedArea = L.circle([o.lat, o.lng], {
                                radius: o.radius * 1000,
                                fillColor: '#ef4444',
                                fillOpacity: 0.15,
                                color: '#ef4444',
                                weight: 2,
                                opacity: 0.6
                            }).addTo(hwMap);
                            
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
                            }).addTo(hwMap);
                            
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
                        hwMap.fitBounds(bounds, {padding: [50, 50]});
                    }
                });
        }
    </script>
</body>
</html>