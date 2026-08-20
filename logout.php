<?php
require_once __DIR__ . '/config/config.php';

if (!empty($_SESSION['user_id'])) {
    audit_log($pdo, 'Logout', 'auth', $_SESSION['user_id'], 'User logged out');
}

$_SESSION = [];
session_destroy();

header('Location: ' . BASE_URL . 'login.php');
exit;
