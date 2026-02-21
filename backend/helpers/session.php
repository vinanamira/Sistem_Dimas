<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Simpan data login ke session
 */
function setSession($id_user, $username, $role) {
    $_SESSION['id_user']   = $id_user;
    $_SESSION['username']  = $username;
    $_SESSION['role']      = $role;
    $_SESSION['logged_in'] = true;
}

/**
 * Cek apakah user sudah login
 */
function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * Ambil data user yang sedang login
 */
function getLoggedUser() {
    if (!isLoggedIn()) return null;
    return [
        'id_user'  => $_SESSION['id_user'],
        'username' => $_SESSION['username'],
        'role'     => $_SESSION['role'],
    ];
}

/**
 * Cek apakah role user sesuai yang diizinkan
 * @param string|array $roles
 */
function requireRole($roles) {
    if (!isLoggedIn()) {
        header('Location: ../../login.php');
        exit;
    }
    if (is_string($roles)) $roles = [$roles];
    if (!in_array($_SESSION['role'], $roles)) {
        die('<p style="color:red;">Akses ditolak. Anda tidak memiliki izin.</p>');
    }
}

/**
 * Hapus session (logout)
 */
function destroySession() {
    session_unset();
    session_destroy();
}
