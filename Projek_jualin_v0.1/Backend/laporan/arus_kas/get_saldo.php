<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
include "../../koneksi.php";

$id_toko = $_GET['id_toko'] ?? null;
if (!$id_toko) { echo json_encode(["error" => "id_toko wajib ada"]); exit; }

// Total penjualan otomatis dari tabel transaksi
$stmt = $conn->prepare("SELECT IFNULL(SUM(TOTAL),0) as total_penjualan FROM transaksi WHERE id_toko = ?");
$stmt->bind_param("i", $id_toko);
$stmt->execute();
$total_penjualan = $stmt->get_result()->fetch_assoc()['total_penjualan'];

// Total pemasukan & pengeluaran manual dari arus_kas
$stmt2 = $conn->prepare(
    "SELECT 
        SUM(CASE WHEN JENIS='Pemasukan'    THEN JUMLAH ELSE 0 END) as total_masuk,
        SUM(CASE WHEN JENIS='Pengeluaran'  THEN JUMLAH ELSE 0 END) as total_keluar
     FROM arus_kas WHERE id_toko = ?"
);
$stmt2->bind_param("i", $id_toko);
$stmt2->execute();
$kas = $stmt2->get_result()->fetch_assoc();

$kas_masuk_manual = $kas['total_masuk']  ?? 0;
$kas_keluar       = $kas['total_keluar'] ?? 0;

$total_pemasukan = $total_penjualan + $kas_masuk_manual;
$saldo           = $total_pemasukan - $kas_keluar;

echo json_encode([
    "total_penjualan" => (int)$total_penjualan,
    "kas_masuk"       => (int)$total_pemasukan,
    "kas_keluar"      => (int)$kas_keluar,
    "saldo"           => (int)$saldo
]);
?>
