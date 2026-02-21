<?php
// proses/laporan_keuangan/hapus_laporan_keuangan.php
require_once '../../config/database.php';
require_once '../../helpers/session.php';
requireRole(['bendahara','kepala']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../../Laporan_Pengeluaran.html');
    exit;
}

$id_lapuang = (int)($_POST['id_lapuang'] ?? 0);
if (!$id_lapuang) {
    header('Location: ../../../Laporan_Pengeluaran.html?error=ID+tidak+valid');
    exit;
}

$db   = getDB();
$stmt = $db->prepare("DELETE FROM laporan_keuangan WHERE id_lapuang=?");
$stmt->bind_param("i", $id_lapuang);
$stmt->execute();
$stmt->close();
$db->close();

header('Location: ../../../Laporan_Pengeluaran.html?sukses=Laporan+keuangan+dihapus');
exit;
