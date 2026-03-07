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


require_once 'includes/init_translations.php';

$is_logged_in = isset($_SESSION['user_id']);
$user_role = isset($_SESSION['role']) ? htmlspecialchars($_SESSION['role']) : null;

$db_connected = false;
try {
    require_once 'config/database.php';
    $database = new Database();
    $pdo = $database->getConnection();
    if ($pdo) {
        $test_query = $pdo->prepare("SELECT 1");
        $test_query->execute();
        $db_connected = true;
    }
} catch (Exception $e) {
    $db_connected = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Community Health Monitoring & Early Warning System">
    <meta name="keywords" content="community, health, reporting, analytics">
    <title>CHMEWS</title>
    
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8fafc;
            color: #1e293b;
            line-height: 1.6;
            scroll-behavior: smooth;
        }

        .nav-bar {
            background: #ffffff;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 15px 0;
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 1px solid #e2e8f0;
        }

        .nav-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .nav-logo {
            font-size: 1.4rem;
            font-weight: 800;
            color: #0ea5e9;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .nav-logo:hover {
            color: #0284c7;
        }

        .nav-logo i {
            font-size: 1.6rem;
            color: #0ea5e9;
        }

        .user-menu {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .btn-nav {
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            border: none;
        }

        .btn-nav-primary {
            background: #0ea5e9;
            color: white;
        }

        .btn-nav-primary:hover {
            background: #0284c7;
            transform: translateY(-2px);
        }

        .btn-nav-danger {
            background: #ef4444;
            color: white;
        }

        .btn-nav-danger:hover {
            background: #dc2626;
        }

.hero-wrapper {
    position: relative;
    height: 100vh;
    padding: 60px 40px;

    background-image: url('images/lab.jpg');
    background-repeat: no-repeat;
    background-size: cover;
    background-position: center;

    display: flex;
    align-items: center;
    overflow: hidden;
}


.hero-wrapper::before {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(
        135deg,
        rgba(224, 242, 254, 0.75),
        rgba(255, 255, 255, 0.6),
        rgba(240, 249, 255, 0.75)
    );
    z-index: 1;
}


.hero-wrapper > * {
    position: relative;
    z-index: 2;
}

        .hero-content h1 {
            font-size: 4.8rem;
            font-weight: 900;
            margin-bottom: 10px;
            color: #000;
            line-height: 1.2;
        }

        .hero-content .subtitle {
            font-size: 2.2rem;
            margin-bottom: 8px;
            color: #000;
            font-weight: 500;
        }

        .hero-content p {
            font-size: 1.4rem;
            margin-bottom: 25px;
            color: #0c0c0c;
            max-width: 700px;
        }

        .hero-badges {
            display: flex;
            gap: 12px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .badge-item {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            padding: 8px 18px;
            border-radius: 25px;
            font-size: 0.85rem;
            font-weight: 600;
            color: #0ea5e9;
            display: flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }

        .hero-buttons {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .btn-hero {
            padding: 12px 30px;
            font-size: 0.95rem;
            font-weight: 700;
            border: none;
            border-radius: 40px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            min-width: 180px;
        }

        .btn-hero-primary {
            background: #0ea5e9;
            color: white;
            box-shadow: 0 4px 15px rgba(14, 165, 233, 0.3);
        }

        .btn-hero-secondary {
            background: white;
            color: #0ea5e9;
            border: 2px solid #0ea5e9;
        }

        .btn-hero-tertiary {
            background: #1e293b;
            color: white;
        }

        .stats-section {
            background: #ffffff;
            padding: 50px 0 !important;
        }

        .stat-card {
            background: white;
            padding: 24px 16px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            border-top: 4px solid #0ea5e9;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            font-size: 1.8rem;
            margin-bottom: 10px;
            color: #0ea5e9;
        }

        .stat-number {
            font-size: 1.8rem;
            font-weight: 900;
            color: #0f172a;
            margin-bottom: 6px;
        }

        .stat-label {
            color: #64748b;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            font-weight: 600;
        }

        .roles-section {
            background: #f8fafc;
            padding: 0 !important;
        }

        .section-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .section-header h2 {
            font-size: 2.2rem;
            color: #0f172a;
            margin-bottom: 12px;
            font-weight: 900;
        }

        .section-header p {
            font-size: 1rem;
            color: #64748b;
            max-width: 600px;
            margin: 0 auto;
        }

        .role-card {
            background: white;
            border-radius: 12px;
            padding: 32px 24px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            border-top: 5px solid;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .role-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.1);
        }

        .role-card.citizen {
            border-top-color: #0ea5e9;
        }

        .role-card.health-worker {
            border-top-color: #3b82f6;
        }

        .role-card.admin {
            border-top-color: #1e293b;
        }

        .role-card.public {
            border-top-color: #64748b;
        }

        .role-icon {
            font-size: 2.8rem;
            margin-bottom: 15px;
            color: #0ea5e9;
        }

        .role-card h3 {
            font-size: 1.4rem;
            color: #0f172a;
            margin-bottom: 10px;
            font-weight: 800;
        }

        .role-card p {
            color: #64748b;
            margin-bottom: 18px;
            font-size: 0.9rem;
        }

        .role-features {
            list-style: none;
            margin: 18px 0;
            text-align: left;
            flex-grow: 1;
        }

        .role-features li {
            color: #475569;
            margin-bottom: 9px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9rem;
        }

        .role-features li::before {
            content: '✓';
            color: #0ea5e9;
            font-weight: bold;
            font-size: 1.1em;
        }

        .btn-role {
            background: #0ea5e9;
            color: white;
            padding: 10px 26px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 700;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            margin-top: 14px;
            font-size: 0.95rem;
        }

        .btn-role:hover {
            background: #0284c7;
            color: white;
        }

        .status-section {
            max-width: 100%;
            margin: 50px 0;
            padding: 30px 40px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }

        .status-header {
            font-size: 1.1rem;
            color: #0f172a;
            margin-bottom: 12px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .status-text {
            color: #475569;
            font-size: 0.95rem;
        }

        footer {
            background: #0f172a;
            color: white;
            padding: 50px 40px 25px;
            margin-top: 60px;
            text-align: center;
        }

        footer p {
            margin: 8px 0;
            font-size: 0.9rem;
        }

        footer .copyright {
            color: #cbd5e1;
        }

        footer .update-time {
            color: #94a3b8;
            font-size: 0.8rem;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid #334155;
        }

        .quick-nav {
            display: flex;
            gap: 15px;
            margin: 20px 0;
            flex-wrap: wrap;
        }

        .quick-nav a {
            padding: 10px 22px;
            background: white;
            color: #0ea5e9;
            text-decoration: none;
            border-radius: 25px;
            transition: all 0.3s ease;
            font-weight: 700;
            border: 2px solid #0ea5e9;
            font-size: 0.9rem;
        }

        .quick-nav a:hover {
            background: #0ea5e9;
            color: white;
        }

        @media (max-width: 1024px) {
            .nav-content {
                padding: 0 25px;
            }
            .hero-wrapper {
                padding: 50px 25px;
            }
            .hero-content h1 {
                font-size: 2.2rem;
            }
            .section-header h2 {
                font-size: 1.7rem;
            }
            .stats-section,
            .roles-section,
            .status-section {
                padding: 0 25px;
            }
        }

        @media (max-width: 768px) {
            .nav-content {
                flex-direction: column;
                gap: 12px;
                padding: 0 15px;
            }
            .hero-wrapper {
                padding: 40px 15px;
            }
            .hero-content h1 {
                font-size: 1.8rem;
            }
            .hero-buttons {
                gap: 10px;
            }
            .btn-hero {
                padding: 10px 24px;
                font-size: 0.9rem;
                min-width: auto;
            }
            .roles-grid {
                grid-template-columns: 1fr;
                gap: 18px;
            }
            .status-section {
                padding: 0 15px;
                margin: 40px auto;
            }
            footer {
                padding: 40px 15px 20px;
            }
        }

        @media (max-width: 480px) {
            .hero-content h1 {
                font-size: 1.5rem;
            }
            .hero-buttons {
                flex-direction: column;
                align-items: stretch;
            }
            .btn-hero {
                width: 100%;
            }
            .quick-nav {
                flex-direction: column;
                align-items: stretch;
            }
            .quick-nav a {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <?php if ($is_logged_in): ?>
    <nav class="nav-bar">
        <div class="nav-content">
            <a href="index.php" class="nav-logo">
                <i class="fas fa-hospital"></i>
                <span><?php echo t('system_title'); ?></span>
            </a>
            <div class="user-menu">
                <?php if ($user_role === 'citizen'): ?>
                    <a href="citizen/dashboard.php" class="btn-nav btn-nav-primary"><?php echo t('go_to_dashboard'); ?></a>
                <?php elseif ($user_role === 'health_worker'): ?>
                    <a href="health_worker/dashboard.php" class="btn-nav btn-nav-primary"><?php echo t('go_to_dashboard'); ?></a>
                <?php elseif ($user_role === 'admin'): ?>
                    <a href="admin/dashboard.php" class="btn-nav btn-nav-primary"><?php echo t('go_to_dashboard'); ?></a>
                <?php endif; ?>
                <a href="auth/logout.php" class="btn-nav btn-nav-danger"><?php echo t('logout'); ?></a>
            </div>
        </div>
    </nav>
    <?php endif; ?>

    <div class="hero-wrapper">
        <div class="hero-content">
            <h1><?php echo t('system_title'); ?></h1>
            <p class="subtitle"><?php echo t('system_subtitle'); ?></p>
            <p><?php echo t('monitor_analyze'); ?></p>
            
            <div class="hero-badges">
                <div class="badge-item">
                    <i class="fas fa-bolt"></i>
                    <span><?php echo t('real_time_updates'); ?></span>
                </div>
                <div class="badge-item">
                    <i class="fas fa-chart-line"></i>
                    <span><?php echo t('advanced_analytics'); ?></span>
                </div>
                <div class="badge-item">
                    <i class="fas fa-lock"></i>
                    <span><?php echo t('bank_grade_security'); ?></span>
                </div>
                <div class="badge-item">
                    <i class="fas fa-mobile-alt"></i>
                    <span><?php echo t('mobile_responsive'); ?></span>
                </div>
            </div>

          
            <div class="language-switcher" style="display: flex; justify-content: center; gap: 10px; margin: 20px 0;">
                <?php $currentLang = $_SESSION['user_lang'] ?? $_COOKIE['user_lang'] ?? 'en'; ?>
                <a href="?lang=en" class="lang-btn <?php echo $currentLang === 'en' ? 'active' : ''; ?>" 
                   style="padding: 8px 16px; border-radius: 4px; text-decoration: none; color: #0ea5e9; background: <?php echo $currentLang === 'en' ? '#0ea5e9' : 'transparent'; ?>; font-size: 0.9rem; border: 2px solid #0ea5e9;">
                    <strong>EN</strong>
                </a>
                <a href="?lang=sw" class="lang-btn <?php echo $currentLang === 'sw' ? 'active' : ''; ?>"
                   style="padding: 8px 16px; border-radius: 4px; text-decoration: none; color: #0ea5e9; background: <?php echo $currentLang === 'sw' ? '#0ea5e9' : 'transparent'; ?>; font-size: 0.9rem; border: 2px solid #0ea5e9;">
                    <strong>SW</strong>
                </a>
            </div>

            <?php if (!$is_logged_in): ?>
            <div class="hero-buttons">
                <a href="auth/login.php" class="btn-hero btn-hero-primary">
                    <i class="fas fa-sign-in-alt"></i>
                    <span><?php echo t('login_now'); ?></span>
                </a>
                <a href="auth/register.php" class="btn-hero btn-hero-secondary">
                    <i class="fas fa-user-plus"></i>
                    <span><?php echo t('create_account'); ?></span>
                </a>
                <a href="public/public_dashboard.php" class="btn-hero btn-hero-tertiary">
                    <i class="fas fa-chart-bar"></i>
                    <span><?php echo t('public_dashboard'); ?></span>
                </a>
            </div>
            <?php else: ?>
            <div class="quick-nav">
                <?php if ($user_role === 'citizen'): ?>
                    <a href="citizen/create_report.php"><i class="fas fa-file-medical"></i> <?php echo t('create_report'); ?></a>
                    <a href="citizen/view_reports.php"><i class="fas fa-list"></i> <?php echo t('my_reports'); ?></a>
                    <a href="citizen/view_recommendations.php"><i class="fas fa-lightbulb"></i> <?php echo t('view_recommendations'); ?></a>
                <?php elseif ($user_role === 'health_worker'): ?>
                    <a href="health_worker/view_reports.php"><i class="fas fa-inbox"></i> Reports Queue</a>
                    <a href="health_worker/analyze_report.php"><i class="fas fa-stethoscope"></i> Analyze</a>
                    <a href="health_worker/create_recommendation.php"><i class="fas fa-pen"></i> Recommendations</a>
                <?php elseif ($user_role === 'admin'): ?>
                    <a href="admin/view_analyses.php"><i class="fas fa-folder-open"></i> Analyses</a>
                    <a href="admin/analytics.php"><i class="fas fa-analytics"></i> Analytics</a>
                    <a href="admin/manage_users.php"><i class="fas fa-users"></i> Manage Users</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$is_logged_in): ?>
    <section class="stats-section">
        <div class="container-fluid px-4 py-5">
            <div class="row g-3">
                <div class="col-lg-2 col-md-4 col-sm-6">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-users-cog"></i></div>
                        <div class="stat-number">3</div>
                        <div class="stat-label">User Roles</div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-tachometer-alt"></i></div>
                        <div class="stat-number"><?php echo t('real_time'); ?></div>
                        <div class="stat-label"><?php echo t('data_processing'); ?></div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-shield-alt"></i></div>
                        <div class="stat-number">100%</div>
                        <div class="stat-label"><?php echo t('encrypted_security'); ?></div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-clock"></i></div>
                        <div class="stat-number">24/7</div>
                        <div class="stat-label"><?php echo t('monitoring'); ?></div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                        <div class="stat-number">AI</div>
                        <div class="stat-label"><?php echo t('analytics'); ?></div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-globe"></i></div>
                        <div class="stat-number"><?php echo t('global'); ?></div>
                        <div class="stat-label"><?php echo t('coverage'); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if (!$is_logged_in): ?>
    <section class="roles-section">
        <div class="container-fluid px-4 py-5">
            <div class="section-header mb-5">
                <h2><?php echo t('how_it_works'); ?></h2>
                <p><?php echo t('choose_role_desc'); ?></p>
            </div>

            <div class="row g-4">
                <div class="col-lg-6 col-xl-3">
                    <div class="role-card citizen h-100">
                        <div class="role-icon"><i class="fas fa-user-md"></i></div>
                        <h3><?php echo t('citizens'); ?></h3>
                        <p><?php echo t('citizen_desc'); ?></p>
                        <ul class="role-features">
                            <li><?php echo t('submit_reports'); ?></li>
                            <li><?php echo t('track_status'); ?></li>
                            <li><?php echo t('receive_recommendations'); ?></li>
                            <li><?php echo t('view_statistics'); ?></li>
                        </ul>
                        <a href="auth/register.php?role=citizen" class="btn-role"><?php echo t('join_as_citizen'); ?></a>
                    </div>
                </div>

                <div class="col-lg-6 col-xl-3">
                    <div class="role-card health-worker h-100">
                        <div class="role-icon"><i class="fas fa-stethoscope"></i></div>
                        <h3><?php echo t('health_workers'); ?></h3>
                        <p><?php echo t('health_worker_desc'); ?></p>
                        <ul class="role-features">
                            <li><?php echo t('review_reports'); ?></li>
                            <li><?php echo t('medical_analysis'); ?></li>
                            <li><?php echo t('create_recommendations'); ?></li>
                            <li><?php echo t('escalate'); ?></li>
                        </ul>
                        <a href="auth/register.php?role=health_worker" class="btn-role"><?php echo t('join_as_health_worker'); ?></a>
                    </div>
                </div>

                <div class="col-lg-6 col-xl-3">
                    <div class="role-card admin h-100">
                        <div class="role-icon"><i class="fas fa-crown"></i></div>
                        <h3><?php echo t('administrators'); ?></h3>
                        <p><?php echo t('admin_desc'); ?></p>
                        <ul class="role-features">
                            <li><?php echo t('view_analyses'); ?></li>
                            <li><?php echo t('create_visualizations'); ?></li>
                            <li><?php echo t('access_analytics'); ?></li>
                            <li><?php echo t('manage_accounts'); ?></li>
                        </ul>
                        <a href="auth/login.php" class="btn-role"><?php echo t('admin_login'); ?></a>
                    </div>
                </div>

                <div class="col-lg-6 col-xl-3">
                    <div class="role-card public h-100">
                        <div class="role-icon"><i class="fas fa-globe"></i></div>
                        <h3><?php echo t('public_access'); ?></h3>
                        <p><?php echo t('public_desc'); ?></p>
                        <ul class="role-features">
                            <li><?php echo t('no_registration'); ?></li>
                            <li><?php echo t('live_statistics'); ?></li>
                            <li><?php echo t('track_trends'); ?></li>
                            <li><?php echo t('export_data'); ?></li>
                        </ul>
                        <a href="public/public_dashboard.php" class="btn-role"><?php echo t('access_dashboard'); ?></a>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <section class="status-section">
        <div class="status-header">
            <span><?php echo t('system_status'); ?></span>
        </div>
        <div class="status-text">
            Database Connection: <strong><?php echo $db_connected ? t('online') : t('connection_error'); ?></strong>
        </div>
        <?php if ($is_logged_in): ?>
            <div class="status-text" style="margin-top: 12px; color: #0ea5e9;">
                <i class="fas fa-user-circle"></i> <?php echo t('logged_in_as'); ?> <strong><?php echo ucfirst(htmlspecialchars($user_role)); ?></strong>
            </div>
        <?php endif; ?>
    </section>

    <footer>
        <div style="max-width: 1400px; margin: 0 auto;">
            <p class="copyright">
                <i class="fas fa-copyright"></i> <?php echo t('copyright'); ?>
            </p>
            <p><?php echo t('advanced_platform'); ?></p>
            <p class="update-time">
                <i class="fas fa-clock"></i> <?php echo t('last_updated'); ?>: <?php echo date('F d, Y \a\t H:i A'); ?>
            </p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/validation.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, {threshold: 0.1});

        document.querySelectorAll('.role-card, .stat-card').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(el);
        });
    </script>
</body>
</html>