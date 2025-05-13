<?php
require_once '../config/config.php';

// Check if user is logged in
if (!isAuthenticated()) {
    redirect('/pages/login.php');
}

$db = Database::getInstance();
$error = '';
$success = '';

// Get user information based on role
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Get user details based on role
switch ($user_role) {
    case 'admin':
        $query = "SELECT u.*, u.email, u.status, u.created_at 
                 FROM users u 
                 WHERE u.id = $user_id AND u.role = 'admin'";
        $table = 'users';
        $fields = [];
        break;
    case 'doctor':
        $query = "SELECT d.*, d.profile_image, u.email, u.status, u.created_at 
                 FROM doctors d 
                 INNER JOIN users u ON d.user_id = u.id 
                 WHERE d.user_id = $user_id";
        $table = 'doctors';
        $fields = ['first_name', 'last_name', 'specialization', 'qualification', 'years_experience', 'bio', 'profile_image'];
        break;
    case 'patient':
        $query = "SELECT p.*, p.profile_image, u.email, u.status, u.created_at 
                 FROM patients p 
                 INNER JOIN users u ON p.user_id = u.id 
                 WHERE p.user_id = $user_id";
        $table = 'patients';
        $fields = ['first_name', 'last_name', 'date_of_birth', 'gender', 'address', 'emergency_contact', 'profile_image'];
        break;
    default:
        redirect('/pages/login.php');
}

$result = $db->query($query);

if (!$result || $result->num_rows === 0) {
    redirect('/pages/login.php');
}

$user = $result->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [];
    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            $data[$field] = sanitize($_POST[$field]);
        }
    }
    $email = sanitize($_POST['email']);
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validate required fields
    $required_fields = array_merge(['email'], array_slice($fields, 0, 2)); // email, first_name, last_name
    $missing_fields = array_filter($required_fields, function ($field) use ($data, $email) {
        return empty($field === 'email' ? $email : $data[$field]);
    });

    if (!empty($missing_fields)) {
        $error = 'Please fill in all required fields';
    } else {
        // Check if email is already taken by another user
        $email_check_query = "SELECT id FROM users WHERE email = '$email' AND id != $user_id";
        $email_check_result = $db->query($email_check_query);

        if ($email_check_result && $email_check_result->num_rows > 0) {
            $error = 'Email is already taken';
        } else {
            // Begin transaction
            $db->query("START TRANSACTION");

            try {
                // Update user email
                $email = $db->escape($email);
                $user_update_query = "UPDATE users SET email = '$email' WHERE id = $user_id";

                if ($db->query($user_update_query)) {
                    // Update role-specific information
                    if ($user_role !== 'admin') {
                        $update_fields = [];
                        foreach ($data as $field => $value) {
                            if ($field !== 'profile_image') { // Skip profile_image as it's handled separately
                                $value = $db->escape($value);
                                $update_fields[] = "$field = '$value'";
                            }
                        }

                        if (!empty($update_fields)) {
                            $update_query = "UPDATE $table SET " . implode(', ', $update_fields) . " WHERE user_id = $user_id";
                            if (!$db->query($update_query)) {
                                throw new Exception("Failed to update user information");
                            }
                        }
                    }

                    // Update password if provided
                    if (!empty($current_password)) {
                        if (empty($new_password) || empty($confirm_password)) {
                            throw new Exception("New password and confirmation are required");
                        }

                        if ($new_password !== $confirm_password) {
                            throw new Exception("New passwords do not match");
                        }

                        if (strlen($new_password) < 8) {
                            throw new Exception("Password must be at least 8 characters long");
                        }

                        // Verify current password
                        $password_check_query = "SELECT password FROM users WHERE id = $user_id";
                        $password_check_result = $db->query($password_check_query);
                        $user_data = $password_check_result->fetch_assoc();

                        if (!password_verify($current_password, $user_data['password'])) {
                            throw new Exception("Current password is incorrect");
                        }

                        // Update password
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $password_update_query = "UPDATE users SET password = '$hashed_password' WHERE id = $user_id";

                        if (!$db->query($password_update_query)) {
                            throw new Exception("Failed to update password");
                        }
                    }

                    // Handle profile image upload
                    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK && $user_role !== 'admin') {
                        $file_uploader = FileUploader::getInstance();

                        // Validate file type
                        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                        $file_type = $_FILES['profile_image']['type'];

                        if (!in_array($file_type, $allowed_types)) {
                            throw new Exception("Invalid file type. Only JPG, PNG, and GIF are allowed.");
                        }

                        // Validate file size (max 5MB)
                        if ($_FILES['profile_image']['size'] > 5 * 1024 * 1024) {
                            throw new Exception("File size too large. Maximum size is 5MB.");
                        }

                        // Delete old profile image if exists
                        if (!empty($user['profile_image'])) {
                            $file_uploader->deleteProfileImage($user['profile_image']);
                        }

                        // Upload new profile image
                        $profile_image = $file_uploader->uploadProfileImage($_FILES['profile_image'], $user_id);

                        // Update profile image in database
                        $image_update_query = "UPDATE $table SET profile_image = '$profile_image' WHERE user_id = $user_id";
                        if (!$db->query($image_update_query)) {
                            throw new Exception("Failed to update profile image");
                        }

                        // Log the change
                        $audit_logger = AuditLogger::getInstance();
                        $audit_logger->log(
                            'profile_image_update',
                            $table,
                            $user_id,
                            ['profile_image' => $user['profile_image']],
                            ['profile_image' => $profile_image]
                        );
                    }

                    $db->query("COMMIT");
                    $success = 'Profile updated successfully';

                    // Refresh user data
                    $result = $db->query($query);
                    $user = $result->fetch_assoc();
                } else {
                    throw new Exception("Failed to update user information");
                }
            } catch (Exception $e) {
                $db->query("ROLLBACK");
                $error = $e->getMessage();
            }
        }
    }
}

// Generate form fields based on user role
$form_fields = '';
foreach ($fields as $field) {
    if ($field === 'profile_image') continue; // Skip profile_image as it's handled separately

    $label = ucwords(str_replace('_', ' ', $field));
    $value = htmlspecialchars($user[$field] ?? '');
    $required = in_array($field, ['first_name', 'last_name', 'email']) ? 'required' : '';
    $type = 'text';

    // Set appropriate input type
    switch ($field) {
        case 'email':
            $type = 'email';
            break;
        case 'date_of_birth':
            $type = 'date';
            break;
        case 'phone':
            $type = 'tel';
            break;
        case 'gender':
            $form_fields .= '
                <div class="mb-3">
                    <label class="form-label">' . $label . '</label>
                    <div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="' . $field . '" value="M" ' . ($value === 'M' ? 'checked' : '') . '>
                            <label class="form-check-label">Male</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="' . $field . '" value="F" ' . ($value === 'F' ? 'checked' : '') . '>
                            <label class="form-check-label">Female</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="' . $field . '" value="O" ' . ($value === 'O' ? 'checked' : '') . '>
                            <label class="form-check-label">Other</label>
                        </div>
                    </div>
                </div>';
            continue 2;
        case 'bio':
            $form_fields .= '
                <div class="mb-3">
                    <label for="' . $field . '" class="form-label">' . $label . '</label>
                    <textarea class="form-control" id="' . $field . '" name="' . $field . '" rows="4">' . $value . '</textarea>
                </div>';
            continue 2;
    }

    $form_fields .= '
        <div class="mb-3">
            <label for="' . $field . '" class="form-label">' . $label . '</label>
            <input type="' . $type . '" class="form-control" id="' . $field . '" name="' . $field . '" value="' . $value . '" ' . $required . '>
        </div>';
}

// Add email field at the beginning of the form
$form_fields = '
    <div class="mb-3">
        <label for="email" class="form-label">Email</label>
        <input type="email" class="form-control" id="email" name="email" value="' . htmlspecialchars($user['email']) . '" required>
    </div>' . $form_fields;

// Add profile image upload field for non-admin users
if ($user_role !== 'admin') {
    $file_uploader = FileUploader::getInstance();
    $profile_image_path = $file_uploader->getProfileImageUrl($user['profile_image'] ?? '');
    $current_image = '<img src="' . $profile_image_path . '" class="img-thumbnail mb-2" style="max-width: 200px;">';

    $form_fields .= '
        <div class="mb-3">
            <label class="form-label">Profile Image</label>
            ' . $current_image . '
            <input type="file" class="form-control" name="profile_image" accept="image/jpeg,image/png,image/gif">
            <small class="text-muted">Max file size: 5MB. Allowed formats: JPG, PNG, GIF</small>
        </div>';
}

$content = '
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Profile Settings</h4>
                </div>
                <div class="card-body">
                    ' . ($error ? '<div class="alert alert-danger">' . $error . '</div>' : '') . '
                    ' . ($success ? '<div class="alert alert-success">' . $success . '</div>' : '') . '
                    
                    <form method="POST" enctype="multipart/form-data">
                        ' . $form_fields . '
                        
                        <hr>
                        
                        <h5>Change Password</h5>
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password">
                        </div>
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password">
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Update Profile</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>';

require_once '../layouts/main.php';
