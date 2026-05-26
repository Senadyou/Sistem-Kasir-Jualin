<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
include "../koneksi.php";

$id_toko = $_GET['id_toko'] ?? null;
$start   = $_GET['start']   ?? null;
$end     = $_GET['end']     ?? null;

if (!$id_toko) {
    echo json_encode(["error" => "id_toko wajib ada"]);
    exit;
}

// Gunakan prepared statement untuk menghindari SQL injection
$params     = [$id_toko];
$paramTypes = "i";
$where      = "WHERE t.id_toko = ?";

if ($start) {
    $where       .= " AND DATE(t.TANGGAL) >= ?";
    $paramTypes  .= "s";
    $params[]    = $start;
}
if ($end) {
    $where       .= " AND DATE(t.TANGGAL) <= ?";
    $paramTypes  .= "s";
    $params[]    = $end;
}

$sql = "SELECT 
            DATE(t.TANGGAL) AS tanggal,
            SUM(ti.JUMLAH * ti.HARGA_JUAL) AS total_penjualan,
            SUM(ti.JUMLAH * ti.HARGA_MODAL) AS total_modal,
            SUM(ti.JUMLAH * ti.HARGA_JUAL) - SUM(ti.JUMLAH * ti.HARGA_MODAL) AS laba
        FROM transaksi t
        JOIN transaksi_items ti ON t.KODE_TRANSAKSI = ti.KODE_TRANSAKSI
        $where
        GROUP BY DATE(t.TANGGAL)
        ORDER BY tanggal DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($paramTypes, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode($data);
?>
