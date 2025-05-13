<?php
require_once '../../config/config.php';

// Check if user is logged in and is a patient
if (!isAuthenticated() || !isPatient()) {
    redirect('/pages/login.php');
}

$db = Database::getInstance();

// Get parameters
$doctor_id = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;
$day = isset($_GET['day']) ? sanitize($_GET['day']) : '';

if (!$doctor_id || !$day) {
    setFlashMessage('error', 'Invalid request parameters.');
    redirect('/pages/patient/find_doctor.php');
}

// Get patient ID
$user_id = $_SESSION['user_id'];
$patient_query = "SELECT id FROM patients WHERE user_id = $user_id";
$patient_result = $db->query($patient_query);

if (!$patient_result || $patient_result->num_rows === 0) {
    setFlashMessage('error', 'Patient not found.');
    redirect('/pages/patient/find_doctor.php');
}

$patient = $patient_result->fetch_assoc();
$patient_id = $patient['id'];

// Get doctor's schedule for the selected day
$schedule_query = "SELECT * FROM doctor_schedules 
                  WHERE doctor_id = $doctor_id 
                  AND day_of_week = '$day'
                  AND is_available = 1";
$schedule_result = $db->query($schedule_query);

if (!$schedule_result || $schedule_result->num_rows === 0) {
    setFlashMessage('error', 'Doctor is not available on this day.');
    redirect('/pages/patient/find_doctor.php');
}

$schedule = $schedule_result->fetch_assoc();

// Calculate available time slots
$start_time = strtotime($schedule['start_time']);
$end_time = strtotime($schedule['end_time']);
$interval = 30 * 60; // 30 minutes in seconds

$available_slots = [];
for ($time = $start_time; $time < $end_time; $time += $interval) {
    $time_slot = date('H:i:s', $time);
    $available_slots[] = $time_slot;
}

// Calculate available dates (next 4 weeks)
$available_dates = [];
$today = new DateTime();
$end_date = (new DateTime())->modify('+4 weeks');

// Get weekly appointment counts for the doctor
$weekly_counts_query = "SELECT 
    DATE_FORMAT(appointment_date, '%Y-%u') as week,
    COUNT(*) as appointment_count
    FROM appointments 
    WHERE doctor_id = $doctor_id 
    AND appointment_date >= CURDATE()
    AND appointment_date <= DATE_ADD(CURDATE(), INTERVAL 4 WEEK)
    AND status IN ('scheduled', 'pending')
    GROUP BY DATE_FORMAT(appointment_date, '%Y-%u')";
$weekly_counts_result = $db->query($weekly_counts_query);
$weekly_counts = [];

if ($weekly_counts_result) {
    while ($row = $weekly_counts_result->fetch_assoc()) {
        $weekly_counts[$row['week']] = $row['appointment_count'];
    }
}

// Maximum appointments per week (adjust this number as needed)
$max_appointments_per_week = 20;

while ($today <= $end_date) {
    if ($today->format('l') === $day) {
        $week_key = $today->format('Y-W');
        $current_count = isset($weekly_counts[$week_key]) ? $weekly_counts[$week_key] : 0;

        // Only add the date if the week hasn't reached its limit
        if ($current_count < $max_appointments_per_week) {
            $available_dates[] = $today->format('Y-m-d');
        }
    }
    $today->modify('+1 day');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appointment_date = sanitize($_POST['appointment_date']);
    $appointment_time = sanitize($_POST['appointment_time']);
    $reason = sanitize($_POST['reason']);

    if (empty($appointment_date) || empty($appointment_time) || empty($reason)) {
        setFlashMessage('error', 'Please fill in all required fields.');
    } else {
        // Verify that the selected date matches the doctor's schedule
        $selected_day = date('l', strtotime($appointment_date));
        if ($selected_day !== $day) {
            setFlashMessage('error', 'Selected date does not match doctor\'s schedule.');
        } else {
            // Check weekly appointment limit
            $selected_week = date('Y-W', strtotime($appointment_date));
            $weekly_count_query = "SELECT COUNT(*) as count 
                                FROM appointments 
                                WHERE doctor_id = $doctor_id 
                                AND DATE_FORMAT(appointment_date, '%Y-%u') = '$selected_week'
                                AND status IN ('scheduled', 'pending')";
            $weekly_count_result = $db->query($weekly_count_query);
            $weekly_count = $weekly_count_result->fetch_assoc()['count'];

            if ($weekly_count >= $max_appointments_per_week) {
                setFlashMessage('error', 'This week has reached its maximum appointment limit. Please select a different week.');
            } else {
                // Get booked slots for the selected day
                $booked_slots_query = "SELECT appointment_time 
                                    FROM appointments 
                                    WHERE doctor_id = $doctor_id 
                                    AND appointment_date = '$appointment_date'
                                    AND status IN ('scheduled', 'pending')";
                $booked_slots_result = $db->query($booked_slots_query);
                $booked_slots = [];

                if ($booked_slots_result) {
                    while ($row = $booked_slots_result->fetch_assoc()) {
                        $booked_slots[] = $row['appointment_time'];
                    }
                }

                // Check if patient already has an appointment on this day
                $existing_appointment_query = "SELECT id FROM appointments 
                                            WHERE doctor_id = $doctor_id 
                                            AND patient_id = $patient_id
                                            AND appointment_date = '$appointment_date'
                                            AND status IN ('scheduled', 'pending')";
                $existing_appointment_result = $db->query($existing_appointment_query);

                if ($existing_appointment_result && $existing_appointment_result->num_rows > 0) {
                    setFlashMessage('error', 'You already have a pending/scheduled appointment for this day.');
                } elseif (!in_array($appointment_time, $available_slots)) {
                    setFlashMessage('error', 'Selected time slot is no longer available.');
                } else {
                    // Verify the appointment time is within doctor's schedule
                    $appointment_time_obj = strtotime($appointment_time);
                    if ($appointment_time_obj < $start_time || $appointment_time_obj >= $end_time) {
                        setFlashMessage('error', 'Selected time is outside doctor\'s schedule.');
                    } else {
                        // Insert the appointment
                        $insert_query = "INSERT INTO appointments (doctor_id, patient_id, appointment_date, appointment_time, reason, status, created_at) 
                                        VALUES ($doctor_id, $patient_id, '$appointment_date', '$appointment_time', '$reason', 'pending', NOW())";

                        if ($db->query($insert_query)) {
                            $appointment_id = $db->getLastId();

                            // Log the appointment creation
                            $audit_logger = AuditLogger::getInstance();
                            $audit_logger->log(
                                'appointment_create',
                                'appointments',
                                $appointment_id,
                                null,
                                [
                                    'doctor_id' => $doctor_id,
                                    'patient_id' => $patient_id,
                                    'appointment_date' => $appointment_date,
                                    'appointment_time' => $appointment_time,
                                    'reason' => $reason,
                                    'status' => 'pending'
                                ]
                            );

                            setFlashMessage('success', 'Appointment request submitted successfully.');
                            redirect('/pages/patient/dashboard.php');
                        } else {
                            setFlashMessage('error', 'Failed to book appointment. Please try again.');
                        }
                    }
                }
            }
        }
    }
}

// Get doctor details
$doctor_query = "SELECT d.*, u.email 
                FROM doctors d 
                INNER JOIN users u ON d.user_id = u.id 
                WHERE d.id = $doctor_id";
$doctor_result = $db->query($doctor_query);

if (!$doctor_result || $doctor_result->num_rows === 0) {
    setFlashMessage('error', 'Doctor not found.');
    redirect('/pages/patient/find_doctor.php');
}

$doctor = $doctor_result->fetch_assoc();

$content = '
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h2 class="card-title mb-4">Book Appointment</h2>
                    
                    <div class="alert alert-info mb-4">
                        <h5 class="alert-heading">Appointment Details</h5>
                        <p class="mb-1"><strong>Doctor:</strong> Dr. ' . htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']) . '</p>
                        <p class="mb-1"><strong>Specialization:</strong> ' . htmlspecialchars($doctor['specialization']) . '</p>
                        <p class="mb-1"><strong>Available Day:</strong> ' . $day . '</p>
                        <p class="mb-0"><strong>Available Hours:</strong> ' . date('h:i A', strtotime($schedule['start_time'])) . ' - ' . date('h:i A', strtotime($schedule['end_time'])) . '</p>
                    </div>

                    <form method="POST" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label for="appointment_date" class="form-label">Select Date</label>
                            <select class="form-select" id="appointment_date" name="appointment_date" required>
                                <option value="">Choose a date...</option>';
foreach ($available_dates as $date) {
    $content .= '
                                <option value="' . $date . '">' . date('F j, Y', strtotime($date)) . '</option>';
}
$content .= '
                            </select>
                            <div class="invalid-feedback">Please select a date.</div>
                        </div>

                        <div class="mb-3">
                            <label for="appointment_time" class="form-label">Select Time Slot</label>
                            <select class="form-select" id="appointment_time" name="appointment_time" required>
                                <option value="">Choose a time slot...</option>';
foreach ($available_slots as $slot) {
    $content .= '
                                <option value="' . $slot . '">' . date('h:i A', strtotime($slot)) . '</option>';
}
$content .= '
                            </select>
                            <div class="invalid-feedback">Please select a time slot.</div>
                        </div>

                        <div class="mb-3">
                            <label for="reason" class="form-label">Reason for Visit</label>
                            <textarea class="form-control" id="reason" name="reason" rows="3" required></textarea>
                            <div class="invalid-feedback">Please provide a reason for your visit.</div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-calendar-check me-2"></i>Book Appointment
                            </button>
                            <a href="find_doctor.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Back to Search
                            </a>
                        </div>
                    </form>
                </div>
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
</script>';

require_once '../../layouts/main.php';
