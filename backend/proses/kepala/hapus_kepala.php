<?php
require_once '../../config/database.php';
require_once '../../helpers/session.php';
requireRole('bendahara');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../../index_admin.html');
    exit;
}

$id_kepala = (int)($_POST['id_kepala'] ?? 0);
if (!$id_kepala) {
    header('Location: ../../../index_admin.html?error=ID+tidak+valid');
    exit;
}

$db    = getDB();
$ambil = $db->prepare("SELECT id_user FROM kepala WHERE id_kepala=?");
$ambil->bind_param("i", $id_kepala);
$ambil->execute();
$ambil->bind_result($id_user);
$ambil->fetch();
$ambil->close();

$stmt = $db->prepare("DELETE FROM kepala WHERE id_kepala=?");
$stmt->bind_param("i", $id_kepala);
$stmt->execute();
$stmt->close();

if ($id_user) {
    $stmt2 = $db->prepare("DELETE FROM users WHERE id_user=?");
    $stmt2->bind_param("i", $id_user);
    $stmt2->execute();
    $stmt2->close();
}

$db->close();
header('Location: ../../../index_admin.html?sukses=Kepala+sekolah+berhasil+dihapus');
exit;
