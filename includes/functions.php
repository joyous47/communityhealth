<?php
require_once __DIR__ . '/../config/database.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function getCurrentUserRole() {
    return isset($_SESSION['role']) ? $_SESSION['role'] : null;
}

function getCurrentUserId() {
    return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
}

function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    $pdo = getDB();
    $user_id = $_SESSION['user_id'];
    
    try {
        $stmt = $pdo->prepare("SELECT id, username, email, role, created_at FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        return null;
    }
}

function generateCSRFToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function getDB() {
    static $pdo = null;
    
    if ($pdo === null) {
        require_once __DIR__ . '/../config/database.php';
        $database = new Database();
        $pdo = $database->getConnection();
    }
    
    return $pdo;
}

function loginUser($email, $password) {
    $pdo = getDB();
    
    if (!$pdo) {
        return [false, "Database connection failed.", null];
    }
    
    try {
        $stmt = $pdo->prepare("SELECT id, username, email, password, role FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            return [true, "Login successful!", $user];
        } else {
            return [false, "Invalid email or password.", null];
        }
    } catch (PDOException $e) {
        return [false, "An error occurred. Please try again.", null];
    }
}

function registerUser($username, $email, $password, $role = 'citizen') {
    $pdo = getDB();
    
    if (!$pdo) {
        return [false, "Database connection failed.", null];
    }
    
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            return [false, "Email already registered.", null];
        }
        
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        
        if ($stmt->fetch()) {
            return [false, "Username already taken.", null];
        }
        
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$username, $email, $hashed_password, $role]);
        
        $user_id = $pdo->lastInsertId();
        return [true, "Registration successful!", $user_id];
        
    } catch (PDOException $e) {
        return [false, "An error occurred. Please try again.", null];
    }
}

function requireRole($required_role, $redirect_url = null) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isLoggedIn()) {
        if ($redirect_url) {
            header('Location: ' . $redirect_url);
            exit();
        } else {
            header('Location: ../auth/login.php');
            exit();
        }
    }
    
    $current_role = getCurrentUserRole();
    
    if ($current_role !== $required_role) {
        if ($redirect_url) {
            header('Location: ' . $redirect_url);
            exit();
        } else {
            header('Location: ../index.php?error=unauthorized');
            exit();
        }
    }
}

function formatDate($date, $format = 'F j, Y, g:i a') {
    if (empty($date)) return 'N/A';
    $timestamp = strtotime($date);
    return $timestamp ? date($format, $timestamp) : 'N/A';
}

function timeAgo($date) {
    if (empty($date)) return 'N/A';
    
    $timestamp = strtotime($date);
    if (!$timestamp) return 'N/A';
    
    $diff = time() - $timestamp;
    
    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 2592000) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 31536000) {
        $months = floor($diff / 2592000);
        return $months . ' month' . ($months > 1 ? 's' : '') . ' ago';
    } else {
        $years = floor($diff / 31536000);
        return $years . ' year' . ($years > 1 ? 's' : '') . ' ago';
    }
}

function getSeverityBadge($severity) {
    $colors = [
        'low' => 'success',
        'medium' => 'warning',
        'high' => 'danger',
        'critical' => 'dark'
    ];
    
    $color = $colors[strtolower($severity)] ?? 'secondary';
    $text = ucfirst($severity);
    
    return '<span class="badge badge-' . $color . '">' . htmlspecialchars($text) . '</span>';
}

function getStatusBadge($status) {
    $colors = [
        'pending' => 'warning',
        'analyzed' => 'info',
        'completed' => 'success'
    ];
    
    $color = $colors[strtolower($status)] ?? 'secondary';
    $text = ucfirst($status);
    
    return '<span class="badge badge-' . $color . '">' . htmlspecialchars($text) . '</span>';
}

function getRoleBadge($role) {
    $colors = [
        'citizen' => 'primary',
        'health_worker' => 'info',
        'admin' => 'success'
    ];
    
    $color = $colors[strtolower($role)] ?? 'secondary';
    $text = ucfirst(str_replace('_', ' ', $role));
    
    return '<span class="badge badge-' . $color . '">' . htmlspecialchars($text) . '</span>';
}

function truncateText($text, $length = 100) {
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . '...';
}

function getRandomColor($opacity = 1) {
    $colors = [
        'rgba(255, 99, 132, ' . $opacity . ')',
        'rgba(54, 162, 235, ' . $opacity . ')',
        'rgba(255, 206, 86, ' . $opacity . ')',
        'rgba(75, 192, 192, ' . $opacity . ')',
        'rgba(153, 102, 255, ' . $opacity . ')',
        'rgba(255, 159, 64, ' . $opacity . ')',
        'rgba(199, 199, 199, ' . $opacity . ')',
        'rgba(83, 102, 255, ' . $opacity . ')',
        'rgba(40, 159, 64, ' . $opacity . ')',
        'rgba(210, 199, 199, ' . $opacity . ')'
    ];
    return $colors[array_rand($colors)];
}

function getDiseaseCategories() {
    return [
        'Respiratory' => ['Influenza', 'COVID-19', 'Common Cold', 'Pneumonia', 'Tuberculosis'],
        'Vector-borne' => ['Malaria', 'Dengue', 'Zika', 'Chikungunya', 'Yellow Fever'],
        'Water-borne' => ['Cholera', 'Typhoid', 'Hepatitis A', 'Diarrhea', 'Dysentery'],
        'Childhood' => ['Measles', 'Chickenpox', 'Mumps', 'Rubella', 'Polio'],
        'Other' => ['Food Poisoning', 'HIV/AIDS', 'Diabetes', 'Hypertension', 'Cancer']
    ];
}

function getLocations() {
    return [
        'Downtown Medical Center',
        'Westside Hospital', 
        'North District Clinic',
        'Central City Hospital',
        'Eastside Restaurant Area',
        'South Suburbs',
        'Riverside Community',
        'Coastal Village',
        'Mountain Town',
        'Lakeview Area',
        'University Campus',
        'Industrial Zone',
        'Business District',
        'Residential Area',
        'Rural Village'
    ];
}

function getDashboardUrl($role) {
    switch ($role) {
        case 'citizen':
            return '../citizen/dashboard.php';
        case 'health_worker':
            return '../health_worker/dashboard.php';
        case 'admin':
            return '../admin/dashboard.php';
        default:
            return '../index.php';
    }
}

function checkSessionTimeout($timeout = 1800) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
        session_unset();
        session_destroy();
        header('Location: ../auth/login.php?timeout=1');
        exit();
    }
    $_SESSION['last_activity'] = time();
}

function generateUniqueFilename($extension = '') {
    return uniqid() . '_' . time() . ($extension ? '.' . $extension : '');
}

function sanitizeFilename($filename) {
    $filename = preg_replace('/[^a-zA-Z0-9\._-]/', '_', $filename);
    $filename = strtolower($filename);
    $filename = preg_replace('/_+/', '_', $filename);
    return $filename;
}

function validateFileUpload($file, $allowed_types = ['image/jpeg', 'image/png', 'image/gif'], $max_size = 5242880) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [false, 'File upload error: ' . $file['error']];
    }
    
    if ($file['size'] > $max_size) {
        return [false, 'File too large. Maximum size: ' . ($max_size / 1024 / 1024) . 'MB'];
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_types)) {
        return [false, 'Invalid file type. Allowed: ' . implode(', ', $allowed_types)];
    }
    
    return [true, 'File valid'];
}
?>