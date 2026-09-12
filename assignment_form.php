<?php
require 'config.php';
requireAdmin();

$id = (int)($_GET['id'] ?? 0);
$assignment = null;

if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM assignments WHERE id=?');
    $stmt->execute([$id]);
    $assignment = $stmt->fetch();
    if (!$assignment) exit('Assignment not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $id = (int)($_POST['id'] ?? 0);
    $workerId = (int)($_POST['worker_id'] ?? 0);
    $category = $_POST['category'] ?? '';
    $start = $_POST['start_datetime'] ?? '';
    $end = $_POST['end_datetime'] ?? '';
    $notes = trim($_POST['notes'] ?? '');

    $allowed = ['toyride', 'children_car', 'juice_slush'];
    if (!$workerId || !in_array($category, $allowed, true) || !$start || !$end) {
        exit('Invalid assignment data.');
    }

    $startObj = new DateTime($start);
    $endObj = new DateTime($end);
    $seconds = $endObj->getTimestamp() - $startObj->getTimestamp();
    if ($seconds <= 0) exit('End date/time must be after start date/time.');

    $hours = round($seconds / 3600, 2);

    $stmt = $pdo->prepare('SELECT hourly_rate, full_name FROM workers WHERE id=?');
    $stmt->execute([$workerId]);
    $worker = $stmt->fetch();
    if (!$worker) exit('Worker not found.');

    $rate = (float)$worker['hourly_rate'];
    $gross = round($hours * $rate, 2);

    if ($id) {
        $stmt = $pdo->prepare(
            'UPDATE assignments
             SET worker_id=?, category=?, start_datetime=?, end_datetime=?, hourly_rate=?,
                 hours_worked=?, gross_earning=?, notes=?, updated_by=?
             WHERE id=?'
        );
        $stmt->execute([
            $workerId, $category, $startObj->format('Y-m-d H:i:s'), $endObj->format('Y-m-d H:i:s'),
            $rate, $hours, $gross, $notes ?: null, currentUser()['id'], $id
        ]);
        audit($pdo, 'UPDATE', 'assignment', $id, "Updated assignment for {$worker['full_name']}; gross £{$gross}");
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Assignment updated.'];
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO assignments
             (worker_id, category, start_datetime, end_datetime, hourly_rate, hours_worked, gross_earning, notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $workerId, $category, $startObj->format('Y-m-d H:i:s'), $endObj->format('Y-m-d H:i:s'),
            $rate, $hours, $gross, $notes ?: null, currentUser()['id']
        ]);
        $newId = (int)$pdo->lastInsertId();
        audit($pdo, 'CREATE', 'assignment', $newId, "Created assignment for {$worker['full_name']}; gross £{$gross}");
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Assignment added.'];
    }

    redirect('assignments.php');
}

$workers = $pdo->query('SELECT id, full_name, hourly_rate FROM workers WHERE is_active=1 ORDER BY full_name')->fetchAll();
$pageTitle = $assignment ? 'Edit Assignment' : 'Add Assignment';
require 'header.php';
?>
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header bg-white fw-bold"><?= e($pageTitle) ?></div>
            <div class="card-body">
                <form method="post" id="assignmentForm">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="id" value="<?= (int)($assignment['id'] ?? 0) ?>">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Worker *</label>
                            <select class="form-select" name="worker_id" id="workerSelect" required>
                                <option value="">Select worker</option>
                                <?php foreach ($workers as $w): ?>
                                    <option value="<?= (int)$w['id'] ?>" data-rate="<?= e((string)$w['hourly_rate']) ?>"
                                        <?= (int)($assignment['worker_id'] ?? 0) === (int)$w['id'] ? 'selected' : '' ?>>
                                        <?= e($w['full_name']) ?> (£<?= number_format((float)$w['hourly_rate'], 2) ?>/hr)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Category *</label>
                            <select class="form-select" name="category" required>
                                <option value="toyride" <?= ($assignment['category'] ?? '') === 'toyride' ? 'selected' : '' ?>>Toy Ride</option>
                                <option value="children_car" <?= ($assignment['category'] ?? '') === 'children_car' ? 'selected' : '' ?>>Children Car</option>
                                <option value="juice_slush" <?= ($assignment['category'] ?? '') === 'juice_slush' ? 'selected' : '' ?>>Juice / Slush Shop</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Start date and time *</label>
                            <input class="form-control" type="datetime-local" name="start_datetime" id="startDate" required
                                   value="<?= $assignment ? e(date('Y-m-d\TH:i', strtotime($assignment['start_datetime']))) : '' ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">End date and time *</label>
                            <input class="form-control" type="datetime-local" name="end_datetime" id="endDate" required
                                   value="<?= $assignment ? e(date('Y-m-d\TH:i', strtotime($assignment['end_datetime']))) : '' ?>">
                        </div>

                        <div class="col-12">
                            <div class="alert alert-info mb-0">
                                Estimated hours: <strong id="previewHours">0.00</strong> ·
                                Estimated earning: <strong>£<span id="previewEarning">0.00</span></strong>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="3"><?= e($assignment['notes'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button class="btn btn-primary">Save assignment</button>
                        <a class="btn btn-light" href="assignments.php">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require 'footer.php'; ?>