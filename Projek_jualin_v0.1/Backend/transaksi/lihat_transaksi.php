<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
include "../koneksi.php";

$id_toko = $_GET['id_toko'] ?? null; // ✅ wajib untuk multi-toko

if (!$id_toko) {
    echo json_encode(["error" => "id_toko wajib ada"]);
    exit;
}

// ✅ Ambil TOTAL langsung dari tabel transaksi (bukan SUM items)
//    Filter by id_toko agar kasir hanya melihat transaksi tokonya sendiri
$stmt = $conn->prepare(
    "SELECT KODE_TRANSAKSI, TANGGAL, METODE, TOTAL
     FROM transaksi
     WHERE id_toko = ?
     ORDER BY TANGGAL DESC"
);
$stmt->bind_param("i", $id_toko);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = [
        "KODE_TRANSAKSI" => $row["KODE_TRANSAKSI"],
        "TANGGAL"        => $row["TANGGAL"],
        "METODE"         => $row["METODE"],
        "TOTAL"          => floatval($row["TOTAL"])
    ];
}

echo json_encode($data);
?>
