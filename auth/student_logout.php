<?php
require_once __DIR__ . '/../config/config.php';

if (!empty($_SESSION['student_cnic'])) {
    audit_log($pdo, 'Student logout', 'student_auth', null, 'CNIC: ' . $_SESSION['student_cnic']);
}

unset($_SESSION['student_cnic']);
redirect('auth/student_login.php');
