<?php
// proses/siswa/edit_siswa.php
// Proses update data siswa (Bendahara only)

require_once '../../config/database.php';
require_once '../../helpers/session.php';
requireRole('bendahara');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../../Data_Siswa.html');
    exit;
}

$id_siswa   = (int)($_POST['id_siswa'] ?? 0);
$nama_siswa = trim($_POST['nama_siswa'] ?? '');
$nis        = trim($_POST['nis'] ?? '');
$kelas      = trim($_POST['kelas'] ?? '');

if (!$id_siswa || empty($nama_siswa) || empty($nis) || empty($kelas)) {
    header('Location: ../../../Data_Siswa.html?error=Data+tidak+lengkap');
    exit;
}

$db   = getDB();
$stmt = $db->prepare("UPDATE siswa SET nama_siswa=?, nis=?, kelas=? WHERE id_siswa=?");
$stmt->bind_param("sssi", $nama_siswa, $nis, $kelas, $id_siswa);
$stmt->execute();
$stmt->close();

// Update juga nama_siswa di tunggakan
$stmt2 = $db->prepare("UPDATE tunggakan SET nama_siswa=? WHERE id_siswa=?");
$stmt2->bind_param("si", $nama_siswa, $id_siswa);
$stmt2->execute();
$stmt2->close();

$db->close();
header('Location: ../../../Data_Siswa.html?sukses=Data+siswa+berhasil+diperbarui');
exit;
