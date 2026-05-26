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
            SUM(ti.JUMLAH) AS TOTAL_TERJUAL,
            SUM(ti.JUMLAH * ti.HARGA_JUAL) AS TOTAL_PENJUALAN,
            SUM(ti.JUMLAH * ti.HARGA_MODAL) AS TOTAL_MODAL,
            SUM(ti.JUMLAH * ti.HARGA_JUAL) - SUM(ti.JUMLAH * ti.HARGA_MODAL) AS LABA
        FROM transaksi t
        JOIN transaksi_items ti ON t.KODE_TRANSAKSI = ti.KODE_TRANSAKSI
        LEFT JOIN produk p ON ti.KODE_PRODUK = p.KODE_PRODUK
        WHERE t.id_toko = ?
        GROUP BY COALESCE(p.NAMA_PRODUK, ti.NAMA_PRODUK)
        ORDER BY TOTAL_TERJUAL DESC";

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
        "TOTAL_TERJUAL"   => $row["TOTAL_TERJUAL"],
        "TOTAL_PENJUALAN" => $row["TOTAL_PENJUALAN"],
        "TOTAL_MODAL"     => $row["TOTAL_MODAL"],
        "LABA"            => $row["LABA"]
    ];
}

echo json_encode($data);
?>
