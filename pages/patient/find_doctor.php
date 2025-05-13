<?php
require_once '../../config/config.php';

// Check if user is logged in and is a patient
if (!isAuthenticated() || !isPatient()) {
    redirect('/pages/login.php');
}

$db = Database::getInstance();

// Get search parameters
$specialization = isset($_GET['specialization']) ? sanitize($_GET['specialization']) : '';
$day = isset($_GET['day']) ? sanitize($_GET['day']) : '';

// Get all specializations
$specializations_query = "SELECT DISTINCT specialization FROM doctors WHERE specialization IS NOT NULL ORDER BY specialization";
$specializations_result = $db->query($specializations_query);
$specializations = [];

if ($specializations_result) {
    while ($row = $specializations_result->fetch_assoc()) {
        $specializations[] = $row['specialization'];
    }
}

// Build the doctor search query
$query = "SELECT d.*, u.email, 
          GROUP_CONCAT(DISTINCT ds.day_of_week) as available_days
          FROM doctors d 
          INNER JOIN users u ON d.user_id = u.id 
          LEFT JOIN doctor_schedules ds ON d.id = ds.doctor_id AND ds.is_available = 1
          WHERE u.status = 'active'";

if ($specialization) {
    $query .= " AND d.specialization = '$specialization'";
}

if ($day) {
    $query .= " AND ds.day_of_week = '$day'";
}

$query .= " GROUP BY d.id ORDER BY d.first_name, d.last_name";

$doctors_result = $db->query($query);
$doctors = [];

if ($doctors_result) {
    while ($row = $doctors_result->fetch_assoc()) {
        $row['available_days'] = $row['available_days'] ? explode(',', $row['available_days']) : [];
        $doctors[] = $row;
    }
}

$content = '
<div class="container py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h2 class="card-title mb-4">Find a Doctor</h2>
                    <form method="GET" class="row g-3">
                        <div class="col-md-5">
                            <label for="specialization" class="form-label">Specialization</label>
                            <select class="form-select" id="specialization" name="specialization">
                                <option value="">All Specializations</option>';
foreach ($specializations as $spec) {
    $content .= '
                                <option value="' . htmlspecialchars($spec) . '" ' . ($specialization === $spec ? 'selected' : '') . '>' . htmlspecialchars($spec) . '</option>';
}
$content .= '
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label for="day" class="form-label">Preferred Day</label>
                            <select class="form-select" id="day" name="day">
                                <option value="">Any Day</option>
                                <option value="Monday" ' . ($day === 'Monday' ? 'selected' : '') . '>Monday</option>
                                <option value="Tuesday" ' . ($day === 'Tuesday' ? 'selected' : '') . '>Tuesday</option>
                                <option value="Wednesday" ' . ($day === 'Wednesday' ? 'selected' : '') . '>Wednesday</option>
                                <option value="Thursday" ' . ($day === 'Thursday' ? 'selected' : '') . '>Thursday</option>
                                <option value="Friday" ' . ($day === 'Friday' ? 'selected' : '') . '>Friday</option>
                                <option value="Saturday" ' . ($day === 'Saturday' ? 'selected' : '') . '>Saturday</option>
                                <option value="Sunday" ' . ($day === 'Sunday' ? 'selected' : '') . '>Sunday</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search me-2"></i>Search
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">';

if (empty($doctors)) {
    $content .= '
        <div class="col-12">
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>No doctors found matching your criteria.
            </div>
        </div>';
} else {
    foreach ($doctors as $doctor) {
        $content .= '
        <div class="col-md-6 col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="flex-shrink-0">
                            <img src="' . APP_URL . '/uploads/profile_images/' . (!empty($doctor['profile_image']) ? htmlspecialchars($doctor['profile_image']) : 'default-profile.jpg') . '" 
                                 alt="Dr. ' . htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']) . '" 
                                 class="rounded-circle" 
                                 style="width: 64px; height: 64px; object-fit: cover;"
                                 onerror="this.src=\'' . APP_URL . '/uploads/profile_images/default-profile.jpg\'">
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="card-title mb-1">Dr. ' . htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']) . '</h5>
                            <p class="text-muted mb-0">' . htmlspecialchars($doctor['specialization']) . '</p>
                        </div>
                    </div>
                    <p class="card-text">' . htmlspecialchars($doctor['bio'] ?? 'No bio available.') . '</p>
                    <div class="mb-3">
                        <small class="text-muted">
                            <i class="fas fa-graduation-cap me-1"></i> ' . htmlspecialchars($doctor['qualification']) . '
                        </small>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted">
                            <i class="fas fa-clock me-1"></i> Available Days: ' .
            (empty($doctor['available_days']) ? 'None' : implode(', ', $doctor['available_days'])) . '
                        </small>
                    </div>
                    <div class="mb-3">
                        <label for="day-' . $doctor['id'] . '" class="form-label">Select Day</label>
                        <select class="form-select day-select" id="day-' . $doctor['id'] . '" data-doctor-id="' . $doctor['id'] . '">
                            <option value="">Choose a day...</option>';
        foreach ($doctor['available_days'] as $available_day) {
            $content .= '
                            <option value="' . $available_day . '">' . $available_day . '</option>';
        }
        $content .= '
                        </select>
                    </div>
                    <div class="schedule-time mb-3" id="schedule-' . $doctor['id'] . '">
                        <small class="text-muted">Select a day to view schedule</small>
                    </div>
                    <div class="d-grid">
                        <button type="button" class="btn btn-primary book-appointment" 
                                data-doctor-id="' . $doctor['id'] . '"
                                data-doctor-name="Dr. ' . htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']) . '"
                                disabled>
                            <i class="fas fa-calendar-check me-2"></i>Book Appointment
                        </button>
                    </div>
                </div>
            </div>
        </div>';
    }
}

$content .= '
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const daySelects = document.querySelectorAll(".day-select");
    const bookButtons = document.querySelectorAll(".book-appointment");

    // Handle day selection
    daySelects.forEach(select => {
        select.addEventListener("change", function() {
            const doctorId = this.dataset.doctorId;
            const day = this.value;
            
            if (day) {
                fetchSchedule(doctorId, day);
            } else {
                const scheduleDisplay = document.getElementById("schedule-" + doctorId);
                scheduleDisplay.innerHTML = \'<small class="text-muted">Select a day to view schedule</small>\';
                const bookButton = this.closest(".card").querySelector(".book-appointment");
                bookButton.disabled = true;
            }
        });
    });

    // Fetch doctor schedule
    function fetchSchedule(doctorId, day) {
        const scheduleDisplay = document.getElementById("schedule-" + doctorId);
        const bookButton = scheduleDisplay.closest(".card").querySelector(".book-appointment");
        
        scheduleDisplay.innerHTML = \'<div class="text-center"><div class="spinner-border spinner-border-sm text-primary" role="status"></div></div>\';
        bookButton.disabled = true;

        fetch("get_doctor_schedule.php?doctor_id=" + doctorId + "&day=" + day)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let statusClass = "text-success";
                    let statusIcon = "fa-calendar-check";
                    
                    if (data.day_status === "pending") {
                        statusClass = "text-warning";
                        statusIcon = "fa-clock";
                    } else if (data.day_status === "booked") {
                        statusClass = "text-danger";
                        statusIcon = "fa-calendar-times";
                    }

                    scheduleDisplay.innerHTML = 
                        \'<small class="text-muted">\' +
                        \'<i class="fas fa-clock me-1"></i> \' + data.start_time + \' - \' + data.end_time + \'<br>\' +
                        \'<i class="fas \' + statusIcon + \' me-1 \' + statusClass + \'"></i> \' + data.message +
                        \'</small>\';
                    bookButton.disabled = !data.is_available;
                } else {
                    scheduleDisplay.innerHTML = \'<small class="text-danger">\' + data.message + \'</small>\';
                    bookButton.disabled = true;
                }
            })
            .catch(error => {
                scheduleDisplay.innerHTML = \'<small class="text-danger">Error loading schedule</small>\';
                bookButton.disabled = true;
            });
    }

    // Handle booking button click
    bookButtons.forEach(button => {
        button.addEventListener("click", function() {
            const doctorId = this.dataset.doctorId;
            const day = this.closest(".card").querySelector(".day-select").value;
            if (doctorId && day) {
                window.location.href = "book_appointment.php?doctor_id=" + doctorId + "&day=" + day;
            }
        });
    });
});
</script>';

require_once '../../layouts/main.php';
