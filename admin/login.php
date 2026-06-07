<?php
session_start();

// Redirect to dashboard if already logged in
if (isset($_SESSION['admin_logged']) && $_SESSION['admin_logged'] === true) {
    header('Location: dashboard.php');
    exit;
}

$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    // Default secure credentials
    $admin_username = 'admin';
    $admin_password = 'password123';

    if ($username === $admin_username && $password === $admin_password) {
        $_SESSION['admin_logged'] = true;
        $_SESSION['admin_user'] = $admin_username;
        header('Location: dashboard.php');
        exit;
    } else {
        $error_msg = 'Invalid Admin Username or Password!';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Online Voting System</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    <div class="container">
        <!-- HEADER -->
        <div>
            <a href="https://voting-system.stripplaza.nl/">
            <img src="../assets/images/header/logo.png" alt="Logo" width="150" height="120" class="header-logo header-logo primary">
        </a>
            <div>
                <a href="../index.php" class="btn btn-secondary">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                    Public Voting Portal
                </a>
            </div>
        </div>

        <!-- LOGIN FORM CARD -->
        <div class="admin-login-card">
            <h2 style="font-family:var(--font-heading); font-weight:900; text-transform:uppercase; text-align:center; margin-bottom:25px; font-size:1.6rem;">Admin Authentication</h2>
            
            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-danger" style="padding: 10px 15px; font-size: 0.9rem; margin-bottom: 20px;">
                    <?= $error_msg ?>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST">
                <div class="form-group">
                    <label class="form-label" for="username">Username</label>
                    <input type="text" id="username" name="username" class="form-input" placeholder="Enter admin username" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-input" placeholder="Enter admin password" required>
                </div>

                <div style="margin-top: 30px;">
                    <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 12px;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path><polyline points="10 17 15 12 10 7"></polyline><line x1="15" y1="12" x2="3" y2="12"></line></svg>
                        Log In Securely
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
