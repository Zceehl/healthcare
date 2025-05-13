<?php
require_once 'config/config.php';

$db = Database::getInstance();

// Get active doctors with their specializations and ratings
$doctors = $db->query("
    SELECT d.*, u.email, u.status,
           COUNT(DISTINCT a.id) as total_appointments
    FROM doctors d
    JOIN users u ON d.user_id = u.id
    LEFT JOIN appointments a ON d.id = a.doctor_id
    WHERE u.status = 'active'
    GROUP BY d.id
    ORDER BY total_appointments DESC
    LIMIT 6
")->fetch_all(MYSQLI_ASSOC);

// Get unique specializations from active doctors
$specializations = $db->query("
    SELECT DISTINCT specialization
    FROM doctors d
    JOIN users u ON d.user_id = u.id
    WHERE u.status = 'active'
    AND specialization IS NOT NULL
    AND specialization != ''
    ORDER BY specialization ASC
")->fetch_all(MYSQLI_ASSOC);

$content = '
<!-- Hero Section -->
<section class="hero bg-primary text-white py-5">
    <div class="container">
        <div class="row justify-content-center text-center">
            <div class="col-lg-8">
                <h1 class="display-4 fw-bold mb-4">Your Health, Our Priority</h1>
                <p class="lead mb-4">Book appointments with expert doctors and manage your healthcare journey with ease.</p>
                <div class="d-flex gap-3 justify-content-center">
                    <a href="pages/register.php" class="btn btn-light btn-lg">Get Started</a>
                    <a href="#doctors" class="btn btn-outline-light btn-lg">Find Doctors</a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Doctors Section -->
<section id="doctors" class="py-5 bg-light">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="display-5 fw-bold">Featured Doctors</h2>
            <p class="lead">Meet our team of experienced healthcare professionals</p>
        </div>
        <div class="row g-4">';

foreach ($doctors as $doctor) {
    $file_uploader = FileUploader::getInstance();
    $profile_image = $file_uploader->getProfileImageUrl($doctor['profile_image'] ?? '');

    $content .= '
        <div class="col-md-6 col-lg-4">
            <div class="card doctor-card h-100">
                <div class="card-body doctor-info text-center">
                    <img src="' . $profile_image . '"
                        alt="Dr. ' . htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']) . '"
                        class="doctor-image">

                    <h4 class="card-title">Dr. ' . htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']) . '</h4>

                    <p class="doctor-specialization mb-2">
                        ' . htmlspecialchars($doctor['specialization']) . '
                    </p>

                    <p class="doctor-qualification mb-3">
                        ' . htmlspecialchars($doctor['qualification']) . '
                    </p>

                    <div class="doctor-stats">
                        <div class="stat-item">
                            <div class="stat-value">' . htmlspecialchars($doctor['years_experience']) . '+</div>
                            <div class="stat-label">Years Exp.</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value">' . number_format($doctor['total_appointments']) . '</div>
                            <div class="stat-label">Appointments</div>
                        </div>
                    </div>

                    <p class="doctor-description mt-3">
                        ' . htmlspecialchars($doctor['bio'] ?? 'Experienced healthcare professional dedicated to providing the best medical care to patients.') . '
                    </p>

                    <div class="mt-3">
                        <a href="pages/patient/find_doctor.php?id=' . $doctor['id'] . '" class="btn btn-primary btn-sm">Book Appointment</a>
                    </div>
                </div>
            </div>
        </div>';
}

$content .= '
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="py-5 bg-primary text-white">
    <div class="container text-center">
        <h2 class="display-5 fw-bold mb-4">Ready to Get Started?</h2>
        <p class="lead mb-4">Create an account and book your appointment today.</p>
        <a href="pages/register.php" class="btn btn-light btn-lg">Register Now</a>
    </div>
</section>';

require_once 'layouts/main.php';
