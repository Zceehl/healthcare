<?php
// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Session configuration - MUST be before session_start()
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));

// Start session
session_start();

// Application configuration
define('APP_NAME', 'Healthcare Appointment System');
define('APP_URL', 'http://localhost/healthcare');
define('APP_ROOT', dirname(__DIR__));

// Time zone
date_default_timezone_set('UTC');

// Security configuration
define('HASH_COST', 12); // For password hashing
define('TOKEN_EXPIRY', 3600); // 1 hour

// File upload configuration
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_FILE_TYPES', ['jpg', 'jpeg', 'png', 'pdf']);

// Email configuration
define('SMTP_HOST', 'smtp.example.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@example.com');
define('SMTP_PASSWORD', 'your-password');
define('SMTP_FROM_EMAIL', 'noreply@healthcare.com');
define('SMTP_FROM_NAME', APP_NAME);

// Load required files
require_once 'database.php';

// Include helper classes
require_once __DIR__ . '/audit_logger.php';
require_once __DIR__ . '/medical_record_manager.php';
require_once __DIR__ . '/prescription_manager.php';
require_once __DIR__ . '/file_uploader.php';

// Get manager instances
$audit_logger = AuditLogger::getInstance();
$medical_record_manager = MedicalRecordManager::getInstance();
$prescription_manager = PrescriptionManager::getInstance();
$file_uploader = FileUploader::getInstance();

// Helper functions
function redirect($path)
{
    header("Location: " . APP_URL . $path);
    exit();
}

function isAuthenticated()
{
    return isset($_SESSION['user_id']);
}

function isAdmin()
{
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isDoctor()
{
    return isset($_SESSION['role']) && $_SESSION['role'] === 'doctor';
}

function isStaff()
{
    return isset($_SESSION['role']) && $_SESSION['role'] === 'staff';
}

function isPatient()
{
    return isset($_SESSION['role']) && $_SESSION['role'] === 'patient';
}

function sanitize($input)
{
    return htmlspecialchars(strip_tags(trim($input)));
}

function generateToken()
{
    return bin2hex(random_bytes(32));
}

function validateToken($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get the appropriate Bootstrap badge class for a given status
 * @param string $status The status to get the badge class for
 * @return string The Bootstrap badge class
 */
function getStatusBadgeClass($status)
{
    return match (strtolower($status)) {
        'scheduled' => 'primary',
        'completed' => 'success',
        'cancelled' => 'danger',
        'no-show' => 'warning',
        default => 'secondary'
    };
}

/**
 * Set a flash message in the session
 * 
 * @param string $type The type of message (success, error, warning, info)
 * @param string $message The message to display
 */
function setFlashMessage($type, $message)
{
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}
