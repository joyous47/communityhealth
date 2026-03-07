<?php
require_once '../includes/header.php';
require_once '../config/database.php';

$database = new Database();
$pdo = $database->getConnection();

if (!isLoggedIn() || getCurrentUserRole() !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

try {
    $total_reports_query = "SELECT COUNT(*) as total FROM reports";
    $total_reports = $pdo->query($total_reports_query)->fetch(PDO::FETCH_ASSOC)['total'];
    
    $total_analyses_query = "SELECT COUNT(*) as total FROM analyses";
    $total_analyses = $pdo->query($total_analyses_query)->fetch(PDO::FETCH_ASSOC)['total'];
    
    $pending_reports_query = "SELECT COUNT(*) as total FROM reports WHERE status = 'pending'";
    $pending_reports = $pdo->query($pending_reports_query)->fetch(PDO::FETCH_ASSOC)['total'];
    
    $completed_reports_query = "SELECT COUNT(*) as total FROM reports WHERE status = 'completed'";
    $completed_reports = $pdo->query($completed_reports_query)->fetch(PDO::FETCH_ASSOC)['total'];
    
    $avg_response_query = "SELECT AVG(response_time_hours) as avg_time FROM analytics WHERE response_time_hours > 0";
    $avg_response = $pdo->query($avg_response_query)->fetch(PDO::FETCH_ASSOC)['avg_time'];
    $avg_response = $avg_response ? round($avg_response, 2) : 0;
    
    $diseases_query = "SELECT disease_category, COUNT(*) as count
                       FROM analytics
                       GROUP BY disease_category
                       ORDER BY count DESC
                       LIMIT 10";
    $diseases_stmt = $pdo->query($diseases_query);
    $top_diseases = $diseases_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $disease_names = array_map(function($d) { return $d['disease_category']; }, $top_diseases);
    $disease_counts = array_map(function($d) { return (int)$d['count']; }, $top_diseases);
    
    $hourly_query = "SELECT report_hour, COUNT(*) as count
                     FROM analytics
                     WHERE report_hour IS NOT NULL
                     GROUP BY report_hour
                     ORDER BY report_hour ASC";
    $hourly_stmt = $pdo->query($hourly_query);
    $hourly_data = $hourly_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $hourly_counts = array_fill(0, 24, 0);
    foreach ($hourly_data as $hour_data) {
        $hourly_counts[(int)$hour_data['report_hour']] = (int)$hour_data['count'];
    }
    
    $daily_query = "SELECT report_day, COUNT(*) as count
                    FROM analytics
                    WHERE report_day IS NOT NULL
                    GROUP BY report_day
                    ORDER BY FIELD(report_day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')";
    $daily_stmt = $pdo->query($daily_query);
    $daily_data = $daily_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $day_names = array_map(function($d) { return $d['report_day']; }, $daily_data);
    $day_counts = array_map(function($d) { return (int)$d['count']; }, $daily_data);
    
    $response_query = "SELECT 
                        SUM(CASE WHEN response_time_hours <= 1 THEN 1 ELSE 0 END) as within_1hr,
                        SUM(CASE WHEN response_time_hours > 1 AND response_time_hours <= 4 THEN 1 ELSE 0 END) as within_4hr,
                        SUM(CASE WHEN response_time_hours > 4 AND response_time_hours <= 12 THEN 1 ELSE 0 END) as within_12hr,
                        SUM(CASE WHEN response_time_hours > 12 THEN 1 ELSE 0 END) as over_12hr
                       FROM analytics";
    $response_data = $pdo->query($response_query)->fetch(PDO::FETCH_ASSOC);
    
    $response_times = [
        (int)$response_data['within_1hr'] ?? 0,
        (int)$response_data['within_4hr'] ?? 0,
        (int)$response_data['within_12hr'] ?? 0,
        (int)$response_data['over_12hr'] ?? 0
    ];
    
    $severity_query = "SELECT severity_level, COUNT(*) as count
                       FROM analyses
                       GROUP BY severity_level
                       ORDER BY FIELD(severity_level, 'critical', 'high', 'medium', 'low')";
    $severity_data = $pdo->query($severity_query)->fetchAll(PDO::FETCH_ASSOC);
    
    $severity_labels = array_map(function($d) { return ucfirst($d['severity_level']); }, $severity_data);
    $severity_counts = array_map(function($d) { return (int)$d['count']; }, $severity_data);
    
    $trend_query = "SELECT DATE(created_at) as date, COUNT(*) as count
                    FROM reports
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    GROUP BY DATE(created_at)
                    ORDER BY created_at ASC";
    $trend_data = $pdo->query($trend_query)->fetchAll(PDO::FETCH_ASSOC);
    
    $trend_dates = array_map(function($d) { return date('M d', strtotime($d['date'])); }, $trend_data);
    $trend_counts = array_map(function($d) { return (int)$d['count']; }, $trend_data);
    
    $status_query = "SELECT status, COUNT(*) as count
                     FROM reports
                     GROUP BY status";
    $status_data = $pdo->query($status_query)->fetchAll(PDO::FETCH_ASSOC);
    
    $status_labels = array_map(function($d) { return ucfirst($d['status']); }, $status_data);
    $status_counts = array_map(function($d) { return (int)$d['count']; }, $status_data);
    
    $response_rate = $total_analyses > 0 ? round(($total_analyses / $total_reports) * 100, 1) : 0;
    
} catch(PDOException $e) {
    $error_message = "Error fetching analytics: " . $e->getMessage();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/charts.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .page-header {
            background: linear-gradient(135deg, #4da8da 0%, #0077be 100%);
            color: white;
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
            color: #000;
            opacity: 0.9;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-left: 5px solid #4da8da;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
            color: #000;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(77,168,218,0.1) 0%, transparent 70%);
            opacity: 0;
            transition: opacity 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.15);
        }

        .stat-card:hover::before {
            opacity: 1;
        }

        .stat-card.warning {
            border-left-color: #ffa502;
        }

        .stat-card.success {
            border-left-color: #2ed573;
        }

        .stat-card.danger {
            border-left-color: #ff4757;
        }

        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
            opacity: 0.7;
        }

        .stat-card.warning .stat-icon {
            color: #ffa502;
        }

        .stat-card.success .stat-icon {
            color: #2ed573;
        }

        .stat-card.danger .stat-icon {
            color: #ff4757;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #000;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.95rem;
            color: #666;
            font-weight: 500;
        }

        .stat-description {
            font-size: 0.85rem;
            color: #999;
            margin-top: 5px;
        }

        .charts-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }

        .chart-container {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            color: #333;
        }

        .chart-container h3 {
            margin-top: 0;
            margin-bottom: 20px;
            color: #000;
            font-size: 1.3rem;
            border-bottom: 2px solid #4da8da;
            padding-bottom: 10px;
        }

        .chart-wrapper {
            position: relative;
            height: 350px;
        }

        .full-width {
            grid-column: 1 / -1;
        }

        .error-message {
            background-color: #fee;
            border: 1px solid #fcc;
            color: #a33;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .export-section {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-bottom: 30px;
        }

        .btn-export {
            background-color: #4da8da;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.95rem;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-export:hover {
            background-color: #0077be;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(77, 168, 218, 0.3);
        }

        .btn-export.csv {
            background-color: #2ed573;
        }

        .btn-export.csv:hover {
            background-color: #1bc56c;
        }

        @media (max-width: 1200px) {
            .charts-section {
                grid-template-columns: 1fr;
            }

            .chart-container.full-width {
                grid-column: 1;
            }
        }

        @media (max-width: 768px) {
            .page-header h1 {
                font-size: 1.8rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .chart-wrapper {
                height: 250px;
            }

            .export-section {
                flex-direction: column;
            }

            .btn-export {
                width: 100%;
                justify-content: center;
            }
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .loading {
            display: inline-block;
            animation: spin 1s linear infinite;
        }
        
        body {
            background-color: #f5f5f5;
        }
        
        .container {
            background: white;
            padding: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <div class="container">
                <h1 style="color: #000;"><i class="fas fa-chart-line"></i> Analytics Dashboard</h1>
                <p>Monitor system performance and community health system metrics</p>
            </div>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <div class="export-section">
            <button class="btn-export csv" onclick="exportAsCSV()">
                <i class="fas fa-download"></i> Export as CSV
            </button>
            <button class="btn-export" onclick="exportAsPDF()">
                <i class="fas fa-file-pdf"></i> Export as PDF
            </button>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="stat-value"><?php echo number_format($total_reports); ?></div>
                <div class="stat-label">Total Reports</div>
                <div class="stat-description">Submissions from citizens</div>
            </div>

            <div class="stat-card success">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-value"><?php echo number_format($total_analyses); ?></div>
                <div class="stat-label">Total Analyses</div>
                <div class="stat-description">Reviewed by health workers</div>
            </div>

            <div class="stat-card warning">
                <div class="stat-icon">
                    <i class="fas fa-hourglass-half"></i>
                </div>
                <div class="stat-value"><?php echo number_format($pending_reports); ?></div>
                <div class="stat-label">Pending Reports</div>
                <div class="stat-description">Awaiting analysis</div>
            </div>

            <div class="stat-card success">
                <div class="stat-icon">
                    <i class="fas fa-tasks"></i>
                </div>
                <div class="stat-value"><?php echo number_format($completed_reports); ?></div>
                <div class="stat-label">Completed Reports</div>
                <div class="stat-description">Ready for visualization</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-percentage"></i>
                </div>
                <div class="stat-value"><?php echo $response_rate; ?>%</div>
                <div class="stat-label">Response Rate</div>
                <div class="stat-description">Analysis coverage</div>
            </div>

            <div class="stat-card warning">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-value"><?php echo $avg_response; ?> hrs</div>
                <div class="stat-label">Avg Response Time</div>
                <div class="stat-description">Time to analyze</div>
            </div>
        </div>

        <div class="charts-section">
            <div class="chart-container">
                <h3><i class="fas fa-virus"></i> Top 10 Most Reported Diseases</h3>
                <div class="chart-wrapper">
                    <canvas id="diseasesChart"></canvas>
                </div>
            </div>

            <div class="chart-container">
                <h3><i class="fas fa-exclamation-triangle"></i> Severity Distribution</h3>
                <div class="chart-wrapper">
                    <canvas id="severityChart"></canvas>
                </div>
            </div>

            <div class="chart-container full-width">
                <h3><i class="fas fa-clock"></i> Hourly Reporting Pattern (24 Hours)</h3>
                <div class="chart-wrapper" style="height: 300px;">
                    <canvas id="hourlyChart"></canvas>
                </div>
            </div>

            <div class="chart-container full-width">
                <h3><i class="fas fa-calendar-alt"></i> Daily Reporting Pattern</h3>
                <div class="chart-wrapper" style="height: 300px;">
                    <canvas id="dailyChart"></canvas>
                </div>
            </div>

            <div class="chart-container">
                <h3><i class="fas fa-hourglass"></i> Response Time Distribution</h3>
                <div class="chart-wrapper">
                    <canvas id="responseTimeChart"></canvas>
                </div>
            </div>

            <div class="chart-container">
                <h3><i class="fas fa-tasks"></i> Report Status Distribution</h3>
                <div class="chart-wrapper">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>

            <div class="chart-container full-width">
                <h3><i class="fas fa-trend-chart"></i> Report Trend (Last 30 Days)</h3>
                <div class="chart-wrapper" style="height: 300px;">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <script>
        const colorScheme = {
            primary: '#4da8da',
            secondary: '#0077be',
            success: '#2ed573',
            warning: '#ffa502',
            danger: '#ff4757',
            info: '#1e90ff'
        };

        new Chart(document.getElementById('diseasesChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($disease_names); ?>,
                datasets: [{
                    label: 'Number of Reports',
                    data: <?php echo json_encode($disease_counts); ?>,
                    backgroundColor: colorScheme.primary,
                    borderColor: colorScheme.secondary,
                    borderWidth: 2,
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        grid: { drawBorder: false }
                    }
                }
            }
        });

        new Chart(document.getElementById('severityChart'), {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($severity_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($severity_counts); ?>,
                    backgroundColor: [
                        '#ff4757',
                        '#ffa502',
                        '#ffd93d',
                        '#2ed573'
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
                        position: 'bottom'
                    }
                }
            }
        });

        new Chart(document.getElementById('hourlyChart'), {
            type: 'line',
            data: {
                labels: Array.from({length: 24}, (_, i) => i + ':00'),
                datasets: [{
                    label: 'Reports per Hour',
                    data: <?php echo json_encode($hourly_counts); ?>,
                    borderColor: colorScheme.primary,
                    backgroundColor: 'rgba(77, 168, 218, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 5,
                    pointBackgroundColor: colorScheme.primary,
                    pointBorderColor: 'white',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { drawBorder: false }
                    }
                }
            }
        });

        new Chart(document.getElementById('dailyChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($day_names); ?>,
                datasets: [{
                    label: 'Reports per Day',
                    data: <?php echo json_encode($day_counts); ?>,
                    backgroundColor: colorScheme.secondary,
                    borderColor: colorScheme.primary,
                    borderWidth: 2,
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { drawBorder: false }
                    }
                }
            }
        });

        new Chart(document.getElementById('responseTimeChart'), {
            type: 'pie',
            data: {
                labels: ['Within 1 Hour', '1-4 Hours', '4-12 Hours', 'Over 12 Hours'],
                datasets: [{
                    data: <?php echo json_encode($response_times); ?>,
                    backgroundColor: [
                        '#2ed573',
                        '#ffa502',
                        '#ff4757',
                        '#e71d36'
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
                        position: 'bottom'
                    }
                }
            }
        });

        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($status_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($status_counts); ?>,
                    backgroundColor: [
                        '#1e90ff',
                        '#ffa502',
                        '#2ed573'
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
                        position: 'bottom'
                    }
                }
            }
        });

        new Chart(document.getElementById('trendChart'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode($trend_dates); ?>,
                datasets: [{
                    label: 'Daily Reports',
                    data: <?php echo json_encode($trend_counts); ?>,
                    borderColor: colorScheme.primary,
                    backgroundColor: 'rgba(77, 168, 218, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointBackgroundColor: colorScheme.primary,
                    pointBorderColor: 'white',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { drawBorder: false }
                    }
                }
            }
        });

        function exportAsCSV() {
            const csv_data = [
                ['Analytics Report - ' + new Date().toLocaleDateString()],
                [],
                ['Key Metrics'],
                ['Total Reports', <?php echo $total_reports; ?>],
                ['Total Analyses', <?php echo $total_analyses; ?>],
                ['Pending Reports', <?php echo $pending_reports; ?>],
                ['Completed Reports', <?php echo $completed_reports; ?>],
                ['Response Rate (%)', <?php echo $response_rate; ?>],
                ['Average Response Time (hours)', <?php echo $avg_response; ?>],
                [],
                ['Top 10 Diseases'],
                ['Disease Name', 'Report Count']
            ];

            const diseases = <?php echo json_encode($top_diseases); ?>;
            diseases.forEach(function(d) {
                csv_data.push([d.disease_category, d.count]);
            });

            let csv_content = csv_data.map(row => 
                row.map(cell => '"' + cell + '"').join(',')
            ).join('\n');

            const blob = new Blob([csv_content], { type: 'text/csv' });
            const link = document.createElement('a');
            link.href = window.URL.createObjectURL(blob);
            link.download = 'analytics_report_' + new Date().getTime() + '.csv';
            link.click();
        }

        function exportAsPDF() {
            alert('PDF export functionality can be implemented using jsPDF library.');
        }
    </script>
</body>
</html>