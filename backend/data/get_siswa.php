<?php
// data/get_siswa.php
// Mengembalikan daftar semua siswa dari database
// Bisa digunakan oleh fetch() / AJAX dari frontend

require_once '../config/database.php';
require_once '../helpers/session.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Belum login']);
    exit;
}

$db    = getDB();
$query = "SELECT s.id_siswa, s.nama_siswa, s.nis, s.kelas,
                 u.username,
                 IFNULL(t.jml_tunggakan, 0) AS jml_tunggakan
          FROM siswa s
          JOIN users u ON s.id_user = u.id_user
          LEFT JOIN tunggakan t ON s.id_siswa = t.id_siswa
          ORDER BY s.nama_siswa ASC";
$result = $db->query($query);
$data   = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}
$db->close();

header('Content-Type: application/json');
echo json_encode($data);
