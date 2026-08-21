<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/check_auth.php';

$pageTitle = 'Applications';
$activeSession = get_active_session($pdo);
$sessionId = $activeSession['id'] ?? null;

// Handle status change (e.g. Cancel / Withdraw)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'change_status') {
        $id = (int)$_POST['id'];
        $newStatus = $_POST['new_status'];
        $allowed = ['Cancelled', 'Withdrawn'];
        if (in_array($newStatus, $allowed, true)) {
            $stmt = $pdo->prepare("SELECT status FROM hostel_applications WHERE id = ?");
            $stmt->execute([$id]);
            $old = $stmt->fetchColumn();

            $pdo->prepare("UPDATE hostel_applications SET status = ? WHERE id = ?")->execute([$newStatus, $id]);
            $pdo->prepare("INSERT INTO application_status_history (application_id, old_status, new_status, changed_by, remarks) VALUES (?,?,?,?,?)")
                ->execute([$id, $old, $newStatus, current_user_id(), 'Manual status change']);
            audit_log($pdo, 'Change application status', 'applications', $id, "{$old} -> {$newStatus}");
            flash_set('success', 'Application status updated.');
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM hostel_applications WHERE id = ?")->execute([$id]);
        audit_log($pdo, 'Delete application', 'applications', $id);
        flash_set('success', 'Application deleted.');
    }
    redirect('applications/index.php' . (!empty($_GET) ? '?' . http_build_query($_GET) : ''));
}

// Filters
$filterGender = $_GET['gender'] ?? '';
$filterDept   = $_GET['department_id'] ?? '';
$filterProg   = $_GET['program_id'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$search       = trim($_GET['q'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 25;
$offset       = ($page - 1) * $perPage;

$where = ["a.hostel_session_id = ?"];
$params = [$sessionId];

if ($filterGender !== '') { $where[] = "a.gender = ?"; $params[] = $filterGender; }
if ($filterDept !== '')   { $where[] = "a.department_id = ?"; $params[] = $filterDept; }
if ($filterProg !== '')   { $where[] = "a.program_id = ?"; $params[] = $filterProg; }
if ($filterStatus !== '') { $where[] = "a.status = ?"; $params[] = $filterStatus; }
if ($search !== '') {
    $where[] = "(a.student_name LIKE ? OR a.form_no LIKE ? OR a.cnic_b_form LIKE ?)";
    $like = "%{$search}%";
    array_push($params, $like, $like, $like);
}
$whereSql = implode(' AND ', $where);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM hostel_applications a WHERE {$whereSql}");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));

$sql = "SELECT a.*, d.department_name, p.program_name,
        (SELECT is_eligible FROM eligibility_results WHERE application_id = a.id) AS is_eligible
        FROM hostel_applications a
        JOIN departments d ON d.id = a.department_id
        JOIN programs p ON p.id = a.program_id
        WHERE {$whereSql}
        ORDER BY a.created_at DESC
        LIMIT {$perPage} OFFSET {$offset}";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$departments = get_all_departments($pdo, false);
$programs = $filterDept ? get_programs_by_department($pdo, $filterDept, false) : [];

$statusOptions = ['Applied','Eligible','Not Eligible','General Merit','Selected','Not Selected','Waiting','Cancelled','Withdrawn'];

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/navbar.php';
?>
<div class="container-fluid py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Applications</h4>
    <div>
      <a href="<?= BASE_URL ?>applications/create.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> New Application</a>
      <a href="<?= BASE_URL ?>imports/upload.php" class="btn btn-outline-secondary"><i class="bi bi-upload"></i> Import</a>
    </div>
  </div>

  <?php if (!$activeSession): ?>
    <div class="alert alert-warning">No active hostel session. <a href="<?= BASE_URL ?>sessions/index.php">Set one up first</a>.</div>
  <?php endif; ?>

  <?php if ($m = flash_get('success')): ?><div class="alert alert-success alert-dismissible fade show"><?= e($m) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
  <?php if ($m = flash_get('error')): ?><div class="alert alert-danger alert-dismissible fade show"><?= e($m) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

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
        <select name="status" class="form-select" onchange="this.form.submit()">
          <option value="">All Statuses</option>
          <?php foreach ($statusOptions as $st): ?>
            <option value="<?= e($st) ?>" <?= $filterStatus===$st?'selected':'' ?>><?= e($st) ?></option>
          <?php endforeach; ?>
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
    <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead>
        <tr>
          <th>Form No.</th><th>Name</th><th>Gender</th><th>Dept</th><th>Program</th><th>District</th>
          <th>%</th><th>Status</th><th>Eligible</th><th>Actions</th>
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
          <td><?= e($r['district']) ?></td>
          <td><?= e($r['percentage']) ?></td>
          <td><span class="badge badge-status bg-info text-dark"><?= e($r['status']) ?></span></td>
          <td>
            <?php if ($r['is_eligible'] === null): ?>
              <span class="badge bg-secondary">Not Checked</span>
            <?php elseif ($r['is_eligible']): ?>
              <span class="badge bg-success">Eligible</span>
            <?php else: ?>
              <span class="badge bg-danger">Not Eligible</span>
            <?php endif; ?>
          </td>
          <td class="text-nowrap">
            <a href="<?= BASE_URL ?>applications/view.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
            <a href="<?= BASE_URL ?>applications/edit.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
            <?php if (!in_array($r['status'], ['Cancelled','Withdrawn'], true)): ?>
            <form method="post" class="d-inline" onsubmit="return confirm('Cancel this application?');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="change_status">
              <input type="hidden" name="id" value="<?= $r['id'] ?>">
              <input type="hidden" name="new_status" value="Cancelled">
              <button class="btn btn-sm btn-outline-danger"><i class="bi bi-x-circle"></i></button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($rows)): ?>
        <tr><td colspan="10" class="text-center text-muted py-4">No applications found.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
    </div>

    <div class="d-flex justify-content-between align-items-center mt-3">
      <small class="text-muted">Showing <?= count($rows) ?> of <?= number_format($totalRows) ?> records</small>
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
<?php require __DIR__ . '/../includes/footer.php'; ?>
