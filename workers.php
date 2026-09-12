<?php
require 'config.php';
requireLogin();

$pageTitle = 'Workers';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAdmin();
    verifyCsrf();

    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $rate = (float)($_POST['hourly_rate'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');

        if ($name === '' || $rate < 0) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Name and valid hourly rate are required.'];
            redirect('workers.php');
        }

        if ($id > 0) {
            $stmt = $pdo->prepare(
                'UPDATE workers SET full_name=?, phone=?, email=?, hourly_rate=?, notes=? WHERE id=?'
            );
            $stmt->execute([$name, $phone ?: null, $email ?: null, $rate, $notes ?: null, $id]);
            audit($pdo, 'UPDATE', 'worker', $id, "Updated worker: {$name}");
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Worker updated.'];
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO workers (full_name, phone, email, hourly_rate, notes, created_by)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$name, $phone ?: null, $email ?: null, $rate, $notes ?: null, currentUser()['id']]);
            $newId = (int)$pdo->lastInsertId();
            audit($pdo, 'CREATE', 'worker', $newId, "Created worker: {$name}");
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Worker added.'];
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT full_name FROM workers WHERE id=?');
        $stmt->execute([$id]);
        $name = $stmt->fetchColumn();

        $stmt = $pdo->prepare('DELETE FROM workers WHERE id=?');
        $stmt->execute([$id]);
        audit($pdo, 'DELETE', 'worker', $id, "Deleted worker: {$name}");
        $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Worker deleted.'];
    }

    redirect('workers.php');
}

$edit = null;
if (isset($_GET['edit']) && isAdmin()) {
    $stmt = $pdo->prepare('SELECT * FROM workers WHERE id=?');
    $stmt->execute([(int)$_GET['edit']]);
    $edit = $stmt->fetch();
}

$workers = $pdo->query(
    "SELECT w.*, u.display_name creator_name,
       COALESCE((SELECT SUM(a.gross_earning) FROM assignments a WHERE a.worker_id=w.id),0) gross,
       COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.worker_id=w.id),0) paid
     FROM workers w
     JOIN system_users u ON u.id=w.created_by
     ORDER BY w.full_name"
)->fetchAll();

require 'header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div><h1 class="h3 mb-1">Workers</h1><p class="text-muted mb-0">Manage staff and hourly rates</p></div>
</div>

<div class="row g-4">
    <?php if (isAdmin()): ?>
    <div class="col-lg-4">
        <div class="card sticky-lg-top" style="top: 1rem;">
            <div class="card-header bg-white fw-bold"><?= $edit ? 'Edit worker' : 'Add worker' ?></div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
                    <div class="mb-3">
                        <label class="form-label">Full name *</label>
                        <input class="form-control" name="full_name" required value="<?= e($edit['full_name'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input class="form-control" name="phone" value="<?= e($edit['phone'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input class="form-control" type="email" name="email" value="<?= e($edit['email'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Hourly rate (£) *</label>
                        <input class="form-control" type="number" min="0" step="0.01" name="hourly_rate" required value="<?= e((string)($edit['hourly_rate'] ?? '')) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="3"><?= e($edit['notes'] ?? '') ?></textarea>
                    </div>
                    <button class="btn btn-primary"><?= $edit ? 'Update worker' : 'Add worker' ?></button>
                    <?php if ($edit): ?><a class="btn btn-light" href="workers.php">Cancel</a><?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="<?= isAdmin() ? 'col-lg-8' : 'col-12' ?>">
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Name</th><th>Rate</th><th>Gross</th><th>Paid</th><th>Due</th><?php if (isAdmin()): ?><th>Actions</th><?php endif; ?></tr></thead>
                    <tbody>
                    <?php foreach ($workers as $w): ?>
                        <?php $due = (float)$w['gross'] - (float)$w['paid']; ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= e($w['full_name']) ?></div>
                                <small class="text-muted"><?= e($w['phone']) ?> <?= $w['email'] ? '· ' . e($w['email']) : '' ?></small>
                            </td>
                            <td>£<?= number_format((float)$w['hourly_rate'], 2) ?></td>
                            <td>£<?= number_format((float)$w['gross'], 2) ?></td>
                            <td>£<?= number_format((float)$w['paid'], 2) ?></td>
                            <td class="fw-bold">£<?= number_format($due, 2) ?></td>
                            <?php if (isAdmin()): ?>
                            <td class="text-nowrap">
                                <a class="btn btn-sm btn-outline-primary" href="?edit=<?= (int)$w['id'] ?>">Edit</a>
                                <form method="post" class="d-inline" onsubmit="return confirm('Delete this worker and all related records?')">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$w['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$workers): ?><tr><td colspan="6" class="text-center text-muted py-4">No workers found.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require 'footer.php'; ?>