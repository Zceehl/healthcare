<?php
require_once '../../config/config.php';

// Check if user is admin
if (!isAuthenticated() || $_SESSION['role'] !== 'admin') {
    redirect('/pages/login.php');
}

$db = Database::getInstance();
$type = isset($_GET['type']) ? sanitize($_GET['type']) : '';
$format = isset($_GET['format']) ? sanitize($_GET['format']) : '';

if (!in_array($type, ['appointments', 'doctors']) || !in_array($format, ['html', 'csv'])) {
    die('Invalid report type or format');
}

// Get data based on report type
if ($type === 'appointments') {
    $data = $db->query("
        SELECT 
            a.*,
            CONCAT(p.first_name, ' ', p.last_name) as patient_name,
            CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
            d.specialization
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN doctors d ON a.doctor_id = d.id
        WHERE a.appointment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        ORDER BY a.appointment_date DESC
    ")->fetch_all(MYSQLI_ASSOC);

    $headers = ['Date', 'Time', 'Patient', 'Doctor', 'Specialization', 'Status'];
    $filename = 'appointments_report';
} else {
    $data = $db->query("
        SELECT 
            CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
            COUNT(a.id) as total_appointments,
            SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed_appointments,
            ROUND((SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) / COUNT(a.id)) * 100, 1) as completion_rate
        FROM doctors d
        JOIN users u ON d.user_id = u.id
        LEFT JOIN appointments a ON d.id = a.doctor_id
        WHERE u.status = 'active'
        GROUP BY d.id, d.first_name, d.last_name
        ORDER BY total_appointments DESC
    ")->fetch_all(MYSQLI_ASSOC);

    $headers = ['Doctor', 'Total Appointments', 'Completed', 'Completion Rate'];
    $filename = 'doctors_performance_report';
}

// Generate report based on format
if ($format === 'html') {
    // Set headers for HTML download
    header('Content-Type: text/html');
    header('Content-Disposition: attachment;filename="' . $filename . '.html"');

    // Start HTML output
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>' . ucfirst($type) . ' Report</title>
        <style>
            body { font-family: Arial, sans-serif; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f5f5f5; }
            tr:nth-child(even) { background-color: #f9f9f9; }
            .header { text-align: center; margin-bottom: 20px; }
            .footer { text-align: center; margin-top: 20px; font-size: 0.8em; color: #666; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>Healthcare System</h1>
            <h2>' . ucfirst($type) . ' Report</h2>
            <p>Generated on: ' . date('F d, Y H:i:s') . '</p>
        </div>
        
        <table>
            <thead>
                <tr>';

    // Add headers
    foreach ($headers as $header) {
        echo '<th>' . htmlspecialchars($header) . '</th>';
    }
    echo '</tr></thead><tbody>';

    // Add data rows
    foreach ($data as $row) {
        echo '<tr>';
        if ($type === 'appointments') {
            echo '<td>' . date('M d, Y', strtotime($row['appointment_date'])) . '</td>';
            echo '<td>' . date('h:i A', strtotime($row['appointment_time'])) . '</td>';
            echo '<td>' . htmlspecialchars($row['patient_name']) . '</td>';
            echo '<td>' . htmlspecialchars($row['doctor_name']) . '</td>';
            echo '<td>' . htmlspecialchars($row['specialization']) . '</td>';
            echo '<td>' . ucfirst($row['status']) . '</td>';
        } else {
            echo '<td>' . htmlspecialchars($row['doctor_name']) . '</td>';
            echo '<td>' . $row['total_appointments'] . '</td>';
            echo '<td>' . $row['completed_appointments'] . '</td>';
            echo '<td>' . $row['completion_rate'] . '%</td>';
        }
        echo '</tr>';
    }

    echo '</tbody></table>
        <div class="footer">
            <p>This is an automatically generated report from the Healthcare System.</p>
        </div>
    </body>
    </html>';
} else {
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment;filename="' . $filename . '.csv"');

    // Create output stream
    $output = fopen('php://output', 'w');

    // Add UTF-8 BOM for proper Excel encoding
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Add headers
    fputcsv($output, $headers);

    // Add data rows
    foreach ($data as $row) {
        if ($type === 'appointments') {
            fputcsv($output, [
                date('M d, Y', strtotime($row['appointment_date'])),
                date('h:i A', strtotime($row['appointment_time'])),
                $row['patient_name'],
                $row['doctor_name'],
                $row['specialization'],
                ucfirst($row['status'])
            ]);
        } else {
            fputcsv($output, [
                $row['doctor_name'],
                $row['total_appointments'],
                $row['completed_appointments'],
                $row['completion_rate'] . '%'
            ]);
        }
    }

    fclose($output);
}
