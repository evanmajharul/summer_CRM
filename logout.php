<?php
require 'config.php';
if (isLoggedIn()) {
    audit($pdo, 'LOGOUT', 'system_user', (int)currentUser()['id'], 'User logged out');
}
$_SESSION = [];
session_destroy();
redirect('login.php');
?>