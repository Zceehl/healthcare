<?php
require_once '../config/config.php';

// Redirect if already logged in
if (isAuthenticated()) {
    redirect('/pages/' . $_SESSION['role'] . '/dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password';
    } else {
        $db = Database::getInstance();

        // Get user with role and status
        $email = $db->escape($email);
        $user = $db->query("
            SELECT id, email, password, role, status 
            FROM users 
            WHERE email = '$email'
        ")->fetch_assoc();

        if ($user && password_verify($password, $user['password'])) {
            // Check if user is active
            if ($user['status'] === 'active') {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];

                // Log the login
                $ip = $_SERVER['REMOTE_ADDR'];
                $db->query("
                    INSERT INTO audit_logs (user_id, action_type, table_name, record_id, ip_address)
                    VALUES ({$user['id']}, 'login', 'users', {$user['id']}, '$ip')
                ");

                // Redirect based on role
                redirect('/pages/' . $user['role'] . '/dashboard.php');
            } else {
                $error = 'Your account is not active. Please contact the administrator.';
            }
        } else {
            $error = 'Invalid email or password';
        }
    }
}

$content = '
<div class="auth-container">
    <div class="auth-card">
        <div class="text-center mb-4">
            <i class="fas fa-calendar-check auth-logo"></i>
            <h1 class="auth-title">MediSchedule</h1>
            <p class="auth-subtitle">Healthcare Management System</p>
        </div>
        
        ' . ($error ? '<div class="alert alert-danger">' . $error . '</div>' : '') . '

        <form method="POST" action="">
            <div class="mb-3">
                <label class="form-label">Email Address</label>
                <input type="email" class="form-control" name="email" value="' . htmlspecialchars($_POST['email'] ?? '') . '" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" class="form-control" name="password" required>
            </div>
            <div class="d-grid">
                <button type="submit" class="btn btn-primary">Login</button>
            </div>
        </form>
        
        <div class="text-center mt-4">
            <p class="mb-0">Don\'t have an account? <a href="register.php">Register here</a></p>
        </div>
    </div>
</div>
';

require_once '../layouts/main.php';
