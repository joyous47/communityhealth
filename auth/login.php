<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/functions.php';
require_once '../config/database.php';

if (isLoggedIn()) {
    $role = getCurrentUserRole();
    switch ($role) {
        case 'citizen':
            header('Location: ../citizen/dashboard.php');
            break;
        case 'health_worker':
            header('Location: ../health_worker/dashboard.php');
            break;
        case 'admin':
            header('Location: ../admin/dashboard.php');
            break;
        default:
            header('Location: ../index.php');
    }
    exit();
}

$error = '';
$email = '';
$success_message = '';
$redirect_url = null;
$show_registration_popup = false;
$show_login_success_popup = false;

if (isset($_GET['success']) && $_GET['success'] === 'registered') {
    $show_registration_popup = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Security token invalid. Please try again.";
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);
        
        if (empty($email) || empty($password)) {
            $error = "Please enter both email and password.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } else {
            list($success_flag, $message, $user) = loginUser($email, $password);
            
            if ($success_flag && $user) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user'] = $user;
                $_SESSION['role'] = $user['role'];
                $_SESSION['last_activity'] = time();
                
                if ($remember) {
                    ini_set('session.gc_maxlifetime', 604800);
                    session_set_cookie_params(604800);
                }
                
                // Set success message based on role
                $role_display = ucfirst(str_replace('_', ' ', $user['role']));
                $success_message = "Welcome back, {$user['username']}! You've successfully logged in as {$role_display}.";
                $show_login_success_popup = true;
                
                // Delay redirect to show popup
                $redirect_url = '../index.php';
            } else {
                $error = $message;
            }
        }
    }
}

if ($redirect_url && !$show_login_success_popup) {
    header('Location: ' . $redirect_url);
    exit();
}

require_once '../includes/header.php';

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Community Surveillance System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: #f0f7ff;
            min-height: 100vh;
            color: #1e293b;
        }

        .container {
            max-width: 1300px;
            margin: 0 auto;
            padding: 30px 20px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Success Popup */
        .temp-popup {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%) translateY(-100px);
            background: #10b981;
            color: white;
            padding: 16px 30px;
            border-radius: 50px;
            box-shadow: 0 10px 30px rgba(16, 185, 129, 0.3);
            display: flex;
            align-items: center;
            gap: 12px;
            z-index: 1000;
            font-weight: 500;
            opacity: 0;
            transition: all 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            min-width: 350px;
            justify-content: center;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .temp-popup.login-success {
            background: #10b981;
        }

        .temp-popup.show {
            transform: translateX(-50%) translateY(0);
            opacity: 1;
        }

        .temp-popup i:first-child {
            font-size: 1.5rem;
        }

        .temp-popup .close-popup {
            cursor: pointer;
            font-size: 1.2rem;
            opacity: 0.8;
            transition: opacity 0.3s;
            margin-left: auto;
        }

        .temp-popup .close-popup:hover {
            opacity: 1;
        }

        /* Header Stats */
        .header-stats {
            display: flex;
            justify-content: flex-end;
            gap: 40px;
            margin-bottom: 30px;
        }

        .stat-item {
            text-align: center;
            background: white;
            padding: 12px 24px;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #1e293b;
        }

        .stat-label {
            font-size: 14px;
            color: #64748b;
        }

        
        main {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px 0;
        }

        .login-wrapper {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            background: white;
            border-radius: 32px;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            max-width: 1100px;
            width: 100%;
        }

        
        .login-container {
            padding: 48px;
            background: white;
        }

        .login-header {
            margin-bottom: 32px;
        }

        .login-header h2 {
            font-size: 36px;
            color: #1e293b;
            margin-bottom: 8px;
            font-weight: 700;
            letter-spacing: -0.02em;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .login-header h2 i {
            color: #2563eb;
        }

        .login-header p {
            color: #64748b;
            font-size: 16px;
        }

        .alert {
            padding: 16px 20px;
            border-radius: 16px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
        }

        .alert-error {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .alert-success {
            background: #dcfce7;
            color: #16a34a;
            border: 1px solid #bbf7d0;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #1e293b;
            font-weight: 600;
            font-size: 14px;
        }

        .form-group label i {
            color: #2563eb;
            width: 20px;
            margin-right: 8px;
        }

        .form-control {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            font-size: 15px;
            transition: all 0.3s;
            background: #f8fafc;
        }

        .form-control:focus {
            outline: none;
            border-color: #2563eb;
            background: white;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        }

        .form-control::placeholder {
            color: #94a3b8;
        }

        .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .remember-me input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #2563eb;
            cursor: pointer;
        }

        .remember-me label {
            color: #475569;
            font-size: 14px;
            cursor: pointer;
        }

        .forgot-password a {
            color: #2563eb;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }

        .forgot-password a:hover {
            text-decoration: underline;
        }

        .btn-login {
            width: 100%;
            padding: 16px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 16px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-login:hover {
            background: #1d4ed8;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(37, 99, 235, 0.3);
        }

        .register-link {
            text-align: center;
            margin-top: 32px;
            padding-top: 24px;
            border-top: 2px solid #e2e8f0;
            color: #64748b;
            font-size: 15px;
        }

        .register-link a {
            color: #2563eb;
            text-decoration: none;
            font-weight: 600;
            margin-left: 5px;
        }

        .register-link a:hover {
            text-decoration: underline;
        }

        
        .info-panel {
            background: #f8fafc;
            padding: 48px 32px;
            display: flex;
            flex-direction: column;
            border-left: 1px solid #e2e8f0;
        }

        .info-header {
            margin-bottom: 32px;
        }

        .info-header h3 {
            font-size: 24px;
            color: #1e293b;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .info-header p {
            color: #64748b;
            font-size: 14px;
        }

        .features-list {
            display: grid;
            gap: 24px;
            margin-bottom: 32px;
        }

        .feature-item {
            display: flex;
            gap: 16px;
            align-items: flex-start;
        }

        .feature-icon {
            width: 48px;
            height: 48px;
            background: white;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: #2563eb;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .feature-text h4 {
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 4px;
        }

        .feature-text p {
            font-size: 14px;
            color: #64748b;
        }

        .demo-box {
            background: white;
            border-radius: 20px;
            padding: 24px;
            margin: 20px 0;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .demo-box h4 {
            font-size: 18px;
            color: #1e293b;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .demo-box h4 i {
            color: #2563eb;
        }

        .demo-credentials {
            display: grid;
            gap: 12px;
        }

        .demo-item {
            background: #f8fafc;
            border-radius: 12px;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .demo-item i {
            color: #2563eb;
            font-size: 18px;
        }

        .demo-item-content {
            flex: 1;
        }

        .demo-role {
            font-weight: 600;
            color: #1e293b;
            font-size: 14px;
            margin-bottom: 4px;
        }

        .demo-credentials-text {
            color: #64748b;
            font-size: 13px;
            font-family: monospace;
        }

        .stats-mini {
            display: flex;
            justify-content: space-around;
            margin-top: auto;
            padding-top: 32px;
            border-top: 2px solid #e2e8f0;
        }

        .stat-mini-item {
            text-align: center;
        }

        .stat-mini-number {
            font-size: 24px;
            font-weight: 700;
            color: #2563eb;
            margin-bottom: 4px;
        }

        .stat-mini-label {
            font-size: 13px;
            color: #64748b;
        }

    
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
            animation: fadeIn 0.3s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background-color: white;
            margin: 15% auto;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            max-width: 400px;
            overflow: hidden;
            animation: slideIn 0.4s ease-out;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-100px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            background: #2563eb;
            color: white;
            padding: 25px 30px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .modal-header i {
            font-size: 2rem;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
        }

        .modal-body {
            padding: 30px;
            text-align: center;
        }

        .modal-body i {
            font-size: 4rem;
            color: #2563eb;
            margin-bottom: 20px;
        }

        .modal-body p {
            color: #1e293b;
            font-size: 1.1rem;
            line-height: 1.6;
        }

        .modal-footer {
            padding: 20px 30px;
            text-align: center;
            border-top: 2px solid #e2e8f0;
        }

        .modal-btn {
            background: #2563eb;
            color: white;
            border: none;
            padding: 14px 40px;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .modal-btn:hover {
            background: #1d4ed8;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(37, 99, 235, 0.3);
        }

        .close-modal {
            position: absolute;
            right: 25px;
            top: 20px;
            color: white;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            opacity: 0.8;
            transition: opacity 0.3s;
        }

        .close-modal:hover {
            opacity: 1;
        }

        
        @media (max-width: 968px) {
            .login-wrapper {
                grid-template-columns: 1fr;
            }
            
            .header-stats {
                justify-content: center;
            }
            
            .info-panel {
                border-left: none;
                border-top: 1px solid #e2e8f0;
            }
        }

        @media (max-width: 576px) {
            .login-container {
                padding: 32px 24px;
            }
            
            .info-panel {
                padding: 32px 24px;
            }
            
            .header-stats {
                flex-wrap: wrap;
                gap: 15px;
            }
            
            .stat-item {
                padding: 10px 18px;
            }
            
            .login-header h2 {
                font-size: 28px;
            }
        }
    </style>
</head>
<body>
    <!-- Success Popup for Login -->
    <div id="successPopup" class="temp-popup login-success">
        <i class="fas fa-check-circle"></i>
        <span class="message" id="popupMessage">Welcome back! You've successfully logged in.</span>
        <i class="fas fa-times close-popup" onclick="hidePopup()"></i>
    </div>

    <div class="container">
        <main>
            <div class="login-wrapper">
                
                <div class="login-container">
                    <div class="login-header">
                        <h2>
                            <i class="fas fa-sign-in-alt"></i>
                            Welcome Back
                        </h2>
                        <p>Access your community surveillance dashboard</p>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" id="loginForm">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        
                        <div class="form-group">
                            <label for="email">
                                <i class="fas fa-envelope"></i>
                                Email Address
                            </label>
                            <input type="email" 
                                   id="email" 
                                   name="email" 
                                   class="form-control" 
                                   value="<?php echo htmlspecialchars($email); ?>" 
                                   required
                                   autocomplete="email"
                                   autofocus
                                   placeholder="you@example.com">
                        </div>
                        
                        <div class="form-group">
                            <label for="password">
                                <i class="fas fa-lock"></i>
                                Password
                            </label>
                            <input type="password" 
                                   id="password" 
                                   name="password" 
                                   class="form-control" 
                                   required
                                   autocomplete="current-password"
                                   placeholder="Enter your password">
                        </div>
                        
                        <div class="remember-forgot">
                            <div class="remember-me">
                                <input type="checkbox" id="remember" name="remember">
                                <label for="remember">Remember me</label>
                            </div>
                            <div class="forgot-password">
                                <a href="#">Forgot password?</a>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn-login">
                                <i class="fas fa-sign-in-alt"></i>
                                Sign In
                            </button>
                        </div>
                    </form>
                    
                    <div class="register-link">
                        Don't have an account? 
                        <a href="register.php">
                            <i class="fas fa-user-plus"></i>
                            Create Account
                        </a>
                    </div>
                </div>

                
                <div class="info-panel">
                    <div class="info-header">
                        <h3>Community Surveillance</h3>
                        <p>Track, monitor, and respond to health concerns together</p>
                    </div>

                    <div class="features-list">
                        <div class="feature-item">
                            <div class="feature-icon">
                                <i class="fas fa-shield-virus"></i>
                            </div>
                            <div class="feature-text">
                                <h4>Real-time Monitoring</h4>
                                <p>Track disease outbreaks as they happen</p>
                            </div>
                        </div>

                        <div class="feature-item">
                            <div class="feature-icon">
                                <i class="fas fa-bell"></i>
                            </div>
                            <div class="feature-text">
                                <h4>Instant Alerts</h4>
                                <p>Get notified about health concerns</p>
                            </div>
                        </div>

                        <div class="feature-item">
                            <div class="feature-icon">
                                <i class="fas fa-chart-bar"></i>
                            </div>
                            <div class="feature-text">
                                <h4>Data Insights</h4>
                                <p>Make informed decisions with analytics</p>
                            </div>
                        </div>
                    </div>

                    <div class="demo-box">
                        <h4>
                            <i class="fas fa-vial"></i>
                            Demo Accounts
                        </h4>
                        <div class="demo-credentials">
                            <div class="demo-item">
                                <i class="fas fa-user"></i>
                                <div class="demo-item-content">
                                    <div class="demo-role">Citizen</div>
                                    <div class="demo-credentials-text">citizen@test.com / password123</div>
                                </div>
                            </div>
                            <div class="demo-item">
                                <i class="fas fa-user-md"></i>
                                <div class="demo-item-content">
                                    <div class="demo-role">Health Worker</div>
                                    <div class="demo-credentials-text">health@test.com / password123</div>
                                </div>
                            </div>
                            <div class="demo-item">
                                <i class="fas fa-user-cog"></i>
                                <div class="demo-item-content">
                                    <div class="demo-role">Administrator</div>
                                    <div class="demo-credentials-text">admin@test.com / password123</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="stats-mini">
                        <div class="stat-mini-item">
                            <div class="stat-mini-number">10K+</div>
                            <div class="stat-mini-label">Reports</div>
                        </div>
                        <div class="stat-mini-item">
                            <div class="stat-mini-number">98%</div>
                            <div class="stat-mini-label">Response</div>
                        </div>
                        <div class="stat-mini-item">
                            <div class="stat-mini-number">24/7</div>
                            <div class="stat-mini-label">Monitoring</div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    
    <div id="registrationPopup" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closePopup()">&times;</span>
            <div class="modal-header">
                <i class="fas fa-check-circle"></i>
                <h3>Welcome!</h3>
            </div>
            <div class="modal-body">
                <i class="fas fa-party-horn"></i>
                <p>Your account has been created successfully!<br>Please login to continue.</p>
            </div>
            <div class="modal-footer">
                <button class="modal-btn" onclick="closePopup()">
                    <i class="fas fa-sign-in-alt"></i> Login Now
                </button>
            </div>
        </div>
    </div>
    
    <script>
        <?php if ($show_registration_popup): ?>
        window.onload = function() {
            showRegistrationPopup();
        };
        <?php endif; ?>

        <?php if ($show_login_success_popup): ?>
        window.onload = function() {
            showPopup('<?php echo addslashes($success_message); ?>');
            
            // Redirect after popup
            setTimeout(function() {
                window.location.href = '<?php echo $redirect_url; ?>';
            }, 2000);
        };
        <?php endif; ?>

        function showPopup(message) {
            const popup = document.getElementById('successPopup');
            const messageSpan = document.getElementById('popupMessage');
            
            if (message) {
                messageSpan.textContent = message;
            }
            
            popup.classList.add('show');
            
            // Don't auto-hide if we're redirecting
            <?php if (!$show_login_success_popup): ?>
            setTimeout(function() {
                hidePopup();
            }, 3000);
            <?php endif; ?>
        }

        function hidePopup() {
            const popup = document.getElementById('successPopup');
            popup.classList.remove('show');
        }

        function showRegistrationPopup() {
            document.getElementById('registrationPopup').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closePopup() {
            document.getElementById('registrationPopup').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        
        window.onclick = function(event) {
            const modal = document.getElementById('registrationPopup');
            if (event.target === modal) {
                closePopup();
            }
        }

    
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            
            if (!email || !password) {
                e.preventDefault();
                alert('Please fill in all fields!');
                return false;
            }
            
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                e.preventDefault();
                alert('Please enter a valid email address!');
                return false;
            }
            
            return true;
        });
        
        document.addEventListener('DOMContentLoaded', function() {
            const emailField = document.getElementById('email');
            if (emailField && !emailField.value) {
                emailField.focus();
            }
        });
    </script>
   <?php  require_once '../includes/footer.php'; ?>
</body>
</html>