<?php
require 'config.php';
requirePrimaryAdmin();

$action = trim($_GET['action'] ?? '');
$adminId = (int)($_GET['admin_id'] ?? 0);

$where = [];
$params = [];

if ($action !== '') {
    $where[] = 'l.action=?';
    $params[] = $action;
}
if ($adminId) {
    $where[] = 'l.admin_id=?';
    $params[] = $adminId;
}

$sql = "SELECT l.*, u.display_name, u.username
        FROM audit_logs l
        LEFT JOIN system_users u ON u.id=l.admin_id";
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY l.created_at DESC LIMIT 1000';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$admins = $pdo->query("SELECT id, display_name, username FROM system_users WHERE role IN ('primary_admin','admin') ORDER BY display_name")->fetchAll();

$pageTitle = 'Audit Log';
require 'header.php';
?>
<div class="mb-4"><h1 class="h3 mb-1">Audit Log</h1><p class="text-muted mb-0">Primary admin only: see who created, edited or deleted information and when</p></div>

<div class="card mb-4">
    <div class="card-body">
        <form class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Admin</label>
                <select class="form-select" name="admin_id">
                    <option value="">All admins</option>
                    <?php foreach ($admins as $a): ?>
                        <option value="<?= (int)$a['id'] ?>" <?= $adminId===(int)$a['id']?'selected':'' ?>><?= e($a['display_name']) ?> (<?= e($a['username']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Action</label>
                <select class="form-select" name="action">
                    <option value="">All actions</option>
                    <?php foreach (['CREATE','UPDATE','DELETE','LOGIN','LOGOUT'] as $a): ?>
                        <option value="<?= e($a) ?>" <?= $action===$a?'selected':'' ?>><?= e($a) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end gap-2"><button class="btn btn-primary">Filter</button><a class="btn btn-light" href="audit.php">Reset</a></div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Date/time</th><th>Admin</th><th>Action</th><th>Record type</th><th>Record ID</th><th>Details</th></tr></thead>
            <tbody>
            <?php foreach ($logs as $l): ?>
                <tr>
                    <td><?= e(date('d M Y H:i:s', strtotime($l['created_at']))) ?></td>
                    <td><?= e($l['display_name'] ?? 'Deleted user') ?><?= $l['username'] ? ' (' . e($l['username']) . ')' : '' ?></td>
                    <td><span class="badge text-bg-<?= $l['action']==='DELETE'?'danger':($l['action']==='UPDATE'?'warning':'secondary') ?>"><?= e($l['action']) ?></span></td>
                    <td><?= e($l['entity_type']) ?></td>
                    <td><?= e((string)$l['entity_id']) ?></td>
                    <td><?= e($l['details']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$logs): ?><tr><td colspan="6" class="text-center text-muted py-4">No audit records found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require 'footer.php'; ?>