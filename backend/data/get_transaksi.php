<?php
require_once '../config/database.php';
require_once '../helpers/session.php';

requireRoleJson(['bendahara', 'kepala']);

header('Content-Type: application/json; charset=utf-8');

$db    = getDB();
$query = "SELECT tr.id_transaksi, tr.nama_siswa, tr.jml_bayar, tr.tgl_transaksi,
                 s.kelas, s.nis, tr.keterangan, tr.bukti_transfer,
                 (tr.bukti_blob IS NOT NULL) AS has_bukti
          FROM transaksi tr
          JOIN siswa s ON tr.id_siswa = s.id_siswa
          ORDER BY tr.tgl_transaksi DESC";
$result = $db->query($query);
$data   = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}
$db->close();

echo json_encode(['success' => true, 'data' => $data]);
