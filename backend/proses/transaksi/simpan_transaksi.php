<?php
// proses/transaksi/simpan_transaksi.php
// Menyimpan transaksi pembayaran SPP (Bendahara only)
// Sesuai class Transaksi: simpan()
// Setelah simpan transaksi → update tunggakan → buat laporan pembayaran

require_once '../../config/database.php';
require_once '../../helpers/session.php';
requireRole('bendahara');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../../Pembayaran_Masuk.html');
    exit;
}

$id_siswa      = (int)($_POST['id_siswa'] ?? 0);
$id_tunggakan  = (int)($_POST['id_tunggakan'] ?? 0);
$jml_bayar     = floatval($_POST['jml_bayar'] ?? 0);
$tgl_transaksi = trim($_POST['tgl_transaksi'] ?? date('Y-m-d'));

if (!$id_siswa || $jml_bayar <= 0) {
    header('Location: ../../../Pembayaran_Masuk.html?error=Data+tidak+valid');
    exit;
}

$db = getDB();

// Ambil nama siswa
$ambil = $db->prepare("SELECT nama_siswa FROM siswa WHERE id_siswa=?");
$ambil->bind_param("i", $id_siswa);
$ambil->execute();
$ambil->bind_result($nama_siswa);
$ambil->fetch();
$ambil->close();

if (!$nama_siswa) {
    $db->close();
    header('Location: ../../../Pembayaran_Masuk.html?error=Siswa+tidak+ditemukan');
    exit;
}

// 1. Simpan transaksi
$id_tugg_val = $id_tunggakan ?: null;
$stmt = $db->prepare("INSERT INTO transaksi (id_siswa, id_tunggakan, nama_siswa, jml_bayar, tgl_transaksi)
                      VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("iisds", $id_siswa, $id_tugg_val, $nama_siswa, $jml_bayar, $tgl_transaksi);
$stmt->execute();
$id_transaksi = $db->insert_id;
$stmt->close();

// 2. Update tunggakan → kurangi jml_tunggakan
if ($id_tunggakan) {
    $stmt2 = $db->prepare("UPDATE tunggakan SET jml_tunggakan = GREATEST(jml_tunggakan - ?, 0) WHERE id_tunggakan=?");
    $stmt2->bind_param("di", $jml_bayar, $id_tunggakan);
    $stmt2->execute();
    $stmt2->close();
}

// 3. Buat laporan pembayaran otomatis
$stmt3 = $db->prepare("INSERT INTO laporan_pembayaran (id_transaksi, id_siswa, jml_bayar, tgl_transaksi, nama_siswa)
                       VALUES (?, ?, ?, ?, ?)");
$stmt3->bind_param("iidss", $id_transaksi, $id_siswa, $jml_bayar, $tgl_transaksi, $nama_siswa);
$stmt3->execute();
$stmt3->close();

$db->close();
header('Location: ../../../Pembayaran_Masuk.html?sukses=Transaksi+berhasil+disimpan');
exit;
