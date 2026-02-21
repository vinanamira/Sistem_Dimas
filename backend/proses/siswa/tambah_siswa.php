<?php
// proses/siswa/tambah_siswa.php
// Proses tambah data siswa baru (Bendahara only)

require_once '../../config/database.php';
require_once '../../helpers/session.php';
requireRole('bendahara');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../../Data_Siswa.html');
    exit;
}

$nama_siswa = trim($_POST['nama_siswa'] ?? '');
$nis        = trim($_POST['nis'] ?? '');
$kelas      = trim($_POST['kelas'] ?? '');
$username   = trim($_POST['username'] ?? '');
$password   = trim($_POST['password'] ?? '');

if (empty($nama_siswa) || empty($nis) || empty($kelas) || empty($username) || empty($password)) {
    header('Location: ../../../Data_Siswa.html?error=Semua+field+wajib+diisi');
    exit;
}

$db = getDB();

// Cek NIS duplikat
$cek = $db->prepare("SELECT id_siswa FROM siswa WHERE nis = ?");
$cek->bind_param("s", $nis);
$cek->execute();
$cek->store_result();
if ($cek->num_rows > 0) {
    $cek->close();
    $db->close();
    header('Location: ../../../Data_Siswa.html?error=NIS+sudah+terdaftar');
    exit;
}
$cek->close();

// Buat user terlebih dahulu
$hash = password_hash($password, PASSWORD_BCRYPT);
$stmt_user = $db->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'siswa')");
$stmt_user->bind_param("ss", $username, $hash);
$stmt_user->execute();
$id_user = $db->insert_id;
$stmt_user->close();

// Buat data siswa
$stmt_siswa = $db->prepare("INSERT INTO siswa (id_user, nama_siswa, nis, kelas) VALUES (?, ?, ?, ?)");
$stmt_siswa->bind_param("isss", $id_user, $nama_siswa, $nis, $kelas);
$stmt_siswa->execute();
$id_siswa = $db->insert_id;
$stmt_siswa->close();

// Buat record tunggakan awal = 0
$stmt_tngg = $db->prepare("INSERT INTO tunggakan (id_siswa, nama_siswa, jml_tunggakan) VALUES (?, ?, 0)");
$stmt_tngg->bind_param("is", $id_siswa, $nama_siswa);
$stmt_tngg->execute();
$stmt_tngg->close();

$db->close();
header('Location: ../../../Data_Siswa.html?sukses=Siswa+berhasil+ditambahkan');
exit;
