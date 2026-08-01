<?php
/**
 * Siswa konfirmasi transfer kegiatan khusus → simpan sebagai pembayaran PENDING.
 * Tagihan kegiatan TIDAK langsung berubah; menunggu verifikasi bendahara.
 */
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/upload_helper.php';
requireRole('siswa');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Metode tidak valid', [], 405);
}

$id_tagihan_keg = (int) ($_POST['id_tagihan_keg'] ?? 0);
$tgl            = trim($_POST['tgl_transaksi'] ?? date('Y-m-d'));

if (!$id_tagihan_keg) {
    jsonResponse(false, 'Data tidak valid', [], 400);
}

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

$st = $db->prepare(
    'SELECT nama_kegiatan, jumlah, status, sisa_tagihan FROM tagihan_kegiatan WHERE id_tagihan_keg = ? AND id_siswa = ?'
);
$st->bind_param('ii', $id_tagihan_keg, $id_siswa);
$st->execute();
$row = $st->get_result()->fetch_assoc();
$st->close();

if (!$row) {
    $db->close();
    jsonResponse(false, 'Tagihan tidak ditemukan', [], 404);
}
if ($row['status'] === 'lunas') {
    $db->close();
    jsonResponse(false, 'Sudah lunas', [], 400);
}

$nama_keg = $row['nama_kegiatan'];
$jumlah_asli = (float) $row['jumlah'];
$sisa_tagihan_sekarang = isset($row['sisa_tagihan']) ? (float)$row['sisa_tagihan'] : $jumlah_asli;
$ket = 'Kegiatan: ' . $nama_keg;

$jml_input = (float) ($_POST['jml_bayar'] ?? 0);
$jml_bayar = $jml_input <= 0 ? $sisa_tagihan_sekarang : $jml_input;

if ($jml_bayar > $sisa_tagihan_sekarang) {
    $db->close();
    jsonResponse(false, 'Nominal pembayaran tidak boleh lebih besar dari total tagihan', [], 400);
}

// Cegah pengajuan ganda untuk kegiatan yang sama selama masih menunggu verifikasi
$cek = $db->prepare("SELECT id_pending FROM pembayaran_pending WHERE id_tagihan_keg = ? AND status = 'pending' LIMIT 1");
$cek->bind_param('i', $id_tagihan_keg);
$cek->execute();
$adaPending = $cek->get_result()->fetch_assoc();
$cek->close();
if ($adaPending) {
    $db->close();
    jsonResponse(false, 'Masih ada pembayaran yang menunggu verifikasi bendahara untuk kegiatan ini', [], 409);
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

$stmt = $db->prepare(
    "INSERT INTO pembayaran_pending
       (id_siswa, nama_siswa, jenis, id_tagihan_keg, jml_bayar, tgl_transaksi, keterangan, bukti_blob, bukti_mime)
     VALUES (?, ?, 'kegiatan', ?, ?, ?, ?, ?, ?)"
);
$stmt->bind_param('isidssss', $id_siswa, $nama_siswa, $id_tagihan_keg, $jml_bayar, $tgl, $ket, $buktiData, $buktiMime);
try {
    $stmt->execute();
} catch (Throwable $e) {
    $stmt->close();
    $db->close();
    jsonResponse(false, 'Gagal menyimpan pembayaran', [], 500);
}
$stmt->close();
$db->close();
jsonResponse(true, 'Pembayaran kegiatan dikirim & menunggu verifikasi bendahara');
