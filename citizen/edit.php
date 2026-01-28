<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/functions.php';
require_once '../config/database.php';

requireRole('citizen', '../auth/login.php');

$user = getCurrentUser();
$user_id = $user['id'];

$report_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($report_id <= 0) {
    $_SESSION['error_message'] = 'Invalid report id.';
    header('Location: view_reports.php');
    exit();
}

$db = getDB();

try {
    $stmt = $db->prepare('SELECT * FROM reports WHERE id = ? AND citizen_id = ?');
    $stmt->execute([$report_id, $user_id]);
    $report = $stmt->fetch();

    if (!$report) {
        $_SESSION['error_message'] = 'Report not found or not accessible.';
        header('Location: view_reports.php');
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Error loading report: ' . $e->getMessage();
    header('Location: view_reports.php');
    exit();
}

if ($report['status'] !== 'pending') {
    $_SESSION['error_message'] = 'Only pending reports can be edited.';
    header('Location: view_report_details.php?id=' . $report_id);
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security token invalid. Please try again.';
    } else {
        $disease_name = trim($_POST['disease_name'] ?? '');
        $symptoms = trim($_POST['symptoms'] ?? '');
        $location = trim($_POST['location'] ?? '');

        if (empty($disease_name) || empty($symptoms) || empty($location)) {
            $error = 'Please fill in all required fields (disease, symptoms, location).';
        } elseif (strlen($disease_name) > 100) {
            $error = 'Disease name must be less than 100 characters.';
        } elseif (strlen($location) > 200) {
            $error = 'Location must be less than 200 characters.';
        } elseif (strlen($symptoms) > 5000) {
            $error = 'Symptoms description is too long.';
        } else {
            try {
                $update = $db->prepare('UPDATE reports SET disease_name = ?, symptoms = ?, location = ? WHERE id = ? AND citizen_id = ?');
                $update->execute([$disease_name, $symptoms, $location, $report_id, $user_id]);

                $success = 'Report updated successfully.';

                $stmt = $db->prepare('SELECT * FROM reports WHERE id = ? AND citizen_id = ?');
                $stmt->execute([$report_id, $user_id]);
                $report = $stmt->fetch();

                header('Location: view_report_details.php?id=' . $report_id);
                exit();

            } catch (PDOException $e) {
                $error = 'Error updating report: ' . $e->getMessage();
            }
        }
    }
}

$csrf_token = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Edit Report </title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8fbff;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
            margin: 0;
            padding: 0;
        }
        
        .edit-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .card {
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 6px 20px rgba(0, 119, 204, 0.1);
            border: 1px solid #e0e0e0;
        }
        
        h2 {
            color: #333;
            margin-top: 0;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e8f4ff;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        h2 i {
            color: #0077cc;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 0.95rem;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #0077cc;
            box-shadow: 0 0 0 3px rgba(0, 119, 204, 0.2);
        }
        
        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        
        .btn-primary {
            background: #0077cc;
            color: #fff;
        }
        
        .btn-primary:hover {
            background: #005599;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #666;
            color: #fff;
        }
        
        .btn-secondary:hover {
            background: #555;
            transform: translateY(-2px);
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-weight: 500;
        }
        
        .alert-error {
            background-color: #ffebee;
            color: #d32f2f;
            border-left: 4px solid #d32f2f;
        }
        
        .alert-success {
            background-color: #e8f5e8;
            color: #2e7d32;
            border-left: 4px solid #4caf50;
        }
        
        .button-group {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }
        
        .breadcrumb {
            margin-bottom: 25px;
            padding: 12px 20px;
            background-color: #f0f8ff;
            border-radius: 8px;
            font-size: 0.95rem;
            color: #333;
        }
        
        .breadcrumb a {
            color: #0077cc;
            text-decoration: none;
            margin: 0 5px;
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
            color: #005599;
        }
        
        @media (max-width: 768px) {
            .edit-container {
                margin: 20px auto;
                padding: 0 15px;
            }
            
            .card {
                padding: 20px;
            }
            
            .button-group {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="edit-container">
        <div class="breadcrumb">
            <a href="../index.php"><i class="fas fa-home"></i> Home</a>
            <span>/</span>
            <a href="dashboard.php">Dashboard</a>
            <span>/</span>
            <a href="view_reports.php">My Reports</a>
            <span>/</span>
            <a href="view_report_details.php?id=<?php echo $report['id']; ?>">Report #<?php echo $report['id']; ?></a>
            <span>/</span>
            <span>Edit Report</span>
        </div>
        
        <div class="card">
            <h2><i class="fas fa-edit"></i> Edit Report #<?php echo $report['id']; ?></h2>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                <div class="form-group">
                    <label for="disease_name">Disease Name</label>
                    <input id="disease_name" name="disease_name" class="form-control" maxlength="100" required 
                           value="<?php echo htmlspecialchars($report['disease_name']); ?>">
                </div>

                <div class="form-group">
                    <label for="symptoms">Symptoms</label>
                    <textarea id="symptoms" name="symptoms" class="form-control" required maxlength="5000" 
                              style="min-height: 150px; resize: vertical;"><?php echo htmlspecialchars($report['symptoms']); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="location">Location</label>
                    <input id="location" name="location" class="form-control" maxlength="200" required 
                           value="<?php echo htmlspecialchars($report['location']); ?>">
                </div>

                <div class="button-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                    <a href="view_report_details.php?id=<?php echo $report['id']; ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
</body>
</html>