<?php
require_once '../includes/header.php';

// Handle language switch for public dashboard
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'sw'])) {
    $_SESSION['user_lang'] = $_GET['lang'];
    setcookie('user_lang', $_GET['lang'], time() + (86400 * 30), '/');
    $redirectUrl = str_replace(['?lang=en', '?lang=sw', '&lang=en', '&lang=sw'], '', $_SERVER['REQUEST_URI']);
    header('Location: ' . ($redirectUrl ?: 'public_dashboard.php'));
    exit;
}

$db_config = [
    'host' => '127.0.0.1',
    'port' => '3306',
    'db' => 'chmwes_db',
    'user' => 'root',
    'pass' => '',
    'charset' => 'utf8mb4'
];

try {
    $dsn = "mysql:host={$db_config['host']};port={$db_config['port']};dbname={$db_config['db']};charset={$db_config['charset']}";
    $pdo = new PDO($dsn, $db_config['user'], $db_config['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db_connected = true;
} catch (PDOException $e) {
    $db_connected = false;
    $error_message = "Database connection failed. Please try again later.";
}

$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 20;
$offset = ($current_page - 1) * $records_per_page;

$start_date = isset($_GET['start_date']) ? date('Y-m-d', strtotime($_GET['start_date'])) : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? date('Y-m-d', strtotime($_GET['end_date'])) : date('Y-m-d');

if (strtotime($start_date) > strtotime($end_date)) {
    $start_date = date('Y-m-d', strtotime('-30 days'));
    $end_date = date('Y-m-d');
}

$severity_filter = isset($_GET['severity']) ? $_GET['severity'] : '';
$disease_filter = isset($_GET['disease']) ? $_GET['disease'] : '';

$top_diseases = [];
$severity_distribution = [];
$daily_reports = [];
$total_reports = 0;
$total_confirmed = 0;
$total_pending = 0;
$total_resolved = 0;
$most_common_disease = 'N/A';
$critical_count = 0;
$high_count = 0;
$medium_count = 0;
$low_count = 0;

if ($db_connected) {
    try {
        $stats_query = "SELECT 
                        COUNT(*) as total_reports,
                        SUM(CASE WHEN r.status = 'analyzed' THEN 1 ELSE 0 END) as confirmed_count,
                        SUM(CASE WHEN r.status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                        SUM(CASE WHEN r.status = 'completed' THEN 1 ELSE 0 END) as resolved_count
                    FROM reports r
                    WHERE r.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)";
        
        $stats_stmt = $pdo->prepare($stats_query);
        $stats_stmt->execute([$start_date, $end_date]);
        $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
        
        $total_reports = (int)($stats['total_reports'] ?? 0);
        $total_confirmed = (int)($stats['confirmed_count'] ?? 0);
        $total_pending = (int)($stats['pending_count'] ?? 0);
        $total_resolved = (int)($stats['resolved_count'] ?? 0);

        $severity_query = "SELECT 
                            a.severity_level as severity,
                            COUNT(*) as count
                        FROM analyses a
                        JOIN reports r ON a.report_id = r.id
                        WHERE r.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
                        GROUP BY a.severity_level
                        ORDER BY count DESC";
        
        $severity_stmt = $pdo->prepare($severity_query);
        $severity_stmt->execute([$start_date, $end_date]);
        $severity_distribution = $severity_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($severity_distribution as $sev) {
            switch (strtolower($sev['severity'])) {
                case 'critical':
                    $critical_count = (int)$sev['count'];
                    break;
                case 'high':
                    $high_count = (int)$sev['count'];
                    break;
                case 'medium':
                    $medium_count = (int)$sev['count'];
                    break;
                case 'low':
                    $low_count = (int)$sev['count'];
                    break;
            }
        }

        $diseases_query = "SELECT 
                            disease_name,
                            COUNT(*) as count,
                            SUM(CASE WHEN status = 'analyzed' THEN 1 ELSE 0 END) as confirmed
                        FROM reports 
                        WHERE created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)";
        
        if ($disease_filter) {
            $diseases_query .= " AND disease_name = ?";
        }
        
        $diseases_query .= " GROUP BY disease_name 
                           ORDER BY count DESC 
                           LIMIT 10";
        
        $diseases_stmt = $pdo->prepare($diseases_query);
        if ($disease_filter) {
            $diseases_stmt->execute([$start_date, $end_date, $disease_filter]);
        } else {
            $diseases_stmt->execute([$start_date, $end_date]);
        }
        $top_diseases = $diseases_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($top_diseases)) {
            $most_common_disease = htmlspecialchars($top_diseases[0]['disease_name']);
        }

        $daily_query = "SELECT 
                        DATE(created_at) as report_date,
                        COUNT(*) as count,
                        SUM(CASE WHEN status = 'analyzed' THEN 1 ELSE 0 END) as confirmed_count
                    FROM reports 
                    WHERE created_at BETWEEN DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND DATE_ADD(CURDATE(), INTERVAL 1 DAY)
                    GROUP BY DATE(created_at)
                    ORDER BY report_date ASC";
        
        $daily_stmt = $pdo->prepare($daily_query);
        $daily_stmt->execute();
        $daily_reports = $daily_stmt->fetchAll(PDO::FETCH_ASSOC);

        $disease_list_query = "SELECT DISTINCT disease_name 
                              FROM reports 
                              ORDER BY disease_name ASC";
        $disease_list_stmt = $pdo->prepare($disease_list_query);
        $disease_list_stmt->execute();
        $disease_list = $disease_list_stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $db_connected = false;
        $error_message = "Error fetching statistics: " . $e->getMessage();
    }
}

$disease_labels = [];
$disease_data = [];
$disease_confirmed = [];

foreach ($top_diseases as $disease) {
    $disease_labels[] = htmlspecialchars($disease['disease_name']);
    $disease_data[] = (int)$disease['count'];
    $disease_confirmed[] = (int)$disease['confirmed'];
}

$severity_labels = [];
$severity_data = [];

foreach ($severity_distribution as $sev) {
    $severity_labels[] = ucfirst(htmlspecialchars($sev['severity']));
    $severity_data[] = (int)$sev['count'];
}

$daily_dates = [];
$daily_counts = [];
$daily_confirmed_counts = [];

foreach ($daily_reports as $daily) {
    $daily_dates[] = date('M d', strtotime($daily['report_date']));
    $daily_counts[] = (int)$daily['count'];
    $daily_confirmed_counts[] = (int)$daily['confirmed_count'];
}

$confirmed_percentage = $total_reports > 0 ? round(($total_confirmed / $total_reports) * 100, 1) : 0;
$pending_percentage = $total_reports > 0 ? round(($total_pending / $total_reports) * 100, 1) : 0;
$resolved_percentage = $total_reports > 0 ? round(($total_resolved / $total_reports) * 100, 1) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Public Community Health System Dashboard - View disease trends and statistics">
    <title><?php echo t('public_dashboard'); ?></title>
    
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/charts.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    
    <style>
        body {
            background: #f8fafc;
            color: #1e293b;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .public-header {
            background: linear-gradient(135deg, #0ea5e9 0%, #1e293b 100%);
            color: white;
            padding: 40px 20px;
            text-align: center;
            margin-bottom: 30px;
            position: relative;
        }
        
        .public-header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            font-weight: 700;
            color: white;
        }
        
        .public-header p {
            font-size: 1.1rem;
            opacity: 0.9;
            margin: 0;
            color: #e2e8f0;
        }
        
        .stats-grid-public {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .stat-card-public {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border-top: 4px solid #0ea5e9;
            transition: all 0.3s ease;
        }
        
        .stat-card-public:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        .stat-card-public.confirmed {
            border-top-color: #0ea5e9;
        }
        
        .stat-card-public.pending {
            border-top-color: #3b82f6;
        }
        
        .stat-card-public.resolved {
            border-top-color: #1e293b;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 10px;
        }
        
        .stat-label {
            color: #64748b;
            font-size: 1rem;
            margin-bottom: 8px;
        }
        
        .stat-percentage {
            font-size: 0.9rem;
            color: #0ea5e9;
            font-weight: 600;
        }
        
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
        }
        
        .filter-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .form-control {
            padding: 10px 15px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 1rem;
            font-family: inherit;
            transition: border-color 0.3s ease;
            background: white;
            color: #1e293b;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #0ea5e9;
            box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
        }
        
        .btn-filter {
            padding: 10px 25px;
            background: #0ea5e9;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s ease;
        }
        
        .btn-filter:hover {
            background: #0284c7;
        }
        
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .chart-container {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
        }
        
        .chart-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f1f5f9;
        }
        
        .chart-wrapper {
            position: relative;
            height: 300px;
            margin-bottom: 10px;
        }
        
        .disease-table-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }
        
        .disease-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .disease-table thead {
            background: #f1f5f9;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .disease-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #0f172a;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .disease-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e2e8f0;
            color: #475569;
        }
        
        .disease-table tbody tr:hover {
            background-color: #f8fafc;
        }
        
        .disease-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .badge-primary {
            background: rgba(14, 165, 233, 0.1);
            color: #0ea5e9;
            border: 1px solid rgba(14, 165, 233, 0.2);
        }
        
        .badge-success {
            background: rgba(14, 165, 233, 0.1);
            color: #0ea5e9;
            border: 1px solid rgba(14, 165, 233, 0.2);
        }
        
        .severity-critical {
            background: rgba(14, 165, 233, 0.1);
            color: #0ea5e9;
            border: 1px solid rgba(14, 165, 233, 0.2);
        }
        
        .severity-high {
            background: rgba(14, 165, 233, 0.1);
            color: #3b82f6;
            border: 1px solid rgba(14, 165, 233, 0.2);
        }
        
        .severity-medium {
            background: rgba(14, 165, 233, 0.1);
            color: #0ea5e9;
            border: 1px solid rgba(14, 165, 233, 0.2);
        }
        
        .severity-low {
            background: rgba(14, 165, 233, 0.1);
            color: #10b981;
            border: 1px solid rgba(14, 165, 233, 0.2);
        }
        
        .data-summary {
            background: #f0f9ff;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 1px solid #bae6fd;
        }
        
        .data-summary p {
            margin: 5px 0;
            color: #0f172a;
        }
        
        .summary-highlight {
            font-weight: 600;
            color: #0ea5e9;
        }
        
        .no-data-message {
            background: white;
            padding: 40px;
            text-align: center;
            border-radius: 10px;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }
        
        .error-alert {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            padding: 15px 20px;
            border-radius: 6px;
            border-left: 4px solid #ef4444;
            margin-bottom: 20px;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        
        .info-alert {
            background: rgba(14, 165, 233, 0.1);
            color: #0ea5e9;
            padding: 15px 20px;
            border-radius: 6px;
            border-left: 4px solid #0ea5e9;
            margin-bottom: 20px;
            border: 1px solid rgba(14, 165, 233, 0.2);
        }
        
        label {
            color: #0f172a;
            font-weight: 600;
        }
        
        .container {
            background: transparent;
            padding: 0 20px;
        }
        
        @media (max-width: 768px) {
            .public-header h1 {
                font-size: 1.8rem;
            }
            
            .charts-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .filter-group {
                grid-template-columns: 1fr;
            }
            
            .disease-table {
                font-size: 0.9rem;
            }
            
            .disease-table th,
            .disease-table td {
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="public-header">
        <!-- Language Switcher -->
        <div style="position: absolute; top: 20px; right: 20px; display: flex; gap: 10px;">
            <?php $currentLang = $_SESSION['user_lang'] ?? $_COOKIE['user_lang'] ?? 'en'; ?>
            <a href="?lang=en" style="padding: 6px 12px; border-radius: 4px; text-decoration: none; color: white; background: <?php echo $currentLang === 'en' ? 'rgba(255,255,255,0.3)' : 'transparent'; ?>; border: 1px solid rgba(255,255,255,0.5);">EN</a>
            <a href="?lang=sw" style="padding: 6px 12px; border-radius: 4px; text-decoration: none; color: white; background: <?php echo $currentLang === 'sw' ? 'rgba(255,255,255,0.3)' : 'transparent'; ?>; border: 1px solid rgba(255,255,255,0.5);">SW</a>
        </div>
        <h1>🏥 <?php echo t('public_dashboard'); ?></h1>
        <p>Community Surveillance and Trend Analysis</p>
    </div>
    
    <div class="container">
        <?php if (!$db_connected): ?>
            <div class="error-alert">
                <strong>⚠️ Connection Error:</strong> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <div class="info-alert">
            <strong>ℹ️ Public Data:</strong> This dashboard displays aggregated community health data for public health awareness. Data shown is from the last 30 days.
        </div>
        
        <div class="filter-section">
            <form method="GET" action="">
                <div class="filter-group">
                    <div>
                        <label for="start_date">Start Date:</label>
                        <input type="date" id="start_date" name="start_date" class="form-control" 
                               value="<?php echo htmlspecialchars($start_date); ?>" 
                               min="<?php echo date('Y-m-d', strtotime('-1 year')); ?>" 
                               max="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div>
                        <label for="end_date">End Date:</label>
                        <input type="date" id="end_date" name="end_date" class="form-control" 
                               value="<?php echo htmlspecialchars($end_date); ?>" 
                               min="<?php echo date('Y-m-d', strtotime('-1 year')); ?>" 
                               max="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div>
                        <label for="disease">Disease Type:</label>
                        <select id="disease" name="disease" class="form-control">
                            <option value=""><?php echo t('all_diseases'); ?></option>
                            <?php if (isset($disease_list)): ?>
                                <?php foreach ($disease_list as $disease): ?>
                                    <option value="<?php echo htmlspecialchars($disease['disease_name']); ?>" 
                                            <?php echo ($disease_filter === $disease['disease_name']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($disease['disease_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div style="display: flex; align-items: flex-end;">
                        <button type="submit" class="btn-filter" style="width: 100%;">🔍 Apply Filters</button>
                    </div>
                </div>
            </form>
        </div>
        
        <div class="data-summary">
            <p><strong>Date Range:</strong> <?php echo date('M d, Y', strtotime($start_date)); ?> to <?php echo date('M d, Y', strtotime($end_date)); ?></p>
            <p><strong>Most Common Disease:</strong> <span class="summary-highlight"><?php echo $most_common_disease; ?></span></p>
            <p><strong>Data Status:</strong> Last updated <?php echo date('M d, Y \a\t H:i', time()); ?> (Server Time)</p>
        </div>
        
        <div class="stats-grid-public">
            <div class="stat-card-public">
                <div class="stat-number"><?php echo number_format($total_reports); ?></div>
                <div class="stat-label"><?php echo t('total_reports'); ?></div>
                <div class="stat-percentage">Period Coverage</div>
            </div>
            
            <div class="stat-card-public confirmed">
                <div class="stat-number"><?php echo number_format($total_confirmed); ?></div>
                <div class="stat-label">Confirmed Cases</div>
                <div class="stat-percentage"><?php echo $confirmed_percentage; ?>% of total</div>
            </div>
            
            <div class="stat-card-public pending">
                <div class="stat-number"><?php echo number_format($total_pending); ?></div>
                <div class="stat-label">Pending Review</div>
                <div class="stat-percentage"><?php echo $pending_percentage; ?>% of total</div>
            </div>
            
            <div class="stat-card-public resolved">
                <div class="stat-number"><?php echo number_format($total_resolved); ?></div>
                <div class="stat-label">Resolved Cases</div>
                <div class="stat-percentage"><?php echo $resolved_percentage; ?>% of total</div>
            </div>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 40px;">
            <div class="stat-card-public" style="border-top-color: #0ea5e9;">
                <div class="stat-number" style="color: #0ea5e9;"><?php echo $critical_count; ?></div>
                <div class="stat-label">🚨 Critical Cases</div>
            </div>
            
            <div class="stat-card-public" style="border-top-color: #3b82f6;">
                <div class="stat-number" style="color: #3b82f6;"><?php echo $high_count; ?></div>
                <div class="stat-label">⚠️ High Severity</div>
            </div>
            
            <div class="stat-card-public" style="border-top-color: #0ea5e9;">
                <div class="stat-number" style="color: #0ea5e9;"><?php echo $medium_count; ?></div>
                <div class="stat-label">⚡ Medium Severity</div>
            </div>
            
            <div class="stat-card-public" style="border-top-color: #10b981;">
                <div class="stat-number" style="color: #10b981;"><?php echo $low_count; ?></div>
                <div class="stat-label">✓ Low Severity</div>
            </div>
        </div>
        
        <!-- Disease Outbreak Map Section -->
        <div class="chart-container" style="margin-bottom: 40px;">
            <h3 class="chart-title"><i class="fas fa-map-marked-alt" style="color: #0ea5e9;"></i> Disease & Outbreak Map</h3>
            <p style="color: #64748b; margin-bottom: 15px;">Geographic distribution of disease reports and active outbreaks across Kenya</p>
            
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
                <button onclick="updateDashboardMap()" style="padding: 8px 15px; background: #0ea5e9; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    <i class="fas fa-sync-alt"></i> Update Map
                </button>
            </div>
            
            <div id="dashboardMap" style="height: 400px; width: 100%; border-radius: 8px; border: 2px solid #0ea5e9;">
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
                    <span style="color: #64748b;">Low Activity</span>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 20px; height: 20px; background: rgba(14, 165, 233, 0.6); border-radius: 50%;"></div>
                    <span style="color: #64748b;">Medium Activity</span>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 20px; height: 20px; background: rgba(14, 165, 233, 0.9); border-radius: 50%;"></div>
                    <span style="color: #64748b;">High Activity</span>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 20px; height: 20px; background: #ef4444; border-radius: 50%;"></div>
                    <span style="color: #64748b;">Outbreak Alert</span>
                </div>
            </div>
        </div>
        
        <?php if ($db_connected && $total_reports > 0): ?>
            <div class="charts-grid">
                <?php if (!empty($disease_labels)): ?>
                    <div class="chart-container">
                        <h3 class="chart-title">📊 Top 10 Diseases</h3>
                        <div class="chart-wrapper">
                            <canvas id="diseaseChart"></canvas>
                        </div>
                        <p style="color: #64748b; font-size: 0.9rem; margin: 10px 0 0;">
                            Total: <?php echo count($disease_labels); ?> disease types reported
                        </p>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($severity_labels)): ?>
                    <div class="chart-container">
                        <h3 class="chart-title">🎯 Severity Distribution</h3>
                        <div class="chart-wrapper">
                            <canvas id="severityChart"></canvas>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="charts-grid" style="grid-template-columns: 1fr;">
                <?php if (!empty($daily_dates)): ?>
                    <div class="chart-container">
                        <h3 class="chart-title">📈 30-Day Reporting Trend</h3>
                        <div class="chart-wrapper" style="height: 350px;">
                            <canvas id="trendChart"></canvas>
                        </div>
                        <p style="color: #64748b; font-size: 0.9rem; margin: 10px 0 0;">
                            Blue line: Total reports | Green line: Confirmed cases
                        </p>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($top_diseases)): ?>
                <div class="disease-table-container" style="margin-bottom: 40px;">
                    <h3 style="padding: 25px 25px 0; font-size: 1.3rem; font-weight: 600; color: #0f172a;">
                        🦠 Top Diseases Breakdown
                    </h3>
                    <table class="disease-table">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Disease Type</th>
                                <th>Total Reports</th>
                                <th>Confirmed Cases</th>
                                <th>Confirmation Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_diseases as $index => $disease): ?>
                                <?php $rate = $disease['count'] > 0 ? round(($disease['confirmed'] / $disease['count']) * 100, 1) : 0; ?>
                                <tr>
                                    <td style="font-weight: 600; color: #0ea5e9;">#<?php echo $index + 1; ?></td>
                                    <td>
                                        <span class="disease-badge badge-primary">
                                            <?php echo htmlspecialchars($disease['disease_name']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo number_format($disease['count']); ?></td>
                                    <td>
                                        <span class="disease-badge badge-success">
                                            <?php echo number_format($disease['confirmed']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $rate; ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php elseif ($db_connected && $total_reports == 0): ?>
            <div class="no-data-message">
                <h3>📭 No Data Available</h3>
                <p>There are no reports for the selected date range and filters.</p>
                <p style="margin-top: 15px; font-size: 0.9rem;">Try adjusting your filters or selecting a different date range.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <footer style="margin-top: 60px; background: #0f172a; color: white; padding: 40px 20px;">
        <div style="max-width: 1400px; margin: 0 auto;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 30px; margin-bottom: 30px;">
                <div>
                    <h4 style="color: #0ea5e9; margin-bottom: 15px;">About This Dashboard</h4>
                    <p style="color: #cbd5e1;">The Public Health Dashboard provides real-time community health system data to support public health awareness and decision-making.</p>
                </div>
                <div>
                    <h4 style="color: #0ea5e9; margin-bottom: 15px;">Data Information</h4>
                    <ul style="list-style: none; padding: 0; color: #cbd5e1;">
                        <li style="margin-bottom: 8px;">📊 Data is aggregated and anonymized</li>
                        <li style="margin-bottom: 8px;">🔄 Updates continuously as reports are processed</li>
                        <li style="margin-bottom: 8px;">🏥 Verified by health professionals</li>
                        <li style="margin-bottom: 8px;">📈 Historical data available for 1 year</li>
                    </ul>
                </div>
                <div>
                    <h4 style="color: #0ea5e9; margin-bottom: 15px;">Resources</h4>
                    <p style="color: #cbd5e1;">Access our public health resources and documentation for community health monitoring.</p>
                </div>
                <div>
                    <h4 style="color: #0ea5e9; margin-bottom: 15px;">Contact & Support</h4>
                    <p style="color: #cbd5e1;">For questions about this community health system, please contact your local health authority.</p>
                    <p style="margin-top: 15px; font-size: 0.85rem; color: #94a3b8;">
                        <strong>Confidentiality Notice:</strong> This system is for authorized public health use only. All data is protected and used solely for community health system.
                    </p>
                </div>
            </div>
            <div style="text-align: center; padding-top: 20px; border-top: 1px solid #334155;">
                <p style="color: #cbd5e1;">&copy; 2026 Community Health Monitoring System. All rights reserved.</p>
                <p style="color: #94a3b8; font-size: 0.9rem;">Last Updated: <?php echo date('F d, Y \a\t H:i A'); ?> (Server Time)</p>
            </div>
        </div>
    </footer>
    
    <script>
        const primaryColor = '#0ea5e9';
        const successColor = '#10b981';
        const warningColor = '#f97316';
        const dangerColor = '#ef4444';

        Chart.defaults.font.family = "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif";
        Chart.defaults.color = '#64748b';

        <?php if (!empty($disease_labels)): ?>
        const diseaseCtx = document.getElementById('diseaseChart');
        if (diseaseCtx) {
            new Chart(diseaseCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($disease_labels); ?>,
                    datasets: [
                        {
                            label: 'Total Reports',
                            data: <?php echo json_encode($disease_data); ?>,
                            backgroundColor: primaryColor,
                            borderColor: '#0284c7',
                            borderWidth: 2,
                            borderRadius: 6,
                            tension: 0.4
                        },
                        {
                            label: 'Confirmed Cases',
                            data: <?php echo json_encode($disease_confirmed); ?>,
                            backgroundColor: successColor,
                            borderColor: '#059669',
                            borderWidth: 2,
                            borderRadius: 6,
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 15,
                                padding: 15,
                                font: { size: 12, weight: '600' }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { font: { size: 11 } },
                            grid: { color: 'rgba(0, 0, 0, 0.05)' }
                        },
                        x: {
                            ticks: { font: { size: 11 } },
                            grid: { display: false }
                        }
                    }
                }
            });
        }
        <?php endif; ?>

        <?php if (!empty($severity_labels)): ?>
        const severityCtx = document.getElementById('severityChart');
        if (severityCtx) {
            new Chart(severityCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode($severity_labels); ?>,
                    datasets: [{
                        data: <?php echo json_encode($severity_data); ?>,
                        backgroundColor: [
                            primaryColor,
                            '#3b82f6',
                            '#0ea5e9',
                            '#10b981'
                        ],
                        borderColor: 'white',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 15,
                                padding: 15,
                                font: { size: 12, weight: '600' }
                            }
                        }
                    }
                }
            });
        }
        <?php endif; ?>

        <?php if (!empty($daily_dates)): ?>
        const trendCtx = document.getElementById('trendChart');
        if (trendCtx) {
            new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($daily_dates); ?>,
                    datasets: [
                        {
                            label: 'Total Reports',
                            data: <?php echo json_encode($daily_counts); ?>,
                            borderColor: primaryColor,
                            backgroundColor: 'rgba(14, 165, 233, 0.1)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4,
                            pointRadius: 4,
                            pointBackgroundColor: primaryColor,
                            pointBorderColor: 'white',
                            pointBorderWidth: 2
                        },
                        {
                            label: 'Confirmed Cases',
                            data: <?php echo json_encode($daily_confirmed_counts); ?>,
                            borderColor: successColor,
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4,
                            pointRadius: 4,
                            pointBackgroundColor: successColor,
                            pointBorderColor: 'white',
                            pointBorderWidth: 2
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 15,
                                padding: 15,
                                font: { size: 12, weight: '600' }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { font: { size: 11 } },
                            grid: { color: 'rgba(0, 0, 0, 0.05)' }
                        },
                        x: {
                            ticks: { font: { size: 11 } },
                            grid: { display: false }
                        }
                    }
                }
            });
        }
        <?php endif; ?>
        
        // Disease Map JavaScript
        var dashboardMap = null;
        var markersLayer = null;
        
        document.addEventListener('DOMContentLoaded', function() {
            initDashboardMap();
        });
        
        function initDashboardMap() {
            var mapElement = document.getElementById('dashboardMap');
            if (mapElement && typeof L !== 'undefined') {
                try {
                    dashboardMap = L.map('dashboardMap', {
                        center: [-1.2864, 36.8172],
                        zoom: 6,
                        zoomControl: true,
                        attributionControl: true
                    });
                    
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                    }).addTo(dashboardMap);
                    
                    updateDashboardMap();
                } catch (e) {
                    console.error('Error initializing map:', e);
                }
            }
        }
        
        function updateDashboardMap() {
            if (!dashboardMap) return;
            
            var disease = document.getElementById('diseaseFilter').value;
            var days = document.getElementById('dateFilter').value;
            
            fetch(`api/get_heatmap_data.php?disease=${disease}&days=${days}`)
                .then(response => response.json())
                .then(data => {
                    if (markersLayer) {
                        dashboardMap.removeLayer(markersLayer);
                        markersLayer = null;
                    }
                    
                    if (data.points && data.points.length > 0) {
                        markersLayer = L.layerGroup().addTo(dashboardMap);
                        
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
                            markersLayer.addLayer(marker);
                        });
                    }
                    
                    // Display outbreaks
                    if (data.outbreaks && data.outbreaks.length > 0) {
                        if (!markersLayer) {
                            markersLayer = L.layerGroup().addTo(dashboardMap);
                        }
                        data.outbreaks.forEach(function(o) {
                            var affectedArea = L.circle([o.lat, o.lng], {
                                radius: o.radius * 1000,
                                fillColor: '#dc2626',
                                fillOpacity: 0.15,
                                color: '#dc2626',
                                weight: 2,
                                opacity: 0.6
                            }).addTo(dashboardMap);
                            
                            affectedArea.bindPopup('<div style="min-width:150px;">' +
                                '<strong style="color:#dc2626;">OUTBREAK ALERT</strong><hr>' +
                                '<strong>Disease:</strong> ' + o.disease + '<br>' +
                                '<strong>Location:</strong> ' + (o.location || 'Unknown') + '<br>' +
                                '<strong>Affected Radius:</strong> ' + o.radius + ' km<br>' +
                                '<strong>Confirmed Cases:</strong> ' + o.cases_confirmed + '<br>' +
                                '<strong>Alert Date:</strong> ' + o.alert_date + '</div>');
                            
                            var centerMarker = L.circleMarker([o.lat, o.lng], {
                                radius: 8,
                                fillColor: '#dc2626',
                                color: '#ffffff',
                                weight: 2,
                                opacity: 1,
                                fillOpacity: 0.9
                            }).addTo(dashboardMap);
                            
                            centerMarker.bindPopup('<div style="min-width:150px;">' +
                                '<strong style="color:#dc2626;">Outbreak Center</strong><hr>' +
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
                        dashboardMap.fitBounds(bounds, {padding: [50, 50]});
                    }
                });
        }
    </script>
</body>
</html>
