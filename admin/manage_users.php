<?php
require_once '../includes/header.php';
require_once '../config/database.php';

$database = new Database();
$pdo = $database->getConnection();

if (!isLoggedIn() || getCurrentUserRole() !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

$success_message = '';
$error_message = '';
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            $error_message = "Invalid security token. Please try again.";
        } else {
            $action_type = $_POST['action_type'] ?? '';
            
            if ($action_type === 'edit_user') {
                $edit_id = (int)$_POST['user_id'];
                $new_role = trim($_POST['role'] ?? '');
                $username = trim($_POST['username'] ?? '');
                $email = trim($_POST['email'] ?? '');
                
                $current_user = getCurrentUser();
                if ($edit_id === $current_user['id']) {
                    $error_message = "You cannot change your own role.";
                } elseif (empty($new_role) || empty($username) || empty($email)) {
                    $error_message = "Please fill in all required fields.";
                } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $error_message = "Invalid email format.";
                } else {
                    $check_query = "SELECT id FROM users WHERE email = ? AND id != ?";
                    $check_stmt = $pdo->prepare($check_query);
                    $check_stmt->execute([$email, $edit_id]);
                    
                    if ($check_stmt->rowCount() > 0) {
                        $error_message = "Email already in use.";
                    } else {
                        $update_query = "UPDATE users SET role = ?, username = ?, email = ? WHERE id = ?";
                        $update_stmt = $pdo->prepare($update_query);
                        $update_stmt->execute([$new_role, $username, $email, $edit_id]);
                        
                        $success_message = "User updated successfully!";
                        $action = 'list';
                    }
                }
            } elseif ($action_type === 'delete_user') {
                $delete_id = (int)$_POST['user_id'];
                $current_user = getCurrentUser();
                
                if ($delete_id === $current_user['id']) {
                    $error_message = "You cannot delete your own account.";
                } else {
                    $pdo->beginTransaction();
                    
                    try {
                        $pdo->prepare("DELETE FROM analyses WHERE health_worker_id = ?")->execute([$delete_id]);
                        $pdo->prepare("DELETE FROM recommendations WHERE health_worker_id = ?")->execute([$delete_id]);
                        $pdo->prepare("DELETE FROM visualizations WHERE admin_id = ?")->execute([$delete_id]);
                        $pdo->prepare("DELETE FROM reports WHERE citizen_id = ?")->execute([$delete_id]);
                        $pdo->prepare("DELETE FROM analytics WHERE report_id IN (SELECT id FROM reports WHERE citizen_id = ?)")->execute([$delete_id]);
                        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$delete_id]);
                        
                        $pdo->commit();
                        $success_message = "User deleted successfully!";
                        $action = 'list';
                    } catch(Exception $e) {
                        $pdo->rollBack();
                        $error_message = "Error deleting user: " . $e->getMessage();
                    }
                }
            }
        }
    }
    
    $role_filter = isset($_GET['role']) ? $_GET['role'] : '';
    $search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $items_per_page = 10;
    $offset = ($page - 1) * $items_per_page;
    
    $where_clauses = [];
    $params = [];
    
    if (!empty($role_filter)) {
        $where_clauses[] = "role = ?";
        $params[] = $role_filter;
    }
    
    if (!empty($search_query)) {
        $where_clauses[] = "(username LIKE ? OR email LIKE ?)";
        $params[] = "%$search_query%";
        $params[] = "%$search_query%";
    }
    
    $where_clause = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";
    
    $count_query = "SELECT COUNT(*) as total FROM users $where_clause";
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute($params);
    $total_users = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_users / $items_per_page);
    
    $users_query = "SELECT * FROM users $where_clause ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $users_stmt = $pdo->prepare($users_query);
    
    $all_params = array_merge($params, [$items_per_page, $offset]);
    $users_stmt->execute($all_params);
    $users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $current_edit_user = null;
    if (!empty($user_id) && $action === 'edit') {
        $user_query = "SELECT * FROM users WHERE id = ?";
        $user_stmt = $pdo->prepare($user_query);
        $user_stmt->execute([$user_id]);
        $current_edit_user = $user_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$current_edit_user) {
            $error_message = "User not found.";
            $action = 'list';
        }
    }
    
    $stats_query = "SELECT 
                    role, 
                    COUNT(*) as count,
                    MIN(created_at) as first_join,
                    MAX(created_at) as last_join
                FROM users 
                GROUP BY role";
    $stats = $pdo->query($stats_query)->fetchAll(PDO::FETCH_ASSOC);
    
    $overall_stats = [
        'total_users' => $pdo->query("SELECT COUNT(*) as count FROM users")->fetch(PDO::FETCH_ASSOC)['count'],
        'total_citizens' => $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'citizen'")->fetch(PDO::FETCH_ASSOC)['count'],
        'total_health_workers' => $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'health_worker'")->fetch(PDO::FETCH_ASSOC)['count'],
        'total_admins' => $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'")->fetch(PDO::FETCH_ASSOC)['count']
    ];
    
} catch(PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
    $users = [];
}

function getRoleIcon($role) {
    switch($role) {
        case 'citizen':
            return '<i class="fas fa-user"></i> Citizen';
        case 'health_worker':
            return '<i class="fas fa-user-nurse"></i> Health Worker';
        case 'admin':
            return '<i class="fas fa-user-shield"></i> Admin';
        default:
            return ucfirst($role);
    }
}

function getRoleColor($role) {
    switch($role) {
        case 'citizen':
            return 'badge-citizen';
        case 'health_worker':
            return 'badge-health-worker';
        case 'admin':
            return 'badge-admin';
        default:
            return 'badge-default';
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('manage_users'); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .page-header {
            background: linear-gradient(135deg, #4da8da 0%, #0077be 100%);
            color: white;

            margin-bottom: 30px;
        }

        .page-header h1 {
            margin: 0;
            font-size: 2.5rem;
            font-weight: 700;
            color: #000;
        }

        .page-header p {
            margin: 10px 0 0 0;
            font-size: 1.1rem;
            opacity: 0.9;
            color:#000;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-left: 4px solid #4da8da;
            text-align: center;
            color: #333;
        }

        .stat-card.health-worker {
            border-left-color: #2ed573;
        }

        .stat-card.admin {
            border-left-color: #ff4757;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #000;
            margin: 10px 0;
        }

        .stat-label {
            font-size: 0.9rem;
            color: #666;
            font-weight: 500;
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
        }

        .filter-section label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            color: #000;
        }

        .filter-section input,
        .filter-section select {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 0.95rem;
            transition: border-color 0.3s;
            color: #333;
            background: white;
        }

        .filter-section input:focus,
        .filter-section select:focus {
            outline: none;
            border-color: #4da8da;
            box-shadow: 0 0 5px rgba(77, 168, 218, 0.3);
        }

        .filter-section button {
            padding: 10px 20px;
            background-color: #4da8da;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }

        .filter-section button:hover {
            background-color: #0077be;
        }

        .filter-section a {
            padding: 10px 20px;
            background-color: #ccc;
            color: #000;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .filter-section a:hover {
            background-color: #999;
        }

        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .alert-danger {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .form-container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #000;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
            color: #333;
            background: white;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #4da8da;
            box-shadow: 0 0 5px rgba(77, 168, 218, 0.3);
        }

        .button-group {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
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
            background-color: #4da8da;
            color: white;
        }

        .btn-primary:hover {
            background-color: #0077be;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background-color: #ccc;
            color: #000;
        }

        .btn-secondary:hover {
            background-color: #999;
        }

        .btn-danger {
            background-color: #ff4757;
            color: white;
        }

        .btn-danger:hover {
            background-color: #e73546;
        }

        .users-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .users-table thead {
            background: linear-gradient(135deg, #4da8da 0%, #0077be 100%);
            color: white;
        }

        .users-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }

        .users-table tbody tr {
            border-bottom: 1px solid #eee;
            transition: background-color 0.3s;
        }

        .users-table tbody tr:hover {
            background-color: #f9f9f9;
        }

        .users-table td {
            padding: 15px;
            color: #333;
        }

        .role-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-citizen {
            background-color: #e3f2fd;
            color: #1976d2;
        }

        .badge-health-worker {
            background-color: #e8f5e9;
            color: #388e3c;
        }

        .badge-admin {
            background-color: #ffebee;
            color: #c62828;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-small {
            padding: 8px 12px;
            font-size: 0.85rem;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .empty-state i {
            font-size: 4rem;
            color: #ccc;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            color: #000;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #666;
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
            color: #4da8da;
            font-weight: 600;
            transition: all 0.3s;
            background: white;
        }

        .pagination-container a:hover {
            background-color: #4da8da;
            color: white;
        }

        .pagination-container .active {
            background-color: #4da8da;
            color: white;
            border-color: #4da8da;
        }

        @media (max-width: 768px) {
            .page-header h1 {
                font-size: 1.8rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .filter-section {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-section input,
            .filter-section select,
            .filter-section button,
            .filter-section a {
                width: 100%;
            }

            .users-table {
                font-size: 0.9rem;
            }

            .users-table th,
            .users-table td {
                padding: 10px;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn-small {
                width: 100%;
                justify-content: center;
            }

            .button-group {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }
        
        body {
            background-color: #f5f5f5;
            color: #333;
        }
        
        .container {
            background: white;
            padding: 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <div class="container">
                <h1><i class="fas fa-users"></i> <?php echo t('manage_users'); ?></h1>
                <p><?php echo t('manage_users_desc'); ?></p>
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

        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-users" style="font-size: 2rem; color: #4da8da;"></i>
                <div class="stat-value"><?php echo $overall_stats['total_users']; ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-user" style="font-size: 2rem; color: #1e90ff;"></i>
                <div class="stat-value"><?php echo $overall_stats['total_citizens']; ?></div>
                <div class="stat-label">Citizens</div>
            </div>
            <div class="stat-card health-worker">
                <i class="fas fa-user-nurse" style="font-size: 2rem; color: #2ed573;"></i>
                <div class="stat-value"><?php echo $overall_stats['total_health_workers']; ?></div>
                <div class="stat-label">Health Workers</div>
            </div>
            <div class="stat-card admin">
                <i class="fas fa-user-shield" style="font-size: 2rem; color: #ff4757;"></i>
                <div class="stat-value"><?php echo $overall_stats['total_admins']; ?></div>
                <div class="stat-label">Admins</div>
            </div>
        </div>

        <?php if ($action === 'edit' && $current_edit_user): ?>
            <div class="form-container">
                <h2>Edit User</h2>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action_type" value="edit_user">
                    <input type="hidden" name="user_id" value="<?php echo $current_edit_user['id']; ?>">

                    <div class="form-group">
                        <label for="username"><i class="fas fa-user"></i> Username</label>
                        <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($current_edit_user['username']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="email"><i class="fas fa-envelope"></i> Email</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($current_edit_user['email']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="role"><i class="fas fa-tag"></i> Role</label>
                        <select id="role" name="role" required>
                            <option value="citizen" <?php echo ($current_edit_user['role'] === 'citizen') ? 'selected' : ''; ?>>Citizen</option>
                            <option value="health_worker" <?php echo ($current_edit_user['role'] === 'health_worker') ? 'selected' : ''; ?>>Health Worker</option>
                            <option value="admin" <?php echo ($current_edit_user['role'] === 'admin') ? 'selected' : ''; ?>>Admin</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-calendar"></i> Member Since</label>
                        <p style="margin: 0; padding: 12px; background: #f5f5f5; border-radius: 5px; color: #333;">
                            <?php echo date('F d, Y', strtotime($current_edit_user['created_at'])); ?>
                        </p>
                    </div>

                    <div class="button-group">
                        <a href="manage_users.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>

        <?php else: ?>
            <div class="filter-section">
                <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: center; width: 100%;">
                    <label for="search"><i class="fas fa-search"></i> Search:</label>
                    <input type="text" id="search" name="search" placeholder="Username or email..." value="<?php echo htmlspecialchars($search_query); ?>" style="flex: 1; min-width: 200px;">

                    <label for="role"><i class="fas fa-filter"></i> Role:</label>
                    <select id="role" name="role" style="min-width: 150px;">
                        <option value="">All Roles</option>
                        <option value="citizen" <?php echo ($role_filter === 'citizen') ? 'selected' : ''; ?>>Citizen</option>
                        <option value="health_worker" <?php echo ($role_filter === 'health_worker') ? 'selected' : ''; ?>>Health Worker</option>
                        <option value="admin" <?php echo ($role_filter === 'admin') ? 'selected' : ''; ?>>Admin</option>
                    </select>

                    <button type="submit"><i class="fas fa-search"></i> Filter</button>
                    <a href="manage_users.php" style="margin-left: auto;"><i class="fas fa-redo"></i> Reset</a>
                </form>
            </div>

            <?php if (!empty($users)): ?>
                <table class="users-table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-user"></i> Username</th>
                            <th><i class="fas fa-envelope"></i> Email</th>
                            <th><i class="fas fa-tag"></i> Role</th>
                            <th><i class="fas fa-calendar"></i> Joined</th>
                            <th><i class="fas fa-cog"></i> Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($users as $user): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <span class="role-badge <?php echo getRoleColor($user['role']); ?>">
                                        <?php echo getRoleIcon($user['role']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="manage_users.php?action=edit&id=<?php echo $user['id']; ?>" class="btn btn-primary btn-small">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <button class="btn btn-danger btn-small" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ($total_pages > 1): ?>
                    <div class="pagination-container">
                        <?php if ($page > 1): ?>
                            <a href="?page=1&role=<?php echo urlencode($role_filter); ?>&search=<?php echo urlencode($search_query); ?>">
                                <i class="fas fa-chevron-left"></i> First
                            </a>
                            <a href="?page=<?php echo $page - 1; ?>&role=<?php echo urlencode($role_filter); ?>&search=<?php echo urlencode($search_query); ?>">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
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
                                <a href="?page=<?php echo $i; ?>&role=<?php echo urlencode($role_filter); ?>&search=<?php echo urlencode($search_query); ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endif; ?>
                        <?php endfor;
                        
                        if ($end < $total_pages): ?>
                            <span>...</span>
                        <?php endif;
                        
                        if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&role=<?php echo urlencode($role_filter); ?>&search=<?php echo urlencode($search_query); ?>">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                            <a href="?page=<?php echo $total_pages; ?>&role=<?php echo urlencode($role_filter); ?>&search=<?php echo urlencode($search_query); ?>">
                                Last <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                    <p style="text-align: center; color: #666; margin-top: 15px;">
                        Showing <?php echo count($users); ?> of <?php echo $total_users; ?> users | Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                    </p>
                <?php endif; ?>

            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-search"></i>
                    <h3>No Users Found</h3>
                    <p>No users match your search criteria.</p>
                </div>
            <?php endif; ?>

        <?php endif; ?>

        <form id="deleteForm" method="POST" style="display: none;">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action_type" value="delete_user">
            <input type="hidden" id="deleteUserId" name="user_id">
        </form>
    </div>

    <script>
        function deleteUser(userId, username) {
            if (confirm(`Are you sure you want to delete user "${username}"? This action cannot be undone.`)) {
                document.getElementById('deleteUserId').value = userId;
                document.getElementById('deleteForm').submit();
            }
        }
    </script>
</body>
</html>