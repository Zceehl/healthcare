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

// Handle schedule updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $day_of_week = sanitize($_POST['day_of_week']);
    $start_time = sanitize($_POST['start_time']);
    $end_time = sanitize($_POST['end_time']);
    $is_available = isset($_POST['is_available']) ? 1 : 0;

    // Check if schedule exists for this day
    $check_query = "SELECT id FROM doctor_schedules WHERE doctor_id = $doctor_id AND day_of_week = '$day_of_week'";
    $check_result = $db->query($check_query);

    if ($check_result->num_rows > 0) {
        // Update existing schedule
        $schedule = $check_result->fetch_assoc();
        $update_query = "UPDATE doctor_schedules 
                        SET start_time = '$start_time', 
                            end_time = '$end_time', 
                            is_available = $is_available 
                        WHERE id = " . $schedule['id'];

        if ($db->query($update_query)) {
            $_SESSION['flash_message'] = 'Schedule updated successfully.';
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = 'Failed to update schedule.';
            $_SESSION['flash_type'] = 'danger';
        }
    } else {
        // Insert new schedule
        $insert_query = "INSERT INTO doctor_schedules (doctor_id, day_of_week, start_time, end_time, is_available) 
                        VALUES ($doctor_id, '$day_of_week', '$start_time', '$end_time', $is_available)";

        if ($db->query($insert_query)) {
            $_SESSION['flash_message'] = 'Schedule added successfully.';
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = 'Failed to add schedule.';
            $_SESSION['flash_type'] = 'danger';
        }
    }
    redirect('/pages/doctor/manage-schedule.php');
}

// Get current schedules
$schedules_query = "SELECT * FROM doctor_schedules WHERE doctor_id = $doctor_id ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday')";
$schedules_result = $db->query($schedules_query);
$schedules = [];

if ($schedules_result) {
    while ($row = $schedules_result->fetch_assoc()) {
        $schedules[$row['day_of_week']] = $row;
    }
}

$content = '
<div class="row">
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <h2 class="card-title mb-0">Manage Schedule</h2>
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
                <form method="POST" class="needs-validation" novalidate>';

$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
foreach ($days as $day) {
    $schedule = $schedules[$day] ?? null;
    $content .= '
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <h5 class="mb-3">' . $day . '</h5>
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="is_available_' . $day . '" 
                                               name="is_available" ' . ($schedule && $schedule['is_available'] ? 'checked' : '') . '>
                                        <label class="form-check-label" for="is_available_' . $day . '">Available</label>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Start Time</label>
                                    <input type="time" class="form-control" name="start_time" 
                                           value="' . ($schedule ? $schedule['start_time'] : '08:00') . '" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">End Time</label>
                                    <input type="time" class="form-control" name="end_time" 
                                           value="' . ($schedule ? $schedule['end_time'] : '17:00') . '" required>
                                </div>
                                <div class="col-md-3">
                                    <input type="hidden" name="day_of_week" value="' . $day . '">
                                </div>
                            </div>
                        </div>
                    </div>';
}

$content .= '
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Schedule
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const forms = document.querySelectorAll(".needs-validation");
    Array.from(forms).forEach(form => {
        form.addEventListener("submit", event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add("was-validated");
        });
    });
});
</script>
';

require_once '../../layouts/main.php';
