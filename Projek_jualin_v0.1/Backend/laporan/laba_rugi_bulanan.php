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
            DATE_FORMAT(t.TANGGAL, '%Y-%m') AS bulan,
            SUM(ti.JUMLAH * ti.HARGA_JUAL) AS total_penjualan,
            SUM(ti.JUMLAH * ti.HARGA_MODAL) AS total_modal,
            SUM(ti.JUMLAH * ti.HARGA_JUAL) - SUM(ti.JUMLAH * ti.HARGA_MODAL) AS laba
        FROM transaksi t
        JOIN transaksi_items ti ON t.KODE_TRANSAKSI = ti.KODE_TRANSAKSI
        WHERE t.id_toko = ?
        GROUP BY DATE_FORMAT(t.TANGGAL, '%Y-%m')
        ORDER BY bulan DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_toko);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = [
        "bulan"           => $row["bulan"],
        "total_penjualan" => (int)$row["total_penjualan"],
        "total_modal"     => (int)$row["total_modal"],
        "laba"            => (int)$row["laba"]
    ];
}

echo json_encode($data);
?>
