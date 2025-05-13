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
$doctor = $doctor_result->fetch_assoc();
$doctor_id = $doctor['id'];

// Handle schedule updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_schedule'])) {
    $day = sanitize($_POST['day']);
    $start_time = sanitize($_POST['start_time']);
    $end_time = sanitize($_POST['end_time']);
    $is_available = isset($_POST['is_available']) ? 1 : 0;

    // Check if schedule exists for this day
    $check_query = "SELECT id FROM doctor_schedules WHERE doctor_id = $doctor_id AND day_of_week = '$day'";
    $check_result = $db->query($check_query);

    if ($check_result && $check_result->num_rows > 0) {
        // Update existing schedule
        $schedule = $check_result->fetch_assoc();
        $update_query = "UPDATE doctor_schedules SET 
                        start_time = '$start_time', 
                        end_time = '$end_time', 
                        is_available = $is_available 
                        WHERE id = " . $schedule['id'];
        $db->query($update_query);
    } else {
        // Insert new schedule
        $insert_query = "INSERT INTO doctor_schedules (doctor_id, day_of_week, start_time, end_time, is_available) 
                        VALUES ($doctor_id, '$day', '$start_time', '$end_time', $is_available)";
        $db->query($insert_query);
    }

    $_SESSION['flash_message'] = 'Schedule updated successfully.';
    $_SESSION['flash_type'] = 'success';
    redirect('/pages/doctor/schedule.php');
}

// Get current schedule
$schedule_query = "SELECT * FROM doctor_schedules WHERE doctor_id = $doctor_id ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')";
$schedule_result = $db->query($schedule_query);
$schedules = [];

if ($schedule_result) {
    while ($row = $schedule_result->fetch_assoc()) {
        $schedules[$row['day_of_week']] = $row;
    }
}

$content = '
<div class="row">
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-body">
                <h2 class="card-title mb-0">My Schedule</h2>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Weekly Schedule</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Day</th>
                                <th>Status</th>
                                <th>Available Hours</th>
                                <th>Actions</th>
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
                                <td>' . $day . '</td>
                                <td><span class="badge bg-' . $status_class . '">' . $status_text . '</span></td>
                                <td>' . $hours . '</td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-primary" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#scheduleModal"
                                            data-day="' . $day . '"
                                            data-start="' . ($schedule ? $schedule['start_time'] : '') . '"
                                            data-end="' . ($schedule ? $schedule['end_time'] : '') . '"
                                            data-available="' . ($is_available ? '1' : '0') . '">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                </td>
                            </tr>';
}

$content .= '
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Schedule Modal -->
<div class="modal fade" id="scheduleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Set Availability</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="day" class="form-label">Day</label>
                        <select class="form-select" id="day" name="day" required>
                            <option value="">Select Day</option>';

foreach ($days as $day) {
    $content .= '
                            <option value="' . $day . '">' . $day . '</option>';
}

$content .= '
                        </select>
                    </div>
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="is_available" name="is_available" checked>
                            <label class="form-check-label" for="is_available">Available</label>
                        </div>
                    </div>
                    <div id="timeInputs">
                        <div class="mb-3">
                            <label for="start_time" class="form-label">Start Time</label>
                            <input type="time" class="form-control" id="start_time" name="start_time" required>
                        </div>
                        <div class="mb-3">
                            <label for="end_time" class="form-label">End Time</label>
                            <input type="time" class="form-control" id="end_time" name="end_time" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="update_schedule" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const scheduleModal = document.getElementById("scheduleModal");
    const isAvailableCheckbox = document.getElementById("is_available");
    const timeInputs = document.getElementById("timeInputs");

    // Handle availability toggle
    isAvailableCheckbox.addEventListener("change", function() {
        timeInputs.style.display = this.checked ? "block" : "none";
        document.getElementById("start_time").required = this.checked;
        document.getElementById("end_time").required = this.checked;
    });

    // Handle edit button clicks
    document.querySelectorAll("[data-bs-target=\'#scheduleModal\']").forEach(button => {
        button.addEventListener("click", function() {
            const day = this.dataset.day;
            const start = this.dataset.start;
            const end = this.dataset.end;
            const available = this.dataset.available === "1";

            document.getElementById("day").value = day;
            document.getElementById("is_available").checked = available;
            document.getElementById("start_time").value = start;
            document.getElementById("end_time").value = end;
            
            timeInputs.style.display = available ? "block" : "none";
            document.getElementById("start_time").required = available;
            document.getElementById("end_time").required = available;
        });
    });
});
</script>';

require_once '../../layouts/main.php';
