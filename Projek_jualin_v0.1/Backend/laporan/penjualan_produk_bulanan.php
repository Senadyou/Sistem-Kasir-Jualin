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
            DATE_FORMAT(t.TANGGAL, '%Y-%m') AS BULAN,
            COUNT(DISTINCT t.KODE_TRANSAKSI) AS TOTAL_TRANSAKSI,
            SUM(ti.JUMLAH) AS TOTAL_PRODUK_TERJUAL,
            SUM(ti.JUMLAH * ti.HARGA_JUAL) AS TOTAL_PENJUALAN
        FROM transaksi t
        JOIN transaksi_items ti ON t.KODE_TRANSAKSI = ti.KODE_TRANSAKSI
        WHERE t.id_toko = ?
        GROUP BY DATE_FORMAT(t.TANGGAL, '%Y-%m')
        ORDER BY BULAN DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_toko);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = [
        "BULAN"               => $row["BULAN"],
        "TOTAL_TRANSAKSI"     => $row["TOTAL_TRANSAKSI"],
        "TOTAL_PRODUK_TERJUAL"=> $row["TOTAL_PRODUK_TERJUAL"],
        "TOTAL_PENJUALAN"     => $row["TOTAL_PENJUALAN"]
    ];
}

echo json_encode($data);
$conn->close();
?>
