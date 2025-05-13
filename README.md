# MediSchedule - Healthcare Management System

A comprehensive healthcare management system that allows patients to book appointments with doctors, manage their medical records, and enables doctors to manage their schedules and patient care.

## Features

### For Patients

- Book appointments with doctors
- View and manage appointments
- Access medical records
- View doctor profiles and specializations
- Rate and review doctors

### For Doctors

- Manage appointment schedule
- View and update patient records
- Set availability and working hours
- Manage medical records

### For Administrators

- Manage users (doctors and patients)
- Monitor system activity
- View and manage appointments
- Access audit logs

## Installation

1. Clone the repository:

```bash
git clone https://github.com/Zceehl/healthcare.git
```

2. Set up the database:

- Create a MySQL database named `healthcare_db`
- Import the database schema from `database/healthcare_db.sql`

3. Configure the application:

- Copy `config/config.example.php` to `config/config.php`
- Update the database credentials in `config/config.php`

4. Set up the web server:

- Point your web server to the project directory
- Ensure the `uploads` directory is writable

## System Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- Composer (for dependency management)

## Directory Structure

```
healthcare/
├── assets/             # Static assets (CSS, JS, images)
├── config/            # Configuration files
├── database/          # Database schema and migrations
├── layouts/           # Layout templates
├── pages/             # Page templates
│   ├── admin/        # Admin pages
│   ├── doctor/       # Doctor pages
│   └── patient/      # Patient pages
├── uploads/           # Uploaded files
│   └── profile_images/ # User profile images
└── README.md         # This file
```

## Account Credentials

### Admin Account

- Email: admin@medischedule.com
- Password: password
- Role: Administrator

### Doctor Account

- Email: doctor@medischedule.com
- Password: password
- Role: Doctor

### Sample Doctor Accounts

1. Dr. Sarah Johnson (Cardiology)

   - Email: dr.sarah@medischedule.com
   - Password: password

2. Dr. Michael Chen (Neurology)

   - Email: dr.michael@medischedule.com
   - Password: password

3. Dr. Emma Williams (Pediatrics)

   - Email: dr.emma@medischedule.com
   - Password: password

4. Dr. David Brown (Orthopedics)

   - Email: dr.david@medischedule.com
   - Password: password

5. Dr. Lisa Garcia (Dermatology)
   - Email: dr.lisa@medischedule.com
   - Password: password

### Patient Account

- Email: patient@medischedule.com
- Password: password
- Role: Patient

## Security Notes

- All passwords are hashed using bcrypt
- User sessions are managed securely
- Input validation and sanitization are implemented
- SQL injection prevention measures are in place
- XSS protection is implemented

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request
