<?php
// Force HTTPS
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === "off") {
    header('HTTP/1.1 301 Moved Permanently');
    header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    exit;
}

// Hardened Session Configuration
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', 1); 
    ini_set('session.use_only_cookies', 1);
    session_set_cookie_params([
        'lifetime' => 0, 
        'path' => '/', 
        'secure' => true, 
        'httponly' => true, 
        'samesite' => 'Strict'
    ]);
    session_start();
}

// HTTP Security Headers
header("X-Frame-Options: SAMEORIGIN"); 
header("X-Content-Type-Options: nosniff"); 
header("X-XSS-Protection: 1; mode=block");

$host = "localhost"; 
$user = "root"; 
$pass = ""; 
$dbname = "db_lms";

try { 
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, 
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, 
        PDO::ATTR_EMULATE_PREPARES => false
    ]); 
} catch (PDOException $e) { 
    die("Database Connection Error."); 
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// User-Agent Binding untuk Mencegah Session Hijacking
if (isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) { 
    session_unset(); 
    session_destroy(); 
    header("Location: index.php"); 
    exit;
}

function verify_csrf() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']))) { 
        http_response_code(403); 
        die("CSRF Security Blocked"); 
    }
}

function e($str) { 
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8'); 
}

// Secure File Upload dengan Magic Bytes Verification
function upload_secure_file($file, $dir, $max_mb = 50) {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['status' => false, 'msg' => 'Gagal mengunggah berkas.'];
    }
    if ($file['size'] > ($max_mb * 1024 * 1024)) {
        return ['status' => false, 'msg' => "Ukuran berkas melebihi batas $max_mb MB."];
    }
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_ext = ['pdf', 'docx', 'doc', 'zip', 'rar', 'png', 'jpg', 'jpeg', 'mp4', 'webm'];
    if (!in_array($ext, $allowed_ext, true)) {
        return ['status' => false, 'msg' => 'Ekstensi berkas dilarang sistem!'];
    }
    
    $handle = fopen($file['tmp_name'], 'rb'); 
    $bytes = fread($handle, 8); 
    fclose($handle);
    
    $valid = false;
    if (strpos($bytes, "%PDF") === 0 && $ext === 'pdf') $valid = true; 
    elseif (strpos($bytes, "\x89PNG\r\n\x1a\n") === 0 && $ext === 'png') $valid = true;
    elseif (strpos($bytes, "\xFF\xD8\xFF") === 0 && in_array($ext, ['jpg', 'jpeg'], true)) $valid = true; 
    elseif (strpos($bytes, "PK\x03\x04") === 0 && in_array($ext, ['zip', 'docx'], true)) $valid = true;
    elseif (strpos($bytes, "Rar!\x1A\x07") === 0 && $ext === 'rar') $valid = true; 
    elseif (strpos($bytes, "\xD0\xCF\x11\xE0") === 0 && $ext === 'doc') $valid = true;
    elseif (in_array($ext, ['mp4', 'webm'], true)) $valid = true;
    
    if (!$valid) {
        return ['status' => false, 'msg' => 'Magic Bytes Invalid! File terindikasi manipulasi.'];
    }

    $new_name = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $destination = rtrim($dir, '/') . '/' . $new_name;
    
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return ['status' => true, 'filename' => $new_name];
    }
    return ['status' => false, 'msg' => 'Gagal menyimpan berkas ke server.'];
}
?>