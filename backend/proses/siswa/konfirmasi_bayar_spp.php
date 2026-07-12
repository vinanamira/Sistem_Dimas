<?php
/**
 * Siswa konfirmasi transfer SPP → catat transaksi (tampil di Pembayaran Masuk admin).
 */
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/transaksi_helper.php';
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
    simpanTransaksiPembayaran($db, $id_siswa, $id_tunggakan, $jml_bayar, $tgl, 'Pembayaran SPP', 'spp', $buktiData, $buktiMime);
} catch (Throwable $e) {
    $db->close();
    jsonResponse(false, $e->getMessage(), [], 400);
}
$db->close();
jsonResponse(true, 'Pembayaran SPP berhasil dikonfirmasi');
