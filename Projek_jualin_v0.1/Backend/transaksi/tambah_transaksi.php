<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
include "../koneksi.php";

$data = json_decode(file_get_contents("php://input"), true);

if (!$data || !isset($data['items']) || !isset($data['metode'])) {
    echo json_encode(["status" => "error", "message" => "Data tidak lengkap"]);
    exit;
}

$items   = $data['items'];
$metode  = $data['metode'];
$id_toko = $data['id_toko'] ?? null; // wajib untuk multi-toko

if (!$id_toko) {
    echo json_encode(["status" => "error", "message" => "id_toko tidak ditemukan, silakan login ulang"]);
    exit;
}
if (count($items) === 0) {
    echo json_encode(["status" => "error", "message" => "Keranjang kosong"]);
    exit;
}

$tanggal        = date("Y-m-d H:i:s");
$kode           = "TRX" . date("YmdHis") . rand(100, 999);
$totalTransaksi = 0;

foreach ($items as $item) {
    // Pastikan penjumlahan total mengizinkan nilai desimal untuk pajak
    $totalTransaksi += floatval($item['harga']) * intval($item['jumlah']);
}

$conn->begin_transaction(); // transaksi atomik
try {
    // 1. INSERT header transaksi
    $stmtHeader = $conn->prepare(
        "INSERT INTO transaksi (KODE_TRANSAKSI, id_toko, TOTAL, METODE, TANGGAL)
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmtHeader->bind_param("sidss", $kode, $id_toko, $totalTransaksi, $metode, $tanggal);
    if (!$stmtHeader->execute()) {
        throw new Exception("Gagal simpan header: " . $stmtHeader->error);
    }
    $stmtHeader->close();

    // 2. Siapkan statement INSERT items (Untuk Produk Normal)
    $stmtItem = $conn->prepare(
        "INSERT INTO transaksi_items
            (KODE_TRANSAKSI, KODE_PRODUK, NAMA_PRODUK, HARGA_MODAL, JUMLAH, HARGA_JUAL)
         VALUES (?, ?, ?, ?, ?, ?)"
    );

    // 3. Siapkan statement UPDATE stok
    $stmtUpdateStok = $conn->prepare(
        "UPDATE produk SET STOK = STOK - ? WHERE KODE_PRODUK = ? AND id_toko = ?"
    );

    foreach ($items as $item) {
        $raw_id     = $item['id'] ?? null;
        $jumlah     = intval($item['jumlah']);
        $harga_jual = floatval($item['harga']); 

        // ==========================================
        // BYPASS UNTUK VIRTUAL ITEM (CONTOH: PAJAK PB1)
        // ==========================================
        if ($raw_id === null || $raw_id === '') {
            $nama_produk = $item['nama']; // Mengambil nama dari request (Pajak Restoran PB1...)
            $harga_modal = 0; // Pajak tidak memotong modal
            
            // Query eksplisit menggunakan NULL untuk KODE_PRODUK agar valid di MariaDB/MySQL
            $stmtVirtual = $conn->prepare(
                "INSERT INTO transaksi_items (KODE_TRANSAKSI, KODE_PRODUK, NAMA_PRODUK, HARGA_MODAL, JUMLAH, HARGA_JUAL)
                 VALUES (?, NULL, ?, ?, ?, ?)"
            );
            $stmtVirtual->bind_param("ssdid", $kode, $nama_produk, $harga_modal, $jumlah, $harga_jual);
            
            if (!$stmtVirtual->execute()) {
                throw new Exception("Gagal simpan item virtual: " . $stmtVirtual->error);
            }
            $stmtVirtual->close();
            
            // Lanjutkan ke item berikutnya tanpa mengecek/memotong stok
            continue;
        }

        // ==========================================
        // ALUR NORMAL UNTUK PRODUK FISIK
        // ==========================================
        $id_produk = intval($raw_id);

        // Ambil nama & harga modal; pastikan produk milik toko ini
        $q = $conn->prepare(
            "SELECT NAMA_PRODUK, HARGA_MODAL, STOK FROM produk
             WHERE KODE_PRODUK = ? AND id_toko = ?"
        );
        $q->bind_param("ii", $id_produk, $id_toko);
        $q->execute();
        $res = $q->get_result()->fetch_assoc();
        $q->close();

        if (!$res) {
            throw new Exception("Produk ID $id_produk tidak ditemukan atau bukan milik toko ini");
        }
        
        // Pengecekan apakah stok cukup
        if ($res['STOK'] < $jumlah) {
             throw new Exception("Stok untuk " . $res['NAMA_PRODUK'] . " tidak mencukupi. Sisa stok: " . $res['STOK']);
        }

        $nama_produk = $res['NAMA_PRODUK'];
        $harga_modal = floatval($res['HARGA_MODAL']);

        // Insert ke transaksi_items
        $stmtItem->bind_param("sisdid",
            $kode, $id_produk, $nama_produk, $harga_modal, $jumlah, $harga_jual
        );
        if (!$stmtItem->execute()) {
            throw new Exception("Gagal simpan item: " . $stmtItem->error);
        }

        // UPDATE (Kurangi) stok di tabel produk
        $stmtUpdateStok->bind_param("iii", $jumlah, $id_produk, $id_toko);
        if (!$stmtUpdateStok->execute()) {
            throw new Exception("Gagal memotong stok: " . $stmtUpdateStok->error);
        }
    }
    
    $stmtItem->close();
    $stmtUpdateStok->close();

    // Jika semua berhasil, simpan permanen
    $conn->commit();

    echo json_encode([
        "status"         => "success",
        "kode_transaksi" => $kode,
        "tanggal"        => $tanggal,
        "metode"         => $metode,
        "total"          => $totalTransaksi,
        "items"          => $items
    ]);

} catch (Exception $e) {
    $conn->rollback(); // batalkan semua jika ada yang gagal
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>