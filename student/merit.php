<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/student_check_auth.php';
require_once __DIR__ . '/../includes/merit_helper.php';

$pageTitle = 'My Merit Status';
$cnic = $_SESSION['student_cnic'];

// Most relevant application: prefer the one tied to the active hostel session,
// otherwise the most recently submitted one.
$activeSession = get_active_session($pdo);
$stmt = $pdo->prepare(
    "SELECT a.*, d.department_name, p.program_name
     FROM hostel_applications a
     JOIN departments d ON d.id = a.department_id
     JOIN programs p ON p.id = a.program_id
     WHERE a.cnic_b_form = ?
     ORDER BY (a.hostel_session_id = ?) DESC, a.created_at DESC
     LIMIT 1"
);
$stmt->execute([$cnic, $activeSession['id'] ?? 0]);
$application = $stmt->fetch();

$quota = null;
if ($application && !empty($application['admission_quota_id'])) {
    try {
        $qStmt = $pdo->prepare("SELECT * FROM admission_quotas WHERE id = ?");
        $qStmt->execute([$application['admission_quota_id']]);
        $quota = $qStmt->fetch();
    } catch (PDOException $e) {
        $quota = null;
    }
}

// Latest Gender-wise merit list generated for this student's session + gender
$meritList = null;
$genderRank = null;
$quotaRank = null;
$quotaTotal = 0;
$quotaSeats = null;

if ($application) {
    $mlStmt = $pdo->prepare(
        "SELECT id, generated_at FROM merit_lists
         WHERE hostel_session_id = ? AND list_type = 'Gender' AND gender = ?
         ORDER BY generated_at DESC LIMIT 1"
    );
    $mlStmt->execute([$application['hostel_session_id'], $application['gender']]);
    $meritList = $mlStmt->fetch();

    if ($meritList) {
        $entries = get_merit_list_entries($pdo, $meritList['id']);

        // Overall gender rank
        foreach ($entries as $e) {
            if ($e['id'] == $application['id']) {
                $genderRank = $e['rank_no'];
                break;
            }
        }

        // Quota-wise rank: filter to same quota, preserve relative order (already percentage-sorted)
        if (!empty($application['admission_quota_id'])) {
            $quotaEntries = array_values(array_filter($entries, function ($e) use ($application) {
                return ($e['admission_quota_id'] ?? null) == $application['admission_quota_id'];
            }));
            $quotaTotal = count($quotaEntries);
            foreach ($quotaEntries as $i => $e) {
                if ($e['id'] == $application['id']) {
                    $quotaRank = $i + 1;
                    break;
                }
            }

            // Configured quota seat limit, if the matrix has been set up
            try {
                $seatStmt = $pdo->prepare(
                    "SELECT total_seats FROM quota_seat_matrix
                     WHERE hostel_session_id = ? AND department_id = ? AND program_id = ? AND gender = ? AND admission_quota_id = ?"
                );
                $seatStmt->execute([
                    $application['hostel_session_id'], $application['department_id'],
                    $application['program_id'], $application['gender'], $application['admission_quota_id'],
                ]);
                $quotaSeats = $seatStmt->fetchColumn();
                $quotaSeats = $quotaSeats !== false ? (int)$quotaSeats : null;
            } catch (PDOException $e) {
                $quotaSeats = null;
            }
        }
    }
}

require __DIR__ . '/../includes/header.php';
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
  <div class="container-fluid">
    <span class="navbar-brand"><i class="bi bi-mortarboard"></i> Student Portal</span>
    <div class="ms-auto">
      <span class="navbar-text text-light-50 me-3"><i class="bi bi-person-circle"></i> <?= e($application['student_name'] ?? 'Student') ?></span>
      <a href="<?= BASE_URL ?>auth/student_logout.php" class="btn btn-outline-light btn-sm"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </div>
  </div>
</nav>

<div class="container py-4">
  <h4 class="mb-3">My Hostel Merit Status</h4>

  <?php if (!$application): ?>
    <div class="alert alert-warning">No application found for your CNIC / B-Form number.</div>
  <?php else: ?>

    <div class="card p-3 mb-4">
      <h6>Application Details</h6>
      <div class="row">
        <div class="col-md-4"><strong>Form No.:</strong> <?= e($application['form_no']) ?></div>
        <div class="col-md-4"><strong>Name:</strong> <?= e($application['student_name']) ?></div>
        <div class="col-md-4"><strong>Gender:</strong> <?= e($application['gender']) ?></div>
        <div class="col-md-4"><strong>Department:</strong> <?= e($application['department_name']) ?></div>
        <div class="col-md-4"><strong>Program:</strong> <?= e($application['program_name']) ?></div>
        <div class="col-md-4"><strong>Admission Quota:</strong> <?= e($quota['quota_name'] ?? 'Not assigned') ?></div>
        <div class="col-md-4"><strong>Percentage:</strong> <?= number_format($application['percentage'], 2) ?>%</div>
        <div class="col-md-4">
          <strong>Application Status:</strong>
          <?php
            $badgeClass = match($application['status']) {
                'Selected' => 'bg-success',
                'Waiting' => 'bg-warning text-dark',
                'Not Eligible', 'Cancelled', 'Not Selected' => 'bg-danger',
                'General Merit' => 'bg-info text-dark',
                default => 'bg-secondary',
            };
          ?>
          <span class="badge <?= $badgeClass ?>"><?= e($application['status']) ?></span>
        </div>
      </div>
    </div>

    <?php if (!$meritList): ?>
      <div class="alert alert-info">The general merit list for your session/gender hasn't been generated yet. Please check back later.</div>
    <?php else: ?>
      <div class="card p-3 mb-4">
        <h6>Merit List Standing <small class="text-muted">(as of <?= format_datetime($meritList['generated_at']) ?>)</small></h6>
        <div class="row text-center g-2">
          <div class="col">
            <div class="p-3 border rounded">
              <div class="fs-4 fw-bold"><?= $genderRank ?? '-' ?></div>
              <div class="text-muted small">Overall Rank (<?= e($application['gender']) ?>)</div>
            </div>
          </div>
          <?php if ($quota): ?>
          <div class="col">
            <div class="p-3 border rounded">
              <div class="fs-4 fw-bold"><?= $quotaRank ?? '-' ?> <span class="fs-6 text-muted">/ <?= $quotaTotal ?></span></div>
              <div class="text-muted small">Rank within <?= e($quota['quota_name']) ?> Quota</div>
            </div>
          </div>
          <div class="col">
            <div class="p-3 border rounded">
              <div class="fs-4 fw-bold"><?= $quotaSeats !== null ? $quotaSeats : 'N/A' ?></div>
              <div class="text-muted small">Seats in Your Quota</div>
            </div>
          </div>
          <?php endif; ?>
        </div>

        <?php if ($quota && $quotaSeats !== null && $quotaRank !== null): ?>
          <div class="mt-3">
            <?php if ($quotaRank <= $quotaSeats): ?>
              <div class="alert alert-success mb-0"><i class="bi bi-check-circle"></i> You are currently within your quota's seat allocation (rank <?= $quotaRank ?> of <?= $quotaSeats ?> seats).</div>
            <?php else: ?>
              <div class="alert alert-warning mb-0"><i class="bi bi-hourglass-split"></i> You are currently on the waiting list for your quota (rank <?= $quotaRank ?>, seats available: <?= $quotaSeats ?>).</div>
            <?php endif; ?>
          </div>
        <?php elseif ($quota && $quotaSeats === null): ?>
          <div class="alert alert-secondary mt-3 mb-0">Seat quota for your category hasn't been configured yet by the administration.</div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

  <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
