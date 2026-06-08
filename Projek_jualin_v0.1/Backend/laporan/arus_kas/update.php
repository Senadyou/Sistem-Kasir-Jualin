<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
include "../../koneksi.php";

$data = json_decode(file_get_contents("php://input"), true);

$id         = intval($data['id']         ?? 0);
$id_toko    = intval($data['id_toko']    ?? 0);
$tanggal    = $data['tanggal']    ?? '';
$keterangan = $data['keterangan'] ?? '';
$tipe       = $data['tipe']       ?? '';   // Pemasukan / Pengeluaran
$jumlah     = $data['jumlah']     ?? 0;

if (!$id || !$id_toko) {
    echo json_encode(["status" => "error", "message" => "id dan id_toko wajib diisi"]);
    exit;
}

// Pastikan record milik toko ini
$cek = $conn->prepare("SELECT ID_KAS FROM arus_kas WHERE ID_KAS = ? AND id_toko = ?");
$cek->bind_param("ii", $id, $id_toko);
$cek->execute();
if ($cek->get_result()->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "Data tidak ditemukan atau bukan milik toko ini"]);
    exit;
}

$stmt = $conn->prepare(
    "UPDATE arus_kas SET TANGGAL=?, KETERANGAN=?, JENIS=?, JUMLAH=? WHERE ID_KAS=? AND id_toko=?"
);
$stmt->bind_param("sssdii", $tanggal, $keterangan, $tipe, $jumlah, $id, $id_toko);

if ($stmt->execute()) {
    echo json_encode(["status" => "success"]);
} else {
    echo json_encode(["status" => "error", "message" => $conn->error]);
}
?>
