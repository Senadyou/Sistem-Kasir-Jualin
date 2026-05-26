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
            DATE(t.TANGGAL) AS TANGGAL,
            COUNT(DISTINCT t.KODE_TRANSAKSI) AS TOTAL_TRANSAKSI,
            IFNULL(SUM(ti.JUMLAH), 0) AS TOTAL_PRODUK_TERJUAL,
            IFNULL(SUM(ti.JUMLAH * ti.HARGA_JUAL), 0) AS TOTAL_PENJUALAN
        FROM transaksi t
        JOIN transaksi_items ti ON t.KODE_TRANSAKSI = ti.KODE_TRANSAKSI
        WHERE t.id_toko = ?
        GROUP BY DATE(t.TANGGAL)
        ORDER BY TANGGAL DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_toko);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = [
        "TANGGAL"              => $row["TANGGAL"],
        "TOTAL_TRANSAKSI"      => (int)$row["TOTAL_TRANSAKSI"],
        "TOTAL_PRODUK_TERJUAL" => (int)$row["TOTAL_PRODUK_TERJUAL"],
        "TOTAL_PENJUALAN"      => (int)$row["TOTAL_PENJUALAN"]
    ];
}

echo json_encode($data);
?>
