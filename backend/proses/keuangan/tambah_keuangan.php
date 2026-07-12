<?php
require_once '../../config/database.php';
require_once '../../helpers/session.php';
requireRole('bendahara');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../../Laporan_Pengeluaran.html');
    exit;
}

$jml_uang    = floatval($_POST['jml_uang'] ?? 0);
$ket_uang    = trim($_POST['ket_uang'] ?? '');
$tgl_uang    = trim($_POST['tgl_uang'] ?? date('Y-m-d'));
$jenis_uang  = trim($_POST['jenis_uang'] ?? '');
$kategori    = trim($_POST['kategori'] ?? 'Lainnya');
$sumber_dana = trim($_POST['sumber_dana'] ?? '');
$sumber_dana = $sumber_dana !== '' ? $sumber_dana : null;

if ($jml_uang <= 0 || $ket_uang === '' || !in_array($jenis_uang, ['pemasukan', 'pengeluaran'], true)) {
    header('Location: ../../../Laporan_Pengeluaran.html?error=Data+tidak+valid');
    exit;
}

$db = getDB();

$id_kepala = null;
$q         = $db->query('SELECT id_kepala FROM kepala ORDER BY id_kepala ASC LIMIT 1');
if ($q && $r = $q->fetch_assoc()) {
    $id_kepala = (int) $r['id_kepala'];
}

$stmt = $db->prepare(
    'INSERT INTO keuangan (jml_uang, ket_uang, tgl_uang, jenis_uang, kategori, sumber_dana) VALUES (?, ?, ?, ?, ?, ?)'
);
$stmt->bind_param('dsssss', $jml_uang, $ket_uang, $tgl_uang, $jenis_uang, $kategori, $sumber_dana);
$stmt->execute();
$id_uang = (int) $db->insert_id;
$stmt->close();

$stmt2 = $db->prepare(
    'INSERT INTO laporan_keuangan (jenis_uang, ket_uang, tgl_uang, jml_uang, kategori, id_keuangan, id_kepala)
     VALUES (?, ?, ?, ?, ?, ?, ?)'
);
$stmt2->bind_param('sssdsii', $jenis_uang, $ket_uang, $tgl_uang, $jml_uang, $kategori, $id_uang, $id_kepala);
$stmt2->execute();
$stmt2->close();

$db->close();
header('Location: ../../../Laporan_Pengeluaran.html?sukses=Data+keuangan+berhasil+ditambahkan');
exit;
