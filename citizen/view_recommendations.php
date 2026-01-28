<?php
require_once '../includes/header.php';
require_once '../config/database.php';

requireRole('citizen', '../auth/login.php');

$user = getCurrentUser();
$user_id = $user['id'];

$db = getDB();

try {
    $query = "SELECT rec.*, 
              a.analysis_details, a.severity_level, a.analyzed_at,
              r.disease_name as report_disease, r.symptoms, r.location,
              u.username as health_worker_name
              FROM recommendations rec
              JOIN analyses a ON rec.analysis_id = a.id
              JOIN reports r ON a.report_id = r.id
              JOIN users u ON rec.health_worker_id = u.id
              WHERE r.citizen_id = ?
              ORDER BY rec.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id]);
    $recommendations = $stmt->fetchAll();
    
    $total_recommendations = count($recommendations);
    
    $severity_counts = [
        'low' => 0,
        'medium' => 0,
        'high' => 0,
        'critical' => 0
    ];
    
    foreach ($recommendations as $rec) {
        $severity_counts[$rec['severity_level']]++;
    }
    
} catch(PDOException $e) {
    $_SESSION['error_message'] = "Error loading recommendations: " . $e->getMessage();
    $recommendations = [];
    $total_recommendations = 0;
    $severity_counts = [
        'low' => 0,
        'medium' => 0,
        'high' => 0,
        'critical' => 0
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Recommendations </title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .recommendations-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .page-header {
            background: #339af0;
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        
        .page-header h2 {
            margin-bottom: 10px;
            font-size: 1.8rem;
        }
        
        .page-header p {
            opacity: 0.9;
        }
        
        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-item {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.08);
            text-align: center;
            border: 2px solid #339af0;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #000000;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666666;
            font-size: 0.9rem;
        }
        
        .severity-icon {
            font-size: 1.5rem;
            margin-bottom: 10px;
            color: #339af0;
        }
        
        .recommendations-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .recommendation-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            overflow: hidden;
            transition: transform 0.3s;
            border: 2px solid #339af0;
        }
        
        .recommendation-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(51, 154, 240, 0.2);
        }
        
        .card-header {
            padding: 20px;
            border-bottom: 2px solid #339af0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .disease-info h3 {
            margin: 0 0 5px 0;
            color: #000000;
            font-size: 1.3rem;
        }
        
        .disease-meta {
            display: flex;
            gap: 15px;
            color: #666666;
            font-size: 0.9rem;
        }
        
        .severity-badge {
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
            border: 2px solid #339af0;
            background-color: white;
            color: #000000;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .section {
            margin-bottom: 25px;
        }
        
        .section:last-child {
            margin-bottom: 0;
        }
        
        .section h4 {
            color: #000000;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #339af0;
            font-size: 1.1rem;
        }
        
        .analysis-content, .recommendation-content {
            line-height: 1.6;
            color: #000000;
            white-space: pre-line;
        }
        
        .recommendation-content {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            border: 2px solid #339af0;
        }
        
        .health-worker-info {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 15px;
            background-color: white;
            border-top: 2px solid #339af0;
        }
        
        .health-worker-avatar {
            width: 40px;
            height: 40px;
            background-color: #339af0;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .health-worker-details h5 {
            margin: 0 0 5px 0;
            color: #000000;
        }
        
        .health-worker-details p {
            margin: 0;
            color: #666666;
            font-size: 0.9rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 50px 20px;
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
        
        .print-btn {
            padding: 8px 20px;
            background-color: #339af0;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
        }
        
        .print-btn:hover {
            background-color: #228be6;
        }
        
        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .filter-tab {
            padding: 10px 20px;
            background-color: white;
            color: #000000;
            border: 2px solid #339af0;
            border-radius: 20px;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        
        .filter-tab:hover {
            background-color: #339af0;
            color: white;
            text-decoration: none;
        }
        
        .filter-tab.active {
            background-color: #339af0;
            color: white;
            border-color: #339af0;
        }
        
        @media (max-width: 768px) {
            .stats-overview {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .card-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .disease-meta {
                flex-direction: column;
                gap: 5px;
            }
            
            .health-worker-info {
                flex-direction: column;
                align-items: flex-start;
            }
        }
        
        @media print {
            .page-header, .stats-overview, .filter-tabs, .print-btn, .health-tips {
                display: none !important;
            }
            
            .recommendation-card {
                box-shadow: none !important;
                border: 2px solid #000000 !important;
                page-break-inside: avoid !important;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="recommendations-container">
            <div class="page-header">
                <h2>Health Recommendations</h2>
                <p>Personalized health advice from our medical professionals based on your submitted reports.</p>
            </div>
            
            <div class="stats-overview">
                <div class="stat-item">
                    <div class="stat-number"><?php echo $total_recommendations; ?></div>
                    <div class="stat-label">Total Recommendations</div>
                </div>
                <div class="stat-item">
                    <div class="severity-icon">
                        <i class="fas fa-thermometer-quarter"></i>
                    </div>
                    <div class="stat-number"><?php echo $severity_counts['low']; ?></div>
                    <div class="stat-label">Low Severity</div>
                </div>
                <div class="stat-item">
                    <div class="severity-icon">
                        <i class="fas fa-thermometer-half"></i>
                    </div>
                    <div class="stat-number"><?php echo $severity_counts['medium']; ?></div>
                    <div class="stat-label">Medium Severity</div>
                </div>
                <div class="stat-item">
                    <div class="severity-icon">
                        <i class="fas fa-thermometer-three-quarters"></i>
                    </div>
                    <div class="stat-number"><?php echo $severity_counts['high']; ?></div>
                    <div class="stat-label">High Severity</div>
                </div>
                <div class="stat-item">
                    <div class="severity-icon">
                        <i class="fas fa-thermometer-full"></i>
                    </div>
                    <div class="stat-number"><?php echo $severity_counts['critical']; ?></div>
                    <div class="stat-label">Critical Severity</div>
                </div>
            </div>
            
            <div class="filter-tabs">
                <a href="view_recommendations.php" class="filter-tab active">All Recommendations</a>
                <a href="view_recommendations.php?severity=low" class="filter-tab">Low Severity</a>
                <a href="view_recommendations.php?severity=medium" class="filter-tab">Medium Severity</a>
                <a href="view_recommendations.php?severity=high" class="filter-tab">High Severity</a>
                <a href="view_recommendations.php?severity=critical" class="filter-tab">Critical</a>
            </div>
            
            <div class="recommendations-list">
                <?php if (!empty($recommendations)): ?>
                    <?php foreach ($recommendations as $rec): ?>
                        <div class="recommendation-card">
                            <div class="card-header">
                                <div class="disease-info">
                                    <h3><?php echo htmlspecialchars($rec['disease_name']); ?></h3>
                                    <div class="disease-meta">
                                        <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($rec['location']); ?></span>
                                        <span><i class="fas fa-clock"></i> <?php echo timeAgo($rec['created_at']); ?></span>
                                        <span><i class="fas fa-user-md"></i> Dr. <?php echo htmlspecialchars($rec['health_worker_name']); ?></span>
                                    </div>
                                </div>
                                <div class="severity-badge">
                                    <?php echo ucfirst($rec['severity_level']); ?> Severity
                                </div>
                            </div>
                            
                            <div class="card-body">
                                <div class="section">
                                    <h4><i class="fas fa-search"></i> Medical Analysis</h4>
                                    <div class="analysis-content">
                                        <?php echo nl2br(htmlspecialchars($rec['analysis_details'])); ?>
                                    </div>
                                </div>
                                
                                <div class="section">
                                    <h4><i class="fas fa-comment-medical"></i> Health Recommendations</h4>
                                    <div class="recommendation-content">
                                        <?php echo nl2br(htmlspecialchars($rec['recommendation_text'])); ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="health-worker-info">
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div class="health-worker-avatar">
                                        <?php echo strtoupper(substr($rec['health_worker_name'], 0, 1)); ?>
                                    </div>
                                    <div class="health-worker-details">
                                        <h5>Dr. <?php echo htmlspecialchars($rec['health_worker_name']); ?></h5>
                                        <p>Medical Professional | Analyzed on <?php echo formatDate($rec['analyzed_at']); ?></p>
                                    </div>
                                </div>
                                <button class="print-btn" onclick="printRecommendation(this)">
                                    <i class="fas fa-print"></i> Print
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fas fa-comment-medical"></i>
                        </div>
                        <h4>No Recommendations Yet</h4>
                        <p>You haven't received any health recommendations yet. Recommendations will appear here after health workers analyze your reports.</p>
                        <a href="create_report.php" class="btn-primary" style="margin-top: 15px; display: inline-block; padding: 10px 20px; background-color: #339af0; color: white; text-decoration: none; border-radius: 5px;">
                            <i class="fas fa-plus-circle"></i> Submit a Report
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="recommendation-card" style="background-color: white; border: 2px solid #339af0;">
                <div class="card-header" style="border-bottom: none;">
                    <h3 style="margin: 0; color: #000000;">
                        <i class="fas fa-heartbeat"></i> General Health Tips
                    </h3>
                </div>
                <div class="card-body">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                        <div>
                            <h4 style="color: #000000; margin-bottom: 10px; font-size: 1rem;">
                                <i class="fas fa-hands-wash"></i> Hygiene
                            </h4>
                            <p style="margin: 0; color: #666666; font-size: 0.9rem;">
                                Wash hands frequently with soap and water for at least 20 seconds.
                            </p>
                        </div>
                        
                        <div>
                            <h4 style="color: #000000; margin-bottom: 10px; font-size: 1rem;">
                                <i class="fas fa-apple-alt"></i> Nutrition
                            </h4>
                            <p style="margin: 0; color: #666666; font-size: 0.9rem;">
                                Eat a balanced diet rich in fruits and vegetables to boost immunity.
                            </p>
                        </div>
                        
                        <div>
                            <h4 style="color: #000000; margin-bottom: 10px; font-size: 1rem;">
                                <i class="fas fa-bed"></i> Rest
                            </h4>
                            <p style="margin: 0; color: #666666; font-size: 0.9rem;">
                                Get 7-8 hours of sleep per night to help your body recover and stay healthy.
                            </p>
                        </div>
                        
                        <div>
                            <h4 style="color: #000000; margin-bottom: 10px; font-size: 1rem;">
                                <i class="fas fa-running"></i> Exercise
                            </h4>
                            <p style="margin: 0; color: #666666; font-size: 0.9rem;">
                                Regular moderate exercise helps strengthen your immune system.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            window.printRecommendation = function(button) {
                const card = button.closest('.recommendation-card');
                const originalDisplay = card.style.display;
                
                document.querySelectorAll('.recommendation-card').forEach(c => {
                    c.style.display = 'none';
                });
                
                card.style.display = 'block';
                
                window.print();
                
                document.querySelectorAll('.recommendation-card').forEach(c => {
                    c.style.display = '';
                });
                card.style.display = originalDisplay;
            };
            
            const readButtons = document.querySelectorAll('.mark-read-btn');
            readButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const recId = this.getAttribute('data-rec-id');
                    
                    fetch('mark_recommendation_read.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ recommendation_id: recId })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            this.closest('.recommendation-card').style.opacity = '0.7';
                            this.remove();
                        }
                    });
                });
            });
            
            const expandButtons = document.querySelectorAll('.expand-btn');
            expandButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const content = this.closest('.recommendation-card').querySelector('.recommendation-content');
                    const isExpanded = content.classList.contains('expanded');
                    
                    if (isExpanded) {
                        content.classList.remove('expanded');
                        content.style.maxHeight = '200px';
                        this.innerHTML = '<i class="fas fa-chevron-down"></i> Show More';
                    } else {
                        content.classList.add('expanded');
                        content.style.maxHeight = 'none';
                        this.innerHTML = '<i class="fas fa-chevron-up"></i> Show Less';
                    }
                });
            });
            
            document.querySelectorAll('.recommendation-content').forEach(content => {
                if (content.scrollHeight > 200) {
                    content.style.maxHeight = '200px';
                    content.style.overflow = 'hidden';
                    
                    const expandBtn = document.createElement('button');
                    expandBtn.className = 'expand-btn';
                    expandBtn.style.marginTop = '10px';
                    expandBtn.style.padding = '5px 10px';
                    expandBtn.style.backgroundColor = '#339af0';
                    expandBtn.style.color = 'white';
                    expandBtn.style.border = 'none';
                    expandBtn.style.borderRadius = '4px';
                    expandBtn.style.cursor = 'pointer';
                    expandBtn.innerHTML = '<i class="fas fa-chevron-down"></i> Show More';
                    
                    content.parentNode.insertBefore(expandBtn, content.nextSibling);
                }
            });
            
            const searchInput = document.getElementById('searchRecommendations');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    const cards = document.querySelectorAll('.recommendation-card');
                    
                    cards.forEach(card => {
                        const text = card.textContent.toLowerCase();
                        if (text.includes(searchTerm)) {
                            card.style.display = 'block';
                        } else {
                            card.style.display = 'none';
                        }
                    });
                });
            }
        });
    </script>
</body>
</html>