<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/check_auth.php';
require_once __DIR__ . '/../includes/merit_helper.php';

$pageTitle = 'Program-wise Merit List';

$sessions = $pdo->query("SELECT * FROM hostel_sessions ORDER BY id DESC")->fetchAll();
$activeSession = get_active_session($pdo);
$departments = get_all_departments($pdo, false);

$selectedSessionId = isset($_GET['hostel_session_id']) && $_GET['hostel_session_id'] !== ''
    ? (int)$_GET['hostel_session_id']
    : ($activeSession['id'] ?? null);

$selectedDeptId = isset($_GET['department_id']) && $_GET['department_id'] !== ''
    ? (int)$_GET['department_id']
    : ($departments[0]['id'] ?? null);

$programs = $selectedDeptId ? get_programs_by_department($pdo, $selectedDeptId, false) : [];

$selectedProgId = isset($_GET['program_id']) && $_GET['program_id'] !== ''
    ? (int)$_GET['program_id']
    : ($programs[0]['id'] ?? null);

$rows = [];
$meritListId = null;

// ------------------------------------------------------------
// Generate (persist a new snapshot run) on demand
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate') {
    verify_csrf();

    $genSessionId = (int)($_POST['hostel_session_id'] ?? 0);
    $genProgId = (int)($_POST['program_id'] ?? 0);

    if (!$genSessionId || !$genProgId) {
        flash_set('error', 'Please select a hostel session and a program.');
        redirect('merit/program_wise.php');
    }

    $result = generate_merit_list($pdo, $genSessionId, 'Program', ['program_id' => $genProgId], current_user_id());
    audit_log($pdo, 'Generate program-wise merit list', 'merit_lists', $result['merit_list_id'], "program #{$genProgId}, session #{$genSessionId}, " . count($result['rows']) . ' entries');

    flash_set('success', 'Program merit list generated (' . count($result['rows']) . ' eligible applicant(s)).');
    redirect('merit/program_wise.php?hostel_session_id=' . $genSessionId . '&department_id=' . (int)$_POST['department_id'] . '&program_id=' . $genProgId . '&merit_list_id=' . $result['merit_list_id']);
}

// ------------------------------------------------------------
// Display: either a specific generated list, or the latest one for this session + program
// ------------------------------------------------------------
if ($selectedSessionId && $selectedProgId) {
    if (!empty($_GET['merit_list_id'])) {
        $meritListId = (int)$_GET['merit_list_id'];
    } else {
        $stmt = $pdo->prepare(
            "SELECT id FROM merit_lists
             WHERE hostel_session_id = ? AND list_type = 'Program' AND program_id = ?
             ORDER BY generated_at DESC LIMIT 1"
        );
        $stmt->execute([$selectedSessionId, $selectedProgId]);
        $meritListId = $stmt->fetchColumn() ?: null;
    }

    if ($meritListId) {
        $rows = get_merit_list_entries($pdo, $meritListId);
    }
}

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/navbar.php';
?>
<div class="container-fluid py-4">
  <h4 class="mb-3">Program-wise Merit List</h4>

  <?php if ($m = flash_get('success')): ?><div class="alert alert-success alert-dismissible fade show"><?= e($m) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
  <?php if ($m = flash_get('error')): ?><div class="alert alert-danger alert-dismissible fade show"><?= e($m) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

  <div class="card p-3 mb-4">
    <form method="get" class="row g-2 align-items-end mb-2">
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
      <div class="col-md-4">
        <label class="form-label mb-1">Department</label>
        <select name="department_id" class="form-select" onchange="this.form.submit()">
          <?php foreach ($departments as $d): ?>
            <option value="<?= $d['id'] ?>" <?= (int)$selectedDeptId === (int)$d['id'] ? 'selected' : '' ?>><?= e($d['department_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label mb-1">Program</label>
        <select name="program_id" class="form-select" onchange="this.form.submit()">
          <?php if (empty($programs)): ?>
            <option value="">-- No programs in this department --</option>
          <?php endif; ?>
          <?php foreach ($programs as $p): ?>
            <option value="<?= $p['id'] ?>" <?= (int)$selectedProgId === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['program_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </form>

    <?php if ($selectedSessionId && $selectedProgId): ?>
      <form method="post" class="d-inline">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="generate">
        <input type="hidden" name="hostel_session_id" value="<?= $selectedSessionId ?>">
        <input type="hidden" name="department_id" value="<?= $selectedDeptId ?>">
        <input type="hidden" name="program_id" value="<?= $selectedProgId ?>">
        <button class="btn btn-primary btn-sm">
          <i class="bi bi-arrow-repeat"></i> Generate / Regenerate Merit List
        </button>
      </form>
      <span class="text-muted small ms-2">Ranks eligible applicants of this program by existing percentage. Regenerating creates a new snapshot.</span>
    <?php endif; ?>
  </div>

  <?php if (!$selectedSessionId || !$selectedProgId): ?>
    <div class="alert alert-warning">Please select a hostel session, department, and program.</div>
  <?php elseif (empty($rows)): ?>
    <div class="alert alert-info">
      No merit list has been generated for this program/session yet. Click "Generate" above to create one from eligible applicants.
    </div>
  <?php else: ?>
    <div class="card p-3">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="mb-0"><?= e($rows[0]['program_name']) ?> Merit List &mdash; <?= count($rows) ?> applicant(s)</h6>
        <button class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
      </div>
      <div class="table-responsive">
        <table class="table table-bordered table-sm align-middle">
          <thead class="table-light">
            <tr>
              <th>Rank</th>
              <th>Form No.</th>
              <th>Student Name</th>
              <th>Gender</th>
              <th class="text-center">Percentage</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td><?= $r['rank_no'] ?></td>
                <td><?= e($r['form_no']) ?></td>
                <td><?= e($r['student_name']) ?></td>
                <td><?= e($r['gender']) ?></td>
                <td class="text-center"><?= number_format($r['percentage'], 2) ?></td>
                <td><span class="badge bg-secondary"><?= e($r['status']) ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
</div>
<script>
// Department change should reload the page (via GET) so the Program dropdown re-populates server-side
document.addEventListener('DOMContentLoaded', function () {
  var deptSelect = document.querySelector('select[name="department_id"]');
  if (deptSelect) {
    deptSelect.addEventListener('change', function () {
      var url = new URL(window.location.href);
      url.searchParams.set('department_id', this.value);
      url.searchParams.delete('program_id');
      url.searchParams.delete('merit_list_id');
      window.location.href = url.toString();
    });
  }
});
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
