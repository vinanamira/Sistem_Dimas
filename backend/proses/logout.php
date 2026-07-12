<?php
require_once '../helpers/session.php';

destroySession();

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['success' => true]);
exit;
