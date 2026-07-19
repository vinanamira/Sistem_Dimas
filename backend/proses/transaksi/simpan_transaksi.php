<?php
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/transaksi_helper.php';
requireRoleJson('bendahara');

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Metode tidak valid', [], 405);
}

$id_siswa       = (int) ($_POST['id_siswa'] ?? 0);
$id_tunggakan   = (int) ($_POST['id_tunggakan'] ?? 0);
$id_tagihan_keg = (int) ($_POST['id_tagihan_keg'] ?? 0);
$jml_bayar      = floatval($_POST['jml_bayar'] ?? 0);
$tgl_transaksi  = trim($_POST['tgl_transaksi'] ?? date('Y-m-d'));
$keterangan     = trim($_POST['keterangan'] ?? '');

if (!$id_siswa || $jml_bayar <= 0) {
    jsonResponse(false, 'Data tidak valid', [], 400);
}

$db = getDB();

// ---------- Pembayaran Kegiatan ----------
if ($id_tagihan_keg > 0) {
    $st = $db->prepare('SELECT nama_kegiatan, jumlah, status, sisa_tagihan FROM tagihan_kegiatan WHERE id_tagihan_keg = ? AND id_siswa = ?');
    $st->bind_param('ii', $id_tagihan_keg, $id_siswa);
    $st->execute();
    $keg = $st->get_result()->fetch_assoc();
    $st->close();

    if (!$keg) {
        $db->close();
        jsonResponse(false, 'Tagihan kegiatan tidak ditemukan', [], 404);
    }
    if ($keg['status'] === 'lunas') {
        $db->close();
        jsonResponse(false, 'Tagihan kegiatan sudah lunas', [], 400);
    }

    $sisa = isset($keg['sisa_tagihan']) ? (float) $keg['sisa_tagihan'] : (float) $keg['jumlah'];
    if ($jml_bayar > $sisa) {
        $db->close();
        jsonResponse(false, 'Nominal melebihi sisa tagihan', [], 400);
    }

    $sisa_baru   = $sisa - $jml_bayar;
    $status_baru = $sisa_baru <= 0 ? 'lunas' : 'cicilan';
    $ket         = $keterangan !== '' ? $keterangan : 'Kegiatan: ' . $keg['nama_kegiatan'];

    try {
        $db->begin_transaction();
        $id_trx = simpanTransaksiPembayaran($db, $id_siswa, null, $jml_bayar, $tgl_transaksi, $ket, 'kegiatan');

        $upTrx = $db->prepare('UPDATE transaksi SET id_tagihan_keg = ? WHERE id_transaksi = ?');
        $upTrx->bind_param('ii', $id_tagihan_keg, $id_trx);
        $upTrx->execute();
        $upTrx->close();

        $up = $db->prepare('UPDATE tagihan_kegiatan SET status = ?, tgl_bayar = ?, sisa_tagihan = ? WHERE id_tagihan_keg = ? AND id_siswa = ?');
        $up->bind_param('ssdii', $status_baru, $tgl_transaksi, $sisa_baru, $id_tagihan_keg, $id_siswa);
        $up->execute();
        $up->close();

        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        $db->close();
        jsonResponse(false, 'Gagal menyimpan transaksi', [], 500);
    }
    $db->close();
    jsonResponse(true, 'Pembayaran kegiatan berhasil disimpan');
}

// ---------- Pembayaran SPP (tunggakan) ----------
if ($id_tunggakan > 0) {
    $st = $db->prepare('SELECT jml_tunggakan, periode_tagihan FROM tunggakan WHERE id_tunggakan = ? AND id_siswa = ?');
    $st->bind_param('ii', $id_tunggakan, $id_siswa);
    $st->execute();
    $tun = $st->get_result()->fetch_assoc();
    $st->close();

    if (!$tun) {
        $db->close();
        jsonResponse(false, 'Tagihan SPP tidak ditemukan', [], 404);
    }
    $sisa = (float) $tun['jml_tunggakan'];
    if ($sisa <= 0) {
        $db->close();
        jsonResponse(false, 'Tagihan SPP sudah lunas', [], 400);
    }
    if ($jml_bayar > $sisa) {
        $db->close();
        jsonResponse(false, 'Nominal melebihi sisa tagihan', [], 400);
    }
    if ($keterangan === '') {
        $keterangan = $tun['periode_tagihan'] ? 'Pembayaran ' . $tun['periode_tagihan'] : 'Pembayaran SPP';
    }
}

// ---------- Pembayaran umum / tanpa tagihan spesifik ----------
if ($keterangan === '') {
    $keterangan = 'Pembayaran Masuk';
}

try {
    simpanTransaksiPembayaran($db, $id_siswa, $id_tunggakan ?: null, $jml_bayar, $tgl_transaksi, $keterangan);
} catch (Throwable $e) {
    $db->close();
    jsonResponse(false, 'Gagal menyimpan transaksi', [], 500);
}
$db->close();
jsonResponse(true, 'Transaksi berhasil disimpan');
