<?php
require_once '../../config/config.php';

// Ensure only admin can access
if (!isAuthenticated() || $_SESSION['role'] !== 'admin') {
    redirect('/pages/login.php');
}

$db = Database::getInstance();
$message = '';
$error = '';

// Handle patient status updates
if (isset($_POST['action']) && isset($_POST['patient_id'])) {
    $patient_id = (int)$_POST['patient_id'];
    $action = $_POST['action'];

    if ($action === 'update_status') {
        $new_status = $_POST['status'];

        // Start transaction
        $db->query("START TRANSACTION");

        try {
            // Update user status
            $db->query("
                UPDATE users u
                JOIN patients p ON u.id = p.user_id
                SET u.status = '$new_status'
                WHERE p.id = $patient_id
            ");

            $db->query("COMMIT");
            $message = 'Patient status updated successfully';
        } catch (Exception $e) {
            $db->query("ROLLBACK");
            $error = 'Failed to update status: ' . $e->getMessage();
        }
    } elseif ($action === 'delete') {
        $db->query("UPDATE patients p JOIN users u ON p.user_id = u.id SET p.status = 'deleted', u.status = 'inactive' WHERE p.id = $patient_id");
        $message = 'Patient deleted successfully';
    }
}

// Get all patients with their user account information
$patients = $db->query("
    SELECT p.*, u.email, u.status
    FROM patients p
    JOIN users u ON p.user_id = u.id
    WHERE u.status != 'deleted'
    ORDER BY p.last_name, p.first_name
")->fetch_all(MYSQLI_ASSOC);

$content = '
<div class="container-fluid">
    ' . ($message ? '<div class="alert alert-success">' . $message . '</div>' : '') . '
    ' . ($error ? '<div class="alert alert-danger">' . $error . '</div>' : '') . '

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Gender</th>
                            <th>Date of Birth</th>
                            <th>Address</th>
                            <th>Emergency Contact</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>';

foreach ($patients as $patient) {
    $status_class = match ($patient['status']) {
        'active' => 'success',
        'inactive' => 'secondary',
        'suspended' => 'danger',
        default => 'secondary'
    };

    $content .= '
                        <tr>
                            <td>' . $patient['id'] . '</td>
                            <td>' . htmlspecialchars($patient['first_name']) . ' ' . htmlspecialchars($patient['last_name']) . '</td>
                            <td>' . htmlspecialchars($patient['email']) . '</td>
                            <td>' . ucfirst($patient['gender']) . '</td>
                            <td>' . date('M d, Y', strtotime($patient['date_of_birth'])) . '</td>
                            <td>' . htmlspecialchars($patient['address']) . '</td>
                            <td>' . htmlspecialchars($patient['emergency_contact']) . '</td>
                            <td>
                                <span class="badge bg-' . $status_class . '">' . ucfirst($patient['status']) . '</span>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <a href="edit_patient.php?id=' . $patient['id'] . '" class="btn btn-sm btn-primary" title="Edit Patient">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#statusModal' . $patient['id'] . '" title="Change Status">
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
foreach ($patients as $patient) {
    $content .= '
    <div class="modal fade" id="statusModal' . $patient['id'] . '" tabindex="-1" aria-labelledby="statusModalLabel' . $patient['id'] . '" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="statusModalLabel' . $patient['id'] . '">Update Patient Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="manage_patients.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="patient_id" value="' . $patient['id'] . '">
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" required>
                                <option value="active" ' . ($patient['status'] === 'active' ? 'selected' : '') . '>Active</option>
                                <option value="inactive" ' . ($patient['status'] === 'inactive' ? 'selected' : '') . '>Inactive</option>
                                <option value="suspended" ' . ($patient['status'] === 'suspended' ? 'selected' : '') . '>Suspended</option>
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
