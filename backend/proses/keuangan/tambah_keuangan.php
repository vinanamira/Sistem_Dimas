<?php
// proses/keuangan/tambah_keuangan.php
// Tambah data keuangan (pemasukan/pengeluaran)

require_once '../../config/database.php';
require_once '../../helpers/session.php';
requireRole('bendahara');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../../Laporan_Pengeluaran.html');
    exit;
}

$jml_uang  = floatval($_POST['jml_uang'] ?? 0);
$ket_uang  = trim($_POST['ket_uang'] ?? '');
$tgl_uang  = trim($_POST['tgl_uang'] ?? date('Y-m-d'));
$jenis_uang = trim($_POST['jenis_uang'] ?? '');

if ($jml_uang <= 0 || empty($ket_uang) || !in_array($jenis_uang, ['pemasukan','pengeluaran'])) {
    header('Location: ../../../Laporan_Pengeluaran.html?error=Data+tidak+valid');
    exit;
}

$db   = getDB();
$stmt = $db->prepare("INSERT INTO keuangan (jml_uang, ket_uang, tgl_uang, jenis_uang) VALUES (?, ?, ?, ?)");
$stmt->bind_param("dsss", $jml_uang, $ket_uang, $tgl_uang, $jenis_uang);
$stmt->execute();
$id_uang = $db->insert_id;
$stmt->close();

// Otomatis buat laporan_keuangan juga
$stmt2 = $db->prepare("INSERT INTO laporan_keuangan (jenis_uang, ket_uang, tgl_uang, jml_uang) VALUES (?, ?, ?, ?)");
$stmt2->bind_param("sssd", $jenis_uang, $ket_uang, $tgl_uang, $jml_uang);
$stmt2->execute();
$stmt2->close();

$db->close();
header('Location: ../../../Laporan_Pengeluaran.html?sukses=Data+keuangan+berhasil+ditambahkan');
exit;
