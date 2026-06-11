<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
include "../../koneksi.php";

$id      = intval($_GET['id']      ?? 0);
$id_toko = intval($_GET['id_toko'] ?? 0);

if (!$id || !$id_toko) {
    echo json_encode(["status" => "error", "message" => "id dan id_toko wajib diisi"]);
    exit;
}

// Cek record ada di arus_kas DAN milik toko ini
$cek = $conn->prepare("SELECT ID_KAS FROM arus_kas WHERE ID_KAS = ? AND id_toko = ?");
$cek->bind_param("ii", $id, $id_toko);
$cek->execute();
if ($cek->get_result()->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "Data ini tidak bisa dihapus (bukan input manual atau bukan milik toko ini)"]);
    exit;
}

$stmt = $conn->prepare("DELETE FROM arus_kas WHERE ID_KAS = ? AND id_toko = ?");
$stmt->bind_param("ii", $id, $id_toko);
if ($stmt->execute()) {
    echo json_encode(["status" => "success"]);
} else {
    echo json_encode(["status" => "error", "message" => $conn->error]);
}
?>
