<?php
require_once '../config/database.php';
require_once '../helpers/session.php';

$referer = $_SERVER['HTTP_REFERER'] ?? '../../Login_Admin.html';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $referer);
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

if (empty($username) || empty($password)) {
    $separator = (strpos($referer, '?') !== false) ? '&' : '?';
    header('Location: ' . $referer . $separator . 'error=Lengkapi+username+dan+password');
    exit;
}

$db   = getDB();
$stmt = $db->prepare("SELECT id_user, username, password, role FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$user   = $result->fetch_assoc();
$stmt->close();
$db->close();

if (!$user || !password_verify($password, $user['password'])) {
    $separator = (strpos($referer, '?') !== false) ? '&' : '?';
    header('Location: ' . $referer . $separator . 'error=Username+atau+password+salah');
    exit;
}

setSession($user['id_user'], $user['username'], $user['role']);

switch ($user['role']) {
    case 'bendahara':
        header('Location: ../../index_admin.html');
        break;
    case 'kepala':
        header('Location: ../../index_admin.html');
        break;
    case 'siswa':
        header('Location: ../../Index_siswa.html');
        break;
    default:
        header('Location: ../../Login_Admin.html');
}
exit;

