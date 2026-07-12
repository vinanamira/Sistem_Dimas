<?php
require_once '../config/database.php';
require_once '../helpers/session.php';

requireRoleJson(['kepala', 'bendahara']);

header('Content-Type: application/json; charset=utf-8');

$tahunRaw = $_GET['tahun'] ?? date('Y');
$bulanRaw = $_GET['bulan'] ?? date('n');
$tahun = strtolower((string) $tahunRaw) === 'all' ? 'all' : (int) $tahunRaw;
$bulan = strtolower((string) $bulanRaw) === 'all' ? 'all' : (int) $bulanRaw;
if ($bulan !== 'all' && ($bulan < 1 || $bulan > 12)) {
    $bulan = (int) date('n');
}

$db = getDB();

$q = $db->prepare(
    "SELECT tgl_uang, ket_uang, jenis_uang, jml_uang
     FROM laporan_keuangan
     WHERE (? = 'all' OR YEAR(tgl_uang) = ?) AND (? = 'all' OR MONTH(tgl_uang) = ?) AND jenis_uang = 'pengeluaran'
     ORDER BY tgl_uang ASC"
);
$q->bind_param('siss', $tahun, $tahun, $bulan, $bulan);
$q->execute();
$res = $q->get_result();

$rows      = [];
$masuk     = 0.0;
$keluar    = 0.0;
$namaBulan = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
    7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
];

while ($row = $res->fetch_assoc()) {
    $rows[] = $row;
    if ($row['jenis_uang'] === 'pemasukan') {
        $masuk += (float) $row['jml_uang'];
    } else {
        $keluar += (float) $row['jml_uang'];
    }
}
$q->close();

$years = [];
$yearResult = $db->query(
    "SELECT DISTINCT y FROM (
       SELECT DISTINCT YEAR(tgl_uang) AS y FROM laporan_keuangan
       UNION
       SELECT DISTINCT YEAR(tgl_transaksi) AS y FROM transaksi
     ) AS all_years ORDER BY y DESC"
);
while ($yearRow = $yearResult->fetch_assoc()) {
    $years[] = (int) $yearRow['y'];
}
$yearResult->close();

$months = [];
$monthStmt = $db->prepare(
    "SELECT DISTINCT m FROM (
       SELECT DISTINCT MONTH(tgl_uang) AS m FROM laporan_keuangan WHERE (? = 'all' OR YEAR(tgl_uang) = ?)
       UNION
       SELECT DISTINCT MONTH(tgl_transaksi) AS m FROM transaksi WHERE (? = 'all' OR YEAR(tgl_transaksi) = ?)
     ) AS all_months ORDER BY m ASC"
);
$monthStmt->bind_param('siss', $tahun, $tahun, $tahun, $tahun);
$monthStmt->execute();
$monthRes = $monthStmt->get_result();
while ($monthRow = $monthRes->fetch_assoc()) {
    $months[] = (int) $monthRow['m'];
}
$monthStmt->close();

$db->close();

echo json_encode([
    'success'          => true,
    'tahun'            => $tahun,
    'bulan'            => $bulan,
    'nama_bulan'       => $namaBulan[$bulan] ?? '',
    'rows'             => $rows,
    'total_pemasukan'  => $masuk,
    'total_pengeluaran'=> $keluar,
    'saldo'            => $masuk - $keluar,
    'available_years'  => $years,
    'available_months' => $months,
]);
