<?php
// data/get_laporan_keuangan.php
// Ambil semua data laporan keuangan (Kepala & Bendahara)

require_once '../config/database.php';
require_once '../helpers/session.php';
requireRole(['bendahara','kepala']);

$db     = getDB();
$result = $db->query("SELECT * FROM laporan_keuangan ORDER BY tgl_uang DESC");
$data   = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}
$db->close();

header('Content-Type: application/json');
echo json_encode($data);
