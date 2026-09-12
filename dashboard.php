<?php
require 'config.php';
requireLogin();

$pageTitle = 'Dashboard';

$totalWorkers = (int)$pdo->query('SELECT COUNT(*) FROM workers WHERE is_active = 1')->fetchColumn();
$totalAssignments = (int)$pdo->query('SELECT COUNT(*) FROM assignments')->fetchColumn();
$totalGross = (float)$pdo->query('SELECT COALESCE(SUM(gross_earning),0) FROM assignments')->fetchColumn();
$totalPaid = (float)$pdo->query('SELECT COALESCE(SUM(amount),0) FROM payments')->fetchColumn();
$outstanding = $totalGross - $totalPaid;

$categoryTotals = $pdo->query(
    "SELECT category, COUNT(*) assignment_count, COALESCE(SUM(gross_earning),0) total
     FROM assignments GROUP BY category ORDER BY category"
)->fetchAll();

$recent = $pdo->query(
    "SELECT a.*, w.full_name
     FROM assignments a
     JOIN workers w ON w.id = a.worker_id
     ORDER BY a.start_datetime DESC LIMIT 8"
)->fetchAll();

require 'header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Dashboard</h1>
        <p class="text-muted mb-0">Summer fair staffing and payment overview</p>
    </div>
    <?php if (isAdmin()): ?>
        <a class="btn btn-primary" href="assignment_form.php">Add assignment</a>
    <?php endif; ?>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6 col-xl">
        <div class="card stat-card"><div class="card-body"><div class="small text-muted">Active workers</div><div class="display-6 fw-bold"><?= $totalWorkers ?></div></div></div>
    </div>
    <div class="col-md-6 col-xl">
        <div class="card stat-card"><div class="card-body"><div class="small text-muted">Assignments</div><div class="display-6 fw-bold"><?= $totalAssignments ?></div></div></div>
    </div>
    <div class="col-md-6 col-xl">
        <div class="card stat-card"><div class="card-body"><div class="small text-muted">Gross earnings</div><div class="display-6 fw-bold">£<?= number_format($totalGross, 2) ?></div></div></div>
    </div>
    <div class="col-md-6 col-xl">
        <div class="card stat-card"><div class="card-body"><div class="small text-muted">Paid</div><div class="display-6 fw-bold">£<?= number_format($totalPaid, 2) ?></div></div></div>
    </div>
    <div class="col-md-6 col-xl">
        <div class="card stat-card"><div class="card-body"><div class="small text-muted">Outstanding</div><div class="display-6 fw-bold">£<?= number_format($outstanding, 2) ?></div></div></div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header bg-white fw-bold">Category totals</div>
            <div class="card-body">
                <?php
                $labels = [
                    'toyride' => 'Toy Ride',
                    'children_car' => 'Children Car',
                    'juice_slush' => 'Juice / Slush Shop'
                ];
                ?>
                <?php foreach ($categoryTotals as $row): ?>
                    <div class="d-flex justify-content-between border-bottom py-3">
                        <div>
                            <div class="fw-semibold"><?= e($labels[$row['category']] ?? $row['category']) ?></div>
                            <small class="text-muted"><?= (int)$row['assignment_count'] ?> assignments</small>
                        </div>
                        <div class="fw-bold">£<?= number_format((float)$row['total'], 2) ?></div>
                    </div>
                <?php endforeach; ?>
                <?php if (!$categoryTotals): ?>
                    <p class="text-muted mb-0">No assignment data yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header bg-white fw-bold">Recent assignments</div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Worker</th><th>Category</th><th>Start</th><th>Hours</th><th>Earning</th></tr></thead>
                    <tbody>
                    <?php foreach ($recent as $row): ?>
                        <tr>
                            <td><?= e($row['full_name']) ?></td>
                            <td><?= e($labels[$row['category']] ?? $row['category']) ?></td>
                            <td><?= e(date('d M Y H:i', strtotime($row['start_datetime']))) ?></td>
                            <td><?= number_format((float)$row['hours_worked'], 2) ?></td>
                            <td>£<?= number_format((float)$row['gross_earning'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$recent): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No assignments yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require 'footer.php'; ?>