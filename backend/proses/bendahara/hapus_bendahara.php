<?php
require_once '../../config/database.php';
require_once '../../helpers/session.php';
requireRole('bendahara');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../../index_admin.html');
    exit;
}

$id_bend = (int)($_POST['id_bend'] ?? 0);
if (!$id_bend) {
    header('Location: ../../../index_admin.html?error=ID+tidak+valid');
    exit;
}

$db   = getDB();
$ambil = $db->prepare("SELECT id_user FROM bendahara WHERE id_bend=?");
$ambil->bind_param("i", $id_bend);
$ambil->execute();
$ambil->bind_result($id_user);
$ambil->fetch();
$ambil->close();

$stmt = $db->prepare("DELETE FROM bendahara WHERE id_bend=?");
$stmt->bind_param("i", $id_bend);
$stmt->execute();
$stmt->close();

if ($id_user) {
    $stmt2 = $db->prepare("DELETE FROM users WHERE id_user=?");
    $stmt2->bind_param("i", $id_user);
    $stmt2->execute();
    $stmt2->close();
}

$db->close();
header('Location: ../../../index_admin.html?sukses=Bendahara+berhasil+dihapus');
exit;
