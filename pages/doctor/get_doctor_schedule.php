<?php
require_once '../../config/config.php';

// Check if user is logged in
if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance();

// Get doctor ID
$doctor_id = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;
$day = isset($_GET['day']) ? sanitize($_GET['day']) : null;

if (!$doctor_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Doctor ID is required']);
    exit;
}

// Build query
$query = "SELECT * FROM doctor_schedules WHERE doctor_id = $doctor_id";
if ($day) {
    $query .= " AND day_of_week = '$day'";
}
$query .= " ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), start_time";

$result = $db->query($query);
$schedules = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        if ($day) {
            // Return single schedule
            echo json_encode($row);
            exit;
        } else {
            // Return all schedules with day as key
            $schedules[$row['day_of_week']] = $row;
        }
    }
}

if ($day) {
    // No schedule found for specific day
    echo json_encode(null);
} else {
    // Return all schedules
    echo json_encode($schedules);
}
