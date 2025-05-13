<?php
require_once '../../config/config.php';

// Check if user is logged in and is a doctor
if (!isAuthenticated() || !isDoctor()) {
    redirect('/pages/login.php');
}

$db = Database::getInstance();

// Get doctor ID
$user_id = $_SESSION['user_id'];
$doctor_query = "SELECT id FROM doctors WHERE user_id = $user_id";
$doctor_result = $db->query($doctor_query);

if (!$doctor_result || $doctor_result->num_rows === 0) {
    redirect('/pages/login.php');
}

$doctor = $doctor_result->fetch_assoc();
$doctor_id = $doctor['id'];

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appointment_id']) && isset($_POST['status'])) {
    $appointment_id = (int)$_POST['appointment_id'];
    $status = sanitize($_POST['status']);

    if (in_array($status, ['scheduled', 'completed', 'cancelled', 'no-show'])) {
        // Get old values before update
        $old_values = $db->query("SELECT * FROM appointments WHERE id = $appointment_id")->fetch_assoc();

        $update_query = "UPDATE appointments SET status = '$status' WHERE id = $appointment_id AND doctor_id = $doctor_id";
        if ($db->query($update_query)) {
            // Log the status change
            $audit_logger = AuditLogger::getInstance();
            $audit_logger->log(
                'appointment_status_update',
                'appointments',
                $appointment_id,
                ['status' => $old_values['status']],
                ['status' => $status]
            );

            $_SESSION['flash_message'] = 'Appointment status updated successfully.';
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = 'Failed to update appointment status.';
            $_SESSION['flash_type'] = 'danger';
        }
    }
    redirect('/pages/doctor/appointments.php');
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$date_filter = isset($_GET['date']) ? sanitize($_GET['date']) : '';

// Build query
$query = "SELECT a.*, p.first_name as patient_first_name, p.last_name as patient_last_name,
                 u.email as patient_email
          FROM appointments a
          INNER JOIN patients p ON a.patient_id = p.id
          INNER JOIN users u ON p.user_id = u.id
          WHERE a.doctor_id = $doctor_id";

if ($status_filter) {
    $query .= " AND a.status = '$status_filter'";
}
if ($date_filter) {
    $query .= " AND a.appointment_date = '$date_filter'";
}

$query .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";
$result = $db->query($query);
$appointments = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $appointments[] = $row;
    }
}

$content = '
<div class="row">
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <h2 class="card-title mb-0">Appointments</h2>
                    <div>
                        <a href="dashboard.php" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-body">
                <!-- Filters -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <form method="GET" class="row g-3">
                            <div class="col-md-5">
                                <select name="status" class="form-select" onchange="this.form.submit()">
                                    <option value="">All Status</option>
                                    <option value="pending"' . ($status_filter === 'pending' ? ' selected' : '') . '>Pending</option>
                                    <option value="scheduled"' . ($status_filter === 'scheduled' ? ' selected' : '') . '>Scheduled</option>
                                    <option value="completed"' . ($status_filter === 'completed' ? ' selected' : '') . '>Completed</option>
                                    <option value="cancelled"' . ($status_filter === 'cancelled' ? ' selected' : '') . '>Cancelled</option>
                                    <option value="rejected"' . ($status_filter === 'rejected' ? ' selected' : '') . '>Rejected</option>
                                    <option value="no-show"' . ($status_filter === 'no-show' ? ' selected' : '') . '>No Show</option>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <input type="date" name="date" class="form-control" value="' . $date_filter . '" onchange="this.form.submit()">
                            </div>
                            <div class="col-md-2">
                                <a href="appointments.php" class="btn btn-outline-secondary w-100">Reset</a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Appointments Table -->
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Patient</th>
                                <th>Email</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>';

if (empty($appointments)) {
    $content .= '
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <div class="text-muted">
                                        <i class="fas fa-calendar-times fa-2x mb-3"></i>
                                        <p class="mb-0">No appointments found</p>
                                    </div>
                                </td>
                            </tr>';
} else {
    foreach ($appointments as $appointment) {
        $status_class = [
            'pending' => 'warning',
            'scheduled' => 'primary',
            'completed' => 'success',
            'cancelled' => 'danger',
            'rejected' => 'danger',
            'no-show' => 'warning'
        ][$appointment['status']] ?? 'secondary';

        $content .= '
                            <tr>
                                <td>' . date('M d, Y', strtotime($appointment['appointment_date'])) . '</td>
                                <td>' . date('h:i A', strtotime($appointment['appointment_time'])) . '</td>
                                <td>' . htmlspecialchars($appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']) . '</td>
                                <td>' . htmlspecialchars($appointment['patient_email']) . '</td>
                                <td>' . htmlspecialchars($appointment['reason']) . '</td>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="badge bg-' . $status_class . ' mb-1">' . ucfirst($appointment['status']) . '</span>';

        if ($appointment['status'] === 'rejected' && !empty($appointment['reject_reason'])) {
            $content .= '
                                        <small class="text-muted">Reason: ' . htmlspecialchars($appointment['reject_reason']) . '</small>';
        }

        $content .= '
                                    </div>
                                </td>
                                <td>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="appointment_id" value="' . $appointment['id'] . '">
                                        <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                                            <option value="scheduled"' . ($appointment['status'] === 'scheduled' ? ' selected' : '') . '>Scheduled</option>
                                            <option value="completed"' . ($appointment['status'] === 'completed' ? ' selected' : '') . '>Completed</option>
                                            <option value="cancelled"' . (($appointment['status'] === 'cancelled' || $appointment['status'] === 'rejected') ? ' selected' : '') . '>Cancelled</option>
                                            <option value="no-show"' . ($appointment['status'] === 'no-show' ? ' selected' : '') . '>No Show</option>
                                        </select>
                                    </form>';

        // Add record button for completed appointments
        if ($appointment['status'] === 'completed') {
            // Check if medical record exists
            $record_check_query = "SELECT id FROM medical_records WHERE appointment_id = " . $appointment['id'];
            $record_check_result = $db->query($record_check_query);

            if (!$record_check_result || $record_check_result->num_rows === 0) {
                $content .= '
                                    <a href="medical-record.php?appointment_id=' . $appointment['id'] . '" class="btn btn-sm btn-primary ms-2">
                                        <i class="fas fa-file-medical"></i> Record
                                    </a>';
            }
        }
        $content .= '
                                </td>
                            </tr>';
    }
}

$content .= '
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
';

require_once '../../layouts/main.php';
