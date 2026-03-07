<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'sw'])) {
    $_SESSION['user_lang'] = $_GET['lang'];
    setcookie('user_lang', $_GET['lang'], time() + (86400 * 30), '/');
    $redirectUrl = str_replace(['?lang=en', '?lang=sw', '&lang=en', '&lang=sw'], '', $_SERVER['REQUEST_URI']);
    header('Location: ' . ($redirectUrl ?: 'index.php'));
    exit;
}

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/init_translations.php';

checkSessionTimeout(1800);

$current_user = getCurrentUser();
$current_role = getCurrentUserRole();
$username = $current_user ? $current_user['username'] : 'Guest';
$user_id = $current_user ? $current_user['id'] : null;

$page_title = t('system_title');
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    
    
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/charts.css">
    
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        
        .main-header {
            background: linear-gradient(135deg, #0077cc, #005599);
            color: white;
            padding: 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .header-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 15px;
            text-decoration: none;
            color: white;
        }
        
        .logo-icon {
            font-size: 2rem;
            color: #0077cc;
        }
        
        .logo-text h1 {
            font-size: 1.8rem;
            margin: 0;
            font-weight: 700;
        }
        
        .logo-text p {
            font-size: 0.9rem;
            margin: 0;
            opacity: 0.9;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-greeting {
            font-size: 1rem;
        }
        
        .user-badge {
            background-color: rgba(255, 255, 255, 0.2);
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .logout-btn {
            background-color: #1976d2;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.9rem;
            transition: background-color 0.3s;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .logout-btn:hover {
            background-color: #0d47a1;
        }
        
       
        .main-nav {
            background-color: rgba(0, 0, 0, 0.1);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            align-items: center;
            align-content: center;
            justify-content: center;
        }
        
        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            align-items: center;
            justify-content: flex-end;
            padding: 0 20px;
        }
        
        .nav-menu {
            display: flex;
            list-style: none;
            margin: 0;
            padding: 0;
        }
        
        .nav-item {
            position: relative;
        }
        
        .nav-link {
            display: block;
            padding: 15px 20px;
            color: white;
            text-decoration: none;
            font-size: 1rem;
            transition: all 0.3s;
            border-bottom: 3px solid transparent;
        }
        
        .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
            border-bottom-color: #0077cc;
        }
        
        .nav-link.active {
            background-color: rgba(255, 255, 255, 0.15);
            border-bottom-color: #0077cc;
            font-weight: 600;
        }
        
        .nav-link i {
            margin-right: 8px;
            width: 20px;
            text-align: center;
        }
        
        
        .mobile-menu-toggle {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 10px;
        }
        
        
        @media (max-width: 768px) {
            .header-top {
                flex-wrap: wrap;
            }
            
            .mobile-menu-toggle {
                display: block;
            }
            
            .nav-menu {
                display: none;
                flex-direction: column;
                width: 100%;
                position: absolute;
                top: 100%;
                left: 0;
                background-color: #0077cc;
                box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            }
            
            .nav-menu.show {
                display: flex;
            }
            
            .nav-link {
                padding: 12px 20px;
                border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            }
            
            .user-info {
                margin-top: 10px;
                width: 100%;
                justify-content: space-between;
            }
        }
        
       
        .breadcrumb {
            background-color: #f8f9fa;
            padding: 10px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .breadcrumb-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .breadcrumb-list {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .breadcrumb-item {
            display: flex;
            align-items: center;
        }
        
        .breadcrumb-item a {
            color: #0077cc;
            text-decoration: none;
        }
        
        .breadcrumb-item a:hover {
            text-decoration: underline;
        }
        
        .breadcrumb-separator {
            margin: 0 10px;
            color: #7f8c8d;
        }
        
        .breadcrumb-item.active {
            color: #2c3e50;
            font-weight: 500;
        }
        
    
        .alert-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid transparent;
        }
        
        .alert-success {
            background-color: #e8f4ff;
            color: #005599;
            border-color: #d0e8ff;
        }
        
        .alert-error {
            background-color: #e3f2fd;
            color: #0d47a1;
            border-color: #bbdefb;
        }
        
        .alert-warning {
            background-color: #e8f4ff;
            color: #005599;
            border-color: #d0e8ff;
        }
        
        .alert-info {
            background-color: #e8f4ff;
            color: #005599;
            border-color: #d0e8ff;
        }
    </style>
</head>
<body>
   
    <header class="main-header">
        <div class="">
            <div class="header-top">
               
                <a href="<?php echo isLoggedIn() ? getDashboardUrl($current_role) : '../index.php'; ?>" class="logo">
                    <div class="logo-icon">
                        <i class="fas fa-shield-virus"></i>
                    </div>
                    <div class="logo-text">
                        <h1><?php echo t('system_title'); ?></h1>
                        <p><?php echo t('system_subtitle'); ?></p>
                    </div>
                </a>
                
               
                <button class="mobile-menu-toggle" id="mobileMenuToggle">
                    <i class="fas fa-bars"></i>
                </button>
              
                   
                    <div class="language-switcher" style="display: flex; align-items: center; gap: 5px; margin-left: 15px;">
                        <?php
                        
                        $currentLang = $_SESSION['user_lang'] ?? $_COOKIE['user_lang'] ?? 'en';
                        ?>
                        <a href="?lang=en" class="lang-btn <?php echo $currentLang === 'en' ? 'active' : ''; ?>" 
                           style="padding: 5px 10px; border-radius: 4px; text-decoration: none; color: #fff; background: <?php echo $currentLang === 'en' ? 'rgba(255,255,255,0.3)' : 'transparent'; ?>; font-size: 0.85rem; border: 1px solid rgba(255,255,255,0.3);">
                            EN
                        </a>
                        <a href="?lang=sw" class="lang-btn <?php echo $currentLang === 'sw' ? 'active' : ''; ?>"
                           style="padding: 5px 10px; border-radius: 4px; text-decoration: none; color: #fff; background: <?php echo $currentLang === 'sw' ? 'rgba(255,255,255,0.3)' : 'transparent'; ?>; font-size: 0.85rem; border: 1px solid rgba(255,255,255,0.3);">
                            SW
                        </a>
                    </div>
                </div>
            </div>
            
           
            <nav class="main-nav">
                <div class="nav-container">
                <ul class="nav-menu" id="navMenu">
                        <?php if (!isLoggedIn()): ?>
                           
                            <li class="nav-item">
                                <a href="../index.php" class="nav-link <?php echo $current_page == 'index.php' ? 'active' : ''; ?>">
                                    <i class="fas fa-home"></i> Home
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="../public/public_dashboard.php" class="nav-link <?php echo $current_page == 'public_dashboard.php' ? 'active' : ''; ?>">
                                    <i class="fas fa-chart-bar"></i> Public Dashboard
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="../auth/login.php" class="nav-link <?php echo $current_page == 'login.php' ? 'active' : ''; ?>">
                                    <i class="fas fa-sign-in-alt"></i> Login
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="../auth/register.php" class="nav-link <?php echo $current_page == 'register.php' ? 'active' : ''; ?>">
                                    <i class="fas fa-user-plus"></i> Register
                                </a>
                            </li>
                            
                        <?php elseif ($current_role == 'citizen'): ?>
                           
                            <li class="nav-item">
                                <a href="../citizen/dashboard.php" class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                                    <i class="fas fa-home"></i> Dashboard
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="../citizen/create_report.php" class="nav-link <?php echo $current_page == 'create_report.php' ? 'active' : ''; ?>">
                                    <i class="fas fa-plus-circle"></i> New Report
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="../citizen/view_reports.php" class="nav-link <?php echo $current_page == 'view_reports.php' ? 'active' : ''; ?>">
                                    <i class="fas fa-list-alt"></i> My Reports
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="../citizen/view_recommendations.php" class="nav-link <?php echo $current_page == 'view_recommendations.php' ? 'active' : ''; ?>">
                                    <i class="fas fa-comment-medical"></i> Recommendations
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="../public/public_dashboard.php" class="nav-link <?php echo $current_page == 'public_dashboard.php' ? 'active' : ''; ?>">
                                    <i class="fas fa-chart-bar"></i> Public Dashboard
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="../public/heatmap.php" class="nav-link <?php echo $current_page == 'heatmap.php' ? 'active' : ''; ?>">
                                    <i class="fas fa-map-marked-alt"></i> Map
                                </a>
                            </li>
                            
                        <?php elseif ($current_role == 'health_worker'): ?>
                           
                            <li class="nav-item">
                                <a href="../health_worker/dashboard.php" class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                                    <i class="fas fa-home"></i> Dashboard
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="../health_worker/view_reports.php" class="nav-link <?php echo $current_page == 'view_reports.php' ? 'active' : ''; ?>">
                                    <i class="fas fa-clipboard-list"></i> Reports Queue
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="../health_worker/create_recommendation.php" class="nav-link <?php echo $current_page == 'create_recommendation.php' ? 'active' : ''; ?>">
                                    <i class="fas fa-comment-medical"></i> Create Recommendations
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="../health_worker/send_to_admin.php" class="nav-link <?php echo $current_page == 'send_to_admin.php' ? 'active' : ''; ?>">
                                    <i class="fas fa-paper-plane"></i> Send to Admin
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="../health_worker/outbreak_tracking.php" class="nav-link <?php echo $current_page == 'outbreak_tracking.php' ? 'active' : ''; ?>">
                                    <i class="fas fa-exclamation-triangle"></i> Outbreaks
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="../public/heatmap.php" class="nav-link <?php echo $current_page == 'heatmap.php' ? 'active' : ''; ?>">
                                    <i class="fas fa-map-marked-alt"></i> Map
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="../public/public_dashboard.php" class="nav-link <?php echo $current_page == 'public_dashboard.php' ? 'active' : ''; ?>">
                                    <i class="fas fa-chart-bar"></i> Public Dashboard
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="../public/heatmap.php" class="nav-link <?php echo $current_page == 'heatmap.php' ? 'active' : ''; ?>">
                                    <i class="fas fa-map-marked-alt"></i> Map
                                </a>
                            </li>
                            
                        <?php elseif ($current_role == 'admin'): ?>
                           
                            <li class="nav-item">
                                <a href="../admin/dashboard.php" class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                                    <i class="fas fa-home"></i> Dashboard
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="../admin/view_analyses.php" class="nav-link <?php echo $current_page == 'view_analyses.php' ? 'active' : ''; ?>">
                                    <i class="fas fa-search"></i> View Analyses
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="../admin/create_visualization.php" class="nav-link <?php echo $current_page == 'create_visualization.php' ? 'active' : ''; ?>">
                                    <i class="fas fa-chart-pie"></i> Create Visualizations
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="../admin/manage_users.php" class="nav-link <?php echo $current_page == 'manage_users.php' ? 'active' : ''; ?>">
                                    <i class="fas fa-users-cog"></i> Manage Users
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="../admin/analytics.php" class="nav-link <?php echo $current_page == 'analytics.php' ? 'active' : ''; ?>">
                                    <i class="fas fa-chart-line"></i> Analytics
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="../admin/sms_management.php" class="nav-link <?php echo $current_page == 'sms_management.php' ? 'active' : ''; ?>">
                                    <i class="fas fa-sms"></i> SMS Management
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="../admin/export_reports.php" class="nav-link <?php echo $current_page == 'export_reports.php' ? 'active' : ''; ?>">
                                    <i class="fas fa-file-export"></i> Export Reports
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="../admin/activity_logs.php" class="nav-link <?php echo $current_page == 'activity_logs.php' ? 'active' : ''; ?>">
                                    <i class="fas fa-history"></i> Activity Logs
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="../api/sms_tables.php" class="nav-link" target="_blank">
                                    <i class="fas fa-database"></i> Setup SMS Tables
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="../api/activity_tables.php" class="nav-link" target="_blank">
                                    <i class="fas fa-database"></i> Setup Activity Tables
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="../public/public_dashboard.php" class="nav-link <?php echo $current_page == 'public_dashboard.php' ? 'active' : ''; ?>">
                                    <i class="fas fa-chart-bar"></i> Public Dashboard
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="../public/heatmap.php" class="nav-link <?php echo $current_page == 'heatmap.php' ? 'active' : ''; ?>">
                                    <i class="fas fa-map-marked-alt"></i> Map
                                </a>
                            </li>
                        <?php endif; ?>
                        
                       
                        <?php if (isLoggedIn()): ?>
                            <li class="nav-item">
                                <a href="../auth/logout.php" class="nav-link">
                                    <i class="fas fa-sign-out-alt"></i> Logout
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul></center>
                </div>
            </nav>
        </div>
    </header>
   
    <div class="breadcrumb">
        <div class="breadcrumb-container">
            <ul class="breadcrumb-list">
                <li class="breadcrumb-item">
                    <a href="<?php echo isLoggedIn() ? getDashboardUrl($current_role) : '../index.php'; ?>">
                        <i class="fas fa-home"></i> Home
                    </a>
                </li>
                <?php
                
                $breadcrumbs = [];
                
                switch ($current_page) {
                    case 'dashboard.php':
                        $breadcrumbs[] = ['name' => 'Dashboard', 'url' => ''];
                        break;
                    case 'create_report.php':
                        $breadcrumbs[] = ['name' => 'Dashboard', 'url' => getDashboardUrl($current_role)];
                        $breadcrumbs[] = ['name' => 'Create Report', 'url' => ''];
                        break;
                    case 'view_reports.php':
                        $breadcrumbs[] = ['name' => 'Dashboard', 'url' => getDashboardUrl($current_role)];
                        $breadcrumbs[] = ['name' => 'View Reports', 'url' => ''];
                        break;
                    case 'view_recommendations.php':
                        $breadcrumbs[] = ['name' => 'Dashboard', 'url' => getDashboardUrl($current_role)];
                        $breadcrumbs[] = ['name' => 'Recommendations', 'url' => ''];
                        break;
                    case 'analyze_report.php':
                        $breadcrumbs[] = ['name' => 'Dashboard', 'url' => getDashboardUrl($current_role)];
                        $breadcrumbs[] = ['name' => 'Reports Queue', 'url' => '../health_worker/view_reports.php'];
                        $breadcrumbs[] = ['name' => 'Analyze Report', 'url' => ''];
                        break;
                    case 'create_recommendation.php':
                        $breadcrumbs[] = ['name' => 'Dashboard', 'url' => getDashboardUrl($current_role)];
                        $breadcrumbs[] = ['name' => 'Create Recommendation', 'url' => ''];
                        break;
                    case 'send_to_admin.php':
                        $breadcrumbs[] = ['name' => 'Dashboard', 'url' => getDashboardUrl($current_role)];
                        $breadcrumbs[] = ['name' => 'Send to Admin', 'url' => ''];
                        break;
                    case 'view_analyses.php':
                        $breadcrumbs[] = ['name' => 'Dashboard', 'url' => getDashboardUrl($current_role)];
                        $breadcrumbs[] = ['name' => 'View Analyses', 'url' => ''];
                        break;
                    case 'create_visualization.php':
                        $breadcrumbs[] = ['name' => 'Dashboard', 'url' => getDashboardUrl($current_role)];
                        $breadcrumbs[] = ['name' => 'Create Visualization', 'url' => ''];
                        break;
                    case 'manage_users.php':
                        $breadcrumbs[] = ['name' => 'Dashboard', 'url' => getDashboardUrl($current_role)];
                        $breadcrumbs[] = ['name' => 'Manage Users', 'url' => ''];
                        break;
                    case 'analytics.php':
                        $breadcrumbs[] = ['name' => 'Dashboard', 'url' => getDashboardUrl($current_role)];
                        $breadcrumbs[] = ['name' => 'Analytics', 'url' => ''];
                        break;
                    case 'public_dashboard.php':
                        $breadcrumbs[] = ['name' => 'Public Dashboard', 'url' => ''];
                        break;
                    case 'login.php':
                        $breadcrumbs[] = ['name' => 'Login', 'url' => ''];
                        break;
                    case 'register.php':
                        $breadcrumbs[] = ['name' => 'Register', 'url' => ''];
                        break;
                }
                
                
                foreach ($breadcrumbs as $index => $crumb) {
                    if ($index > 0) {
                        echo '<li class="breadcrumb-separator"><i class="fas fa-chevron-right"></i></li>';
                    }
                    
                    if ($crumb['url']) {
                        echo '<li class="breadcrumb-item"><a href="' . $crumb['url'] . '">' . htmlspecialchars($crumb['name']) . '</a></li>';
                    } else {
                        echo '<li class="breadcrumb-item active">' . htmlspecialchars($crumb['name']) . '</li>';
                    }
                }
                ?>
            </ul>
        </div>
    </div>
    
   
    <div class="alert-container">
        <?php
        
        if (isset($_SESSION['success_message'])) {
            echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
            unset($_SESSION['success_message']);
        }
        
        if (isset($_SESSION['error_message'])) {
            echo '<div class="alert alert-error">' . htmlspecialchars($_SESSION['error_message']) . '</div>';
            unset($_SESSION['error_message']);
        }
        
        if (isset($_SESSION['warning_message'])) {
            echo '<div class="alert alert-warning">' . htmlspecialchars($_SESSION['warning_message']) . '</div>';
            unset($_SESSION['warning_message']);
        }
        
        if (isset($_SESSION['info_message'])) {
            echo '<div class="alert alert-info">' . htmlspecialchars($_SESSION['info_message']) . '</div>';
            unset($_SESSION['info_message']);
        }
        
       
        if (isset($_GET['success'])) {
            echo '<div class="alert alert-success">' . htmlspecialchars(urldecode($_GET['success'])) . '</div>';
        }
        
        if (isset($_GET['error'])) {
            echo '<div class="alert alert-error">' . htmlspecialchars(urldecode($_GET['error'])) . '</div>';
        }
        
        if (isset($_GET['warning'])) {
            echo '<div class="alert alert-warning">' . htmlspecialchars(urldecode($_GET['warning'])) . '</div>';
        }
        
        if (isset($_GET['info'])) {
            echo '<div class="alert alert-info">' . htmlspecialchars(urldecode($_GET['info'])) . '</div>';
        }
        ?>
    </div>
    
    
    <main class="main-content">
        </main>

        <script>
            
            document.getElementById('mobileMenuToggle').addEventListener('click', function() {
                const navMenu = document.getElementById('navMenu');
                navMenu.classList.toggle('show');
            });

            
            document.querySelectorAll('.nav-link').forEach(link => {
                link.addEventListener('click', function() {
                    document.getElementById('navMenu').classList.remove('show');
                });
            });
        </script>
    </body>
    </html>
      