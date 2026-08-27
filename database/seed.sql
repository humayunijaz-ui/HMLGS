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
('Department of Computer Science (Old Campus', 'CSO', 'Active'),
('Department of Computer Science (New Campus)', 'CSN', 'Active'),
('Department of Software Engineering (New Campus)', 'SEN', 'Active'),
('Department of Software Engineering (Old Campus)', 'SEO', 'Active'),
('Department of Information Technology (New Campus)', 'ITN', 'Active'),
('Department of Information Technology (Old Campus)', 'ITO', 'Active'),
('Department of Data Science (New Campus)', 'DS', 'Active');

INSERT INTO admission_quotas (quota_name, quota_code, status) VALUES
('Open Merit', 'OPEN', 'Active'),
('Overseas Pakistani Merit', 'OVERSEASE', 'Active'),
('Disable Merit', 'DISABLE', 'Active');

INSERT INTO programs 
(program_name, program_code, department_id, degree, status) 
VALUES
('BS Computer Science, 1st Semester (Morning)', 'BCSO', 1, 'BS', 'Active'),
('BS Computer Science, 1st Semester (Morning)', 'BCSN', 2, 'BS', 'Active'),
('BS Computer Science (Specialization in Software Engineering), 1st Semester (Morning)', 'BSEN', 3, 'BS', 'Active'),
('BS Computer Science (Specialization in Software Engineering), 1st Semester (Morning)', 'BSEO', 4, 'BS', 'Active'),
('BS Computer Science (Specialization in Artificial Intelligence), 1st Semester (Morning)', 'BAIN', 5, 'BS', 'Active'),
('BS Computer Science (Specialization in Artificial Intelligence), 1st Semester (Morning)', 'BAIO', 6, 'BS', 'Active'),
('BS Data Science', 'BSDS', 7, 'BDS', 'Active'),

('M.Phil. Artificial Intelligence, 1st Semester (Morning)', 'MPHILAI', 6, 'MPhil', 'Active'),
('PhD. Artificial Intelligence, 1st Semester (Morning)', 'PHDAI', 6, 'PhD', 'Active'),
('M.Phil Computer Science (Regular), 1st Semester (Morning)', 'MPHILCS', 1, 'MPhil', 'Active'),
('PhD. Computer Science (Regular), 1st Semester (Morning)', 'PHDCS', 1, 'PhD', 'Active'),
('MS Data Science (Regular), 1st Semester (Morning)', 'MSDS', 7, 'MS', 'Active'),
('Ph.D. Data Science, 1st Semester (Morning)', 'PHDDS', 7, 'PhD', 'Active');

INSERT INTO hostels (hostel_name, hostel_code, gender, total_capacity, status) VALUES
('Boys Hostel', 'BH', 'Male', 100, 'Active'),
('Girls Hostel ', 'GH', 'Female', 100, 'Active');

INSERT INTO hostel_seats
    (hostel_id, hostel_session_id, total_seats, allocated_seats, cancelled_seats)
VALUES
    (1, 1, 100, 0, 0),
    (2, 1, 100, 0, 0);

