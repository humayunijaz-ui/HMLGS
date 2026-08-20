<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/check_auth.php';

$pageTitle = 'Programs';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim($_POST['program_name'] ?? '');
        $code = strtoupper(trim($_POST['program_code'] ?? ''));
        $deptId = (int)($_POST['department_id'] ?? 0);
        $degree = trim($_POST['degree'] ?? '');

        if ($name === '' || $code === '' || !$deptId) {
            flash_set('error', 'Program name, code, and department are required.');
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO programs (program_name, program_code, department_id, degree) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $code, $deptId, $degree]);
                audit_log($pdo, 'Create program', 'programs', $pdo->lastInsertId(), $name);
                flash_set('success', 'Program added.');
            } catch (PDOException $e) {
                flash_set('error', 'Program code must be unique.');
            }
        }
    } elseif ($action === 'toggle_status') {
        $id = (int)$_POST['id'];
        $pdo->prepare("UPDATE programs SET status = IF(status='Active','Inactive','Active') WHERE id = ?")->execute([$id]);
        audit_log($pdo, 'Toggle program status', 'programs', $id);
        flash_set('success', 'Program status updated.');
    }
    redirect('programs/index.php' . (!empty($_POST['department_id']) ? '?department_id=' . (int)$_POST['department_id'] : ''));
}

$filterDept = isset($_GET['department_id']) ? (int)$_GET['department_id'] : null;
$departments = get_all_departments($pdo, false);

$sql = "SELECT p.*, d.department_name FROM programs p JOIN departments d ON d.id = p.department_id";
$params = [];
if ($filterDept) {
    $sql .= " WHERE p.department_id = ?";
    $params[] = $filterDept;
}
$sql .= " ORDER BY d.department_name, p.program_name";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/navbar.php';
?>
<div class="container-fluid py-4">
  <h4 class="mb-3">Programs</h4>
  <?php if ($m = flash_get('success')): ?><div class="alert alert-success alert-dismissible fade show"><?= e($m) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
  <?php if ($m = flash_get('error')): ?><div class="alert alert-danger alert-dismissible fade show"><?= e($m) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

  <div class="card p-3 mb-4">
    <h6>Add Program</h6>
    <form method="post" class="row g-2">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create">
      <div class="col-md-3"><input type="text" name="program_name" class="form-control" placeholder="Program Name" required></div>
      <div class="col-md-2"><input type="text" name="program_code" class="form-control" placeholder="Code e.g. BSCS" required></div>
      <div class="col-md-3">
        <select name="department_id" class="form-select" required>
          <option value="">-- Department --</option>
          <?php foreach ($departments as $d): ?>
            <option value="<?= $d['id'] ?>" <?= $filterDept == $d['id'] ? 'selected' : '' ?>><?= e($d['department_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2"><input type="text" name="degree" class="form-control" placeholder="Degree e.g. BS"></div>
      <div class="col-md-2"><button class="btn btn-primary w-100"><i class="bi bi-plus-lg"></i> Add</button></div>
    </form>
  </div>

  <div class="card p-3 mb-3">
    <form method="get" class="row g-2">
      <div class="col-md-4">
        <select name="department_id" class="form-select" onchange="this.form.submit()">
          <option value="">-- All Departments --</option>
          <?php foreach ($departments as $d): ?>
            <option value="<?= $d['id'] ?>" <?= $filterDept == $d['id'] ? 'selected' : '' ?>><?= e($d['department_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </form>
  </div>

  <div class="card p-3">
    <table class="table table-hover align-middle">
      <thead><tr><th>#</th><th>Program</th><th>Code</th><th>Department</th><th>Degree</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $p): ?>
        <tr>
          <td><?= $p['id'] ?></td>
          <td><?= e($p['program_name']) ?></td>
          <td><?= e($p['program_code']) ?></td>
          <td><?= e($p['department_name']) ?></td>
          <td><?= e($p['degree']) ?></td>
          <td><span class="badge <?= $p['status']==='Active' ? 'bg-primary' : 'bg-secondary' ?>"><?= e($p['status']) ?></span></td>
          <td>
            <form method="post" class="d-inline">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="toggle_status">
              <input type="hidden" name="id" value="<?= $p['id'] ?>">
              <input type="hidden" name="department_id" value="<?= e($filterDept) ?>">
              <button class="btn btn-sm btn-outline-secondary"><?= $p['status']==='Active' ? 'Deactivate' : 'Activate' ?></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
