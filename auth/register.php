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
                $redirect_url = '../auth/login.php?success=registered';
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
    <title>Register </title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .register-container {
            max-width: 500px;
            margin: 40px auto;
            padding: 40px;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            border: 2px solid #339af0;
        }
        
        .register-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .register-header h2 {
            color: #000000;
            margin-bottom: 10px;
        }
        
        .register-header p {
            color: #666666;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #000000;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #339af0;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
            color: #000000;
            background-color: white;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #228be6;
            box-shadow: 0 0 0 3px rgba(51, 154, 240, 0.1);
        }
        
        .form-select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #339af0;
            border-radius: 5px;
            font-size: 1rem;
            background-color: white;
            cursor: pointer;
            color: #000000;
        }
        
        .form-select:focus {
            outline: none;
            border-color: #228be6;
            box-shadow: 0 0 0 3px rgba(51, 154, 240, 0.1);
        }
        
        .role-description {
            font-size: 0.9rem;
            color: #666666;
            margin-top: 5px;
            padding: 10px;
            background-color: white;
            border-radius: 5px;
            border: 2px solid #339af0;
        }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 2px solid;
        }
        
        .alert-success {
            background-color: white;
            color: #000000;
            border-color: #51cf66;
        }
        
        .alert-error {
            background-color: white;
            color: #000000;
            border-color: #ff6b6b;
        }
        
        .btn-register {
            width: 100%;
            padding: 14px;
            background-color: #339af0;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .btn-register:hover {
            background-color: #228be6;
        }
        
        .login-link {
            text-align: center;
            margin-top: 20px;
            color: #666666;
        }
        
        .login-link a {
            color: #339af0;
            text-decoration: none;
            font-weight: 500;
        }
        
        .login-link a:hover {
            text-decoration: underline;
            color: #228be6;
        }
        
        .password-requirements {
            font-size: 0.85rem;
            color: #666666;
            margin-top: 5px;
        }
        
        header {
            background: #339af0;
            color: white;
            padding: 30px 0;
            text-align: center;
            margin-bottom: 30px;
        }
        
        header h1 {
            margin: 0 0 10px 0;
            font-size: 2.5rem;
        }
        
        .tagline {
            margin: 0;
            opacity: 0.9;
            font-size: 1.1rem;
        }
        
        footer {
            margin-top: 40px;
            padding: 20px;
            text-align: center;
            color: #666666;
            border-top: 2px solid #339af0;
        }
        
        .footer-bottom {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        @media (max-width: 768px) {
            .register-container {
                padding: 20px;
                margin: 20px;
            }
            
            .footer-bottom {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Community Health System</h1>
            <p class="tagline">Create Your Account</p>
        </header>
        
        <main>
            <div class="register-container">
                <div class="register-header">
                    <h2>Create Account</h2>
                    <p>Join the Community surveillance community</p>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" id="registerForm">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    
                    <div class="form-group">
                        <label for="username">Username *</label>
                        <input type="text" 
                               id="username" 
                               name="username" 
                               class="form-control" 
                               value="<?php echo htmlspecialchars($username); ?>" 
                               required
                               maxlength="100"
                               autocomplete="username">
                        <div class="password-requirements">3-100 characters, letters and numbers only</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address *</label>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               class="form-control" 
                               value="<?php echo htmlspecialchars($email); ?>" 
                               required
                               maxlength="100"
                               autocomplete="email">
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password *</label>
                        <input type="password" 
                               id="password" 
                               name="password" 
                               class="form-control" 
                               required
                               minlength="6"
                               autocomplete="new-password">
                        <div class="password-requirements">Minimum 6 characters</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password *</label>
                        <input type="password" 
                               id="confirm_password" 
                               name="confirm_password" 
                               class="form-control" 
                               required
                               minlength="6"
                               autocomplete="new-password">
                    </div>
                    
                    <div class="form-group">
                        <label for="role">Select Your Role *</label>
                        <select id="role" name="role" class="form-select" required>
                            <option value="citizen" <?php echo $role === 'citizen' ? 'selected' : ''; ?>>Citizen</option>
                            <option value="health_worker" <?php echo $role === 'health_worker' ? 'selected' : ''; ?>>Health Worker</option>
                            <option value="admin" <?php echo $role === 'admin' ? 'selected' : ''; ?>>Administrator</option>
                        </select>
                        
                        <div class="role-description" id="citizen-desc" style="display: <?php echo $role === 'citizen' ? 'block' : 'none'; ?>;">
                            <strong>Citizen:</strong> Report diseases, view recommendations, track your reports.
                        </div>
                        <div class="role-description" id="health_worker-desc" style="display: <?php echo $role === 'health_worker' ? 'block' : 'none'; ?>;">
                            <strong>Health Worker:</strong> Analyze reports, provide recommendations, monitor outbreaks.
                        </div>
                        <div class="role-description" id="admin-desc" style="display: <?php echo $role === 'admin' ? 'block' : 'none'; ?>;">
                            <strong>Administrator:</strong> Create visualizations, manage users, view analytics.
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn-register">Create Account</button>
                    </div>
                </form>
                
                <div class="login-link">
                    Already have an account? <a href="login.php">Login here</a>
                </div>
            </div>
        </main>
        
        <footer>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> Community Surveillance System</p>
                <p>Secure Registration | Password Hashing Enabled</p>
            </div>
        </footer>
    </div>
    
    <script>
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
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
            
            if (password.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long!');
                return false;
            }
            
            return true;
        });
        
        document.getElementById('username').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^a-zA-Z0-9]/g, '');
        });
    </script>
</body>
</html>