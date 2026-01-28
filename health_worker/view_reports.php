<?php
require_once '../includes/header.php';
require_once '../config/database.php';

requireRole('health_worker', '../auth/login.php');

$user = getCurrentUser();
$user_id = $user['id'];

$db = getDB();

$disease_filter = $_GET['disease'] ?? '';
$location_filter = $_GET['location'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$urgency_filter = $_GET['urgency'] ?? 'all';

$locations = getLocations();

$query = "SELECT r.*, u.username as citizen_name, u.email as citizen_email 
          FROM reports r 
          JOIN users u ON r.citizen_id = u.id 
          WHERE r.status = 'pending'";
          
$params = [];
$types = [];

if (!empty($disease_filter)) {
    $query .= " AND r.disease_name LIKE ?";
    $params[] = "%$disease_filter%";
}

if (!empty($location_filter) && $location_filter !== 'all') {
    $query .= " AND r.location = ?";
    $params[] = $location_filter;
}

if (!empty($date_from)) {
    $query .= " AND DATE(r.created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $query .= " AND DATE(r.created_at) <= ?";
    $params[] = $date_to;
}

switch ($urgency_filter) {
    case 'high':
        $query .= " ORDER BY (
            CASE 
                WHEN r.disease_name LIKE '%COVID%' THEN 1
                WHEN r.symptoms LIKE '%emergency%' OR r.symptoms LIKE '%severe%' THEN 2
                WHEN r.disease_name LIKE '%malaria%' OR r.disease_name LIKE '%dengue%' THEN 3
                ELSE 4
            END
        ), r.created_at ASC";
        break;
    case 'medium':
        $query .= " ORDER BY (
            CASE 
                WHEN r.symptoms LIKE '%fever%' AND r.symptoms LIKE '%cough%' THEN 1
                WHEN r.disease_name LIKE '%influenza%' OR r.disease_name LIKE '%flu%' THEN 2
                ELSE 3
            END
        ), r.created_at ASC";
        break;
    default:
        $query .= " ORDER BY r.created_at DESC";
}

try {
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $pending_reports = $stmt->fetchAll();
    
    $disease_stmt = $db->prepare("SELECT DISTINCT disease_name FROM reports WHERE status = 'pending' ORDER BY disease_name");
    $disease_stmt->execute();
    $distinct_diseases = $disease_stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    
    $total_pending = count($pending_reports);
    
    $high_urgency = 0;
    $medium_urgency = 0;
    $low_urgency = 0;
    
    foreach ($pending_reports as $report) {
        $symptoms_lower = strtolower($report['symptoms']);
        $disease_lower = strtolower($report['disease_name']);
        
        if (strpos($symptoms_lower, 'emergency') !== false || 
            strpos($symptoms_lower, 'severe') !== false ||
            strpos($disease_lower, 'covid') !== false ||
            strpos($disease_lower, 'critical') !== false) {
            $high_urgency++;
        } elseif (strpos($symptoms_lower, 'fever') !== false && 
                 strpos($symptoms_lower, 'cough') !== false) {
            $medium_urgency++;
        } else {
            $low_urgency++;
        }
    }
    
} catch(PDOException $e) {
    $_SESSION['error_message'] = "Error loading pending reports: " . $e->getMessage();
    $pending_reports = [];
    $distinct_diseases = [];
    $total_pending = $high_urgency = $medium_urgency = $low_urgency = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports Queue  System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body {
            background: #ffffff;
            color: #000000;
        }
        
        .reports-queue-container {
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
        
        .urgency-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .urgency-stat {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.08);
            text-align: center;
            border: 1px solid #e2e8f0;
        }
        
        .urgency-stat.high { border-top: 4px solid #000000; }
        .urgency-stat.medium { border-top: 4px solid #000000; }
        .urgency-stat.low { border-top: 4px solid #000000; }
        .urgency-stat.total { border-top: 4px solid #0ea5e9; }
        
        .urgency-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .urgency-stat.high .urgency-number { color: #000000; }
        .urgency-stat.medium .urgency-number { color: #000000; }
        .urgency-stat.low .urgency-number { color: #000000; }
        .urgency-stat.total .urgency-number { color: #0ea5e9; }
        
        .urgency-label {
            color: #64748b;
            font-size: 0.9rem;
        }
        
        .filter-card {
            background-color: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            margin-bottom: 30px;
            border: 1px solid #e2e8f0;
        }
        
        .filter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f1f5f9;
        }
        
        .filter-header h3 {
            color: #000000;
            margin: 0;
            font-size: 1.3rem;
        }
        
        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 0;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #000000;
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .form-control, .form-select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 5px;
            font-size: 0.95rem;
            background: white;
            color: #000000;
        }
        
        .filter-actions {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }
        
        .btn-primary {
            padding: 10px 20px;
            background-color: #0ea5e9;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            transition: background-color 0.3s;
        }
        
        .btn-primary:hover {
            background-color: #0284c7;
        }
        
        .btn-secondary {
            padding: 10px 20px;
            background-color: #64748b;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            text-align: center;
            transition: background-color 0.3s;
        }
        
        .btn-secondary:hover {
            background-color: #475569;
            color: white;
            text-decoration: none;
        }
        
        .reports-table-container {
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
        
        .reports-count {
            color: #64748b;
            font-size: 0.95rem;
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
            vertical-align: top;
            color: #000000;
        }
        
        .reports-table tr:hover {
            background-color: #f8fafc;
        }
        
        .report-id {
            font-weight: 600;
            color: #000000;
        }
        
        .disease-name {
            font-weight: 500;
            color: #000000;
        }
        
        .symptoms-preview {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: #64748b;
            font-size: 0.9rem;
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
        
        .citizen-email {
            color: #64748b;
            font-size: 0.85rem;
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
        
        .actions-cell {
            text-align: right;
            white-space: nowrap;
        }
        
        .action-btn {
            padding: 6px 12px;
            background-color: #0ea5e9;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85rem;
            text-decoration: none;
            display: inline-block;
            margin-left: 5px;
            transition: background-color 0.3s;
        }
        
        .action-btn:hover {
            background-color: #0284c7;
            color: white;
            text-decoration: none;
        }
        
        .action-btn.analyze {
            background-color: #0ea5e9;
        }
        
        .action-btn.view {
            background-color: #000000;
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
        
        .urgency-filter {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .urgency-btn {
            padding: 8px 20px;
            background-color: white;
            color: #64748b;
            border: 1px solid #cbd5e1;
            border-radius: 20px;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        
        .urgency-btn:hover {
            background-color: #f8fafc;
            color: #0ea5e9;
            text-decoration: none;
        }
        
        .urgency-btn.active {
            background-color: #0ea5e9;
            color: white;
            border-color: #0ea5e9;
        }
        
        @media (max-width: 992px) {
            .reports-table {
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
            .urgency-stats {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .filter-form {
                grid-template-columns: 1fr;
            }
            
            .filter-actions {
                flex-direction: column;
            }
            
            .actions-cell {
                white-space: normal;
            }
            
            .action-btn {
                margin-bottom: 5px;
                display: block;
                width: 100%;
            }
        }
        
        @media (max-width: 480px) {
            .urgency-stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="reports-queue-container">
            <div class="page-header">
                <h2>Reports Queue</h2>
                <p>Analyze pending disease reports from citizens. Prioritize based on urgency and symptoms.</p>
            </div>
            
            <div class="urgency-stats">
                <div class="urgency-stat total">
                    <div class="urgency-number"><?php echo $total_pending; ?></div>
                    <div class="urgency-label">Total Pending Reports</div>
                </div>
                <div class="urgency-stat high">
                    <div class="urgency-number"><?php echo $high_urgency; ?></div>
                    <div class="urgency-label">High Urgency</div>
                </div>
                <div class="urgency-stat medium">
                    <div class="urgency-number"><?php echo $medium_urgency; ?></div>
                    <div class="urgency-label">Medium Urgency</div>
                </div>
                <div class="urgency-stat low">
                    <div class="urgency-number"><?php echo $low_urgency; ?></div>
                    <div class="urgency-label">Low Urgency</div>
                </div>
            </div>
            
            <div class="urgency-filter">
                <a href="?urgency=all<?php echo !empty($disease_filter) ? '&disease=' . urlencode($disease_filter) : ''; ?><?php echo !empty($location_filter) ? '&location=' . urlencode($location_filter) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?>" 
                   class="urgency-btn <?php echo $urgency_filter === 'all' ? 'active' : ''; ?>">
                    All Reports
                </a>
                <a href="?urgency=high<?php echo !empty($disease_filter) ? '&disease=' . urlencode($disease_filter) : ''; ?><?php echo !empty($location_filter) ? '&location=' . urlencode($location_filter) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?>" 
                   class="urgency-btn <?php echo $urgency_filter === 'high' ? 'active' : ''; ?>">
                    High Urgency
                </a>
                <a href="?urgency=medium<?php echo !empty($disease_filter) ? '&disease=' . urlencode($disease_filter) : ''; ?><?php echo !empty($location_filter) ? '&location=' . urlencode($location_filter) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?>" 
                   class="urgency-btn <?php echo $urgency_filter === 'medium' ? 'active' : ''; ?>">
                    Medium Urgency
                </a>
                <a href="?urgency=low<?php echo !empty($disease_filter) ? '&disease=' . urlencode($disease_filter) : ''; ?><?php echo !empty($location_filter) ? '&location=' . urlencode($location_filter) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?>" 
                   class="urgency-btn <?php echo $urgency_filter === 'low' ? 'active' : ''; ?>">
                    Low Urgency
                </a>
            </div>
            
            <div class="filter-card">
                <div class="filter-header">
                    <h3>Filter Reports</h3>
                </div>
                <form method="GET" action="" class="filter-form">
                    <input type="hidden" name="urgency" value="<?php echo htmlspecialchars($urgency_filter); ?>">
                    
                    <div class="form-group">
                        <label for="disease">Disease Name</label>
                        <select id="disease" name="disease" class="form-select">
                            <option value="">All Diseases</option>
                            <?php foreach ($distinct_diseases as $disease): ?>
                                <option value="<?php echo htmlspecialchars($disease); ?>" 
                                    <?php echo $disease_filter === $disease ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($disease); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="location">Location</label>
                        <select id="location" name="location" class="form-select">
                            <option value="all">All Locations</option>
                            <?php foreach ($locations as $location): ?>
                                <option value="<?php echo htmlspecialchars($location); ?>" 
                                    <?php echo $location_filter === $location ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($location); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="date_from">Date From</label>
                        <input type="date" 
                               id="date_from" 
                               name="date_from" 
                               class="form-control" 
                               value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="date_to">Date To</label>
                        <input type="date" 
                               id="date_to" 
                               name="date_to" 
                               class="form-control" 
                               value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        <a href="view_reports.php" class="btn-secondary">
                            <i class="fas fa-times"></i> Clear Filters
                        </a>
                    </div>
                </form>
            </div>
            
            <div class="reports-table-container">
                <div class="table-header">
                    <h3>Pending Reports</h3>
                    <div class="reports-count">
                        Showing <?php echo count($pending_reports); ?> report<?php echo count($pending_reports) !== 1 ? 's' : ''; ?>
                    </div>
                </div>
                
                <?php if (!empty($pending_reports)): ?>
                    <div class="table-responsive">
                        <table class="reports-table">
                            <thead>
                                <tr>
                                    <th>Report ID</th>
                                    <th>Disease</th>
                                    <th>Symptoms</th>
                                    <th>Citizen</th>
                                    <th>Location</th>
                                    <th>Submitted</th>
                                    <th>Urgency</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_reports as $report): 
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
                                        <td class="report-id">#<?php echo $report['id']; ?></td>
                                        <td>
                                            <div class="disease-name"><?php echo htmlspecialchars($report['disease_name']); ?></div>
                                        </td>
                                        <td>
                                            <div class="symptoms-preview" title="<?php echo htmlspecialchars($report['symptoms']); ?>">
                                                <?php echo truncateText(htmlspecialchars($report['symptoms']), 50); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="citizen-info">
                                                <div class="citizen-name"><?php echo htmlspecialchars($report['citizen_name']); ?></div>
                                                <div class="citizen-email"><?php echo htmlspecialchars($report['citizen_email']); ?></div>
                                            </div>
                                        </td>
                                        <td class="location"><?php echo htmlspecialchars($report['location']); ?></td>
                                        <td><?php echo timeAgo($report['created_at']); ?></td>
                                        <td>
                                            <span class="urgency-badge urgency-<?php echo $urgency; ?>">
                                                <?php echo ucfirst($urgency); ?> Priority
                                            </span>
                                        </td>
                                        <td class="actions-cell">
                                            <a href="analyze_report.php?id=<?php echo $report['id']; ?>" 
                                               class="action-btn analyze" 
                                               title="Analyze Report">
                                                <i class="fas fa-stethoscope"></i> Analyze
                                            </a>
                                            <a href="../citizen/view_reports.php?report_id=<?php echo $report['id']; ?>" 
                                               target="_blank"
                                               class="action-btn view" 
                                               title="View Details">
                                                <i class="fas fa-eye"></i> View
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
                        <p>
                            <?php if (!empty($disease_filter) || !empty($location_filter) || !empty($date_from) || !empty($date_to) || $urgency_filter !== 'all'): ?>
                                No reports match your current filters. Try adjusting your search criteria.
                                                       <?php else: ?>
                                All reports have been analyzed. Great work!
                            <?php endif; ?>
                        </p>
                        <a href="view_reports.php" class="btn-primary" style="margin-top: 15px; display: inline-block;">
                            <i class="fas fa-sync"></i> Refresh
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="filter-card" style="background-color: #f0f9ff; border-left: 4px solid #0ea5e9;">
                <h3 style="margin-top: 0; color: #000000; margin-bottom: 15px;">
                    <i class="fas fa-lightbulb"></i> Quick Analysis Guidelines
                </h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
                    <div style="padding: 15px; background-color: white; border-radius: 8px; border: 1px solid #e2e8f0;">
                        <h4 style="margin: 0 0 10px 0; color: #000000; font-size: 1rem;">
                            <span style="color: #000000;">⏰ High Urgency:</span>
                        </h4>
                        <p style="margin: 0; color: #64748b; font-size: 0.9rem;">
                            COVID-19 symptoms, severe breathing issues, high fever (≥39°C), emergency cases
                        </p>
                    </div>
                    
                    <div style="padding: 15px; background-color: white; border-radius: 8px; border: 1px solid #e2e8f0;">
                        <h4 style="margin: 0 0 10px 0; color: #000000; font-size: 1rem;">
                            <span style="color: #000000;">⚠ Medium Urgency:</span>
                        </h4>
                        <p style="margin: 0; color: #64748b; font-size: 0.9rem;">
                            Fever with cough, persistent symptoms >3 days, elderly/child patients
                        </p>
                    </div>
                    
                    <div style="padding: 15px; background-color: white; border-radius: 8px; border: 1px solid #e2e8f0;">
                        <h4 style="margin: 0 0 10px 0; color: #000000; font-size: 1rem;">
                            <span style="color: #000000;">✓ Low Urgency:</span>
                        </h4>
                        <p style="margin: 0; color: #64748b; font-size: 0.9rem;">
                            Mild symptoms, common cold, non-contagious conditions, follow-up cases
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const dateFrom = document.getElementById('date_from');
            const dateTo = document.getElementById('date_to');
            
            if (dateFrom && dateTo) {
                dateFrom.addEventListener('change', function() {
                    if (dateTo.value && this.value > dateTo.value) {
                        dateTo.value = this.value;
                    }
                });
                
                dateTo.addEventListener('change', function() {
                    if (dateFrom.value && this.value < dateFrom.value) {
                        dateFrom.value = this.value;
                    }
                });
            }
            
            const pendingCount = <?php echo $total_pending; ?>;
            if (pendingCount > 0) {
                setTimeout(() => {
                    window.location.reload();
                }, 60000);
            }
            
            const markAllBtn = document.getElementById('markAllBtn');
            if (markAllBtn) {
                markAllBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    if (confirm('Mark all visible reports as reviewed? This will remove them from your queue.')) {
                        const reportIds = [];
                        document.querySelectorAll('.report-id').forEach(el => {
                            const id = el.textContent.replace('#', '');
                            reportIds.push(id);
                        });
                        
                        fetch('mark_all_reviewed.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({ report_ids: reportIds })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert(data.message);
                                window.location.reload();
                            }
                        });
                    }
                });
            }
            
            document.addEventListener('keydown', function(e) {
                if (e.ctrlKey && e.key === 'a') {
                    e.preventDefault();
                    const firstAnalyzeBtn = document.querySelector('.action-btn.analyze');
                    if (firstAnalyzeBtn) {
                        firstAnalyzeBtn.click();
                    }
                }
                
                if (e.ctrlKey && e.key === 'r') {
                    e.preventDefault();
                    window.location.reload();
                }
            });
            
            const symptomPreviews = document.querySelectorAll('.symptoms-preview');
            symptomPreviews.forEach(preview => {
                preview.addEventListener('mouseenter', function() {
                    const fullText = this.getAttribute('title');
                    const tooltip = document.createElement('div');
                    tooltip.className = 'symptom-tooltip';
                    tooltip.textContent = fullText;
                    tooltip.style.position = 'absolute';
                    tooltip.style.backgroundColor = '#000000';
                    tooltip.style.color = 'white';
                    tooltip.style.padding = '10px';
                    tooltip.style.borderRadius = '5px';
                    tooltip.style.maxWidth = '400px';
                    tooltip.style.zIndex = '1000';
                    tooltip.style.boxShadow = '0 4px 10px rgba(0,0,0,0.2)';
                    
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
        });
    </script>
</body>
</html>