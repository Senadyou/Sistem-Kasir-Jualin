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
            COALESCE(p.NAMA_PRODUK, ti.NAMA_PRODUK) AS NAMA_PRODUK,
            SUM(ti.JUMLAH) AS TOTAL_QTY,
            SUM(ti.JUMLAH * ti.HARGA_JUAL) AS TOTAL_PENJUALAN
        FROM transaksi t
        JOIN transaksi_items ti ON t.KODE_TRANSAKSI = ti.KODE_TRANSAKSI
        LEFT JOIN produk p ON ti.KODE_PRODUK = p.KODE_PRODUK
        WHERE t.id_toko = ?
        GROUP BY COALESCE(p.NAMA_PRODUK, ti.NAMA_PRODUK)
        ORDER BY TOTAL_QTY DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_toko);
$stmt->execute();
$result = $stmt->get_result();

if (!$result) {
    echo json_encode(["error" => $conn->error]);
    exit;
}

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = [
        "NAMA_PRODUK"     => $row["NAMA_PRODUK"],
        "TOTAL_QTY"       => $row["TOTAL_QTY"],
        "TOTAL_PENJUALAN" => $row["TOTAL_PENJUALAN"]
    ];
}

echo json_encode($data);
?>
