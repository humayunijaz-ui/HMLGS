<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/check_auth.php';

// Pull one real department/program code (if any exist) to make the example row realistic
$sampleDept = $pdo->query("SELECT department_code FROM departments WHERE status = 'Active' ORDER BY id LIMIT 1")->fetchColumn();
$sampleProg = null;
if ($sampleDept) {
    $stmt = $pdo->prepare("SELECT p.program_code FROM programs p JOIN departments d ON d.id = p.department_id WHERE d.department_code = ? AND p.status = 'Active' ORDER BY p.id LIMIT 1");
    $stmt->execute([$sampleDept]);
    $sampleProg = $stmt->fetchColumn();
}
$sampleDept = $sampleDept ?: 'CS';
$sampleProg = $sampleProg ?: 'BSCS';

$columns = [
    'form_no', 'student_name', 'father_name', 'cnic_b_form', 'gender',
    'contact_number', 'email', 'address', 'district', 'province', 'domicile',
    'department', 'program', 'admission_quota', 'degree', 'session', 'semester', 'admission_year', 'percentage',
];

$exampleRow = [
    'H-001', 'Ali Ahmed', 'Ahmed Khan', '12345-1234567-1', 'Male',
    '03001234567', 'ali.ahmed@example.com', 'Street 1, Model Town', 'Lahore', 'Punjab', 'Punjab',
    $sampleDept, $sampleProg, 'Open Merit', 'BS', 'Fall 2026', '1', '2026', '85.50',
];

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="hmlgs_import_template.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, $columns);
fputcsv($out, $exampleRow);
fclose($out);
exit;
