<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/check_auth.php';

$pageTitle = 'Hostel Allocation';

$sessions = $pdo->query("SELECT * FROM hostel_sessions ORDER BY id DESC")->fetchAll();
$activeSession = get_active_session($pdo);

$selectedSessionId = isset($_GET['hostel_session_id']) && $_GET['hostel_session_id'] !== ''
    ? (int)$_GET['hostel_session_id']
    : ($activeSession['id'] ?? null);

$selectedGender = $_GET['gender'] ?? 'Male';
if (!in_array($selectedGender, ['Male', 'Female'], true)) {
    $selectedGender = 'Male';
}

// Hostels available for this gender
$hostelStmt = $pdo->prepare("SELECT * FROM hostels WHERE gender = ? AND status = 'Active' ORDER BY hostel_name");
$hostelStmt->execute([$selectedGender]);
$hostels = $hostelStmt->fetchAll();

$selectedHostelId = isset($_GET['hostel_id']) && $_GET['hostel_id'] !== ''
    ? (int)$_GET['hostel_id']
    : ($hostels[0]['id'] ?? null);

// ------------------------------------------------------------
// Actions: allocate one, waitlist one, cancel one, auto-allocate
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $postSessionId = (int)($_POST['hostel_session_id'] ?? 0);
    $postHostelId  = (int)($_POST['hostel_id'] ?? 0);

    if (!$postSessionId || !$postHostelId) {
        flash_set('error', 'Please select a session and a hostel first.');
        redirect('allocation/index.php');
    }

    // Lock/read current seat counters
    $seatStmt = $pdo->prepare("SELECT * FROM hostel_seats WHERE hostel_id = ? AND hostel_session_id = ?");
    $seatStmt->execute([$postHostelId, $postSessionId]);
    $seatRow = $seatStmt->fetch();
    if (!$seatRow) {
        flash_set('error', 'No seat record found for this hostel/session. Please configure hostel seats first.');
        redirect('allocation/index.php?hostel_session_id=' . $postSessionId . '&hostel_id=' . $postHostelId);
    }
    $available = (int)$seatRow['total_seats'] - ((int)$seatRow['allocated_seats'] - (int)$seatRow['cancelled_seats']);

    if ($action === 'allocate_one' || $action === 'waitlist_one') {
        $appId = (int)$_POST['application_id'];
        $stmt = $pdo->prepare("SELECT status FROM hostel_applications WHERE id = ?");
        $stmt->execute([$appId]);
        $oldStatus = $stmt->fetchColumn();

        if ($action === 'allocate_one') {
            if ($available <= 0) {
                flash_set('error', 'No available seats in this hostel. Consider waitlisting instead.');
                redirect('allocation/index.php?hostel_session_id=' . $postSessionId . '&gender=' . $selectedGender . '&hostel_id=' . $postHostelId);
            }
            $allocStatus = 'Selected';
            $newAppStatus = 'Selected';
        } else {
            $allocStatus = 'Waiting';
            $newAppStatus = 'Waiting';
        }

        $existing = $pdo->prepare("SELECT id, allocation_status FROM hostel_allocations WHERE application_id = ?");
        $existing->execute([$appId]);
        $existingRow = $existing->fetch();

        if ($existingRow) {
            $pdo->prepare("UPDATE hostel_allocations SET hostel_id = ?, hostel_session_id = ?, allocation_status = ?, allocated_by = ?, updated_at = NOW() WHERE id = ?")
                ->execute([$postHostelId, $postSessionId, $allocStatus, current_user_id(), $existingRow['id']]);
            // Adjust seat counters if it was previously Selected and now isn't (or vice versa)
            if ($existingRow['allocation_status'] === 'Selected' && $allocStatus !== 'Selected') {
                $pdo->prepare("UPDATE hostel_seats SET cancelled_seats = cancelled_seats + 1 WHERE hostel_id = ? AND hostel_session_id = ?")
                    ->execute([$postHostelId, $postSessionId]);
            } elseif ($existingRow['allocation_status'] !== 'Selected' && $allocStatus === 'Selected') {
                $pdo->prepare("UPDATE hostel_seats SET allocated_seats = allocated_seats + 1 WHERE hostel_id = ? AND hostel_session_id = ?")
                    ->execute([$postHostelId, $postSessionId]);
            }
        } else {
            $pdo->prepare("INSERT INTO hostel_allocations (application_id, hostel_id, hostel_session_id, allocation_status, allocated_by) VALUES (?,?,?,?,?)")
                ->execute([$appId, $postHostelId, $postSessionId, $allocStatus, current_user_id()]);
            if ($allocStatus === 'Selected') {
                $pdo->prepare("UPDATE hostel_seats SET allocated_seats = allocated_seats + 1 WHERE hostel_id = ? AND hostel_session_id = ?")
                    ->execute([$postHostelId, $postSessionId]);
            }
        }

        $pdo->prepare("UPDATE hostel_applications SET status = ? WHERE id = ?")->execute([$newAppStatus, $appId]);
        $pdo->prepare("INSERT INTO application_status_history (application_id, old_status, new_status, changed_by, remarks) VALUES (?,?,?,?,?)")
            ->execute([$appId, $oldStatus, $newAppStatus, current_user_id(), 'Hostel allocation: ' . $allocStatus]);
        audit_log($pdo, 'Hostel allocation', 'allocation', $appId, "{$allocStatus} - hostel #{$postHostelId}");
        flash_set('success', 'Application ' . strtolower($allocStatus) . '.');
    } elseif ($action === 'cancel_one') {
        $appId = (int)$_POST['application_id'];
        $existing = $pdo->prepare("SELECT id, allocation_status FROM hostel_allocations WHERE application_id = ?");
        $existing->execute([$appId]);
        $existingRow = $existing->fetch();

        if ($existingRow) {
            $wasSelected = $existingRow['allocation_status'] === 'Selected';
            $pdo->prepare("UPDATE hostel_allocations SET allocation_status = 'Cancelled', updated_at = NOW() WHERE id = ?")
                ->execute([$existingRow['id']]);
            if ($wasSelected) {
                $pdo->prepare("UPDATE hostel_seats SET cancelled_seats = cancelled_seats + 1 WHERE hostel_id = ? AND hostel_session_id = ?")
                    ->execute([$postHostelId, $postSessionId]);
            }
        }
        $stmt = $pdo->prepare("SELECT status FROM hostel_applications WHERE id = ?");
        $stmt->execute([$appId]);
        $oldStatus = $stmt->fetchColumn();
        $pdo->prepare("UPDATE hostel_applications SET status = 'Cancelled' WHERE id = ?")->execute([$appId]);
        $pdo->prepare("INSERT INTO application_status_history (application_id, old_status, new_status, changed_by, remarks) VALUES (?,?,?,?,?)")
            ->execute([$appId, $oldStatus, 'Cancelled', current_user_id(), 'Allocation cancelled']);
        audit_log($pdo, 'Cancel allocation', 'allocation', $appId);
        flash_set('success', 'Allocation cancelled and seat released.');
    } elseif ($action === 'auto_allocate') {
        // Rank-order eligible General-Merit applicants of this gender/session not yet allocated,
        // fill remaining seats, then waitlist the rest.
        $stmt = $pdo->prepare(
            "SELECT a.id FROM hostel_applications a
             LEFT JOIN hostel_allocations al ON al.application_id = a.id
             WHERE a.hostel_session_id = ? AND a.gender = ?
               AND a.status IN ('General Merit')
               AND al.id IS NULL
             ORDER BY a.percentage DESC, a.created_at ASC, a.id ASC"
        );
        $stmt->execute([$postSessionId, $selectedGender]);
        $candidates = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $selected = 0;
        $waitlisted = 0;
        $remaining = $available;

        $insAlloc = $pdo->prepare("INSERT INTO hostel_allocations (application_id, hostel_id, hostel_session_id, allocation_status, rank_no, allocated_by) VALUES (?,?,?,?,?,?)");
        $updApp = $pdo->prepare("UPDATE hostel_applications SET status = ? WHERE id = ?");
        $insHist = $pdo->prepare("INSERT INTO application_status_history (application_id, old_status, new_status, changed_by, remarks) VALUES (?, 'General Merit', ?, ?, ?)");

        $rank = 1;
        $pdo->beginTransaction();
        try {
            foreach ($candidates as $appId) {
                if ($remaining > 0) {
                    $status = 'Selected';
                    $remaining--;
                    $selected++;
                } else {
                    $status = 'Waiting';
                    $waitlisted++;
                }
                $insAlloc->execute([$appId, $postHostelId, $postSessionId, $status, $rank, current_user_id()]);
                $updApp->execute([$status, $appId]);
                $insHist->execute([$appId, $status, current_user_id(), 'Auto-allocation (rank ' . $rank . ')']);
                $rank++;
            }
            if ($selected > 0) {
                $pdo->prepare("UPDATE hostel_seats SET allocated_seats = allocated_seats + ? WHERE hostel_id = ? AND hostel_session_id = ?")
                    ->execute([$selected, $postHostelId, $postSessionId]);
            }
            audit_log($pdo, 'Auto-allocate', 'allocation', null, "{$selected} selected, {$waitlisted} waitlisted for hostel #{$postHostelId}");
            $pdo->commit();
            flash_set('success', "Auto-allocation complete: {$selected} selected, {$waitlisted} placed on waiting list.");
        } catch (Exception $ex) {
            $pdo->rollBack();
            flash_set('error', 'Auto-allocation failed: ' . $ex->getMessage());
        }
    }

    redirect('allocation/index.php?hostel_session_id=' . $postSessionId . '&gender=' . $selectedGender . '&hostel_id=' . $postHostelId);
}

// ------------------------------------------------------------
// Display data
// ------------------------------------------------------------
$seatInfo = null;
if ($selectedSessionId && $selectedHostelId) {
    $stmt = $pdo->prepare("SELECT * FROM hostel_seats WHERE hostel_id = ? AND hostel_session_id = ?");
    $stmt->execute([$selectedHostelId, $selectedSessionId]);
    $seatInfo = $stmt->fetch();
}

$rows = [];
if ($selectedSessionId) {
    $stmt = $pdo->prepare(
        "SELECT a.id, a.form_no, a.student_name, a.percentage, a.status,
                d.department_name, p.program_name,
                al.allocation_status, al.hostel_id, h.hostel_name
         FROM hostel_applications a
         JOIN departments d ON d.id = a.department_id
         JOIN programs p ON p.id = a.program_id
         LEFT JOIN hostel_allocations al ON al.application_id = a.id
         LEFT JOIN hostels h ON h.id = al.hostel_id
         WHERE a.hostel_session_id = ? AND a.gender = ?
           AND a.status IN ('General Merit','Selected','Waiting','Cancelled')
         ORDER BY a.percentage DESC, a.created_at ASC, a.id ASC"
    );
    $stmt->execute([$selectedSessionId, $selectedGender]);
    $rows = $stmt->fetchAll();
}

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/navbar.php';
?>
<div class="container-fluid py-4">
  <h4 class="mb-3">Hostel Allocation</h4>

  <?php if ($m = flash_get('success')): ?><div class="alert alert-success alert-dismissible fade show"><?= e($m) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
  <?php if ($m = flash_get('error')): ?><div class="alert alert-danger alert-dismissible fade show"><?= e($m) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

  <div class="card p-3 mb-4">
    <form method="get" class="row g-2 align-items-end">
      <div class="col-md-3">
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
      <div class="col-md-2">
        <label class="form-label mb-1">Gender</label>
        <select name="gender" class="form-select" onchange="this.form.submit()">
          <option value="Male" <?= $selectedGender === 'Male' ? 'selected' : '' ?>>Male</option>
          <option value="Female" <?= $selectedGender === 'Female' ? 'selected' : '' ?>>Female</option>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label mb-1">Hostel</label>
        <select name="hostel_id" class="form-select" onchange="this.form.submit()">
          <?php if (empty($hostels)): ?>
            <option value="">-- No <?= e($selectedGender) ?> hostels configured --</option>
          <?php endif; ?>
          <?php foreach ($hostels as $h): ?>
            <option value="<?= $h['id'] ?>" <?= (int)$selectedHostelId === (int)$h['id'] ? 'selected' : '' ?>>
              <?= e($h['hostel_name']) ?> (<?= e($h['hostel_code']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </form>
  </div>

  <?php if (empty($hostels)): ?>
    <div class="alert alert-warning">
      No active <?= strtolower($selectedGender) ?> hostels are configured yet. Please add a hostel first (Hostels &amp; Seat Management module).
    </div>
  <?php elseif (!$selectedSessionId): ?>
    <div class="alert alert-warning">Please select a hostel session.</div>
  <?php else: ?>

    <?php
      $totalSeats = $seatInfo['total_seats'] ?? 0;
      $activeAllocated = ($seatInfo['allocated_seats'] ?? 0) - ($seatInfo['cancelled_seats'] ?? 0);
      $availableSeats = $totalSeats - $activeAllocated;
    ?>
    <div class="row g-2 mb-4">
      <div class="col"><div class="card p-3 text-center"><div class="fs-4 fw-bold"><?= (int)$totalSeats ?></div><div class="text-muted small">Total Seats</div></div></div>
      <div class="col"><div class="card p-3 text-center"><div class="fs-4 fw-bold text-primary"><?= (int)$activeAllocated ?></div><div class="text-muted small">Allocated</div></div></div>
      <div class="col"><div class="card p-3 text-center"><div class="fs-4 fw-bold <?= $availableSeats > 0 ? 'text-success' : 'text-danger' ?>"><?= (int)$availableSeats ?></div><div class="text-muted small">Available</div></div></div>
    </div>

    <div class="card p-3 mb-4">
      <form method="post" onsubmit="return confirm('Auto-allocate ranked applicants to fill available seats, and waitlist the rest?');">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="auto_allocate">
        <input type="hidden" name="hostel_session_id" value="<?= $selectedSessionId ?>">
        <input type="hidden" name="hostel_id" value="<?= $selectedHostelId ?>">
        <button class="btn btn-primary" <?= !$selectedHostelId ? 'disabled' : '' ?>>
          <i class="bi bi-magic"></i> Auto-Allocate by Rank
        </button>
      </form>
      <span class="text-muted small">Fills available seats with the highest-ranked General Merit applicants not yet allocated; remaining eligible applicants are placed on the waiting list.</span>
    </div>

    <div class="card p-3">
      <h6>General Merit / Selection Status &mdash; <?= e($selectedGender) ?></h6>
      <div class="table-responsive">
        <table class="table table-sm table-bordered align-middle">
          <thead class="table-light">
            <tr>
              <th>Form No.</th><th>Name</th><th>Dept</th><th>Program</th>
              <th class="text-center">%</th><th>App Status</th><th>Hostel</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td><?= e($r['form_no']) ?></td>
                <td><?= e($r['student_name']) ?></td>
                <td><?= e($r['department_name']) ?></td>
                <td><?= e($r['program_name']) ?></td>
                <td class="text-center"><?= number_format($r['percentage'], 2) ?></td>
                <td>
                  <?php
                    $badgeClass = match($r['status']) {
                        'Selected' => 'bg-success',
                        'Waiting' => 'bg-warning text-dark',
                        'Cancelled' => 'bg-danger',
                        default => 'bg-secondary',
                    };
                  ?>
                  <span class="badge <?= $badgeClass ?>"><?= e($r['status']) ?></span>
                </td>
                <td><?= e($r['hostel_name'] ?? '-') ?></td>
                <td class="text-nowrap">
                  <?php if (!in_array($r['status'], ['Cancelled'], true)): ?>
                    <?php if ($r['status'] !== 'Selected'): ?>
                    <form method="post" class="d-inline">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="allocate_one">
                      <input type="hidden" name="application_id" value="<?= $r['id'] ?>">
                      <input type="hidden" name="hostel_session_id" value="<?= $selectedSessionId ?>">
                      <input type="hidden" name="hostel_id" value="<?= $selectedHostelId ?>">
                      <button class="btn btn-sm btn-outline-success" title="Allocate Seat"><i class="bi bi-house-check"></i></button>
                    </form>
                    <?php endif; ?>
                    <?php if ($r['status'] !== 'Waiting'): ?>
                    <form method="post" class="d-inline">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="waitlist_one">
                      <input type="hidden" name="application_id" value="<?= $r['id'] ?>">
                      <input type="hidden" name="hostel_session_id" value="<?= $selectedSessionId ?>">
                      <input type="hidden" name="hostel_id" value="<?= $selectedHostelId ?>">
                      <button class="btn btn-sm btn-outline-warning" title="Move to Waiting List"><i class="bi bi-hourglass-split"></i></button>
                    </form>
                    <?php endif; ?>
                    <form method="post" class="d-inline" onsubmit="return confirm('Cancel this allocation and release the seat?');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="cancel_one">
                      <input type="hidden" name="application_id" value="<?= $r['id'] ?>">
                      <input type="hidden" name="hostel_session_id" value="<?= $selectedSessionId ?>">
                      <input type="hidden" name="hostel_id" value="<?= $selectedHostelId ?>">
                      <button class="btn btn-sm btn-outline-danger" title="Cancel"><i class="bi bi-x-circle"></i></button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($rows)): ?>
              <tr><td colspan="8" class="text-center text-muted py-4">No General Merit applicants found for this gender/session yet. Generate the merit list first.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
