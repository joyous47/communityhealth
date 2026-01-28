<?php
require_once '../includes/header.php';
require_once '../config/database.php';
requireRole('citizen', '../auth/login.php');

$user = getCurrentUser();
$user_id = $user['id'];
$report_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($report_id <= 0) {
    $_SESSION['error_message'] = "Invalid report ID.";
    header('Location: view_reports.php');
    exit();
}

$db = getDB();

try {
    $query = "SELECT r.*, 
              (SELECT COUNT(*) FROM analyses WHERE report_id = r.id) as analysis_count
              FROM reports r 
              WHERE r.id = ? AND r.citizen_id = ?";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$report_id, $user_id]);
    $report = $stmt->fetch();
    
    if (!$report) {
        $_SESSION['error_message'] = "Report not found or you don't have permission to view it.";
        header('Location: view_reports.php');
        exit();
    }
    
    $analysis_query = "SELECT a.*, u.username as health_worker_name
                       FROM analyses a
                       JOIN users u ON a.health_worker_id = u.id
                       WHERE a.report_id = ?
                       ORDER BY a.created_at DESC";
    
    $analysis_stmt = $db->prepare($analysis_query);
    $analysis_stmt->execute([$report_id]);
    $analyses = $analysis_stmt->fetchAll();
    
    $recommendations_query = "SELECT rec.*, u.username as health_worker_name
                             FROM recommendations rec
                             JOIN analyses a ON rec.analysis_id = a.id
                             JOIN users u ON rec.health_worker_id = u.id
                             WHERE a.report_id = ?
                             ORDER BY rec.created_at DESC";
    
    $recommendations_stmt = $db->prepare($recommendations_query);
    $recommendations_stmt->execute([$report_id]);
    $recommendations = $recommendations_stmt->fetchAll();
    
} catch(PDOException $e) {
    $_SESSION['error_message'] = "Error loading report details: " . $e->getMessage();
    header('Location: view_reports.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Details System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .report-details-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .breadcrumb {
            margin-bottom: 30px;
            padding: 12px 20px;
            background-color: #f0f8ff;
            border-radius: 8px;
            font-size: 0.95rem;
            color: #333;
        }

        .breadcrumb a {
            color: #0077cc;
            text-decoration: none;
            margin: 0 5px;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
            color: #005599;
        }

        .report-header {
            background: linear-gradient(135deg, #87ceeb, #5ba4d6);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .report-header-info h2 {
            margin: 0 0 10px 0;
            font-size: 2rem;
        }

        .report-header-info p {
            margin: 5px 0;
            opacity: 0.95;
        }

        .report-actions {
            display: flex;
            gap: 10px;
        }

        .btn-action {
            padding: 10px 20px;
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
            border: 2px solid white;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-action:hover {
            background-color: white;
            color: #0077cc;
            text-decoration: none;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 30px;
        }

        .card {
            background-color: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            border: 1px solid #e0e0e0;
        }

        .card-title {
            font-size: 1.3rem;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e8f4ff;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-title i {
            color: #0077cc;
        }

        .info-group {
            margin-bottom: 20px;
        }

        .info-label {
            font-weight: 600;
            color: #333;
            font-size: 0.95rem;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #666;
        }

        .info-value {
            color: #333;
            font-size: 1rem;
            word-break: break-word;
        }

        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .status-pending {
            background-color: #fff8e1;
            color: #333;
            border: 1px solid #ffd54f;
        }

        .status-analyzed {
            background-color: #e1f5fe;
            color: #333;
            border: 1px solid #4fc3f7;
        }

        .status-completed {
            background-color: #e8f5e8;
            color: #333;
            border: 1px solid #81c784;
        }

        .timeline {
            position: relative;
            padding-left: 30px;
        }

        .timeline-item {
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e0e0e0;
        }

        .timeline-item:last-child {
            border-bottom: none;
        }

        .timeline-dot {
            position: absolute;
            left: -20px;
            top: 5px;
            width: 12px;
            height: 12px;
            background-color: #0077cc;
            border-radius: 50%;
            border: 3px solid white;
            box-shadow: 0 0 0 2px #0077cc;
        }

        .timeline-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .timeline-meta {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 10px;
        }

        .timeline-content {
            color: #333;
            line-height: 1.6;
        }

        .analysis-card {
            background-color: #f8fbff;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #0077cc;
            margin-bottom: 15px;
        }

        .analysis-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .analysis-worker {
            font-weight: 600;
            color: #333;
        }

        .severity-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .severity-low {
            background-color: #e8f5e8;
            color: #333;
            border: 1px solid #81c784;
        }

        .severity-medium {
            background-color: #fff8e1;
            color: #333;
            border: 1px solid #ffd54f;
        }

        .severity-high {
            background-color: #ffebee;
            color: #333;
            border: 1px solid #e57373;
        }

        .severity-critical {
            background-color: #d32f2f;
            color: white;
            border: 1px solid #b71c1c;
        }

        .recommendation-card {
            background-color: #e8f4ff;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #4caf50;
            margin-bottom: 15px;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }

        .empty-state-icon {
            font-size: 3rem;
            color: #b0b0b0;
            margin-bottom: 15px;
        }

        .edit-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background-color: #4caf50;
            color: white;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .edit-btn:hover {
            background-color: #3d8b40;
            color: white;
            text-decoration: none;
        }

        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }

            .report-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .report-actions {
                margin-top: 20px;
                flex-wrap: wrap;
            }

            .analysis-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="report-details-container">
            <div class="breadcrumb">
                <a href="../index.php"><i class="fas fa-home"></i> Home</a>
                <span>/</span>
                <a href="dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="view_reports.php">My Reports</a>
                <span>/</span>
                <span>Report #<?php echo $report['id']; ?></span>
            </div>

            <div class="report-header">
                <div class="report-header-info">
                    <h2>Report #<?php echo $report['id']; ?></h2>
                    <p><i class="fas fa-virus"></i> <?php echo htmlspecialchars($report['disease_name']); ?></p>
                    <p><i class="fas fa-calendar"></i> Submitted <?php echo formatDate($report['created_at']); ?></p>
                </div>
                <div class="report-actions">
                    <?php if ($report['status'] === 'pending'): ?>
                        <a href="edit_report.php?id=<?php echo $report['id']; ?>" class="btn-action edit-btn">
                            <i class="fas fa-edit"></i> Edit Report
                        </a>
                    <?php endif; ?>
                    <a href="view_reports.php" class="btn-action">
                        <i class="fas fa-arrow-left"></i> Back to Reports
                    </a>
                </div>
            </div>

            <div class="content-grid">
                <div class="card">
                    <div class="card-title">
                        <i class="fas fa-file-medical"></i> Report Information
                    </div>

                    <div class="info-group">
                        <div class="info-label">Status</div>
                        <div class="info-value">
                            <span class="status-badge status-<?php echo $report['status']; ?>">
                                <?php echo ucfirst(htmlspecialchars($report['status'])); ?>
                            </span>
                        </div>
                    </div>

                    <div class="info-group">
                        <div class="info-label">Disease Name</div>
                        <div class="info-value"><?php echo htmlspecialchars($report['disease_name']); ?></div>
                    </div>

                    <div class="info-group">
                        <div class="info-label">Location</div>
                        <div class="info-value"><?php echo htmlspecialchars($report['location']); ?></div>
                    </div>

                    <div class="info-group">
                        <div class="info-label">Symptoms</div>
                        <div class="info-value" style="white-space: pre-wrap; line-height: 1.6;">
                            <?php echo htmlspecialchars($report['symptoms']); ?>
                        </div>
                    </div>

                    <div class="info-group">
                        <div class="info-label">Submitted Date</div>
                        <div class="info-value"><?php echo formatDate($report['created_at']); ?></div>
                    </div>

                    <div class="info-group">
                        <div class="info-label">Total Analyses</div>
                        <div class="info-value">
                            <span style="background-color: #e8f4ff; padding: 6px 12px; border-radius: 20px; color: #333; font-weight: 600;">
                                <?php echo count($analyses); ?> Analysis<?php echo count($analyses) !== 1 ? 'es' : ''; ?>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-title">
                        <i class="fas fa-chart-bar"></i> Analysis Overview
                    </div>

                    <div style="text-align: center; padding: 40px 0;">
                        <div style="margin-bottom: 30px;">
                            <div style="font-size: 3rem; font-weight: bold; color: #0077cc;">
                                <?php echo count($analyses); ?>
                            </div>
                            <div style="color: #666; font-size: 1.1rem;">
                                Health Worker Analyses
                            </div>
                        </div>

                        <div style="margin-bottom: 30px;">
                            <div style="font-size: 3rem; font-weight: bold; color: #4caf50;">
                                <?php echo count($recommendations); ?>
                            </div>
                            <div style="color: #666; font-size: 1.1rem;">
                                Recommendations Received
                            </div>
                        </div>

                        <div style="padding: 20px; background-color: #f8fbff; border-radius: 8px; color: #666;">
                            <p style="margin: 0; font-size: 0.95rem;">
                                <?php if ($report['status'] === 'pending'): ?>
                                    <i class="fas fa-clock"></i> Waiting for health worker analysis...
                                <?php elseif ($report['status'] === 'analyzed'): ?>
                                    <i class="fas fa-hourglass-half"></i> Analysis in progress. Check back soon!
                                <?php else: ?>
                                    <i class="fas fa-check-circle"></i> Analysis complete! Review recommendations below.
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!empty($analyses)): ?>
                <div class="card" style="margin-bottom: 30px;">
                    <div class="card-title">
                        <i class="fas fa-microscope"></i> Health Worker Analyses
                    </div>

                    <div class="timeline">
                        <?php foreach ($analyses as $analysis): ?>
                            <div class="timeline-item">
                                <div class="timeline-dot"></div>
                                <div class="analysis-card">
                                    <div class="analysis-header">
                                        <div>
                                            <div class="analysis-worker">
                                                <i class="fas fa-user-md"></i> <?php echo htmlspecialchars($analysis['health_worker_name']); ?>
                                            </div>
                                            <div class="timeline-meta">
                                                <?php echo formatDate($analysis['created_at']); ?>
                                            </div>
                                        </div>
                                        <span class="severity-badge severity-<?php echo strtolower($analysis['severity_level']); ?>">
                                            <?php echo ucfirst(htmlspecialchars($analysis['severity_level'])); ?> Severity
                                        </span>
                                    </div>
                                    <div class="timeline-content">
                                        <strong>Analysis Details:</strong><br>
                                        <div style="margin-top: 10px; line-height: 1.6; white-space: pre-wrap;">
                                            <?php echo htmlspecialchars($analysis['analysis_details']); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="card" style="margin-bottom: 30px;">
                    <div class="card-title">
                        <i class="fas fa-microscope"></i> Health Worker Analyses
                    </div>
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fas fa-inbox"></i>
                        </div>
                        <p>No analyses yet. Health workers will review your report soon.</p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($recommendations)): ?>
                <div class="card">
                    <div class="card-title">
                        <i class="fas fa-lightbulb"></i> Health Recommendations
                    </div>

                    <div class="timeline">
                        <?php foreach ($recommendations as $recommendation): ?>
                            <div class="timeline-item">
                                <div class="timeline-dot" style="background-color: #4caf50; box-shadow: 0 0 0 2px #4caf50;"></div>
                                <div class="recommendation-card">
                                    <div>
                                        <div class="analysis-worker">
                                            <i class="fas fa-check-circle"></i> Recommendation for: <?php echo htmlspecialchars($recommendation['disease_name']); ?>
                                        </div>
                                        <div class="timeline-meta">
                                            From: <?php echo htmlspecialchars($recommendation['health_worker_name']); ?> | 
                                            <?php echo formatDate($recommendation['created_at']); ?>
                                        </div>
                                    </div>
                                    <div class="timeline-content">
                                        <div style="margin-top: 12px; line-height: 1.6; white-space: pre-wrap;">
                                            <?php echo htmlspecialchars($recommendation['recommendation_text']); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const printBtn = document.querySelector('.print-btn');
            if (printBtn) {
                printBtn.addEventListener('click', function() {
                    window.print();
                });
            }
        });
    </script>
</body>
</html>