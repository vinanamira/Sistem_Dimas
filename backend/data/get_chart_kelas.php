<?php
require_once '../config/database.php';
require_once '../helpers/session.php';

requireRoleJson(['bendahara', 'kepala']);

header('Content-Type: application/json; charset=utf-8');

$db = getDB();

$result = $db->query(
    "SELECT s.kelas,
            SUM(CASE WHEN IFNULL(t.jml_tunggakan,0) = 0 THEN 1 ELSE 0 END) AS lunas,
            COUNT(*) AS total
     FROM siswa s
     LEFT JOIN tunggakan t ON s.id_siswa = t.id_siswa
     GROUP BY s.kelas
     ORDER BY s.kelas ASC"
);

$labels = [];
$lunas  = [];
while ($row = $result->fetch_assoc()) {
    $labels[] = $row['kelas'];
    $lunas[]   = (int) $row['lunas'];
}

$db->close();

echo json_encode([
    'success' => true,
    'labels'  => $labels,
    'data'    => $lunas,
]);
