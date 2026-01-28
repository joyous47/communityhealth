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

if (isset($_GET['success']) && $_GET['success'] === 'registered') {
    $success_message = "Registration successful! Please login with your credentials.";
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
                
                switch ($user['role']) {
                    case 'citizen':
                        $redirect_url = '../citizen/dashboard.php';
                        break;
                    case 'health_worker':
                        $redirect_url = '../health_worker/dashboard.php';
                        break;
                    case 'admin':
                        $redirect_url = '../admin/dashboard.php';
                        break;
                    default:
                        $redirect_url = '../index.php';
                }
            } else {
                $error = $message;
            }
        }
    }
}

if ($redirect_url) {
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
    <title>Login</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #f8fbff 0%, #e8f4ff 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .container {
            flex: 1;
            display: flex;
            flex-direction: column;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        header {
            text-align: center;
            padding: 40px 20px;
        }
        
        header h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 2.5rem;
        }
        
        .tagline {
            color: #666;
            font-size: 1.1rem;
            margin-bottom: 10px;
        }
        
        main {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px 0;
        }
        
        .login-container {
            background-color: white;
            padding: 50px 40px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 119, 204, 0.15);
            max-width: 450px;
            width: 100%;
            border: 1px solid #e0e0e0;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .login-header h2 {
            color: #333;
            margin-bottom: 15px;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }
        
        .login-header h2 i {
            color: #0077cc;
        }
        
        .login-header p {
            color: #666;
            font-size: 1rem;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 0.95rem;
        }
        
        .form-control {
            width: 100%;
            padding: 14px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s;
            box-sizing: border-box;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #0077cc;
            box-shadow: 0 0 0 3px rgba(0, 119, 204, 0.2);
        }
        
        .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        
        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .remember-me input {
            margin: 0;
            accent-color: #0077cc;
        }
        
        .remember-me label {
            margin: 0;
            color: #333;
            font-size: 0.9rem;
            font-weight: normal;
        }
        
        .forgot-password a {
            color: #0077cc;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .forgot-password a:hover {
            text-decoration: underline;
            color: #005599;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .alert-success {
            background-color: #e8f5e8;
            color: #2e7d32;
            border-left: 4px solid #4caf50;
        }
        
        .alert-error {
            background-color: #ffebee;
            color: #d32f2f;
            border-left: 4px solid #d32f2f;
        }
        
        .alert i {
            font-size: 1.2rem;
        }
        
        .btn-login {
            width: 100%;
            padding: 16px;
            background-color: #0077cc;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-login:hover {
            background-color: #005599;
            transform: translateY(-2px);
        }
        
        .register-link {
            text-align: center;
            margin-top: 30px;
            padding-top: 25px;
            border-top: 1px solid #eee;
            color: #666;
        }
        
        .register-link a {
            color: #0077cc;
            text-decoration: none;
            font-weight: 600;
        }
        
        .register-link a:hover {
            text-decoration: underline;
            color: #005599;
        }
        
        .demo-credentials {
            margin-top: 35px;
            padding: 20px;
            background-color: #f8fbff;
            border-radius: 10px;
            border-left: 4px solid #0077cc;
        }
        
        .demo-credentials h4 {
            color: #333;
            margin-bottom: 15px;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .demo-credentials h4 i {
            color: #0077cc;
        }
        
        .demo-credentials p {
            color: #666;
            font-size: 0.9rem;
            margin: 8px 0;
            padding-left: 25px;
            position: relative;
        }
        
        .demo-credentials p strong {
            color: #333;
            font-weight: 600;
        }
        
        .demo-credentials p::before {
            content: "•";
            color: #0077cc;
            position: absolute;
            left: 10px;
        }
        
        footer {
            margin-top: 40px;
            padding: 25px 0;
            text-align: center;
            color: #666;
            border-top: 1px solid #e8f4ff;
        }
        
        .footer-bottom p {
            margin: 5px 0;
            font-size: 0.9rem;
        }
        
        .footer-bottom p:first-child {
            font-weight: 600;
            color: #333;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .login-container {
                padding: 30px 25px;
            }
            
            header h1 {
                font-size: 2rem;
            }
            
            .tagline {
                font-size: 1rem;
            }
            
            .remember-forgot {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .forgot-password {
                width: 100%;
                text-align: right;
            }
        }
        
        @media (max-width: 480px) {
            .login-container {
                padding: 25px 20px;
            }
            
            header {
                padding: 30px 15px;
            }
            
            header h1 {
                font-size: 1.8rem;
            }
            
            .login-header h2 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1><i class="fas fa-shield-virus"></i>Community Health Monitoring System</h1>
            <p class="tagline">Secure Login Portal</p>
        </header>
        
        <main>
            <div class="login-container">
                <div class="login-header">
                    <h2><i class="fas fa-sign-in-alt"></i> Login to Your Account</h2>
                    <p>Access your personalized dashboard</p>
                </div>
                
                <?php if ($success_message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" id="loginForm">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               class="form-control" 
                               value="<?php echo htmlspecialchars($email); ?>" 
                               required
                               autocomplete="email"
                               autofocus
                               placeholder="Enter your email address">
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
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
                            <i class="fas fa-sign-in-alt"></i> Login
                        </button>
                    </div>
                </form>
                
                <div class="register-link">
                    Don't have an account? <a href="register.php">Register here</a>
                </div>
                
                <div class="demo-credentials">
                    <h4><i class="fas fa-vial"></i> Demo Accounts (Create via Registration First):</h4>
                    <p><strong>Citizen:</strong> citizen@test.com / password123</p>
                    <p><strong>Health Worker:</strong> health@test.com / password123</p>
                    <p><strong>Admin:</strong> admin@test.com / password123</p>
                </div>
            </div>
        </main>
        
        <footer>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> Community Health Monitoring System</p>
                <p>Secure Login | Session Protection Enabled</p>
            </div>
        </footer>
    </div>
    
    <script>
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
</body>
</html>