<?php
// data/get_dashboard.php
// Data ringkasan untuk halaman dashboard bendahara

require_once '../config/database.php';
require_once '../helpers/session.php';
requireRole(['bendahara','kepala']);

$db = getDB();

// Total siswa
$total_siswa = $db->query("SELECT COUNT(*) as jml FROM siswa")->fetch_assoc()['jml'];

// Total tunggakan yang masih ada
$total_tunggakan = $db->query("SELECT COUNT(*) as jml FROM tunggakan WHERE jml_tunggakan > 0")->fetch_assoc()['jml'];

// Total jumlah tunggakan (rupiah)
$total_jml_tunggakan = $db->query("SELECT IFNULL(SUM(jml_tunggakan),0) as jml FROM tunggakan")->fetch_assoc()['jml'];

// Total pemasukan bulan ini
$total_pemasukan = $db->query(
    "SELECT IFNULL(SUM(jml_bayar),0) as jml FROM transaksi WHERE MONTH(tgl_transaksi)=MONTH(NOW()) AND YEAR(tgl_transaksi)=YEAR(NOW())"
)->fetch_assoc()['jml'];

$db->close();

header('Content-Type: application/json');
echo json_encode([
    'total_siswa'          => $total_siswa,
    'total_tunggakan'      => $total_tunggakan,
    'total_jml_tunggakan'  => $total_jml_tunggakan,
    'total_pemasukan_bulan_ini' => $total_pemasukan,
]);
