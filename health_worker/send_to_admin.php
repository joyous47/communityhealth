<?php
require_once '../includes/header.php';
require_once '../config/database.php';

requireRole('health_worker', '../auth/login.php');

$user = getCurrentUser();
$user_id = $user['id'];

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error_message'] = "Security token invalid. Please try again.";
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'send_selected') {
            $analysis_ids = $_POST['analysis_ids'] ?? [];
            
            if (empty($analysis_ids)) {
                $_SESSION['error_message'] = "Please select at least one analysis to send.";
            } else {
                try {
                    $placeholders = implode(',', array_fill(0, count($analysis_ids), '?'));
                    $stmt = $db->prepare("UPDATE analyses SET sent_to_admin = TRUE WHERE id IN ($placeholders) AND health_worker_id = ?");
                    $params = array_merge($analysis_ids, [$user_id]);
                    $stmt->execute($params);
                    
                    $count = $stmt->rowCount();
                    $_SESSION['success_message'] = "Successfully sent $count analysis(es) to administrators.";
                    
                } catch(PDOException $e) {
                    $_SESSION['error_message'] = "Error sending analyses: " . $e->getMessage();
                }
            }
        } elseif ($action === 'send_all_critical') {
            try {
                $stmt = $db->prepare("UPDATE analyses SET sent_to_admin = TRUE WHERE severity_level = 'critical' AND health_worker_id = ? AND sent_to_admin = FALSE");
                $stmt->execute([$user_id]);
                
                $count = $stmt->rowCount();
                $_SESSION['success_message'] = "Successfully sent $count critical analysis(es) to administrators.";
                
            } catch(PDOException $e) {
                $_SESSION['error_message'] = "Error sending critical analyses: " . $e->getMessage();
            }
        }
        
        header('Location: send_to_admin.php');
        exit();
    }
}

try {
    $query = "SELECT a.*, r.disease_name, r.location, r.created_at as report_date,
              u.username as citizen_name,
              (SELECT COUNT(*) FROM recommendations WHERE analysis_id = a.id) as recommendation_count
              FROM analyses a
              JOIN reports r ON a.report_id = r.id
              JOIN users u ON r.citizen_id = u.id
              WHERE a.health_worker_id = ? 
              AND a.sent_to_admin = FALSE
              ORDER BY 
                CASE a.severity_level
                    WHEN 'critical' THEN 1
                    WHEN 'high' THEN 2
                    WHEN 'medium' THEN 3
                    WHEN 'low' THEN 4
                END,
                a.analyzed_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id]);
    $analyses = $stmt->fetchAll();
    
    $total_analyses = count($analyses);
    
    $severity_counts = [
        'critical' => 0,
        'high' => 0,
        'medium' => 0,
        'low' => 0
    ];
    
    foreach ($analyses as $analysis) {
        $severity_counts[$analysis['severity_level']]++;
    }
    
    $sent_stmt = $db->prepare("SELECT COUNT(*) as sent_count FROM analyses WHERE health_worker_id = ? AND sent_to_admin = TRUE");
    $sent_stmt->execute([$user_id]);
    $sent_count = $sent_stmt->fetch()['sent_count'];
    
} catch(PDOException $e) {
    $_SESSION['error_message'] = "Error loading analyses: " . $e->getMessage();
    $analyses = [];
    $total_analyses = 0;
    $severity_counts = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
    $sent_count = 0;
}

$csrf_token = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send to Admin System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body {
            background: #ffffff;
            color: #000000;
        }
        
        .send-admin-container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .page-header {
            background: #0ea5e9;
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        
        .page-header h2 {
            margin-bottom: 10px;
            font-size: 1.8rem;
            color: white;
        }
        
        .page-header p {
            opacity: 0.9;
            color: white;
        }
        
        .stats-overview {
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
            border-top: 4px solid #0ea5e9;
            border: 1px solid #e2e8f0;
        }
        
        .stat-card.critical { border-top-color: #000000; }
        .stat-card.high { border-top-color: #000000; }
        .stat-card.medium { border-top-color: #000000; }
        .stat-card.low { border-top-color: #000000; }
        .stat-card.sent { border-top-color: #0ea5e9; }
        
        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
            color: #0ea5e9;
        }
        
        .stat-card.critical .stat-icon { color: #000000; }
        .stat-card.high .stat-icon { color: #000000; }
        .stat-card.medium .stat-icon { color: #000000; }
        .stat-card.low .stat-icon { color: #000000; }
        .stat-card.sent .stat-icon { color: #0ea5e9; }
        
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
        
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        
        .btn-action {
            padding: 12px 25px;
            background-color: #0ea5e9;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            font-size: 1rem;
            transition: background-color 0.3s;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-action:hover {
            background-color: #0284c7;
        }
        
        .btn-action.critical {
            background-color: #000000;
        }
        
        .btn-action.critical:hover {
            background-color: #333333;
        }
        
        .btn-action.select-all {
            background-color: #0ea5e9;
        }
        
        .btn-action.select-all:hover {
            background-color: #0284c7;
        }
        
        .analyses-table-container {
            background-color: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            margin-bottom: 30px;
            border: 1px solid #e2e8f0;
        }
        
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .table-header h3 {
            color: #000000;
            margin: 0;
            font-size: 1.3rem;
        }
        
        .analyses-count {
            color: #64748b;
            font-size: 0.95rem;
        }
        
        .analyses-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .analyses-table th {
            background-color: #f1f5f9;
            color: #000000;
            font-weight: 600;
            text-align: left;
            padding: 15px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .analyses-table td {
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
            color: #000000;
        }
        
        .analyses-table tr:hover {
            background-color: #f8fafc;
        }
        
        .analysis-id {
            font-weight: 600;
            color: #000000;
        }
        
        .disease-name {
            font-weight: 500;
            color: #000000;
        }
        
        .location {
            color: #64748b;
            font-size: 0.9rem;
        }
        
        .citizen-info {
            font-size: 0.9rem;
        }
        
        .citizen-name {
            font-weight: 500;
            color: #000000;
        }
        
        .analysis-preview {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: #64748b;
            font-size: 0.9rem;
        }
        
        .recommendation-count {
            text-align: center;
        }
        
        .recommendation-badge {
            display: inline-block;
            padding: 4px 10px;
            background-color: #0ea5e9;
            color: white;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .checkbox-cell {
            width: 50px;
            text-align: center;
        }
        
        .analysis-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .empty-state {
            text-align: center;
            padding: 50px 20px;
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
        
        .send-form {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            text-align: right;
        }
        
        .btn-send {
            padding: 12px 30px;
            background-color: #0ea5e9;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            font-size: 1.1rem;
            transition: background-color 0.3s;
        }
        
        .btn-send:hover {
            background-color: #0284c7;
        }
        
        .btn-send:disabled {
            background-color: #cbd5e1;
            cursor: not-allowed;
        }
        
        .guidelines-card {
            background-color: #f0f9ff;
            padding: 25px;
            border-radius: 10px;
            border-left: 4px solid #0ea5e9;
            margin-bottom: 30px;
        }
        
        .guidelines-card h3 {
            color: #000000;
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 1.3rem;
        }
        
        .guideline-item {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .guideline-item:last-child {
            margin-bottom: 0;
        }
        
        .guideline-icon {
            font-size: 1.5rem;
            color: #0ea5e9;
            flex-shrink: 0;
        }
        
        .guideline-content h4 {
            color: #000000;
            margin: 0 0 5px 0;
            font-size: 1rem;
        }
        
        .guideline-content p {
            margin: 0;
            color: #64748b;
            font-size: 0.9rem;
        }
        
        @media (max-width: 992px) {
            .analyses-table {
                display: block;
                overflow-x: auto;
            }
            
            .table-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }
        
        @media (max-width: 768px) {
            .stats-overview {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn-action {
                width: 100%;
                justify-content: center;
            }
        }
        
        @media (max-width: 480px) {
            .stats-overview {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="send-admin-container">
            <div class="page-header">
                <h2>Send to Administrators</h2>
                <p>Forward critical analyses to administrators for review and action.</p>
            </div>
            
            <div class="stats-overview">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-file-medical-alt"></i>
                    </div>
                    <div class="stat-number"><?php echo $total_analyses; ?></div>
                    <div class="stat-label">Available Analyses</div>
                </div>
                
                <div class="stat-card critical">
                    <div class="stat-icon">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <div class="stat-number"><?php echo $severity_counts['critical']; ?></div>
                    <div class="stat-label">Critical Severity</div>
                </div>
                
                <div class="stat-card high">
                    <div class="stat-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-number"><?php echo $severity_counts['high']; ?></div>
                    <div class="stat-label">High Severity</div>
                </div>
                
                <div class="stat-card sent">
                    <div class="stat-icon">
                        <i class="fas fa-paper-plane"></i>
                    </div>
                    <div class="stat-number"><?php echo $sent_count; ?></div>
                    <div class="stat-label">Already Sent</div>
                </div>
            </div>
            
            <div class="guidelines-card">
                <h3><i class="fas fa-info-circle"></i> When to Send to Administrators</h3>
                
                <div class="guideline-item">
                    <div class="guideline-icon">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <div class="guideline-content">
                        <h4>Critical Severity Cases</h4>
                        <p>All critical severity analyses should be sent to administrators for immediate review and action.</p>
                    </div>
                </div>
                
                <div class="guideline-item">
                    <div class="guideline-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="guideline-content">
                        <h4>Potential Outbreaks</h4>
                        <p>Reports indicating potential disease outbreaks or clusters in specific locations.</p>
                    </div>
                </div>
                
                <div class="guideline-item">
                    <div class="guideline-icon">
                        <i class="fas fa-ambulance"></i>
                    </div>
                    <div class="guideline-content">
                        <h4>Emergency Situations</h4>
                        <p>Cases requiring immediate public health intervention or emergency response.</p>
                    </div>
                </div>
                
                <div class="guideline-item">
                    <div class="guideline-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="guideline-content">
                        <h4>Data for Visualization</h4>
                        <p>Analyses that provide important data for system-wide visualizations and trends.</p>
                    </div>
                </div>
            </div>
            
            <div class="action-buttons">
                <button type="button" class="btn-action select-all" id="selectAllBtn">
                    <i class="fas fa-check-square"></i> Select All Analyses
                </button>
                <button type="button" class="btn-action critical" id="selectCriticalBtn">
                    <i class="fas fa-exclamation-circle"></i> Select Critical Only
                </button>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="action" value="send_all_critical">
                    <button type="submit" class="btn-action critical" 
                            <?php echo $severity_counts['critical'] == 0 ? 'disabled' : ''; ?>
                            onclick="return confirm('Send all critical analyses to administrators?')">
                        <i class="fas fa-paper-plane"></i> Send All Critical
                    </button>
                </form>
            </div>
            
            <div class="analyses-table-container">
                <div class="table-header">
                    <h3>Analyses Available for Sending</h3>
                    <div class="analyses-count">
                        Showing <?php echo $total_analyses; ?> analysis<?php echo $total_analyses !== 1 ? 'es' : ''; ?>
                    </div>
                </div>
                
                <?php if (!empty($analyses)): ?>
                    <form method="POST" action="" id="sendForm">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <input type="hidden" name="action" value="send_selected">
                        
                        <div class="table-responsive">
                            <table class="analyses-table">
                                <thead>
                                    <tr>
                                        <th class="checkbox-cell">Select</th>
                                        <th>Analysis ID</th>
                                        <th>Disease</th>
                                        <th>Citizen</th>
                                        <th>Location</th>
                                        <th>Analysis Preview</th>
                                        <th>Severity</th>
                                        <th>Analyzed</th>
                                        <th>Recommendations</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($analyses as $analysis): ?>
                                        <tr>
                                            <td class="checkbox-cell">
                                                <input type="checkbox" 
                                                       class="analysis-checkbox" 
                                                       name="analysis_ids[]" 
                                                       value="<?php echo $analysis['id']; ?>"
                                                       data-severity="<?php echo $analysis['severity_level']; ?>">
                                            </td>
                                            <td class="analysis-id">#<?php echo $analysis['id']; ?></td>
                                            <td>
                                                <div class="disease-name"><?php echo htmlspecialchars($analysis['disease_name']); ?></div>
                                            </td>
                                            <td>
                                                <div class="citizen-info">
                                                    <div class="citizen-name"><?php echo htmlspecialchars($analysis['citizen_name']); ?></div>
                                                </div>
                                            </td>
                                            <td class="location"><?php echo htmlspecialchars($analysis['location']); ?></td>
                                            <td>
                                                <div class="analysis-preview" title="<?php echo htmlspecialchars($analysis['analysis_details']); ?>">
                                                    <?php echo truncateText(htmlspecialchars($analysis['analysis_details']), 50); ?>
                                                </div>
                                            </td>
                                            <td><?php echo getSeverityBadge($analysis['severity_level']); ?></td>
                                            <td><?php echo timeAgo($analysis['analyzed_at']); ?></td>
                                            <td class="recommendation-count">
                                                <?php if ($analysis['recommendation_count'] > 0): ?>
                                                    <span class="recommendation-badge">
                                                        <?php echo $analysis['recommendation_count']; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span style="color: #64748b;">None</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="send-form">
                            <button type="submit" 
                                    class="btn-send" 
                                    id="sendSelectedBtn"
                                    disabled
                                    onclick="return confirm('Send selected analyses to administrators?')">
                                <i class="fas fa-paper-plane"></i> Send Selected to Administrators
                                (<span id="selectedCount">0</span> selected)
                            </button>
                        </div>
                    </form>
                    
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h4>No Analyses Available</h4>
                        <p>All your analyses have been sent to administrators or you haven't created any analyses yet.</p>
                        <a href="view_reports.php" class="btn-action" style="margin-top: 15px; display: inline-block;">
                            <i class="fas fa-stethoscope"></i> Analyze Reports
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if ($sent_count > 0): ?>
                <div class="guidelines-card" style="background-color: #f0f9ff; border-color: #0ea5e9;">
                    <h3 style="color: #0ea5e9;">
                        <i class="fas fa-check-circle"></i> Previously Sent Analyses
                    </h3>
                    <p style="color: #64748b; margin: 0;">
                        You have sent <strong><?php echo $sent_count; ?> analysis<?php echo $sent_count !== 1 ? 'es' : ''; ?></strong> to administrators. 
                        Administrators can view these in their dashboard and take appropriate action.
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const selectAllBtn = document.getElementById('selectAllBtn');
            const selectCriticalBtn = document.getElementById('selectCriticalBtn');
            const checkboxes = document.querySelectorAll('.analysis-checkbox');
            const sendSelectedBtn = document.getElementById('sendSelectedBtn');
            const selectedCountSpan = document.getElementById('selectedCount');
            
            function updateSelectedCount() {
                const selected = document.querySelectorAll('.analysis-checkbox:checked').length;
                selectedCountSpan.textContent = selected;
                sendSelectedBtn.disabled = selected === 0;
            }
            
            selectAllBtn.addEventListener('click', function() {
                const allChecked = Array.from(checkboxes).every(cb => cb.checked);
                
                checkboxes.forEach(checkbox => {
                    checkbox.checked = !allChecked;
                });
                
                updateSelectedCount();
            });
            
            selectCriticalBtn.addEventListener('click', function() {
                checkboxes.forEach(checkbox => {
                    checkbox.checked = checkbox.getAttribute('data-severity') === 'critical';
                });
                
                updateSelectedCount();
            });
            
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', updateSelectedCount);
            });
            
            updateSelectedCount();
            
            const sendForm = document.getElementById('sendForm');
            if (sendForm) {
                sendForm.addEventListener('submit', function(e) {
                    const selected = document.querySelectorAll('.analysis-checkbox:checked').length;
                    
                    if (selected === 0) {
                        e.preventDefault();
                        alert('Please select at least one analysis to send.');
                        return false;
                    }
                    
                    return confirm(`Send ${selected} analysis(es) to administrators?`);
                });
            }
            
            const analysisPreviews = document.querySelectorAll('.analysis-preview');
            analysisPreviews.forEach(preview => {
                preview.addEventListener('mouseenter', function() {
                    const fullText = this.getAttribute('title');
                    const tooltip = document.createElement('div');
                    tooltip.className = 'analysis-tooltip';
                    tooltip.textContent = fullText;
                    tooltip.style.position = 'absolute';
                    tooltip.style.backgroundColor = '#000000';
                    tooltip.style.color = 'white';
                    tooltip.style.padding = '10px';
                    tooltip.style.borderRadius = '5px';
                    tooltip.style.maxWidth = '500px';
                    tooltip.style.zIndex = '1000';
                    tooltip.style.boxShadow = '0 4px 10px rgba(0,0,0,0.2)';
                    tooltip.style.whiteSpace = 'pre-wrap';
                    tooltip.style.wordBreak = 'break-word';
                    
                    document.body.appendChild(tooltip);
                    
                    const rect = this.getBoundingClientRect();
                    tooltip.style.left = (rect.left + window.scrollX) + 'px';
                    tooltip.style.top = (rect.top + window.scrollY - tooltip.offsetHeight - 10) + 'px';
                    
                    this._tooltip = tooltip;
                });
                
                preview.addEventListener('mouseleave', function() {
                    if (this._tooltip) {
                        this._tooltip.remove();
                        this._tooltip = null;
                    }
                });
            });
            
            const criticalCheckboxes = document.querySelectorAll('.analysis-checkbox[data-severity="critical"]');
            if (criticalCheckboxes.length > 0) {
                setTimeout(() => {
                    const criticalCount = criticalCheckboxes.length;
                    if (criticalCount > 0 && confirm(`You have ${criticalCount} critical analysis(es). Would you like to select them automatically?`)) {
                        criticalCheckboxes.forEach(checkbox => {
                            checkbox.checked = true;
                        });
                        updateSelectedCount();
                    }
                }, 1000);
            }
            
            document.addEventListener('keydown', function(e) {
                if (e.ctrlKey && e.key === 's') {
                    e.preventDefault();
                    if (!sendSelectedBtn.disabled) {
                        sendSelectedBtn.click();
                    }
                }
                if (e.ctrlKey && e.key === 'a') {
                    e.preventDefault();
                    selectAllBtn.click();
                }

                if (e.ctrlKey && e.key === 'c') {
                    e.preventDefault();
                    selectCriticalBtn.click();
                }
            });
        });
    </script>
</body>
</html>