<?php
function uploadFile($fileInfo, $inputName) {
    if (!isset($fileInfo['error']) || is_array($fileInfo['error'])) {
        throw new RuntimeException('Invalid parameters.');
    }
    switch ($fileInfo['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            throw new RuntimeException('Bukti transfer tidak boleh kosong.');
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            throw new RuntimeException('Ukuran file terlalu besar (Maksimal 15MB).');
        default:
            throw new RuntimeException('Terjadi kesalahan saat mengunggah file.');
    }
    if ($fileInfo['size'] > 15000000) {
        throw new RuntimeException('Ukuran gambar maksimal 15MB.');
    }
    
    // Check MIME type
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($fileInfo['tmp_name']);
    $allowedTypes = [
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'pdf' => 'application/pdf'
    ];
    $ext = array_search($mime, $allowedTypes, true);
    if ($ext === false) {
        throw new RuntimeException('Invalid file format.');
    }
    
    // Create uploads directory if not exists
    $uploadDir = __DIR__ . '/../../uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    // Generate unique name
    $fileName = sprintf('%s.%s', sha1_file($fileInfo['tmp_name']), $ext);
    $destination = $uploadDir . $fileName;
    
    if (!move_uploaded_file($fileInfo['tmp_name'], $destination)) {
        throw new RuntimeException('Failed to move uploaded file.');
    }

    return 'uploads/' . $fileName;
}

/**
 * Validasi bukti transfer lalu kembalikan isinya untuk disimpan ke database.
 * @return array{data:string, mime:string}
 */
function readBuktiUpload($fileInfo) {
    if (!isset($fileInfo['error']) || is_array($fileInfo['error'])) {
        throw new RuntimeException('Invalid parameters.');
    }
    switch ($fileInfo['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            throw new RuntimeException('Bukti transfer tidak boleh kosong.');
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            throw new RuntimeException('Ukuran file terlalu besar (Maksimal 15MB).');
        default:
            throw new RuntimeException('Terjadi kesalahan saat mengunggah file.');
    }
    if ($fileInfo['size'] > 15000000) {
        throw new RuntimeException('Ukuran gambar maksimal 15MB.');
    }

    $finfo        = new finfo(FILEINFO_MIME_TYPE);
    $mime         = $finfo->file($fileInfo['tmp_name']);
    $allowedTypes = [
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'pdf' => 'application/pdf',
    ];
    if (array_search($mime, $allowedTypes, true) === false) {
        throw new RuntimeException('Invalid file format.');
    }

    $data = file_get_contents($fileInfo['tmp_name']);
    if ($data === false) {
        throw new RuntimeException('Gagal membaca file bukti.');
    }

    return ['data' => $data, 'mime' => $mime];
}
