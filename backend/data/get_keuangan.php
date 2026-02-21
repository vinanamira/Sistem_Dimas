<?php
// data/get_keuangan.php
// Ambil data keuangan (pemasukan/pengeluaran)

require_once '../config/database.php';
require_once '../helpers/session.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Belum login']);
    exit;
}

$jenis  = $_GET['jenis'] ?? '';  // optional filter: 'pemasukan' atau 'pengeluaran'
$db     = getDB();

if (in_array($jenis, ['pemasukan','pengeluaran'])) {
    $stmt = $db->prepare("SELECT * FROM keuangan WHERE jenis_uang=? ORDER BY tgl_uang DESC");
    $stmt->bind_param("s", $jenis);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $db->query("SELECT * FROM keuangan ORDER BY tgl_uang DESC");
}

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}
$db->close();

header('Content-Type: application/json');
echo json_encode($data);
