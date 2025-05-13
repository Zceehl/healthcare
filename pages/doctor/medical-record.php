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

// Get appointment ID from URL
$appointment_id = isset($_GET['appointment_id']) ? (int)$_GET['appointment_id'] : 0;

if (!$appointment_id) {
    $_SESSION['flash_message'] = 'Invalid appointment ID.';
    $_SESSION['flash_type'] = 'danger';
    redirect('/pages/doctor/appointments.php');
}

// Get appointment details with patient information
$appointment_query = "SELECT a.*, p.first_name as patient_first_name, p.last_name as patient_last_name,
                            p.date_of_birth, p.gender, p.address,
                            u.email as patient_email
                     FROM appointments a
                     INNER JOIN patients p ON a.patient_id = p.id
                     INNER JOIN users u ON p.user_id = u.id
                     WHERE a.id = $appointment_id AND a.doctor_id = $doctor_id";
$appointment_result = $db->query($appointment_query);

if (!$appointment_result || $appointment_result->num_rows === 0) {
    $_SESSION['flash_message'] = 'Appointment not found.';
    $_SESSION['flash_type'] = 'danger';
    redirect('/pages/doctor/appointments.php');
}

$appointment = $appointment_result->fetch_assoc();

// Handle medical record submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $diagnosis = sanitize($_POST['diagnosis']);
    $prescription = sanitize($_POST['prescription']);
    $notes = sanitize($_POST['notes']);

    if (empty($diagnosis)) {
        setFlashMessage('error', 'Diagnosis is required.');
    } else {
        // Update or insert medical record
        $record_query = "INSERT INTO medical_records 
                        (appointment_id, patient_id, doctor_id, diagnosis, prescription, notes, created_at) 
                        VALUES ($appointment_id, " . $appointment['patient_id'] . ", $doctor_id, '$diagnosis', '$prescription', '$notes', NOW())
                        ON DUPLICATE KEY UPDATE 
                        diagnosis = '$diagnosis',
                        prescription = '$prescription',
                        notes = '$notes',
                        updated_at = NOW()";

        if ($db->query($record_query)) {
            // Get the record ID
            $record_id = $db->getLastId();
            if (!$record_id) {
                // If it was an update, get the existing record ID
                $record_id = $db->query("SELECT id FROM medical_records WHERE appointment_id = $appointment_id")->fetch_assoc()['id'];
            }

            // Log the medical record creation/update
            $audit_logger = AuditLogger::getInstance();
            $audit_logger->log(
                $record_id ? 'medical_record_update' : 'medical_record_create',
                'medical_records',
                $record_id,
                $record_id ? [
                    'diagnosis' => $record['diagnosis'] ?? null,
                    'prescription' => $record['prescription'] ?? null,
                    'notes' => $record['notes'] ?? null
                ] : null,
                [
                    'diagnosis' => $diagnosis,
                    'prescription' => $prescription,
                    'notes' => $notes
                ]
            );

            // Update appointment status to completed
            $update_query = "UPDATE appointments SET status = 'completed' WHERE id = $appointment_id";
            $db->query($update_query);

            setFlashMessage('success', 'Medical record saved successfully.');
            redirect('/pages/doctor/appointments.php');
        } else {
            setFlashMessage('error', 'Failed to save medical record.');
        }
    }
}

// Get existing medical record if any
$record_query = "SELECT * FROM medical_records WHERE appointment_id = $appointment_id";
$record_result = $db->query($record_query);
$record = $record_result->fetch_assoc();

$content = '
<div class="row">
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <h2 class="card-title mb-0">Medical Record</h2>
                    <div>
                        <a href="appointments.php" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left"></i> Back to Appointments
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <!-- Patient Information -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Patient Information</h5>
            </div>
            <div class="card-body">
                <h4>' . $appointment['patient_first_name'] . ' ' . $appointment['patient_last_name'] . '</h4>
                <p class="mb-1"><strong>Date of Birth:</strong> ' . date('M j, Y', strtotime($appointment['date_of_birth'])) . '</p>
                <p class="mb-1"><strong>Gender:</strong> ' . ($appointment['gender'] === 'M' ? 'Male' : ($appointment['gender'] === 'F' ? 'Female' : 'Other')) . '</p>
                <p class="mb-1"><strong>Address:</strong> ' . htmlspecialchars($appointment['address']) . '</p>
                <p class="mb-0">
                    <strong>Email:</strong> 
                    <a href="mailto:' . $appointment['patient_email'] . '" class="text-decoration-none">
                        ' . $appointment['patient_email'] . '
                    </a>
                </p>
            </div>
        </div>

        <!-- Appointment Details -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Appointment Details</h5>
            </div>
            <div class="card-body">
                <p class="mb-1"><strong>Date:</strong> ' . date('M j, Y', strtotime($appointment['appointment_date'])) . '</p>
                <p class="mb-1"><strong>Time:</strong> ' . date('h:i A', strtotime($appointment['appointment_time'])) . '</p>
                <p class="mb-1"><strong>Status:</strong> <span class="badge bg-' .
    ($appointment['status'] === 'completed' ? 'success' : ($appointment['status'] === 'cancelled' ? 'danger' : ($appointment['status'] === 'no-show' ? 'warning' : 'primary'))) . '">' .
    ucfirst($appointment['status']) . '</span></p>
                <p class="mb-0"><strong>Reason for Visit:</strong> ' . htmlspecialchars($appointment['reason']) . '</p>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <!-- Medical Record Form -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Medical Record</h5>
            </div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <div class="mb-3">
                        <label for="diagnosis" class="form-label">Diagnosis <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="diagnosis" name="diagnosis" rows="3" required>' .
    (isset($record['diagnosis']) ? htmlspecialchars($record['diagnosis']) : '') . '</textarea>
                        <div class="invalid-feedback">Please provide a diagnosis.</div>
                    </div>
                    <div class="mb-3">
                        <label for="prescription" class="form-label">Prescription</label>
                        <textarea class="form-control" id="prescription" name="prescription" rows="3">' .
    (isset($record['prescription']) ? htmlspecialchars($record['prescription']) : '') . '</textarea>
                    </div>
                    <div class="mb-3">
                        <label for="notes" class="form-label">Additional Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3">' .
    (isset($record['notes']) ? htmlspecialchars($record['notes']) : '') . '</textarea>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save Medical Record
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Form validation
(function() {
    "use strict";
    const forms = document.querySelectorAll(".needs-validation");
    Array.from(forms).forEach(form => {
        form.addEventListener("submit", event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add("was-validated");
        }, false);
    });
})();
</script>
';

require_once '../../layouts/main.php';
