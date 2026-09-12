<?php
require 'config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAdmin();
    verifyCsrf();

    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $workerId = (int)($_POST['worker_id'] ?? 0);
        $amount = (float)($_POST['amount'] ?? 0);
        $date = $_POST['payment_date'] ?? date('Y-m-d');
        $method = trim($_POST['payment_method'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        $balanceStmt = $pdo->prepare(
            "SELECT
               COALESCE((SELECT SUM(gross_earning) FROM assignments WHERE worker_id=?),0) -
               COALESCE((SELECT SUM(amount) FROM payments WHERE worker_id=?),0) AS balance"
        );
        $balanceStmt->execute([$workerId, $workerId]);
        $balance = (float)$balanceStmt->fetchColumn();

        if (!$workerId || $amount <= 0 || $amount > $balance) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Payment must be greater than zero and cannot exceed the outstanding balance.'];
            redirect('payments.php');
        }

        $stmt = $pdo->prepare(
            'INSERT INTO payments (worker_id, amount, payment_date, payment_method, notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$workerId, $amount, $date, $method ?: null, $notes ?: null, currentUser()['id']]);
        $id = (int)$pdo->lastInsertId();

        audit($pdo, 'CREATE', 'payment', $id, "Recorded payment £{$amount} for worker ID {$workerId}");
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Payment recorded and deducted from the balance.'];
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM payments WHERE id=?');
        $stmt->execute([$id]);
        $payment = $stmt->fetch();

        $stmt = $pdo->prepare('DELETE FROM payments WHERE id=?');
        $stmt->execute([$id]);

        audit($pdo, 'DELETE', 'payment', $id, 'Deleted payment of £' . ($payment['amount'] ?? '0'));
        $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Payment deleted.'];
    }

    redirect('payments.php');
}

$workers = $pdo->query(
    "SELECT w.id, w.full_name,
       COALESCE((SELECT SUM(a.gross_earning) FROM assignments a WHERE a.worker_id=w.id),0) gross,
       COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.worker_id=w.id),0) paid
     FROM workers w
     ORDER BY w.full_name"
)->fetchAll();

$payments = $pdo->query(
    "SELECT p.*, w.full_name, u.display_name created_by_name
     FROM payments p
     JOIN workers w ON w.id=p.worker_id
     JOIN system_users u ON u.id=p.created_by
     ORDER BY p.payment_date DESC, p.id DESC"
)->fetchAll();

$pageTitle = 'Payments';
require 'header.php';
?>
<div class="mb-4"><h1 class="h3 mb-1">Payments</h1><p class="text-muted mb-0">Record partial or full payments</p></div>

<div class="row g-4">
    <?php if (isAdmin()): ?>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-white fw-bold">Record payment</div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label class="form-label">Worker *</label>
                        <select class="form-select" name="worker_id" required>
                            <option value="">Select worker</option>
                            <?php foreach ($workers as $w): $due=(float)$w['gross']-(float)$w['paid']; ?>
                                <option value="<?= (int)$w['id'] ?>">
                                    <?= e($w['full_name']) ?> — Due £<?= number_format($due, 2) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3"><label class="form-label">Amount (£) *</label><input class="form-control" type="number" min="0.01" step="0.01" name="amount" required></div>
                    <div class="mb-3"><label class="form-label">Payment date *</label><input class="form-control" type="date" name="payment_date" value="<?= date('Y-m-d') ?>" required></div>
                    <div class="mb-3"><label class="form-label">Method</label><select class="form-select" name="payment_method"><option>Cash</option><option>Bank transfer</option><option>Card</option><option>Other</option></select></div>
                    <div class="mb-3"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="3"></textarea></div>
                    <button class="btn btn-primary">Save payment</button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="<?= isAdmin() ? 'col-lg-8' : 'col-12' ?>">
        <div class="card">
            <div class="card-header bg-white fw-bold">Payment history</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Date</th><th>Worker</th><th>Amount</th><th>Method</th><?php if (isAdmin()): ?><th>Action</th><?php endif; ?></tr></thead>
                    <tbody>
                    <?php foreach ($payments as $p): ?>
                        <tr>
                            <td><?= e(date('d M Y', strtotime($p['payment_date']))) ?></td>
                            <td><?= e($p['full_name']) ?></td>
                            <td class="fw-bold">£<?= number_format((float)$p['amount'], 2) ?></td>
                            <td><?= e($p['payment_method']) ?></td>
                            <?php if (isAdmin()): ?>
                            <td>
                                <form method="post" onsubmit="return confirm('Delete this payment? The amount will return to the outstanding balance.')">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$payments): ?><tr><td colspan="5" class="text-center text-muted py-4">No payments recorded.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require 'footer.php'; ?>