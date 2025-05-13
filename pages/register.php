<?php
require_once '../config/config.php';

// Redirect if already logged in
if (isAuthenticated()) {
    redirect('/pages/dashboard.php');
}

$db = Database::getInstance();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate input
        $email = trim($_POST['email']);
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $date_of_birth = trim($_POST['date_of_birth']);
        $gender = trim($_POST['gender']);
        $address = trim($_POST['address']);
        $emergency_contact = trim($_POST['emergency_contact']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];

        if (
            empty($email) || empty($first_name) || empty($last_name) ||
            empty($date_of_birth) || empty($gender) || empty($password) ||
            empty($confirm_password) || empty($address) || empty($emergency_contact)
        ) {
            throw new Exception("All required fields must be filled out");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format");
        }

        if ($password !== $confirm_password) {
            throw new Exception("Passwords do not match");
        }

        if (strlen($password) < 8) {
            throw new Exception("Password must be at least 8 characters long");
        }

        // Check if email already exists
        $existing_user = $db->query("SELECT id FROM users WHERE email = '$email'")->fetch_assoc();
        if ($existing_user) {
            throw new Exception("Email already exists");
        }

        // Start transaction
        $db->query("START TRANSACTION");

        // Create user account
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $db->query("
            INSERT INTO users (email, password, role, status)
            VALUES ('$email', '$hashed_password', 'patient', 'active')
        ");

        $user_id = $db->getLastId();

        // Create patient record
        $db->query("
            INSERT INTO patients (
                user_id, first_name, last_name, date_of_birth, 
                gender, address, emergency_contact
            )
            VALUES (
                $user_id, '$first_name', '$last_name', '$date_of_birth', 
                '$gender', '$address', '$emergency_contact'
            )
        ");

        $db->query("COMMIT");

        // Set success message and redirect to login
        $_SESSION['success_message'] = 'Registration successful! Please login.';
        redirect('/pages/login.php');
    } catch (Exception $e) {
        $db->query("ROLLBACK");
        $error = $e->getMessage();
    }
}

$content = '
<div class="auth-container">
    <div class="auth-card">
        <div class="text-center mb-4">
            <i class="fas fa-calendar-check auth-logo"></i>
            <h1 class="auth-title">MediSchedule</h1>
            <p class="auth-subtitle">Patient Registration</p>
        </div>
        
        ' . ($error ? '<div class="alert alert-danger">' . $error . '</div>' : '') . '
        ' . ($success ? '<div class="alert alert-success">' . $success . '</div>' : '') . '
        
        <form method="POST" action="" data-validate>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="first_name" class="form-label">First Name</label>
                    <input type="text" class="form-control" id="first_name" name="first_name" required>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="last_name" class="form-label">Last Name</label>
                    <input type="text" class="form-control" id="last_name" name="last_name" required>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="date_of_birth" class="form-label">Date of Birth</label>
                    <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" required>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="confirm_password" class="form-label">Confirm Password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="gender" class="form-label">Gender</label>
                    <select class="form-select" id="gender" name="gender" required>
                        <option value="">Select Gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label for="address" class="form-label">Address</label>
                <textarea class="form-control" id="address" name="address" rows="3" required></textarea>
            </div>

            <div class="mb-3">
                <label for="emergency_contact" class="form-label">Emergency Contact</label>
                <input type="text" class="form-control" id="emergency_contact" name="emergency_contact" required>
                <small class="text-muted">Please provide a name and contact number</small>
            </div>
            
            <div class="d-grid">
                <button type="submit" class="btn btn-primary">Register</button>
            </div>
        </form>
        
        <div class="text-center mt-3">
            <p>Already have an account? <a href="login.php">Login here</a></p>
        </div>
    </div>
</div>
';

require_once '../layouts/main.php';
