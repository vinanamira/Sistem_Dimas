<?php
// data/get_transaksi.php
// Ambil semua transaksi pembayaran

require_once '../config/database.php';
require_once '../helpers/session.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Belum login']);
    exit;
}

$db    = getDB();
$query = "SELECT tr.id_transaksi, tr.nama_siswa, tr.jml_bayar, tr.tgl_transaksi,
                 s.kelas, s.nis
          FROM transaksi tr
          JOIN siswa s ON tr.id_siswa = s.id_siswa
          ORDER BY tr.tgl_transaksi DESC";
$result = $db->query($query);
$data   = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}
$db->close();

header('Content-Type: application/json');
echo json_encode($data);
