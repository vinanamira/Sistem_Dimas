<?php
// proses/laporan_pembayaran/hapus_laporan_pembayaran.php
require_once '../../config/database.php';
require_once '../../helpers/session.php';
requireRole(['bendahara','kepala']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../../Riwayat_pembayaran.html');
    exit;
}

$id_lapbayar = (int)($_POST['id_lapbayar'] ?? 0);
if (!$id_lapbayar) {
    header('Location: ../../../Riwayat_pembayaran.html?error=ID+tidak+valid');
    exit;
}

$db   = getDB();
$stmt = $db->prepare("DELETE FROM laporan_pembayaran WHERE id_lapbayar=?");
$stmt->bind_param("i", $id_lapbayar);
$stmt->execute();
$stmt->close();
$db->close();

header('Location: ../../../Riwayat_pembayaran.html?sukses=Laporan+pembayaran+dihapus');
exit;
