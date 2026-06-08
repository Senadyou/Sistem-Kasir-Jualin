<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
include "../../koneksi.php";

$id_toko = $_GET['id_toko'] ?? null;
if (!$id_toko) { echo json_encode(["error" => "id_toko wajib ada"]); exit; }

$stmt = $conn->prepare(
    "SELECT 
        SUM(CASE WHEN JENIS='Pemasukan'   THEN JUMLAH ELSE 0 END) as total_masuk,
        SUM(CASE WHEN JENIS='Pengeluaran' THEN JUMLAH ELSE 0 END) as total_keluar
     FROM arus_kas WHERE id_toko = ?"
);
$stmt->bind_param("i", $id_toko);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$data['saldo'] = $data['total_masuk'] - $data['total_keluar'];
echo json_encode($data);
?>
