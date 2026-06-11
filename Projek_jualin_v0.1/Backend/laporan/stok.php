<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
include "../koneksi.php";

$id_toko = $_GET['id_toko'] ?? null;

if (!$id_toko) {
    echo json_encode(["error" => "id_toko wajib ada"]);
    exit;
}

$stmt = $conn->prepare(
    "SELECT KODE_PRODUK, NAMA_PRODUK, STOK, HARGA_JUAL, GAMBAR
     FROM produk
     WHERE id_toko = ?
     ORDER BY NAMA_PRODUK ASC"
);
$stmt->bind_param("i", $id_toko);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode($data);
?>
