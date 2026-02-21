<?php
require_once '../../config/database.php';
require_once '../../helpers/session.php';
requireRole('bendahara');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../../index_admin.html');
    exit;
}

$id_kepala   = (int)($_POST['id_kepala'] ?? 0);
$nama_kepala = trim($_POST['nama_kepala'] ?? '');

if (!$id_kepala || empty($nama_kepala)) {
    header('Location: ../../../index_admin.html?error=Data+tidak+lengkap');
    exit;
}

$db   = getDB();
$stmt = $db->prepare("UPDATE kepala SET nama_kepala=? WHERE id_kepala=?");
$stmt->bind_param("si", $nama_kepala, $id_kepala);
$stmt->execute();
$stmt->close();
$db->close();

header('Location: ../../../index_admin.html?sukses=Data+kepala+sekolah+diperbarui');
exit;
