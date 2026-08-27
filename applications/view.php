<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/check_auth.php';

$pageTitle = 'Application Details';
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    "SELECT a.*, d.department_name, p.program_name,
            (SELECT is_eligible FROM eligibility_results WHERE application_id = a.id) AS is_eligible,
            (SELECT reason FROM eligibility_results WHERE application_id = a.id) AS eligibility_reason
     FROM hostel_applications a
     JOIN departments d ON d.id = a.department_id
     JOIN programs p ON p.id = a.program_id
     WHERE a.id = ?"
);
$stmt->execute([$id]);
$app = $stmt->fetch();

if (!$app) {
    flash_set('error', 'Application not found.');
    redirect('applications/index.php');
}

// Admission quota name (column may not exist if migration_quota.sql hasn't been run yet)
$quotaName = null;
if (!empty($app['admission_quota_id'])) {
    try {
        $qStmt = $pdo->prepare("SELECT quota_name FROM admission_quotas WHERE id = ?");
        $qStmt->execute([$app['admission_quota_id']]);
        $quotaName = $qStmt->fetchColumn() ?: null;
    } catch (PDOException $e) {
        $quotaName = null;
    }
}

// Allocation info (if any)
$allocStmt = $pdo->prepare(
    "SELECT al.*, h.hostel_name FROM hostel_allocations al
     LEFT JOIN hostels h ON h.id = al.hostel_id
     WHERE al.application_id = ?"
);
$allocStmt->execute([$id]);
$allocation = $allocStmt->fetch();

// Status history
$histStmt = $pdo->prepare(
    "SELECT * FROM application_status_history WHERE application_id = ? ORDER BY created_at DESC"
);
$histStmt->execute([$id]);
$history = $histStmt->fetchAll();

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/navbar.php';
?>
<div class="container-fluid py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Application Details &mdash; <?= e($app['form_no']) ?></h4>
    <div>
      <a href="<?= BASE_URL ?>applications/edit.php?id=<?= $app['id'] ?>" class="btn btn-primary btn-sm"><i class="bi bi-pencil"></i> Edit</a>
      <a href="<?= BASE_URL ?>applications/index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back to List</a>
    </div>
  </div>

  <?php if ($m = flash_get('success')): ?><div class="alert alert-success alert-dismissible fade show"><?= e($m) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

  <div class="card p-4 mb-3">
    <h6 class="mb-3"><i class="bi bi-person-badge"></i> Personal Information</h6>
    <div class="table-responsive">
      <table class="table table-borderless table-sm mb-0">
        <tbody>
          <tr>
            <th class="text-muted" style="width:180px;">Form No.</th>
            <td class="fw-semibold"><?= e($app['form_no']) ?></td>
            <th class="text-muted" style="width:180px;">Gender</th>
            <td class="fw-semibold"><?= e($app['gender']) ?></td>
          </tr>
          <tr>
            <th class="text-muted">Student Name</th>
            <td class="fw-semibold"><?= e($app['student_name']) ?></td>
            <th class="text-muted">Father Name</th>
            <td class="fw-semibold"><?= e($app['father_name'] ?: '-') ?></td>
          </tr>
          <tr>
            <th class="text-muted">CNIC / B-Form</th>
            <td class="fw-semibold"><?= e($app['cnic_b_form'] ?: '-') ?></td>
            <th class="text-muted">Contact Number</th>
            <td class="fw-semibold"><?= e($app['contact_number'] ?: '-') ?></td>
          </tr>
          <tr>
            <th class="text-muted">Email</th>
            <td class="fw-semibold"><?= e($app['email'] ?: '-') ?></td>
            <th class="text-muted">District / Province</th>
            <td class="fw-semibold"><?= e($app['district'] ?: '-') ?> / <?= e($app['province'] ?: '-') ?></td>
          </tr>
          <tr>
            <th class="text-muted">Address</th>
            <td class="fw-semibold" colspan="3"><?= e($app['address'] ?: '-') ?></td>
          </tr>
          <tr>
            <th class="text-muted">Domicile</th>
            <td class="fw-semibold" colspan="3"><?= e($app['domicile'] ?: '-') ?></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card p-4 mb-3">
    <h6 class="mb-3"><i class="bi bi-mortarboard"></i> Academic Information</h6>
    <div class="table-responsive">
      <table class="table table-borderless table-sm mb-0">
        <tbody>
          <tr>
            <th class="text-muted" style="width:180px;">Department</th>
            <td class="fw-semibold" colspan="3"><?= e($app['department_name']) ?></td>
          </tr>
          <tr>
            <th class="text-muted">Program</th>
            <td class="fw-semibold" colspan="3"><?= e($app['program_name']) ?></td>
          </tr>
          <tr>
            <th class="text-muted">Admission Quota</th>
            <td class="fw-semibold"><?= e($quotaName ?? 'Not assigned') ?></td>
            <th class="text-muted" style="width:180px;">Percentage</th>
            <td class="fw-semibold"><?= number_format($app['percentage'], 2) ?>%</td>
          </tr>
          <tr>
            <th class="text-muted">Degree</th>
            <td class="fw-semibold"><?= e($app['degree'] ?: '-') ?></td>
            <th class="text-muted">Session</th>
            <td class="fw-semibold"><?= e($app['session'] ?: '-') ?></td>
          </tr>
          <tr>
            <th class="text-muted">Semester</th>
            <td class="fw-semibold"><?= e($app['semester'] ?: '-') ?></td>
            <th class="text-muted">Admission Year</th>
            <td class="fw-semibold"><?= e($app['admission_year'] ?: '-') ?></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <div class="row">
    <div class="col-md-6">
      <div class="card p-4 mb-3">
        <h6 class="mb-3">Status</h6>
        <p><strong>Application Status:</strong>
          <?php
            $badgeClass = match($app['status']) {
                'Selected' => 'bg-success',
                'Waiting' => 'bg-warning text-dark',
                'Not Eligible', 'Cancelled', 'Not Selected', 'Withdrawn' => 'bg-danger',
                'General Merit' => 'bg-info text-dark',
                default => 'bg-secondary',
            };
          ?>
          <span class="badge <?= $badgeClass ?>"><?= e($app['status']) ?></span>
        </p>
        <p><strong>Eligibility:</strong>
          <?php if ($app['is_eligible'] === null): ?>
            <span class="badge bg-secondary">Not Checked</span>
          <?php elseif ($app['is_eligible']): ?>
            <span class="badge bg-success">Eligible</span>
          <?php else: ?>
            <span class="badge bg-danger">Not Eligible</span>
            <?php if ($app['eligibility_reason']): ?><div class="small text-muted mt-1"><?= e($app['eligibility_reason']) ?></div><?php endif; ?>
          <?php endif; ?>
        </p>
        <?php if ($allocation): ?>
          <p class="mb-0"><strong>Hostel Allocation:</strong>
            <span class="badge <?= $allocation['allocation_status']==='Selected' ? 'bg-success' : ($allocation['allocation_status']==='Waiting' ? 'bg-warning text-dark' : 'bg-danger') ?>">
              <?= e($allocation['allocation_status']) ?>
            </span>
            <?php if ($allocation['hostel_name']): ?> &mdash; <?= e($allocation['hostel_name']) ?><?php endif; ?>
          </p>
        <?php endif; ?>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card p-4 mb-3">
        <h6 class="mb-3">Status History</h6>
        <?php if (empty($history)): ?>
          <p class="text-muted small mb-0">No history recorded.</p>
        <?php else: ?>
          <ul class="list-unstyled small mb-0">
            <?php foreach ($history as $h): ?>
              <li class="mb-2 pb-2 border-bottom">
                <strong><?= e($h['old_status'] ?? 'New') ?> &rarr; <?= e($h['new_status']) ?></strong><br>
                <span class="text-muted"><?= format_datetime($h['created_at']) ?></span>
                <?php if ($h['remarks']): ?><br><span class="text-muted"><?= e($h['remarks']) ?></span><?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
