<?php
require_once 'config.php';
if (isset($_SESSION['user_id'])) { 
    header("Location: " . ($_SESSION['role'] === 'guru' ? "teacher_dashboard.php" : "student_dashboard.php")); 
    exit; 
}

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([trim($_POST['username'])]);
    $user = $stmt->fetch();
    $dummy_hash = '$2y$10$usesomesillystringfore2uDLvn12bIqp.1vH.x/o.YwB9b.jG2';

    if ($user) {
        if ($user['lockout_time'] && strtotime($user['lockout_time']) > time()) {
            $sisa = ceil((strtotime($user['lockout_time']) - time()) / 60);
            $err = "🔒 Akun dikunci karena terlalu banyak percobaan salah! Coba lagi dalam $sisa menit.";
        } else {
            if (password_verify($_POST['password'], $user['password'])) {
                $pdo->prepare("UPDATE users SET failed_attempts = 0, lockout_time = NULL WHERE id = ?")->execute([$user['id']]);
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['nama'] = $user['nama_lengkap'];
                $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
                header("Location: " . ($user['role'] === 'guru' ? "teacher_dashboard.php" : "student_dashboard.php"));
                exit;
            } else {
                $attempts = $user['failed_attempts'] + 1;
                if ($attempts >= 5) {
                    $pdo->prepare("UPDATE users SET failed_attempts = ?, lockout_time = DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE id = ?")->execute([$attempts, $user['id']]);
                    $err = "⛔ Akses diblokir! Akun dikunci selama 15 menit.";
                } else {
                    $pdo->prepare("UPDATE users SET failed_attempts = ? WHERE id = ?")->execute([$attempts, $user['id']]);
                    $err = "Username atau Password salah!";
                }
            }
        }
    } else {
        password_verify($_POST['password'], $dummy_hash); // Anti Timing Attack
        $err = "Username atau Password salah!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - LMS Portal Enterprise</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center vh-100">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-4">
            <div class="card shadow border-0 p-4">
                <h4 class="text-center text-primary fw-bold mb-1">LMS PORTAL</h4>
                <p class="text-center text-muted small mb-4">Kelas 10, 11, & 12 Enterprise Edition</p>
                <?php if ($err): ?><div class="alert alert-danger small fw-bold"><?= e($err) ?></div><?php endif; ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <div class="mb-3"><label class="form-label">Username</label><input type="text" name="username" class="form-control" required placeholder="Masukkan username"></div>
                    <div class="mb-3"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required placeholder="Masukkan password"></div>
                    <button type="submit" class="btn btn-primary w-100 mb-3 fw-bold">Masuk Portal</button>
                </form>
                <a href="register.php" class="btn btn-outline-success btn-sm w-100 fw-bold">Daftar Akun Siswa Baru</a>
            </div>
        </div>
    </div>
</div>
</body>
</html>