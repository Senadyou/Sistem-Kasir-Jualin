<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

include "../koneksi.php"; // ✅ path benar

$data = json_decode(file_get_contents("php://input"), true);

$nama    = $data['nama']    ?? "";
$id_toko = $data['id_toko'] ?? null; // ✅ filter by toko

// ✅ Cari produk hanya dalam toko yang sama
if ($id_toko) {
    $stmt = $conn->prepare("SELECT * FROM produk WHERE NAMA_PRODUK LIKE ? AND id_toko=?");
    $search = "%$nama%";
    $stmt->bind_param("si", $search, $id_toko);
} else {
    $stmt = $conn->prepare("SELECT * FROM produk WHERE NAMA_PRODUK LIKE ?");
    $search = "%$nama%";
    $stmt->bind_param("s", $search);
}

$stmt->execute();
$result = $stmt->get_result();

$produk = [];
while ($row = $result->fetch_assoc()) {
    $produk[] = $row;
}

if (count($produk) > 0) {
    echo json_encode([
        "status" => "success",
        "jumlah" => count($produk),
        "data"   => $produk
    ]);
} else {
    echo json_encode([
        "status"  => "not_found",
        "message" => "Produk dengan nama '$nama' tidak ditemukan"
    ]);
}
?>
