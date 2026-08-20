<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/check_auth.php';
require_once __DIR__ . '/../includes/validation.php';

$pageTitle = 'New Application';
$activeSession = get_active_session($pdo);
$sessionId = $activeSession['id'] ?? null;
$departments = get_all_departments($pdo);

$errors = [];
$old = [];

if (!$sessionId) {
    flash_set('error', 'No active hostel session. Please activate a session first.');
    redirect('sessions/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $old = $_POST;

    $row = [
        'form_no'       => trim($_POST['form_no'] ?? ''),
        'student_name'  => trim($_POST['student_name'] ?? ''),
        'gender'        => $_POST['gender'] ?? '',
        'department_id' => $_POST['department_id'] ?? '',
        'program_id'    => $_POST['program_id'] ?? '',
        'percentage'    => $_POST['percentage'] ?? '',
    ];

    $errors = validate_application_record($row, ['pdo' => $pdo, 'session_id' => $sessionId]);

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            "INSERT INTO hostel_applications
             (hostel_session_id, form_no, student_name, father_name, cnic_b_form, gender, contact_number, email,
              address, district, province, domicile, department_id, program_id, degree, session, semester,
              admission_year, percentage, status)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'Applied')"
        );
        $stmt->execute([
            $sessionId,
            $row['form_no'],
            $row['student_name'],
            trim($_POST['father_name'] ?? ''),
            trim($_POST['cnic_b_form'] ?? ''),
            $row['gender'],
            trim($_POST['contact_number'] ?? ''),
            trim($_POST['email'] ?? ''),
            trim($_POST['address'] ?? ''),
            trim($_POST['district'] ?? ''),
            trim($_POST['province'] ?? ''),
            trim($_POST['domicile'] ?? ''),
            $row['department_id'],
            $row['program_id'],
            trim($_POST['degree'] ?? ''),
            trim($_POST['session'] ?? ''),
            trim($_POST['semester'] ?? ''),
            trim($_POST['admission_year'] ?? ''),
            $row['percentage'],
        ]);
        $newId = $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO application_status_history (application_id, old_status, new_status, changed_by, remarks) VALUES (?,NULL,'Applied',?,?)")
            ->execute([$newId, current_user_id(), 'Application created']);
        audit_log($pdo, 'Create application', 'applications', $newId, $row['form_no']);
        flash_set('success', 'Application created successfully.');
        redirect('applications/index.php');
    }
}

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/navbar.php';
?>
<div class="container-fluid py-4">
  <h4 class="mb-3">New Hostel Application</h4>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
      <ul class="mb-0">
        <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="card p-4">
    <form method="post">
      <?= csrf_field() ?>
      <div class="row g-3">
        <div class="col-md-3">
          <label class="form-label">Form No. *</label>
          <input type="text" name="form_no" class="form-control" required value="<?= e($old['form_no'] ?? '') ?>">
        </div>
        <div class="col-md-5">
          <label class="form-label">Student Name *</label>
          <input type="text" name="student_name" class="form-control" required value="<?= e($old['student_name'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Father Name</label>
          <input type="text" name="father_name" class="form-control" value="<?= e($old['father_name'] ?? '') ?>">
        </div>

        <div class="col-md-4">
          <label class="form-label">CNIC / B-Form</label>
          <input type="text" name="cnic_b_form" class="form-control" value="<?= e($old['cnic_b_form'] ?? '') ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Gender *</label>
          <select name="gender" class="form-select" required>
            <option value="">-- Select --</option>
            <option value="Male" <?= ($old['gender'] ?? '')==='Male'?'selected':'' ?>>Male</option>
            <option value="Female" <?= ($old['gender'] ?? '')==='Female'?'selected':'' ?>>Female</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Contact Number</label>
          <input type="text" name="contact_number" class="form-control" value="<?= e($old['contact_number'] ?? '') ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control" value="<?= e($old['email'] ?? '') ?>">
        </div>

        <div class="col-md-6">
          <label class="form-label">Address</label>
          <input type="text" name="address" class="form-control" value="<?= e($old['address'] ?? '') ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label">District</label>
          <input type="text" name="district" class="form-control" value="<?= e($old['district'] ?? '') ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label">Province</label>
          <input type="text" name="province" class="form-control" value="<?= e($old['province'] ?? '') ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label">Domicile</label>
          <input type="text" name="domicile" class="form-control" value="<?= e($old['domicile'] ?? '') ?>">
        </div>

        <div class="col-md-4">
          <label class="form-label">Department *</label>
          <select name="department_id" id="department_id" class="form-select" required>
            <option value="">-- Select Department --</option>
            <?php foreach ($departments as $d): ?>
              <option value="<?= $d['id'] ?>" <?= ($old['department_id'] ?? '')==$d['id']?'selected':'' ?>><?= e($d['department_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Program *</label>
          <select name="program_id" id="program_id" class="form-select" required>
            <option value="">-- Select Department First --</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">Degree</label>
          <input type="text" name="degree" class="form-control" value="<?= e($old['degree'] ?? '') ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label">Percentage *</label>
          <input type="number" step="0.01" min="0" max="100" name="percentage" class="form-control" required value="<?= e($old['percentage'] ?? '') ?>">
        </div>

        <div class="col-md-3">
          <label class="form-label">Session</label>
          <input type="text" name="session" class="form-control" value="<?= e($old['session'] ?? ($activeSession['session_name'] ?? '')) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Semester</label>
          <input type="text" name="semester" class="form-control" value="<?= e($old['semester'] ?? '') ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Admission Year</label>
          <input type="text" name="admission_year" class="form-control" value="<?= e($old['admission_year'] ?? '') ?>">
        </div>
      </div>

      <div class="mt-4">
        <button class="btn btn-primary"><i class="bi bi-check2"></i> Save Application</button>
        <a href="<?= BASE_URL ?>applications/index.php" class="btn btn-outline-secondary">Cancel</a>
      </div>
    </form>
  </div>
</div>
<script>
// Pre-load programs for selected department if validation failed and department was chosen
document.addEventListener('DOMContentLoaded', function () {
  var deptSelect = document.getElementById('department_id');
  if (deptSelect && deptSelect.value) {
    deptSelect.dispatchEvent(new Event('change'));
  }
});
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
