<?php
session_start();

$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logging Out </title>
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
            justify-content: center;
            align-items: center;
        }
        
        .logout-container {
            background-color: white;
            padding: 50px 40px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 119, 204, 0.15);
            text-align: center;
            max-width: 500px;
            width: 90%;
            border: 1px solid #e0e0e0;
        }
        
        .logout-icon {
            font-size: 4rem;
            color: #0077cc;
            margin-bottom: 20px;
            animation: pulse 1.5s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); opacity: 0.8; }
            50% { transform: scale(1.1); opacity: 1; }
            100% { transform: scale(1); opacity: 0.8; }
        }
        
        .logout-title {
            color: #333;
            margin-bottom: 15px;
            font-size: 2rem;
        }
        
        .logout-message {
            color: #666;
            font-size: 1.1rem;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        
        .redirect-message {
            color: #333;
            font-size: 1rem;
            background-color: #f8fbff;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #0077cc;
            margin-bottom: 25px;
        }
        
        .redirect-message i {
            color: #0077cc;
            margin-right: 8px;
        }
        
        .login-link {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 24px;
            background-color: #0077cc;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
            margin-top: 10px;
        }
        
        .login-link:hover {
            background-color: #005599;
            transform: translateY(-2px);
            color: white;
            text-decoration: none;
        }
        
        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #e8f4ff;
            border-top: 3px solid #0077cc;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @media (max-width: 768px) {
            .logout-container {
                padding: 30px 20px;
            }
            
            .logout-icon {
                font-size: 3rem;
            }
            
            .logout-title {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="logout-container">
        <div class="logout-icon">
            <i class="fas fa-sign-out-alt"></i>
        </div>
        
        <h1 class="logout-title">Logging Out</h1>
        
        <div class="logout-message">
            You have been successfully logged out from the Community Health Surveillance System.
        </div>
        
        <div class="redirect-message">
            <i class="fas fa-info-circle"></i>
            You will be redirected to the home page in a few seconds...
        </div>
        
        <a href="../index.php" class="login-link">
            <div class="spinner"></div>
            Redirecting... Click here if not redirected
        </a>
    </div>
    
    <script>
        setTimeout(function() {
            window.location.href = "../index.php";
        }, 3000);
    </script>
</body>
</html>