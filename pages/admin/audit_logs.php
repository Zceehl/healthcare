<?php
require_once '../../config/config.php';

// Ensure only admin can access
if (!isAuthenticated() || $_SESSION['role'] !== 'admin') {
    redirect('/pages/login.php');
}

$db = Database::getInstance();

// Get all audit logs with user information
$logs = $db->query("
    SELECT al.*, u.email, u.role
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.id
    ORDER BY al.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

$content = '
<div class="container-fluid">
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">Audit Logs</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Timestamp</th>
                            <th>User</th>
                            <th>Role</th>
                            <th>Action Type</th>
                            <th>Table</th>
                            <th>Record ID</th>
                            <th>Changes</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>';

foreach ($logs as $log) {
    $changes = '';
    if ($log['old_values'] && $log['new_values']) {
        $old = json_decode($log['old_values'], true);
        $new = json_decode($log['new_values'], true);
        $changes = '<ul class="list-unstyled mb-0">';
        foreach ($new as $key => $value) {
            if (isset($old[$key]) && $old[$key] !== $value) {
                $changes .= '<li><strong>' . ucfirst($key) . ':</strong> ' . htmlspecialchars($old[$key]) . ' → ' . htmlspecialchars($value) . '</li>';
            }
        }
        $changes .= '</ul>';
    }

    $content .= '
                        <tr>
                            <td>' . date('M d, Y h:i A', strtotime($log['created_at'])) . '</td>
                            <td>' . htmlspecialchars($log['email'] ?? 'System') . '</td>
                            <td>' . htmlspecialchars($log['role'] ?? 'System') . '</td>
                            <td>' . htmlspecialchars($log['action_type']) . '</td>
                            <td>' . htmlspecialchars($log['table_name']) . '</td>
                            <td>' . $log['record_id'] . '</td>
                            <td>' . $changes . '</td>
                            <td>' . htmlspecialchars($log['ip_address'] ?? '') . '</td>
                        </tr>';
}

$content .= '
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>';

require_once '../../layouts/main.php';
