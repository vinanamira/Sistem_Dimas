<?php

require_once 'backend/helpers/session.php';

if (isLoggedIn()) {
    $user = getLoggedUser();
    switch ($user['role']) {
        case 'bendahara': header('Location: index_admin.html'); break;
        case 'kepala':    header('Location: Login_Kepsek.html'); break;
        case 'siswa':     header('Location: Index_siswa.html'); break;
    }
    exit;
}

$error = htmlspecialchars($_GET['error'] ?? '');
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Login - Sistem Pembayaran SPP</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">

  <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
    <h2 class="text-2xl font-bold text-center mb-6 text-blue-600">Login Sistem SPP</h2>

    <?php if ($error): ?>
    <div class="mb-4 p-3 bg-red-100 text-red-700 rounded text-sm">
      <?= $error ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="backend/proses/login.php">
      <div class="mb-4">
        <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
        <input type="text" id="username" name="username" required
          class="w-full px-4 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500" />
      </div>

      <div class="mb-6">
        <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
        <input type="password" id="password" name="password" required
          class="w-full px-4 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500" />
      </div>

      <button type="submit"
        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 rounded transition duration-200">
        Masuk
      </button>
    </form>

    <p class="text-xs text-center text-gray-400 mt-6">
      Akun default — Password: <strong>admin123</strong><br/>
      Admin: <code>admin</code> &nbsp;|&nbsp;
      Kepala: <code>kepsek</code> &nbsp;|&nbsp;
      Siswa: <code>siswa001</code>
    </p>
  </div>

</body>
</html>
