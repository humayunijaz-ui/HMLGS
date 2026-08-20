USE hmlgs;

-- Default administrator
-- username: admin
-- password: Admin@123   (change this after first login)
INSERT INTO users (username, full_name, email, password_hash, status)
VALUES ('admin', 'Hostel Administrator', 'admin@hmlgs.local',
'$2y$10$Wd6FKMSxzu4.kyq.QQN1lumQV0R9PngCHcXAbpJkNpDcuB7qXhJke', 'Active');

INSERT INTO hostel_sessions (session_name, is_active, status) VALUES
('2026-27', 1, 'Active');

INSERT INTO departments (department_name, department_code, status) VALUES
('Computer Science', 'CS', 'Active'),
('Software Engineering', 'SE', 'Active'),
('Information Technology', 'IT', 'Active'),
('Data Science', 'DS', 'Active'),
('Artificial Intelligence', 'AI', 'Active');

INSERT INTO programs (program_name, program_code, department_id, degree, status) VALUES
('BS Computer Science', 'BSCS', 1, 'BS', 'Active'),
('BS Software Engineering', 'BSSE', 2, 'BS', 'Active'),
('BS Information Technology', 'BSIT', 3, 'BS', 'Active'),
('BS Data Science', 'BSDS', 4, 'BS', 'Active'),
('BS Artificial Intelligence', 'BSAI', 5, 'BS', 'Active');

INSERT INTO hostels (hostel_name, hostel_code, gender, total_capacity, status) VALUES
('Boys Hostel 1', 'BH-1', 'Male', 100, 'Active'),
('Girls Hostel 1', 'GH-1', 'Female', 100, 'Active');

INSERT INTO hostel_seats (hostel_id, hostel_session_id, total_seats, allocated_seats, cancelled_seats) VALUES
(1, 1, 100, 0, 0),
(2, 1, 100, 0, 0);
