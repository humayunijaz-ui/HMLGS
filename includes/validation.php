<?php
/**
 * Validation helpers for hostel applications (manual entry + import)
 */

const ALLOWED_GENDERS = ['Male', 'Female'];

/**
 * Validate a single application record (associative array).
 * Returns an array of error strings; empty array = valid.
 *
 * $context: ['pdo' => PDO, 'session_id' => int, 'exclude_id' => int|null]
 */
function validate_application_record(array $row, array $context) {
    $errors = [];
    $pdo = $context['pdo'];
    $sessionId = $context['session_id'];
    $excludeId = $context['exclude_id'] ?? null;

    // Required fields
    $required = ['form_no', 'student_name', 'gender', 'department_id', 'program_id', 'percentage'];
    foreach ($required as $field) {
        if (!isset($row[$field]) || trim((string)$row[$field]) === '') {
            $errors[] = "Missing required field: {$field}";
        }
    }
    if (!empty($errors)) return $errors; // stop early if basics missing

    // Gender
    if (!in_array($row['gender'], ALLOWED_GENDERS, true)) {
        $errors[] = "Invalid gender: {$row['gender']}";
    }

    // Session
    $stmt = $pdo->prepare("SELECT id FROM hostel_sessions WHERE id = ? AND status = 'Active'");
    $stmt->execute([$sessionId]);
    if (!$stmt->fetch()) {
        $errors[] = "Invalid or inactive hostel session";
    }

    // Department
    $stmt = $pdo->prepare("SELECT id FROM departments WHERE id = ? AND status = 'Active'");
    $stmt->execute([$row['department_id']]);
    if (!$stmt->fetch()) {
        $errors[] = "Invalid department";
    }

    // Program must exist and belong to department
    $stmt = $pdo->prepare("SELECT id FROM programs WHERE id = ? AND department_id = ? AND status = 'Active'");
    $stmt->execute([$row['program_id'], $row['department_id']]);
    if (!$stmt->fetch()) {
        $errors[] = "Invalid program, or program does not belong to selected department";
    }

    // Percentage numeric range
    if (!is_numeric($row['percentage']) || $row['percentage'] < 0 || $row['percentage'] > 100) {
        $errors[] = "Invalid percentage value";
    }

    // Duplicate form_no within session
    $sql = "SELECT id FROM hostel_applications WHERE form_no = ? AND hostel_session_id = ?";
    $params = [$row['form_no'], $sessionId];
    if ($excludeId) {
        $sql .= " AND id != ?";
        $params[] = $excludeId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    if ($stmt->fetch()) {
        $errors[] = "Duplicate Form No. within this hostel session";
    }

    return $errors;
}

/**
 * Resolve a department name/code to its id, or null if not found.
 */
function resolve_department_id(PDO $pdo, $nameOrCode) {
    $stmt = $pdo->prepare("SELECT id FROM departments WHERE department_code = ? OR department_name = ? LIMIT 1");
    $stmt->execute([$nameOrCode, $nameOrCode]);
    $row = $stmt->fetch();
    return $row ? $row['id'] : null;
}

/**
 * Resolve a program name/code (scoped to a department) to its id, or null.
 */
function resolve_program_id(PDO $pdo, $nameOrCode, $departmentId) {
    $stmt = $pdo->prepare("SELECT id FROM programs WHERE (program_code = ? OR program_name = ?) AND department_id = ? LIMIT 1");
    $stmt->execute([$nameOrCode, $nameOrCode, $departmentId]);
    $row = $stmt->fetch();
    return $row ? $row['id'] : null;
}
