<?php
// data/get_laporan_pembayaran.php
// Ambil semua laporan pembayaran (Riwayat Pembayaran)

require_once '../config/database.php';
require_once '../helpers/session.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Belum login']);
    exit;
}

// Jika siswa, hanya tampilkan miliknya sendiri
$user  = getLoggedUser();
$db    = getDB();

if ($user['role'] === 'siswa') {
    // Ambil id_siswa untuk user ini
    $cek = $db->prepare("SELECT id_siswa FROM siswa WHERE id_user=?");
    $cek->bind_param("i", $user['id_user']);
    $cek->execute();
    $cek->bind_result($id_siswa_login);
    $cek->fetch();
    $cek->close();

    $stmt = $db->prepare(
        "SELECT lp.id_lapbayar, lp.nama_siswa, lp.jml_bayar, lp.tgl_transaksi, s.kelas
         FROM laporan_pembayaran lp
         JOIN siswa s ON lp.id_siswa = s.id_siswa
         WHERE lp.id_siswa = ?
         ORDER BY lp.tgl_transaksi DESC"
    );
    $stmt->bind_param("i", $id_siswa_login);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    // Bendahara / Kepala bisa lihat semua
    $result = $db->query(
        "SELECT lp.id_lapbayar, lp.nama_siswa, lp.jml_bayar, lp.tgl_transaksi, s.kelas
         FROM laporan_pembayaran lp
         JOIN siswa s ON lp.id_siswa = s.id_siswa
         ORDER BY lp.tgl_transaksi DESC"
    );
}

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}
$db->close();

header('Content-Type: application/json');
echo json_encode($data);
