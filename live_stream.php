<?php
require_once 'config.php';
if (!isset($_SESSION['user_id'])) exit;
$user_id = (int)$_SESSION['user_id'];
$action = $_GET['action'] ?? '';

if ($action === 'send') {
    $data = json_decode(file_get_contents('php://input'), true);
    if ($data) {
        $stmt = $pdo->prepare("INSERT INTO webrtc_stream (sender_id, target_id, type, payload) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $data['target_id'] ?? NULL, $data['type'], json_encode($data['payload'])]);
        echo json_encode(['status' => 'ok']);
    }
} elseif ($action === 'fetch') {
    $stmt = $pdo->prepare("SELECT * FROM webrtc_stream WHERE (target_id = ? OR target_id IS NULL) AND sender_id != ?");
    $stmt->execute([$user_id, $user_id]);
    $msgs = $stmt->fetchAll();
    if ($msgs) {
        $del = $pdo->prepare("DELETE FROM webrtc_stream WHERE id = ?");
        foreach ($msgs as $m) $del->execute([$m['id']]);
    }
    header('Content-Type: application/json');
    echo json_encode($msgs);
}
?>