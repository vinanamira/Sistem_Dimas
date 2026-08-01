<?php
/**
 * Bendahara memverifikasi pembayaran transfer siswa (pending).
 *
 *   aksi=setujui  → catat ke transaksi + laporan_pembayaran, kurangi
 *                   tunggakan / update tagihan kegiatan (via simpanTransaksiPembayaran).
 *   aksi=tolak    → tandai ditolak, tidak ada perubahan tagihan.
 *
 * Hanya pembayaran berstatus 'pending' yang bisa diproses.
 */
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/transaksi_helper.php';
requireRoleJson('bendahara');

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Metode tidak valid', [], 405);
}

$id_pending = (int) ($_POST['id_pending'] ?? 0);
$aksi       = trim($_POST['aksi'] ?? '');
$catatan    = trim($_POST['catatan'] ?? '');

if (!$id_pending || !in_array($aksi, ['setujui', 'tolak'], true)) {
    jsonResponse(false, 'Data tidak valid', [], 400);
}

$db = getDB();

$st = $db->prepare(
    "SELECT id_siswa, jenis, id_tunggakan, id_tagihan_keg, jml_bayar, tgl_transaksi, keterangan,
            bukti_blob, bukti_mime, status
     FROM pembayaran_pending WHERE id_pending = ?"
);
$st->bind_param('i', $id_pending);
$st->execute();
$p = $st->get_result()->fetch_assoc();
$st->close();

if (!$p) {
    $db->close();
    jsonResponse(false, 'Pembayaran tidak ditemukan', [], 404);
}
if ($p['status'] !== 'pending') {
    $db->close();
    jsonResponse(false, 'Pembayaran sudah diproses sebelumnya', [], 409);
}

// ---------- TOLAK ----------
if ($aksi === 'tolak') {
    $up = $db->prepare("UPDATE pembayaran_pending SET status = 'ditolak', catatan_bendahara = ?, tgl_verifikasi = NOW() WHERE id_pending = ?");
    $up->bind_param('si', $catatan, $id_pending);
    $up->execute();
    $up->close();
    $db->close();
    jsonResponse(true, 'Pembayaran ditolak');
}

// ---------- SETUJUI ----------
$id_siswa  = (int) $p['id_siswa'];
$jml_bayar = (float) $p['jml_bayar'];
$tgl       = $p['tgl_transaksi'];
$ket       = $p['keterangan'];
$buktiData = $p['bukti_blob'];
$buktiMime = $p['bukti_mime'];

try {
    $db->begin_transaction();

    if ($p['jenis'] === 'kegiatan') {
        $id_tagihan_keg = (int) $p['id_tagihan_keg'];
        $stk = $db->prepare('SELECT status, jumlah, sisa_tagihan FROM tagihan_kegiatan WHERE id_tagihan_keg = ? AND id_siswa = ? FOR UPDATE');
        $stk->bind_param('ii', $id_tagihan_keg, $id_siswa);
        $stk->execute();
        $keg = $stk->get_result()->fetch_assoc();
        $stk->close();

        if (!$keg) {
            throw new RuntimeException('Tagihan kegiatan tidak ditemukan');
        }
        if ($keg['status'] === 'lunas') {
            throw new RuntimeException('Tagihan kegiatan sudah lunas');
        }
        $sisa = isset($keg['sisa_tagihan']) ? (float) $keg['sisa_tagihan'] : (float) $keg['jumlah'];
        if ($jml_bayar > $sisa) {
            throw new RuntimeException('Nominal melebihi sisa tagihan saat ini (tagihan mungkin sudah berubah)');
        }

        $sisa_baru   = $sisa - $jml_bayar;
        $status_baru = $sisa_baru <= 0 ? 'lunas' : 'cicilan';

        $id_trx = simpanTransaksiPembayaran($db, $id_siswa, null, $jml_bayar, $tgl, $ket, 'kegiatan', $buktiData, $buktiMime);

        $upTrx = $db->prepare('UPDATE transaksi SET id_tagihan_keg = ? WHERE id_transaksi = ?');
        $upTrx->bind_param('ii', $id_tagihan_keg, $id_trx);
        $upTrx->execute();
        $upTrx->close();

        $up = $db->prepare('UPDATE tagihan_kegiatan SET status = ?, tgl_bayar = ?, sisa_tagihan = ? WHERE id_tagihan_keg = ? AND id_siswa = ?');
        $up->bind_param('ssdii', $status_baru, $tgl, $sisa_baru, $id_tagihan_keg, $id_siswa);
        $up->execute();
        $up->close();
    } else {
        $id_tunggakan = (int) $p['id_tunggakan'];
        $stt = $db->prepare('SELECT jml_tunggakan FROM tunggakan WHERE id_tunggakan = ? AND id_siswa = ? FOR UPDATE');
        $stt->bind_param('ii', $id_tunggakan, $id_siswa);
        $stt->execute();
        $tun = $stt->get_result()->fetch_assoc();
        $stt->close();

        if (!$tun) {
            throw new RuntimeException('Tagihan SPP tidak ditemukan');
        }
        $sisa = (float) $tun['jml_tunggakan'];
        if ($sisa <= 0) {
            throw new RuntimeException('Tagihan SPP sudah lunas');
        }
        if ($jml_bayar > $sisa) {
            throw new RuntimeException('Nominal melebihi sisa tagihan saat ini (tagihan mungkin sudah berubah)');
        }

        simpanTransaksiPembayaran($db, $id_siswa, $id_tunggakan ?: null, $jml_bayar, $tgl, $ket, 'spp', $buktiData, $buktiMime);
    }

    $up = $db->prepare("UPDATE pembayaran_pending SET status = 'disetujui', catatan_bendahara = ?, tgl_verifikasi = NOW() WHERE id_pending = ?");
    $up->bind_param('si', $catatan, $id_pending);
    $up->execute();
    $up->close();

    $db->commit();
} catch (Throwable $e) {
    $db->rollback();
    $db->close();
    jsonResponse(false, $e->getMessage(), [], 400);
}

$db->close();
jsonResponse(true, 'Pembayaran disetujui & dicatat');
