<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
include "../koneksi.php";

$tanggal = $_GET['tanggal'] ?? '';
$id_toko = $_GET['id_toko'] ?? null;

if (!$tanggal) {
    echo json_encode([]);
    exit;
}

if (!$id_toko) {
    echo json_encode(["error" => "id_toko wajib ada"]);
    exit;
}

$sql = "SELECT 
            DATE(t.TANGGAL) AS TANGGAL,
            COALESCE(p.NAMA_PRODUK, ti.NAMA_PRODUK) AS NAMA_PRODUK,
            SUM(ti.JUMLAH) AS TOTAL_TERJUAL,
            SUM(ti.JUMLAH * ti.HARGA_JUAL) AS TOTAL_PENJUALAN
        FROM transaksi t
        JOIN transaksi_items ti ON t.KODE_TRANSAKSI = ti.KODE_TRANSAKSI
        LEFT JOIN produk p ON ti.KODE_PRODUK = p.KODE_PRODUK
        WHERE DATE(t.TANGGAL) = ?
          AND t.id_toko = ?
        GROUP BY COALESCE(p.NAMA_PRODUK, ti.NAMA_PRODUK)
        ORDER BY TOTAL_TERJUAL DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $tanggal, $id_toko);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = [
        "TANGGAL"         => $row["TANGGAL"],
        "NAMA_PRODUK"     => $row["NAMA_PRODUK"],
        "TOTAL_TERJUAL"   => (int)$row["TOTAL_TERJUAL"],
        "TOTAL_PENJUALAN" => (int)$row["TOTAL_PENJUALAN"]
    ];
}

echo json_encode($data);
?>
