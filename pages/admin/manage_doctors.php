<?php
require_once '../../config/config.php';

// Ensure only admin can access
if (!isAuthenticated() || $_SESSION['role'] !== 'admin') {
    redirect('/pages/login.php');
}

$db = Database::getInstance();
$message = '';
$error = '';

// Handle doctor status updates
if (isset($_POST['action']) && isset($_POST['doctor_id'])) {
    $doctor_id = (int)$_POST['doctor_id'];
    $action = $_POST['action'];

    if ($action === 'update_status') {
        $new_status = $_POST['status'];

        // Start transaction
        $db->query("START TRANSACTION");

        try {
            // Update user status
            $db->query("
                UPDATE users u
                JOIN doctors d ON u.id = d.user_id
                SET u.status = '$new_status'
                WHERE d.id = $doctor_id
            ");

            $db->query("COMMIT");
            $message = 'Doctor status updated successfully';
        } catch (Exception $e) {
            $db->query("ROLLBACK");
            $error = 'Failed to update status: ' . $e->getMessage();
        }
    } elseif ($action === 'delete') {
        $db->query("UPDATE doctors d JOIN users u ON d.user_id = u.id SET d.status = 'deleted', u.status = 'inactive' WHERE d.id = $doctor_id");
        $message = 'Doctor deleted successfully';
    }
}

// Get all doctors with their user account information
$doctors = $db->query("
    SELECT d.*, u.email, u.status
    FROM doctors d
    JOIN users u ON d.user_id = u.id
    WHERE u.status != 'deleted'
    ORDER BY d.last_name, d.first_name
")->fetch_all(MYSQLI_ASSOC);

$content = '
<div class="container-fluid">
    <div class="d-flex justify-content-end mb-4">
        <a href="add_doctor.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add New Doctor
        </a>
    </div>

    ' . ($message ? '<div class="alert alert-success">' . $message . '</div>' : '') . '
    ' . ($error ? '<div class="alert alert-danger">' . $error . '</div>' : '') . '

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Specialization</th>
                            <th>Qualification</th>
                            <th>Experience</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>';

foreach ($doctors as $doctor) {
    $status_class = match ($doctor['status']) {
        'active' => 'success',
        'inactive' => 'secondary',
        'suspended' => 'danger',
        default => 'secondary'
    };

    $content .= '
                        <tr>
                            <td>Dr. ' . htmlspecialchars($doctor['first_name']) . ' ' . htmlspecialchars($doctor['last_name']) . '</td>
                            <td>' . htmlspecialchars($doctor['specialization']) . '</td>
                            <td>' . htmlspecialchars($doctor['qualification']) . '</td>
                            <td>' . $doctor['years_experience'] . ' years</td>
                            <td>' . htmlspecialchars($doctor['email']) . '</td>
                            <td>
                                <span class="badge bg-' . $status_class . '">' . ucfirst($doctor['status']) . '</span>
                            </td>
                            <td class="text-end">
                                <div class="btn-group">
                                    <a href="edit_doctor.php?id=' . $doctor['id'] . '" class="btn btn-sm btn-primary" title="Edit Doctor">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#statusModal' . $doctor['id'] . '" title="Change Status">
                                        <i class="fas fa-user-cog"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>';
}

$content .= '
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>';

// Add modals after the main content
foreach ($doctors as $doctor) {
    $content .= '
    <div class="modal fade" id="statusModal' . $doctor['id'] . '" tabindex="-1" aria-labelledby="statusModalLabel' . $doctor['id'] . '" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="statusModalLabel' . $doctor['id'] . '">Update Doctor Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="manage_doctors.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="doctor_id" value="' . $doctor['id'] . '">
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" required>
                                <option value="active" ' . ($doctor['status'] === 'active' ? 'selected' : '') . '>Active</option>
                                <option value="inactive" ' . ($doctor['status'] === 'inactive' ? 'selected' : '') . '>Inactive</option>
                                <option value="suspended" ' . ($doctor['status'] === 'suspended' ? 'selected' : '') . '>Suspended</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="action" value="update_status" class="btn btn-primary">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>';
}

$content .= '
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Initialize all modals
    var modals = document.querySelectorAll(".modal");
    modals.forEach(function(modal) {
        new bootstrap.Modal(modal, {
            backdrop: "static",
            keyboard: false
        });
    });
});
</script>';

require_once '../../layouts/main.php';
