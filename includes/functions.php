<?php
/**
 * General helper functions
 */

function e($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect($path) {
    header('Location: ' . BASE_URL . ltrim($path, '/'));
    exit;
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . e($_SESSION['csrf_token']) . '">';
}

function verify_csrf() {
    if (
        $_SERVER['REQUEST_METHOD'] === 'POST' &&
        (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) ||
         !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']))
    ) {
        http_response_code(403);
        die('Invalid CSRF token. Please go back and try again.');
    }
}

function flash_set($type, $message) {
    $_SESSION['flash_' . $type] = $message;
}

function flash_get($type) {
    if (!empty($_SESSION['flash_' . $type])) {
        $msg = $_SESSION['flash_' . $type];
        unset($_SESSION['flash_' . $type]);
        return $msg;
    }
    return null;
}

function current_user_id() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Record an entry in the audit log.
 */
function audit_log(PDO $pdo, $action, $module = null, $recordId = null, $details = null) {
    $stmt = $pdo->prepare(
        "INSERT INTO audit_logs (user_id, action, module, record_id, details, ip_address)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        current_user_id(),
        $action,
        $module,
        $recordId,
        $details,
        $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
}

/**
 * Get the currently active hostel session (or null).
 */
function get_active_session(PDO $pdo) {
    $stmt = $pdo->query("SELECT * FROM hostel_sessions WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
    return $stmt->fetch();
}

function get_all_departments(PDO $pdo, $activeOnly = true) {
    $sql = "SELECT * FROM departments" . ($activeOnly ? " WHERE status = 'Active'" : "") . " ORDER BY department_name";
    return $pdo->query($sql)->fetchAll();
}

function get_programs_by_department(PDO $pdo, $departmentId, $activeOnly = true) {
    $sql = "SELECT * FROM programs WHERE department_id = ?" . ($activeOnly ? " AND status = 'Active'" : "") . " ORDER BY program_name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$departmentId]);
    return $stmt->fetchAll();
}

function format_datetime($value) {
    if (empty($value)) return '-';
    return date('d-M-Y h:i A', strtotime($value));
}
