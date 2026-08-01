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
    "SELECT tk.id_tagihan_keg, tk.nama_kegiatan, tk.kelas_label, tk.jumlah, tk.sisa_tagihan,
            tk.status, tk.tgl_bayar, tk.id_transaksi,
            (SELECT COUNT(*) FROM pembayaran_pending pp
             WHERE pp.id_tagihan_keg = tk.id_tagihan_keg AND pp.status = 'pending') AS pending
     FROM tagihan_kegiatan tk
     WHERE tk.id_siswa = ?
     ORDER BY tk.id_tagihan_keg ASC"
);
$q->bind_param('i', $id_siswa);
$q->execute();
$result = $q->get_result();
$data   = [];
while ($r = $result->fetch_assoc()) {
    $r['pending'] = (int) $r['pending'] > 0;
    $data[] = $r;
}
$q->close();
$db->close();

echo json_encode(['success' => true, 'data' => $data]);
