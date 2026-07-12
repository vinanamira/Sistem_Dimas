<?php
require_once '../config/database.php';
require_once '../helpers/session.php';

requireRoleJson('bendahara');

header('Content-Type: application/json; charset=utf-8');

$db = getDB();

$query = 'SELECT t.id_tunggakan, t.id_siswa, t.nama_siswa, t.jml_tunggakan,
                 t.periode_tagihan, t.tgl_jatuh_tempo,
                 s.kelas, s.nis
          FROM tunggakan t
          JOIN siswa s ON t.id_siswa = s.id_siswa
          ORDER BY t.jml_tunggakan DESC';

$result = $db->query($query);
if ($result === false) {
    $query = 'SELECT t.id_tunggakan, t.id_siswa, t.nama_siswa, t.jml_tunggakan,
                     s.kelas, s.nis
              FROM tunggakan t
              JOIN siswa s ON t.id_siswa = s.id_siswa
              ORDER BY t.jml_tunggakan DESC';
    $result = $db->query($query);
}

$data = [];
while ($row = $result->fetch_assoc()) {
    if (!isset($row['periode_tagihan'])) {
        $row['periode_tagihan'] = '';
    }
    if (!isset($row['tgl_jatuh_tempo'])) {
        $row['tgl_jatuh_tempo'] = null;
    }
    $data[] = $row;
}
$db->close();

echo json_encode(['success' => true, 'data' => $data]);
