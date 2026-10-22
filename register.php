<?php
require_once 'config.php';
$err = ''; $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim($_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $kelas = $_POST['kelas'];
    $no_telp = trim($_POST['no_telp']);
    $email = trim($_POST['email']);

    if (!in_array($kelas, getDaftarKelas(), true)) {
        $err = "Pilihan kelas tidak valid!";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO users (username, password, nama_lengkap, role, kelas, no_telp, email) VALUES (?, ?, ?, 'siswa', ?, ?, ?)");
            $stmt->execute([$username, $password, $nama_lengkap, $kelas, $no_telp, $email]);
            $success = "Registrasi Berhasil! Silakan <a href='index.php'>Login di sini</a>.";
        } catch (PDOException $e) {
            $err = "Username sudah terdaftar! Gunakan username lain.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrasi Siswa - LMS Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center py-5">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow border-0 p-4">
                <h4 class="text-center text-success fw-bold mb-3">REGISTRASI SISWA</h4>
                <?php if ($err): ?><div class="alert alert-danger small"><?= e($err) ?></div><?php endif; ?>
                <?php if ($success): ?><div class="alert alert-success small"><?= $success ?></div><?php endif; ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <div class="mb-3"><label class="form-label">Nama Lengkap</label><input type="text" name="nama_lengkap" class="form-control" required></div>
                    <div class="mb-3">
                        <label class="form-label">Kelas (Pilih Kelas Anda)</label>
                        <select name="kelas" class="form-select" required>
                            <option value="">-- Pilih Kelas --</option>
                            <?php foreach(getDaftarKelas() as $kls): ?>
                                <option value="<?= $kls ?>"><?= $kls ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3"><label class="form-label">No. Telepon / WhatsApp</label><input type="text" name="no_telp" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Email Aktif</label><input type="email" name="email" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Username</label><input type="text" name="username" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
                    <button type="submit" class="btn btn-success w-100 fw-bold">Daftar Sekarang</button>
                </form>
                <div class="text-center mt-3"><a href="index.php" class="text-decoration-none small">Sudah punya akun? Login</a></div>
            </div>
        </div>
    </div>
</div>
</body>
</html>