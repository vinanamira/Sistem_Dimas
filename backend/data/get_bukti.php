<?php
require_once '../config/database.php';
require_once '../helpers/session.php';

requireRoleJson(['bendahara', 'kepala']);

$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
    http_response_code(404);
    exit;
}

$db   = getDB();
$stmt = $db->prepare('SELECT bukti_blob, bukti_mime, bukti_transfer FROM transaksi WHERE id_transaksi = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
$db->close();

if ($row && $row['bukti_blob'] !== null && $row['bukti_blob'] !== '') {
    header('Content-Type: ' . ($row['bukti_mime'] ?: 'application/octet-stream'));
    header('Content-Length: ' . strlen($row['bukti_blob']));
    header('Cache-Control: private, max-age=3600');
    echo $row['bukti_blob'];
    exit;
}

// Fallback bukti lama yang masih tersimpan sebagai file
if ($row && !empty($row['bukti_transfer'])) {
    header('Location: ../../' . $row['bukti_transfer']);
    exit;
}

http_response_code(404);
