<?php
/**
 * Include this file at the top of every protected page.
 * Requires config/config.php to already be loaded (for session).
 */

if (empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}
