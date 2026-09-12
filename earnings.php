<?php
require 'config.php';
requireLogin();

$period = $_GET['period'] ?? 'daily';
$workerId = (int)($_GET['worker_id'] ?? 0);
$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');

$groupExpr = $period === 'weekly'
    ? "DATE_FORMAT(a.start_datetime, '%x-W%v')"
    : "DATE(a.start_datetime)";

$where = ['DATE(a.start_datetime) BETWEEN ? AND ?'];
$params = [$from, $to];

if ($workerId) {
    $where[] = 'a.worker_id=?';
    $params[] = $workerId;
}

$sql = "SELECT {$groupExpr} period_label, a.worker_id, w.full_name,
               SUM(a.hours_worked) total_hours, SUM(a.gross_earning) gross_earning
        FROM assignments a
        JOIN workers w ON w.id=a.worker_id
        WHERE " . implode(' AND ', $where) . "
        GROUP BY period_label, a.worker_id, w.full_name
        ORDER BY period_label DESC, w.full_name";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$workers = $pdo->query('SELECT id, full_name FROM workers ORDER BY full_name')->fetchAll();

$summaryStmt = $pdo->prepare(
    "SELECT w.id, w.full_name,
       COALESCE((SELECT SUM(a.gross_earning) FROM assignments a WHERE a.worker_id=w.id AND DATE(a.start_datetime) BETWEEN ? AND ?),0) gross,
       COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.worker_id=w.id AND p.payment_date <= ?),0) paid
     FROM workers w
     ORDER BY w.full_name"
);
$summaryStmt->execute([$from, $to, $to]);
$summaries = $summaryStmt->fetchAll();

$pageTitle = 'Earnings';
require 'header.php';
?>
<div class="mb-4"><h1 class="h3 mb-1">Earnings</h1><p class="text-muted mb-0">Daily or weekly earnings by worker</p></div>

<div class="card mb-4">
    <div class="card-body">
        <form class="row g-3">
            <div class="col-md-2">
                <label class="form-label">View</label>
                <select class="form-select" name="period">
                    <option value="daily" <?= $period==='daily'?'selected':'' ?>>Daily</option>
                    <option value="weekly" <?= $period==='weekly'?'selected':'' ?>>Weekly</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Worker</label>
                <select class="form-select" name="worker_id">
                    <option value="">All workers</option>
                    <?php foreach ($workers as $w): ?>
                        <option value="<?= (int)$w['id'] ?>" <?= $workerId===(int)$w['id']?'selected':'' ?>><?= e($w['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2"><label class="form-label">From</label><input class="form-control" type="date" name="from" value="<?= e($from) ?>"></div>
            <div class="col-md-2"><label class="form-label">To</label><input class="form-control" type="date" name="to" value="<?= e($to) ?>"></div>
            <div class="col-md-3 d-flex align-items-end gap-2"><button class="btn btn-primary">Apply</button><a class="btn btn-light" href="earnings.php">Reset</a></div>
        </form>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header bg-white fw-bold"><?= $period === 'weekly' ? 'Weekly' : 'Daily' ?> breakdown</div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Period</th><th>Worker</th><th>Hours</th><th>Earning</th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><?= e($r['period_label']) ?></td>
                            <td><?= e($r['full_name']) ?></td>
                            <td><?= number_format((float)$r['total_hours'], 2) ?></td>
                            <td class="fw-bold">£<?= number_format((float)$r['gross_earning'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$rows): ?><tr><td colspan="4" class="text-center text-muted py-4">No earnings in this period.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header bg-white fw-bold">Worker balances</div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Worker</th><th>Gross</th><th>Paid</th><th>Due</th></tr></thead>
                    <tbody>
                    <?php foreach ($summaries as $s): ?>
                        <tr>
                            <td><?= e($s['full_name']) ?></td>
                            <td>£<?= number_format((float)$s['gross'], 2) ?></td>
                            <td>£<?= number_format((float)$s['paid'], 2) ?></td>
                            <td class="fw-bold">£<?= number_format((float)$s['gross'] - (float)$s['paid'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require 'footer.php'; ?>