<?php
require_once '../config/database.php';
require_once '../helpers/session.php';

requireRoleJson('bendahara');

header('Content-Type: application/json; charset=utf-8');

$db    = getDB();
$filter = $_GET['filter'] ?? '';

if ($filter === 'current_month') {
    $months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $currentMonthStr = $months[date('n') - 1] . ' ' . date('Y');
    $currentMonthNum = (int)date('n');

    $query = '
    SELECT 
        s.id_siswa, s.nama_siswa, s.nis, s.kelas, s.email, u.username
    FROM siswa s
    JOIN users u ON s.id_user = u.id_user
    ORDER BY s.nama_siswa ASC
    ';
    $result = $db->query($query);
    $studentMap = [];
    while ($row = $result->fetch_assoc()) {
        $row['jml_tunggakan'] = 0;
        $row['total_awal'] = 0;
        $row['total_tagihan_record'] = 0;
        $studentMap[$row['id_siswa']] = $row;
    }

    $stmtT = $db->prepare("SELECT id_siswa, jml_tunggakan, jumlah_tagihan_awal, periode_tagihan FROM tunggakan WHERE periode_tagihan LIKE ?");
    $likePattern = "%$currentMonthStr%";
    $stmtT->bind_param('s', $likePattern);
    $stmtT->execute();
    $resT = $stmtT->get_result();
    while ($t = $resT->fetch_assoc()) {
        $sid = $t['id_siswa'];
        if (isset($studentMap[$sid])) {
            $studentMap[$sid]['jml_tunggakan'] += (float)$t['jml_tunggakan'];
            $studentMap[$sid]['total_awal'] += (float)$t['jumlah_tagihan_awal'];
            $studentMap[$sid]['total_tagihan_record'] += 1;
        }
    }
    $stmtT->close();

    $stmtK = $db->query("SELECT id_siswa, nama_kegiatan, jumlah, sisa_tagihan FROM tagihan_kegiatan");
    while ($k = $stmtK->fetch_assoc()) {
        $sid = $k['id_siswa'];
        if (isset($studentMap[$sid])) {
            $kelasStr = strtoupper($studentMap[$sid]['kelas']);
            $isKelasXI_XII = strpos($kelasStr, 'XI') === 0 || strpos($kelasStr, 'XII') === 0;
            
            $namaKeg = strtoupper(trim($k['nama_kegiatan'] ?? ''));
            
            $isHidden = false;
            if ($isKelasXI_XII && $currentMonthNum === 7 && strpos($namaKeg, 'DSP') !== false) {
                $isHidden = true;
            }
            $isKegAkhirTahun = strtolower(trim($k['nama_kegiatan'])) === 'kegiatan akhir tahun';
            if ($isKegAkhirTahun && ($currentMonthNum < 1 || $currentMonthNum > 4)) {
                $isHidden = true;
            }
            
            if (!$isHidden) {
                $studentMap[$sid]['jml_tunggakan'] += (float)$k['sisa_tagihan'];
                $studentMap[$sid]['total_awal'] += (float)$k['jumlah'];
                $studentMap[$sid]['total_tagihan_record'] += 1;
            }
        }
    }

    $data = array_values($studentMap);
} else {
    $query = '
    SELECT 
        s.id_siswa, s.nama_siswa, s.nis, s.kelas, s.email, u.username,
        IFNULL(t.total_tunggakan, 0) + IFNULL(k.total_kegiatan, 0) AS jml_tunggakan,
        IFNULL(t.total_awal, 0) + IFNULL(k.total_awal, 0) AS total_awal,
        IFNULL(t.count_tunggakan, 0) + IFNULL(k.count_kegiatan, 0) AS total_tagihan_record
    FROM siswa s
    JOIN users u ON s.id_user = u.id_user
    LEFT JOIN (
        SELECT id_siswa, 
               SUM(jml_tunggakan) AS total_tunggakan, 
               SUM(CASE WHEN jml_tunggakan > 0 THEN jumlah_tagihan_awal ELSE 0 END) AS total_awal, 
               COUNT(id_tunggakan) AS count_tunggakan 
        FROM tunggakan 
        GROUP BY id_siswa
    ) t ON s.id_siswa = t.id_siswa
    LEFT JOIN (
        SELECT id_siswa, 
               SUM(sisa_tagihan) AS total_kegiatan, 
               SUM(CASE WHEN sisa_tagihan > 0 THEN jumlah ELSE 0 END) AS total_awal, 
               COUNT(id_tagihan_keg) AS count_kegiatan 
        FROM tagihan_kegiatan 
        GROUP BY id_siswa
    ) k ON s.id_siswa = k.id_siswa
    ORDER BY s.nama_siswa ASC
    ';
    $result = $db->query($query);
    $data   = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
}

$db->close();

echo json_encode(['success' => true, 'data' => $data]);
