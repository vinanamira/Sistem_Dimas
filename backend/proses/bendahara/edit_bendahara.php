<?php
require_once '../../config/database.php';
require_once '../../helpers/session.php';
requireRole('bendahara');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../../index_admin.html');
    exit;
}

$id_bend   = (int)($_POST['id_bend'] ?? 0);
$nama_bend = trim($_POST['nama_bend'] ?? '');

if (!$id_bend || empty($nama_bend)) {
    header('Location: ../../../index_admin.html?error=Data+tidak+lengkap');
    exit;
}

$db   = getDB();
$stmt = $db->prepare("UPDATE bendahara SET nama_bend=? WHERE id_bend=?");
$stmt->bind_param("si", $nama_bend, $id_bend);
$stmt->execute();
$stmt->close();
$db->close();

header('Location: ../../../index_admin.html?sukses=Bendahara+berhasil+diperbarui');
exit;
