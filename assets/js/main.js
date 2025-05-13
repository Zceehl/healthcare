// Utility Functions
function showLoading() {
    const spinner = document.createElement('div');
    spinner.className = 'spinner-overlay';
    spinner.innerHTML = '<div class="spinner"></div>';
    document.body.appendChild(spinner);
}

function hideLoading() {
    const spinner = document.querySelector('.spinner-overlay');
    if (spinner) {
        spinner.remove();
    }
}

function showAlert(message, type = 'success') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.querySelector('main').insertBefore(alertDiv, document.querySelector('main').firstChild);
    
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}

// Form Validation
function validateForm(form) {
    const requiredFields = form.querySelectorAll('[required]');
    let isValid = true;

    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.classList.add('is-invalid');
            isValid = false;
        } else {
            field.classList.remove('is-invalid');
        }
    });

    return isValid;
}

// Date and Time Formatting
function formatDate(date) {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
}

function formatTime(time) {
    return new Date(`2000-01-01T${time}`).toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Calendar Functions
function initializeCalendar(container, events) {
    const calendar = container.querySelector('.calendar-grid');
    const days = calendar.querySelectorAll('.calendar-day');
    
    days.forEach(day => {
        const date = day.dataset.date;
        if (events[date]) {
            day.classList.add('has-appointments');
            day.innerHTML += `<div class="appointment-count">${events[date].length}</div>`;
        }
    });
}

// Appointment Management
function bookAppointment(doctorId, date, time) {
    showLoading();
    
    fetch(`${APP_URL}/api/appointments/book.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            doctor_id: doctorId,
            date: date,
            time: time
        })
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            showAlert('Appointment booked successfully!');
            // Refresh the calendar or appointment list
            location.reload();
        } else {
            showAlert(data.message || 'Failed to book appointment', 'danger');
        }
    })
    .catch(error => {
        hideLoading();
        showAlert('An error occurred while booking the appointment', 'danger');
        console.error('Error:', error);
    });
}

// Medical History
function loadMedicalHistory(patientId) {
    showLoading();
    
    fetch(`${APP_URL}/api/medical-history.php?patient_id=${patientId}`)
        .then(response => response.json())
        .then(data => {
            hideLoading();
            if (data.success) {
                displayMedicalHistory(data.records);
            } else {
                showAlert(data.message || 'Failed to load medical history', 'danger');
            }
        })
        .catch(error => {
            hideLoading();
            showAlert('An error occurred while loading medical history', 'danger');
            console.error('Error:', error);
        });
}

function displayMedicalHistory(records) {
    const container = document.querySelector('#medical-history');
    if (!container) return;

    container.innerHTML = records.map(record => `
        <div class="medical-record">
            <div class="medical-record-header">
                <h5>${formatDate(record.date)}</h5>
                <span class="badge bg-primary">${record.doctor_name}</span>
            </div>
            <div class="medical-record-body">
                <p><strong>Diagnosis:</strong> ${record.diagnosis}</p>
                <p><strong>Prescription:</strong> ${record.prescription}</p>
                <p><strong>Notes:</strong> ${record.notes}</p>
            </div>
        </div>
    `).join('');
}

// Event Listeners
document.addEventListener('DOMContentLoaded', function() {
    // Form validation
    const forms = document.querySelectorAll('form[data-validate]');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validateForm(this)) {
                e.preventDefault();
                showAlert('Please fill in all required fields', 'danger');
            }
        });
    });

    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Initialize popovers
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function(popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
}); 