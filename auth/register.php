<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/functions.php';
require_once '../config/database.php';

$error = '';
$success = '';
$username = '';
$email = '';
$role = 'citizen';
$redirect_url = null;
$show_success_popup = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Security token invalid. Please try again.";
    } else {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $role = $_POST['role'] ?? 'citizen';
        
        if (empty($username) || empty($email) || empty($password)) {
            $error = "All fields are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } elseif (strlen($password) < 6) {
            $error = "Password must be at least 6 characters long.";
        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match.";
        } elseif (!in_array($role, ['citizen', 'health_worker', 'admin'])) {
            $error = "Invalid role selected.";
        } else {
            list($success_flag, $message, $user_id) = registerUser($username, $email, $password, $role);
            
            if ($success_flag) {
                $show_success_popup = true;
                $success = "Registration successful! Redirecting to login...";
                
                $username = '';
                $email = '';
                $role = 'citizen';
            } else {
                $error = $message;
            }
        }
    }
}

require_once '../includes/header.php';

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Community Surveillance System</title>
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
            background: white;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        
        .site-header {
            background: white;
            padding: 20px 40px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            border-bottom: 1px solid #e0e0e0;
        }

        .header-container {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo i {
            font-size: 32px;
            color: #2563eb;
        }

        .logo-text h1 {
            font-size: 24px;
            color: #1e293b;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .logo-text p {
            font-size: 14px;
            color: #64748b;
        }

        .nav-menu {
            display: flex;
            gap: 30px;
        }

        .nav-menu a {
            color: #1e293b;
            text-decoration: none;
            font-weight: 500;
            font-size: 16px;
            padding: 8px 0;
            border-bottom: 2px solid transparent;
            transition: all 0.3s;
        }

        .nav-menu a:hover {
            color: #2563eb;
            border-bottom-color: #2563eb;
        }

        .nav-menu a i {
            margin-right: 8px;
            color: #2563eb;
        }


        .main-content {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            max-width: 1400px;
            margin: 0 auto;
            width: 100%;
        }

        .register-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            max-width: 1200px;
            width: 100%;
            display: grid;
            grid-template-columns: 1fr 1fr;
            overflow: hidden;
        }

    
        .form-side {
            padding: 60px;
            background: white;
        }

        .form-side h2 {
            font-size: 36px;
            color: #333;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .form-side h2 i {
            color: #2563eb;
            margin-right: 10px;
            font-size: 36px;
        }

        .form-side > p {
            color: #666;
            margin-bottom: 30px;
            font-size: 18px;
        }

        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 16px;
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
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 10px;
            color: #333;
            font-weight: 500;
            font-size: 16px;
        }

        .form-group label i {
            color: #2563eb;
            width: 20px;
            margin-right: 8px;
            font-size: 16px;
        }

        .form-control {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .form-control::placeholder {
            color: #999;
            font-size: 15px;
        }

        .form-select {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 16px;
            background: white;
            cursor: pointer;
            color: #333;
        }

        .form-select:focus {
            outline: none;
            border-color: #2563eb;
        }

        .password-requirements {
            font-size: 13px;
            color: #666;
            margin-top: 8px;
            margin-left: 4px;
        }

        .role-description {
            margin-top: 15px;
            padding: 18px;
            background: #f8f9fa;
            border-radius: 12px;
            border-left: 4px solid #2563eb;
            font-size: 15px;
            color: #555;
            line-height: 1.6;
        }

        .role-description strong {
            color: #2563eb;
            display: block;
            margin-bottom: 8px;
            font-size: 16px;
        }

        .btn-register {
            width: 100%;
            padding: 16px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
        }

        .btn-register:hover {
            background: #1d4ed8;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(37, 99, 235, 0.2);
        }

        .login-link {
            text-align: center;
            margin-top: 35px;
            padding-top: 25px;
            border-top: 2px solid #f0f0f0;
            color: #666;
            font-size: 16px;
        }

        .login-link a {
            color: #2563eb;
            text-decoration: none;
            font-weight: 600;
            margin-left: 5px;
            font-size: 16px;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        /* Right Side - Info */
        .info-side {
            background: white;
            padding: 60px;
            color: #333;
            display: flex;
            flex-direction: column;
            border-left: 1px solid #e0e0e0;
        }

        .info-side h3 {
            font-size: 32px;
            margin-bottom: 15px;
            font-weight: 600;
            color: #1e293b;
        }

        .info-side h3 i {
            margin-right: 10px;
            color: #2563eb;
            font-size: 32px;
        }

        .info-side > p {
            color: #64748b;
            margin-bottom: 35px;
            font-size: 18px;
            line-height: 1.6;
        }

        .features {
            display: flex;
            flex-direction: column;
            gap: 30px;
            margin-bottom: 35px;
        }

        .feature {
            display: flex;
            align-items: flex-start;
            gap: 20px;
        }

        .feature i {
            font-size: 28px;
            color: #2563eb;
            background: #e6f0ff;
            padding: 15px;
            border-radius: 14px;
            width: 58px;
            height: 58px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .feature-text h4 {
            font-size: 20px;
            margin-bottom: 8px;
            font-weight: 500;
            color: #1e293b;
        }

        .feature-text p {
            color: #64748b;
            font-size: 16px;
        }

        .demo-accounts {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin: 25px 0;
            border: 1px solid #e0e0e0;
        }

        .demo-accounts h4 {
            font-size: 24px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #1e293b;
        }

        .demo-accounts h4 i {
            color: #2563eb;
            font-size: 24px;
        }

        .demo-item {
            margin-bottom: 18px;
            padding: 15px;
            background: #f8fafc;
            border-radius: 10px;
            border: 1px solid #e0e0e0;
        }

        .demo-role {
            font-weight: 600;
            margin-bottom: 6px;
            color: #2563eb;
            font-size: 16px;
        }

        .demo-credentials {
            font-size: 14px;
            color: #64748b;
            font-family: monospace;
        }

        .stats {
            display: flex;
            justify-content: space-around;
            margin-top: auto;
            padding-top: 35px;
            border-top: 1px solid #e0e0e0;
        }

        .stat {
            text-align: center;
        }

        .stat-number {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
            color: #1e293b;
        }

        .stat-label {
            font-size: 15px;
            color: #64748b;
        }

        
        .site-footer {
            background: white;
            padding: 50px 40px 30px;
            border-top: 1px solid #e0e0e0;
            margin-top: auto;
        }

        .footer-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .footer-content {
            display: grid;
            grid-template-columns: 1.5fr 1fr 1fr 1.2fr;
            gap: 50px;
            margin-bottom: 40px;
        }

        .footer-section h3 {
            color: #1e293b;
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 25px;
        }

        .footer-section p {
            color: #64748b;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .footer-features {
            list-style: none;
        }

        .footer-features li {
            color: #475569;
            font-size: 16px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .footer-features li i {
            color: #2563eb;
            font-size: 16px;
            width: 20px;
        }

        .footer-links {
            list-style: none;
        }

        .footer-links li {
            margin-bottom: 15px;
        }

        .footer-links a {
            color: #64748b;
            text-decoration: none;
            font-size: 16px;
            transition: color 0.3s;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .footer-links a i {
            color: #2563eb;
            font-size: 16px;
            width: 20px;
        }

        .footer-links a:hover {
            color: #2563eb;
        }

        .contact-info {
            list-style: none;
        }

        .contact-info li {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 18px;
            color: #64748b;
            font-size: 16px;
        }

        .contact-info li i {
            color: #2563eb;
            font-size: 18px;
            width: 20px;
        }

        .emergency-support {
            background: #fee2e2;
            color: #dc2626;
            padding: 15px 20px;
            border-radius: 12px;
            margin-top: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 16px;
            font-weight: 600;
        }

        .emergency-support i {
            font-size: 20px;
        }

        .footer-bottom {
            text-align: center;
            padding-top: 30px;
            border-top: 2px solid #f0f0f0;
            color: #94a3b8;
            font-size: 15px;
        }

        .footer-bottom i {
            color: #ef4444;
            margin: 0 3px;
        }

        /* Success Popup */
        .temp-popup {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%) translateY(-100px);
            background: #10b981;
            color: white;
            padding: 18px 35px;
            border-radius: 50px;
            box-shadow: 0 10px 30px rgba(16, 185, 129, 0.3);
            display: flex;
            align-items: center;
            gap: 15px;
            z-index: 1000;
            font-weight: 500;
            opacity: 0;
            transition: all 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            min-width: 400px;
            justify-content: center;
            font-size: 16px;
        }

        .temp-popup.show {
            transform: translateX(-50%) translateY(0);
            opacity: 1;
        }

        .temp-popup i:first-child {
            font-size: 1.8rem;
        }

        .temp-popup .close-popup {
            cursor: pointer;
            font-size: 1.3rem;
            opacity: 0.8;
            transition: opacity 0.3s;
            margin-left: auto;
        }

        .temp-popup .close-popup:hover {
            opacity: 1;
        }

        @media (max-width: 968px) {
            .register-card {
                grid-template-columns: 1fr;
            }
            
            .info-side {
                display: none;
            }
            
            .form-side {
                padding: 40px 30px;
            }
            
            .footer-content {
                grid-template-columns: 1fr;
                gap: 35px;
            }
            
            .nav-menu {
                display: none;
            }
        }
    </style>
</head>
<body>
   
        

    
    <div id="successPopup" class="temp-popup">
        <i class="fas fa-check-circle"></i>
        <span class="message" id="popupMessage">Registration successful! Welcome to Community Surveillance System!</span>
        <i class="fas fa-times close-popup" onclick="hidePopup()"></i>
    </div>

    <div class="main-content">
        <div class="register-card">
            
            <div class="form-side">
                <h2>
                    <i class="fas fa-user-plus"></i>
                    Create Account
                </h2>
                <p>Secure registration in minutes</p>
                
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" id="registerForm">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    
                    <div class="form-group">
                        <label for="username">
                            <i class="fas fa-user"></i>
                            Full Name
                        </label>
                        <input type="text" 
                               id="username" 
                               name="username" 
                               class="form-control" 
                               value="<?php echo htmlspecialchars($username); ?>" 
                               required
                               maxlength="100"
                               autocomplete="username"
                               placeholder="John Doe">
                    </div>
                    
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
                               maxlength="100"
                               autocomplete="email"
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
                               minlength="6"
                               autocomplete="new-password"
                               placeholder="Enter your password">
                        <div class="password-requirements">Minimum 6 characters</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">
                            <i class="fas fa-lock"></i>
                            Confirm Password
                        </label>
                        <input type="password" 
                               id="confirm_password" 
                               name="confirm_password" 
                               class="form-control" 
                               required
                               minlength="6"
                               autocomplete="new-password"
                               placeholder="Confirm your password">
                    </div>
                    
                    <div class="form-group">
                        <label for="role">
                            <i class="fas fa-user-tag"></i>
                            Select Your Role
                        </label>
                        <select id="role" name="role" class="form-select" required>
                            <option value="citizen" <?php echo $role === 'citizen' ? 'selected' : ''; ?>>Citizen</option>
                            <option value="health_worker" <?php echo $role === 'health_worker' ? 'selected' : ''; ?>>Health Worker</option>
                            <option value="admin" <?php echo $role === 'admin' ? 'selected' : ''; ?>>Administrator</option>
                        </select>
                        
                        <div class="role-description" id="citizen-desc" style="display: <?php echo $role === 'citizen' ? 'block' : 'none'; ?>;">
                            <strong><i class="fas fa-user"></i> Citizen</strong>
                            Report diseases, view recommendations, track your reports, and stay informed about health concerns in your community.
                        </div>
                        <div class="role-description" id="health_worker-desc" style="display: <?php echo $role === 'health_worker' ? 'block' : 'none'; ?>;">
                            <strong><i class="fas fa-user-md"></i> Health Worker</strong>
                            Analyze reports, provide recommendations, monitor outbreaks, and help coordinate community health responses.
                        </div>
                        <div class="role-description" id="admin-desc" style="display: <?php echo $role === 'admin' ? 'block' : 'none'; ?>;">
                            <strong><i class="fas fa-user-cog"></i> Administrator</strong>
                            Create visualizations, manage users, view analytics, and oversee the entire community health system.
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-register">
                        <i class="fas fa-user-plus"></i>
                        Create Account
                    </button>
                </form>
                
                <div class="login-link">
                    Already have an account? <a href="login.php">Sign In</a>
                </div>
            </div>

            
            <div class="info-side">
                <h3>
                    <i class="fas fa-shield-alt"></i>
                    Community Surveillance
                </h3>
                <p>Track, monitor, and respond to health concerns together</p>

                <div class="features">
                    <div class="feature">
                        <i class="fas fa-chart-line"></i>
                        <div class="feature-text">
                            <h4>Real-time Monitoring</h4>
                            <p>Track disease outbreaks as they happen</p>
                        </div>
                    </div>

                    <div class="feature">
                        <i class="fas fa-bell"></i>
                        <div class="feature-text">
                            <h4>Instant Alerts</h4>
                            <p>Get notified about health concerns</p>
                        </div>
                    </div>

                    <div class="feature">
                        <i class="fas fa-chart-bar"></i>
                        <div class="feature-text">
                            <h4>Data Insights</h4>
                            <p>Make informed decisions with analytics</p>
                        </div>
                    </div>
                </div>

                <div class="demo-accounts">
                    <h4>
                        <i class="fas fa-flask"></i>
                        Demo Accounts
                    </h4>
                    
                    <div class="demo-item">
                        <div class="demo-role">Citizen</div>
                        <div class="demo-credentials">citizen@test.com / password123</div>
                    </div>
                    
                    <div class="demo-item">
                        <div class="demo-role">Health Worker</div>
                        <div class="demo-credentials">health@test.com / password123</div>
                    </div>
                    
                    <div class="demo-item">
                        <div class="demo-role">Administrator</div>
                        <div class="demo-credentials">admin@test.com / password123</div>
                    </div>
                </div>

                <div class="stats">
                    <div class="stat">
                        <div class="stat-number">10K+</div>
                        <div class="stat-label">Reports</div>
                    </div>
                    <div class="stat">
                        <div class="stat-number">98%</div>
                        <div class="stat-label">Response</div>
                    </div>
                    <div class="stat">
                        <div class="stat-number">24/7</div>
                        <div class="stat-label">Monitoring</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

              
    
    <script>
        <?php if ($show_success_popup): ?>
        document.addEventListener('DOMContentLoaded', function() {
            showPopup('Registration successful! Welcome to Community Surveillance System!');
            
            setTimeout(function() {
                window.location.href = 'login.php?success=registered';
            }, 3000);
        });
        <?php endif; ?>

        function showPopup(message) {
            const popup = document.getElementById('successPopup');
            const messageSpan = document.getElementById('popupMessage');
            
            if (message) {
                messageSpan.textContent = message;
            }
            
            popup.classList.add('show');
        }

        function hidePopup() {
            const popup = document.getElementById('successPopup');
            popup.classList.remove('show');
        }

        document.getElementById('role').addEventListener('change', function() {
            document.querySelectorAll('.role-description').forEach(desc => {
                desc.style.display = 'none';
            });
            
            const selectedDesc = document.getElementById(this.value + '-desc');
            if (selectedDesc) {
                selectedDesc.style.display = 'block';
            }
        });
        
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const username = document.getElementById('username').value;
        
            const usernameRegex = /^[a-zA-Z0-9]{3,100}$/;
            if (!usernameRegex.test(username)) {
                e.preventDefault();
                showErrorPopup('Username must be 3-100 characters and contain only letters and numbers!');
                return false;
            }
            
            if (password !== confirmPassword) {
                e.preventDefault();
                showErrorPopup('Passwords do not match!');
                return false;
            }
            
            if (password.length < 6) {
                e.preventDefault();
                showErrorPopup('Password must be at least 6 characters long!');
                return false;
            }
            
            return true;
        });
        
        function showErrorPopup(message) {
            const popup = document.getElementById('successPopup');
            const messageSpan = document.getElementById('popupMessage');
            
            popup.style.background = '#dc2626';
            popup.querySelector('i:first-child').className = 'fas fa-exclamation-circle';
            
            messageSpan.textContent = message;
            popup.classList.add('show');
            
            setTimeout(function() {
                popup.classList.remove('show');
                
                setTimeout(function() {
                    popup.style.background = '#10b981';
                    popup.querySelector('i:first-child').className = 'fas fa-check-circle';
                }, 500);
            }, 3000);
        }
        
        document.getElementById('username').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^a-zA-Z0-9]/g, '');
        });
    </script>
    <?php require_once '../includes/footer.php'; ?>
</body>
</html>