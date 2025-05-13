<?php
require_once '../../config/config.php';

// Ensure only admin can access
if (!isAuthenticated() || $_SESSION['role'] !== 'admin') {
    redirect('/pages/login.php');
}

$db = Database::getInstance();
$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate input
        $password = trim($_POST['password']);
        $email = trim($_POST['email']);
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $specialization = trim($_POST['specialization']);
        $qualification = trim($_POST['qualification']);
        $years_experience = (int)$_POST['years_experience'];
        $bio = trim($_POST['bio']);

        if (
            empty($password) || empty($email) || empty($first_name) ||
            empty($last_name) || empty($specialization) || empty($qualification)
        ) {
            throw new Exception("All required fields must be filled out");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format");
        }

        if (strlen($password) < 8) {
            throw new Exception("Password must be at least 8 characters long");
        }

        // Check if email already exists
        $existing_user = $db->query("
            SELECT id FROM users 
            WHERE email = '$email'
        ")->fetch_assoc();

        if ($existing_user) {
            throw new Exception("Email already exists");
        }

        // Start transaction
        $db->query("START TRANSACTION");

        try {
            // Create user account
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $db->query("
                INSERT INTO users (email, password, role, status)
                VALUES ('$email', '$hashed_password', 'doctor', 'active')
            ");

            $user_id = $db->getLastId();

            // Create doctor record
            $db->query("
                INSERT INTO doctors (
                    user_id, first_name, last_name, specialization, 
                    qualification, years_experience, bio
                ) VALUES (
                    $user_id, '$first_name', '$last_name', '$specialization',
                    '$qualification', $years_experience, '$bio'
                )
            ");

            $db->query("COMMIT");
            $message = 'Doctor added successfully';

            // Clear form data
            $_POST = array();
        } catch (Exception $e) {
            $db->query("ROLLBACK");
            throw $e;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$content = '
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Add New Doctor</h5>
                </div>
                <div class="card-body">
                    ' . ($message ? '<div class="alert alert-success">' . $message . '</div>' : '') . '
                    ' . ($error ? '<div class="alert alert-danger">' . $error . '</div>' : '') . '

                    <form method="POST" action="" class="needs-validation" novalidate>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" value="' . htmlspecialchars($_POST['email'] ?? '') . '" required>
                                <small class="text-muted">This will be used for login</small>
                            </div>
                            <div class="col-md-6">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <small class="text-muted">Password must be at least 8 characters long</small>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="first_name" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" value="' . htmlspecialchars($_POST['first_name'] ?? '') . '" required>
                            </div>
                            <div class="col-md-6">
                                <label for="last_name" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" value="' . htmlspecialchars($_POST['last_name'] ?? '') . '" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="specialization" class="form-label">Specialization</label>
                                <select class="form-select" id="specialization" name="specialization" required>
                                    <option value="">Select Specialization</option>
                                    <option value="Cardiology">Cardiology</option>
                                    <option value="Dermatology">Dermatology</option>
                                    <option value="Endocrinology">Endocrinology</option>
                                    <option value="Gastroenterology">Gastroenterology</option>
                                    <option value="Neurology">Neurology</option>
                                    <option value="Obstetrics and Gynecology">Obstetrics and Gynecology</option>
                                    <option value="Ophthalmology">Ophthalmology</option>
                                    <option value="Orthopedics">Orthopedics</option>
                                    <option value="Pediatrics">Pediatrics</option>
                                    <option value="Psychiatry">Psychiatry</option>
                                    <option value="Urology">Urology</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="qualification" class="form-label">Qualification</label>
                                <input type="text" class="form-control" id="qualification" name="qualification" value="' . htmlspecialchars($_POST['qualification'] ?? '') . '" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="years_experience" class="form-label">Years of Experience</label>
                                <input type="number" class="form-control" id="years_experience" name="years_experience" value="' . htmlspecialchars($_POST['years_experience'] ?? '0') . '" min="0" required>
                            </div>
                            <div class="col-md-6">
                                <label for="bio" class="form-label">Bio</label>
                                <textarea class="form-control" id="bio" name="bio" rows="4" placeholder="Enter doctor\'s biography">' . htmlspecialchars($_POST['bio'] ?? '') . '</textarea>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="manage_doctors.php" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Add Doctor</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Form validation
(function () {
    "use strict";
    var forms = document.querySelectorAll(".needs-validation");
    Array.prototype.slice.call(forms).forEach(function (form) {
        form.addEventListener("submit", function (event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add("was-validated");
        }, false);
    });
})();
</script>';

require_once '../../layouts/main.php';
