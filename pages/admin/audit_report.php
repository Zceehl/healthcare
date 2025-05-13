<?php
require_once '../../config/config.php';

// Ensure only admin can access
if (!isAuthenticated() || $_SESSION['role'] !== 'admin') {
    redirect('/pages/login.php');
}

$db = Database::getInstance();
$message = '';
$error = '';

// Get filter parameters
$start_date = isset($_GET['start_date']) ? sanitize($_GET['start_date']) : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? sanitize($_GET['end_date']) : date('Y-m-d');
$action_type = isset($_GET['action_type']) ? sanitize($_GET['action_type']) : '';
$table_name = isset($_GET['table_name']) ? sanitize($_GET['table_name']) : '';
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

// Build query
$query = "
    SELECT al.*, u.email, u.role
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.id
    WHERE DATE(al.created_at) BETWEEN '$start_date' AND '$end_date'
";

if ($action_type) {
    $query .= " AND al.action_type = '$action_type'";
}
if ($table_name) {
    $query .= " AND al.table_name = '$table_name'";
}
if ($user_id) {
    $query .= " AND al.user_id = $user_id";
}

$query .= " ORDER BY al.created_at DESC";

// Get unique action types and table names for filters
$action_types = $db->query("SELECT DISTINCT action_type FROM audit_logs ORDER BY action_type")->fetch_all(MYSQLI_ASSOC);
$table_names = $db->query("SELECT DISTINCT table_name FROM audit_logs ORDER BY table_name")->fetch_all(MYSQLI_ASSOC);
$users = $db->query("SELECT DISTINCT u.id, u.email, u.role FROM users u JOIN audit_logs al ON u.id = al.user_id ORDER BY u.email")->fetch_all(MYSQLI_ASSOC);

// Handle export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="audit_logs_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');

    // Add headers
    fputcsv($output, ['Timestamp', 'User', 'Role', 'Action Type', 'Table', 'Record ID', 'Old Values', 'New Values', 'IP Address']);

    // Add data
    $logs = $db->query($query)->fetch_all(MYSQLI_ASSOC);
    foreach ($logs as $log) {
        fputcsv($output, [
            date('Y-m-d H:i:s', strtotime($log['created_at'])),
            $log['email'] ?? 'System',
            $log['role'] ?? 'System',
            $log['action_type'],
            $log['table_name'],
            $log['record_id'],
            $log['old_values'],
            $log['new_values'],
            $log['ip_address']
        ]);
    }

    fclose($output);
    exit;
}

// Get logs for display
$logs = $db->query($query)->fetch_all(MYSQLI_ASSOC);

$content = '
<div class="container-fluid">
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">Audit Log Report</h5>
        </div>
        <div class="card-body">
            <form method="GET" class="mb-4">
                <div class="row">
                    <div class="col-md-3">
                        <div class="mb-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-control" name="start_date" value="' . $start_date . '">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="mb-3">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" name="end_date" value="' . $end_date . '">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="mb-3">
                            <label class="form-label">Action Type</label>
                            <select class="form-select" name="action_type">
                                <option value="">All Actions</option>';
foreach ($action_types as $type) {
    $content .= '<option value="' . $type['action_type'] . '"' . ($action_type === $type['action_type'] ? ' selected' : '') . '>' . ucfirst($type['action_type']) . '</option>';
}
$content .= '
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="mb-3">
                            <label class="form-label">Table</label>
                            <select class="form-select" name="table_name">
                                <option value="">All Tables</option>';
foreach ($table_names as $table) {
    $content .= '<option value="' . $table['table_name'] . '"' . ($table_name === $table['table_name'] ? ' selected' : '') . '>' . ucfirst($table['table_name']) . '</option>';
}
$content .= '
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="mb-3">
                            <label class="form-label">User</label>
                            <select class="form-select" name="user_id">
                                <option value="">All Users</option>';
foreach ($users as $user) {
    $content .= '<option value="' . $user['id'] . '"' . ($user_id === $user['id'] ? ' selected' : '') . '>' . htmlspecialchars($user['email']) . ' (' . ucfirst($user['role']) . ')</option>';
}
$content .= '
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                        <a href="?export=csv' . (isset($_SERVER['QUERY_STRING']) ? '&' . $_SERVER['QUERY_STRING'] : '') . '" class="btn btn-success">Export to CSV</a>
                        <a href="audit_report.php" class="btn btn-secondary">Reset Filters</a>
                    </div>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Timestamp</th>
                            <th>User</th>
                            <th>Role</th>
                            <th>Action</th>
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
