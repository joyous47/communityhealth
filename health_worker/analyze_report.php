<?php
require_once '../includes/header.php';
require_once '../config/database.php';

requireRole('health_worker', '../auth/login.php');

$user = getCurrentUser();
$user_id = $user['id'];

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "Invalid report ID.";
    header('Location: view_reports.php');
    exit();
}

$report_id = intval($_GET['id']);

$db = getDB();

try {
    $stmt = $db->prepare("SELECT r.*, u.username as citizen_name, u.email as citizen_email 
                         FROM reports r 
                         JOIN users u ON r.citizen_id = u.id 
                         WHERE r.id = ? AND r.status = 'pending'");
    $stmt->execute([$report_id]);
    $report = $stmt->fetch();
    
    if (!$report) {
        $_SESSION['error_message'] = "Report not found or already analyzed.";
        header('Location: view_reports.php');
        exit();
    }
    
    $stmt = $db->prepare("SELECT id FROM analyses WHERE report_id = ? AND health_worker_id = ?");
    $stmt->execute([$report_id, $user_id]);
    $existing_analysis = $stmt->fetch();
    
} catch(PDOException $e) {
    $_SESSION['error_message'] = "Error loading report: " . $e->getMessage();
    header('Location: view_reports.php');
    exit();
}

$error = '';
$success = '';
$form_data = [
    'analysis_details' => '',
    'severity_level' => 'medium',
    'sent_to_admin' => false
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Security token invalid. Please try again.";
    } elseif ($existing_analysis) {
        $error = "You have already analyzed this report.";
    } else {
        $analysis_details = trim($_POST['analysis_details'] ?? '');
        $severity_level = $_POST['severity_level'] ?? 'medium';
        $sent_to_admin = isset($_POST['sent_to_admin']) ? 1 : 0;
        
        if (empty($analysis_details)) {
            $error = "Please provide analysis details.";
        } elseif (!in_array($severity_level, ['low', 'medium', 'high', 'critical'])) {
            $error = "Invalid severity level selected.";
        } elseif (strlen($analysis_details) > 5000) {
            $error = "Analysis details are too long.";
        } else {
            try {
                $db->beginTransaction();
                
                $stmt = $db->prepare("INSERT INTO analyses (report_id, health_worker_id, analysis_details, severity_level, sent_to_admin, analyzed_at) 
                                     VALUES (?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$report_id, $user_id, $analysis_details, $severity_level, $sent_to_admin]);
                
                $analysis_id = $db->lastInsertId();
                
                $stmt = $db->prepare("UPDATE reports SET status = 'analyzed' WHERE id = ?");
                $stmt->execute([$report_id]);
                
                $stmt = $db->prepare("SELECT TIMESTAMPDIFF(HOUR, r.created_at, NOW()) as response_hours 
                                     FROM reports r WHERE r.id = ?");
                $stmt->execute([$report_id]);
                $response_data = $stmt->fetch();
                
                if ($response_data) {
                    $response_hours = $response_data['response_hours'];
                    
                    $stmt = $db->prepare("UPDATE analytics SET response_time_hours = ? WHERE report_id = ?");
                    $stmt->execute([$response_hours, $report_id]);
                }
                
                $db->commit();
                
                $success = "Report analyzed successfully! Analysis ID: #" . $analysis_id;
                
                $show_recommendation_button = true;
                $new_analysis_id = $analysis_id;
                
            } catch(PDOException $e) {
                $db->rollBack();
                $error = "Error saving analysis: " . $e->getMessage();
            }
        }
        
        $form_data = [
            'analysis_details' => htmlspecialchars($analysis_details),
            'severity_level' => htmlspecialchars($severity_level),
            'sent_to_admin' => $sent_to_admin
        ];
    }
}

$csrf_token = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analyze Report - Community Health System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .analyze-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .page-header {
            background: linear-gradient(135deg, #4dabf7, #339af0);
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
        
        .report-details-card {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            border-left: 4px solid #339af0;
        }
        
        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f1f1f1;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .report-info h3 {
            color: #212529;
            margin: 0 0 10px 0;
            font-size: 1.4rem;
        }
        
        .report-meta {
            display: flex;
            gap: 20px;
            color: #6c757d;
            font-size: 0.95rem;
            flex-wrap: wrap;
        }
        
        .report-id-badge {
            background-color: #339af0;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 1rem;
        }
        
        .detail-section {
            margin-bottom: 25px;
        }
        
        .detail-section:last-child {
            margin-bottom: 0;
        }
        
        .detail-section h4 {
            color: #212529;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
            font-size: 1.1rem;
        }
        
        .detail-content {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            line-height: 1.6;
            white-space: pre-line;
            color: #212529;
        }
        
        .form-card {
            background-color: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .form-section {
            margin-bottom: 30px;
        }
        
        .form-section h3 {
            color: #212529;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f1f1f1;
            font-size: 1.3rem;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #212529;
            font-weight: 500;
        }
        
        .required::after {
            content: " *";
            color: #dc3545;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ced4da;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
            font-family: inherit;
            color: #212529;
            background-color: white;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #339af0;
            box-shadow: 0 0 0 3px rgba(51, 154, 240, 0.1);
        }
        
        textarea.form-control {
            min-height: 150px;
            resize: vertical;
        }
        
        .severity-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 10px;
        }
        
        .severity-option {
            padding: 15px;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
            background-color: white;
        }
        
        .severity-option:hover {
            border-color: #339af0;
            background-color: #f8f9fa;
        }
        
        .severity-option.selected {
            border-color: #339af0;
            background-color: #e7f5ff;
        }
        
        .severity-option.low.selected {
            border-color: #51cf66;
            background-color: #ebfbee;
        }
        
        .severity-option.medium.selected {
            border-color: #ff922b;
            background-color: #fff4e6;
        }
        
        .severity-option.high.selected {
            border-color: #ff6b6b;
            background-color: #fff5f5;
        }
        
        .severity-option.critical.selected {
            border-color: #c92a2a;
            background-color: #ffe3e3;
        }
        
        .severity-icon {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .severity-option.low .severity-icon { color: #51cf66; }
        .severity-option.medium .severity-icon { color: #ff922b; }
        .severity-option.high .severity-icon { color: #ff6b6b; }
        .severity-option.critical .severity-icon { color: #c92a2a; }
        
        .severity-name {
            font-weight: 600;
            margin-bottom: 5px;
            color: #212529;
        }
        
        .severity-desc {
            font-size: 0.85rem;
            color: #6c757d;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
        }
        
        .checkbox-group input {
            width: 18px;
            height: 18px;
        }
        
        .checkbox-group label {
            margin: 0;
            font-weight: normal;
            color: #212529;
        }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: #d1e7dd;
            color: #0f5132;
            border: 1px solid #badbcc;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #842029;
            border: 1px solid #f5c2c7;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .btn-submit {
            padding: 14px 30px;
            background-color: #339af0;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s;
            flex: 1;
        }
        
        .btn-submit:hover {
            background-color: #228be6;
        }
        
        .btn-cancel {
            padding: 14px 30px;
            background-color: #6c757d;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s;
            text-decoration: none;
            text-align: center;
            flex: 1;
        }
        
        .btn-cancel:hover {
            background-color: #5c636a;
            color: white;
            text-decoration: none;
        }
        
        .btn-recommendation {
            padding: 14px 30px;
            background-color: #7950f2;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s;
            text-decoration: none;
            text-align: center;
            flex: 1;
            display: none;
        }
        
        .btn-recommendation:hover {
            background-color: #6741d9;
            color: white;
            text-decoration: none;
        }
        
        .character-count {
            text-align: right;
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 5px;
        }
        
        .character-count.warning {
            color: #ff922b;
        }
        
        .character-count.error {
            color: #ff6b6b;
        }
        
        .analysis-guide {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 10px;
            border-left: 3px solid #339af0;
        }
        
        .analysis-guide h4 {
            margin-top: 0;
            margin-bottom: 10px;
            color: #212529;
            font-size: 1rem;
        }
        
        .analysis-guide ul {
            margin: 0;
            padding-left: 20px;
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .analysis-guide li {
            margin-bottom: 5px;
        }
        
        @media (max-width: 768px) {
            .form-card {
                padding: 25px;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .report-header {
                flex-direction: column;
            }
            
            .severity-options {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="analyze-container">
            <div class="page-header">
                <h2>Analyze Disease Report</h2>
                <p>Provide professional medical analysis for citizen-submitted disease reports.</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <div class="report-details-card">
                <div class="report-header">
                    <div class="report-info">
                        <h3><?php echo htmlspecialchars($report['disease_name']); ?></h3>
                        <div class="report-meta">
                            <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($report['citizen_name']); ?></span>
                            <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($report['location']); ?></span>
                            <span><i class="fas fa-clock"></i> <?php echo timeAgo($report['created_at']); ?></span>
                        </div>
                    </div>
                    <div class="report-id-badge">
                        Report #<?php echo $report['id']; ?>
                    </div>
                </div>
                
                <div class="detail-section">
                    <h4><i class="fas fa-stethoscope"></i> Symptoms Reported</h4>
                    <div class="detail-content">
                        <?php echo nl2br(htmlspecialchars($report['symptoms'])); ?>
                    </div>
                </div>
                
                <div class="detail-section">
                    <h4><i class="fas fa-info-circle"></i> Citizen Information</h4>
                    <div class="detail-content">
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($report['citizen_name']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($report['citizen_email']); ?></p>
                        <p><strong>Report Submitted:</strong> <?php echo formatDate($report['created_at']); ?></p>
                        <p><strong>Location:</strong> <?php echo htmlspecialchars($report['location']); ?></p>
                    </div>
                </div>
            </div>
            
            <?php if (!$existing_analysis && !isset($show_recommendation_button)): ?>
            <div class="form-card">
                <form method="POST" action="" id="analysisForm">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    
                    <div class="form-section">
                        <h3>Medical Analysis</h3>
                        
                        <div class="form-group">
                            <label for="analysis_details" class="required">Analysis Details</label>
                            <textarea id="analysis_details" 
                                      name="analysis_details" 
                                      class="form-control" 
                                      required
                                      maxlength="5000"
                                      placeholder="Provide your professional medical analysis..."><?php echo $form_data['analysis_details']; ?></textarea>
                            <div class="character-count" id="analysisCount">0 / 5000 characters</div>
                            
                            <div class="analysis-guide">
                                <h4>Include in your analysis:</h4>
                                <ul>
                                    <li>Probable diagnosis based on symptoms</li>
                                    <li>Contagion risk assessment</li>
                                    <li>Recommended tests or examinations</li>
                                    <li>Immediate care recommendations</li>
                                    <li>Referral suggestions if needed</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h3>Severity Assessment</h3>
                        
                        <div class="severity-options">
                            <div class="severity-option low <?php echo $form_data['severity_level'] === 'low' ? 'selected' : ''; ?>" 
                                 data-value="low">
                                <div class="severity-icon">
                                    <i class="fas fa-thermometer-quarter"></i>
                                </div>
                                <div class="severity-name">Low Severity</div>
                                <div class="severity-desc">
                                    Mild symptoms, non-contagious, self-limiting conditions
                                </div>
                            </div>
                            
                            <div class="severity-option medium <?php echo $form_data['severity_level'] === 'medium' ? 'selected' : ''; ?>" 
                                 data-value="medium">
                                <div class="severity-icon">
                                    <i class="fas fa-thermometer-half"></i>
                                </div>
                                <div class="severity-name">Medium Severity</div>
                                <div class="severity-desc">
                                    Moderate symptoms, some contagion risk, requires monitoring
                                </div>
                            </div>
                            
                            <div class="severity-option high <?php echo $form_data['severity_level'] === 'high' ? 'selected' : ''; ?>" 
                                 data-value="high">
                                <div class="severity-icon">
                                    <i class="fas fa-thermometer-three-quarters"></i>
                                </div>
                                <div class="severity-name">High Severity</div>
                                <div class="severity-desc">
                                    Serious symptoms, high contagion risk, requires medical attention
                                </div>
                            </div>
                            
                            <div class="severity-option critical <?php echo $form_data['severity_level'] === 'critical' ? 'selected' : ''; ?>" 
                                 data-value="critical">
                                <div class="severity-icon">
                                    <i class="fas fa-thermometer-full"></i>
                                </div>
                                <div class="severity-name">Critical Severity</div>
                                <div class="severity-desc">
                                    Life-threatening, emergency care needed, notify authorities
                                </div>
                            </div>
                        </div>
                        
                        <input type="hidden" id="severity_level" name="severity_level" value="<?php echo $form_data['severity_level']; ?>">
                        
                        <div class="checkbox-group">
                            <input type="checkbox" 
                                   id="sent_to_admin" 
                                   name="sent_to_admin" 
                                   value="1" 
                                   <?php echo $form_data['sent_to_admin'] ? 'checked' : ''; ?>>
                            <label for="sent_to_admin">
                                <strong>Send to Administrator</strong> - Check this for critical cases requiring administrative review
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-check-circle"></i> Submit Analysis
                        </button>
                        <a href="view_reports.php" class="btn-cancel">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
            <?php endif; ?>
            
            <?php if (isset($show_recommendation_button) && $show_recommendation_button): ?>
                <div class="form-card" style="text-align: center;">
                    <h3 style="color: #212529; margin-bottom: 20px;">
                        <i class="fas fa-comment-medical"></i> Analysis Complete!
                    </h3>
                    <p style="color: #6c757d; margin-bottom: 30px;">
                        Would you like to create health recommendations for this citizen now?
                    </p>
                    <div class="form-actions" style="justify-content: center;">
                        <a href="create_recommendation.php?analysis_id=<?php echo $new_analysis_id; ?>" 
                           class="btn-recommendation" 
                           style="display: inline-block; width: auto;">
                            <i class="fas fa-comment-medical"></i> Create Recommendations
                        </a>
                        <a href="view_reports.php" class="btn-cancel" style="width: auto;">
                            <i class="fas fa-list"></i> Back to Reports Queue
                        </a>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($existing_analysis && !isset($show_recommendation_button)): ?>
                <div class="form-card" style="text-align: center; background-color: #fff3cd; border: 2px solid #ff922b;">
                    <h3 style="color: #664d03; margin-bottom: 20px;">
                        <i class="fas fa-exclamation-triangle"></i> Already Analyzed
                    </h3>
                    <p style="color: #664d03; margin-bottom: 30px;">
                        You have already analyzed this report. You can view your analysis or create recommendations.
                    </p>
                    <div class="form-actions" style="justify-content: center;">
                        <a href="create_recommendation.php?report_id=<?php echo $report_id; ?>" 
                           class="btn-recommendation" 
                           style="display: inline-block; width: auto;">
                            <i class="fas fa-comment-medical"></i> Create Recommendations
                        </a>
                        <a href="view_reports.php" class="btn-cancel" style="width: auto;">
                            <i class="fas fa-list"></i> Back to Reports Queue
                        </a>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="report-details-card" style="background-color: #f8f9fa; border-color: #339af0;">
                <h3 style="color: #212529; margin-bottom: 20px;">
                    <i class="fas fa-book-medical"></i> Analysis Guidelines
                </h3>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                    <div>
                        <h4 style="color: #212529; margin-bottom: 10px; font-size: 1rem;">
                            <i class="fas fa-search" style="color: #339af0;"></i> Assessment
                        </h4>
                        <p style="margin: 0; color: #6c757d; font-size: 0.9rem;">
                            Evaluate symptoms, identify potential diseases, assess contagion risk.
                        </p>
                    </div>
                    
                    <div>
                        <h4 style="color: #212529; margin-bottom: 10px; font-size: 1rem;">
                            <i class="fas fa-exclamation-triangle" style="color: #ff6b6b;"></i> Severity
                        </h4>
                        <p style="margin: 0; color: #6c757d; font-size: 0.9rem;">
                            Assign appropriate severity level based on symptoms and potential impact.
                        </p>
                    </div>
                    
                    <div>
                        <h4 style="color: #212529; margin-bottom: 10px; font-size: 1rem;">
                            <i class="fas fa-paper-plane" style="color: #7950f2;"></i> Escalation
                        </h4>
                        <p style="margin: 0; color: #6c757d; font-size: 0.9rem;">
                            Send critical cases to administrators for immediate action.
                        </p>
                    </div>
                    
                    <div>
                        <h4 style="color: #212529; margin-bottom: 10px; font-size: 1rem;">
                            <i class="fas fa-comment-medical" style="color: #51cf66;"></i> Recommendations
                        </h4>
                        <p style="margin: 0; color: #6c757d; font-size: 0.9rem;">
                            Provide clear health recommendations for the citizen's recovery.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const analysisTextarea = document.getElementById('analysis_details');
            const analysisCount = document.getElementById('analysisCount');
            
            function updateCharacterCount(textarea, countElement, maxLength) {
                const length = textarea.value.length;
                countElement.textContent = length + ' / ' + maxLength + ' characters';
                
                if (length > maxLength * 0.9) {
                    countElement.classList.add('warning');
                    countElement.classList.remove('error');
                } else if (length > maxLength) {
                    countElement.classList.add('error');
                    countElement.classList.remove('warning');
                } else {
                    countElement.classList.remove('warning', 'error');
                }
            }
            
            if (analysisTextarea) {
                analysisTextarea.addEventListener('input', function() {
                    updateCharacterCount(this, analysisCount, 5000);
                });
                updateCharacterCount(analysisTextarea, analysisCount, 5000);
            }
            
            const severityOptions = document.querySelectorAll('.severity-option');
            const severityInput = document.getElementById('severity_level');
            
            severityOptions.forEach(option => {
                option.addEventListener('click', function() {
                    severityOptions.forEach(opt => {
                        opt.classList.remove('selected');
                    });
                    
                    this.classList.add('selected');
                    
                    severityInput.value = this.getAttribute('data-value');
                });
            });
            
            const analysisForm = document.getElementById('analysisForm');
            if (analysisForm) {
                analysisForm.addEventListener('submit', function(e) {
                    const analysisDetails = document.getElementById('analysis_details').value.trim();
                    const severityLevel = document.getElementById('severity_level').value;
                    
                    let isValid = true;
                    let errorMessage = '';
                    
                    document.querySelectorAll('.form-control').forEach(el => {
                        el.style.borderColor = '';
                    });
                    
                    if (!analysisDetails) {
                        document.getElementById('analysis_details').style.borderColor = '#ff6b6b';
                        isValid = false;
                        errorMessage = 'Please provide analysis details.';
                    } else if (analysisDetails.length > 5000) {
                        document.getElementById('analysis_details').style.borderColor = '#ff6b6b';
                        isValid = false;
                        errorMessage = 'Analysis details are too long.';
                    }
                    
                    if (!severityLevel) {
                        isValid = false;
                        if (!errorMessage) errorMessage = 'Please select a severity level.';
                    }
                    
                    if (!isValid) {
                        e.preventDefault();
                        alert('Please fix the following errors:\n\n' + errorMessage);
                    }
                });
            }
            
            let autoSaveTimer;
            const analysisField = document.getElementById('analysis_details');
            if (analysisField) {
                analysisField.addEventListener('input', function() {
                    clearTimeout(autoSaveTimer);
                    autoSaveTimer = setTimeout(() => {
                        const draft = {
                            analysis_details: this.value,
                            severity_level: document.getElementById('severity_level').value,
                            sent_to_admin: document.getElementById('sent_to_admin')?.checked || false,
                            report_id: <?php echo $report_id; ?>
                        };
                        
                        localStorage.setItem('analysis_draft_<?php echo $report_id; ?>', JSON.stringify(draft));
                        
                        const notification = document.createElement('div');
                        notification.textContent = 'Draft saved locally';
                        notification.style.position = 'fixed';
                        notification.style.bottom = '20px';
                        notification.style.right = '20px';
                        notification.style.backgroundColor = '#339af0';
                        notification.style.color = 'white';
                        notification.style.padding = '10px 20px';
                        notification.style.borderRadius = '5px';
                        notification.style.zIndex = '1000';
                        notification.style.boxShadow = '0 2px 10px rgba(0,0,0,0.2)';
                        
                        document.body.appendChild(notification);
                        
                        setTimeout(() => {
                            notification.remove();
                        }, 2000);
                    }, 2000);
                });
                
                const savedDraft = localStorage.getItem('analysis_draft_<?php echo $report_id; ?>');
                if (savedDraft) {
                    const draft = JSON.parse(savedDraft);
                    if (confirm('You have a saved draft for this analysis. Would you like to load it?')) {
                        analysisField.value = draft.analysis_details;
                        updateCharacterCount(analysisField, analysisCount, 5000);
                        
                        if (draft.severity_level) {
                            severityInput.value = draft.severity_level;
                            severityOptions.forEach(opt => {
                                opt.classList.remove('selected');
                                if (opt.getAttribute('data-value') === draft.severity_level) {
                                    opt.classList.add('selected');
                                }
                            });
                        }
                        
                        if (document.getElementById('sent_to_admin')) {
                            document.getElementById('sent_to_admin').checked = draft.sent_to_admin;
                        }
                    }
                }
            }
            
            const successAlert = document.querySelector('.alert-success');
            if (successAlert) {
                localStorage.removeItem('analysis_draft_<?php echo $report_id; ?>');
            }
        });
    </script>
</body>
</html>