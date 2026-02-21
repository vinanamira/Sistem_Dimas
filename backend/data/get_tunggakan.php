<?php
// data/get_tunggakan.php
// Ambil semua data tunggakan (untuk halaman Tagihan Bulanan)

require_once '../config/database.php';
require_once '../helpers/session.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Belum login']);
    exit;
}

$db   = getDB();
$query = "SELECT t.id_tunggakan, t.id_siswa, t.nama_siswa, t.jml_tunggakan,
                 s.kelas, s.nis
          FROM tunggakan t
          JOIN siswa s ON t.id_siswa = s.id_siswa
          ORDER BY t.jml_tunggakan DESC";
$result = $db->query($query);
$data   = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}
$db->close();

header('Content-Type: application/json');
echo json_encode($data);
