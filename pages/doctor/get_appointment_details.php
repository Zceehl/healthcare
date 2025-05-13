<?php
require_once '../../config/config.php';

// Check if user is logged in and is a doctor
if (!isAuthenticated() || !isDoctor()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance();

// Get doctor ID
$user_id = $_SESSION['user_id'];
$doctor_query = "SELECT id FROM doctors WHERE user_id = $user_id";
$doctor_result = $db->query($doctor_query);

if (!$doctor_result || $doctor_result->num_rows === 0) {
    http_response_code(401);
    echo json_encode(['error' => 'Doctor not found']);
    exit;
}

$doctor = $doctor_result->fetch_assoc();
$doctor_id = $doctor['id'];

// Get appointment ID
$appointment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$appointment_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid appointment ID']);
    exit;
}

// Get appointment details
$query = "SELECT a.*, p.first_name as patient_first_name, p.last_name as patient_last_name,
                 p.phone_number, u.email as patient_email
          FROM appointments a
          INNER JOIN patients p ON a.patient_id = p.id
          INNER JOIN users u ON p.user_id = u.id
          WHERE a.id = $appointment_id AND a.doctor_id = $doctor_id";
$result = $db->query($query);

if (!$result) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $db->error]);
    exit;
}

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'Appointment not found']);
    exit;
}

$appointment = $result->fetch_assoc();

// Format the response
$response = [
    'patient_name' => $appointment['patient_first_name'] . ' ' . $appointment['patient_last_name'],
    'patient_email' => $appointment['patient_email'],
    'patient_phone' => $appointment['phone_number'],
    'appointment_date' => date('F j, Y', strtotime($appointment['appointment_date'])),
    'appointment_time' => date('h:i A', strtotime($appointment['appointment_time'])),
    'reason' => $appointment['reason'],
    'status' => $appointment['status']
];

echo json_encode($response);
