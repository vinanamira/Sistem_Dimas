<?php
/**
 * Daftar tagihan (SPP + kegiatan) untuk satu siswa — dipakai admin di modal Pembayaran Masuk.
 * Menyamakan tampilan tagihan siswa agar admin tahu jenis & total yang harus dibayar.
 */
require_once '../config/database.php';
require_once '../helpers/session.php';
require_once '../helpers/auto_spp.php';

requireRoleJson('bendahara');

header('Content-Type: application/json; charset=utf-8');

$id_siswa = (int) ($_GET['id_siswa'] ?? 0);
if (!$id_siswa) {
    echo json_encode(['success' => false, 'error' => 'ID siswa tidak valid']);
    exit;
}

$db = getDB();

$stmt = $db->prepare('SELECT id_siswa, nama_siswa, kelas FROM siswa WHERE id_siswa = ?');
$stmt->bind_param('i', $id_siswa);
$stmt->execute();
$sis = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$sis) {
    $db->close();
    echo json_encode(['success' => false, 'error' => 'Siswa tidak ditemukan']);
    exit;
}

// Samakan dengan tampilan siswa: pastikan SPP sudah ter-generate
autoGenerateSPP($db, $id_siswa, $sis['nama_siswa'], $sis['kelas']);

$tagihan = [];
$total   = 0.0;

// --- SPP (tunggakan yang belum lunas) ---
$qt = $db->prepare(
    'SELECT id_tunggakan, jml_tunggakan, jumlah_tagihan_awal, periode_tagihan, tgl_jatuh_tempo
     FROM tunggakan WHERE id_siswa = ? AND jml_tunggakan > 0 ORDER BY tgl_jatuh_tempo ASC'
);
$qt->bind_param('i', $id_siswa);
$qt->execute();
$rt = $qt->get_result();
while ($row = $rt->fetch_assoc()) {
    $sisa = (float) $row['jml_tunggakan'];
    $tagihan[] = [
        'id'          => 'spp_' . $row['id_tunggakan'],
        'jenis'       => 'spp',
        'label'       => $row['periode_tagihan'] ?: 'SPP',
        'sisa'        => $sisa,
        'awal'        => (float) ($row['jumlah_tagihan_awal'] ?: $sisa),
        'jatuh_tempo' => $row['tgl_jatuh_tempo'],
    ];
    $total += $sisa;
}
$qt->close();

// --- Kegiatan khusus (belum lunas) ---
$qk = $db->prepare(
    'SELECT id_tagihan_keg, nama_kegiatan, jumlah, sisa_tagihan
     FROM tagihan_kegiatan WHERE id_siswa = ? AND status != \'lunas\' AND sisa_tagihan > 0
     ORDER BY id_tagihan_keg ASC'
);
$qk->bind_param('i', $id_siswa);
$qk->execute();
$rk = $qk->get_result();
while ($row = $rk->fetch_assoc()) {
    $sisa = (float) $row['sisa_tagihan'];
    $tagihan[] = [
        'id'    => 'kegiatan_' . $row['id_tagihan_keg'],
        'jenis' => 'kegiatan',
        'label' => 'Kegiatan: ' . $row['nama_kegiatan'],
        'sisa'  => $sisa,
        'awal'  => (float) $row['jumlah'],
    ];
    $total += $sisa;
}
$qk->close();

$db->close();

echo json_encode([
    'success'       => true,
    'siswa'         => $sis,
    'total_tagihan' => $total,
    'tagihan'       => $tagihan,
]);
