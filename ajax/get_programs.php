<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/check_auth.php';

$departmentId = (int)($_GET['department_id'] ?? 0);
header('Content-Type: application/json');

if (!$departmentId) {
    echo json_encode([]);
    exit;
}

$programs = get_programs_by_department($pdo, $departmentId);
echo json_encode($programs);
