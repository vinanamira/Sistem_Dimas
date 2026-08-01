<?php
require_once '../config/database.php';
require_once '../helpers/session.php';

requireRoleJson('siswa');

header('Content-Type: application/json; charset=utf-8');

$db     = getDB();
$uid    = (int) getLoggedUser()['id_user'];
$stmt = $db->prepare('SELECT id_siswa, nama_siswa, kelas FROM siswa WHERE id_user = ?');
$stmt->bind_param('i', $uid);
$stmt->execute();
$sis = $stmt->get_result()->fetch_assoc();
$stmt->close();

$id_siswa = (int) ($sis['id_siswa'] ?? 0);
$total_tunggakan = 0.0;
$tunggakan_list = [];

$pending_tunggakan = [];
$ada_pending = false;
if ($id_siswa) {
    require_once '../helpers/auto_spp.php';
    autoGenerateSPP($db, $id_siswa, $sis['nama_siswa'], $sis['kelas']);

    // Set id_tunggakan yang punya pembayaran menunggu verifikasi
    $qp = $db->prepare("SELECT id_tunggakan FROM pembayaran_pending WHERE id_siswa = ? AND jenis = 'spp' AND status = 'pending' AND id_tunggakan IS NOT NULL");
    $qp->bind_param('i', $id_siswa);
    $qp->execute();
    $rp = $qp->get_result();
    while ($rowP = $rp->fetch_assoc()) {
        $pending_tunggakan[(int) $rowP['id_tunggakan']] = true;
    }
    $qp->close();

    $qt = $db->prepare('SELECT id_tunggakan, jml_tunggakan, jumlah_tagihan_awal, periode_tagihan, tgl_jatuh_tempo FROM tunggakan WHERE id_siswa = ? AND jml_tunggakan > 0 ORDER BY tgl_jatuh_tempo ASC');
    $qt->bind_param('i', $id_siswa);
    $qt->execute();
    $rt = $qt->get_result();
    while ($row = $rt->fetch_assoc()) {
        $row['pending'] = isset($pending_tunggakan[(int) $row['id_tunggakan']]);
        if ($row['pending']) {
            $ada_pending = true;
        }
        $tunggakan_list[] = $row;
        $total_tunggakan += (float) $row['jml_tunggakan'];
    }
    $qt->close();

    // Ambil juga tagihan kegiatan (DSP, dll)
    $qk = $db->prepare('SELECT id_tagihan_keg, sisa_tagihan FROM tagihan_kegiatan WHERE id_siswa = ? AND sisa_tagihan > 0');
    $qk->bind_param('i', $id_siswa);
    $qk->execute();
    $rk = $qk->get_result();
    while ($rowK = $rk->fetch_assoc()) {
        $total_tunggakan += (float) $rowK['sisa_tagihan'];
    }
    $qk->close();
}
$sis['jml_tunggakan'] = $total_tunggakan;
$sis['tunggakan_list'] = $tunggakan_list;

if (count($tunggakan_list) > 0) {
    $sis['periode_tagihan'] = $tunggakan_list[0]['periode_tagihan'];
    $sis['tgl_jatuh_tempo'] = $tunggakan_list[0]['tgl_jatuh_tempo'];
    $sis['id_tunggakan']    = $tunggakan_list[0]['id_tunggakan'];
} else {
    $sis['periode_tagihan'] = '';
    $sis['tgl_jatuh_tempo'] = null;
    $sis['id_tunggakan']    = null;
}

$total_bayar = 0.0;
if ($id_siswa) {
    $q = $db->prepare('SELECT IFNULL(SUM(jml_bayar),0) AS j FROM transaksi WHERE id_siswa=?');
    $q->bind_param('i', $id_siswa);
    $q->execute();
    $total_bayar = (float) $q->get_result()->fetch_assoc()['j'];
    $q->close();
}

$sisa   = (float) ($sis['jml_tunggakan'] ?? 0);
$total  = $total_bayar + $sisa;
$persen = $total > 0 ? round(($total_bayar / $total) * 100) : 0;

$byMonth = [];
if ($id_siswa) {
    $q2 = $db->prepare(
        'SELECT MONTH(tgl_transaksi) AS m, SUM(jml_bayar) AS j
         FROM transaksi WHERE id_siswa=? GROUP BY MONTH(tgl_transaksi) ORDER BY m'
    );
    $q2->bind_param('i', $id_siswa);
    $q2->execute();
    $r2 = $q2->get_result();
    while ($row = $r2->fetch_assoc()) {
        $byMonth[(int) $row['m']] = (float) $row['j'];
    }
    $q2->close();
}

$recent = [];
if ($id_siswa) {
    $q3 = $db->prepare(
        'SELECT DATE_FORMAT(tgl_transaksi, "%M") AS bulan, tgl_transaksi, jml_bayar
         FROM transaksi WHERE id_siswa=? ORDER BY tgl_transaksi DESC LIMIT 5'
    );
    $q3->bind_param('i', $id_siswa);
    $q3->execute();
    $r3 = $q3->get_result();
    while ($row = $r3->fetch_assoc()) {
        $recent[] = $row;
    }
    $q3->close();
}

$db->close();

echo json_encode([
    'success'       => true,
    'siswa'         => $sis,
    'ada_pending'   => $ada_pending,
    'total_tagihan' => $total,
    'sisa_tunggakan'=> $sisa,
    'total_bayar'   => $total_bayar,
    'progress_pct'  => $persen,
    'chart_months'  => $byMonth,
    'recent'        => $recent,
]);
