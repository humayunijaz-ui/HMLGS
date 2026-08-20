# Hostel Merit List Generator System (HMLGS)

## Project Overview

The **Hostel Merit List Generator System (HMLGS)** is a PHP + MySQL web application for managing hostel application records, validating applications, generating ranks, preparing gender-wise and department-wise merit lists, managing selected and waiting applicants, and allocating hostel seats.

The system maintains **only hostel application records**. It is not a complete university student information system.

> **Important:** HMLGS does not calculate a new merit score. The existing percentage/merit supplied in the hostel application is used for ranking only.

```text
Hostel Application
        |
        v
Data Validation
        |
        v
Eligibility Check
        |
        v
Existing Percentage / Merit
        |
        v
Rank Generation
        |
        v
General Merit List
        |
        v
Selection / Waiting List
        |
        v
Hostel Allocation
```

---

# 1. Technology Stack

- **Backend:** PHP 8.x+
- **Database:** MySQL 8.x+
- **Database Access:** PDO
- **Frontend:** HTML5, CSS3, Bootstrap 5, JavaScript/AJAX
- **Development:** XAMPP / Apache / MySQL
- **Production:** Linux + Apache + PHP + MySQL

---

# 2. User Model — Single Interface

The project uses **one unified user interface**.

There is only one system user type:

> **Hostel Administrator / SuperAdmin**

This user has complete access to the system.

The following are **not required**:

- Separate Admin role
- Separate Hostel Administrator role
- Committee user
- Committee/Authority user
- Report user
- Student login
- Multiple dashboards

```text
                    SINGLE LOGIN
                         |
                         v
             HOSTEL ADMINISTRATOR
                         |
                         v
                    DASHBOARD
                         |
       +-----------------+-----------------+
       |                 |                 |
       v                 v                 v
 Applications       Merit Lists       Hostels & Seats
       |                 |                 |
       +-----------------+-----------------+
                         |
                         v
                Selection / Waiting
                         |
                         v
                  Hostel Allocation
                         |
                         v
                      Reports
```

---

# 3. Main Features

- Single administrator login
- Dashboard
- Hostel session management
- Department management
- Program management
- Hostel management
- Hostel seat management
- Hostel application management
- CSV/Excel application import
- Import validation
- Eligibility management
- General Merit List
- Gender-wise merit lists
- Department-wise merit lists
- Program-wise filtering
- Rank generation
- Application status management
- Selected list
- Waiting list
- Not Selected list
- Hostel seat allocation
- Cancellation and withdrawal
- Reports
- Audit log

---

# 4. Student Application Data

The system maintains only hostel application records.

## Student Information

- Form No.
- Student Name
- Father Name
- CNIC/B-Form
- Gender
- Contact Number
- Email
- Address
- District
- Province
- Domicile

## Academic Information

- Department
- Program
- Degree
- Session
- Semester
- Admission Year
- Percentage

---

# 5. Gender-Wise Management

The system supports:

- Male
- Female

Example:

```text
Hostel Session 2026-27
|
+-- Male
|   +-- Computer Science
|   +-- Information Technology
|   +-- Software Engineering
|
+-- Female
    +-- Computer Science
    +-- Information Technology
    +-- Software Engineering
```

Male and female applications must be independently filterable and reportable.

---

# 6. Department and Program Management

## Department

- Department Name
- Department Code
- Status

## Program

- Program Name
- Program Code
- Department
- Degree
- Status

A program must belong to a valid department.

---

# 7. Hostel Management

## Hostel Fields

- Hostel Name
- Hostel Code
- Gender
- Total Capacity
- Available Seats
- Status
- Remarks

## Seat Tracking

The system tracks:

- Total Seats
- Allocated Seats
- Available Seats
- Cancelled Seats
- Remaining Seats

---

# 8. Application Import

The system supports:

- CSV
- Excel

Process:

```text
Upload File
     |
     v
Validate Columns
     |
     v
Validate Records
     |
     v
Show Errors
     |
     v
Show Import Summary
     |
     v
Administrator Confirmation
     |
     v
Save Valid Records
```

Example:

```text
Total Records:       2,500
Valid Records:       2,450
Invalid Records:        35
Duplicate Records:      15
```

Invalid records must not be silently imported.

---

# 9. Import Validation

The system validates:

### Duplicate Form No.

Form No. must be unique within the relevant hostel session.

### Invalid Gender

Allowed values:

```text
Male
Female
```

### Invalid Department

The department must exist in the configured department table.

### Invalid Program

The program must exist and belong to the selected department.

### Missing Required Fields

Required fields must not be blank.

### Invalid Session

The session must exist in the hostel session configuration.

---

# 10. Eligibility

The system determines whether an application is eligible for merit-list consideration.

Statuses:

- Eligible
- Not Eligible

The reason for `Not Eligible` should be recorded.

---

# 11. Merit List Generation

The system supports **General Merit List generation**.

```text
All Hostel Applications
          |
          v
Eligibility Validation
          |
          v
Eligible Students
          |
          v
Existing Percentage / Merit
          |
          v
Rank Generation
          |
          v
General Merit List
```

## No Merit Recalculation

The system will **not**:

- Recalculate percentage
- Add marks
- Deduct marks
- Apply academic weightage
- Apply distance weightage
- Apply financial/need weightage
- Create a new hostel merit score

The system only generates the rank using the approved ranking rules.

---

# 12. Ranking

```text
Eligible Applications
        |
        v
Existing Percentage / Merit
        |
        v
Approved Ranking Order
        |
        v
Rank
```

Example:

| Rank | Form No. | Student | Gender | Department | Program | Percentage |
|---:|---|---|---|---|---|---:|
| 1 | H-001 | Student A | Male | CS | BSCS | 92.50 |
| 2 | H-015 | Student B | Male | CS | BSCS | 91.80 |
| 3 | H-021 | Student C | Male | CS | BSCS | 90.75 |

Tie-breaking rules must be approved before implementation.

---

# 13. Gender-Wise Merit

The administrator can generate:

- Male General Merit List
- Female General Merit List

Filtering:

```text
Session + Gender + Department + Program
```

---

# 14. Department-Wise Merit

The administrator can generate separate lists by department.

```text
Male
 |
 +-- Computer Science
 +-- Information Technology
 +-- Software Engineering

Female
 |
 +-- Computer Science
 +-- Information Technology
 +-- Software Engineering
```

---

# 15. Application Status

Supported statuses:

```text
Applied
Eligible
Not Eligible
General Merit
Selected
Not Selected
Waiting
Cancelled
Withdrawn
```

Suggested flow:

```text
Applied
   |
   v
Eligibility Check
   |
   +----> Not Eligible
   |
   v
Eligible
   |
   v
General Merit
   |
   +----> Selected
   +----> Waiting
   +----> Not Selected
   +----> Cancelled
   +----> Withdrawn
```

---

# 16. Selection and Waiting List

Selection should consider:

- Rank
- Gender
- Department
- Program
- Hostel
- Available seats
- Approved selection rules

If seats are unavailable, eligible applicants may be placed on the waiting list.

```text
Available Seats: 100

Rank 1-100
    -> Selected

Rank 101 onward
    -> Waiting
```

The system must not allocate more students than the available hostel capacity.

---

# 17. Hostel Allocation

The system shall allocate hostel seats to selected applicants.

Allocation considers:

- Gender
- Hostel
- Available seats
- Rank
- Selection status

Cancelled and withdrawn applicants must not occupy active seats.

---

# 18. Reports

The system shall provide:

1. General Merit List
2. Male Merit List
3. Female Merit List
4. Department-wise Merit List
5. Program-wise Merit List
6. Eligible Applicants List
7. Not Eligible Applicants List
8. Selected Applicants List
9. Waiting List
10. Not Selected List
11. Cancelled Applications
12. Withdrawn Applications
13. Hostel Seat Utilization
14. Gender-wise Summary
15. Department-wise Summary
16. Import Error Report

Reports should support:

- Screen
- Print
- PDF
- Excel
- CSV

---

# 19. Dashboard

The single administrator dashboard should show:

```text
HOSTEL SESSION: 2026-27

Total Applications       2,200
Eligible                  1,990
Not Eligible                210

Male Applications         1,250
Female Applications         950

Total Hostel Seats          900
Allocated Seats              ---
Available Seats              ---
Selected                     ---
Waiting                      ---
Not Selected                 ---
```

Filters:

- Session
- Gender
- Department
- Program
- Hostel
- Status

---

# 20. Database Tables

Recommended MySQL tables:

```text
users
hostel_sessions
departments
programs
hostels
hostel_seats
hostel_applications
application_status_history
eligibility_results
merit_lists
merit_list_entries
hostel_allocations
audit_logs
system_settings
```

## Hostel Applications

```text
id
hostel_session_id
form_no
student_name
father_name
cnic_b_form
gender
contact_number
email
address
district
province
domicile
department_id
program_id
degree
session
semester
admission_year
percentage
status
created_at
updated_at
```

There should be **no calculated `merit_score` field** in the current project.

---

# 21. PHP Project Structure

```text
hmlgs/
|
+-- index.php
+-- login.php
+-- logout.php
|
+-- config/
|   +-- database.php
|   +-- config.php
|
+-- auth/
|   +-- check_auth.php
|
+-- dashboard/
|   +-- index.php
|
+-- applications/
|   +-- index.php
|   +-- create.php
|   +-- edit.php
|   +-- view.php
|
+-- imports/
|   +-- upload.php
|   +-- process.php
|   +-- errors.php
|
+-- merit/
|   +-- general.php
|   +-- gender_wise.php
|   +-- department_wise.php
|   +-- program_wise.php
|   +-- generate.php
|
+-- eligibility/
|   +-- index.php
|   +-- check.php
|
+-- hostels/
|   +-- index.php
|   +-- create.php
|   +-- edit.php
|   +-- seats.php
|
+-- allocation/
|   +-- index.php
|   +-- select.php
|   +-- waiting.php
|   +-- allocate.php
|
+-- departments/
+-- programs/
+-- sessions/
|
+-- reports/
|
+-- includes/
|   +-- header.php
|   +-- footer.php
|   +-- navbar.php
|   +-- functions.php
|   +-- validation.php
|   +-- merit_helper.php
|
+-- ajax/
+-- assets/
|   +-- css/
|   +-- js/
|   +-- images/
|
+-- uploads/
|   +-- imports/
|
+-- database/
|   +-- schema.sql
|   +-- seed.sql
|
+-- .htaccess
+-- .gitignore
+-- README.md
```

---

# 22. PHP Development Standards

Use:

- PHP 8.x+
- PDO
- Prepared statements
- PHP Sessions
- Bootstrap 5
- JavaScript/AJAX

Example:

```php
$stmt = $pdo->prepare(
    "SELECT * FROM hostel_applications WHERE form_no = ?"
);

$stmt->execute([$formNo]);
```

All database queries must use prepared statements.

Passwords must use:

```php
password_hash()
```

and:

```php
password_verify()
```

---

# 23. XAMPP Installation

### Step 1

Install XAMPP with Apache and MySQL.

### Step 2

Copy the project to:

```text
C:\xampp\htdocs\hmlgs
```

### Step 3

Start Apache and MySQL.

### Step 4

Create the database:

```sql
CREATE DATABASE hmlgs
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;
```

### Step 5

Import:

```text
database/schema.sql
```

using phpMyAdmin.

### Step 6

Configure:

```text
config/database.php
```

Example:

```php
$host = 'localhost';
$db   = 'hmlgs';
$user = 'root';
$pass = '';
```

### Step 7

Open:

```text
http://localhost/hmlgs/
```

---

# 24. Production Deployment

Recommended architecture:

```text
Internet
    |
    v
Apache
    |
    v
PHP
    |
    v
MySQL
```

Production should:

- Disable PHP error display
- Enable error logging
- Use strong database credentials
- Use HTTPS
- Protect configuration files
- Restrict upload directories
- Maintain regular database backups

---

# 25. Security

The application should implement:

- Secure login
- PHP session authentication
- Password hashing
- PDO prepared statements
- CSRF protection
- Input validation
- Output escaping
- File-upload validation
- Session timeout
- Audit logging
- Secure configuration
- HTTPS in production

---

# 26. Testing

## Login

- Valid login
- Invalid login
- Logout
- Session expiration

## Applications

- Create application
- Edit application
- Duplicate Form No.
- Missing fields
- Invalid gender
- Invalid department
- Invalid program
- Invalid session

## Import

- Valid CSV
- Invalid CSV
- Duplicate records
- Missing columns
- Invalid values
- Partial errors

## Ranking

- Eligible applicants
- Not Eligible applicants
- Ranking order
- Gender-wise ranking
- Department-wise ranking
- Tie handling

## Allocation

- Available seats
- Full hostel
- Selected applicants
- Waiting applicants
- Cancellation
- Withdrawal
- Seat release

---

# 27. Development Sequence

```text
1. PHP/MySQL Project Setup
2. Single Login
3. Dashboard
4. Hostel Session Management
5. Department Management
6. Program Management
7. Hostel Management
8. Hostel Seat Management
9. Hostel Application Management
10. CSV/Excel Import
11. Data Validation
12. Eligibility
13. Rank Generation
14. General Merit List
15. Gender-Wise Merit
16. Department-Wise Merit
17. Program-Wise Filtering
18. Selection
19. Waiting List
20. Hostel Allocation
21. Reports
22. Audit Log
23. Testing
24. Production Deployment
```

---

# 28. Important Business Rules

1. The system maintains only hostel application records.
2. The technology is PHP + MySQL.
3. The system has one unified administrative interface.
4. The Hostel Administrator/SuperAdmin has complete access.
5. There is no committee workflow.
6. There is no separate committee user.
7. There is no separate report user.
8. Form No. must be unique within a hostel session.
9. Gender is mandatory.
10. Department is mandatory.
11. Program must be valid.
12. Program must belong to the selected department.
13. Session must be valid.
14. Required fields must not be missing.
15. Invalid imported records must be reported.
16. Eligible and Not Eligible applicants must be clearly separated.
17. General Merit List contains eligible applicants.
18. The system does not calculate a new merit score.
19. Existing percentage/merit is retained.
20. Rank is generated according to approved ranking rules.
21. Male and female lists are separately filterable.
22. Department-wise lists are supported.
23. Program-wise filtering is supported.
24. Hostel allocation cannot exceed available seats.
25. Cancelled and withdrawn applicants must not occupy active seats.
26. Important actions must be recorded in the audit log.

---

# 29. Final System Definition

**HMLGS = Hostel Application Management + Data Validation + Eligibility + Rank Generation + Gender-wise Merit + Department-wise Merit + Selection + Waiting List + Hostel Allocation + Reporting**

Final architecture:

```text
PHP + MySQL
     |
     v
Single Login
     |
     v
Hostel Administrator / SuperAdmin
     |
     v
Single Dashboard
     |
     +-- Hostel Sessions
     +-- Departments
     +-- Programs
     +-- Hostels & Seats
     +-- Applications
     +-- Import
     +-- Validation
     +-- Eligibility
     +-- General Merit
     +-- Gender-wise Merit
     +-- Department-wise Merit
     +-- Selection
     +-- Waiting List
     +-- Hostel Allocation
     +-- Reports
     +-- Audit Log
```

### Merit Principle

```text
Existing Percentage / Merit
             |
             v
       Eligibility
             |
             v
       Rank Generation
             |
             v
        Merit List
```

**No new merit calculation is performed by HMLGS.**
