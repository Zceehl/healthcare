<?php
require_once 'config.php';

class FileUploader
{
    private static $instance = null;
    private $upload_dir;
    private $allowed_types;
    private $max_size;

    private function __construct()
    {
        $this->upload_dir = APP_ROOT . '/uploads/profile_images/';
        $this->allowed_types = ['jpg', 'jpeg', 'png'];
        $this->max_size = 5 * 1024 * 1024; // 5MB

        // Create upload directory if it doesn't exist
        if (!file_exists($this->upload_dir)) {
            mkdir($this->upload_dir, 0777, true);
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function uploadProfileImage($file, $user_id)
    {
        // Validate file
        if (!isset($file['error']) || is_array($file['error'])) {
            throw new Exception('Invalid file parameters');
        }

        // Check for upload errors
        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new Exception('File too large');
            case UPLOAD_ERR_PARTIAL:
                throw new Exception('File upload incomplete');
            case UPLOAD_ERR_NO_FILE:
                throw new Exception('No file uploaded');
            default:
                throw new Exception('Unknown upload error');
        }

        // Check file size
        if ($file['size'] > $this->max_size) {
            throw new Exception('File too large');
        }

        // Get file info
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime_type = $finfo->file($file['tmp_name']);

        // Validate file type
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $this->allowed_types)) {
            throw new Exception('Invalid file type');
        }

        // Generate unique filename
        $filename = sprintf(
            '%s.%s',
            sha1_file($file['tmp_name']),
            $ext
        );

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $this->upload_dir . $filename)) {
            throw new Exception('Failed to move uploaded file');
        }

        // Return just the filename
        return $filename;
    }

    public function deleteProfileImage($filename)
    {
        $filepath = $this->upload_dir . $filename;
        if (file_exists($filepath) && is_file($filepath)) {
            unlink($filepath);
        }
    }

    public function getProfileImageUrl($filename)
    {
        if (empty($filename)) {
            return APP_URL . '/uploads/profile_images/default-profile.jpg';
        }
        return APP_URL . '/uploads/profile_images/' . $filename;
    }
}
