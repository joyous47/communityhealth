<?php
require_once '../includes/header.php';
require_once '../config/database.php';

requireRole('citizen', '../auth/login.php');

$user = getCurrentUser();
$user_id = $user['id'];

$db = getDB();

$status_filter = $_GET['status'] ?? 'all';
$disease_filter = $_GET['disease'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$query = "SELECT r.*, 
          (SELECT COUNT(*) FROM analyses WHERE report_id = r.id) as analysis_count,
          (SELECT COUNT(*) FROM recommendations rec 
           JOIN analyses a ON rec.analysis_id = a.id 
           WHERE a.report_id = r.id) as recommendation_count
          FROM reports r 
          WHERE r.citizen_id = ?";
          
$params = [$user_id];
$types = ['all', 'pending', 'analyzed', 'completed'];

if (in_array($status_filter, $types) && $status_filter !== 'all') {
    $query .= " AND r.status = ?";
    $params[] = $status_filter;
}

if (!empty($disease_filter)) {
    $query .= " AND r.disease_name LIKE ?";
    $params[] = "%$disease_filter%";
}

if (!empty($date_from)) {
    $query .= " AND DATE(r.created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $query .= " AND DATE(r.created_at) <= ?";
    $params[] = $date_to;
}

$query .= " ORDER BY r.created_at DESC";

try {
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $reports = $stmt->fetchAll();
    
    $disease_stmt = $db->prepare("SELECT DISTINCT disease_name FROM reports WHERE citizen_id = ? ORDER BY disease_name");
    $disease_stmt->execute([$user_id]);
    $distinct_diseases = $disease_stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    
} catch(PDOException $e) {
    $_SESSION['error_message'] = "Error loading reports: " . $e->getMessage();
    $reports = [];
    $distinct_diseases = [];
}

try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM reports WHERE citizen_id = ?");
    $stmt->execute([$user_id]);
    $total_reports = $stmt->fetch()['total'];
    
    $stmt = $db->prepare("SELECT status, COUNT(*) as count FROM reports WHERE citizen_id = ? GROUP BY status");
    $stmt->execute([$user_id]);
    $status_counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
} catch(PDOException $e) {
    $total_reports = 0;
    $status_counts = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Reports </title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .reports-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .page-header {
            background: #000000;
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            border: 2px solid #339af0;
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
        
        .filter-card {
            background-color: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            margin-bottom: 30px;
            border: 2px solid #339af0;
        }
        
        .filter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #339af0;
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
            border: 2px solid #339af0;
            border-radius: 5px;
            font-size: 0.95rem;
            color: #000000;
            background-color: white;
        }
        
        .filter-actions {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }
        
        .btn-primary {
            padding: 10px 20px;
            background-color: #000000;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            transition: background-color 0.3s;
            border: 2px solid #339af0;
        }
        
        .btn-primary:hover {
            background-color: #339af0;
            color: white;
        }
        
        .btn-secondary {
            padding: 10px 20px;
            background-color: white;
            color: #000000;
            border: 2px solid #339af0;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s;
        }
        
        .btn-secondary:hover {
            background-color: #339af0;
            color: white;
            text-decoration: none;
        }
        
        .reports-table-container {
            background-color: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            margin-bottom: 30px;
            border: 2px solid #339af0;
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
            color: #666666;
            font-size: 0.95rem;
        }
        
        .reports-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .reports-table th {
            background-color: #339af0;
            color: white;
            font-weight: 600;
            text-align: left;
            padding: 15px;
            border-bottom: 2px solid #000000;
        }
        
        .reports-table td {
            padding: 15px;
            border-bottom: 1px solid #339af0;
            vertical-align: middle;
            color: #000000;
        }
        
        .reports-table tr:hover {
            background-color: #e7f5ff;
        }
        
        .report-id {
            font-weight: 600;
            color: #000000;
        }
        
        .disease-name {
            font-weight: 500;
            color: #000000;
        }
        
        .location {
            color: #666666;
            font-size: 0.9rem;
        }
        
        .actions-cell {
            text-align: right;
            white-space: nowrap;
        }
        
        .action-btn {
            padding: 6px 12px;
            background-color: #000000;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85rem;
            text-decoration: none;
            display: inline-block;
            margin-left: 5px;
            transition: background-color 0.3s;
            border: 2px solid #339af0;
        }
        
        .action-btn:hover {
            background-color: #339af0;
            color: white;
            text-decoration: none;
        }
        
        .action-btn.view {
            background-color: #000000;
        }
        
        .action-btn.analyze {
            background-color: #000000;
            border: 2px solid #51cf66;
        }
        
        .action-btn.analyze:hover {
            background-color: #51cf66;
        }
        
        .action-btn.delete {
            background-color: #000000;
            border: 2px solid #ff6b6b;
        }
        
        .action-btn.delete:hover {
            background-color: #ff6b6b;
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
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 30px;
        }
        
        .page-link {
            padding: 8px 15px;
            background-color: white;
            color: #339af0;
            border: 2px solid #339af0;
            border-radius: 4px;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .page-link:hover {
            background-color: #339af0;
            color: white;
            text-decoration: none;
        }
        
        .page-link.active {
            background-color: #000000;
            color: white;
            border-color: #000000;
        }
        
        .status-filter {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .status-btn {
            padding: 8px 20px;
            background-color: white;
            color: #000000;
            border: 2px solid #339af0;
            border-radius: 20px;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        
        .status-btn:hover {
            background-color: #339af0;
            color: white;
            text-decoration: none;
        }
        
        .status-btn.active {
            background-color: #000000;
            color: white;
            border-color: #000000;
        }
        
        @media (max-width: 768px) {
            .stats-overview {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .filter-form {
                grid-template-columns: 1fr;
            }
            
            .filter-actions {
                flex-direction: column;
            }
            
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
    </style>
</head>
<body>
    <div class="container">
        <div class="reports-container">
            <div class="page-header">
                <h2>My Disease Reports</h2>
                <p>View and manage all your submitted disease reports. Track their status and see health worker analyses.</p>
            </div>
            
            <div class="stats-overview">
                <div class="stat-item">
                    <div class="stat-number"><?php echo $total_reports; ?></div>
                    <div class="stat-label">Total Reports</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo $status_counts['pending'] ?? 0; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo $status_counts['analyzed'] ?? 0; ?></div>
                    <div class="stat-label">Being Analyzed</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo $status_counts['completed'] ?? 0; ?></div>
                    <div class="stat-label">Completed</div>
                </div>
            </div>
            
            <div class="status-filter">
                <a href="?status=all<?php echo !empty($disease_filter) ? '&disease=' . urlencode($disease_filter) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?>" 
                   class="status-btn <?php echo $status_filter === 'all' ? 'active' : ''; ?>">
                    All Reports (<?php echo $total_reports; ?>)
                </a>
                <a href="?status=pending<?php echo !empty($disease_filter) ? '&disease=' . urlencode($disease_filter) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?>" 
                   class="status-btn <?php echo $status_filter === 'pending' ? 'active' : ''; ?>">
                    Pending (<?php echo $status_counts['pending'] ?? 0; ?>)
                </a>
                <a href="?status=analyzed<?php echo !empty($disease_filter) ? '&disease=' . urlencode($disease_filter) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?>" 
                   class="status-btn <?php echo $status_filter === 'analyzed' ? 'active' : ''; ?>">
                    Being Analyzed (<?php echo $status_counts['analyzed'] ?? 0; ?>)
                </a>
                <a href="?status=completed<?php echo !empty($disease_filter) ? '&disease=' . urlencode($disease_filter) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?>" 
                   class="status-btn <?php echo $status_filter === 'completed' ? 'active' : ''; ?>">
                    Completed (<?php echo $status_counts['completed'] ?? 0; ?>)
                </a>
            </div>
            
            <div class="filter-card">
                <div class="filter-header">
                    <h3>Filter Reports</h3>
                </div>
                <form method="GET" action="" class="filter-form">
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
                    <h3>Reports List</h3>
                    <div class="reports-count">
                        Showing <?php echo count($reports); ?> report<?php echo count($reports) !== 1 ? 's' : ''; ?>
                    </div>
                </div>
                
                <?php if (!empty($reports)): ?>
                    <div class="table-responsive">
                        <table class="reports-table">
                            <thead>
                                <tr>
                                    <th>Report ID</th>
                                    <th>Disease</th>
                                    <th>Symptoms Preview</th>
                                    <th>Location</th>
                                    <th>Status</th>
                                    <th>Submitted</th>
                                    <th>Analyses</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reports as $report): ?>
                                    <tr>
                                        <td class="report-id">#<?php echo $report['id']; ?></td>
                                        <td>
                                            <div class="disease-name"><?php echo htmlspecialchars($report['disease_name']); ?></div>
                                        </td>
                                        <td>
                                            <div style="max-width: 200px;">
                                                <?php echo truncateText(htmlspecialchars($report['symptoms']), 50); ?>
                                            </div>
                                        </td>
                                        <td class="location"><?php echo htmlspecialchars($report['location']); ?></td>
                                        <td><?php echo getStatusBadge($report['status']); ?></td>
                                        <td><?php echo timeAgo($report['created_at']); ?></td>
                                        <td>
                                            <?php if ($report['analysis_count'] > 0): ?>
                                                <span class="badge badge-info" data-toggle="tooltip" title="<?php echo $report['analysis_count']; ?> analysis<?php echo $report['analysis_count'] > 1 ? 'es' : ''; ?>">
                                                    <?php echo $report['analysis_count']; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">None</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="actions-cell">
                                            <a href="view_report_details.php?id=<?php echo $report['id']; ?>" 
                                               class="action-btn view" 
                                               title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ($report['status'] === 'pending'): ?>
                                                <a href="edit_report.php?id=<?php echo $report['id']; ?>" 
                                                   class="action-btn analyze" 
                                                   title="Edit Report">
                                                    <i class="fas fa-edit"></i>
                                                </a>
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
                        <h4>No Reports Found</h4>
                        <p>
                            <?php if ($status_filter !== 'all' || !empty($disease_filter) || !empty($date_from) || !empty($date_to)): ?>
                                No reports match your current filters. Try adjusting your search criteria.
                            <?php else: ?>
                                You haven't submitted any disease reports yet.
                            <?php endif; ?>
                        </p>
                        <a href="create_report.php" class="btn-primary" style="margin-top: 15px; display: inline-block;">
                            <i class="fas fa-plus-circle"></i> Submit Your First Report
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="filter-card" style="background-color: #e7f5ff; border: 2px solid #339af0;">
                <h3 style="margin-top: 0; color: #000000; margin-bottom: 15px;">Report Status Guide</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
                    <div style="padding: 15px; background-color: white; border-radius: 8px; border: 2px solid #ff922b;">
                        <h4 style="margin: 0 0 10px 0; color: #000000; font-size: 1rem;">
                            <span class="badge badge-warning" style="margin-right: 8px; background-color: #ff922b; color: white;">Pending</span>
                        </h4>
                        <p style="margin: 0; color: #666666; font-size: 0.9rem;">Your report has been submitted and is waiting for health worker review.</p>
                    </div>
                    
                    <div style="padding: 15px; background-color: white; border-radius: 8px; border: 2px solid #339af0;">
                        <h4 style="margin: 0 0 10px 0; color: #000000; font-size: 1rem;">
                            <span class="badge badge-info" style="margin-right: 8px; background-color: #339af0; color: white;">Analyzed</span>
                        </h4>
                        <p style="margin: 0; color: #666666; font-size: 0.9rem;">Health workers are analyzing your report and will provide recommendations.</p>
                    </div>
                    
                    <div style="padding: 15px; background-color: white; border-radius: 8px; border: 2px solid #51cf66;">
                        <h4 style="margin: 0 0 10px 0; color: #000000; font-size: 1rem;">
                            <span class="badge badge-success" style="margin-right: 8px; background-color: #51cf66; color: white;">Completed</span>
                        </h4>
                        <p style="margin: 0; color: #666666; font-size: 0.9rem;">Analysis is complete. Check the recommendations for health advice.</p>
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
            
            const exportBtn = document.querySelector('.export-btn');
            if (exportBtn) {
                exportBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    const filters = {
                        status: '<?php echo $status_filter; ?>',
                        disease: '<?php echo $disease_filter; ?>',
                        date_from: '<?php echo $date_from; ?>',
                        date_to: '<?php echo $date_to; ?>'
                    };
                    
                    const exportType = confirm('Export as CSV? Click OK for CSV, Cancel for PDF.');
                    
                    if (exportType) {
                        window.location.href = 'export_reports.php?format=csv&' + new URLSearchParams(filters).toString();
                    } else {
                        window.location.href = 'export_reports.php?format=pdf&' + new URLSearchParams(filters).toString();
                    }
                });
            }
            
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
                    tooltip.style.border = '2px solid #339af0';
                    
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
            
            const diseaseSelect = document.getElementById('disease');
            if (diseaseSelect) {
                diseaseSelect.addEventListener('change', function() {
                    if (this.value) {
                        this.form.submit();
                    }
                });
            }
        });
    </script>
</body>
</html>