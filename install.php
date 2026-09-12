<?php
declare(strict_types=1);

$lockFile = __DIR__ . '/install.lock';
if (is_file($lockFile)) {
    http_response_code(403);
    exit('Installation is locked. Delete install.lock only if you intentionally need to reinstall.');
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = trim($_POST['db_host'] ?? 'localhost');
    $port = trim($_POST['db_port'] ?? '3306');
    $name = trim($_POST['db_name'] ?? '');
    $user = trim($_POST['db_user'] ?? '');
    $pass = $_POST['db_pass'] ?? '';
    $baseUrl = rtrim(trim($_POST['base_url'] ?? ''), '/');
    $forceHttps = isset($_POST['force_https']);

    try {
        if ($name === '' || $user === '' || $baseUrl === '') {
            throw new RuntimeException('Database name, database user and application URL are required.');
        }

        $pdo = new PDO(
            "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
            $user,
            $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
        );

        $sql = file_get_contents(__DIR__ . '/database_live.sql');
        if ($sql === false) throw new RuntimeException('Could not read database_live.sql.');
        $pdo->exec($sql);

        $users = [
            ['evan', 'Qwaszx92837465@', 'primary_admin', 'Evan'],
            ['shaif', 'shaif123', 'admin', 'Shaif'],
            ['fahad', 'fahad123', 'admin', 'Fahad'],
            ['marcus', 'marcus321', 'viewer', 'Marcus'],
            ['james', 'james321', 'viewer', 'James'],
        ];

        $stmt = $pdo->prepare(
            'INSERT INTO system_users (username, password_hash, role, display_name)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash), role=VALUES(role), display_name=VALUES(display_name), is_active=1'
        );
        foreach ($users as [$username, $password, $role, $display]) {
            $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $role, $display]);
        }

        $config = "<?php\nreturn " . var_export([
            'db_host' => $host,
            'db_port' => $port,
            'db_name' => $name,
            'db_user' => $user,
            'db_pass' => $pass,
            'base_url' => $baseUrl,
            'environment' => 'production',
            'force_https' => $forceHttps,
        ], true) . ";\n";

        if (file_put_contents(__DIR__ . '/app_config.php', $config, LOCK_EX) === false) {
            throw new RuntimeException('Could not create app_config.php. Check folder write permissions.');
        }
        chmod(__DIR__ . '/app_config.php', 0600);
        file_put_contents($lockFile, 'Installed: ' . date(DATE_ATOM), LOCK_EX);
        $message = 'Installation completed. Delete install.php from the server, then open login.php.';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Install Summer Fair CRM</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light"><div class="container py-5"><div class="row justify-content-center"><div class="col-lg-7">
<div class="card shadow-sm border-0"><div class="card-body p-4">
<h1 class="h3">Live Server Installer</h1>
<p class="text-muted">Create the database and database user in cPanel first, then enter those details below.</p>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES) ?></div><?php endif; ?>
<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message, ENT_QUOTES) ?></div><a class="btn btn-primary" href="login.php">Open login</a><?php else: ?>
<form method="post">
<div class="row g-3">
<div class="col-md-8"><label class="form-label">Database host</label><input class="form-control" name="db_host" value="localhost" required></div>
<div class="col-md-4"><label class="form-label">Port</label><input class="form-control" name="db_port" value="3306" required></div>
<div class="col-12"><label class="form-label">Database name</label><input class="form-control" name="db_name" placeholder="cpaneluser_summerfair" required></div>
<div class="col-12"><label class="form-label">Database username</label><input class="form-control" name="db_user" placeholder="cpaneluser_crmuser" required></div>
<div class="col-12"><label class="form-label">Database password</label><input class="form-control" type="password" name="db_pass" required></div>
<div class="col-12"><label class="form-label">Application URL</label><input class="form-control" type="url" name="base_url" placeholder="https://yourdomain.com/summer-fair" required></div>
<div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="force_https" id="https" checked><label class="form-check-label" for="https">Force HTTPS</label></div></div>
</div><button class="btn btn-primary mt-4">Install system</button>
</form><?php endif; ?>
</div></div></div></div></div></body></html>
