<?php
require_once '../../config/config.php';

// Check if user is admin
if (!isAuthenticated() || $_SESSION['role'] !== 'admin') {
    redirect('/pages/login.php');
}

$db = Database::getInstance();

// Get counts for dashboard
$stats = $db->query("
    SELECT 
        (SELECT COUNT(*) FROM users WHERE role = 'doctor' AND status = 'active') as active_doctors,
        (SELECT COUNT(*) FROM users WHERE role = 'patient' AND status = 'active') as active_patients,
        (SELECT COUNT(*) FROM appointments WHERE status = 'pending') as pending_appointments,
        (SELECT COUNT(*) FROM appointments WHERE status = 'scheduled') as scheduled_appointments,
        (
            SELECT COUNT(*) 
            FROM appointments 
            WHERE DATE(appointment_date) = CURDATE() 
            AND status IN ('completed', 'scheduled', 'pending')
        ) as total_today_appointments,
        (
            SELECT COUNT(*) 
            FROM appointments 
            WHERE DATE(appointment_date) = CURDATE() 
            AND status = 'completed'
        ) as today_appointments,
        (
            SELECT COUNT(*) 
            FROM appointments 
            WHERE DATE(appointment_date) = CURDATE() 
            AND status = 'cancelled'
        ) as today_cancelled,
        (
            SELECT COUNT(*) 
            FROM appointments 
            WHERE DATE(appointment_date) = CURDATE() 
            AND status = 'no-show'
        ) as today_no_show
")->fetch_all(MYSQLI_ASSOC)[0];

// Get recent appointments
$recent_appointments = $db->query("
    SELECT a.*, 
           p.first_name as patient_first_name, 
           p.last_name as patient_last_name,
           d.first_name as doctor_first_name, 
           d.last_name as doctor_last_name,
           d.specialization
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN doctors d ON a.doctor_id = d.id
    WHERE a.status IN ('pending', 'scheduled')
    ORDER BY 
        CASE 
            WHEN a.status = 'pending' THEN 1
            WHEN a.status = 'scheduled' THEN 2
        END,
        a.appointment_date ASC, 
        a.appointment_time ASC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// Get recent audit logs
$recent_logs = $db->query("
    SELECT al.*, u.email, u.role
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.id
    ORDER BY al.created_at DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// Get appointment statistics for the last 7 days
$weekly_stats = $db->query("
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as total,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
        SUM(CASE WHEN status = 'no-show' THEN 1 ELSE 0 END) as no_show
    FROM appointments
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date ASC
")->fetch_all(MYSQLI_ASSOC);

$content = '
<div class="container py-4">
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Active Doctors</h5>
                    <h2 class="mb-0">' . $stats['active_doctors'] . '</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Active Patients</h5>
                    <h2 class="mb-0">' . $stats['active_patients'] . '</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h5 class="card-title">Pending Appointments</h5>
                    <h2 class="mb-0">' . $stats['pending_appointments'] . '</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title">Scheduled Appointments</h5>
                    <h2 class="mb-0">' . $stats['scheduled_appointments'] . '</h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Today\'s Statistics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Today\'s Total</h5>
                    <h2 class="text-primary">' . $stats['total_today_appointments'] . '</h2>
                    <small class="text-muted">(Scheduled + Pending + Completed)</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Today\'s Completed</h5>
                    <h2 class="text-success">' . $stats['today_appointments'] . '</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Today\'s Cancelled</h5>
                    <h2 class="text-danger">' . $stats['today_cancelled'] . '</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Today\'s No Shows</h5>
                    <h2 class="text-warning">' . $stats['today_no_show'] . '</h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Weekly Statistics Chart -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Weekly Appointment Statistics</h5>
                </div>
                <div class="card-body">
                    <canvas id="weeklyStatsChart" height="100"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Recent Appointments -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Upcoming & Pending Appointments</h5>
                    <a href="appointments.php" class="btn btn-sm btn-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive scrollable-table" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-hover mb-0">
                            <thead class="sticky-top bg-white">
                                <tr>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Patient</th>
                                    <th>Doctor</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>';
foreach ($recent_appointments as $appointment) {
    $status_class = $appointment['status'] === 'pending' ? 'bg-warning' : 'bg-info';
    $status_text = $appointment['status'] === 'pending' ? 'Pending' : 'Scheduled';
    $content .= '
                                <tr>
                                    <td>' . date('M d, Y', strtotime($appointment['appointment_date'])) . '</td>
                                    <td>' . date('h:i A', strtotime($appointment['appointment_time'])) . '</td>
                                    <td>' . htmlspecialchars($appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']) . '</td>
                                    <td>
                                        Dr. ' . htmlspecialchars($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']) . '
                                        <br>
                                        <small class="text-muted">' . htmlspecialchars($appointment['specialization']) . '</small>
                                    </td>
                                    <td><span class="badge ' . $status_class . '">' . $status_text . '</span></td>
                                </tr>';
}
$content .= '
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Recent Activity</h5>
                    <a href="audit_report.php" class="btn btn-sm btn-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive scrollable-table" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-hover mb-0">
                            <thead class="sticky-top bg-white">
                                <tr>
                                    <th>Time</th>
                                    <th>User</th>
                                    <th>Action Type</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody>';
foreach ($recent_logs as $log) {
    $content .= '
                                <tr>
                                    <td>' . date('M d, Y h:i A', strtotime($log['created_at'])) . '</td>
                                    <td>' . htmlspecialchars($log['email'] ?? 'System') . '</td>
                                    <td>' . htmlspecialchars($log['action_type'] ?? '') . '</td>
                                    <td>' . htmlspecialchars($log['details'] ?? '') . '</td>
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
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Weekly Statistics Chart
const ctx = document.getElementById("weeklyStatsChart").getContext("2d");
new Chart(ctx, {
    type: "line",
    data: {
        labels: ' . json_encode(array_column($weekly_stats, "date")) . ',
        datasets: [{
            label: "Total Appointments",
            data: ' . json_encode(array_column($weekly_stats, "total")) . ',
            borderColor: "#3498db",
            tension: 0.1,
            fill: false
        }, {
            label: "Completed",
            data: ' . json_encode(array_column($weekly_stats, "completed")) . ',
            borderColor: "#2ecc71",
            tension: 0.1,
            fill: false
        }, {
            label: "Cancelled",
            data: ' . json_encode(array_column($weekly_stats, "cancelled")) . ',
            borderColor: "#e74c3c",
            tension: 0.1,
            fill: false
        }, {
            label: "No Shows",
            data: ' . json_encode(array_column($weekly_stats, "no_show")) . ',
            borderColor: "#f1c40f",
            tension: 0.1,
            fill: false
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: "top",
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});
</script>

<style>
/* Custom scrollbar styling */
.scrollable-table::-webkit-scrollbar {
    width: 8px;
}

.scrollable-table::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.scrollable-table::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 4px;
}

.scrollable-table::-webkit-scrollbar-thumb:hover {
    background: #555;
}

/* Sticky header styling */
.sticky-top {
    position: sticky;
    top: 0;
    z-index: 1;
    box-shadow: 0 2px 2px rgba(0,0,0,0.1);
}

/* Table hover effect */
.table-hover tbody tr:hover {
    background-color: rgba(0,0,0,0.02);
}

/* Ensure table headers are visible */
.table thead th {
    background-color: #fff;
    border-bottom: 2px solid #dee2e6;
}

/* Remove bottom margin from card body when using p-0 */
.card-body.p-0 .table {
    margin-bottom: 0;
}
</style>';

require_once '../../layouts/main.php';
