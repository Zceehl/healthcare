<?php
require_once '../../config/config.php';

// Check if user is admin
if (!isAuthenticated() || $_SESSION['role'] !== 'admin') {
    redirect('/pages/login.php');
}

$db = Database::getInstance();

// Get statistics for reports
$stats = [
    'total_patients' => $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'patient' AND status = 'active'")->fetch_assoc()['count'],
    'total_doctors' => $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'doctor' AND status = 'active'")->fetch_assoc()['count'],
    'total_appointments' => $db->query("SELECT COUNT(*) as count FROM appointments")->fetch_assoc()['count'],
    'completed_appointments' => $db->query("SELECT COUNT(*) as count FROM appointments WHERE status = 'completed'")->fetch_assoc()['count']
];

// Get monthly appointment statistics
$monthly_stats = $db->query("
    SELECT 
        DATE_FORMAT(appointment_date, '%b %Y') as month,
        COUNT(*) as total,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
    FROM appointments
    WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
    GROUP BY DATE_FORMAT(appointment_date, '%Y-%m')
    ORDER BY appointment_date ASC
")->fetch_all(MYSQLI_ASSOC);

// Get doctor performance
$doctor_stats = $db->query("
    SELECT 
        CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
        COUNT(a.id) as total_appointments,
        SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed_appointments
    FROM doctors d
    JOIN users u ON d.user_id = u.id
    LEFT JOIN appointments a ON d.id = a.doctor_id
    WHERE u.role = 'doctor' AND u.status = 'active'
    GROUP BY d.id
    ORDER BY total_appointments DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

$content = '
<div class="container-fluid">
    <!-- Report Generation -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Generate Reports</h5>
                <div class="btn-group">
                    <a href="generate_report.php?type=appointments&format=html" class="btn btn-outline-primary">
                        <i class="fas fa-file-code"></i> Appointments (HTML)
                    </a>
                    <a href="generate_report.php?type=appointments&format=csv" class="btn btn-outline-primary">
                        <i class="fas fa-file-excel"></i> Appointments (CSV)
                    </a>
                    <a href="generate_report.php?type=doctors&format=html" class="btn btn-outline-primary">
                        <i class="fas fa-file-code"></i> Doctors (HTML)
                    </a>
                    <a href="generate_report.php?type=doctors&format=csv" class="btn btn-outline-primary">
                        <i class="fas fa-file-excel"></i> Doctors (CSV)
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle mb-2 text-muted">Total Patients</h6>
                            <h2 class="card-title mb-0">' . $stats['total_patients'] . '</h2>
                        </div>
                        <div class="bg-secondary bg-opacity-10 p-3 rounded">
                            <i class="fas fa-hospital-user fa-2x text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle mb-2 text-muted">Total Doctors</h6>
                            <h2 class="card-title mb-0">' . $stats['total_doctors'] . '</h2>
                        </div>
                        <div class="bg-success bg-opacity-10 p-3 rounded">
                            <i class="fas fa-user-md fa-2x text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle mb-2 text-muted">Total Appointments</h6>
                            <h2 class="card-title mb-0">' . $stats['total_appointments'] . '</h2>
                        </div>
                        <div class="bg-info bg-opacity-10 p-3 rounded">
                            <i class="fas fa-calendar-check fa-2x text-info"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle mb-2 text-muted">Completed Appointments</h6>
                            <h2 class="card-title mb-0">' . $stats['completed_appointments'] . '</h2>
                        </div>
                        <div class="bg-success bg-opacity-10 p-3 rounded">
                            <i class="fas fa-check-circle fa-2x text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Monthly Appointment Trends</h5>
                </div>
                <div class="card-body">
                    <div style="height: 400px; position: relative;">
                        <canvas id="appointmentChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Appointment Status Distribution</h5>
                </div>
                <div class="card-body">
                    <div style="height: 400px; position: relative;">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Doctor Performance -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Top Performing Doctors</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Doctor</th>
                                    <th>Total Appointments</th>
                                    <th>Completed</th>
                                    <th>Completion Rate</th>
                                </tr>
                            </thead>
                            <tbody>';
foreach ($doctor_stats as $doctor) {
    $completion_rate = $doctor['total_appointments'] > 0
        ? round(($doctor['completed_appointments'] / $doctor['total_appointments']) * 100, 1)
        : 0;
    $content .= '
                                <tr>
                                    <td>' . htmlspecialchars($doctor['doctor_name']) . '</td>
                                    <td>' . $doctor['total_appointments'] . '</td>
                                    <td>' . $doctor['completed_appointments'] . '</td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar bg-success" role="progressbar" 
                                                style="width: ' . $completion_rate . '%;" 
                                                aria-valuenow="' . $completion_rate . '" 
                                                aria-valuemin="0" 
                                                aria-valuemax="100">' . $completion_rate . '%</div>
                                        </div>
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
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Monthly Appointment Trends Chart
const appointmentCtx = document.getElementById("appointmentChart").getContext("2d");
new Chart(appointmentCtx, {
    type: "line",
    data: {
        labels: ' . json_encode(array_column($monthly_stats, "month")) . ',
        datasets: [{
            label: "Total Appointments",
            data: ' . json_encode(array_column($monthly_stats, "total")) . ',
            borderColor: "#3498db",
            tension: 0.1,
            fill: false
        }, {
            label: "Completed",
            data: ' . json_encode(array_column($monthly_stats, "completed")) . ',
            borderColor: "#2ecc71",
            tension: 0.1,
            fill: false
        }, {
            label: "Cancelled",
            data: ' . json_encode(array_column($monthly_stats, "cancelled")) . ',
            borderColor: "#e74c3c",
            tension: 0.1,
            fill: false
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: "top",
                align: "center",
                labels: {
                    boxWidth: 12,
                    padding: 15
                }
            }
        },
        scales: {
            x: {
                grid: {
                    display: false
                },
                ticks: {
                    maxRotation: 0,
                    autoSkip: true
                }
            },
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1,
                    precision: 0
                }
            }
        }
    }
});

// Appointment Status Distribution Chart
const statusCtx = document.getElementById("statusChart").getContext("2d");
new Chart(statusCtx, {
    type: "doughnut",
    data: {
        labels: ["Completed", "Pending", "Cancelled"],
        datasets: [{
            data: [
                ' . $stats['completed_appointments'] . ',
                ' . ($stats['total_appointments'] - $stats['completed_appointments']) . ',
                ' . $db->query("SELECT COUNT(*) as count FROM appointments WHERE status = 'cancelled'")->fetch_assoc()['count'] . '
            ],
            backgroundColor: ["#2ecc71", "#f1c40f", "#e74c3c"]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: "bottom",
                align: "center",
                labels: {
                    boxWidth: 12,
                    padding: 15
                }
            }
        },
        cutout: "60%"
    }
});
</script>
';

require_once '../../layouts/main.php';
