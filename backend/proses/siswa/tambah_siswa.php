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
$email      = trim($_POST['email'] ?? '');
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

$emailVal = $email === '' ? '' : $email;

// Buat data siswa
$stmt_siswa = $db->prepare('INSERT INTO siswa (id_user, nama_siswa, nis, kelas, email) VALUES (?, ?, ?, ?, ?)');
$stmt_siswa->bind_param('issss', $id_user, $nama_siswa, $nis, $kelas, $emailVal);
$stmt_siswa->execute();
$id_siswa = $db->insert_id;
$stmt_siswa->close();


// Buat tagihan awal berdasarkan kelas
$is_kelas_x = (strpos($kelas, 'X ') === 0) || $kelas === 'X';
$is_kelas_xi = (strpos($kelas, 'XI ') === 0) || $kelas === 'XI';
$is_kelas_xii = (strpos($kelas, 'XII ') === 0) || $kelas === 'XII';

// 2. Tagihan Kegiatan/DSP
$stmt_keg = $db->prepare("INSERT INTO tagihan_kegiatan (id_siswa, nama_kegiatan, jenis_tagihan, kelas_label, jumlah, sisa_tagihan, status) VALUES (?, ?, ?, ?, ?, ?, 'belum_lunas')");

if ($is_kelas_x) {
    $nama_keg = 'DSP & Biaya Tahunan';
    $jenis = 'dsp';
    $jumlah = 2750000;
    $stmt_keg->bind_param("isssdd", $id_siswa, $nama_keg, $jenis, $kelas, $jumlah, $jumlah);
    $stmt_keg->execute();
} elseif ($is_kelas_xi) {
    $nama_keg = 'DSP Tahunan';
    $jenis = 'dsp';
    $jumlah = 1000000;
    $stmt_keg->bind_param("isssdd", $id_siswa, $nama_keg, $jenis, $kelas, $jumlah, $jumlah);
    $stmt_keg->execute();
} elseif ($is_kelas_xii) {
    $nama_keg = 'DSP Tahunan';
    $jenis = 'dsp';
    $jumlah = 1000000;
    $stmt_keg->bind_param("isssdd", $id_siswa, $nama_keg, $jenis, $kelas, $jumlah, $jumlah);
    $stmt_keg->execute();

    $nama_keg_akhir = 'Kegiatan Akhir Tahun';
    $jenis_akhir = 'kegiatan';
    $jumlah_akhir = 1200000;
    $stmt_keg->bind_param("isssdd", $id_siswa, $nama_keg_akhir, $jenis_akhir, $kelas, $jumlah_akhir, $jumlah_akhir);
    $stmt_keg->execute();
}

$stmt_keg->close();

$db->close();
header('Location: ../../../Data_Siswa.html?sukses=Siswa+berhasil+ditambahkan');
exit;
