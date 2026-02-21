<?php
// proses/keuangan/hapus_keuangan.php
require_once '../../config/database.php';
require_once '../../helpers/session.php';
requireRole('bendahara');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../../Laporan_Pengeluaran.html');
    exit;
}

$id_uang = (int)($_POST['id_uang'] ?? 0);
if (!$id_uang) {
    header('Location: ../../../Laporan_Pengeluaran.html?error=ID+tidak+valid');
    exit;
}

$db   = getDB();
$stmt = $db->prepare("DELETE FROM keuangan WHERE id_uang=?");
$stmt->bind_param("i", $id_uang);
$stmt->execute();
$stmt->close();
$db->close();

header('Location: ../../../Laporan_Pengeluaran.html?sukses=Data+keuangan+dihapus');
exit;
