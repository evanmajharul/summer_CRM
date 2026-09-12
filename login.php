<?php
require 'config.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT * FROM system_users WHERE username = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => (int)$user['id'],
            'username' => $user['username'],
            'display_name' => $user['display_name'],
            'role' => $user['role'],
        ];
        audit($pdo, 'LOGIN', 'system_user', (int)$user['id'], 'User logged in');
        redirect('dashboard.php');
    }

    $error = 'Invalid username or password.';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - Summer Fair CRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
</head>
<body class="login-page">
<div class="container">
    <div class="row min-vh-100 align-items-center justify-content-center">
        <div class="col-md-5 col-lg-4">
            <div class="card border-0 shadow-lg">
                <div class="card-body p-4">
                    <h2 class="fw-bold text-center mb-1">Summer Fair CRM</h2>
                    <p class="text-muted text-center mb-4">Staff, earnings and payment dashboard</p>

                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= e($error) ?></div>
                    <?php endif; ?>

                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input class="form-control" name="username" required autofocus>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input class="form-control" type="password" name="password" required>
                        </div>
                        <button class="btn btn-primary w-100">Sign in</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>