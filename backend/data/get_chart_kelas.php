<?php
require_once '../config/database.php';
require_once '../helpers/session.php';

requireRoleJson(['bendahara', 'kepala']);

header('Content-Type: application/json; charset=utf-8');

$db = getDB();

$months          = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$currentMonthStr = $months[(int) date('n') - 1] . ' ' . date('Y');
$currentMonthNum = (int) date('n');

// Kumpulkan semua siswa
$siswa = [];
$resS = $db->query('SELECT id_siswa, kelas FROM siswa');
while ($row = $resS->fetch_assoc()) {
    $siswa[(int) $row['id_siswa']] = [
        'kelas'   => $row['kelas'],
        'arrears' => 0.0, // sisa tunggakan
        'awal'    => 0.0, // total tagihan awal (untuk bedakan cicilan vs belum bayar)
        'record'  => 0,   // jumlah tagihan aktif
    ];
}

// SPP periode bulan berjalan
$stmtT = $db->prepare("SELECT id_siswa, jml_tunggakan, jumlah_tagihan_awal FROM tunggakan WHERE periode_tagihan LIKE ?");
$likePattern = "%$currentMonthStr%";
$stmtT->bind_param('s', $likePattern);
$stmtT->execute();
$resT = $stmtT->get_result();
while ($t = $resT->fetch_assoc()) {
    $sid = (int) $t['id_siswa'];
    if (isset($siswa[$sid])) {
        $siswa[$sid]['arrears'] += (float) $t['jml_tunggakan'];
        $siswa[$sid]['awal']    += (float) $t['jumlah_tagihan_awal'];
        $siswa[$sid]['record']  += 1;
    }
}
$stmtT->close();

// Tagihan kegiatan (aturan sembunyi sama seperti get_siswa.php filter current_month)
$resK = $db->query('SELECT id_siswa, nama_kegiatan, jumlah, sisa_tagihan FROM tagihan_kegiatan');
while ($k = $resK->fetch_assoc()) {
    $sid = (int) $k['id_siswa'];
    if (!isset($siswa[$sid])) {
        continue;
    }
    $kelasStr      = strtoupper($siswa[$sid]['kelas'] ?? '');
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
        $siswa[$sid]['arrears'] += (float) $k['sisa_tagihan'];
        $siswa[$sid]['awal']    += (float) $k['jumlah'];
        $siswa[$sid]['record']  += 1;
    }
}

// Klasifikasi per kelas (logika status sama dengan halaman Tagihan Bulanan)
$byKelas = [];
foreach ($siswa as $s) {
    $kelas = $s['kelas'];
    if (!isset($byKelas[$kelas])) {
        $byKelas[$kelas] = ['lunas' => 0, 'cicilan' => 0, 'belum' => 0];
    }
    if ($s['arrears'] > 0) {
        if ($s['arrears'] < $s['awal']) {
            $byKelas[$kelas]['cicilan']++;
        } else {
            $byKelas[$kelas]['belum']++;
        }
    } else {
        // arrears 0 (lunas) atau belum ada tagihan -> dihitung lunas
        $byKelas[$kelas]['lunas']++;
    }
}
ksort($byKelas);

$labels  = [];
$lunas   = [];
$cicilan = [];
$belum   = [];
foreach ($byKelas as $kelas => $c) {
    $labels[]  = $kelas;
    $lunas[]   = $c['lunas'];
    $cicilan[] = $c['cicilan'];
    $belum[]   = $c['belum'];
}

$db->close();

echo json_encode([
    'success' => true,
    'labels'  => $labels,
    'data'    => $lunas, // kompatibilitas lama
    'lunas'   => $lunas,
    'cicilan' => $cicilan,
    'belum'   => $belum,
]);
