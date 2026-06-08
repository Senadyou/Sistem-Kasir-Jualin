<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");
include "../../koneksi.php";

$data = json_decode(file_get_contents("php://input"), true);
if (!$data) { echo json_encode(["status" => "error", "message" => "Data tidak ditemukan"]); exit; }

$id_toko    = $data['id_toko']    ?? null;
$tanggal    = $data['tanggal']    ?? null;
$keterangan = $data['keterangan'] ?? null;
$tipe       = $data['tipe']       ?? null;  // Pemasukan / Pengeluaran
$jumlah     = $data['jumlah']     ?? null;

if (!$id_toko || !$tanggal || !$keterangan || !$tipe || !$jumlah) {
    echo json_encode(["status" => "error", "message" => "Semua field wajib diisi"]);
    exit;
}

$stmt = $conn->prepare(
    "INSERT INTO arus_kas (id_toko, TANGGAL, KETERANGAN, JENIS, JUMLAH)
     VALUES (?, ?, ?, ?, ?)"
);
$stmt->bind_param("isssd", $id_toko, $tanggal, $keterangan, $tipe, $jumlah);

if ($stmt->execute()) {
    echo json_encode(["status" => "success"]);
} else {
    echo json_encode(["status" => "error", "message" => $conn->error]);
}
?>
