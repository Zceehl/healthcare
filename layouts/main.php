<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediSchedule - <?php echo APP_NAME; ?></title>

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Open+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="<?php echo APP_URL; ?>/assets/css/style.css" rel="stylesheet">
    <link href="<?php echo APP_URL; ?>/assets/css/branding.css" rel="stylesheet">
    <style>
        :root {
            --text-primary: #2c3e50;
            --text-secondary: #34495e;
            --text-muted: #7f8c8d;
            --bg-light: #f8f9fa;
            --border-color: #e9ecef;
        }

        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            font-family: 'Open Sans', sans-serif;
            color: var(--text-primary);
            background-color: var(--bg-light);
        }

        h1,
        h2,
        h3,
        h4,
        h5,
        h6 {
            font-family: 'Montserrat', sans-serif;
            color: var(--text-primary);
            font-weight: 600;
        }

        .navbar {
            background-color: var(--medical-blue);
            padding: 0.5rem 1rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .navbar-brand {
            color: white !important;
            font-size: 1.25rem;
            font-weight: 600;
        }

        .nav-link {
            color: rgba(255, 255, 255, 0.9) !important;
            padding: 0.5rem 1rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .nav-link:hover {
            color: white !important;
            background-color: rgba(255, 255, 255, 0.15);
        }

        .nav-link.active {
            color: white !important;
            background-color: rgba(255, 255, 255, 0.2);
            font-weight: 600;
        }

        .nav-link i {
            margin-right: 0.5rem;
            width: 1.5rem;
            text-align: center;
        }

        .main-content {
            flex: 1;
            padding: 20px;
        }

        .card {
            border: none;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            margin-bottom: 1.5rem;
        }

        .card-header {
            background-color: white;
            border-bottom: 1px solid var(--border-color);
            padding: 1rem;
        }

        .card-title {
            margin-bottom: 0;
            color: var(--text-primary);
            font-weight: 600;
        }

        .table {
            color: var(--text-secondary);
        }

        .table thead th {
            background-color: var(--bg-light);
            border-bottom: 2px solid var(--border-color);
            color: var(--text-primary);
            font-weight: 600;
        }

        .table td {
            vertical-align: middle;
        }

        .badge {
            font-weight: 500;
            padding: 0.5em 0.75em;
            display: inline-block;
        }

        .alert {
            border: none;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .footer {
            background-color: white;
            padding: 1rem 0;
            margin-top: auto;
            border-top: 1px solid var(--border-color);
        }

        .footer p {
            color: var(--text-muted);
        }

        @media (max-width: 991.98px) {
            .navbar-collapse {
                background-color: var(--medical-blue);
                padding: 1rem;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            }
        }

        /* Status badge colors */
        .badge.bg-primary {
            background-color: #3498db !important;
        }

        .badge.bg-success {
            background-color: #2ecc71 !important;
        }

        .badge.bg-warning {
            background-color: #f1c40f !important;
        }

        .badge.bg-danger {
            background-color: #e74c3c !important;
        }

        .badge.bg-info {
            background-color: #3498db !important;
        }

        /* Button styles */
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            line-height: 1.5;
            border-radius: 0.2rem;
        }

        .btn-danger {
            color: #fff;
            background-color: #e74c3c;
            border-color: #e74c3c;
        }

        .btn-danger:hover {
            background-color: #c0392b;
            border-color: #c0392b;
        }
    </style>
</head>

<body>
    <?php if (!isAuthenticated()): ?>
        <!-- Show minimal navigation for non-authenticated users -->
        <nav class="navbar navbar-expand-lg navbar-dark">
            <div class="container">
                <a class="navbar-brand" href="<?php echo APP_URL; ?>">
                    <i class="fas fa-calendar-check"></i> MediSchedule
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto align-items-center">
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page === 'index.php' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>">
                                <i class="fas fa-home"></i> Home
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page === 'login.php' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/pages/login.php">
                                <i class="fas fa-sign-in-alt"></i> Login
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page === 'register.php' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/pages/register.php">
                                <i class="fas fa-user-plus"></i> Register
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    <?php else: ?>
        <!-- Show role-specific navigation for authenticated users -->
        <nav class="navbar navbar-expand-lg navbar-dark">
            <div class="container">
                <a class="navbar-brand" href="<?php echo APP_URL; ?>/pages/<?php echo $_SESSION['role']; ?>/dashboard.php">
                    <i class="fas fa-calendar-check"></i> MediSchedule
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav me-auto">
                        <!-- Dashboard Link (All Roles) -->
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>"
                                href="<?php echo APP_URL; ?>/pages/<?php echo $_SESSION['role']; ?>/dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>

                        <?php if ($_SESSION['role'] === 'admin'): ?>
                            <!-- Admin Navigation -->
                            <li class="nav-item">
                                <a class="nav-link <?php echo $current_page === 'manage_doctors.php' ? 'active' : ''; ?>"
                                    href="<?php echo APP_URL; ?>/pages/admin/manage_doctors.php">
                                    <i class="fas fa-user-md"></i> Doctors
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $current_page === 'manage_patients.php' ? 'active' : ''; ?>"
                                    href="<?php echo APP_URL; ?>/pages/admin/manage_patients.php">
                                    <i class="fas fa-users"></i> Patients
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $current_page === 'appointments.php' ? 'active' : ''; ?>"
                                    href="<?php echo APP_URL; ?>/pages/admin/appointments.php">
                                    <i class="fas fa-calendar-check"></i> Appointments
                                </a>
                            </li>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="reportsDropdown" role="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-chart-bar"></i> Reports
                                </a>
                                <ul class="dropdown-menu">
                                    <li>
                                        <a class="dropdown-item" href="<?php echo APP_URL; ?>/pages/admin/reports.php">
                                            <i class="fas fa-chart-line"></i> Analytics
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="<?php echo APP_URL; ?>/pages/admin/audit_report.php">
                                            <i class="fas fa-file-alt"></i> Audit Reports
                                        </a>
                                    </li>
                                </ul>
                            </li>
                        <?php endif; ?>

                        <?php if ($_SESSION['role'] === 'doctor'): ?>
                            <!-- Doctor Navigation -->
                            <li class="nav-item">
                                <a class="nav-link <?php echo $current_page === 'schedule.php' ? 'active' : ''; ?>"
                                    href="<?php echo APP_URL; ?>/pages/doctor/schedule.php">
                                    <i class="fas fa-calendar-alt"></i> Schedule
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $current_page === 'appointments.php' ? 'active' : ''; ?>"
                                    href="<?php echo APP_URL; ?>/pages/doctor/appointments.php">
                                    <i class="fas fa-calendar-check"></i> Appointments
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php if ($_SESSION['role'] === 'patient'): ?>
                            <!-- Patient Navigation -->
                            <li class="nav-item">
                                <a class="nav-link <?php echo $current_page === 'find_doctor.php' ? 'active' : ''; ?>"
                                    href="<?php echo APP_URL; ?>/pages/patient/find_doctor.php">
                                    <i class="fas fa-user-md"></i> Find Doctor
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $current_page === 'appointments.php' ? 'active' : ''; ?>"
                                    href="<?php echo APP_URL; ?>/pages/patient/appointments.php">
                                    <i class="fas fa-calendar-check"></i> My Appointments
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $current_page === 'medical-history.php' ? 'active' : ''; ?>"
                                    href="<?php echo APP_URL; ?>/pages/patient/medical-history.php">
                                    <i class="fas fa-file-medical"></i> Medical History
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>

                    <!-- User Profile Dropdown (All Roles) -->
                    <ul class="navbar-nav">
                        <li class="nav-item dropdown">
                            <?php
                            $db = Database::getInstance();
                            $user_id = $_SESSION['user_id'];
                            $role = $_SESSION['role'];

                            // Get user's profile image
                            $file_uploader = FileUploader::getInstance();
                            $profile_image = '';
                            if ($role !== 'admin') {
                                $table = $role === 'doctor' ? 'doctors' : 'patients';
                                $result = $db->query("SELECT profile_image FROM $table WHERE user_id = $user_id");
                                if ($result && $result->num_rows > 0) {
                                    $user_data = $result->fetch_assoc();
                                    if (!empty($user_data['profile_image'])) {
                                        $profile_image = $user_data['profile_image'];
                                    }
                                }
                            }
                            ?>
                            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                <img src="<?php echo $file_uploader->getProfileImageUrl($profile_image); ?>"
                                    alt="Profile"
                                    class="rounded-circle me-2"
                                    style="width: 32px; height: 32px; object-fit: cover;">
                                <?php echo $_SESSION['email']; ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/pages/profile.php">
                                        <i class="fas fa-user"></i> Profile
                                    </a>
                                    <hr class="dropdown-divider">
                                </li>
                                <li>
                                    <a class="dropdown-item text-danger" href="<?php echo APP_URL; ?>/pages/logout.php">
                                        <i class="fas fa-sign-out-alt"></i> Logout
                                    </a>
                                </li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    <?php endif; ?>

    <main>
        <?php if (isset($_SESSION['flash_message'])): ?>
            <div class="alert alert-<?php echo $_SESSION['flash_type']; ?> alert-dismissible fade show">
                <?php
                echo $_SESSION['flash_message'];
                unset($_SESSION['flash_message']);
                unset($_SESSION['flash_type']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php echo $content; ?>
    </main>

    <footer class="footer py-4 mt-auto">
        <div class="container">
            <div class="text-center text-muted">
                <small>&copy; <?php echo date('Y'); ?> MediSchedule. All rights reserved.</small>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>