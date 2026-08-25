<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
  <div class="container-fluid">
    <a class="navbar-brand" href="<?= BASE_URL ?>dashboard/index.php">
      <i class="bi bi-building"></i> HMLGS
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>dashboard/index.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>applications/index.php"><i class="bi bi-people"></i> Applications</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>imports/upload.php"><i class="bi bi-upload"></i> Import</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>eligibility/index.php"><i class="bi bi-check2-square"></i> Eligibility</a></li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"><i class="bi bi-list-ol"></i> Merit Lists</a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="<?= BASE_URL ?>merit/general.php">General Merit</a></li>
            <li><a class="dropdown-item" href="<?= BASE_URL ?>merit/gender_wise.php">Gender-wise Merit</a></li>
            <li><a class="dropdown-item" href="<?= BASE_URL ?>merit/department_wise.php">Department-wise Merit</a></li>
            <li><a class="dropdown-item" href="<?= BASE_URL ?>merit/program_wise.php">Program-wise Merit</a></li>
          </ul>
        </li>
        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>allocation/index.php"><i class="bi bi-house-check"></i> Allocation</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>hostels/index.php"><i class="bi bi-building-fill"></i> Hostels</a></li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"><i class="bi bi-gear"></i> Setup</a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="<?= BASE_URL ?>sessions/index.php">Hostel Sessions</a></li>
            <li><a class="dropdown-item" href="<?= BASE_URL ?>departments/index.php">Departments</a></li>
            <li><a class="dropdown-item" href="<?= BASE_URL ?>programs/index.php">Programs</a></li>
            <li><a class="dropdown-item" href="<?= BASE_URL ?>quotas/index.php">Admission Quotas</a></li>
            <li><a class="dropdown-item" href="<?= BASE_URL ?>quotas/matrix.php">Quota Seat Matrix</a></li>
          </ul>
        </li>
        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>reports/index.php"><i class="bi bi-file-earmark-bar-graph"></i> Reports</a></li>
      </ul>
      <ul class="navbar-nav">
        <li class="nav-item">
          <span class="nav-link text-light-50">
            <i class="bi bi-person-circle"></i> <?= e($_SESSION['full_name'] ?? 'Administrator') ?>
          </span>
        </li>
        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
      </ul>
    </div>
  </div>
</nav>
