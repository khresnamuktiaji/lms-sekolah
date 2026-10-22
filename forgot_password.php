<?php
require_once 'config.php';
$err = ''; $msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim($_POST['username'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $new_password = $_POST['new_password'] ?? '';

    if (empty($username) || empty($contact) || empty($new_password)) {
        $err = 'Semua kolom wajib diisi!';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND (email = ? OR no_telp = ?)");
        $stmt->execute([$username, $contact, $contact]);
        $user = $stmt->fetch();

        if ($user) {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $upd = $pdo->prepare("UPDATE users SET password = ?, failed_attempts = 0, lockout_time = NULL WHERE id = ?");
            $upd->execute([$hashed, $user['id']]);
            $msg = 'Password berhasil direset! Silakan login kembali.';
        } else {
            $err = 'Data username atau email/No. Telepon tidak cocok.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Reset Password</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-light d-flex align-items-center vh-100">
<div class="container col-md-4">
    <div class="card shadow border-0 p-4">
        <h4 class="text-center text-secondary fw-bold mb-3">Reset Password</h4>
        <?php if($err) echo "<div class='alert alert-danger p-2 small'>".e($err)."</div>"; ?>
        <?php if($msg) echo "<div class='alert alert-success p-2 small'>".e($msg)."</div>"; ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div class="mb-3"><label class="form-label">Username</label><input type="text" name="username" class="form-control" required></div>
            <div class="mb-3"><label class="form-label">Email / No. Telepon Terdaftar</label><input type="text" name="contact" class="form-control" required></div>
            <div class="mb-3"><label class="form-label">Password Baru</label><input type="password" name="new_password" class="form-control" required></div>
            <button type="submit" class="btn btn-warning w-100 mb-2 text-white">Reset Password</button>
            <a href="index.php" class="btn btn-link w-100 text-decoration-none small text-secondary">Kembali ke Login</a>
        </form>
    </div>
</div>
</body>
</html>