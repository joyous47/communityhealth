<?php
require_once '../includes/header.php';
require_once '../config/database.php';

requireRole('citizen', '../auth/login.php');

$user = getCurrentUser();
$user_id = $user['id'];

$db = getDB();

try {
    $stmt = $db->prepare("SELECT COUNT(*) as total_reports FROM reports WHERE citizen_id = ?");
    $stmt->execute([$user_id]);
    $total_reports = $stmt->fetch()['total_reports'];
    
    $stmt = $db->prepare("SELECT COUNT(*) as pending_reports FROM reports WHERE citizen_id = ? AND status = 'pending'");
    $stmt->execute([$user_id]);
    $pending_reports = $stmt->fetch()['pending_reports'];
    
    $stmt = $db->prepare("SELECT COUNT(*) as analyzed_reports FROM reports WHERE citizen_id = ? AND status = 'analyzed'");
    $stmt->execute([$user_id]);
    $analyzed_reports = $stmt->fetch()['analyzed_reports'];
    
    $stmt = $db->prepare("SELECT COUNT(*) as completed_reports FROM reports WHERE citizen_id = ? AND status = 'completed'");
    $stmt->execute([$user_id]);
    $completed_reports = $stmt->fetch()['completed_reports'];
    
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
    
    $stmt = $db->prepare("SELECT COUNT(*) as total_recommendations 
                         FROM recommendations rec 
                         JOIN analyses a ON rec.analysis_id = a.id 
                         JOIN reports r ON a.report_id = r.id 
                         WHERE r.citizen_id = ?");
    $stmt->execute([$user_id]);
    $total_recommendations = $stmt->fetch()['total_recommendations'];
    
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Citizen Dashboard </title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        .dashboard-header {
            background: #339af0;
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
        }
        
        .user-info-card h3 {
            margin-bottom: 5px;
            font-size: 1.2rem;
        }
        
        .user-info-card p {
            margin: 0;
            font-size: 0.9rem;
            opacity: 0.9;
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
            border: 2px solid #339af0;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(51, 154, 240, 0.2);
        }
        
        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
            color: #339af0;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 10px;
            color: #000000;
        }
        
        .stat-label {
            color: #666666;
            font-size: 1rem;
        }
        
        .dashboard-section {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border: 2px solid #339af0;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #339af0;
        }
        
        .section-header h3 {
            color: #000000;
            margin: 0;
            font-size: 1.4rem;
        }
        
        .view-all-link {
            color: #339af0;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.95rem;
        }
        
        .view-all-link:hover {
            text-decoration: underline;
            color: #228be6;
        }
        
        .reports-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .reports-table th {
            background-color: #e7f5ff;
            color: #000000;
            font-weight: 600;
            text-align: left;
            padding: 15px;
            border-bottom: 2px solid #339af0;
        }
        
        .reports-table td {
            padding: 15px;
            border-bottom: 1px solid #e7f5ff;
            vertical-align: top;
            color: #000000;
        }
        
        .reports-table tr:hover {
            background-color: #e7f5ff;
        }
        
        .disease-badge {
            display: inline-block;
            padding: 4px 10px;
            background-color: #e7f5ff;
            color: #000000;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            border: 2px solid #339af0;
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
            border: 2px solid #339af0;
        }
        
        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(51, 154, 240, 0.2);
        }
        
        .action-icon {
            font-size: 3rem;
            margin-bottom: 20px;
            color: #339af0;
        }
        
        .action-card h4 {
            color: #000000;
            margin-bottom: 15px;
            font-size: 1.2rem;
        }
        
        .action-card p {
            color: #666666;
            margin-bottom: 20px;
            font-size: 0.95rem;
        }
        
        .action-btn {
            display: inline-block;
            padding: 10px 25px;
            background-color: #339af0;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 500;
            transition: background-color 0.3s;
        }
        
        .action-btn:hover {
            background-color: #228be6;
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
            background-color: white;
            border-radius: 8px;
            border: 2px solid #339af0;
        }
        
        .disease-name {
            font-weight: 500;
            color: #000000;
        }
        
        .disease-count {
            background-color: #339af0;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #666666;
        }
        
        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            color: #339af0;
        }
        
        .empty-state h4 {
            color: #000000;
            margin-bottom: 10px;
        }
        
        .health-tips {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .tip-card {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 20px;
            background-color: white;
            border-radius: 8px;
            border: 2px solid #339af0;
        }
        
        .tip-icon {
            font-size: 2rem;
            color: #339af0;
        }
        
        .tip-content h4 {
            color: #000000;
            margin-bottom: 5px;
            font-size: 1rem;
        }
        
        .tip-content p {
            color: #666666;
            margin: 0;
            font-size: 0.9rem;
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
            
            .health-tips {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="dashboard-header">
            <div class="welcome-message">
                <div>
                    <h2>Welcome back, <?php echo htmlspecialchars($user['username']); ?>! 👋</h2>
                    <p>Monitor your disease reports and health recommendations in one place.</p>
                </div>
                <div class="user-info-card">
                    <h3>Citizen Account</h3>
                    <p>Member since <?php echo formatDate($user['created_at'], 'F Y'); ?></p>
                </div>
            </div>
        </div>
        
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
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-file-medical"></i>
                </div>
                <div class="stat-number"><?php echo $total_reports; ?></div>
                <div class="stat-label">Total Reports</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-number"><?php echo $pending_reports; ?></div>
                <div class="stat-label">Pending Review</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-search"></i>
                </div>
                <div class="stat-number"><?php echo $analyzed_reports; ?></div>
                <div class="stat-label">Being Analyzed</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-number"><?php echo $completed_reports; ?></div>
                <div class="stat-label">Completed</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-comment-medical"></i>
                </div>
                <div class="stat-number"><?php echo $total_recommendations; ?></div>
                <div class="stat-label">Recommendations</div>
            </div>
        </div>
        
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
                                    <td><?php echo htmlspecialchars($report['location']); ?></td>
                                    <td><?php echo getStatusBadge($report['status']); ?></td>
                                    <td><?php echo timeAgo($report['created_at']); ?></td>
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
            
            function updateLastActive() {
                fetch('../includes/update_last_active.php')
                    .catch(err => console.log('Activity update failed:', err));
            }
            
            setInterval(updateLastActive, 300000);
            updateLastActive();
            
            const tooltips = document.querySelectorAll('[data-toggle="tooltip"]');
            tooltips.forEach(tooltip => {
                tooltip.addEventListener('mouseenter', function() {
                    const tooltipText = this.getAttribute('title');
                    const tooltipEl = document.createElement('div');
                    tooltipEl.className = 'custom-tooltip';
                    tooltipEl.textContent = tooltipText;
                    document.body.appendChild(tooltipEl);
                    
                    const rect = this.getBoundingClientRect();
                    tooltipEl.style.position = 'absolute';
                    tooltipEl.style.left = rect.left + rect.width / 2 - tooltipEl.offsetWidth / 2 + 'px';
                    tooltipEl.style.top = rect.top - tooltipEl.offsetHeight - 10 + 'px';
                    tooltipEl.style.pointerEvents = 'none';
                    tooltipEl.style.backgroundColor = '#000000';
                    tooltipEl.style.color = 'white';
                    tooltipEl.style.padding = '5px 10px';
                    tooltipEl.style.borderRadius = '4px';
                    tooltipEl.style.border = '2px solid #339af0';
                    tooltipEl.style.fontSize = '12px';
                    tooltipEl.style.zIndex = '1000';
                    
                    this._tooltipElement = tooltipEl;
                });
                
                tooltip.addEventListener('mouseleave', function() {
                    if (this._tooltipElement) {
                        this._tooltipElement.remove();
                        this._tooltipElement = null;
                    }
                });
            });
        });
    </script>
</body>
</html>