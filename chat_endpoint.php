<?php
require_once 'config.php';
if (!isset($_SESSION['user_id'])) exit;
$action = $_GET['action'] ?? '';

if ($action === 'get') {
    $kelas = $_GET['kelas'] ?? '';
    $stmt = $pdo->prepare("SELECT c.*, u.nama_lengkap FROM class_chats c JOIN users u ON c.id_sender = u.id WHERE c.kelas = ? ORDER BY c.id ASC LIMIT 100");
    $stmt->execute([$kelas]);
    echo json_encode($stmt->fetchAll());
} elseif ($action === 'send') {
    $data = json_decode(file_get_contents('php://input'), true);
    if ($data && !empty($data['pesan'])) {
        $stmt = $pdo->prepare("INSERT INTO class_chats (kelas, id_sender, pesan) VALUES (?, ?, ?)");
        $stmt->execute([$data['kelas'], $_SESSION['user_id'], trim($data['pesan'])]);
        echo json_encode(['status' => 'ok']);
    }
}
?>