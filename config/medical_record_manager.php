<?php
require_once 'config.php';
require_once 'audit_logger.php';

class MedicalRecordManager
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

    public function createRecord($patient_id, $doctor_id, $diagnosis, $treatment, $notes = '')
    {
        $query = "INSERT INTO medical_records (patient_id, doctor_id, record_date, diagnosis, treatment, notes) 
                 VALUES (?, ?, CURDATE(), ?, ?, ?)";

        $stmt = $this->db->prepare($query);
        $stmt->bind_param("iisss", $patient_id, $doctor_id, $diagnosis, $treatment, $notes);

        if ($stmt->execute()) {
            $record_id = $this->db->getLastId();
            $this->audit_logger->log('medical_record_create', 'medical_records', $record_id, null, [
                'patient_id' => $patient_id,
                'doctor_id' => $doctor_id,
                'diagnosis' => $diagnosis,
                'treatment' => $treatment,
                'notes' => $notes
            ]);
            return $record_id;
        }
        return false;
    }

    public function updateRecord($record_id, $diagnosis, $treatment, $notes = '')
    {
        // Get old values
        $old_values = $this->db->query("SELECT * FROM medical_records WHERE id = $record_id")->fetch_assoc();

        $query = "UPDATE medical_records SET diagnosis = ?, treatment = ?, notes = ? WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("sssi", $diagnosis, $treatment, $notes, $record_id);

        if ($stmt->execute()) {
            $this->audit_logger->log('medical_record_update', 'medical_records', $record_id, $old_values, [
                'diagnosis' => $diagnosis,
                'treatment' => $treatment,
                'notes' => $notes
            ]);
            return true;
        }
        return false;
    }

    public function getPatientRecords($patient_id)
    {
        return $this->db->query("
            SELECT mr.*, d.first_name as doctor_first_name, d.last_name as doctor_last_name
            FROM medical_records mr
            JOIN doctors d ON mr.doctor_id = d.id
            WHERE mr.patient_id = $patient_id
            ORDER BY mr.record_date DESC
        ")->fetch_all(MYSQLI_ASSOC);
    }

    public function getDoctorRecords($doctor_id)
    {
        return $this->db->query("
            SELECT mr.*, p.first_name as patient_first_name, p.last_name as patient_last_name
            FROM medical_records mr
            JOIN patients p ON mr.patient_id = p.id
            WHERE mr.doctor_id = $doctor_id
            ORDER BY mr.record_date DESC
        ")->fetch_all(MYSQLI_ASSOC);
    }
}
