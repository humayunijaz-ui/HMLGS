<?php
require_once __DIR__ . '/config/config.php';

if (!empty($_SESSION['user_id'])) {
    redirect('dashboard/index.php');
} else {
    redirect('login.php');
}
