<?php
require_once '../../config/database.php';
require_once '../../helpers/session.php';
requireRole('bendahara');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../../index_admin.html');
    exit;
}

$nama_bend = trim($_POST['nama_bend'] ?? '');
$username  = trim($_POST['username'] ?? '');
$password  = trim($_POST['password'] ?? '');

if (empty($nama_bend) || empty($username) || empty($password)) {
    header('Location: ../../../index_admin.html?error=Semua+field+wajib+diisi');
    exit;
}

$db   = getDB();
$hash = password_hash($password, PASSWORD_BCRYPT);

$stmt_user = $db->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'bendahara')");
$stmt_user->bind_param("ss", $username, $hash);
$stmt_user->execute();
$id_user = $db->insert_id;
$stmt_user->close();

$stmt = $db->prepare("INSERT INTO bendahara (id_user, nama_bend) VALUES (?, ?)");
$stmt->bind_param("is", $id_user, $nama_bend);
$stmt->execute();
$stmt->close();

$db->close();
header('Location: ../../../index_admin.html?sukses=Bendahara+berhasil+ditambahkan');
exit;
