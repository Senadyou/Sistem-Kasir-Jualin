<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

include "../koneksi.php"; // ✅ path benar

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['KODE_PRODUK'])) {
    echo json_encode(["status" => false, "message" => "KODE_PRODUK tidak dikirim"]);
    exit;
}

$kode    = $data['KODE_PRODUK'];
$id_toko = $data['id_toko'] ?? null; // ✅ ambil id_toko untuk keamanan

// ✅ Pastikan produk ada DAN milik toko yang benar
if ($id_toko) {
    $cek = $conn->prepare("SELECT * FROM produk WHERE KODE_PRODUK=? AND id_toko=?");
    $cek->bind_param("si", $kode, $id_toko);
} else {
    $cek = $conn->prepare("SELECT * FROM produk WHERE KODE_PRODUK=?");
    $cek->bind_param("s", $kode);
}
$cek->execute();
$res = $cek->get_result();

if ($res->num_rows === 0) {
    echo json_encode(["status" => false, "message" => "Produk tidak ditemukan atau bukan milik toko ini"]);
    exit;
}
$cek->close();

// Hapus produk
$stmt = $conn->prepare("DELETE FROM produk WHERE KODE_PRODUK=?");
$stmt->bind_param("s", $kode);

if ($stmt->execute()) {
    echo json_encode(["status" => true, "message" => "Produk berhasil dihapus"]);
} else {
    if ($conn->errno == 1451) {
        echo json_encode([
            "status"  => false,
            "message" => "Produk tidak bisa dihapus karena sudah ada di riwayat transaksi."
        ]);
    } else {
        echo json_encode(["status" => false, "message" => "Gagal hapus produk: " . $stmt->error]);
    }
}

$stmt->close();
$conn->close();
?>
