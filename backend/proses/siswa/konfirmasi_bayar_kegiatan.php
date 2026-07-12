<?php
/**
 * Siswa konfirmasi transfer kegiatan khusus → transaksi + update tagihan_kegiatan.
 */
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/transaksi_helper.php';
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

$stmt = $db->prepare('SELECT id_siswa FROM siswa WHERE id_user = ?');
$stmt->bind_param('i', $user['id_user']);
$stmt->execute();
$res = $stmt->get_result();
$srow = $res->fetch_assoc();
$stmt->close();

if (!$srow) {
    $db->close();
    jsonResponse(false, 'Profil tidak ditemukan', [], 404);
}
$id_siswa = (int) $srow['id_siswa'];

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
if ($jml_input <= 0) {
    $jml_bayar = $sisa_tagihan_sekarang; // default bayar semua sisa
} else {
    $jml_bayar = $jml_input;
}

if ($jml_bayar > $sisa_tagihan_sekarang) {
    jsonResponse(false, 'Nominal pembayaran tidak boleh lebih besar dari total tagihan', [], 400);
}

$sisa_baru = $sisa_tagihan_sekarang - $jml_bayar;
$status_baru = $sisa_baru <= 0 ? 'lunas' : 'cicilan';

$buktiData = null;
$buktiMime = null;
if (isset($_FILES['bukti_transfer']) && $_FILES['bukti_transfer']['error'] !== UPLOAD_ERR_NO_FILE) {
    try {
        $bukti     = readBuktiUpload($_FILES['bukti_transfer']);
        $buktiData = $bukti['data'];
        $buktiMime = $bukti['mime'];
    } catch (Exception $e) {
        $db->close();
        jsonResponse(false, $e->getMessage(), [], 400);
    }
}

try {
    $db->begin_transaction();
    $id_trx = simpanTransaksiPembayaran($db, $id_siswa, null, $jml_bayar, $tgl, $ket, 'kegiatan', $buktiData, $buktiMime);
    
    // update fk tagihan kegiatan inside transaksi
    $upTrx = $db->prepare('UPDATE transaksi SET id_tagihan_keg = ? WHERE id_transaksi = ?');
    $upTrx->bind_param('ii', $id_tagihan_keg, $id_trx);
    $upTrx->execute();
    $upTrx->close();

    $up = $db->prepare(
        'UPDATE tagihan_kegiatan SET status = ?, tgl_bayar = ?, sisa_tagihan = ? WHERE id_tagihan_keg = ? AND id_siswa = ?'
    );
    $up->bind_param('ssdii', $status_baru, $tgl, $sisa_baru, $id_tagihan_keg, $id_siswa);
    $up->execute();
    $up->close();
    $db->commit();
} catch (Throwable $e) {
    $db->rollback();
    $db->close();
    jsonResponse(false, 'Gagal menyimpan: ' . $e->getMessage(), [], 500);
}
$db->close();
jsonResponse(true, 'Pembayaran kegiatan dicatat');
