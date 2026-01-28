<?php
require_once '../includes/header.php';
require_once '../config/database.php';

$database = new Database();
$pdo = $database->getConnection();

if (!isLoggedIn() || getCurrentUserRole() !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$items_per_page = 10;
$offset = ($page - 1) * $items_per_page;

$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'date_desc';
$severity_filter = isset($_GET['severity']) ? $_GET['severity'] : '';

$where_clause = "WHERE analyses.sent_to_admin = 1";
if (!empty($severity_filter)) {
    $where_clause .= " AND analyses.severity_level = ?";
}

$sort_order = '';
switch($sort_by) {
    case 'severity_high':
        $sort_order = "CASE 
                        WHEN analyses.severity_level = 'critical' THEN 1
                        WHEN analyses.severity_level = 'high' THEN 2
                        WHEN analyses.severity_level = 'medium' THEN 3
                        WHEN analyses.severity_level = 'low' THEN 4
                    END ASC";
        break;
    case 'date_asc':
        $sort_order = "analyses.created_at ASC";
        break;
    case 'date_desc':
    default:
        $sort_order = "analyses.created_at DESC";
        break;
}

try {
    $count_query = "SELECT COUNT(*) as total FROM analyses " . $where_clause;
    $count_stmt = $pdo->prepare($count_query);
    
    if (!empty($severity_filter)) {
        $count_stmt->execute([$severity_filter]);
    } else {
        $count_stmt->execute();
    }
    
    $total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_records / $items_per_page);
    
    $query = "SELECT 
                analyses.id,
                analyses.report_id,
                analyses.analysis_details,
                analyses.severity_level,
                analyses.created_at,
                analyses.analyzed_at,
                reports.disease_name,
                reports.symptoms,
                reports.location,
                reports.created_at as report_date,
                users.username as health_worker_name,
                citizen.username as citizen_name
            FROM analyses
            JOIN reports ON analyses.report_id = reports.id
            JOIN users ON analyses.health_worker_id = users.id
            JOIN users as citizen ON reports.citizen_id = citizen.id
            " . $where_clause . "
            ORDER BY " . $sort_order . "
            LIMIT ? OFFSET ?";
    
    $stmt = $pdo->prepare($query);
    
    $param_index = 1;
    if (!empty($severity_filter)) {
        $stmt->bindValue($param_index++, $severity_filter, PDO::PARAM_STR);
    }
    $stmt->bindValue($param_index++, $items_per_page, PDO::PARAM_INT);
    $stmt->bindValue($param_index++, $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $analyses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $error_message = "Error fetching analyses: " . $e->getMessage();
    $analyses = [];
}

function getSeverityClass($severity) {
    switch($severity) {
        case 'critical':
            return 'severity-critical';
        case 'high':
            return 'severity-high';
        case 'medium':
            return 'severity-medium';
        case 'low':
            return 'severity-low';
        default:
            return 'severity-unknown';
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Analyses </title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .page-header {
            background: linear-gradient(135deg, #87CEEB 0%, #B0E0E6 100%);
            color: black;
            padding: 30px 0;
            margin-bottom: 30px;
            border-radius: 0;
        }

        .page-header h1 {
            margin: 0;
            font-size: 2.5rem;
            font-weight: 700;
        }

        .page-header p {
            margin: 10px 0 0 0;
            font-size: 1.1rem;
            opacity: 0.8;
        }

        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
            border: 1px solid #e0e0e0;
        }

        .filter-section label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            color: #333;
        }

        .filter-section select,
        .filter-section a {
            padding: 8px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 0.95rem;
            background: white;
            color: #333;
            cursor: pointer;
            transition: all 0.3s;
        }

        .filter-section select:hover {
            border-color: #87CEEB;
            box-shadow: 0 2px 5px rgba(135, 206, 235, 0.1);
        }

        .filter-section a {
            background: #87CEEB;
            color: black;
            border-color: #87CEEB;
            text-decoration: none;
            display: inline-block;
        }

        .filter-section a:hover {
            background: #76B9D6;
        }

        .analyses-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border: 1px solid #e0e0e0;
        }

        .analyses-table thead {
            background: linear-gradient(135deg, #87CEEB 0%, #B0E0E6 100%);
            color: black;
        }

        .analyses-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            border: none;
        }

        .analyses-table tbody tr {
            border-bottom: 1px solid #eee;
            transition: background-color 0.3s;
        }

        .analyses-table tbody tr:hover {
            background-color: #f8f8f8;
        }

        .analyses-table td {
            padding: 15px;
            color: #333;
        }

        .severity-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .severity-critical {
            background-color: #000000;
            color: white;
        }

        .severity-high {
            background-color: #333333;
            color: white;
        }

        .severity-medium {
            background-color: #666666;
            color: white;
        }

        .severity-low {
            background-color: #999999;
            color: white;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-view {
            background-color: #87CEEB;
            color: black;
            padding: 8px 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.85rem;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-view:hover {
            background-color: #76B9D6;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(135, 206, 235, 0.3);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border: 1px solid #e0e0e0;
        }

        .empty-state i {
            font-size: 4rem;
            color: #ccc;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            color: #666;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #999;
        }

        .pagination-container {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .pagination-container a,
        .pagination-container span {
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            text-decoration: none;
            color: #87CEEB;
            font-weight: 600;
            transition: all 0.3s;
        }

        .pagination-container a:hover {
            background-color: #87CEEB;
            color: black;
        }

        .pagination-container .active {
            background-color: #87CEEB;
            color: black;
            border-color: #87CEEB;
        }

        .pagination-container .disabled {
            color: #ccc;
            cursor: not-allowed;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            animation: fadeIn 0.3s;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 8px;
            width: 90%;
            max-width: 700px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            animation: slideIn 0.3s;
        }

        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .modal-header {
            background: linear-gradient(135deg, #87CEEB 0%, #B0E0E6 100%);
            color: black;
            padding: 20px;
            border-radius: 8px 8px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            margin: 0;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: black;
            cursor: pointer;
        }

        .close-btn:hover {
            opacity: 0.8;
        }

        .modal-body {
            padding: 25px;
        }

        .detail-row {
            margin-bottom: 20px;
        }

        .detail-row label {
            font-weight: 600;
            color: #87CEEB;
            display: block;
            margin-bottom: 5px;
        }

        .detail-row p {
            margin: 0;
            color: #333;
            line-height: 1.6;
            padding: 10px;
            background: #f5f5f5;
            border-radius: 5px;
            border: 1px solid #e0e0e0;
        }

        @media (max-width: 768px) {
            .page-header h1 {
                font-size: 1.8rem;
            }

            .filter-section {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-section select,
            .filter-section a {
                width: 100%;
            }

            .analyses-table {
                font-size: 0.9rem;
            }

            .analyses-table th,
            .analyses-table td {
                padding: 10px;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn-view {
                width: 100%;
                justify-content: center;
            }

            .modal-content {
                width: 95%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <div class="container">
                <h1><i class="fas fa-chart-bar"></i> View Analyses</h1>
                <p>Review all health worker analyses sent to you</p>
            </div>
        </div>

        <?php if (isset($error_message)): ?>
            <div style="background-color: #fee; border: 1px solid #fcc; color: #a33; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <div class="filter-section">
            <label for="severity-filter"><i class="fas fa-filter"></i> Filter by Severity:</label>
            <select id="severity-filter" onchange="applyFilter()">
                <option value="">All Severities</option>
                <option value="critical" <?php echo ($severity_filter === 'critical') ? 'selected' : ''; ?>>Critical</option>
                <option value="high" <?php echo ($severity_filter === 'high') ? 'selected' : ''; ?>>High</option>
                <option value="medium" <?php echo ($severity_filter === 'medium') ? 'selected' : ''; ?>>Medium</option>
                <option value="low" <?php echo ($severity_filter === 'low') ? 'selected' : ''; ?>>Low</option>
            </select>

            <label for="sort-filter"><i class="fas fa-sort"></i> Sort by:</label>
            <select id="sort-filter" onchange="applyFilter()">
                <option value="date_desc" <?php echo ($sort_by === 'date_desc') ? 'selected' : ''; ?>>Latest First</option>
                <option value="date_asc" <?php echo ($sort_by === 'date_asc') ? 'selected' : ''; ?>>Oldest First</option>
                <option value="severity_high" <?php echo ($sort_by === 'severity_high') ? 'selected' : ''; ?>>Severity (High to Low)</option>
            </select>

            <a href="view_analyses.php" style="margin-left: auto;"><i class="fas fa-redo"></i> Reset</a>
        </div>

        <?php if (!empty($analyses)): ?>
            <table class="analyses-table">
                <thead>
                    <tr>
                        <th><i class="fas fa-virus"></i> Disease</th>
                        <th><i class="fas fa-map-marker-alt"></i> Location</th>
                        <th><i class="fas fa-exclamation-triangle"></i> Severity</th>
                        <th><i class="fas fa-user-nurse"></i> Health Worker</th>
                        <th><i class="fas fa-calendar"></i> Date</th>
                        <th><i class="fas fa-actions"></i> Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($analyses as $analysis): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($analysis['disease_name']); ?></strong>
                                <br>
                                <small style="color: #999;">Report ID: #<?php echo htmlspecialchars($analysis['report_id']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($analysis['location']); ?></td>
                            <td>
                                <span class="severity-badge <?php echo getSeverityClass($analysis['severity_level']); ?>">
                                    <?php echo ucfirst(htmlspecialchars($analysis['severity_level'])); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($analysis['health_worker_name']); ?></td>
                            <td><?php echo formatDate($analysis['created_at']); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn-view" onclick="viewAnalysis(<?php echo htmlspecialchars(json_encode($analysis)); ?>)">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1): ?>
                <div class="pagination-container">
                    <?php
                    if ($page > 1): ?>
                        <a href="?page=1&sort=<?php echo urlencode($sort_by); ?>&severity=<?php echo urlencode($severity_filter); ?>">
                            <i class="fas fa-chevron-left"></i> First
                        </a>
                        <a href="?page=<?php echo $page - 1; ?>&sort=<?php echo urlencode($sort_by); ?>&severity=<?php echo urlencode($severity_filter); ?>">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php else: ?>
                        <span class="disabled"><i class="fas fa-chevron-left"></i> First</span>
                        <span class="disabled"><i class="fas fa-chevron-left"></i> Previous</span>
                    <?php endif; ?>

                    <?php
                    $start = max(1, $page - 2);
                    $end = min($total_pages, $page + 2);
                    
                    if ($start > 1): ?>
                        <span>...</span>
                    <?php endif;
                    
                    for ($i = $start; $i <= $end; $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?>&sort=<?php echo urlencode($sort_by); ?>&severity=<?php echo urlencode($severity_filter); ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endif; ?>
                    <?php endfor;
                    
                    if ($end < $total_pages): ?>
                        <span>...</span>
                    <?php endif; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&sort=<?php echo urlencode($sort_by); ?>&severity=<?php echo urlencode($severity_filter); ?>">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                        <a href="?page=<?php echo $total_pages; ?>&sort=<?php echo urlencode($sort_by); ?>&severity=<?php echo urlencode($severity_filter); ?>">
                            Last <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="disabled">Next <i class="fas fa-chevron-right"></i></span>
                        <span class="disabled">Last <i class="fas fa-chevron-right"></i></span>
                    <?php endif; ?>
                </div>
                <p style="text-align: center; color: #999; margin-top: 15px;">
                    Showing <?php echo count($analyses); ?> of <?php echo $total_records; ?> analyses | Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                </p>
            <?php endif; ?>

        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h3>No Analyses Found</h3>
                <p>There are no analyses sent to you yet. Check back later!</p>
            </div>
        <?php endif; ?>
    </div>

    <div id="analysisModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Analysis Details</h2>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body" id="modalBody">
            </div>
        </div>
    </div>

    <script>
        function applyFilter() {
            const severity = document.getElementById('severity-filter').value;
            const sort = document.getElementById('sort-filter').value;
            
            let url = 'view_analyses.php?page=1';
            if (sort) url += '&sort=' + encodeURIComponent(sort);
            if (severity) url += '&severity=' + encodeURIComponent(severity);
            
            window.location.href = url;
        }

        function viewAnalysis(analysis) {
            const modal = document.getElementById('analysisModal');
            const modalBody = document.getElementById('modalBody');
            
            const html = `
                <div class="detail-row">
                    <label><i class="fas fa-virus"></i> Disease Name</label>
                    <p>${escapeHtml(analysis.disease_name)}</p>
                </div>
                <div class="detail-row">
                    <label><i class="fas fa-map-marker-alt"></i> Location</label>
                    <p>${escapeHtml(analysis.location)}</p>
                </div>
                <div class="detail-row">
                    <label><i class="fas fa-user"></i> Citizen</label>
                    <p>${escapeHtml(analysis.citizen_name)}</p>
                </div>
                <div class="detail-row">
                    <label><i class="fas fa-symptoms"></i> Symptoms</label>
                    <p>${escapeHtml(analysis.symptoms)}</p>
                </div>
                <div class="detail-row">
                    <label><i class="fas fa-stethoscope"></i> Analysis Details</label>
                    <p>${escapeHtml(analysis.analysis_details)}</p>
                </div>
                <div class="detail-row">
                    <label><i class="fas fa-exclamation-triangle"></i> Severity Level</label>
                    <p><span class="severity-badge severity-${analysis.severity_level}">${escapeHtml(analysis.severity_level.toUpperCase())}</span></p>
                </div>
                <div class="detail-row">
                    <label><i class="fas fa-user-nurse"></i> Health Worker</label>
                    <p>${escapeHtml(analysis.health_worker_name)}</p>
                </div>
                <div class="detail-row">
                    <label><i class="fas fa-calendar"></i> Report Date</label>
                    <p>${escapeHtml(new Date(analysis.report_date).toLocaleString())}</p>
                </div>
                <div class="detail-row">
                    <label><i class="fas fa-check"></i> Analysis Date</label>
                    <p>${escapeHtml(new Date(analysis.created_at).toLocaleString())}</p>
                </div>
            `;
            
            modalBody.innerHTML = html;
            modal.style.display = 'block';
        }

        function closeModal() {
            const modal = document.getElementById('analysisModal');
            modal.style.display = 'none';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('analysisModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        }

        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }
    </script>
</body>
</html>