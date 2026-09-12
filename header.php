<?php
requireLogin();
$user = currentUser();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? 'Summer Fair CRM') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="dashboard.php">Summer Fair CRM</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="nav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="workers.php">Workers</a></li>
                <li class="nav-item"><a class="nav-link" href="assignments.php">Assignments</a></li>
                <li class="nav-item"><a class="nav-link" href="earnings.php">Earnings</a></li>
                <li class="nav-item"><a class="nav-link" href="payments.php">Payments</a></li>
                <?php if (isPrimaryAdmin()): ?>
                    <li class="nav-item"><a class="nav-link" href="audit.php">Audit Log</a></li>
                <?php endif; ?>
            </ul>
            <span class="navbar-text me-3">
                <?= e($user['display_name']) ?> · <?= e(str_replace('_', ' ', $user['role'])) ?>
            </span>
            <a class="btn btn-outline-light btn-sm" href="logout.php">Logout</a>
        </div>
    </div>
</nav>
<main class="container-fluid py-4">
<?php if (!empty($_SESSION['flash'])): ?>
    <div class="alert alert-<?= e($_SESSION['flash']['type']) ?> alert-dismissible fade show">
        <?= e($_SESSION['flash']['message']) ?>
        <button class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['flash']); ?>
<?php endif; ?>
