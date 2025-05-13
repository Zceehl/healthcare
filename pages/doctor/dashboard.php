<?php
require_once '../../config/config.php';

// Check if user is logged in and is a doctor
if (!isAuthenticated() || !isDoctor()) {
    redirect('/pages/login.php');
}

$db = Database::getInstance();

// Get doctor ID
$user_id = $_SESSION['user_id'];
$doctor_query = "SELECT d.*, u.email 
                FROM doctors d 
                INNER JOIN users u ON d.user_id = u.id 
                WHERE d.user_id = $user_id";
$doctor_result = $db->query($doctor_query);

if (!$doctor_result || $doctor_result->num_rows === 0) {
    redirect('/pages/login.php');
}

$doctor = $doctor_result->fetch_assoc();
$doctor_id = $doctor['id'];

// Handle appointment status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_appointment'])) {
    $appointment_id = (int)$_POST['appointment_id'];
    $action = sanitize($_POST['action']);
    $reason = sanitize($_POST['reason'] ?? '');

    // Verify the appointment belongs to this doctor
    $verify_query = "SELECT a.*, p.first_name as patient_first_name, p.last_name as patient_last_name, 
                           u.email as patient_email
                    FROM appointments a
                    INNER JOIN patients p ON a.patient_id = p.id
                    INNER JOIN users u ON p.user_id = u.id
                    WHERE a.id = $appointment_id AND a.doctor_id = " . $doctor['id'];
    $verify_result = $db->query($verify_query);

    if ($verify_result && $verify_result->num_rows > 0) {
        $appointment = $verify_result->fetch_assoc();

        // Only allow updates for pending appointments
        if ($appointment['status'] === 'pending') {
            $new_status = ($action === 'accept') ? 'scheduled' : 'rejected';
            $update_query = "UPDATE appointments SET 
                           status = '$new_status', 
                           reject_reason = " . ($action === 'reject' ? "'$reason'" : "NULL") . ",
                           updated_at = NOW()
                           WHERE id = $appointment_id";

            if ($db->query($update_query)) {
                $_SESSION['flash_message'] = 'Appointment ' . ($action === 'accept' ? 'accepted' : 'rejected') . ' successfully.';
                $_SESSION['flash_type'] = 'success';
            } else {
                $_SESSION['flash_message'] = 'Failed to update appointment status.';
                $_SESSION['flash_type'] = 'danger';
            }
        } else {
            $_SESSION['flash_message'] = 'Only pending appointments can be updated.';
            $_SESSION['flash_type'] = 'warning';
        }
    } else {
        $_SESSION['flash_message'] = 'Invalid appointment.';
        $_SESSION['flash_type'] = 'danger';
    }
    redirect('/pages/doctor/dashboard.php');
}

// Get today's appointments
$today = date('Y-m-d');
$today_appointments_query = "SELECT a.*, p.first_name as patient_first_name, p.last_name as patient_last_name
                           FROM appointments a
                           INNER JOIN patients p ON a.patient_id = p.id
                           WHERE a.doctor_id = " . $doctor['id'] . "
                           AND a.appointment_date = '$today'
                           ORDER BY a.appointment_time ASC";
$today_appointments_result = $db->query($today_appointments_query);
$today_appointments = [];

if ($today_appointments_result) {
    while ($row = $today_appointments_result->fetch_assoc()) {
        $today_appointments[] = $row;
    }
}

// Get pending appointments
$pending_appointments_query = "SELECT a.*, p.first_name as patient_first_name, p.last_name as patient_last_name
                             FROM appointments a
                             INNER JOIN patients p ON a.patient_id = p.id
                             WHERE a.doctor_id = " . $doctor['id'] . "
                             AND a.status = 'pending'
                             ORDER BY a.appointment_date ASC, a.appointment_time ASC";
$pending_appointments_result = $db->query($pending_appointments_query);
$pending_appointments = [];

if ($pending_appointments_result) {
    while ($row = $pending_appointments_result->fetch_assoc()) {
        $pending_appointments[] = $row;
    }
}

// Get doctor's schedule
$schedule_query = "SELECT * FROM doctor_schedules 
                  WHERE doctor_id = $doctor_id 
                  ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')";
$schedule_result = $db->query($schedule_query);
$schedules = [];

if ($schedule_result) {
    while ($row = $schedule_result->fetch_assoc()) {
        $schedules[$row['day_of_week']] = $row;
    }
}

// Get appointment statistics
$stats_query = "SELECT 
    COUNT(*) as total_appointments,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
    SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled_count,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count,
    SUM(CASE WHEN appointment_date = CURDATE() THEN 1 ELSE 0 END) as today_count
FROM appointments 
WHERE doctor_id = $doctor_id";
$stats_result = $db->query($stats_query);
$stats = $stats_result->fetch_assoc();

$content = '
<div class="container-fluid py-4">
    <!-- Welcome Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-1">Welcome, Dr. ' . $doctor['first_name'] . ' ' . $doctor['last_name'] . '</h2>
                    <p class="text-muted mb-0">' . $doctor['specialization'] . '</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="schedule.php" class="btn btn-primary">
                        <i class="fas fa-clock me-2"></i>Manage Schedule
                    </a>
                    <a href="appointments.php" class="btn btn-outline-primary">
                        <i class="fas fa-calendar-alt me-2"></i>View All Appointments
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Appointments Section -->
    <div class="row g-4">
        <!-- Pending Appointments -->
        <div class="col-lg-6">
            <div class="bg-white rounded p-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">
                        <i class="fas fa-clock text-warning me-2"></i>Pending Appointments
                        <span class="badge bg-warning text-dark ms-2">' . count($pending_appointments) . '</span>
                    </h5>
                </div>
                <div class="table-responsive custom-scroll" style="max-height: 400px;">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="sticky-top bg-white">
                            <tr>
                                <th style="width: 15%">Date</th>
                                <th style="width: 15%">Time</th>
                                <th style="width: 20%">Patient</th>
                                <th style="width: 35%">Reason</th>
                                <th style="width: 15%">Actions</th>
                            </tr>
                        </thead>
                        <tbody>';

if (empty($pending_appointments)) {
    $content .= '
                            <tr>
                                <td colspan="5" class="text-center py-4">
                                    <div class="text-muted">
                                        <i class="fas fa-calendar-check fa-2x mb-3"></i>
                                        <p class="mb-0">No pending appointments</p>
                                    </div>
                                </td>
                            </tr>';
} else {
    foreach ($pending_appointments as $appointment) {
        $content .= '
                            <tr>
                                <td class="text-nowrap">' . date('M d, Y', strtotime($appointment['appointment_date'])) . '</td>
                                <td class="text-nowrap">' . date('h:i A', strtotime($appointment['appointment_time'])) . '</td>
                                <td class="text-nowrap">' . htmlspecialchars($appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']) . '</td>
                                <td class="text-truncate" style="max-width: 0;">' . htmlspecialchars($appointment['reason']) . '</td>
                                <td>
                                    <div class="btn-group">
                                        <form method="POST" class="d-inline" onsubmit="return confirm(\'Are you sure you want to accept this appointment?\');">
                                            <input type="hidden" name="appointment_id" value="' . $appointment['id'] . '">
                                            <input type="hidden" name="action" value="accept">
                                            <button type="submit" name="update_appointment" class="btn btn-sm btn-success" title="Accept">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        </form>
                                        <form method="POST" class="d-inline" onsubmit="return confirm(\'Are you sure you want to reject this appointment?\');">
                                            <input type="hidden" name="appointment_id" value="' . $appointment['id'] . '">
                                            <input type="hidden" name="action" value="reject">
                                            <input type="hidden" name="reason" value="Appointment rejected by doctor">
                                            <button type="submit" name="update_appointment" class="btn btn-sm btn-danger" title="Reject">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </form>
                                    </div>
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

        <!-- Today\'s Appointments -->
        <div class="col-lg-6">
            <div class="bg-white rounded p-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">
                        <i class="fas fa-calendar-day text-primary me-2"></i>Today\'s Schedule
                        <span class="badge bg-primary ms-2">' . count($today_appointments) . '</span>
                    </h5>
                </div>
                <div class="table-responsive custom-scroll" style="max-height: 400px;">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="sticky-top bg-white">
                            <tr>
                                <th style="width: 25%">Time</th>
                                <th style="width: 50%">Patient</th>
                                <th style="width: 25%">Status</th>
                            </tr>
                        </thead>
                        <tbody>';

if (empty($today_appointments)) {
    $content .= '
                            <tr>
                                <td colspan="3" class="text-center py-4">
                                    <div class="text-muted">
                                        <i class="fas fa-calendar-times fa-2x mb-3"></i>
                                        <p class="mb-0">No appointments today</p>
                                    </div>
                                </td>
                            </tr>';
} else {
    foreach ($today_appointments as $appointment) {
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
                                <td class="text-nowrap">' . date('h:i A', strtotime($appointment['appointment_time'])) . '</td>
                                <td class="text-nowrap">' . htmlspecialchars($appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']) . '</td>
                                <td><span class="badge bg-' . $status_class . '">' . ucfirst($appointment['status']) . '</span></td>
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

    <!-- Weekly Schedule Section -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="bg-white rounded p-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">
                        <i class="fas fa-calendar-week text-info me-2"></i>Weekly Schedule
                    </h5>
                    <a href="schedule.php" class="btn btn-sm btn-outline-info">
                        <i class="fas fa-edit me-1"></i>Edit
                    </a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width: 30%">Day</th>
                                <th style="width: 30%">Status</th>
                                <th style="width: 40%">Hours</th>
                            </tr>
                        </thead>
                        <tbody>';

$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
foreach ($days as $day) {
    $schedule = $schedules[$day] ?? null;
    $is_available = $schedule ? $schedule['is_available'] : false;
    $status_class = $is_available ? 'success' : 'danger';
    $status_text = $is_available ? 'Available' : 'Not Available';
    $hours = $is_available ? date('h:i A', strtotime($schedule['start_time'])) . ' - ' . date('h:i A', strtotime($schedule['end_time'])) : 'N/A';

    $content .= '
                            <tr>
                                <td class="text-nowrap">' . $day . '</td>
                                <td><span class="badge bg-' . $status_class . '">' . $status_text . '</span></td>
                                <td class="text-nowrap">' . $hours . '</td>
                            </tr>';
}

$content .= '
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>';

$content .= '
<style>
.custom-scroll {
    scrollbar-width: thin;
    scrollbar-color: rgba(0, 0, 0, 0.2) transparent;
}

.custom-scroll::-webkit-scrollbar {
    width: 6px;
}

.custom-scroll::-webkit-scrollbar-track {
    background: transparent;
}

.custom-scroll::-webkit-scrollbar-thumb {
    background-color: rgba(0, 0, 0, 0.2);
    border-radius: 3px;
}

.sticky-top {
    position: sticky;
    top: 0;
    z-index: 1;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
}
</style>';

require_once '../../layouts/main.php';
