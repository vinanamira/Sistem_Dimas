<?php
/**
 * Logika simpan transaksi + update tunggakan + laporan_pembayaran (diagram: Transaksi.simpan)
 *
 * @param string $keterangan Contoh: "Pembayaran SPP", "Kegiatan: Prakerin"
 * @return int id_transaksi baru
 */
function simpanTransaksiPembayaran(
    mysqli $db,
    int $id_siswa,
    ?int $id_tunggakan,
    float $jml_bayar,
    string $tgl_transaksi,
    string $keterangan = 'Pembayaran SPP',
    string $jenis = 'spp',
    ?string $buktiData = null,
    ?string $buktiMime = null
): int {
    if ($id_siswa <= 0 || $jml_bayar <= 0) {
        throw new InvalidArgumentException('Data transaksi tidak valid');
    }

    $ambil = $db->prepare('SELECT nama_siswa FROM siswa WHERE id_siswa=?');
    $ambil->bind_param('i', $id_siswa);
    $ambil->execute();
    $res = $ambil->get_result();
    $row = $res->fetch_assoc();
    $ambil->close();

    if (!$row) {
        throw new RuntimeException('Siswa tidak ditemukan');
    }
    $nama_siswa = $row['nama_siswa'];

    if ($id_tunggakan) {
        $stmt = $db->prepare(
            'INSERT INTO transaksi (id_siswa, id_tunggakan, nama_siswa, jml_bayar, tgl_transaksi, keterangan, bukti_blob, bukti_mime)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('iisdssss', $id_siswa, $id_tunggakan, $nama_siswa, $jml_bayar, $tgl_transaksi, $keterangan, $buktiData, $buktiMime);
    } else {
        $stmt = $db->prepare(
            'INSERT INTO transaksi (id_siswa, nama_siswa, jml_bayar, tgl_transaksi, keterangan, bukti_blob, bukti_mime)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('isdssss', $id_siswa, $nama_siswa, $jml_bayar, $tgl_transaksi, $keterangan, $buktiData, $buktiMime);
    }

    $stmt->execute();
    $id_transaksi = (int) $db->insert_id;
    $stmt->close();

    if ($id_tunggakan) {
        $stmt2 = $db->prepare('UPDATE tunggakan SET jml_tunggakan = GREATEST(jml_tunggakan - ?, 0) WHERE id_tunggakan=?');
        $stmt2->bind_param('di', $jml_bayar, $id_tunggakan);
        $stmt2->execute();
        $stmt2->close();
    }

    $stmt3 = $db->prepare(
        'INSERT INTO laporan_pembayaran (id_transaksi, id_siswa, jml_bayar, tgl_transaksi, nama_siswa)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt3->bind_param('iidss', $id_transaksi, $id_siswa, $jml_bayar, $tgl_transaksi, $nama_siswa);
    $stmt3->execute();
    $stmt3->close();

    return $id_transaksi;
}
