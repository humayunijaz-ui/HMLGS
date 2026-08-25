<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/check_auth.php';

$pageTitle = 'Quota-wise Report';

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

$rows = [];
if (!$migrationNeeded && $selectedSessionId) {
    // Configured seat limits (Dept > Program > Gender > Quota)
    $matrixStmt = $pdo->prepare(
        "SELECT qsm.department_id, d.department_name, qsm.program_id, p.program_name,
                qsm.gender, qsm.admission_quota_id, q.quota_name, qsm.total_seats
         FROM quota_seat_matrix qsm
         JOIN departments d ON d.id = qsm.department_id
         JOIN programs p ON p.id = qsm.program_id
         JOIN admission_quotas q ON q.id = qsm.admission_quota_id
         WHERE qsm.hostel_session_id = ?
         ORDER BY d.department_name, p.program_name, qsm.gender, q.quota_name"
    );
    $matrixStmt->execute([$selectedSessionId]);
    $matrix = $matrixStmt->fetchAll();

    // Actual application / allocation counts per cell
    $countStmt = $pdo->prepare(
        "SELECT department_id, program_id, gender, admission_quota_id,
                COUNT(*) AS applied_count,
                SUM(CASE WHEN status = 'Selected' THEN 1 ELSE 0 END) AS allocated_count
         FROM hostel_applications
         WHERE hostel_session_id = ?
         GROUP BY department_id, program_id, gender, admission_quota_id"
    );
    $countStmt->execute([$selectedSessionId]);
    $counts = [];
    foreach ($countStmt->fetchAll() as $c) {
        $key = $c['department_id'] . '-' . $c['program_id'] . '-' . $c['gender'] . '-' . $c['admission_quota_id'];
        $counts[$key] = $c;
    }

    foreach ($matrix as $m) {
        $key = $m['department_id'] . '-' . $m['program_id'] . '-' . $m['gender'] . '-' . $m['admission_quota_id'];
        $c = $counts[$key] ?? ['applied_count' => 0, 'allocated_count' => 0];

        $deptId = $m['department_id'];
        $progId = $m['program_id'];
        if (!isset($rows[$deptId])) {
            $rows[$deptId] = ['department_name' => $m['department_name'], 'programs' => []];
        }
        if (!isset($rows[$deptId]['programs'][$progId])) {
            $rows[$deptId]['programs'][$progId] = ['program_name' => $m['program_name'], 'cells' => []];
        }
        $rows[$deptId]['programs'][$progId]['cells'][] = [
            'gender' => $m['gender'],
            'quota_name' => $m['quota_name'],
            'quota_seats' => (int)$m['total_seats'],
            'applied' => (int)$c['applied_count'],
            'allocated' => (int)$c['allocated_count'],
        ];
    }
}

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/navbar.php';
?>
<div class="container-fluid py-4">
  <h4 class="mb-3">Quota-wise Report &mdash; Department &gt; Program &gt; Gender &gt; Quota</h4>

  <?php if ($migrationNeeded): ?>
    <div class="alert alert-danger">
      Required tables don't exist yet. Please import <code>database/migration_quota.sql</code> via phpMyAdmin first, then reload this page.
    </div>
  <?php else: ?>

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
  <?php elseif (empty($rows)): ?>
    <div class="alert alert-info">
      No quota seat matrix configured for this session yet.
      <a href="<?= BASE_URL ?>quotas/matrix.php?hostel_session_id=<?= $selectedSessionId ?>">Configure it here</a>.
    </div>
  <?php else: ?>
    <?php foreach ($rows as $dept): ?>
      <div class="card p-3 mb-3">
        <h6><?= e($dept['department_name']) ?></h6>
        <?php foreach ($dept['programs'] as $prog): ?>
          <div class="mb-3">
            <div class="fw-semibold small text-muted mb-1"><?= e($prog['program_name']) ?></div>
            <div class="table-responsive">
              <table class="table table-bordered table-sm align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Gender</th><th>Quota</th>
                    <th class="text-center">Quota Seats</th>
                    <th class="text-center">Applied</th>
                    <th class="text-center">Allocated</th>
                    <th class="text-center">Remaining</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($prog['cells'] as $cell): ?>
                    <tr>
                      <td><?= e($cell['gender']) ?></td>
                      <td><?= e($cell['quota_name']) ?></td>
                      <td class="text-center"><?= $cell['quota_seats'] ?></td>
                      <td class="text-center"><?= $cell['applied'] ?></td>
                      <td class="text-center"><?= $cell['allocated'] ?></td>
                      <?php $remaining = $cell['quota_seats'] - $cell['allocated']; ?>
                      <td class="text-center <?= $remaining < 0 ? 'text-danger fw-bold' : '' ?>"><?= $remaining ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
