<?php
require_once __DIR__ . '/../config/database.php';

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
        'low' => 'badge-lightblue',
        'medium' => 'badge-lightblue',
        'high' => 'badge-lightblue',
        'critical' => 'badge-lightblue'
    ];
    
    $color = $colors[strtolower($severity)] ?? 'badge-lightblue';
    $text = ucfirst($severity);
    
    return '<span class="badge ' . $color . '">' . htmlspecialchars($text) . '</span>';
}

function getStatusBadge($status) {
    $colors = [
        'pending' => 'badge-lightblue',
        'analyzed' => 'badge-lightblue',
        'completed' => 'badge-lightblue'
    ];
    
    $color = $colors[strtolower($status)] ?? 'badge-lightblue';
    $text = ucfirst($status);
    
    return '<span class="badge ' . $color . '">' . htmlspecialchars($text) . '</span>';
}

function getRoleBadge($role) {
    $colors = [
        'citizen' => 'badge-lightblue',
        'health_worker' => 'badge-lightblue',
        'admin' => 'badge-lightblue'
    ];
    
    $color = $colors[strtolower($role)] ?? 'badge-lightblue';
    $text = ucfirst(str_replace('_', ' ', $role));
    
    return '<span class="badge ' . $color . '">' . htmlspecialchars($text) . '</span>';
}

function truncateText($text, $length = 100) {
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . '...';
}

function getRandomColor($opacity = 1) {
    $colors = [
        'rgba(14, 165, 233, ' . $opacity . ')',
        'rgba(56, 189, 248, ' . $opacity . ')',
        'rgba(125, 211, 252, ' . $opacity . ')',
        'rgba(186, 230, 253, ' . $opacity . ')',
        'rgba(14, 165, 233, ' . $opacity . ')',
        'rgba(56, 189, 248, ' . $opacity . ')',
        'rgba(125, 211, 252, ' . $opacity . ')',
        'rgba(186, 230, 253, ' . $opacity . ')',
        'rgba(14, 165, 233, ' . $opacity . ')',
        'rgba(56, 189, 248, ' . $opacity . ')'
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