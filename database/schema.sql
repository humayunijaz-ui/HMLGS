-- HMLGS Database Schema
CREATE DATABASE IF NOT EXISTS hmlgs
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE hmlgs;

-- ------------------------------------------------------------
-- users (single Hostel Administrator / SuperAdmin type)
-- ------------------------------------------------------------
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) DEFAULT NULL,
    password_hash VARCHAR(255) NOT NULL,
    status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    last_login_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- hostel_sessions
-- ------------------------------------------------------------
CREATE TABLE hostel_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_name VARCHAR(50) NOT NULL UNIQUE, -- e.g. 2026-27
    is_active TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- departments
-- ------------------------------------------------------------
CREATE TABLE departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department_name VARCHAR(150) NOT NULL,
    department_code VARCHAR(20) NOT NULL UNIQUE,
    status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- programs
-- ------------------------------------------------------------
CREATE TABLE programs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    program_name VARCHAR(150) NOT NULL,
    program_code VARCHAR(20) NOT NULL UNIQUE,
    department_id INT NOT NULL,
    degree VARCHAR(50) DEFAULT NULL,
    status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_programs_department FOREIGN KEY (department_id) REFERENCES departments(id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- hostels
-- ------------------------------------------------------------
CREATE TABLE hostels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hostel_name VARCHAR(150) NOT NULL,
    hostel_code VARCHAR(20) NOT NULL UNIQUE,
    gender ENUM('Male','Female') NOT NULL,
    total_capacity INT NOT NULL DEFAULT 0,
    status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    remarks TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- hostel_seats (seat tracking per hostel per session)
-- ------------------------------------------------------------
CREATE TABLE hostel_seats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hostel_id INT NOT NULL,
    hostel_session_id INT NOT NULL,
    total_seats INT NOT NULL DEFAULT 0,
    allocated_seats INT NOT NULL DEFAULT 0,
    cancelled_seats INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_seats_hostel FOREIGN KEY (hostel_id) REFERENCES hostels(id),
    CONSTRAINT fk_seats_session FOREIGN KEY (hostel_session_id) REFERENCES hostel_sessions(id),
    UNIQUE KEY uq_hostel_session (hostel_id, hostel_session_id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- hostel_applications
-- ------------------------------------------------------------
CREATE TABLE hostel_applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hostel_session_id INT NOT NULL,
    form_no VARCHAR(50) NOT NULL,
    student_name VARCHAR(150) NOT NULL,
    father_name VARCHAR(150) DEFAULT NULL,
    cnic_b_form VARCHAR(30) DEFAULT NULL,
    gender ENUM('Male','Female') NOT NULL,
    contact_number VARCHAR(30) DEFAULT NULL,
    email VARCHAR(150) DEFAULT NULL,
    address VARCHAR(255) DEFAULT NULL,
    district VARCHAR(100) DEFAULT NULL,
    province VARCHAR(100) DEFAULT NULL,
    domicile VARCHAR(100) DEFAULT NULL,
    department_id INT NOT NULL,
    program_id INT NOT NULL,
    degree VARCHAR(50) DEFAULT NULL,
    session VARCHAR(50) DEFAULT NULL,
    semester VARCHAR(20) DEFAULT NULL,
    admission_year VARCHAR(10) DEFAULT NULL,
    percentage DECIMAL(5,2) NOT NULL,
    status ENUM('Applied','Eligible','Not Eligible','General Merit','Selected','Not Selected','Waiting','Cancelled','Withdrawn') NOT NULL DEFAULT 'Applied',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_app_session FOREIGN KEY (hostel_session_id) REFERENCES hostel_sessions(id),
    CONSTRAINT fk_app_department FOREIGN KEY (department_id) REFERENCES departments(id),
    CONSTRAINT fk_app_program FOREIGN KEY (program_id) REFERENCES programs(id),
    UNIQUE KEY uq_formno_session (form_no, hostel_session_id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- application_status_history
-- ------------------------------------------------------------
CREATE TABLE application_status_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    old_status VARCHAR(30) DEFAULT NULL,
    new_status VARCHAR(30) NOT NULL,
    changed_by INT DEFAULT NULL,
    remarks VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_hist_application FOREIGN KEY (application_id) REFERENCES hostel_applications(id) ON DELETE CASCADE,
    CONSTRAINT fk_hist_user FOREIGN KEY (changed_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- eligibility_results
-- ------------------------------------------------------------
CREATE TABLE eligibility_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL UNIQUE,
    is_eligible TINYINT(1) NOT NULL,
    reason VARCHAR(255) DEFAULT NULL,
    checked_by INT DEFAULT NULL,
    checked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_elig_application FOREIGN KEY (application_id) REFERENCES hostel_applications(id) ON DELETE CASCADE,
    CONSTRAINT fk_elig_user FOREIGN KEY (checked_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- merit_lists (a generated snapshot / run)
-- ------------------------------------------------------------
CREATE TABLE merit_lists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hostel_session_id INT NOT NULL,
    list_type ENUM('General','Gender','Department','Program') NOT NULL,
    gender ENUM('Male','Female') DEFAULT NULL,
    department_id INT DEFAULT NULL,
    program_id INT DEFAULT NULL,
    generated_by INT DEFAULT NULL,
    generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ml_session FOREIGN KEY (hostel_session_id) REFERENCES hostel_sessions(id),
    CONSTRAINT fk_ml_department FOREIGN KEY (department_id) REFERENCES departments(id),
    CONSTRAINT fk_ml_program FOREIGN KEY (program_id) REFERENCES programs(id),
    CONSTRAINT fk_ml_user FOREIGN KEY (generated_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- merit_list_entries
-- ------------------------------------------------------------
CREATE TABLE merit_list_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    merit_list_id INT NOT NULL,
    application_id INT NOT NULL,
    rank_no INT NOT NULL,
    percentage DECIMAL(5,2) NOT NULL,
    CONSTRAINT fk_mle_list FOREIGN KEY (merit_list_id) REFERENCES merit_lists(id) ON DELETE CASCADE,
    CONSTRAINT fk_mle_application FOREIGN KEY (application_id) REFERENCES hostel_applications(id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- hostel_allocations
-- ------------------------------------------------------------
CREATE TABLE hostel_allocations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL UNIQUE,
    hostel_id INT NOT NULL,
    hostel_session_id INT NOT NULL,
    allocation_status ENUM('Selected','Waiting','Not Selected','Cancelled','Withdrawn') NOT NULL DEFAULT 'Selected',
    rank_no INT DEFAULT NULL,
    allocated_by INT DEFAULT NULL,
    allocated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_alloc_application FOREIGN KEY (application_id) REFERENCES hostel_applications(id),
    CONSTRAINT fk_alloc_hostel FOREIGN KEY (hostel_id) REFERENCES hostels(id),
    CONSTRAINT fk_alloc_session FOREIGN KEY (hostel_session_id) REFERENCES hostel_sessions(id),
    CONSTRAINT fk_alloc_user FOREIGN KEY (allocated_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- audit_logs
-- ------------------------------------------------------------
CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    action VARCHAR(100) NOT NULL,
    module VARCHAR(50) DEFAULT NULL,
    record_id INT DEFAULT NULL,
    details TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- system_settings
-- ------------------------------------------------------------
CREATE TABLE system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value VARCHAR(255) DEFAULT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- helpful indexes
CREATE INDEX idx_app_gender ON hostel_applications (gender);
CREATE INDEX idx_app_department ON hostel_applications (department_id);
CREATE INDEX idx_app_program ON hostel_applications (program_id);
CREATE INDEX idx_app_status ON hostel_applications (status);
CREATE INDEX idx_app_session ON hostel_applications (hostel_session_id);
CREATE INDEX idx_app_percentage ON hostel_applications (percentage);
