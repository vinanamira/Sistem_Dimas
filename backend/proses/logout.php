<?php
// proses/logout.php
// Memproses logout: hapus session dan redirect ke halaman login

require_once '../helpers/session.php';

destroySession();
header('Location: ../../login.php');
exit;
