<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/check_auth.php';
require_once __DIR__ . '/../includes/validation.php';

$pageTitle = 'Edit Application';
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM hostel_applications WHERE id = ?");
$stmt->execute([$id]);
$app = $stmt->fetch();

if (!$app) {
    flash_set('error', 'Application not found.');
    redirect('applications/index.php');
}

$departments = get_all_departments($pdo);
$currentPrograms = get_programs_by_department($pdo, $app['department_id']);

// Admission quotas (optional — table may not exist yet if migration_quota.sql hasn't run)
$quotas = [];
try {
    $quotas = $pdo->query("SELECT * FROM admission_quotas WHERE status='Active' ORDER BY quota_name")->fetchAll();
} catch (PDOException $e) {
    $quotas = [];
}

$errors = [];
$old = $app;

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

    $errors = validate_application_record($row, ['pdo' => $pdo, 'session_id' => $app['hostel_session_id'], 'exclude_id' => $id]);

    if (empty($errors)) {
        $quotaId = $_POST['admission_quota_id'] ?? '';
        $quotaId = $quotaId !== '' ? (int)$quotaId : null;

        $hasQuotaColumn = !empty($quotas) || array_key_exists('admission_quota_id', $app);

        if ($hasQuotaColumn) {
            $stmt = $pdo->prepare(
                "UPDATE hostel_applications SET
                 form_no=?, student_name=?, father_name=?, cnic_b_form=?, gender=?, contact_number=?, email=?,
                 address=?, district=?, province=?, domicile=?, department_id=?, program_id=?, admission_quota_id=?,
                 degree=?, session=?, semester=?, admission_year=?, percentage=?
                 WHERE id=?"
            );
            $stmt->execute([
                $row['form_no'], $row['student_name'], trim($_POST['father_name'] ?? ''), trim($_POST['cnic_b_form'] ?? ''),
                $row['gender'], trim($_POST['contact_number'] ?? ''), trim($_POST['email'] ?? ''),
                trim($_POST['address'] ?? ''), trim($_POST['district'] ?? ''), trim($_POST['province'] ?? ''), trim($_POST['domicile'] ?? ''),
                $row['department_id'], $row['program_id'], $quotaId,
                trim($_POST['degree'] ?? ''), trim($_POST['session'] ?? ''), trim($_POST['semester'] ?? ''), trim($_POST['admission_year'] ?? ''),
                $row['percentage'], $id,
            ]);
        } else {
            $stmt = $pdo->prepare(
                "UPDATE hostel_applications SET
                 form_no=?, student_name=?, father_name=?, cnic_b_form=?, gender=?, contact_number=?, email=?,
                 address=?, district=?, province=?, domicile=?, department_id=?, program_id=?,
                 degree=?, session=?, semester=?, admission_year=?, percentage=?
                 WHERE id=?"
            );
            $stmt->execute([
                $row['form_no'], $row['student_name'], trim($_POST['father_name'] ?? ''), trim($_POST['cnic_b_form'] ?? ''),
                $row['gender'], trim($_POST['contact_number'] ?? ''), trim($_POST['email'] ?? ''),
                trim($_POST['address'] ?? ''), trim($_POST['district'] ?? ''), trim($_POST['province'] ?? ''), trim($_POST['domicile'] ?? ''),
                $row['department_id'], $row['program_id'],
                trim($_POST['degree'] ?? ''), trim($_POST['session'] ?? ''), trim($_POST['semester'] ?? ''), trim($_POST['admission_year'] ?? ''),
                $row['percentage'], $id,
            ]);
        }

        audit_log($pdo, 'Update application', 'applications', $id, $row['form_no']);
        flash_set('success', 'Application updated successfully.');
        redirect('applications/view.php?id=' . $id);
    }

    // Reload programs for the submitted department so the dropdown is correct on error
    $currentPrograms = get_programs_by_department($pdo, $row['department_id'] ?: 0);
}

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/navbar.php';
?>
<div class="container-fluid py-4">
  <h4 class="mb-3">Edit Application &mdash; <?= e($app['form_no']) ?></h4>

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
            <option value="">-- Select Program --</option>
            <?php foreach ($currentPrograms as $p): ?>
              <option value="<?= $p['id'] ?>" <?= ($old['program_id'] ?? '')==$p['id']?'selected':'' ?>><?= e($p['program_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php if (!empty($quotas)): ?>
        <div class="col-md-4">
          <label class="form-label">Admission Quota</label>
          <select name="admission_quota_id" class="form-select">
            <option value="">-- Not Assigned --</option>
            <?php foreach ($quotas as $q): ?>
              <option value="<?= $q['id'] ?>" <?= ($old['admission_quota_id'] ?? '')==$q['id']?'selected':'' ?>><?= e($q['quota_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>

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
          <input type="text" name="session" class="form-control" value="<?= e($old['session'] ?? '') ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Semester</label>
          <input type="text" name="semester" class="form-control" value="<?= e($old['semester'] ?? '') ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label">Admission Year</label>
          <input type="text" name="admission_year" class="form-control" value="<?= e($old['admission_year'] ?? '') ?>">
        </div>
      </div>

      <div class="mt-4">
        <button class="btn btn-primary"><i class="bi bi-check2"></i> Update Application</button>
        <a href="<?= BASE_URL ?>applications/view.php?id=<?= $id ?>" class="btn btn-outline-secondary">Cancel</a>
      </div>
    </form>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
