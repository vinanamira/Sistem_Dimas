<?php
require_once '../../config/database.php';
require_once '../../helpers/session.php';
requireRole('bendahara');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method tidak diizinkan']);
    exit;
}

$nama_siswa        = trim($_POST['nama_siswa'] ?? '');
$kelas             = trim($_POST['kelas'] ?? '');
$periode_tagihan   = trim($_POST['periode_tagihan'] ?? '');
$jenis_tagihan     = trim($_POST['jenis_tagihan'] ?? 'spp');
$jumlah_tagihan    = floatval($_POST['jumlah_tagihan'] ?? 0);
$tgl_jatuh_tempo   = trim($_POST['tgl_jatuh_tempo'] ?? '');

if ($nama_siswa === '' || $kelas === '' || $periode_tagihan === '' || $jumlah_tagihan <= 0) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Lengkapi semua data dengan benar']);
    exit;
}

if ($tgl_jatuh_tempo === '') {
    $tgl_jatuh_tempo = date('Y-m-d', strtotime('+30 days'));
}

$db = getDB();

// Cek apakah siswa sudah ada berdasarkan nama_siswa atau nis (karena field di frontend bisa jadi NIS)
$cek_s = $db->prepare('SELECT id_siswa, nama_siswa FROM siswa WHERE nama_siswa = ? OR nis = ? LIMIT 1');
$cek_s->bind_param('ss', $nama_siswa, $nama_siswa);
$cek_s->execute();
$res_s = $cek_s->get_result();
if ($row_s = $res_s->fetch_assoc()) {
    $id_siswa = (int) $row_s['id_siswa'];
    $nama_siswa = $row_s['nama_siswa']; // gunakan nama asli di DB
}
$cek_s->close();

if (!isset($id_siswa)) {
    // Jika tidak ada, buat siswa baru
    $nis      = 'GEN' . substr((string) time(), -8);
    $username = 'siswa_' . $nis;
    $hash     = password_hash('siswa123', PASSWORD_BCRYPT);
    
    $cek = $db->prepare('SELECT id_user FROM users WHERE username=?');
    $cek->bind_param('s', $username);
    $cek->execute();
    $cek->store_result();
    if ($cek->num_rows > 0) {
        $username = 'siswa_' . $nis . '_' . random_int(100, 999);
    }
    $cek->close();
    
    $stmt_user = $db->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'siswa')");
    $stmt_user->bind_param('ss', $username, $hash);
    $stmt_user->execute();
    $id_user = (int) $db->insert_id;
    $stmt_user->close();
    
    $stmt_siswa = $db->prepare('INSERT INTO siswa (id_user, nama_siswa, nis, kelas, email) VALUES (?, ?, ?, ?, NULL)');
    $stmt_siswa->bind_param('isss', $id_user, $nama_siswa, $nis, $kelas);
    $stmt_siswa->execute();
    $id_siswa = (int) $db->insert_id;
    $stmt_siswa->close();
}

if ($jenis_tagihan === 'kegiatan') {
    $stmt_t = $db->prepare(
        'INSERT INTO tagihan_kegiatan (id_siswa, nama_kegiatan, jenis_tagihan, jumlah, sisa_tagihan, status, kelas_label)
         VALUES (?, ?, ?, ?, ?, "belum_lunas", ?)'
    );
    if (!$stmt_t) {
        $db->close();
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Error persiapan query tagihan kegiatan']);
        exit;
    }
    $stmt_t->bind_param('issdds', $id_siswa, $periode_tagihan, $jenis_tagihan, $jumlah_tagihan, $jumlah_tagihan, $kelas);
} else {
    $stmt_t = $db->prepare(
        'INSERT INTO tunggakan (id_siswa, nama_siswa, jml_tunggakan, jumlah_tagihan_awal, jenis_tagihan, periode_tagihan, tgl_jatuh_tempo)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    if (!$stmt_t) {
        $db->close();
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Error persiapan query tunggakan']);
        exit;
    }
    $stmt_t->bind_param('isddsss', $id_siswa, $nama_siswa, $jumlah_tagihan, $jumlah_tagihan, $jenis_tagihan, $periode_tagihan, $tgl_jatuh_tempo);
}

if (!$stmt_t->execute()) {
    $stmt_t->close();
    $db->close();
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Gagal menyimpan tagihan: ' . $stmt_t->error]);
    exit;
}
$stmt_t->close();

$db->close();

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['success' => true, 'message' => 'Siswa dan tagihan berhasil tersimpan']);
exit;

