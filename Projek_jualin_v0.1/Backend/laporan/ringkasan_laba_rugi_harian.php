<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
include "../koneksi.php";

$id_toko = $_GET['id_toko'] ?? null;

if (!$id_toko) {
    echo json_encode(["error" => "id_toko wajib ada"]);
    exit;
}

$sql = "SELECT 
            COALESCE(SUM(ti.JUMLAH * ti.HARGA_JUAL), 0) AS total_penjualan,
            COALESCE(SUM(ti.JUMLAH * ti.HARGA_MODAL), 0) AS total_modal,
            COALESCE(SUM(ti.JUMLAH * ti.HARGA_JUAL) - SUM(ti.JUMLAH * ti.HARGA_MODAL), 0) AS laba
        FROM transaksi t
        JOIN transaksi_items ti ON t.KODE_TRANSAKSI = ti.KODE_TRANSAKSI
        WHERE DATE(t.TANGGAL) = CURDATE()
          AND t.id_toko = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_toko);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

echo json_encode($row);
?>
