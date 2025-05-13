<?php
require_once '../../config/config.php';

// Ensure only admin can access
if (!isAuthenticated() || $_SESSION['role'] !== 'admin') {
    redirect('/pages/login.php');
}

$db = Database::getInstance();
$message = '';
$error = '';

// Get doctor ID from URL
$doctor_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get doctor information
$doctor = $db->query("
    SELECT d.*, u.email, u.status as user_status
    FROM doctors d
    JOIN users u ON d.user_id = u.id
    WHERE d.id = $doctor_id
")->fetch_assoc();

if (!$doctor) {
    redirect('/pages/admin/manage_doctors.php');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate input
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $specialization = trim($_POST['specialization']);
        $qualification = trim($_POST['qualification']);
        $years_experience = (int)$_POST['years_experience'];
        $bio = trim($_POST['bio']);
        $email = trim($_POST['email']);

        if (
            empty($first_name) || empty($last_name) || empty($specialization) ||
            empty($qualification) || empty($email)
        ) {
            throw new Exception("All required fields must be filled out");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format");
        }

        // Check if email already exists for another user
        $existing_user = $db->query("
            SELECT id FROM users 
            WHERE email = '$email' AND id != {$doctor['user_id']}
        ")->fetch_assoc();

        if ($existing_user) {
            throw new Exception("Email already exists");
        }

        // Update doctor information
        $db->query("
            UPDATE doctors 
            SET first_name = '$first_name',
                last_name = '$last_name',
                specialization = '$specialization',
                qualification = '$qualification',
                years_experience = $years_experience,
                bio = '$bio'
            WHERE id = $doctor_id
        ");

        // Update user account
        $db->query("
            UPDATE users 
            SET email = '$email'
            WHERE id = {$doctor['user_id']}
        ");

        $message = 'Doctor information updated successfully';

        // Refresh doctor data
        $doctor = $db->query("
            SELECT d.*, u.email, u.status as user_status
            FROM doctors d
            JOIN users u ON d.user_id = u.id
            WHERE d.id = $doctor_id
        ")->fetch_assoc();
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
                    <h5 class="card-title mb-0">Edit Doctor</h5>
                </div>
                <div class="card-body">
                    ' . ($message ? '<div class="alert alert-success">' . $message . '</div>' : '') . '
                    ' . ($error ? '<div class="alert alert-danger">' . $error . '</div>' : '') . '

                    <form method="POST" action="" class="needs-validation" novalidate>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" value="' . htmlspecialchars($doctor['email']) . '" required>
                                <small class="text-muted">This will be used for login</small>
                            </div>
                            <div class="col-md-6">
                                <label for="first_name" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" value="' . htmlspecialchars($doctor['first_name']) . '" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="last_name" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" value="' . htmlspecialchars($doctor['last_name']) . '" required>
                            </div>
                            <div class="col-md-6">
                                <label for="specialization" class="form-label">Specialization</label>
                                <select class="form-select" id="specialization" name="specialization" required>
                                    <option value="">Select Specialization</option>
                                    <option value="Cardiology" ' . ($doctor['specialization'] === 'Cardiology' ? 'selected' : '') . '>Cardiology</option>
                                    <option value="Dermatology" ' . ($doctor['specialization'] === 'Dermatology' ? 'selected' : '') . '>Dermatology</option>
                                    <option value="Endocrinology" ' . ($doctor['specialization'] === 'Endocrinology' ? 'selected' : '') . '>Endocrinology</option>
                                    <option value="Gastroenterology" ' . ($doctor['specialization'] === 'Gastroenterology' ? 'selected' : '') . '>Gastroenterology</option>
                                    <option value="Neurology" ' . ($doctor['specialization'] === 'Neurology' ? 'selected' : '') . '>Neurology</option>
                                    <option value="Obstetrics and Gynecology" ' . ($doctor['specialization'] === 'Obstetrics and Gynecology' ? 'selected' : '') . '>Obstetrics and Gynecology</option>
                                    <option value="Ophthalmology" ' . ($doctor['specialization'] === 'Ophthalmology' ? 'selected' : '') . '>Ophthalmology</option>
                                    <option value="Orthopedics" ' . ($doctor['specialization'] === 'Orthopedics' ? 'selected' : '') . '>Orthopedics</option>
                                    <option value="Pediatrics" ' . ($doctor['specialization'] === 'Pediatrics' ? 'selected' : '') . '>Pediatrics</option>
                                    <option value="Psychiatry" ' . ($doctor['specialization'] === 'Psychiatry' ? 'selected' : '') . '>Psychiatry</option>
                                    <option value="Urology" ' . ($doctor['specialization'] === 'Urology' ? 'selected' : '') . '>Urology</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="qualification" class="form-label">Qualification</label>
                                <input type="text" class="form-control" id="qualification" name="qualification" value="' . htmlspecialchars($doctor['qualification']) . '" required>
                            </div>
                            <div class="col-md-6">
                                <label for="years_experience" class="form-label">Years of Experience</label>
                                <input type="number" class="form-control" id="years_experience" name="years_experience" value="' . htmlspecialchars($doctor['years_experience']) . '" min="0" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-12">
                                <label for="bio" class="form-label">Bio</label>
                                <textarea class="form-control" id="bio" name="bio" rows="4" placeholder="Enter doctor\'s biography">' . htmlspecialchars($doctor['bio'] ?? '') . '</textarea>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="manage_doctors.php" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Update Doctor</button>
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
