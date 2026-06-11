<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

include "../koneksi.php"; // ✅ path benar ke Backend/koneksi.php

if (isset($_GET['kode'])) {
    // Ambil satu produk berdasarkan kode
    $kode = intval($_GET['kode']);
    $stmt = $conn->prepare("SELECT * FROM produk WHERE KODE_PRODUK=?");
    $stmt->bind_param("i", $kode);
    $stmt->execute();
    $result = $stmt->get_result();
    echo json_encode($result->fetch_assoc());

} else {
    // ✅ Filter berdasarkan id_toko agar beda toko beda produk
    $id_toko = $_GET['id_toko'] ?? null;

    if ($id_toko) {
        $stmt = $conn->prepare("SELECT * FROM produk WHERE id_toko=?");
        $stmt->bind_param("i", $id_toko);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query("SELECT * FROM produk");
    }

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    echo json_encode($data);
}
?>
