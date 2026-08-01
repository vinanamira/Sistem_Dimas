<?php
/**
 * Daftar pembayaran transfer siswa yang menunggu verifikasi bendahara.
 */
require_once '../config/database.php';
require_once '../helpers/session.php';

requireRoleJson('bendahara');

header('Content-Type: application/json; charset=utf-8');

$db = getDB();

$query = "SELECT p.id_pending, p.id_siswa, p.nama_siswa, p.jenis,
                 p.id_tunggakan, p.id_tagihan_keg, p.jml_bayar, p.tgl_transaksi,
                 p.keterangan, p.tgl_pengajuan,
                 (p.bukti_blob IS NOT NULL) AS has_bukti,
                 s.kelas, s.nis
          FROM pembayaran_pending p
          JOIN siswa s ON p.id_siswa = s.id_siswa
          WHERE p.status = 'pending'
          ORDER BY p.tgl_pengajuan ASC";

$result = $db->query($query);
$data   = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
}
$db->close();

echo json_encode(['success' => true, 'data' => $data]);
