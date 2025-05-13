<?php
require_once '../../config/config.php';

// Check if user is logged in and is a patient
if (!isAuthenticated() || !isPatient()) {
    redirect('/pages/login.php');
}

$db = Database::getInstance();

// Get patient information
$user_id = $_SESSION['user_id'];
$patient_query = "SELECT p.*, u.email 
                 FROM patients p 
                 INNER JOIN users u ON p.user_id = u.id 
                 WHERE p.user_id = $user_id";
$patient_result = $db->query($patient_query);

if (!$patient_result || $patient_result->num_rows === 0) {
    $_SESSION['flash_message'] = 'Patient information not found.';
    $_SESSION['flash_type'] = 'danger';
    redirect('/pages/login.php');
}

$patient = $patient_result->fetch_assoc();
$patient_id = $patient['id'];

// Get today's appointments
$today = date('Y-m-d');
$today_appointments_query = "SELECT a.*, d.first_name as doctor_first_name, d.last_name as doctor_last_name,
                                   d.specialization
                            FROM appointments a
                            INNER JOIN doctors d ON a.doctor_id = d.id
                            WHERE a.patient_id = $patient_id 
                            AND a.appointment_date = '$today'
                            ORDER BY a.appointment_time ASC";
$today_appointments_result = $db->query($today_appointments_query);
$today_appointments = [];

if ($today_appointments_result) {
    while ($row = $today_appointments_result->fetch_assoc()) {
        $today_appointments[] = $row;
    }
}

// Get upcoming appointments
$upcoming_query = "SELECT a.*, d.first_name as doctor_first_name, d.last_name as doctor_last_name,
                         d.specialization
                  FROM appointments a
                  INNER JOIN doctors d ON a.doctor_id = d.id
                  WHERE a.patient_id = $patient_id 
                  AND a.appointment_date >= CURDATE()
                  AND a.status = 'scheduled'
                  ORDER BY a.appointment_date ASC, a.appointment_time ASC
                  LIMIT 5";
$upcoming_result = $db->query($upcoming_query);
$upcoming_appointments = [];

if ($upcoming_result) {
    while ($row = $upcoming_result->fetch_assoc()) {
        $upcoming_appointments[] = $row;
    }
}

// Get recent medical records
$medical_records_query = "SELECT mr.*, d.first_name as doctor_first_name, d.last_name as doctor_last_name,
                                d.specialization
                         FROM medical_records mr
                         INNER JOIN doctors d ON mr.doctor_id = d.id
                         WHERE mr.patient_id = $patient_id
                         ORDER BY mr.created_at DESC
                         LIMIT 5";
$medical_records_result = $db->query($medical_records_query);
$medical_records = [];

if ($medical_records_result) {
    while ($row = $medical_records_result->fetch_assoc()) {
        $medical_records[] = $row;
    }
}

$content = '
<div class="row">
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="card-title mb-1">Welcome, ' . htmlspecialchars($patient['first_name']) . '!</h2>
                        <p class="text-muted mb-0">Your upcoming appointments</p>
                    </div>
                    <div>
                        <a href="find_doctor.php" class="btn btn-primary">
                            <i class="fas fa-calendar-plus"></i> Book Appointment
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
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Upcoming Appointments</h5>
                <a href="appointments.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body">';

if (empty($upcoming_appointments)) {
    $content .= '
                <div class="text-center py-5">
                    <i class="fas fa-calendar fa-3x text-muted mb-3"></i>
                    <p class="text-muted mb-3">No upcoming appointments</p>
                    <a href="find_doctor.php" class="btn btn-primary">
                        <i class="fas fa-calendar-plus"></i> Book an Appointment
                    </a>
                </div>';
} else {
    $content .= '
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Doctor</th>
                                <th>Specialization</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>';
    foreach ($upcoming_appointments as $appointment) {
        $status_class = [
            'scheduled' => 'primary',
            'completed' => 'success',
            'cancelled' => 'danger',
            'no-show' => 'warning'
        ][$appointment['status']] ?? 'secondary';

        $content .= '
                            <tr>
                                <td>
                                    <div class="fw-medium">Dr. ' . htmlspecialchars($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']) . '</div>
                                </td>
                                <td>' . htmlspecialchars($appointment['specialization']) . '</td>
                                <td>' . date('M j, Y', strtotime($appointment['appointment_date'])) . '</td>
                                <td>' . date('h:i A', strtotime($appointment['appointment_time'])) . '</td>
                                <td><span class="badge bg-' . $status_class . '">' . ucfirst($appointment['status']) . '</span></td>
                                <td>
                                    <a href="appointments.php" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>';
    }
    $content .= '
                        </tbody>
                    </table>
                </div>';
}

$content .= '
            </div>
        </div>
    </div>
</div>';

require_once '../../layouts/main.php';
