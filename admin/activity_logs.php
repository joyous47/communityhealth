<?php
require_once '../includes/header.php';
require_once '../includes/activity_logger.php';
requireRole('admin', '../auth/login.php');

$logger = new ActivityLogger();


$filters = [];
if (!empty($_GET['user_id'])) $filters['user_id'] = (int)$_GET['user_id'];
if (!empty($_GET['action'])) $filters['action'] = $_GET['action'];
if (!empty($_GET['module'])) $filters['module'] = $_GET['module'];
if (!empty($_GET['date_from'])) $filters['date_from'] = $_GET['date_from'];
if (!empty($_GET['date_to'])) $filters['date_to'] = $_GET['date_to'];


$activities = $logger->getActivities($filters, 100);
$stats = $logger->getStats($filters['date_from'] ?? null, $filters['date_to'] ?? null);


$db = getDB();
$users = [];
try {
    $stmt = $db->query("SELECT id, username, role FROM users ORDER BY username");
    $users = $stmt->fetchAll();
} catch (PDOException $e) {}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - CHMEWS</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: -apple-system, sans-serif; background: #f5f7fa; margin: 0; }
        .page-header { background: linear-gradient(135deg, #0077cc, #005599); color: white; padding: 25px; margin-bottom: 20px; }
        .container { max-width: 1400px; margin: 0 auto; padding: 0 20px; }
        .card { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); margin-bottom: 20px; }
        h1 { margin: 0; font-size: 1.6rem; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .stat-card { background: white; padding: 20px; border-radius: 10px; border-left: 4px solid #0077cc; }
        .stat-card h3 { margin: 0 0 5px; color: #666; font-size: 0.8rem; text-transform: uppercase; }
        .stat-card .value { font-size: 1.8rem; font-weight: bold; color: #0077cc; }
        
        .filters { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin-bottom: 20px; }
        .filters select, .filters input { padding: 8px 12px; border: 1px solid #ddd; border-radius: 5px; }
        .filters button { padding: 8px 16px; background: #0077cc; color: white; border: none; border-radius: 5px; cursor: pointer; }
        
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; font-weight: 600; }
        .action-badge { padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: 500; }
        .action-login { background: #e8f5e9; color: #2e7d32; }
        .action-logout { background: #ffebee; color: #c62828; }
        .action-create { background: #e3f2fd; color: #1565c0; }
        .action-update { background: #fff3e0; color: #ef6c00; }
        .action-delete { background: #fce4ec; color: #c2185b; }
        .action-export { background: #f3e5f5; color: #7b1fa2; }
    </style>
</head>
<body>
    <div class="page-header">
        <h1><i class="fas fa-history"></i> Activity Logs</h1>
    </div>
    
    <div class="container">
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Activities</h3>
                <div class="value"><?php echo $stats['total_activities']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Logins Today</h3>
                <div class="value"><?php echo $stats['logins_today']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Reports Created</h3>
                <div class="value"><?php echo $stats['reports_created']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Active Users (7d)</h3>
                <div class="value"><?php echo $stats['active_users']; ?></div>
            </div>
        </div>
        
        <div class="card">
            <form class="filters" method="GET">
                <select name="user_id">
                    <option value="">All Users</option>
                    <?php foreach ($users as $u): ?>
                    <option value="<?php echo $u['id']; ?>" <?php echo ($_GET['user_id'] ?? '') == $u['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($u['username']); ?> (<?php echo $u['role']; ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
                <select name="action">
                    <option value="">All Actions</option>
                    <option value="LOGIN" <?php echo ($_GET['action'] ?? '') == 'LOGIN' ? 'selected' : ''; ?>>Login</option>
                    <option value="LOGOUT" <?php echo ($_GET['action'] ?? '') == 'LOGOUT' ? 'selected' : ''; ?>>Logout</option>
                    <option value="CREATE" <?php echo ($_GET['action'] ?? '') == 'CREATE' ? 'selected' : ''; ?>>Create</option>
                    <option value="UPDATE" <?php echo ($_GET['action'] ?? '') == 'UPDATE' ? 'selected' : ''; ?>>Update</option>
                    <option value="DELETE" <?php echo ($_GET['action'] ?? '') == 'DELETE' ? 'selected' : ''; ?>>Delete</option>
                    <option value="EXPORT" <?php echo ($_GET['action'] ?? '') == 'EXPORT' ? 'selected' : ''; ?>>Export</option>
                </select>
                <select name="module">
                    <option value="">All Modules</option>
                    <option value="Authentication">Authentication</option>
                    <option value="Reports">Reports</option>
                    <option value="Recommendations">Recommendations</option>
                    <option value="Users">Users</option>
                </select>
                <input type="date" name="date_from" value="<?php echo $_GET['date_from'] ?? ''; ?>" placeholder="From">
                <input type="date" name="date_to" value="<?php echo $_GET['date_to'] ?? ''; ?>" placeholder="To">
                <button type="submit"><i class="fas fa-filter"></i> Filter</button>
                <a href="activity_logs.php" style="padding: 8px 16px; color: #666;">Reset</a>
            </form>
            
            <table>
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Module</th>
                        <th>Details</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($activities)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: #666;">
                            No activities found. <a href="activity_tables.php">Create activity tables</a>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($activities as $activity): ?>
                    <tr>
                        <td><?php echo date('M d, H:i', strtotime($activity['created_at'])); ?></td>
                        <td>#<?php echo $activity['user_id']; ?></td>
                        <td><span class="action-badge action-<?php echo strtolower($activity['action']); ?>"><?php echo $activity['action']; ?></span></td>
                        <td><?php echo $activity['module']; ?></td>
                        <td><?php echo htmlspecialchars($activity['details'] ?? '-'); ?></td>
                        <td><?php echo $activity['ip_address']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>

<?php require_once '../includes/footer.php'; ?>
