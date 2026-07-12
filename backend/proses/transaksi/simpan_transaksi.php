<?php
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/transaksi_helper.php';
requireRole('bendahara');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../../Pembayaran_Masuk.html');
    exit;
}

$id_siswa      = (int) ($_POST['id_siswa'] ?? 0);
$id_tunggakan  = (int) ($_POST['id_tunggakan'] ?? 0);
$jml_bayar     = floatval($_POST['jml_bayar'] ?? 0);
$tgl_transaksi = trim($_POST['tgl_transaksi'] ?? date('Y-m-d'));
$keterangan    = trim($_POST['keterangan'] ?? 'Pembayaran SPP');
if ($keterangan === '') {
    $keterangan = 'Pembayaran SPP';
}

if (!$id_siswa || $jml_bayar <= 0) {
    header('Location: ../../../Pembayaran_Masuk.html?error=Data+tidak+valid');
    exit;
}

$db = getDB();
try {
    simpanTransaksiPembayaran($db, $id_siswa, $id_tunggakan ?: null, $jml_bayar, $tgl_transaksi, $keterangan);
} catch (Throwable $e) {
    $db->close();
    header('Location: ../../../Pembayaran_Masuk.html?error=Gagal+menyimpan+transaksi');
    exit;
}
$db->close();
header('Location: ../../../Pembayaran_Masuk.html?sukses=Transaksi+berhasil+disimpan');
exit;
