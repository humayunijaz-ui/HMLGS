<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/check_auth.php';

$pageTitle = 'Reports';

// ------------------------------------------------------------
// Session selection (defaults to the active hostel session)
// ------------------------------------------------------------
$sessions = $pdo->query("SELECT * FROM hostel_sessions ORDER BY id DESC")->fetchAll();

$activeSession = get_active_session($pdo);
$selectedSessionId = isset($_GET['hostel_session_id']) && $_GET['hostel_session_id'] !== ''
    ? (int)$_GET['hostel_session_id']
    : ($activeSession['id'] ?? null);

// ------------------------------------------------------------
// Department > Program > Gender seat breakdown
//   "Seats" = allocated seats (hostel_allocations.allocation_status = 'Selected')
//   for the student's department/program/gender in the chosen session.
// ------------------------------------------------------------
$rows = [];
$grandTotal = 0;

if ($selectedSessionId) {
    $sql = "SELECT
                d.id   AS department_id,
                d.department_name,
                p.id   AS program_id,
                p.program_name,
                a.gender,
                COUNT(*) AS seat_count
            FROM hostel_allocations al
            JOIN hostel_applications a ON a.id = al.application_id
            JOIN departments d ON d.id = a.department_id
            JOIN programs p    ON p.id = a.program_id
            WHERE al.hostel_session_id = ?
              AND al.allocation_status = 'Selected'
            GROUP BY d.id, d.department_name, p.id, p.program_name, a.gender
            ORDER BY d.department_name, p.program_name, a.gender";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$selectedSessionId]);
    $flat = $stmt->fetchAll();

    // Build nested structure: department -> program -> gender -> count
    foreach ($flat as $r) {
        $deptId = $r['department_id'];
        $progId = $r['program_id'];

        if (!isset($rows[$deptId])) {
            $rows[$deptId] = [
                'department_name' => $r['department_name'],
                'programs' => [],
                'dept_total' => 0,
            ];
        }
        if (!isset($rows[$deptId]['programs'][$progId])) {
            $rows[$deptId]['programs'][$progId] = [
                'program_name' => $r['program_name'],
                'Male' => 0,
                'Female' => 0,
                'program_total' => 0,
            ];
        }

        $count = (int)$r['seat_count'];
        $rows[$deptId]['programs'][$progId][$r['gender']] += $count;
        $rows[$deptId]['programs'][$progId]['program_total'] += $count;
        $rows[$deptId]['dept_total'] += $count;
        $grandTotal += $count;
    }
}

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/navbar.php';
?>
<div class="container-fluid py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Reports &mdash; Seats by Department / Program / Gender</h4>
  </div>

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
    <div class="alert alert-warning">Please select a hostel session to view the report.</div>
  <?php elseif (empty($rows)): ?>
    <div class="alert alert-info">No allocated seats found for the selected session.</div>
  <?php else: ?>
    <div class="card p-3">
      <div class="table-responsive">
        <table class="table table-bordered align-middle">
          <thead class="table-light">
            <tr>
              <th>Department</th>
              <th>Program</th>
              <th class="text-center">Male</th>
              <th class="text-center">Female</th>
              <th class="text-center">Total Seats</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($rows as $dept): ?>
            <?php $progCount = count($dept['programs']); $first = true; ?>
            <?php foreach ($dept['programs'] as $prog): ?>
              <tr>
                <?php if ($first): ?>
                  <td rowspan="<?= $progCount ?>" class="fw-semibold align-middle"><?= e($dept['department_name']) ?></td>
                <?php endif; ?>
                <td><?= e($prog['program_name']) ?></td>
                <td class="text-center"><?= $prog['Male'] ?></td>
                <td class="text-center"><?= $prog['Female'] ?></td>
                <td class="text-center fw-semibold"><?= $prog['program_total'] ?></td>
              </tr>
              <?php $first = false; ?>
            <?php endforeach; ?>
            <tr class="table-secondary">
              <td colspan="2" class="text-end fw-semibold">Department Total (<?= e($dept['department_name']) ?>)</td>
              <?php
                $deptMale = array_sum(array_column($dept['programs'], 'Male'));
                $deptFemale = array_sum(array_column($dept['programs'], 'Female'));
              ?>
              <td class="text-center fw-semibold"><?= $deptMale ?></td>
              <td class="text-center fw-semibold"><?= $deptFemale ?></td>
              <td class="text-center fw-semibold"><?= $dept['dept_total'] ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr class="table-dark">
              <th colspan="4" class="text-end">Grand Total</th>
              <th class="text-center"><?= $grandTotal ?></th>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
