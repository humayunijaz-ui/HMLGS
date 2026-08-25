<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/check_auth.php';
require_once __DIR__ . '/../includes/validation.php';

$pageTitle = 'Import Applications';

$activeSession = get_active_session($pdo);
$sessionId = $activeSession['id'] ?? null;

if (!$sessionId) {
    flash_set('error', 'No active hostel session. Please activate a session before importing.');
    redirect('sessions/index.php');
}

$expectedColumns = [
    'form_no', 'student_name', 'father_name', 'cnic_b_form', 'gender',
    'contact_number', 'email', 'address', 'district', 'province', 'domicile',
    'department', 'program', 'degree', 'session', 'semester', 'admission_year', 'percentage',
];

$summary = null;
$validRows = [];
$invalidRows = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'confirm') {
    verify_csrf();

    $importData = $_SESSION['import_preview'] ?? null;
    if (!$importData || $importData['session_id'] != $sessionId) {
        flash_set('error', 'Import preview expired or invalid. Please upload the file again.');
        redirect('imports/upload.php');
    }

    $saved = 0;
    $stmt = $pdo->prepare(
        "INSERT INTO hostel_applications
         (hostel_session_id, form_no, student_name, father_name, cnic_b_form, gender, contact_number, email,
          address, district, province, domicile, department_id, program_id, admission_quota_id, degree, session, semester,
          admission_year, percentage, status)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'Eligible')"
    );
    $histStmt = $pdo->prepare(
        "INSERT INTO application_status_history (application_id, old_status, new_status, changed_by, remarks)
         VALUES (?, NULL, 'Eligible', ?, 'Imported via CSV - auto marked eligible')"
    );
    $eligStmt = $pdo->prepare(
        "INSERT INTO eligibility_results (application_id, is_eligible, reason, checked_by)
         VALUES (?, 1, 'Auto-marked eligible on CSV import', ?)"
    );

    $pdo->beginTransaction();
    try {
        foreach ($importData['valid_rows'] as $row) {
            $stmt->execute([
                $sessionId,
                $row['form_no'], $row['student_name'], $row['father_name'], $row['cnic_b_form'],
                $row['gender'], $row['contact_number'], $row['email'], $row['address'],
                $row['district'], $row['province'], $row['domicile'],
                $row['department_id'], $row['program_id'], $row['admission_quota_id'], $row['degree'],
                $row['session'], $row['semester'], $row['admission_year'], $row['percentage'],
            ]);
            $newId = $pdo->lastInsertId();
            $histStmt->execute([$newId, current_user_id()]);
            $eligStmt->execute([$newId, current_user_id()]);
            $saved++;
        }
        audit_log($pdo, 'Import applications', 'imports', null, "{$saved} record(s) imported for session #{$sessionId}");
        $pdo->commit();
    } catch (Exception $ex) {
        $pdo->rollBack();
        flash_set('error', 'Import failed: ' . $ex->getMessage());
        redirect('imports/upload.php');
    }

    unset($_SESSION['import_preview']);
    flash_set('success', "{$saved} application(s) imported successfully.");
    redirect('applications/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload') {
    verify_csrf();

    if (empty($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
        flash_set('error', 'Please choose a valid CSV file to upload.');
        redirect('imports/upload.php');
    }

    $tmpName = $_FILES['import_file']['tmp_name'];
    $origName = $_FILES['import_file']['name'];
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

    if ($ext !== 'csv') {
        flash_set('error', 'Only CSV files are supported. Please export your Excel sheet as .csv first.');
        redirect('imports/upload.php');
    }

    $storedName = 'import_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.csv';
    $storedPath = __DIR__ . '/../uploads/imports/' . $storedName;
    move_uploaded_file($tmpName, $storedPath);

    $handle = fopen($storedPath, 'r');
    if (!$handle) {
        flash_set('error', 'Unable to read the uploaded file.');
        redirect('imports/upload.php');
    }

    $header = fgetcsv($handle);
    if (!$header) {
        flash_set('error', 'The CSV file appears to be empty.');
        redirect('imports/upload.php');
    }
    $header = array_map(function ($h) { return strtolower(trim($h)); }, $header);

    $missingCols = array_diff(['form_no', 'student_name', 'cnic_b_form', 'gender', 'department', 'program', 'percentage'], $header);
    if (!empty($missingCols)) {
        fclose($handle);
        flash_set('error', 'Missing required column(s) in CSV: ' . implode(', ', $missingCols));
        redirect('imports/upload.php');
    }

    $rowNum = 1;
    $seenFormNos = [];
    $total = 0;

    while (($line = fgetcsv($handle)) !== false) {
        $rowNum++;
        if (count(array_filter($line, fn($v) => trim((string)$v) !== '')) === 0) {
            continue;
        }
        $total++;

        $raw = [];
        foreach ($header as $i => $col) {
            $raw[$col] = trim($line[$i] ?? '');
        }

        $rowErrors = [];

        $formNo = $raw['form_no'] ?? '';
        if ($formNo !== '') {
            if (isset($seenFormNos[$formNo])) {
                $rowErrors[] = "Duplicate Form No. within uploaded file (also on row {$seenFormNos[$formNo]})";
            } else {
                $seenFormNos[$formNo] = $rowNum;
            }
        }

        if (empty($raw['cnic_b_form'])) {
            $rowErrors[] = "Missing required field: cnic_b_form";
        }

        $deptId = $raw['department'] !== '' ? resolve_department_id($pdo, $raw['department']) : null;
        $progId = null;
        if ($deptId && !empty($raw['program'])) {
            $progId = resolve_program_id($pdo, $raw['program'], $deptId);
        }
        if (!empty($raw['department']) && !$deptId) {
            $rowErrors[] = "Unknown department: {$raw['department']}";
        }
        if (!empty($raw['program']) && $deptId && !$progId) {
            $rowErrors[] = "Unknown program '{$raw['program']}' for department '{$raw['department']}'";
        }

        // Admission quota: match by name or code; blank defaults to Open Merit
        $quotaId = null;
        $quotaInput = trim($raw['admission_quota'] ?? '');
        if ($quotaInput === '') {
            $quotaInput = 'Open Merit';
        }
        try {
            $qStmt = $pdo->prepare("SELECT id FROM admission_quotas WHERE quota_name = ? OR quota_code = ? LIMIT 1");
            $qStmt->execute([$quotaInput, strtoupper($quotaInput)]);
            $quotaId = $qStmt->fetchColumn() ?: null;
            if (!$quotaId) {
                $rowErrors[] = "Unknown admission quota: {$quotaInput}";
            }
        } catch (PDOException $e) {
            // admission_quotas table not migrated yet — skip quota assignment silently
            $quotaId = null;
        }

        $record = [
            'form_no'        => $formNo,
            'student_name'   => $raw['student_name'] ?? '',
            'father_name'    => $raw['father_name'] ?? '',
            'cnic_b_form'    => $raw['cnic_b_form'] ?? '',
            'gender'         => $raw['gender'] ?? '',
            'contact_number' => $raw['contact_number'] ?? '',
            'email'          => $raw['email'] ?? '',
            'address'        => $raw['address'] ?? '',
            'district'       => $raw['district'] ?? '',
            'province'       => $raw['province'] ?? '',
            'domicile'       => $raw['domicile'] ?? '',
            'department_id'  => $deptId,
            'program_id'     => $progId,
            'admission_quota_id' => $quotaId,
            'degree'         => $raw['degree'] ?? '',
            'session'        => $raw['session'] ?? '',
            'semester'       => $raw['semester'] ?? '',
            'admission_year' => $raw['admission_year'] ?? '',
            'percentage'     => $raw['percentage'] ?? '',
        ];

        $coreErrors = validate_application_record($record, ['pdo' => $pdo, 'session_id' => $sessionId]);
        $rowErrors = array_merge($rowErrors, $coreErrors);

        if (empty($rowErrors)) {
            $validRows[] = $record;
        } else {
            $invalidRows[] = [
                'row' => $rowNum,
                'form_no' => $formNo,
                'student_name' => $record['student_name'],
                'errors' => $rowErrors,
            ];
        }
    }
    fclose($handle);

    $summary = [
        'total' => $total,
        'valid' => count($validRows),
        'invalid' => count($invalidRows),
        'file' => $origName,
    ];

    $_SESSION['import_preview'] = [
        'session_id' => $sessionId,
        'valid_rows' => $validRows,
        'summary' => $summary,
    ];
}

if (!$summary && !empty($_SESSION['import_preview']) && $_SESSION['import_preview']['session_id'] == $sessionId) {
    $summary = $_SESSION['import_preview']['summary'];
    $validRows = $_SESSION['import_preview']['valid_rows'];
}

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/navbar.php';
?>
<div class="container-fluid py-4">
  <h4 class="mb-3">Import Applications (CSV)</h4>
  <p class="text-muted">Active session: <strong><?= e($activeSession['session_name']) ?></strong></p>

  <?php if ($m = flash_get('success')): ?><div class="alert alert-success alert-dismissible fade show"><?= e($m) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
  <?php if ($m = flash_get('error')): ?><div class="alert alert-danger alert-dismissible fade show"><?= e($m) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

  <div class="card p-3 mb-4">
    <div class="d-flex justify-content-between align-items-start">
      <h6>Upload CSV File</h6>
      <a href="<?= BASE_URL ?>imports/template.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-download"></i> Download CSV Template</a>
    </div>
    <p class="small text-muted mb-2">
      Required columns: <code>form_no, student_name, cnic_b_form, gender, department, program, percentage</code>.
      Optional: <code>father_name, contact_number, email, address, district, province, domicile, degree, session, semester, admission_year, admission_quota</code>.
      <code>department</code> / <code>program</code> / <code>admission_quota</code> may be the name or the code. If <code>admission_quota</code> is left blank, it defaults to "Open Merit".
    </p>
    <form method="post" enctype="multipart/form-data" class="row g-2">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="upload">
      <div class="col-md-6">
        <input type="file" name="import_file" accept=".csv" class="form-control" required>
      </div>
      <div class="col-md-2">
        <button class="btn btn-primary w-100"><i class="bi bi-upload"></i> Upload &amp; Validate</button>
      </div>
    </form>
  </div>

  <?php if ($summary): ?>
    <div class="card p-3 mb-4">
      <h6>Import Summary<?= isset($summary['file']) ? ' — ' . e($summary['file']) : '' ?></h6>
      <div class="row text-center g-2">
        <div class="col"><div class="p-3 border rounded"><div class="fs-4 fw-bold"><?= $summary['total'] ?></div><div class="text-muted small">Total Records</div></div></div>
        <div class="col"><div class="p-3 border rounded bg-success bg-opacity-10"><div class="fs-4 fw-bold text-success"><?= $summary['valid'] ?></div><div class="text-muted small">Valid Records</div></div></div>
        <div class="col"><div class="p-3 border rounded bg-danger bg-opacity-10"><div class="fs-4 fw-bold text-danger"><?= $summary['invalid'] ?></div><div class="text-muted small">Invalid Records</div></div></div>
      </div>

      <?php if ($summary['valid'] > 0): ?>
        <form method="post" class="mt-3">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="confirm">
          <button class="btn btn-success"><i class="bi bi-check2-circle"></i> Confirm &amp; Save <?= $summary['valid'] ?> Valid Record(s)</button>
        </form>
      <?php endif; ?>
    </div>

    <?php if (!empty($invalidRows)): ?>
      <div class="card p-3">
        <h6 class="text-danger">Import Errors (<?= count($invalidRows) ?>)</h6>
        <div class="table-responsive">
          <table class="table table-sm table-bordered">
            <thead><tr><th>Row</th><th>Form No.</th><th>Student Name</th><th>Errors</th></tr></thead>
            <tbody>
              <?php foreach ($invalidRows as $ir): ?>
                <tr>
                  <td><?= $ir['row'] ?></td>
                  <td><?= e($ir['form_no']) ?></td>
                  <td><?= e($ir['student_name']) ?></td>
                  <td class="text-danger small"><?= e(implode('; ', $ir['errors'])) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
