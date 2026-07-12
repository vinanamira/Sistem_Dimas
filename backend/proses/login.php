<?php
require_once '../config/database.php';
require_once '../helpers/session.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

if (empty($username) || empty($password)) {
    echo json_encode(['success' => false, 'error' => 'Lengkapi username dan password']);
    exit;
}

$db = getDB();
$user = null;

if (is_numeric($username)) {
    $stmt = $db->prepare("SELECT u.id_user, u.username, u.password, u.role FROM users u JOIN siswa s ON u.id_user = s.id_user WHERE s.nis = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
} else {
    $stmt = $db->prepare("SELECT id_user, username, password, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
}

$db->close();

if (!$user || !password_verify($password, $user['password'])) {
    echo json_encode(['success' => false, 'error' => 'Username atau password salah']);
    exit;
}

// Terisolasi per tab: buat ID sesi baru untuk login ini
$newSessionId = bin2hex(random_bytes(16));
session_write_close();
session_id($newSessionId);
session_start();

setSession($user['id_user'], $user['username'], $user['role']);

$redirectUrl = 'login.php';
switch ($user['role']) {
    case 'bendahara':
        $redirectUrl = 'index_admin.html';
        break;
    case 'kepala':
        $redirectUrl = 'index_kepsek.html';
        break;
    case 'siswa':
        $redirectUrl = 'Index_siswa.html';
        break;
}

echo json_encode([
    'success' => true,
    'session_id' => $newSessionId,
    'redirect' => $redirectUrl
]);
exit;
