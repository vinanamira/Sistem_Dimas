<?php
require_once '../config/database.php';
require_once '../helpers/session.php';

requireRoleJson(['bendahara', 'kepala']);

header('Content-Type: application/json; charset=utf-8');

$db = getDB();

$total_siswa = (int) $db->query('SELECT COUNT(*) as jml FROM siswa')->fetch_assoc()['jml'];

// Hitung total tagihan konsisten dengan halaman Tagihan Bulanan:
// SPP periode bulan berjalan + tagihan kegiatan (dengan aturan sembunyi yang sama).
$months          = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$currentMonthStr = $months[(int) date('n') - 1] . ' ' . date('Y');
$currentMonthNum = (int) date('n');

$perSiswa = []; // id_siswa => total tagihan bulan ini

// SPP periode bulan berjalan
$stmtT = $db->prepare("SELECT id_siswa, jml_tunggakan FROM tunggakan WHERE jml_tunggakan > 0 AND periode_tagihan LIKE ?");
$likePattern = "%$currentMonthStr%";
$stmtT->bind_param('s', $likePattern);
$stmtT->execute();
$resT = $stmtT->get_result();
while ($t = $resT->fetch_assoc()) {
    $sid = (int) $t['id_siswa'];
    $perSiswa[$sid] = ($perSiswa[$sid] ?? 0) + (float) $t['jml_tunggakan'];
}
$stmtT->close();

// Tagihan kegiatan (aturan sembunyi sama seperti get_siswa.php filter current_month)
$resK = $db->query(
    "SELECT tk.id_siswa, tk.nama_kegiatan, tk.sisa_tagihan, s.kelas
     FROM tagihan_kegiatan tk JOIN siswa s ON tk.id_siswa = s.id_siswa
     WHERE tk.sisa_tagihan > 0"
);
while ($k = $resK->fetch_assoc()) {
    $kelasStr      = strtoupper($k['kelas'] ?? '');
    $isKelasXI_XII = strpos($kelasStr, 'XI') === 0 || strpos($kelasStr, 'XII') === 0;
    $namaKeg       = strtoupper(trim($k['nama_kegiatan'] ?? ''));

    $isHidden = false;
    if ($isKelasXI_XII && $currentMonthNum === 7 && strpos($namaKeg, 'DSP') !== false) {
        $isHidden = true;
    }
    if (strtolower(trim($k['nama_kegiatan'] ?? '')) === 'kegiatan akhir tahun' && ($currentMonthNum < 1 || $currentMonthNum > 4)) {
        $isHidden = true;
    }
    if (!$isHidden) {
        $sid = (int) $k['id_siswa'];
        $perSiswa[$sid] = ($perSiswa[$sid] ?? 0) + (float) $k['sisa_tagihan'];
    }
}

$total_jml_tunggakan = 0.0;
$total_tunggakan     = 0; // jumlah siswa yang masih berutang
foreach ($perSiswa as $amt) {
    if ($amt > 0) {
        $total_jml_tunggakan += $amt;
        $total_tunggakan++;
    }
}

$total_pemasukan = (float) $db->query(
    'SELECT IFNULL(SUM(jml_bayar),0) as jml FROM transaksi WHERE MONTH(tgl_transaksi)=MONTH(NOW()) AND YEAR(tgl_transaksi)=YEAR(NOW())'
)->fetch_assoc()['jml'];

$db->close();

echo json_encode([
    'success'                   => true,
    'total_siswa'               => $total_siswa,
    'total_tunggakan'           => $total_tunggakan,
    'total_jml_tunggakan'       => $total_jml_tunggakan,
    'total_pemasukan_bulan_ini' => $total_pemasukan,
]);
