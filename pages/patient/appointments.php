<?php
require_once '../../config/config.php';

// Check if user is logged in and is a patient
if (!isAuthenticated() || !isPatient()) {
    redirect('/pages/login.php');
}

$db = Database::getInstance();

// Get patient ID
$user_id = $_SESSION['user_id'];
$patient_query = "SELECT id FROM patients WHERE user_id = $user_id";
$patient_result = $db->query($patient_query);
$patient = $patient_result->fetch_assoc();
$patient_id = $patient['id'];

// Handle appointment cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_appointment'])) {
    $appointment_id = (int)$_POST['appointment_id'];

    // Verify the appointment belongs to this patient
    $verify_query = "SELECT * FROM appointments WHERE id = $appointment_id AND patient_id = $patient_id";
    $verify_result = $db->query($verify_query);

    if ($verify_result && $verify_result->num_rows > 0) {
        $appointment = $verify_result->fetch_assoc();

        // Only allow cancellation of pending or scheduled appointments
        if ($appointment['status'] === 'pending' || $appointment['status'] === 'scheduled') {
            // Get old values before update
            $old_values = $appointment;

            $update_query = "UPDATE appointments SET status = 'cancelled' WHERE id = $appointment_id";
            if ($db->query($update_query)) {
                // Log the cancellation
                $audit_logger = AuditLogger::getInstance();
                $audit_logger->log(
                    'appointment_cancel',
                    'appointments',
                    $appointment_id,
                    ['status' => $old_values['status']],
                    ['status' => 'cancelled']
                );

                setFlashMessage('success', 'Appointment cancelled successfully.');
            } else {
                setFlashMessage('error', 'Failed to cancel appointment.');
            }
        } else {
            setFlashMessage('warning', 'Only pending or scheduled appointments can be cancelled.');
        }
    } else {
        setFlashMessage('error', 'Invalid appointment.');
    }
    redirect('/pages/patient/appointments.php');
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$date_filter = isset($_GET['date']) ? sanitize($_GET['date']) : '';

// Build query
$query = "SELECT a.*, d.first_name as doctor_first_name, d.last_name as doctor_last_name,
                 d.specialization
          FROM appointments a
          INNER JOIN doctors d ON a.doctor_id = d.id
          WHERE a.patient_id = $patient_id";

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
                    <h2 class="card-title mb-0">My Appointments</h2>
                    <div>
                        <a href="dashboard.php" class="btn btn-outline-primary me-2">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                        <a href="find_doctor.php" class="btn btn-primary">
                            <i class="fas fa-calendar-plus"></i> Book New Appointment
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
                                <th>Doctor</th>
                                <th>Specialization</th>
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
                                <td>Dr. ' . htmlspecialchars($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']) . '</td>
                                <td>' . htmlspecialchars($appointment['specialization']) . '</td>
                                <td>' . htmlspecialchars($appointment['reason']) . '</td>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="badge bg-' . $status_class . ' mb-1">' . ucfirst($appointment['status']) . '</span>';

        if ($appointment['status'] === 'cancelled' && !empty($appointment['cancel_reason'])) {
            $content .= '
                                        <small class="text-muted">Reason: ' . htmlspecialchars($appointment['cancel_reason']) . '</small>';
        } elseif ($appointment['status'] === 'rejected' && !empty($appointment['reject_reason'])) {
            $content .= '
                                        <small class="text-muted">Reason: ' . htmlspecialchars($appointment['reject_reason']) . '</small>';
        }

        $content .= '
                                    </div>
                                </td>
                                <td>';

        // Show cancel button for pending or scheduled appointments
        if ($appointment['status'] === 'pending' || $appointment['status'] === 'scheduled') {
            $content .= '
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="appointment_id" value="' . $appointment['id'] . '">
                                        <button type="submit" name="cancel_appointment" class="btn btn-sm btn-danger" onclick="return confirm(\'Are you sure you want to cancel this appointment?\')">
                                            <i class="fas fa-times"></i> Cancel
                                        </button>
                                    </form>';
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
</div>';

$content .= '
<style>
/* Aggressive anti-flicker measures */
* {
    transition: none !important;
    animation: none !important;
}

.modal {
    display: none !important;
    opacity: 1 !important;
    visibility: hidden !important;
}

.modal.show {
    display: block !important;
    visibility: visible !important;
}

.modal-backdrop {
    display: none !important;
    opacity: 1 !important;
}

.modal-backdrop.show {
    display: block !important;
}

/* Prevent any content shifts */
body.modal-open {
    padding-right: 0 !important;
    overflow: hidden !important;
    position: fixed !important;
    width: 100% !important;
}

/* Disable all hover effects */
.table-hover tbody tr:hover {
    background-color: transparent !important;
}

.btn:hover {
    transform: none !important;
}

/* Force hardware acceleration */
.modal-dialog {
    transform: none !important;
    will-change: auto !important;
}
</style>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Handle appointment cancellation
    const cancelForms = document.querySelectorAll("form[id^=\'cancelForm\']");
    cancelForms.forEach(form => {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitButton = this.querySelector("button[type=\'submit\']");
            const modal = this.closest(".modal");
            
            // Disable submit button and show loading state
            submitButton.disabled = true;
            submitButton.innerHTML = \'<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Cancelling...\';
            
            // Send the request
            fetch(window.location.href, {
                method: "POST",
                body: formData
            })
            .then(response => {
                if (!response.ok) throw new Error("Network response was not ok");
                return response.text();
            })
            .then(() => {
                // Immediately remove modal and backdrop without animation
                modal.style.display = "none";
                modal.classList.remove("show");
                document.body.classList.remove("modal-open");
                const backdrop = document.querySelector(".modal-backdrop");
                if (backdrop) {
                    backdrop.style.display = "none";
                    backdrop.classList.remove("show");
                    backdrop.remove();
                }
                
                // Force a clean reload
                window.location.href = window.location.pathname + "?success=1";
            })
            .catch(error => {
                console.error("Error:", error);
                submitButton.disabled = false;
                submitButton.innerHTML = "Confirm Cancellation";
                alert("Failed to cancel appointment. Please try again.");
            });
        });
    });

    // Prevent any modal transitions
    const modals = document.querySelectorAll(".modal");
    modals.forEach(modal => {
        modal.addEventListener("show.bs.modal", function(e) {
            e.preventDefault();
            this.style.display = "block";
            this.classList.add("show");
            document.body.classList.add("modal-open");
            const backdrop = document.createElement("div");
            backdrop.className = "modal-backdrop show";
            document.body.appendChild(backdrop);
        });

        modal.addEventListener("hide.bs.modal", function(e) {
            e.preventDefault();
            this.style.display = "none";
            this.classList.remove("show");
            document.body.classList.remove("modal-open");
            const backdrop = document.querySelector(".modal-backdrop");
            if (backdrop) {
                backdrop.style.display = "none";
                backdrop.classList.remove("show");
                backdrop.remove();
            }
        });
    });
});
</script>';

// Add success message handling at the top of the file after the initial checks
if (isset($_GET['success']) && $_GET['success'] === '1') {
    setFlashMessage('success', 'Appointment cancelled successfully.');
    // Remove the success parameter from URL
    echo "<script>history.replaceState({}, '', window.location.pathname);</script>";
}

require_once '../../layouts/main.php';
