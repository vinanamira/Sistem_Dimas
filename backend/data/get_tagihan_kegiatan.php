<?php
require_once '../config/database.php';
require_once '../helpers/session.php';

requireRoleJson('siswa');

header('Content-Type: application/json; charset=utf-8');

$user = getLoggedUser();
$db   = getDB();

$stmt = $db->prepare(
    'SELECT id_siswa FROM siswa WHERE id_user = ? LIMIT 1'
);
$stmt->bind_param('i', $user['id_user']);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row) {
    $db->close();
    echo json_encode(['success' => false, 'error' => 'Profil siswa tidak ditemukan']);
    exit;
}

$id_siswa = (int) $row['id_siswa'];

$q = $db->prepare(
    'SELECT id_tagihan_keg, nama_kegiatan, kelas_label, jumlah, sisa_tagihan, status, tgl_bayar, id_transaksi
     FROM tagihan_kegiatan
     WHERE id_siswa = ?
     ORDER BY id_tagihan_keg ASC'
);
$q->bind_param('i', $id_siswa);
$q->execute();
$result = $q->get_result();
$data   = [];
while ($r = $result->fetch_assoc()) {
    $data[] = $r;
}
$q->close();
$db->close();

echo json_encode(['success' => true, 'data' => $data]);
