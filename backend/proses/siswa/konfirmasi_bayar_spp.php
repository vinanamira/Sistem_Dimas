<?php
/**
 * Siswa konfirmasi transfer SPP → simpan sebagai pembayaran PENDING.
 * Tunggakan TIDAK langsung berkurang; menunggu bendahara memverifikasi
 * bukti transfer (lihat backend/proses/transaksi/approve_pembayaran.php).
 */
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/upload_helper.php';
requireRole('siswa');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Metode tidak valid', [], 405);
}

$id_tunggakan = (int) ($_POST['id_tunggakan'] ?? 0);
$jml_input    = floatval($_POST['jml_bayar'] ?? 0);
$tgl          = trim($_POST['tgl_transaksi'] ?? date('Y-m-d'));

$user = getLoggedUser();
$db   = getDB();

$stmt = $db->prepare('SELECT id_siswa, nama_siswa FROM siswa WHERE id_user = ?');
$stmt->bind_param('i', $user['id_user']);
$stmt->execute();
$res = $stmt->get_result();
$srow = $res->fetch_assoc();
$stmt->close();

if (!$srow) {
    $db->close();
    jsonResponse(false, 'Profil tidak ditemukan', [], 404);
}
$id_siswa   = (int) $srow['id_siswa'];
$nama_siswa = $srow['nama_siswa'];

if (!$id_tunggakan) {
    $st2 = $db->prepare('SELECT id_tunggakan, jml_tunggakan FROM tunggakan WHERE id_siswa = ? LIMIT 1');
    $st2->bind_param('i', $id_siswa);
    $st2->execute();
    $r2 = $st2->get_result()->fetch_assoc();
    $st2->close();
    if (!$r2) {
        $db->close();
        jsonResponse(false, 'Tidak ada tagihan SPP', [], 404);
    }
    $id_tunggakan = (int) $r2['id_tunggakan'];
    $jml_max      = (float) $r2['jml_tunggakan'];
} else {
    $st2 = $db->prepare('SELECT jml_tunggakan FROM tunggakan WHERE id_tunggakan = ? AND id_siswa = ?');
    $st2->bind_param('ii', $id_tunggakan, $id_siswa);
    $st2->execute();
    $r2 = $st2->get_result()->fetch_assoc();
    $st2->close();
    if (!$r2) {
        $db->close();
        jsonResponse(false, 'Tagihan tidak valid', [], 404);
    }
    $jml_max = (float) $r2['jml_tunggakan'];
}

if ($jml_max <= 0) {
    $db->close();
    jsonResponse(false, 'SPP sudah lunas', [], 400);
}

if ($jml_input > $jml_max) {
    $db->close();
    jsonResponse(false, 'Nominal pembayaran tidak boleh lebih besar dari total tagihan', [], 400);
}

$jml_bayar = $jml_input > 0 ? $jml_input : $jml_max;

// Cegah pengajuan ganda untuk tagihan yang sama selama masih menunggu verifikasi
$cek = $db->prepare("SELECT id_pending FROM pembayaran_pending WHERE id_tunggakan = ? AND status = 'pending' LIMIT 1");
$cek->bind_param('i', $id_tunggakan);
$cek->execute();
$adaPending = $cek->get_result()->fetch_assoc();
$cek->close();
if ($adaPending) {
    $db->close();
    jsonResponse(false, 'Masih ada pembayaran yang menunggu verifikasi bendahara untuk tagihan ini', [], 409);
}

// Bukti transfer wajib supaya bendahara bisa memverifikasi
if (!isset($_FILES['bukti_transfer']) || $_FILES['bukti_transfer']['error'] === UPLOAD_ERR_NO_FILE) {
    $db->close();
    jsonResponse(false, 'Bukti transfer wajib diunggah', [], 400);
}
try {
    $bukti     = readBuktiUpload($_FILES['bukti_transfer']);
    $buktiData = $bukti['data'];
    $buktiMime = $bukti['mime'];
} catch (Exception $e) {
    $db->close();
    jsonResponse(false, $e->getMessage(), [], 400);
}

$keterangan = 'Pembayaran SPP';
$stmt = $db->prepare(
    "INSERT INTO pembayaran_pending
       (id_siswa, nama_siswa, jenis, id_tunggakan, jml_bayar, tgl_transaksi, keterangan, bukti_blob, bukti_mime)
     VALUES (?, ?, 'spp', ?, ?, ?, ?, ?, ?)"
);
$stmt->bind_param('isidssss', $id_siswa, $nama_siswa, $id_tunggakan, $jml_bayar, $tgl, $keterangan, $buktiData, $buktiMime);
try {
    $stmt->execute();
} catch (Throwable $e) {
    $stmt->close();
    $db->close();
    jsonResponse(false, 'Gagal menyimpan pembayaran', [], 500);
}
$stmt->close();
$db->close();
jsonResponse(true, 'Pembayaran dikirim & menunggu verifikasi bendahara');
