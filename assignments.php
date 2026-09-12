<?php
require 'config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAdmin();
    verifyCsrf();

    if (($_POST['action'] ?? '') === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare(
            'SELECT a.*, w.full_name FROM assignments a JOIN workers w ON w.id=a.worker_id WHERE a.id=?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        $stmt = $pdo->prepare('DELETE FROM assignments WHERE id=?');
        $stmt->execute([$id]);

        audit($pdo, 'DELETE', 'assignment', $id, 'Deleted assignment for ' . ($row['full_name'] ?? 'unknown worker'));
        $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Assignment deleted.'];
        redirect('assignments.php');
    }
}

$category = $_GET['category'] ?? '';
$workerId = (int)($_GET['worker_id'] ?? 0);
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';

$where = [];
$params = [];

if (in_array($category, ['toyride','children_car','juice_slush'], true)) {
    $where[] = 'a.category=?';
    $params[] = $category;
}
if ($workerId) {
    $where[] = 'a.worker_id=?';
    $params[] = $workerId;
}
if ($from) {
    $where[] = 'DATE(a.start_datetime) >= ?';
    $params[] = $from;
}
if ($to) {
    $where[] = 'DATE(a.start_datetime) <= ?';
    $params[] = $to;
}

$sql = "SELECT a.*, w.full_name, cu.display_name created_by_name, uu.display_name updated_by_name
        FROM assignments a
        JOIN workers w ON w.id=a.worker_id
        JOIN system_users cu ON cu.id=a.created_by
        LEFT JOIN system_users uu ON uu.id=a.updated_by";
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY a.start_datetime DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$assignments = $stmt->fetchAll();
$workers = $pdo->query('SELECT id, full_name FROM workers ORDER BY full_name')->fetchAll();

$labels = ['toyride'=>'Toy Ride','children_car'=>'Children Car','juice_slush'=>'Juice / Slush Shop'];
$pageTitle = 'Assignments';
require 'header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div><h1 class="h3 mb-1">Assignments</h1><p class="text-muted mb-0">View work by category, worker and date</p></div>
    <?php if (isAdmin()): ?><a class="btn btn-primary" href="assignment_form.php">Add assignment</a><?php endif; ?>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Category</label>
                <select class="form-select" name="category">
                    <option value="">All categories</option>
                    <?php foreach ($labels as $key=>$label): ?>
                        <option value="<?= e($key) ?>" <?= $category===$key?'selected':'' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
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
            <div class="col-md-2 d-flex align-items-end gap-2"><button class="btn btn-primary">Filter</button><a class="btn btn-light" href="assignments.php">Reset</a></div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Worker</th><th>Category</th><th>Start</th><th>End</th><th>Hours</th><th>Rate</th><th>Earning</th><?php if (isAdmin()): ?><th>Actions</th><?php endif; ?></tr></thead>
            <tbody>
            <?php foreach ($assignments as $a): ?>
                <tr>
                    <td><?= e($a['full_name']) ?></td>
                    <td><span class="badge text-bg-secondary"><?= e($labels[$a['category']] ?? $a['category']) ?></span></td>
                    <td><?= e(date('d M Y H:i', strtotime($a['start_datetime']))) ?></td>
                    <td><?= e(date('d M Y H:i', strtotime($a['end_datetime']))) ?></td>
                    <td><?= number_format((float)$a['hours_worked'], 2) ?></td>
                    <td>£<?= number_format((float)$a['hourly_rate'], 2) ?></td>
                    <td class="fw-bold">£<?= number_format((float)$a['gross_earning'], 2) ?></td>
                    <?php if (isAdmin()): ?>
                    <td class="text-nowrap">
                        <a class="btn btn-sm btn-outline-primary" href="assignment_form.php?id=<?= (int)$a['id'] ?>">Edit</a>
                        <form method="post" class="d-inline" onsubmit="return confirm('Delete this assignment?')">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger">Delete</button>
                        </form>
                    </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            <?php if (!$assignments): ?><tr><td colspan="8" class="text-center text-muted py-4">No assignments found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require 'footer.php'; ?>