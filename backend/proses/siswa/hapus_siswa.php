<?php
// proses/siswa/hapus_siswa.php
// Proses hapus siswa (Bendahara only)

require_once '../../config/database.php';
require_once '../../helpers/session.php';
requireRole('bendahara');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../../Data_Siswa.html');
    exit;
}

$id_siswa = (int)($_POST['id_siswa'] ?? 0);

if (!$id_siswa) {
    header('Location: ../../../Data_Siswa.html?error=ID+siswa+tidak+valid');
    exit;
}

$db = getDB();

// Ambil id_user dulu sebelum hapus
$ambil = $db->prepare("SELECT id_user FROM siswa WHERE id_siswa = ?");
$ambil->bind_param("i", $id_siswa);
$ambil->execute();
$ambil->bind_result($id_user);
$ambil->fetch();
$ambil->close();

// Hapus siswa (tunggakan terhapus otomatis via ON DELETE CASCADE)
$stmt = $db->prepare("DELETE FROM siswa WHERE id_siswa = ?");
$stmt->bind_param("i", $id_siswa);
$stmt->execute();
$stmt->close();

// Hapus juga user terkait
if ($id_user) {
    $stmt2 = $db->prepare("DELETE FROM users WHERE id_user = ?");
    $stmt2->bind_param("i", $id_user);
    $stmt2->execute();
    $stmt2->close();
}

$db->close();
header('Location: ../../../Data_Siswa.html?sukses=Siswa+berhasil+dihapus');
exit;
