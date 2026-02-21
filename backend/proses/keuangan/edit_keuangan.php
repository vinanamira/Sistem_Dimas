<?php
// proses/keuangan/edit_keuangan.php
require_once '../../config/database.php';
require_once '../../helpers/session.php';
requireRole('bendahara');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../../Laporan_Pengeluaran.html');
    exit;
}

$id_uang   = (int)($_POST['id_uang'] ?? 0);
$jml_uang  = floatval($_POST['jml_uang'] ?? 0);
$ket_uang  = trim($_POST['ket_uang'] ?? '');
$tgl_uang  = trim($_POST['tgl_uang'] ?? '');
$jenis_uang = trim($_POST['jenis_uang'] ?? '');

if (!$id_uang || $jml_uang <= 0 || empty($ket_uang) || !in_array($jenis_uang, ['pemasukan','pengeluaran'])) {
    header('Location: ../../../Laporan_Pengeluaran.html?error=Data+tidak+valid');
    exit;
}

$db   = getDB();
$stmt = $db->prepare("UPDATE keuangan SET jml_uang=?, ket_uang=?, tgl_uang=?, jenis_uang=? WHERE id_uang=?");
$stmt->bind_param("dsssi", $jml_uang, $ket_uang, $tgl_uang, $jenis_uang, $id_uang);
$stmt->execute();
$stmt->close();
$db->close();

header('Location: ../../../Laporan_Pengeluaran.html?sukses=Data+keuangan+berhasil+diperbarui');
exit;
