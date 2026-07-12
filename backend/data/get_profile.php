<?php
require_once '../config/database.php';
require_once '../helpers/session.php';

requireLoginJson();

header('Content-Type: application/json; charset=utf-8');

$user = getLoggedUser();
$db   = getDB();
$out  = [
    'success' => true,
    'user'    => $user,
    'siswa'   => null,
    'bendahara' => null,
    'kepala'  => null,
];

if ($user['role'] === 'siswa') {
    $stmt = $db->prepare(
        'SELECT s.id_siswa, s.nama_siswa, s.nis, s.kelas, s.email
         FROM siswa s WHERE s.id_user = ?'
    );
    $stmt->bind_param('i', $user['id_user']);
    $stmt->execute();
    $res = $stmt->get_result();
    $out['siswa'] = $res->fetch_assoc();
    $stmt->close();
} elseif ($user['role'] === 'bendahara') {
    $stmt = $db->prepare('SELECT id_bend, nama_bend FROM bendahara WHERE id_user=?');
    $stmt->bind_param('i', $user['id_user']);
    $stmt->execute();
    $res = $stmt->get_result();
    $out['bendahara'] = $res->fetch_assoc();
    $stmt->close();
} elseif ($user['role'] === 'kepala') {
    $stmt = $db->prepare('SELECT id_kepala, nama_kepala FROM kepala WHERE id_user=?');
    $stmt->bind_param('i', $user['id_user']);
    $stmt->execute();
    $res = $stmt->get_result();
    $out['kepala'] = $res->fetch_assoc();
    $stmt->close();
}

$db->close();
echo json_encode($out);
