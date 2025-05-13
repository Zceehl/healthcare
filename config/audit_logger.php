<?php
require_once 'config.php';

class AuditLogger
{
    private static $instance = null;
    private $db;

    private function __construct()
    {
        $this->db = Database::getInstance();
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function log($action_type, $table_name, $record_id, $old_values = null, $new_values = null)
    {
        try {
            $user_id = $_SESSION['user_id'] ?? null;
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

            // Convert arrays to JSON strings
            $old_values_json = $old_values ? json_encode($old_values) : null;
            $new_values_json = $new_values ? json_encode($new_values) : null;

            // Sanitize inputs
            $action_type = $this->db->escape($action_type);
            $table_name = $this->db->escape($table_name);
            $record_id = (int)$record_id;
            $user_id = $user_id ? (int)$user_id : 'NULL';
            $old_values_json = $old_values_json ? "'" . $this->db->escape($old_values_json) . "'" : 'NULL';
            $new_values_json = $new_values_json ? "'" . $this->db->escape($new_values_json) . "'" : 'NULL';
            $ip_address = $ip_address ? "'" . $this->db->escape($ip_address) . "'" : 'NULL';
            $user_agent = $user_agent ? "'" . $this->db->escape($user_agent) . "'" : 'NULL';

            $query = "INSERT INTO audit_logs (
                        user_id, action_type, table_name, record_id, 
                        old_values, new_values, ip_address, user_agent
                    ) VALUES (
                        $user_id, '$action_type', '$table_name', $record_id,
                        $old_values_json, $new_values_json, $ip_address, $user_agent
                    )";

            return $this->db->query($query);
        } catch (Exception $e) {
            // Log error to error log
            error_log("Audit Log Error: " . $e->getMessage());
            return false;
        }
    }

    public function getLogs($filters = [])
    {
        $query = "
            SELECT al.*, u.email, u.role
            FROM audit_logs al
            LEFT JOIN users u ON al.user_id = u.id
            WHERE 1=1
        ";

        if (!empty($filters['start_date'])) {
            $query .= " AND DATE(al.created_at) >= '" . $this->db->escape($filters['start_date']) . "'";
        }
        if (!empty($filters['end_date'])) {
            $query .= " AND DATE(al.created_at) <= '" . $this->db->escape($filters['end_date']) . "'";
        }
        if (!empty($filters['action_type'])) {
            $query .= " AND al.action_type = '" . $this->db->escape($filters['action_type']) . "'";
        }
        if (!empty($filters['table_name'])) {
            $query .= " AND al.table_name = '" . $this->db->escape($filters['table_name']) . "'";
        }
        if (!empty($filters['user_id'])) {
            $query .= " AND al.user_id = " . (int)$filters['user_id'];
        }

        $query .= " ORDER BY al.created_at DESC";

        return $this->db->query($query)->fetch_all(MYSQLI_ASSOC);
    }
}
