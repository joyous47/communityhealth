<?php
require_once '../includes/header.php';
require_once '../config/database.php';

$database = new Database();
$pdo = $database->getConnection();

if (!isLoggedIn() || getCurrentUserRole() !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

$admin_id = getCurrentUser()['id'];
$success_message = '';
$error_message = '';
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$viz_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            $error_message = "Invalid security token. Please try again.";
        } else {
            $action_type = $_POST['action_type'] ?? 'create';
            
            if ($action_type === 'create') {
                $disease_name = trim($_POST['disease_name'] ?? '');
                $chart_type = trim($_POST['chart_type'] ?? '');
                $affected_locations = isset($_POST['affected_locations']) ? $_POST['affected_locations'] : [];
                $data_source = trim($_POST['data_source'] ?? '');
                
                if (empty($disease_name) || empty($chart_type) || empty($affected_locations)) {
                    $error_message = "Please fill in all required fields.";
                } else {
                    $affected_locations_json = json_encode($affected_locations);
                    
                    $locations_placeholder = implode(',', array_fill(0, count($affected_locations), '?'));
                    $chart_data_query = "SELECT location, COUNT(*) as count
                                        FROM reports
                                        WHERE disease_name = ? AND location IN ($locations_placeholder)
                                        GROUP BY location";
                    
                    $chart_stmt = $pdo->prepare($chart_data_query);
                    $params = array_merge([$disease_name], $affected_locations);
                    $chart_stmt->execute($params);
                    $chart_data = $chart_stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $chart_data_json = json_encode($chart_data);
                    
                    $insert_query = "INSERT INTO visualizations (admin_id, disease_name, affected_locations, chart_data, created_at)
                                    VALUES (?, ?, ?, ?, NOW())";
                    $insert_stmt = $pdo->prepare($insert_query);
                    $insert_stmt->execute([$admin_id, $disease_name, $affected_locations_json, $chart_data_json]);
                    
                    $success_message = "Visualization created successfully!";
                    $action = 'list';
                }
            } elseif ($action_type === 'delete' && !empty($viz_id)) {
                $delete_query = "DELETE FROM visualizations WHERE id = ? AND admin_id = ?";
                $delete_stmt = $pdo->prepare($delete_query);
                $delete_stmt->execute([$viz_id, $admin_id]);
                
                $success_message = "Visualization deleted successfully!";
                $action = 'list';
            }
        }
    }
    
    $diseases_query = "SELECT DISTINCT disease_name FROM reports ORDER BY disease_name";
    $diseases = $pdo->query($diseases_query)->fetchAll(PDO::FETCH_ASSOC);
    
    $locations_query = "SELECT DISTINCT location FROM reports WHERE location IS NOT NULL AND location != '' ORDER BY location";
    $locations = $pdo->query($locations_query)->fetchAll(PDO::FETCH_ASSOC);
    
    $viz_query = "SELECT * FROM visualizations WHERE admin_id = ? ORDER BY created_at DESC";
    $viz_stmt = $pdo->prepare($viz_query);
    $viz_stmt->execute([$admin_id]);
    $visualizations = $viz_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $current_viz = null;
    if (!empty($viz_id) && $action === 'view') {
        $view_query = "SELECT * FROM visualizations WHERE id = ? AND admin_id = ?";
        $view_stmt = $pdo->prepare($view_query);
        $view_stmt->execute([$viz_id, $admin_id]);
        $current_viz = $view_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$current_viz) {
            $error_message = "Visualization not found.";
            $action = 'list';
        }
    }
    
} catch(PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Visualization</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/charts.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .page-header {
            background: linear-gradient(135deg, #87CEEB 0%, #B0E0E6 100%);
            color: black;
            padding: 30px 0;
            margin-bottom: 30px;
        }

        .page-header h1 {
            margin: 0;
            font-size: 2.5rem;
            font-weight: 700;
        }

        .page-header p {
            margin: 10px 0 0 0;
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .nav-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 2px solid #ddd;
        }

        .tab-btn {
            padding: 12px 20px;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            color: #666;
            transition: all 0.3s;
        }

        .tab-btn.active {
            color: #87CEEB;
            border-bottom-color: #87CEEB;
        }

        .tab-btn:hover {
            color: #87CEEB;
        }

        .form-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            border: 1px solid #e0e0e0;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .form-group input[type="text"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            font-family: inherit;
            transition: border-color 0.3s;
        }

        .form-group input[type="text"]:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #87CEEB;
            box-shadow: 0 0 5px rgba(135, 206, 235, 0.3);
        }

        .location-checkboxes {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 10px;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 5px;
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #e0e0e0;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .checkbox-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .checkbox-item label {
            margin: 0;
            cursor: pointer;
            font-weight: normal;
        }

        .button-group {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 30px;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background-color: #87CEEB;
            color: black;
        }

        .btn-primary:hover {
            background-color: #76B9D6;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(135, 206, 235, 0.3);
        }

        .btn-secondary {
            background-color: #cccccc;
            color: black;
        }

        .btn-secondary:hover {
            background-color: #bbbbbb;
        }

        .btn-danger {
            background-color: #000000;
            color: white;
        }

        .btn-danger:hover {
            background-color: #333333;
        }

        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            border: 1px solid;
        }

        .alert-success {
            background-color: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }

        .alert-danger {
            background-color: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }

        .viz-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(500px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }

        .viz-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: all 0.3s;
            border: 1px solid #e0e0e0;
        }

        .viz-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.15);
        }

        .viz-card-header {
            background: linear-gradient(135deg, #87CEEB 0%, #B0E0E6 100%);
            color: black;
            padding: 20px;
        }

        .viz-card-header h3 {
            margin: 0;
            font-size: 1.3rem;
        }

        .viz-card-body {
            padding: 20px;
        }

        .chart-preview {
            position: relative;
            height: 250px;
            margin: 20px 0;
        }

        .viz-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin: 15px 0;
            font-size: 0.9rem;
            color: #666;
        }

        .viz-info-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .viz-card-footer {
            padding: 15px 20px;
            background: #f9f9f9;
            border-top: 1px solid #eee;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .btn-small {
            padding: 8px 15px;
            font-size: 0.9rem;
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
            margin-bottom: 20px;
        }

        .empty-state .btn {
            margin-top: 20px;
        }

        @media (max-width: 768px) {
            .page-header h1 {
                font-size: 1.8rem;
            }

            .viz-grid {
                grid-template-columns: 1fr;
            }

            .nav-tabs {
                flex-wrap: wrap;
            }

            .button-group {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            .chart-preview {
                height: 200px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <div class="container">
                <h1><i class="fas fa-chart-pie"></i> Create Visualization</h1>
                <p>Create and manage disease distribution visualizations by location</p>
            </div>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <div class="nav-tabs">
            <button class="tab-btn <?php echo ($action === 'create') ? 'active' : ''; ?>" onclick="switchTab('create')">
                <i class="fas fa-plus"></i> Create New
            </button>
            <button class="tab-btn <?php echo ($action === 'list') ? 'active' : ''; ?>" onclick="switchTab('list')">
                <i class="fas fa-list"></i> My Visualizations
            </button>
        </div>

        <?php if ($action === 'create'): ?>
            <div class="form-container">
                <h2>Create New Visualization</h2>
                <form method="POST" id="vizForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action_type" value="create">

                    <div class="form-group">
                        <label for="disease_name">
                            <i class="fas fa-virus"></i> Select Disease *
                        </label>
                        <select id="disease_name" name="disease_name" required>
                            <option value="">-- Choose a disease --</option>
                            <?php foreach($diseases as $disease): ?>
                                <option value="<?php echo htmlspecialchars($disease['disease_name']); ?>">
                                    <?php echo htmlspecialchars($disease['disease_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="chart_type">
                            <i class="fas fa-chart-bar"></i> Chart Type *
                        </label>
                        <select id="chart_type" name="chart_type" required onchange="updatePreview()">
                            <option value="">-- Choose chart type --</option>
                            <option value="bar">Bar Chart</option>
                            <option value="pie">Pie Chart</option>
                            <option value="doughnut">Doughnut Chart</option>
                            <option value="line">Line Chart</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>
                            <i class="fas fa-map-marker-alt"></i> Select Locations *
                        </label>
                        <div class="location-checkboxes">
                            <?php if (!empty($locations)): ?>
                                <?php foreach($locations as $location): ?>
                                    <div class="checkbox-item">
                                        <input type="checkbox" id="loc_<?php echo md5($location['location']); ?>" 
                                               name="affected_locations[]" 
                                               value="<?php echo htmlspecialchars($location['location']); ?>"
                                               onchange="updatePreview()">
                                        <label for="loc_<?php echo md5($location['location']); ?>">
                                            <?php echo htmlspecialchars($location['location']); ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p style="color: #999;">No locations available yet. Submit some reports first.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="data_source">Data Source Information</label>
                        <textarea id="data_source" name="data_source" rows="3" placeholder="Add any notes about this visualization..."></textarea>
                    </div>

                    <div class="button-group">
                        <button type="button" class="btn btn-secondary" onclick="switchTab('list')">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Create Visualization
                        </button>
                    </div>
                </form>
            </div>

            <div class="form-container">
                <h3><i class="fas fa-eye"></i> Chart Preview</h3>
                <div style="position: relative; height: 300px;">
                    <canvas id="previewChart"></canvas>
                </div>
                <p style="text-align: center; color: #999; margin-top: 15px;">
                    Select disease, chart type, and locations to see preview
                </p>
            </div>

        <?php else: ?>
            <?php if (!empty($visualizations)): ?>
                <div class="viz-grid">
                    <?php foreach($visualizations as $viz): ?>
                        <?php 
                            $chart_data = json_decode($viz['chart_data'], true);
                            $locations = json_decode($viz['affected_locations'], true);
                        ?>
                        <div class="viz-card">
                            <div class="viz-card-header">
                                <h3><?php echo htmlspecialchars($viz['disease_name']); ?></h3>
                            </div>
                            <div class="viz-card-body">
                                <div class="viz-info">
                                    <div class="viz-info-item">
                                        <i class="fas fa-calendar"></i>
                                        <span><?php echo date('M d, Y', strtotime($viz['created_at'])); ?></span>
                                    </div>
                                    <div class="viz-info-item">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <span><?php echo count($locations); ?> locations</span>
                                    </div>
                                </div>

                                <div class="chart-preview">
                                    <canvas id="chart_<?php echo $viz['id']; ?>"></canvas>
                                </div>

                                <p style="color: #666; font-size: 0.9rem; margin: 15px 0;">
                                    <strong>Locations:</strong> <?php echo htmlspecialchars(implode(', ', $locations)); ?>
                                </p>
                            </div>

                            <div class="viz-card-footer">
                                <button class="btn btn-secondary btn-small" onclick="viewVisualization(<?php echo $viz['id']; ?>)">
                                    <i class="fas fa-eye"></i> View
                                </button>
                                <button class="btn btn-danger btn-small" onclick="deleteVisualization(<?php echo $viz['id']; ?>)">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-chart-pie"></i>
                    <h3>No Visualizations Yet</h3>
                    <p>Create your first visualization to analyze disease distribution by location</p>
                    <button class="btn btn-primary" onclick="switchTab('create')">
                        <i class="fas fa-plus"></i> Create Visualization
                    </button>
                </div>
            <?php endif; ?>

        <?php endif; ?>

        <form id="deleteForm" method="POST" style="display: none;">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action_type" value="delete">
            <input type="hidden" id="deleteId" name="id">
        </form>
    </div>

    <script>
        const colorScheme = {
            primary: '#87CEEB',
            secondary: '#B0E0E6',
            accent1: '#76B9D6',
            accent2: '#5CA8D2',
            accent3: '#ADD8E6'
        };

        let previewChart = null;

        function switchTab(tab) {
            if (tab === 'create') {
                window.location.href = 'create_visualization.php?action=create';
            } else {
                window.location.href = 'create_visualization.php?action=list';
            }
        }

        function updatePreview() {
            const disease = document.getElementById('disease_name').value;
            const chartType = document.getElementById('chart_type').value;
            const locations = Array.from(document.querySelectorAll('input[name="affected_locations[]"]:checked'))
                .map(el => el.value);

            if (!disease || !chartType || locations.length === 0) {
                if (previewChart) {
                    previewChart.destroy();
                    previewChart = null;
                }
                return;
            }

            const chartData = locations.map(loc => Math.floor(Math.random() * 100) + 10);

            const ctx = document.getElementById('previewChart').getContext('2d');

            if (previewChart) {
                previewChart.destroy();
            }

            const config = {
                type: chartType,
                data: {
                    labels: locations,
                    datasets: [{
                        label: 'Reports by Location',
                        data: chartData,
                        backgroundColor: [
                            colorScheme.primary,
                            colorScheme.secondary,
                            colorScheme.accent1,
                            colorScheme.accent2,
                            colorScheme.accent3
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
            };

            previewChart = new Chart(ctx, config);
        }

        function viewVisualization(id) {
            window.location.href = 'create_visualization.php?action=view&id=' + id;
        }

        function deleteVisualization(id) {
            if (confirm('Are you sure you want to delete this visualization?')) {
                document.getElementById('deleteId').value = id;
                document.getElementById('deleteForm').submit();
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const charts = document.querySelectorAll('[id^="chart_"]');
            charts.forEach(canvas => {
                const chartData = <?php echo json_encode(array_map(function($v) { 
                    return [
                        'id' => $v['id'],
                        'disease' => $v['disease_name'],
                        'data' => json_decode($v['chart_data'], true)
                    ];
                }, $visualizations ?? [])); ?>;

                const chartId = canvas.id.replace('chart_', '');
                const viz = chartData.find(v => v.id == chartId);

                if (viz && viz.data) {
                    const locations = viz.data.map(d => d.location);
                    const counts = viz.data.map(d => d.count);

                    new Chart(canvas, {
                        type: 'bar',
                        data: {
                            labels: locations,
                            datasets: [{
                                label: 'Reports',
                                data: counts,
                                backgroundColor: colorScheme.primary,
                                borderColor: 'white',
                                borderWidth: 2,
                                borderRadius: 5
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false }
                            },
                            scales: {
                                y: { beginAtZero: true }
                            }
                        }
                    });
                }
            });
        });
    </script>
</body>
</html>