<?php
// proses/tunggakan/update_tunggakan.php
// Update jumlah tunggakan siswa (Bendahara only)

require_once '../../config/database.php';
require_once '../../helpers/session.php';
requireRole('bendahara');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../../Tagihan_Bulanan.html');
    exit;
}

$id_tunggakan  = (int)($_POST['id_tunggakan'] ?? 0);
$jml_tunggakan = floatval($_POST['jml_tunggakan'] ?? 0);

if (!$id_tunggakan || $jml_tunggakan < 0) {
    header('Location: ../../../Tagihan_Bulanan.html?error=Data+tidak+valid');
    exit;
}

$db   = getDB();
$stmt = $db->prepare("UPDATE tunggakan SET jml_tunggakan=? WHERE id_tunggakan=?");
$stmt->bind_param("di", $jml_tunggakan, $id_tunggakan);
$stmt->execute();
$stmt->close();
$db->close();

header('Location: ../../../Tagihan_Bulanan.html?sukses=Tunggakan+berhasil+diperbarui');
exit;
