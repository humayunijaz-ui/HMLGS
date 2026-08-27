<?php
/**
 * Include this file at the top of every student-protected page.
 * Requires config/config.php to already be loaded (for session).
 */

if (empty($_SESSION['student_cnic'])) {
    header('Location: ' . BASE_URL . 'auth/student_login.php');
    exit;
}
