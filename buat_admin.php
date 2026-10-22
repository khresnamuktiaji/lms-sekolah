<?php
require_once 'config.php';
$sql = "INSERT INTO users (username, password, nama_lengkap, role, email, failed_attempts, lockout_time) VALUES (?, ?, 'Administrator Server', 'guru', 'admin@sekolah.sch.id', 0, NULL) ON DUPLICATE KEY UPDATE password = ?, failed_attempts = 0, lockout_time = NULL";
try {
    $pdo->prepare($sql)->execute(['admin', password_hash('adminxampp', PASSWORD_DEFAULT), password_hash('adminxampp', PASSWORD_DEFAULT)]);
    echo "<h2 style='color:green'>✅ ADMIN RESET/BUAT BERHASIL! (Pass: adminxampp) HAPUS FILE INI!</h2><a href='index.php'>Ke Login</a>";
} catch (Exception $e) { echo "Error: " . $e->getMessage(); }
?>