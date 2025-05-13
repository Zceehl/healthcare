<?php
require_once '../../config/config.php';

// Ensure only admin can access
if (!isAuthenticated() || $_SESSION['role'] !== 'admin') {
    redirect('/pages/login.php');
}

$db = Database::getInstance();
$message = '';
$error = '';

// Handle appointment status updates
if (isset($_POST['action']) && isset($_POST['appointment_id'])) {
    $appointment_id = (int)$_POST['appointment_id'];
    $action = $_POST['action'];

    if ($action === 'update_status') {
        $new_status = $_POST['status'];

        try {
            $db->query("
                UPDATE appointments 
                SET status = '$new_status'
                WHERE id = $appointment_id
            ");
            $message = 'Appointment status updated successfully';
        } catch (Exception $e) {
            $error = 'Failed to update status: ' . $e->getMessage();
        }
    }
}

// Get all appointments with patient and doctor information
$appointments = $db->query("
    SELECT a.*, 
           p.first_name as patient_first_name, 
           p.last_name as patient_last_name,
           d.first_name as doctor_first_name, 
           d.last_name as doctor_last_name,
           u.status as doctor_status
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users u ON d.user_id = u.id
    WHERE u.status = 'active'
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
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
                            <th>Patient</th>
                            <th>Doctor</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>';

foreach ($appointments as $appointment) {
    $status_class = match ($appointment['status']) {
        'scheduled' => 'primary',
        'completed' => 'success',
        'cancelled' => 'danger',
        'no_show' => 'warning',
        default => 'secondary'
    };

    $content .= '
                        <tr>
                            <td>' . htmlspecialchars($appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']) . '</td>
                            <td>Dr. ' . htmlspecialchars($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']) . '</td>
                            <td>' . date('M d, Y', strtotime($appointment['appointment_date'])) . '</td>
                            <td>' . date('h:i A', strtotime($appointment['appointment_time'])) . '</td>
                            <td>
                                <span class="badge bg-' . $status_class . '">' . ucfirst(str_replace('_', ' ', $appointment['status'])) . '</span>
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

require_once '../../layouts/main.php';
