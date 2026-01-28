<?php
require_once '../includes/header.php';
require_once '../config/database.php';

requireRole('health_worker', '../auth/login.php');

$user = getCurrentUser();
$user_id = $user['id'];

$db = getDB();

$analysis_id = null;
if (isset($_GET['analysis_id']) && is_numeric($_GET['analysis_id'])) {
    $analysis_id = intval($_GET['analysis_id']);
}

$error = '';
$success = '';
$form_data = [
    'disease_name' => '',
    'recommendation_text' => '',
    'analysis_id' => $analysis_id
];

$analysis = null;
$report = null;
$citizen = null;

if ($analysis_id) {
    try {
        $stmt = $db->prepare("SELECT a.*, r.disease_name, r.location, r.citizen_id, 
                             u.username as citizen_name, u.email as citizen_email
                             FROM analyses a
                             JOIN reports r ON a.report_id = r.id
                             JOIN users u ON r.citizen_id = u.id
                             WHERE a.id = ? AND a.health_worker_id = ?");
        $stmt->execute([$analysis_id, $user_id]);
        $analysis = $stmt->fetch();
        
        if ($analysis) {
            $form_data['disease_name'] = $analysis['disease_name'];
            
            $stmt = $db->prepare("SELECT id FROM recommendations WHERE analysis_id = ?");
            $stmt->execute([$analysis_id]);
            $existing_recommendation = $stmt->fetch();
            
            if ($existing_recommendation) {
                $error = "A recommendation already exists for this analysis.";
            }
        } else {
            $error = "Analysis not found or you don't have permission to access it.";
        }
        
    } catch(PDOException $e) {
        $error = "Error loading analysis: " . $e->getMessage();
    }
} else {
    try {
        $stmt = $db->prepare("SELECT a.*, r.disease_name, r.location, 
                             u.username as citizen_name,
                             (SELECT COUNT(*) FROM recommendations WHERE analysis_id = a.id) as has_recommendation
                             FROM analyses a
                             JOIN reports r ON a.report_id = r.id
                             JOIN users u ON r.citizen_id = u.id
                             WHERE a.health_worker_id = ? 
                             AND (SELECT COUNT(*) FROM recommendations WHERE analysis_id = a.id) = 0
                             ORDER BY a.analyzed_at DESC
                             LIMIT 10");
        $stmt->execute([$user_id]);
        $analyses_needing_recommendations = $stmt->fetchAll();
        
    } catch(PDOException $e) {
        $analyses_needing_recommendations = [];
        $_SESSION['error_message'] = "Error loading analyses: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Security token invalid. Please try again.";
    } else {
        $analysis_id = intval($_POST['analysis_id'] ?? 0);
        $disease_name = trim($_POST['disease_name'] ?? '');
        $recommendation_text = trim($_POST['recommendation_text'] ?? '');
        
        if (empty($analysis_id) || $analysis_id <= 0) {
            $error = "Invalid analysis ID.";
        } elseif (empty($disease_name)) {
            $error = "Disease name is required.";
        } elseif (empty($recommendation_text)) {
            $error = "Recommendation text is required.";
        } elseif (strlen($recommendation_text) > 5000) {
            $error = "Recommendation text is too long.";
        } elseif (strlen($disease_name) > 100) {
            $error = "Disease name is too long.";
        } else {
            try {
                $stmt = $db->prepare("SELECT id FROM analyses WHERE id = ? AND health_worker_id = ?");
                $stmt->execute([$analysis_id, $user_id]);
                
                if ($stmt->fetch()) {
                    $stmt = $db->prepare("SELECT id FROM recommendations WHERE analysis_id = ?");
                    $stmt->execute([$analysis_id]);
                    
                    if (!$stmt->fetch()) {
                        $stmt = $db->prepare("INSERT INTO recommendations (analysis_id, health_worker_id, disease_name, recommendation_text) 
                                             VALUES (?, ?, ?, ?)");
                        $stmt->execute([$analysis_id, $user_id, $disease_name, $recommendation_text]);
                        
                        $recommendation_id = $db->lastInsertId();
                        
                        $success = "Recommendation created successfully! Recommendation ID: #" . $recommendation_id;
                        
                        $form_data = [
                            'disease_name' => '',
                            'recommendation_text' => '',
                            'analysis_id' => $analysis_id
                        ];
                        
                        $stmt = $db->prepare("SELECT a.*, r.disease_name, r.location, r.citizen_id, 
                                             u.username as citizen_name, u.email as citizen_email
                                             FROM analyses a
                                             JOIN reports r ON a.report_id = r.id
                                             JOIN users u ON r.citizen_id = u.id
                                             WHERE a.id = ? AND a.health_worker_id = ?");
                        $stmt->execute([$analysis_id, $user_id]);
                        $analysis = $stmt->fetch();
                        
                    } else {
                        $error = "A recommendation already exists for this analysis.";
                    }
                } else {
                    $error = "Analysis not found or you don't have permission to access it.";
                }
                
            } catch(PDOException $e) {
                $error = "Error creating recommendation: " . $e->getMessage();
            }
        }
        
        $form_data = [
            'disease_name' => htmlspecialchars($disease_name),
            'recommendation_text' => htmlspecialchars($recommendation_text),
            'analysis_id' => $analysis_id
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
    <title>Create Recommendation  System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body {
            background: #ffffff;
            color: #000000;
        }
        
        .create-recommendation-container {
            max-width: 1200px;
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
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: rgba(16, 185, 129, 0.1);
            color: #065f46;
            border: 1px solid #10b981;
        }
        
        .alert-error {
            background-color: rgba(239, 68, 68, 0.1);
            color: #7f1d1d;
            border: 1px solid #ef4444;
        }
        
        .alert-info {
            background-color: rgba(14, 165, 233, 0.1);
            color: #075985;
            border: 1px solid #0ea5e9;
        }
        
        .analysis-details-card {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            border-left: 4px solid #0ea5e9;
            border: 1px solid #e2e8f0;
        }
        
        .analysis-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f1f5f9;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .analysis-info h3 {
            color: #000000;
            margin: 0 0 10px 0;
            font-size: 1.4rem;
        }
        
        .analysis-meta {
            display: flex;
            gap: 20px;
            color: #64748b;
            font-size: 0.95rem;
            flex-wrap: wrap;
        }
        
        .analysis-id-badge {
            background-color: #0ea5e9;
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
            color: #000000;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 1.1rem;
        }
        
        .detail-content {
            background-color: #f8fafc;
            padding: 20px;
            border-radius: 8px;
            line-height: 1.6;
            white-space: pre-line;
            color: #000000;
        }
        
        .severity-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .severity-critical {
            background-color: rgba(0, 0, 0, 0.1);
            color: #000000;
            border: 1px solid rgba(0, 0, 0, 0.2);
        }
        
        .severity-high {
            background-color: rgba(14, 165, 233, 0.1);
            color: #000000;
            border: 1px solid rgba(14, 165, 233, 0.2);
        }
        
        .severity-medium {
            background-color: rgba(14, 165, 233, 0.1);
            color: #000000;
            border: 1px solid rgba(14, 165, 233, 0.2);
        }
        
        .severity-low {
            background-color: rgba(14, 165, 233, 0.1);
            color: #000000;
            border: 1px solid rgba(14, 165, 233, 0.2);
        }
        
        .recommendation-form-card {
            background-color: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            border: 1px solid #e2e8f0;
        }
        
        .form-section {
            margin-bottom: 30px;
        }
        
        .form-section h3 {
            color: #000000;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f1f5f9;
            font-size: 1.3rem;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #000000;
            font-weight: 500;
        }
        
        .required::after {
            content: " *";
            color: #000000;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #cbd5e1;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
            font-family: inherit;
            background: white;
            color: #000000;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #0ea5e9;
            box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
        }
        
        textarea.form-control {
            min-height: 200px;
            resize: vertical;
        }
        
        .character-count {
            text-align: right;
            font-size: 0.85rem;
            color: #64748b;
            margin-top: 5px;
        }
        
        .character-count.warning {
            color: #f97316;
        }
        
        .character-count.error {
            color: #ef4444;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }
        
        .btn-submit {
            padding: 14px 30px;
            background-color: #0ea5e9;
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
            background-color: #0284c7;
        }
        
        .btn-cancel {
            padding: 14px 30px;
            background-color: #64748b;
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
            background-color: #475569;
            color: white;
            text-decoration: none;
        }
        
        .recommendation-guide {
            background-color: #f0f9ff;
            padding: 20px;
            border-radius: 8px;
            margin-top: 10px;
            border-left: 3px solid #0ea5e9;
        }
        
        .recommendation-guide h4 {
            margin-top: 0;
            margin-bottom: 10px;
            color: #000000;
            font-size: 1rem;
        }
        
        .recommendation-guide ul {
            margin: 0;
            padding-left: 20px;
            color: #64748b;
            font-size: 0.9rem;
        }
        
        .recommendation-guide li {
            margin-bottom: 5px;
        }
        
        .analyses-list-card {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            border: 1px solid #e2e8f0;
        }
        
        .list-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f1f5f9;
        }
        
        .list-header h3 {
            color: #000000;
            margin: 0;
            font-size: 1.4rem;
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
        
        .create-btn {
            padding: 6px 12px;
            background-color: #0ea5e9;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85rem;
            text-decoration: none;
            display: inline-block;
            transition: background-color 0.3s;
        }
        
        .create-btn:hover {
            background-color: #0284c7;
            color: white;
            text-decoration: none;
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
            .recommendation-form-card {
                padding: 25px;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .analysis-header {
                flex-direction: column;
            }
            
            .analyses-table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="create-recommendation-container">
            <div class="page-header">
                <h2>Create Health Recommendation</h2>
                <p>Provide personalized health advice for citizens based on your analysis.</p>
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
            
            <?php if (!$analysis_id && isset($analyses_needing_recommendations) && !empty($analyses_needing_recommendations)): ?>
                <div class="analyses-list-card">
                    <div class="list-header">
                        <h3>Analyses Needing Recommendations</h3>
                        <div style="color: #64748b; font-size: 0.95rem;">
                            <?php echo count($analyses_needing_recommendations); ?> analysis(es) found
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="analyses-table">
                            <thead>
                                <tr>
                                    <th>Analysis ID</th>
                                    <th>Disease</th>
                                    <th>Citizen</th>
                                    <th>Location</th>
                                    <th>Severity</th>
                                    <th>Analyzed</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($analyses_needing_recommendations as $analysis_item): ?>
                                    <tr>
                                        <td>#<?php echo $analysis_item['id']; ?></td>
                                        <td><?php echo htmlspecialchars($analysis_item['disease_name']); ?></td>
                                        <td><?php echo htmlspecialchars($analysis_item['citizen_name']); ?></td>
                                        <td><?php echo htmlspecialchars($analysis_item['location']); ?></td>
                                        <td>
                                            <span class="severity-badge severity-<?php echo $analysis_item['severity_level']; ?>">
                                                <?php echo ucfirst($analysis_item['severity_level']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo timeAgo($analysis_item['analyzed_at']); ?></td>
                                        <td>
                                            <a href="create_recommendation.php?analysis_id=<?php echo $analysis_item['id']; ?>" 
                                               class="create-btn">
                                                <i class="fas fa-comment-medical"></i> Create Recommendation
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php elseif (!$analysis_id): ?>
                <div class="alert alert-info">
                    <p>No analyses found that need recommendations. You might have already created recommendations for all your analyses, or you haven't analyzed any reports yet.</p>
                    <a href="view_reports.php" class="create-btn" style="margin-top: 10px; display: inline-block;">
                        <i class="fas fa-stethoscope"></i> Analyze Reports
                    </a>
                </div>
            <?php endif; ?>
            
            <?php if ($analysis): ?>
                <div class="analysis-details-card">
                    <div class="analysis-header">
                        <div class="analysis-info">
                            <h3><?php echo htmlspecialchars($analysis['disease_name']); ?></h3>
                            <div class="analysis-meta">
                                <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($analysis['citizen_name']); ?></span>
                                <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($analysis['location']); ?></span>
                                <span><i class="fas fa-clock"></i> <?php echo timeAgo($analysis['analyzed_at']); ?></span>
                            </div>
                        </div>
                        <div class="analysis-id-badge">
                            Analysis #<?php echo $analysis['id']; ?>
                        </div>
                    </div>
                    
                    <div class="detail-section">
                        <h4><i class="fas fa-stethoscope"></i> Medical Analysis</h4>
                        <div class="detail-content">
                            <?php echo nl2br(htmlspecialchars($analysis['analysis_details'])); ?>
                        </div>
                    </div>
                    
                    <div class="detail-section">
                        <h4><i class="fas fa-info-circle"></i> Details</h4>
                        <div class="detail-content">
                            <p><strong>Disease:</strong> <?php echo htmlspecialchars($analysis['disease_name']); ?></p>
                            <p><strong>Severity Level:</strong> 
                                <span class="severity-badge severity-<?php echo $analysis['severity_level']; ?>">
                                    <?php echo ucfirst($analysis['severity_level']); ?>
                                </span>
                            </p>
                            <p><strong>Citizen:</strong> <?php echo htmlspecialchars($analysis['citizen_name']); ?> (<?php echo htmlspecialchars($analysis['citizen_email']); ?>)</p>
                            <p><strong>Location:</strong> <?php echo htmlspecialchars($analysis['location']); ?></p>
                            <p><strong>Analyzed:</strong> <?php echo formatDate($analysis['analyzed_at']); ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($analysis && !$success): ?>
                <div class="recommendation-form-card">
                    <form method="POST" action="" id="recommendationForm">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <input type="hidden" name="analysis_id" value="<?php echo $analysis_id; ?>">
                        
                        <div class="form-section">
                            <h3>Health Recommendation</h3>
                            
                            <div class="form-group">
                                <label for="disease_name" class="required">Disease Name</label>
                                <input type="text" 
                                       id="disease_name" 
                                       name="disease_name" 
                                       class="form-control" 
                                       value="<?php echo $form_data['disease_name']; ?>" 
                                       required
                                       maxlength="100"
                                       placeholder="Enter the disease name...">
                            </div>
                            
                            <div class="form-group">
                                <label for="recommendation_text" class="required">Recommendation Text</label>
                                <textarea id="recommendation_text" 
                                          name="recommendation_text" 
                                          class="form-control" 
                                          required
                                          maxlength="5000"
                                          placeholder="Provide detailed health recommendations..."><?php echo $form_data['recommendation_text']; ?></textarea>
                                <div class="character-count" id="recommendationCount">0 / 5000 characters</div>
                                
                                <div class="recommendation-guide">
                                    <h4>What to include in recommendations:</h4>
                                    <ul>
                                        <li>Immediate actions the citizen should take</li>
                                        <li>Medication suggestions (if applicable)</li>
                                        <li>When to seek emergency medical care</li>
                                        <li>Preventive measures for family members</li>
                                        <li>Follow-up instructions</li>
                                        <li>Dietary and lifestyle recommendations</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn-submit">
                                <i class="fas fa-comment-medical"></i> Create Recommendation
                            </button>
                            <a href="create_recommendation.php" class="btn-cancel">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            <?php elseif ($analysis && $success): ?>
                <div class="recommendation-form-card" style="text-align: center;">
                    <h3 style="color: #000000; margin-bottom: 20px;">
                        <i class="fas fa-check-circle" style="color: #0ea5e9;"></i> Recommendation Created Successfully!
                    </h3>
                    <p style="color: #64748b; margin-bottom: 30px;">
                        Your health recommendation has been saved and will be visible to the citizen.
                    </p>
                    <div class="form-actions" style="justify-content: center;">
                        <a href="create_recommendation.php" class="btn-submit" style="width: auto;">
                            <i class="fas fa-plus-circle"></i> Create Another Recommendation
                        </a>
                        <a href="dashboard.php" class="btn-cancel" style="width: auto;">
                            <i class="fas fa-home"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="analysis-details-card" style="background-color: #f0f9ff; border-color: #0ea5e9;">
                <h3 style="color: #000000; margin-bottom: 20px;">
                    <i class="fas fa-lightbulb"></i> Recommendation Writing Tips
                </h3>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                    <div>
                        <h4 style="color: #000000; margin-bottom: 10px; font-size: 1rem;">
                            <i class="fas fa-user-md" style="color: #0ea5e9;"></i> Be Clear
                        </h4>
                        <p style="margin: 0; color: #64748b; font-size: 0.9rem;">
                            Use simple language that citizens can understand easily.
                        </p>
                    </div>
                    
                    <div>
                        <h4 style="color: #000000; margin-bottom: 10px; font-size: 1rem;">
                            <i class="fas fa-exclamation-triangle" style="color: #000000;"></i> Be Specific
                        </h4>
                        <p style="margin: 0; color: #64748b; font-size: 0.9rem;">
                            Provide specific instructions, dosages, and timelines when applicable.
                        </p>
                    </div>
                    
                    <div>
                        <h4 style="color: #000000; margin-bottom: 10px; font-size: 1rem;">
                            <i class="fas fa-heartbeat" style="color: #000000;"></i> Be Comprehensive
                        </h4>
                        <p style="margin: 0; color: #64748b; font-size: 0.9rem;">
                            Cover all aspects: medication, lifestyle, follow-up, and emergency signs.
                        </p>
                    </div>
                    
                    <div>
                        <h4 style="color: #000000; margin-bottom: 10px; font-size: 1rem;">
                            <i class="fas fa-shield-alt" style="color: #0ea5e9;"></i> Be Preventive
                        </h4>
                        <p style="margin: 0; color: #64748b; font-size: 0.9rem;">
                            Include preventive measures for family members and the community.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const recommendationTextarea = document.getElementById('recommendation_text');
            const recommendationCount = document.getElementById('recommendationCount');
            
            function updateCharacterCount(textarea, countElement, maxLength) {
                if (!textarea || !countElement) return;
                
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
            
            if (recommendationTextarea) {
                recommendationTextarea.addEventListener('input', function() {
                    updateCharacterCount(this, recommendationCount, 5000);
                });
                updateCharacterCount(recommendationTextarea, recommendationCount, 5000);
            }
            
            const recommendationForm = document.getElementById('recommendationForm');
            if (recommendationForm) {
                recommendationForm.addEventListener('submit', function(e) {
                    const diseaseName = document.getElementById('disease_name').value.trim();
                    const recommendationText = document.getElementById('recommendation_text').value.trim();
                    
                    let isValid = true;
                    let errorMessage = '';
                    
                    document.querySelectorAll('.form-control').forEach(el => {
                        el.style.borderColor = '';
                    });
                    
                    if (!diseaseName) {
                        document.getElementById('disease_name').style.borderColor = '#000000';
                        isValid = false;
                        errorMessage = 'Please enter a disease name.';
                    } else if (diseaseName.length > 100) {
                        document.getElementById('disease_name').style.borderColor = '#000000';
                        isValid = false;
                        errorMessage = 'Disease name is too long.';
                    }
                    
                    if (!recommendationText) {
                        document.getElementById('recommendation_text').style.borderColor = '#000000';
                        isValid = false;
                        if (!errorMessage) errorMessage = 'Please provide recommendation text.';
                    } else if (recommendationText.length > 5000) {
                        document.getElementById('recommendation_text').style.borderColor = '#000000';
                        isValid = false;
                        if (!errorMessage) errorMessage = 'Recommendation text is too long.';
                    }
                    
                    if (!isValid) {
                        e.preventDefault();
                        alert('Please fix the following errors:\n\n' + errorMessage);
                    }
                });
            }
            
            let autoSaveTimer;
            const recommendationField = document.getElementById('recommendation_text');
            if (recommendationField) {
                recommendationField.addEventListener('input', function() {
                    clearTimeout(autoSaveTimer);
                    autoSaveTimer = setTimeout(() => {
                        const draft = {
                            recommendation_text: this.value,
                            disease_name: document.getElementById('disease_name').value,
                            analysis_id: <?php echo $analysis_id ?: 0; ?>
                        };
                        
                        localStorage.setItem('recommendation_draft_<?php echo $analysis_id ?: 'new'; ?>', JSON.stringify(draft));
                        
                        const notification = document.createElement('div');
                        notification.textContent = 'Draft saved locally';
                        notification.style.position = 'fixed';
                        notification.style.bottom = '20px';
                        notification.style.right = '20px';
                        notification.style.backgroundColor = '#0ea5e9';
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
                
                const savedDraft = localStorage.getItem('recommendation_draft_<?php echo $analysis_id ?: 'new'; ?>');
                if (savedDraft && !<?php echo $success ? 'true' : 'false'; ?>) {
                    const draft = JSON.parse(savedDraft);
                    if (confirm('You have a saved draft for this recommendation. Would you like to load it?')) {
                        recommendationField.value = draft.recommendation_text || '';
                        updateCharacterCount(recommendationField, recommendationCount, 5000);
                        
                        if (draft.disease_name && document.getElementById('disease_name')) {
                            document.getElementById('disease_name').value = draft.disease_name;
                        }
                    }
                }
            }
            
            const successAlert = document.querySelector('.alert-success');
            if (successAlert) {
                localStorage.removeItem('recommendation_draft_<?php echo $analysis_id ?: 'new'; ?>');
            }
            
            const templateButtons = document.createElement('div');
            templateButtons.innerHTML = `
                <div style="margin-top: 10px; display: flex; gap: 10px; flex-wrap: wrap;">
                    <button type="button" class="template-btn" data-template="mild_flu" style="padding: 8px 15px; background: #0ea5e9; color: white; border: none; border-radius: 5px; cursor: pointer;">Mild Flu Template</button>
                    <button type="button" class="template-btn" data-template="covid" style="padding: 8px 15px; background: #0ea5e9; color: white; border: none; border-radius: 5px; cursor: pointer;">COVID-19 Template</button>
                    <button type="button" class="template-btn" data-template="food_poisoning" style="padding: 8px 15px; background: #0ea5e9; color: white; border: none; border-radius: 5px; cursor: pointer;">Food Poisoning Template</button>
                </div>
            `;
            
            if (recommendationField) {
                recommendationField.parentNode.appendChild(templateButtons);
                
                const templates = {
                    mild_flu: `Based on your symptoms, you appear to have a mild case of influenza. Here are my recommendations:

1. REST: Get plenty of sleep and avoid strenuous activity.
2. HYDRATION: Drink at least 8 glasses of water daily.
3. MEDICATION: Take over-the-counter fever reducers like acetaminophen as needed.
4. MONITOR: Watch for difficulty breathing or high fever (>39°C).
5. ISOLATE: Stay home to prevent spreading to others.
6. FOLLOW-UP: If symptoms worsen or persist beyond 5 days, contact your doctor.

Most flu cases resolve within 7-10 days with proper rest and hydration.`,
                    
                    covid: `Based on your symptoms, COVID-19 is suspected. Here are my recommendations:

1. ISOLATE IMMEDIATELY: Stay in a separate room from family members.
2. TESTING: Get a COVID-19 test as soon as possible.
3. MONITOR SYMPTOMS: Watch for difficulty breathing, chest pain, or confusion.
4. EMERGENCY SIGNS: Seek emergency care if you have trouble breathing or persistent chest pain.
5. MEDICATION: Take fever reducers as needed. Do not take antibiotics unless prescribed.
6. HYDRATION: Drink plenty of fluids.
7. REPORT: Inform close contacts so they can monitor symptoms.

Most cases are mild, but monitor closely as COVID-19 can worsen rapidly.`,
                    
                    food_poisoning: `Your symptoms suggest food poisoning. Here are my recommendations:

1. HYDRATION: Drink clear fluids (water, broth, electrolyte solutions) frequently.
2. REST: Allow your digestive system to recover.
3. DIET: Start with bland foods (BRAT diet: bananas, rice, applesauce, toast).
4. AVOID: Dairy, fatty foods, caffeine, and alcohol until recovered.
5. MONITOR: Watch for signs of severe dehydration (dizziness, dark urine).
6. MEDICATION: Anti-diarrheal medications may help but consult a doctor first.
7. PREVENTION: Practice good food hygiene to prevent recurrence.

Symptoms should improve within 24-48 hours. If they persist, see a doctor.`
                };
                
                document.querySelectorAll('.template-btn').forEach(button => {
                    button.addEventListener('click', function() {
                        const template = templates[this.getAttribute('data-template')];
                        if (template && confirm('Load this template? Your current text will be replaced.')) {
                            recommendationField.value = template;
                            updateCharacterCount(recommendationField, recommendationCount, 5000);
                        }
                    });
                });
            }
        });
    </script>
</body>
</html>