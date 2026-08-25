<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/check_auth.php';

$pageTitle = 'Quota Seat Matrix';

$sessions = $pdo->query("SELECT * FROM hostel_sessions ORDER BY id DESC")->fetchAll();
$activeSession = get_active_session($pdo);
$selectedSessionId = isset($_GET['hostel_session_id']) && $_GET['hostel_session_id'] !== ''
    ? (int)$_GET['hostel_session_id']
    : ($activeSession['id'] ?? null);

$migrationNeeded = false;
try {
    $pdo->query("SELECT 1 FROM quota_seat_matrix LIMIT 1");
} catch (PDOException $e) {
    $migrationNeeded = true;
}

$quotas = [];
$departments = [];
if (!$migrationNeeded) {
    $quotas = $pdo->query("SELECT * FROM admission_quotas WHERE status='Active' ORDER BY quota_name")->fetchAll();
    $departments = get_all_departments($pdo, false);
}

// ------------------------------------------------------------
// Save the whole matrix (upsert every cell submitted)
// ------------------------------------------------------------
if (!$migrationNeeded && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $postSessionId = (int)$_POST['hostel_session_id'];

    $upsert = $pdo->prepare(
        "INSERT INTO quota_seat_matrix (hostel_session_id, department_id, program_id, gender, admission_quota_id, total_seats)
         VALUES (?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE total_seats = VALUES(total_seats)"
    );

    $count = 0;
    foreach ($_POST['seats'] ?? [] as $progId => $genders) {
        foreach ($genders as $gender => $quotaVals) {
            if (!in_array($gender, ['Male', 'Female'], true)) continue;
            foreach ($quotaVals as $quotaId => $seats) {
                $seats = (int)$seats;
                $progStmt = $pdo->prepare("SELECT department_id FROM programs WHERE id = ?");
                $progStmt->execute([(int)$progId]);
                $deptId = $progStmt->fetchColumn();
                if (!$deptId) continue;
                $upsert->execute([$postSessionId, $deptId, (int)$progId, $gender, (int)$quotaId, $seats]);
                $count++;
            }
        }
    }
    audit_log($pdo, 'Update quota seat matrix', 'quota_seat_matrix', null, "{$count} cell(s) updated for session #{$postSessionId}");
    flash_set('success', 'Seat matrix saved.');
    redirect('quotas/matrix.php?hostel_session_id=' . $postSessionId);
}

// ------------------------------------------------------------
// Load existing values for the selected session
// ------------------------------------------------------------
$existing = []; // [program_id][gender][quota_id] = seats
if (!$migrationNeeded && $selectedSessionId) {
    $stmt = $pdo->prepare("SELECT program_id, gender, admission_quota_id, total_seats FROM quota_seat_matrix WHERE hostel_session_id = ?");
    $stmt->execute([$selectedSessionId]);
    foreach ($stmt->fetchAll() as $r) {
        $existing[$r['program_id']][$r['gender']][$r['admission_quota_id']] = (int)$r['total_seats'];
    }
}

// Programs grouped by department
$programsByDept = [];
if (!$migrationNeeded) {
    $progRows = $pdo->query(
        "SELECT p.id, p.program_name, p.department_id FROM programs p WHERE p.status='Active' ORDER BY p.program_name"
    )->fetchAll();
    foreach ($progRows as $p) {
        $programsByDept[$p['department_id']][] = $p;
    }
}

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/navbar.php';
?>
<div class="container-fluid py-4">
  <h4 class="mb-3">Quota Seat Matrix &mdash; Department &gt; Program &gt; Gender &gt; Quota</h4>

  <?php if ($migrationNeeded): ?>
    <div class="alert alert-danger">
      Required tables don't exist yet. Please import <code>database/migration_quota.sql</code> via phpMyAdmin first, then reload this page.
    </div>
  <?php else: ?>

  <?php if ($m = flash_get('success')): ?><div class="alert alert-success alert-dismissible fade show"><?= e($m) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
  <?php if ($m = flash_get('error')): ?><div class="alert alert-danger alert-dismissible fade show"><?= e($m) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

  <div class="card p-3 mb-4">
    <form method="get" class="row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label mb-1">Hostel Session</label>
        <select name="hostel_session_id" class="form-select" onchange="this.form.submit()">
          <option value="">-- Select Session --</option>
          <?php foreach ($sessions as $s): ?>
            <option value="<?= $s['id'] ?>" <?= (int)$selectedSessionId === (int)$s['id'] ? 'selected' : '' ?>>
              <?= e($s['session_name']) ?><?= $s['is_active'] ? ' (Active)' : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </form>
  </div>

  <?php if (!$selectedSessionId): ?>
    <div class="alert alert-warning">Please select a hostel session.</div>
  <?php elseif (empty($quotas)): ?>
    <div class="alert alert-warning">No active admission quotas defined. <a href="<?= BASE_URL ?>quotas/index.php">Add one first</a>.</div>
  <?php else: ?>

    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="hostel_session_id" value="<?= $selectedSessionId ?>">

      <?php foreach ($departments as $dept): ?>
        <?php $progs = $programsByDept[$dept['id']] ?? []; if (empty($progs)) continue; ?>
        <div class="card p-3 mb-3">
          <h6><?= e($dept['department_name']) ?></h6>
          <div class="table-responsive">
            <table class="table table-bordered table-sm align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th rowspan="2" class="align-middle">Program</th>
                  <th rowspan="2" class="align-middle">Gender</th>
                  <?php foreach ($quotas as $q): ?>
                    <th class="text-center"><?= e($q['quota_name']) ?></th>
                  <?php endforeach; ?>
                  <th rowspan="2" class="align-middle text-center">Row Total</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($progs as $prog): ?>
                  <?php foreach (['Male', 'Female'] as $gender): ?>
                    <tr>
                      <?php if ($gender === 'Male'): ?>
                        <td rowspan="2" class="align-middle fw-semibold"><?= e($prog['program_name']) ?></td>
                      <?php endif; ?>
                      <td><?= $gender ?></td>
                      <?php $rowTotal = 0; foreach ($quotas as $q):
                        $val = $existing[$prog['id']][$gender][$q['id']] ?? 0;
                        $rowTotal += $val;
                      ?>
                        <td>
                          <input type="number" min="0" class="form-control form-control-sm text-center quota-cell"
                                 name="seats[<?= $prog['id'] ?>][<?= $gender ?>][<?= $q['id'] ?>]"
                                 value="<?= $val ?>">
                        </td>
                      <?php endforeach; ?>
                      <td class="text-center fw-semibold row-total"><?= $rowTotal ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endforeach; ?>

      <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save Seat Matrix</button>
    </form>

    <script>
    // Live row-total recompute
    document.querySelectorAll('.quota-cell').forEach(function (input) {
      input.addEventListener('input', function () {
        const row = input.closest('tr');
        let sum = 0;
        row.querySelectorAll('.quota-cell').forEach(function (c) { sum += parseInt(c.value || 0, 10); });
        row.querySelector('.row-total').textContent = sum;
      });
    });
    </script>
  <?php endif; ?>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
