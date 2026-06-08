<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
include "../koneksi.php";

if (!isset($_GET['kode'])) {
    echo json_encode(["status" => "error", "message" => "Kode transaksi tidak diberikan"]);
    exit;
}

$kode    = $_GET['kode'];
$id_toko = $_GET['id_toko'] ?? null; // ✅ validasi kepemilikan

// ✅ Ambil header + TOTAL langsung dari tabel transaksi
if ($id_toko) {
    $stmt = $conn->prepare(
        "SELECT KODE_TRANSAKSI, TANGGAL, METODE, TOTAL
         FROM transaksi WHERE KODE_TRANSAKSI = ? AND id_toko = ?"
    );
    $stmt->bind_param("si", $kode, $id_toko);
} else {
    $stmt = $conn->prepare(
        "SELECT KODE_TRANSAKSI, TANGGAL, METODE, TOTAL
         FROM transaksi WHERE KODE_TRANSAKSI = ?"
    );
    $stmt->bind_param("s", $kode);
}
$stmt->execute();
$header = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$header) {
    echo json_encode(["status" => "error", "message" => "Transaksi tidak ditemukan"]);
    exit;
}

// ✅ Ambil items pakai kolom BARU: NAMA_PRODUK, JUMLAH, HARGA_JUAL
//    subtotal = JUMLAH * HARGA_JUAL (dihitung di query)
//    COALESCE: jika produk sudah dihapus, pakai NAMA_PRODUK snapshot di transaksi_items
$stmtDetail = $conn->prepare(
    "SELECT
        ti.JUMLAH,
        ti.HARGA_JUAL,
        (ti.JUMLAH * ti.HARGA_JUAL)                         AS subtotal,
        COALESCE(p.NAMA_PRODUK, ti.NAMA_PRODUK)             AS nama
     FROM transaksi_items ti
     LEFT JOIN produk p ON ti.KODE_PRODUK = p.KODE_PRODUK
     WHERE ti.KODE_TRANSAKSI = ?"
);
$stmtDetail->bind_param("s", $kode);
$stmtDetail->execute();
$resDetail = $stmtDetail->get_result();
$stmtDetail->close();

$items = [];
while ($row = $resDetail->fetch_assoc()) {
    $items[] = [
        "nama"     => $row["nama"],
        "jumlah"   => intval($row["JUMLAH"]),
        "harga"    => floatval($row["HARGA_JUAL"]),
        "subtotal" => floatval($row["subtotal"])
    ];
}

echo json_encode([
    "KODE_TRANSAKSI" => $header["KODE_TRANSAKSI"],
    "TANGGAL"        => $header["TANGGAL"],
    "METODE"         => $header["METODE"],
    "TOTAL"          => floatval($header["TOTAL"]),
    "ITEMS"          => $items
]);
?>
