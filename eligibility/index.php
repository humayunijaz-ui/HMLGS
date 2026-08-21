<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/check_auth.php';

$pageTitle = 'Eligibility';
$activeSession = get_active_session($pdo);
$sessionId = $activeSession['id'] ?? null;

// ------------------------------------------------------------
// Handle eligibility decision (single application)
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'set_eligibility') {
        $id = (int)$_POST['id'];
        $isEligible = (int)$_POST['is_eligible']; // 1 or 0
        $reason = trim($_POST['reason'] ?? '');

        $stmt = $pdo->prepare("SELECT status FROM hostel_applications WHERE id = ?");
        $stmt->execute([$id]);
        $oldStatus = $stmt->fetchColumn();

        // Upsert eligibility_results
        $stmt = $pdo->prepare("SELECT id FROM eligibility_results WHERE application_id = ?");
        $stmt->execute([$id]);
        $existing = $stmt->fetchColumn();

        if ($existing) {
            $pdo->prepare("UPDATE eligibility_results SET is_eligible = ?, reason = ?, checked_by = ?, checked_at = NOW() WHERE application_id = ?")
                ->execute([$isEligible, $reason ?: null, current_user_id(), $id]);
        } else {
            $pdo->prepare("INSERT INTO eligibility_results (application_id, is_eligible, reason, checked_by) VALUES (?,?,?,?)")
                ->execute([$id, $isEligible, $reason ?: null, current_user_id()]);
        }

        $newStatus = $isEligible ? 'Eligible' : 'Not Eligible';
        $pdo->prepare("UPDATE hostel_applications SET status = ? WHERE id = ?")->execute([$newStatus, $id]);
        $pdo->prepare("INSERT INTO application_status_history (application_id, old_status, new_status, changed_by, remarks) VALUES (?,?,?,?,?)")
            ->execute([$id, $oldStatus, $newStatus, current_user_id(), $isEligible ? 'Marked eligible' : ('Marked not eligible: ' . $reason)]);

        audit_log($pdo, 'Set eligibility', 'eligibility', $id, $newStatus . ($reason ? " ({$reason})" : ''));
        flash_set('success', 'Eligibility updated.');
    } elseif ($action === 'bulk_eligible') {
        // Mark all currently-filtered "Not Checked" / "Applied" applications as Eligible in one go
        $ids = array_filter(array_map('intval', explode(',', $_POST['ids'] ?? '')));
        $count = 0;
        foreach ($ids as $id) {
            $stmt = $pdo->prepare("SELECT id FROM eligibility_results WHERE application_id = ?");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn()) continue; // skip already-checked ones

            $pdo->prepare("INSERT INTO eligibility_results (application_id, is_eligible, reason, checked_by) VALUES (?,1,NULL,?)")
                ->execute([$id, current_user_id()]);
            $pdo->prepare("UPDATE hostel_applications SET status = 'Eligible' WHERE id = ?")->execute([$id]);
            $pdo->prepare("INSERT INTO application_status_history (application_id, old_status, new_status, changed_by, remarks) VALUES (?, 'Applied', 'Eligible', ?, 'Bulk marked eligible')")
                ->execute([$id, current_user_id()]);
            $count++;
        }
        audit_log($pdo, 'Bulk set eligibility', 'eligibility', null, "{$count} application(s) marked eligible");
        flash_set('success', "{$count} application(s) marked eligible.");
    }

    redirect('eligibility/index.php' . (!empty($_GET) ? '?' . http_build_query($_GET) : ''));
}

// ------------------------------------------------------------
// Filters
// ------------------------------------------------------------
$filterGender = $_GET['gender'] ?? '';
$filterDept   = $_GET['department_id'] ?? '';
$filterProg   = $_GET['program_id'] ?? '';
$filterElig   = $_GET['eligibility'] ?? ''; // '', 'checked', 'not_checked', 'eligible', 'not_eligible'
$search       = trim($_GET['q'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 25;
$offset       = ($page - 1) * $perPage;

$where = ["a.hostel_session_id = ?"];
$params = [$sessionId];

if ($filterGender !== '') { $where[] = "a.gender = ?"; $params[] = $filterGender; }
if ($filterDept !== '')   { $where[] = "a.department_id = ?"; $params[] = $filterDept; }
if ($filterProg !== '')   { $where[] = "a.program_id = ?"; $params[] = $filterProg; }
if ($search !== '') {
    $where[] = "(a.student_name LIKE ? OR a.form_no LIKE ? OR a.cnic_b_form LIKE ?)";
    $like = "%{$search}%";
    array_push($params, $like, $like, $like);
}

switch ($filterElig) {
    case 'not_checked':
        $where[] = "er.id IS NULL"; break;
    case 'checked':
        $where[] = "er.id IS NOT NULL"; break;
    case 'eligible':
        $where[] = "er.is_eligible = 1"; break;
    case 'not_eligible':
        $where[] = "er.is_eligible = 0"; break;
}
$whereSql = implode(' AND ', $where);

$baseFrom = "FROM hostel_applications a
             JOIN departments d ON d.id = a.department_id
             JOIN programs p ON p.id = a.program_id
             LEFT JOIN eligibility_results er ON er.application_id = a.id
             WHERE {$whereSql}";

$countStmt = $pdo->prepare("SELECT COUNT(*) {$baseFrom}");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));

$sql = "SELECT a.id, a.form_no, a.student_name, a.gender, a.percentage, a.status,
               d.department_name, p.program_name,
               er.is_eligible, er.reason
        {$baseFrom}
        ORDER BY a.created_at DESC
        LIMIT {$perPage} OFFSET {$offset}";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Summary counts (for the whole session, not just current page)
$summarySql = "SELECT
    COUNT(*) AS total,
    SUM(CASE WHEN er.id IS NULL THEN 1 ELSE 0 END) AS not_checked,
    SUM(CASE WHEN er.is_eligible = 1 THEN 1 ELSE 0 END) AS eligible,
    SUM(CASE WHEN er.is_eligible = 0 THEN 1 ELSE 0 END) AS not_eligible
    FROM hostel_applications a
    LEFT JOIN eligibility_results er ON er.application_id = a.id
    WHERE a.hostel_session_id = ?";
$summaryStmt = $pdo->prepare($summarySql);
$summaryStmt->execute([$sessionId]);
$summary = $summaryStmt->fetch();

$departments = get_all_departments($pdo, false);
$programs = $filterDept ? get_programs_by_department($pdo, $filterDept, false) : [];

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/navbar.php';
?>
<div class="container-fluid py-4">
  <h4 class="mb-3">Eligibility Management</h4>

  <?php if (!$activeSession): ?>
    <div class="alert alert-warning">No active hostel session. <a href="<?= BASE_URL ?>sessions/index.php">Set one up first</a>.</div>
  <?php endif; ?>

  <?php if ($m = flash_get('success')): ?><div class="alert alert-success alert-dismissible fade show"><?= e($m) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
  <?php if ($m = flash_get('error')): ?><div class="alert alert-danger alert-dismissible fade show"><?= e($m) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

  <div class="row g-2 mb-3">
    <div class="col"><div class="card p-3 text-center"><div class="fs-4 fw-bold"><?= (int)($summary['total'] ?? 0) ?></div><div class="text-muted small">Total Applications</div></div></div>
    <div class="col"><div class="card p-3 text-center"><div class="fs-4 fw-bold text-secondary"><?= (int)($summary['not_checked'] ?? 0) ?></div><div class="text-muted small">Not Checked</div></div></div>
    <div class="col"><div class="card p-3 text-center"><div class="fs-4 fw-bold text-success"><?= (int)($summary['eligible'] ?? 0) ?></div><div class="text-muted small">Eligible</div></div></div>
    <div class="col"><div class="card p-3 text-center"><div class="fs-4 fw-bold text-danger"><?= (int)($summary['not_eligible'] ?? 0) ?></div><div class="text-muted small">Not Eligible</div></div></div>
  </div>

  <div class="card p-3 mb-3">
    <form method="get" class="row g-2">
      <div class="col-md-2">
        <select name="gender" class="form-select" onchange="this.form.submit()">
          <option value="">All Genders</option>
          <option value="Male" <?= $filterGender==='Male'?'selected':'' ?>>Male</option>
          <option value="Female" <?= $filterGender==='Female'?'selected':'' ?>>Female</option>
        </select>
      </div>
      <div class="col-md-3">
        <select name="department_id" class="form-select" onchange="this.form.submit()">
          <option value="">All Departments</option>
          <?php foreach ($departments as $d): ?>
            <option value="<?= $d['id'] ?>" <?= $filterDept==$d['id']?'selected':'' ?>><?= e($d['department_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <select name="eligibility" class="form-select" onchange="this.form.submit()">
          <option value="">All (Checked + Not Checked)</option>
          <option value="not_checked" <?= $filterElig==='not_checked'?'selected':'' ?>>Not Checked</option>
          <option value="eligible" <?= $filterElig==='eligible'?'selected':'' ?>>Eligible</option>
          <option value="not_eligible" <?= $filterElig==='not_eligible'?'selected':'' ?>>Not Eligible</option>
        </select>
      </div>
      <div class="col-md-3">
        <input type="text" name="q" class="form-control" placeholder="Search name / form no / CNIC" value="<?= e($search) ?>">
      </div>
      <div class="col-md-1">
        <button class="btn btn-outline-primary w-100"><i class="bi bi-search"></i></button>
      </div>
    </form>
  </div>

  <div class="card p-3">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <small class="text-muted">Showing <?= count($rows) ?> of <?= number_format($totalRows) ?> record(s)</small>
      <?php $pendingIds = implode(',', array_column(array_filter($rows, fn($r) => $r['is_eligible'] === null), 'id')); ?>
      <?php if ($pendingIds !== ''): ?>
        <form method="post" onsubmit="return confirm('Mark all not-checked applications on this page as Eligible?');">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="bulk_eligible">
          <input type="hidden" name="ids" value="<?= e($pendingIds) ?>">
          <button class="btn btn-sm btn-outline-success"><i class="bi bi-check2-all"></i> Mark All Not-Checked (this page) Eligible</button>
        </form>
      <?php endif; ?>
    </div>

    <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead>
        <tr>
          <th>Form No.</th><th>Name</th><th>Gender</th><th>Dept</th><th>Program</th>
          <th class="text-center">%</th><th>Eligibility</th><th>Reason</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= e($r['form_no']) ?></td>
          <td><?= e($r['student_name']) ?></td>
          <td><?= e($r['gender']) ?></td>
          <td><?= e($r['department_name']) ?></td>
          <td><?= e($r['program_name']) ?></td>
          <td class="text-center"><?= number_format($r['percentage'], 2) ?></td>
          <td>
            <?php if ($r['is_eligible'] === null): ?>
              <span class="badge bg-secondary">Not Checked</span>
            <?php elseif ($r['is_eligible']): ?>
              <span class="badge bg-success">Eligible</span>
            <?php else: ?>
              <span class="badge bg-danger">Not Eligible</span>
            <?php endif; ?>
          </td>
          <td class="small text-muted"><?= e($r['reason'] ?? '') ?></td>
          <td class="text-nowrap">
            <form method="post" class="d-inline">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="set_eligibility">
              <input type="hidden" name="id" value="<?= $r['id'] ?>">
              <input type="hidden" name="is_eligible" value="1">
              <button class="btn btn-sm btn-outline-success" title="Mark Eligible"><i class="bi bi-check-lg"></i></button>
            </form>
            <button type="button" class="btn btn-sm btn-outline-danger" title="Mark Not Eligible"
                    onclick="markNotEligible(<?= $r['id'] ?>)"><i class="bi bi-x-lg"></i></button>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($rows)): ?>
        <tr><td colspan="9" class="text-center text-muted py-4">No applications found.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
    </div>

    <div class="d-flex justify-content-between align-items-center mt-3">
      <nav>
        <ul class="pagination pagination-sm mb-0">
          <?php for ($i = 1; $i <= $totalPages; $i++):
            $qs = array_merge($_GET, ['page' => $i]);
          ?>
            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
              <a class="page-link" href="?<?= http_build_query($qs) ?>"><?= $i ?></a>
            </li>
          <?php endfor; ?>
        </ul>
      </nav>
    </div>
  </div>
</div>

<!-- Hidden form used to submit "Not Eligible" with a reason -->
<form method="post" id="notEligibleForm" class="d-none">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="set_eligibility">
  <input type="hidden" name="id" id="notEligibleId">
  <input type="hidden" name="is_eligible" value="0">
  <input type="hidden" name="reason" id="notEligibleReason">
</form>
<script>
function markNotEligible(id) {
  const reason = prompt('Reason for marking this application as Not Eligible:');
  if (reason === null) return; // cancelled
  document.getElementById('notEligibleId').value = id;
  document.getElementById('notEligibleReason').value = reason;
  document.getElementById('notEligibleForm').submit();
}
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
