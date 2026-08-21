-- ============================================================
-- HMLGS Migration: Admission-Quota-wise Seat Allocation
-- Run this once in phpMyAdmin (select the `hmlgs` database, go to
-- the SQL tab, paste this file's contents, and click Go).
-- ============================================================
USE hmlgs;

-- ------------------------------------------------------------
-- admission_quotas: configurable quota categories
-- (e.g. Open Merit, Self-Finance, Reserved ... editable via UI)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admission_quotas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quota_name VARCHAR(100) NOT NULL,
    quota_code VARCHAR(20) NOT NULL UNIQUE,
    status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO admission_quotas (quota_name, quota_code, status) VALUES
('Open Merit', 'OPEN', 'Active'),
('Self-Finance', 'SELF', 'Active'),
('Reserved', 'RSVD', 'Active');

-- ------------------------------------------------------------
-- hostel_applications: link each application to a quota
-- (nullable so existing rows aren't broken; defaults new
-- manual entries to Open Merit if left unset)
-- ------------------------------------------------------------
ALTER TABLE hostel_applications
    ADD COLUMN admission_quota_id INT DEFAULT NULL AFTER program_id,
    ADD CONSTRAINT fk_app_quota FOREIGN KEY (admission_quota_id) REFERENCES admission_quotas(id);

CREATE INDEX idx_app_quota ON hostel_applications (admission_quota_id);

-- ------------------------------------------------------------
-- quota_seat_matrix: seat limits at
-- Department > Program > Gender > Admission Quota, per session
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS quota_seat_matrix (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hostel_session_id INT NOT NULL,
    department_id INT NOT NULL,
    program_id INT NOT NULL,
    gender ENUM('Male','Female') NOT NULL,
    admission_quota_id INT NOT NULL,
    total_seats INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_qsm_session FOREIGN KEY (hostel_session_id) REFERENCES hostel_sessions(id),
    CONSTRAINT fk_qsm_department FOREIGN KEY (department_id) REFERENCES departments(id),
    CONSTRAINT fk_qsm_program FOREIGN KEY (program_id) REFERENCES programs(id),
    CONSTRAINT fk_qsm_quota FOREIGN KEY (admission_quota_id) REFERENCES admission_quotas(id),
    UNIQUE KEY uq_quota_matrix (hostel_session_id, department_id, program_id, gender, admission_quota_id)
) ENGINE=InnoDB;
