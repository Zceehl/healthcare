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

// Get specific appointment record if requested
$appointment_id = isset($_GET['appointment_id']) ? (int)$_GET['appointment_id'] : null;
$where_clause = "WHERE mr.patient_id = $patient_id";

if ($appointment_id) {
    // Verify the appointment belongs to this patient
    $verify_query = "SELECT * FROM appointments WHERE id = $appointment_id AND patient_id = $patient_id";
    $verify_result = $db->query($verify_query);

    if (!$verify_result || $verify_result->num_rows === 0) {
        $_SESSION['flash_message'] = 'Invalid appointment.';
        $_SESSION['flash_type'] = 'danger';
        redirect('/pages/patient/medical-history.php');
    }

    $where_clause .= " AND mr.appointment_id = $appointment_id";
}

// Get medical records
$records_query = "SELECT mr.*, d.first_name as doctor_first_name, d.last_name as doctor_last_name,
                        d.specialization, a.appointment_date, a.appointment_time
                 FROM medical_records mr
                 INNER JOIN doctors d ON mr.doctor_id = d.id
                 LEFT JOIN appointments a ON mr.appointment_id = a.id
                 $where_clause
                 ORDER BY mr.created_at DESC";
$records_result = $db->query($records_query);
$medical_records = [];

if ($records_result) {
    while ($row = $records_result->fetch_assoc()) {
        $medical_records[] = $row;
    }
}

$content = '
<div class="row">
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <h2 class="card-title mb-0">Medical History</h2>
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
            <div class="card-body">';

if (empty($medical_records)) {
    $content .= '
                <div class="text-center py-5">
                    <i class="fas fa-file-medical fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No medical records found</p>
                </div>';
} else {
    $content .= '
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Doctor</th>
                                <th>Diagnosis</th>
                                <th>Prescription</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>';
    foreach ($medical_records as $record) {
        $content .= '
                            <tr>
                                <td>
                                    ' . date('M d, Y', strtotime($record['created_at'])) . '
                                    ' . ($record['appointment_date'] ? '<br><small class="text-muted">Appointment: ' . date('M d, Y', strtotime($record['appointment_date'])) . ' ' . date('h:i A', strtotime($record['appointment_time'])) . '</small>' : '') . '
                                </td>
                                <td>
                                    Dr. ' . htmlspecialchars($record['doctor_first_name'] . ' ' . $record['doctor_last_name']) . '
                                    <br>
                                    <small class="text-muted">' . htmlspecialchars($record['specialization']) . '</small>
                                </td>
                                <td>' . nl2br(htmlspecialchars($record['diagnosis'])) . '</td>
                                <td>' . nl2br(htmlspecialchars($record['prescription'])) . '</td>
                                <td>' . nl2br(htmlspecialchars($record['notes'])) . '</td>
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
</div>
';

require_once '../../layouts/main.php';
