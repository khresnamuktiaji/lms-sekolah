<?php
require_once 'config.php';[cite: 11]

$username = 'admin'; 
$password = password_hash('adminxampp', PASSWORD_DEFAULT);

$sql = "INSERT INTO users (username, password, nama_lengkap, role, email, failed_attempts, lockout_time) 
        VALUES (?, ?, 'Administrator Server', 'guru', 'admin@sekolah.sch.id', 0, NULL)
        ON DUPLICATE KEY UPDATE password = ?, failed_attempts = 0, lockout_time = NULL";

try {
    $pdo->prepare($sql)->execute([$username, $password, $password]);
    echo "<h3 style='color:green; font-family:sans-serif;'>✅ RESET ADMIN BERHASIL!</h3>";
    echo "<p style='font-family:sans-serif;'>Akun <b>admin</b> telah dipulihkan. Blokir/Lockout telah dihapus.</p>";
    echo "<ul style='font-family:sans-serif;'><li>Username: <b>admin</b></li><li>Password: <b>adminxampp</b></li></ul>";
    echo "<p style='color:red; font-weight:bold; font-family:sans-serif;'>⚠️ HAPUS file reset_admin.php ini setelah berhasil!</p>";
    echo "<a href='index.php' style='font-family:sans-serif;'>Kembali ke Login</a>";
} catch(Exception $e) {
    echo "Error Database: " . e($e->getMessage());
}
?>