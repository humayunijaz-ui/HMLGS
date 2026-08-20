<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/check_auth.php';

$pageTitle = 'Dashboard';
$activeSession = get_active_session($pdo);
$sessionId = $activeSession['id'] ?? null;

$stats = [
    'total' => 0, 'eligible' => 0, 'not_eligible' => 0,
    'male' => 0, 'female' => 0,
];

if ($sessionId) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM hostel_applications WHERE hostel_session_id = ?");
    $stmt->execute([$sessionId]);
    $stats['total'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM hostel_applications a JOIN eligibility_results er ON er.application_id=a.id WHERE a.hostel_session_id=? AND er.is_eligible=1");
    $stmt->execute([$sessionId]);
    $stats['eligible'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM hostel_applications a JOIN eligibility_results er ON er.application_id=a.id WHERE a.hostel_session_id=? AND er.is_eligible=0");
    $stmt->execute([$sessionId]);
    $stats['not_eligible'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT gender, COUNT(*) c FROM hostel_applications WHERE hostel_session_id=? GROUP BY gender");
    $stmt->execute([$sessionId]);
    foreach ($stmt->fetchAll() as $r) {
        if ($r['gender'] === 'Male') $stats['male'] = (int)$r['c'];
        if ($r['gender'] === 'Female') $stats['female'] = (int)$r['c'];
    }

    $seatStmt = $pdo->prepare("SELECT COALESCE(SUM(total_seats),0) total, COALESCE(SUM(allocated_seats),0) allocated, COALESCE(SUM(cancelled_seats),0) cancelled FROM hostel_seats WHERE hostel_session_id = ?");
    $seatStmt->execute([$sessionId]);
    $seats = $seatStmt->fetch();

    $statusStmt = $pdo->prepare("SELECT status, COUNT(*) c FROM hostel_applications WHERE hostel_session_id=? GROUP BY status");
    $statusStmt->execute([$sessionId]);
    $statusCounts = [];
    foreach ($statusStmt->fetchAll() as $r) { $statusCounts[$r['status']] = (int)$r['c']; }
} else {
    $seats = ['total' => 0, 'allocated' => 0, 'cancelled' => 0];
    $statusCounts = [];
}

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/navbar.php';
?>
<div class="container-fluid py-4">
  <h4 class="mb-1">Dashboard</h4>
  <p class="text-muted">
    Hostel Session:
    <strong><?= $activeSession ? e($activeSession['session_name']) : 'No active session configured' ?></strong>
    <?php if (!$activeSession): ?>
      &mdash; <a href="<?= BASE_URL ?>sessions/index.php">Set one up</a>
    <?php endif; ?>
  </p>

  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <div class="card stat-card p-3 border-start border-4 border-primary">
        <div class="text-muted small">Total Applications</div>
        <div class="value"><?= number_format($stats['total']) ?></div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card stat-card p-3 border-start border-4 border-success">
        <div class="text-muted small">Eligible</div>
        <div class="value"><?= number_format($stats['eligible']) ?></div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card stat-card p-3 border-start border-4 border-danger">
        <div class="text-muted small">Not Eligible</div>
        <div class="value"><?= number_format($stats['not_eligible']) ?></div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card stat-card p-3 border-start border-4 border-warning">
        <div class="text-muted small">Hostel Seats (Total)</div>
        <div class="value"><?= number_format($seats['total']) ?></div>
      </div>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-md-6">
      <div class="card p-3">
        <h6>Gender-wise Applications</h6>
        <div class="d-flex justify-content-between border-bottom py-2">
          <span><i class="bi bi-person"></i> Male</span><strong><?= number_format($stats['male']) ?></strong>
        </div>
        <div class="d-flex justify-content-between py-2">
          <span><i class="bi bi-person-dress"></i> Female</span><strong><?= number_format($stats['female']) ?></strong>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card p-3">
        <h6>Hostel Seat Utilization</h6>
        <div class="d-flex justify-content-between border-bottom py-2">
          <span>Allocated</span><strong><?= number_format($seats['allocated']) ?></strong>
        </div>
        <div class="d-flex justify-content-between border-bottom py-2">
          <span>Cancelled</span><strong><?= number_format($seats['cancelled']) ?></strong>
        </div>
        <div class="d-flex justify-content-between py-2">
          <span>Available</span><strong><?= number_format(max(0, $seats['total'] - $seats['allocated'])) ?></strong>
        </div>
      </div>
    </div>
  </div>

  <div class="card p-3">
    <h6>Application Status Breakdown</h6>
    <div class="row text-center g-2">
      <?php
      $allStatuses = ['Applied','Eligible','Not Eligible','General Merit','Selected','Waiting','Not Selected','Cancelled','Withdrawn'];
      foreach ($allStatuses as $st):
      ?>
      <div class="col">
        <div class="border rounded p-2">
          <div class="small text-muted"><?= e($st) ?></div>
          <div class="fw-bold"><?= number_format($statusCounts[$st] ?? 0) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
