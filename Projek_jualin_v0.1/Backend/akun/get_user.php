<?php
session_start();
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

include "../koneksi.php";

// Pastikan user sudah login
if (!isset($_SESSION['USER_ID'])) {
    echo json_encode(["success" => false, "message" => "Belum login"]);
    exit;
}

$user_id = $_SESSION['USER_ID'];

// Ambil data user dari database
$sql = "SELECT NAMA FROM user WHERE ID_USER = '$user_id' LIMIT 1";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo json_encode(["success" => true, "nama" => $row['NAMA']]);
} else {
    echo json_encode(["success" => false, "message" => "User tidak ditemukan"]);
}
?>
