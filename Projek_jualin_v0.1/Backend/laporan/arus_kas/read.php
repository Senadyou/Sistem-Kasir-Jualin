<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
include "../../koneksi.php";

$id_toko = $_GET['id_toko'] ?? null;
if (!$id_toko) { echo json_encode(["error" => "id_toko wajib ada"]); exit; }

$stmt = $conn->prepare(
    "SELECT * FROM arus_kas WHERE id_toko = ? ORDER BY TANGGAL DESC"
);
$stmt->bind_param("i", $id_toko);
$stmt->execute();
$result = $stmt->get_result();
$data = [];
while ($row = $result->fetch_assoc()) $data[] = $row;
echo json_encode($data);
?>
