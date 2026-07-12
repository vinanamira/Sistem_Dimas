<?php
require_once '../config/database.php';
require_once '../helpers/session.php';

requireRoleJson(['bendahara', 'kepala']);

header('Content-Type: application/json; charset=utf-8');

$db = getDB();

$result = $db->query(
    "SELECT id_uang, tgl_uang, ket_uang, jml_uang, kategori, sumber_dana
     FROM keuangan
     WHERE jenis_uang = 'pemasukan'
     ORDER BY tgl_uang DESC"
);

$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}

$totalResult = $db->query("SELECT COALESCE(SUM(jml_uang),0) AS total FROM keuangan WHERE jenis_uang = 'pemasukan'");
$totalRow    = $totalResult->fetch_assoc();

$db->close();

echo json_encode([
    'success' => true,
    'data'    => $rows,
    'total'   => (float) $totalRow['total'],
]);
