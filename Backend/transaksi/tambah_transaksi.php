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
$id_toko = $data['id_toko'] ?? null; // ✅ wajib untuk multi-toko

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
    $totalTransaksi += intval($item['harga']) * intval($item['jumlah']);
}

$conn->begin_transaction(); // ✅ transaksi atomik
try {
    // ✅ INSERT header — sertakan id_toko
    $stmtHeader = $conn->prepare(
        "INSERT INTO transaksi (KODE_TRANSAKSI, id_toko, TOTAL, METODE, TANGGAL)
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmtHeader->bind_param("sidss", $kode, $id_toko, $totalTransaksi, $metode, $tanggal);
    if (!$stmtHeader->execute()) {
        throw new Exception("Gagal simpan header: " . $stmtHeader->error);
    }
    $stmtHeader->close();

    // ✅ INSERT items — kolom BARU: NAMA_PRODUK, HARGA_MODAL, JUMLAH, HARGA_JUAL
    $stmtItem = $conn->prepare(
        "INSERT INTO transaksi_items
            (KODE_TRANSAKSI, KODE_PRODUK, NAMA_PRODUK, HARGA_MODAL, JUMLAH, HARGA_JUAL)
         VALUES (?, ?, ?, ?, ?, ?)"
    );

    foreach ($items as $item) {
        $id_produk  = intval($item['id']);
        $jumlah     = intval($item['jumlah']);
        $harga_jual = intval($item['harga']); // harga jual per unit

        // ✅ Ambil nama & harga modal; pastikan produk milik toko ini
        $q = $conn->prepare(
            "SELECT NAMA_PRODUK, HARGA_MODAL FROM produk
             WHERE KODE_PRODUK = ? AND id_toko = ?"
        );
        $q->bind_param("ii", $id_produk, $id_toko);
        $q->execute();
        $res = $q->get_result()->fetch_assoc();
        $q->close();

        if (!$res) {
            throw new Exception("Produk ID $id_produk tidak ditemukan atau bukan milik toko ini");
        }

        $nama_produk = $res['NAMA_PRODUK'];
        $harga_modal = floatval($res['HARGA_MODAL']);

        // bind: s=KODE_TRANSAKSI, i=KODE_PRODUK, s=NAMA_PRODUK, d=HARGA_MODAL, i=JUMLAH, d=HARGA_JUAL
        $stmtItem->bind_param("sisdid",
            $kode, $id_produk, $nama_produk, $harga_modal, $jumlah, $harga_jual
        );
        if (!$stmtItem->execute()) {
            throw new Exception("Gagal simpan item: " . $stmtItem->error);
        }
    }
    $stmtItem->close();

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
    $conn->rollback(); // ✅ batalkan semua jika ada yang gagal
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>
