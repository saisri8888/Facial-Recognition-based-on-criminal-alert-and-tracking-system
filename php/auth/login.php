<?php
// php/auth/login.php

require_once __DIR__ . '/../../includes/functions.php';

if (isLoggedIn()) {
    redirect('php/dashboard/index.php');
}

$error = '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND is_active = 1");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['profile_pic'] = $user['profile_pic'];

            // Update last login
            $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
            
            logAction('login', 'auth', 'User logged in successfully');
            redirect('php/dashboard/index.php');
        } else {
            $error = 'Invalid username or password.';
            logAction('login_failed', 'auth', "Failed login attempt for: $username");
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: linear-gradient(135deg, #0c0c1d 0%, #1a1a2e 50%, #16213e 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', sans-serif;
            overflow: hidden;
        }
        .particles { position: fixed; width: 100%; height: 100%; z-index: 0; }
        .particle {
            position: absolute; width: 4px; height: 4px;
            background: rgba(0, 212, 255, 0.3);
            border-radius: 50%;
            animation: float 6s infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); opacity: 0; }
            50% { opacity: 1; }
            100% { transform: translateY(-100vh) rotate(720deg); opacity: 0; }
        }
        .login-container {
            position: relative; z-index: 10;
            width: 450px;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
        }
        .login-header { text-align: center; margin-bottom: 30px; }
        .login-header .icon {
            width: 80px; height: 80px;
            background: linear-gradient(135deg, #00d4ff, #0099ff);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 15px;
            font-size: 36px; color: white;
            box-shadow: 0 10px 30px rgba(0, 212, 255, 0.3);
        }
        .login-header h1 { color: #fff; font-size: 24px; font-weight: 700; }
        .login-header p { color: rgba(255,255,255,0.5); font-size: 14px; }
        .form-group { margin-bottom: 20px; position: relative; }
        .form-group label { color: rgba(255,255,255,0.7); font-size: 13px; margin-bottom: 5px; display: block; }
        .form-group input {
            width: 100%; padding: 12px 15px 12px 45px;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 10px; color: #fff;
            font-size: 15px; transition: all 0.3s;
        }
        .form-group input:focus {
            outline: none;
            border-color: #00d4ff;
            box-shadow: 0 0 15px rgba(0, 212, 255, 0.2);
        }
        .form-group .input-icon {
            position: absolute; left: 15px; top: 40px;
            color: rgba(255,255,255,0.4); font-size: 16px;
        }
        .btn-login {
            width: 100%; padding: 14px;
            background: linear-gradient(135deg, #00d4ff, #0099ff);
            border: none; border-radius: 10px;
            color: white; font-size: 16px; font-weight: 600;
            cursor: pointer; transition: all 0.3s;
            text-transform: uppercase; letter-spacing: 1px;
        }
        .btn-login:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(0, 212, 255, 0.4); }
        .alert-custom { background: rgba(220, 53, 69, 0.2); border: 1px solid rgba(220, 53, 69, 0.5);
                        color: #ff6b6b; border-radius: 10px; padding: 10px 15px; font-size: 14px; }
        .scanner-line {
            position: absolute; top: 0; left: 0; right: 0;
            height: 2px; background: linear-gradient(90deg, transparent, #00d4ff, transparent);
            animation: scan 3s infinite;
        }
        @keyframes scan { 0% { top: 0; } 50% { top: 100%; } 100% { top: 0; } }
    </style>
</head>
<body>
    <div class="particles" id="particles"></div>
    
    <div class="login-container">
        <div class="scanner-line"></div>
        <div class="login-header">
            <div class="icon"><i class="fas fa-shield-halved"></i></div>
            <h1><?= SITE_NAME ?></h1>
            <p>Secure Access Portal</p>
        </div>

        <?php if ($error): ?>
            <div class="alert-custom mb-3"><i class="fas fa-exclamation-triangle me-2"></i><?= $error ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success" style="background:rgba(25,135,84,0.2);border-color:rgba(25,135,84,0.5);color:#75e6a0;border-radius:10px;padding:10px 15px;font-size:14px;">
                <i class="fas fa-check-circle me-2"></i><?= $success ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Username or Email</label>
                <i class="fas fa-user input-icon"></i>
                <input type="text" name="username" placeholder="Enter username or email" required 
                       value="<?= $username ?? '' ?>" autocomplete="username">
            </div>
            <div class="form-group">
                <label>Password</label>
                <i class="fas fa-lock input-icon"></i>
                <input type="password" name="password" placeholder="Enter password" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn-login">
                <i class="fas fa-fingerprint me-2"></i> Authenticate
            </button>
        </form>
    </div>

    <script>
        // Create floating particles
        const container = document.getElementById('particles');
        for (let i = 0; i < 50; i++) {
            const particle = document.createElement('div');
            particle.className = 'particle';
            particle.style.left = Math.random() * 100 + '%';
            particle.style.top = Math.random() * 100 + '%';
            particle.style.animationDelay = Math.random() * 6 + 's';
            particle.style.animationDuration = (Math.random() * 4 + 4) + 's';
            container.appendChild(particle);
        }
    </script>
</body>
</html>