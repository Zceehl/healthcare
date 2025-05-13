<?php
require_once '../../config/config.php';

// Ensure only admin can access
if (!isAuthenticated() || $_SESSION['role'] !== 'admin') {
    redirect('/pages/login.php');
}

$db = Database::getInstance();
$message = '';
$error = '';

// Get patient ID from URL
$patient_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$patient_id) {
    redirect('/pages/admin/manage_patients.php');
}

// Get patient information
$patient = $db->query("
    SELECT p.*, u.email, u.status as user_status
    FROM patients p
    JOIN users u ON p.user_id = u.id
    WHERE p.id = $patient_id
")->fetch_assoc();

if (!$patient) {
    redirect('/pages/admin/manage_patients.php');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate input
        $first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
        $last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
        $date_of_birth = isset($_POST['date_of_birth']) ? trim($_POST['date_of_birth']) : '';
        $gender = isset($_POST['gender']) ? trim($_POST['gender']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $address = isset($_POST['address']) ? trim($_POST['address']) : '';
        $emergency_contact = isset($_POST['emergency_contact']) ? trim($_POST['emergency_contact']) : '';

        if (
            empty($first_name) || empty($last_name) || empty($date_of_birth) ||
            empty($gender) || empty($email)
        ) {
            throw new Exception("All required fields must be filled out");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format");
        }

        // Check if email already exists for another user
        $existing_user = $db->query("
            SELECT id FROM users 
            WHERE email = '$email' AND id != {$patient['user_id']}
        ")->fetch_assoc();

        if ($existing_user) {
            throw new Exception("Email already exists");
        }

        // Start transaction
        $db->query("START TRANSACTION");

        // Update patient record
        $db->query("
            UPDATE patients SET 
                first_name = '$first_name',
                last_name = '$last_name',
                date_of_birth = '$date_of_birth',
                gender = '$gender',
                address = '$address',
                emergency_contact = '$emergency_contact'
            WHERE id = $patient_id
        ");

        // Update user account
        $db->query("
            UPDATE users 
            SET email = '$email'
            WHERE id = {$patient['user_id']}
        ");

        $db->query("COMMIT");
        $message = 'Patient information updated successfully';

        // Refresh patient data
        $patient = $db->query("
            SELECT p.*, u.email, u.status as user_status
            FROM patients p
            JOIN users u ON p.user_id = u.id
            WHERE p.id = $patient_id
        ")->fetch_assoc();
    } catch (Exception $e) {
        $db->query("ROLLBACK");
        $error = $e->getMessage();
    }
}

$content = '
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h2 class="card-title mb-4">Edit Patient</h2>
                    
                    ' . ($message ? '<div class="alert alert-success mb-4">' . $message . '</div>' : '') . '
                    ' . ($error ? '<div class="alert alert-danger mb-4">' . $error . '</div>' : '') . '
                    
                    <div class="alert alert-info mb-4">
                        <h5 class="alert-heading">Patient Details</h5>
                        <p class="mb-1"><strong>Email:</strong> ' . htmlspecialchars($patient['email'] ?? '') . '</p>
                        <p class="mb-0"><strong>Status:</strong> ' . ucfirst($patient['user_status'] ?? '') . '</p>
                    </div>

                    <form method="POST" action="" data-validate>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="first_name" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" value="' . htmlspecialchars($patient['first_name'] ?? '') . '" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="last_name" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" value="' . htmlspecialchars($patient['last_name'] ?? '') . '" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" value="' . htmlspecialchars($patient['email'] ?? '') . '" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="date_of_birth" class="form-label">Date of Birth</label>
                                <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" value="' . ($patient['date_of_birth'] ?? '') . '" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="gender" class="form-label">Gender</label>
                                <select class="form-select" id="gender" name="gender" required>
                                    <option value="">Select Gender</option>
                                    <option value="Male"' . (($patient['gender'] ?? '') === 'Male' ? ' selected' : '') . '>Male</option>
                                    <option value="Female"' . (($patient['gender'] ?? '') === 'Female' ? ' selected' : '') . '>Female</option>
                                    <option value="Other"' . (($patient['gender'] ?? '') === 'Other' ? ' selected' : '') . '>Other</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3" required>' . htmlspecialchars($patient['address'] ?? '') . '</textarea>
                        </div>

                        <div class="mb-3">
                            <label for="emergency_contact" class="form-label">Emergency Contact</label>
                            <input type="text" class="form-control" id="emergency_contact" name="emergency_contact" value="' . htmlspecialchars($patient['emergency_contact'] ?? '') . '" required>
                            <small class="text-muted">Please provide a name and contact number</small>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="manage_patients.php" class="btn btn-secondary">Back to List</a>
                            <button type="submit" class="btn btn-primary">Update Patient</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
';

require_once '../../layouts/main.php';
