<?php
require_once 'config.php';
require_once 'audit_logger.php';

class PrescriptionManager
{
    private static $instance = null;
    private $db;
    private $audit_logger;

    private function __construct()
    {
        $this->db = Database::getInstance();
        $this->audit_logger = AuditLogger::getInstance();
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function createPrescription($appointment_id, $medication_name, $dosage, $frequency, $duration, $instructions = '')
    {
        $query = "INSERT INTO prescriptions (appointment_id, medication_name, dosage, frequency, duration, instructions) 
                 VALUES (?, ?, ?, ?, ?, ?)";

        $stmt = $this->db->prepare($query);
        $stmt->bind_param("isssss", $appointment_id, $medication_name, $dosage, $frequency, $duration, $instructions);

        if ($stmt->execute()) {
            $prescription_id = $this->db->getLastId();
            $this->audit_logger->log('prescription_create', 'prescriptions', $prescription_id, null, [
                'appointment_id' => $appointment_id,
                'medication_name' => $medication_name,
                'dosage' => $dosage,
                'frequency' => $frequency,
                'duration' => $duration,
                'instructions' => $instructions
            ]);
            return $prescription_id;
        }
        return false;
    }

    public function getPatientPrescriptions($patient_id)
    {
        return $this->db->query("
            SELECT p.*, a.appointment_date, d.first_name as doctor_first_name, d.last_name as doctor_last_name
            FROM prescriptions p
            JOIN appointments a ON p.appointment_id = a.id
            JOIN doctors d ON a.doctor_id = d.id
            WHERE a.patient_id = $patient_id
            ORDER BY a.appointment_date DESC
        ")->fetch_all(MYSQLI_ASSOC);
    }

    public function getDoctorPrescriptions($doctor_id)
    {
        return $this->db->query("
            SELECT p.*, a.appointment_date, pt.first_name as patient_first_name, pt.last_name as patient_last_name
            FROM prescriptions p
            JOIN appointments a ON p.appointment_id = a.id
            JOIN patients pt ON a.patient_id = pt.id
            WHERE a.doctor_id = $doctor_id
            ORDER BY a.appointment_date DESC
        ")->fetch_all(MYSQLI_ASSOC);
    }
}
