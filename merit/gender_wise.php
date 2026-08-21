<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/check_auth.php';
require_once __DIR__ . '/../includes/merit_helper.php';

$pageTitle = 'Gender-wise Merit List';

$sessions = $pdo->query("SELECT * FROM hostel_sessions ORDER BY id DESC")->fetchAll();
$activeSession = get_active_session($pdo);

$selectedSessionId = isset($_GET['hostel_session_id']) && $_GET['hostel_session_id'] !== ''
    ? (int)$_GET['hostel_session_id']
    : ($activeSession['id'] ?? null);

$selectedGender = $_GET['gender'] ?? ($_POST['gender'] ?? 'Male');
if (!in_array($selectedGender, ['Male', 'Female'], true)) {
    $selectedGender = 'Male';
}

$rows = [];
$meritListId = null;

// ------------------------------------------------------------
// Generate (persist a new snapshot run) on demand
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate') {
    verify_csrf();

    $genSessionId = (int)($_POST['hostel_session_id'] ?? 0);
    $genGender = in_array($_POST['gender'] ?? '', ['Male', 'Female'], true) ? $_POST['gender'] : 'Male';

    if (!$genSessionId) {
        flash_set('error', 'Please select a hostel session.');
        redirect('merit/gender_wise.php');
    }

    $result = generate_merit_list($pdo, $genSessionId, 'Gender', ['gender' => $genGender], current_user_id());
    audit_log($pdo, 'Generate gender-wise merit list', 'merit_lists', $result['merit_list_id'], "{$genGender} merit list, session #{$genSessionId}, " . count($result['rows']) . ' entries');

    flash_set('success', ucfirst(strtolower($genGender)) . ' merit list generated (' . count($result['rows']) . ' eligible applicant(s)).');
    redirect('merit/gender_wise.php?hostel_session_id=' . $genSessionId . '&gender=' . urlencode($genGender) . '&merit_list_id=' . $result['merit_list_id']);
}

// ------------------------------------------------------------
// Display: either a specific generated list, or the latest one
// for this session + gender, if any exists.
// ------------------------------------------------------------
if ($selectedSessionId) {
    if (!empty($_GET['merit_list_id'])) {
        $meritListId = (int)$_GET['merit_list_id'];
    } else {
        $stmt = $pdo->prepare(
            "SELECT id FROM merit_lists
             WHERE hostel_session_id = ? AND list_type = 'Gender' AND gender = ?
             ORDER BY generated_at DESC LIMIT 1"
        );
        $stmt->execute([$selectedSessionId, $selectedGender]);
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
  <h4 class="mb-3">Gender-wise Merit List</h4>

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
      <div class="col-md-3">
        <label class="form-label mb-1">Gender</label>
        <select name="gender" class="form-select" onchange="this.form.submit()">
          <option value="Male" <?= $selectedGender === 'Male' ? 'selected' : '' ?>>Male</option>
          <option value="Female" <?= $selectedGender === 'Female' ? 'selected' : '' ?>>Female</option>
        </select>
      </div>
    </form>

    <?php if ($selectedSessionId): ?>
      <form method="post" class="d-inline">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="generate">
        <input type="hidden" name="hostel_session_id" value="<?= $selectedSessionId ?>">
        <input type="hidden" name="gender" value="<?= e($selectedGender) ?>">
        <button class="btn btn-primary btn-sm">
          <i class="bi bi-arrow-repeat"></i> Generate / Regenerate <?= e($selectedGender) ?> Merit List
        </button>
      </form>
      <span class="text-muted small ms-2">Ranks eligible <?= strtolower($selectedGender) ?> applicants by existing percentage. Regenerating creates a new snapshot.</span>
    <?php endif; ?>
  </div>

  <?php if (!$selectedSessionId): ?>
    <div class="alert alert-warning">Please select a hostel session.</div>
  <?php elseif (empty($rows)): ?>
    <div class="alert alert-info">
      No <?= strtolower($selectedGender) ?> merit list has been generated for this session yet. Click "Generate" above to create one from eligible applicants.
    </div>
  <?php else: ?>
    <div class="card p-3">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="mb-0"><?= e($selectedGender) ?> General Merit List &mdash; <?= count($rows) ?> applicant(s)</h6>
        <button class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
      </div>
      <div class="table-responsive">
        <table class="table table-bordered table-sm align-middle">
          <thead class="table-light">
            <tr>
              <th>Rank</th>
              <th>Form No.</th>
              <th>Student Name</th>
              <th>Department</th>
              <th>Program</th>
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
                <td><?= e($r['department_name']) ?></td>
                <td><?= e($r['program_name']) ?></td>
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
<?php require __DIR__ . '/../includes/footer.php'; ?>
