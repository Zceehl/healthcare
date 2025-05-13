<?php
require_once '../../config/config.php';

// Check if user is logged in
if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance();

// Get parameters
$doctor_id = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;
$day = isset($_GET['day']) ? sanitize($_GET['day']) : '';

if (!$doctor_id || !$day) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

// Get patient ID
$user_id = $_SESSION['user_id'];
$patient_query = "SELECT id FROM patients WHERE user_id = $user_id";
$patient_result = $db->query($patient_query);

if (!$patient_result || $patient_result->num_rows === 0) {
    http_response_code(401);
    echo json_encode(['error' => 'Patient not found']);
    exit;
}

$patient = $patient_result->fetch_assoc();
$patient_id = $patient['id'];

// Calculate the next occurrence of the selected day
$today = new DateTime();
$target_day = new DateTime("next $day");
if ($target_day <= $today) {
    $target_day->modify('next ' . $day);
}
$appointment_date = $target_day->format('Y-m-d');

// Get doctor's schedule for the selected day
$schedule_query = "SELECT * FROM doctor_schedules 
                  WHERE doctor_id = $doctor_id 
                  AND day_of_week = '$day'
                  AND is_available = 1";
$schedule_result = $db->query($schedule_query);

if (!$schedule_result || $schedule_result->num_rows === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Doctor is not available on this day.'
    ]);
    exit;
}

$schedule = $schedule_result->fetch_assoc();

// Get booked slots for the selected day
$booked_slots_query = "SELECT appointment_time, status 
                      FROM appointments 
                      WHERE doctor_id = $doctor_id 
                      AND appointment_date = '$appointment_date'
                      AND status IN ('scheduled', 'pending')";
$booked_slots_result = $db->query($booked_slots_query);
$booked_slots = [];
$day_status = 'available';
$has_active_appointments = false;

if ($booked_slots_result) {
    while ($row = $booked_slots_result->fetch_assoc()) {
        $booked_slots[] = $row['appointment_time'];
        // If any slot is pending or scheduled, mark the day as unavailable
        if ($row['status'] === 'pending') {
            $day_status = 'pending';
            $has_active_appointments = true;
        } else if ($row['status'] === 'scheduled') {
            $day_status = 'booked';
            $has_active_appointments = true;
        }
    }
}

// Calculate available time slots
$start_time = strtotime($schedule['start_time']);
$end_time = strtotime($schedule['end_time']);
$interval = 30 * 60; // 30 minutes in seconds

$available_slots = [];
$total_slots = 0;

for ($time = $start_time; $time < $end_time; $time += $interval) {
    $time_slot = date('H:i:s', $time);
    $total_slots++;

    if (!in_array($time_slot, $booked_slots)) {
        $available_slots[] = $time_slot;
    }
}

// Format times for display
$start_time_display = date('h:i A', $start_time);
$end_time_display = date('h:i A', $end_time);

// Check if patient already has an appointment on this day
$existing_appointment_query = "SELECT id FROM appointments 
                             WHERE doctor_id = $doctor_id 
                             AND patient_id = $patient_id
                             AND appointment_date = '$appointment_date'
                             AND status IN ('scheduled', 'pending')";
$existing_appointment_result = $db->query($existing_appointment_query);
$has_existing_appointment = $existing_appointment_result && $existing_appointment_result->num_rows > 0;

echo json_encode([
    'success' => true,
    'date' => $appointment_date,
    'start_time' => $start_time_display,
    'end_time' => $end_time_display,
    'available_slots' => count($available_slots),
    'total_slots' => $total_slots,
    'is_available' => !$has_existing_appointment && !$has_active_appointments && count($available_slots) > 0,
    'day_status' => $day_status,
    'message' => $has_existing_appointment ?
        'You already have a pending/scheduled appointment for this day.' : ($has_active_appointments ?
            'This day is not available for booking due to existing appointments.' :
            'Slots available for booking.')
]);
