<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE");
header("Content-Type: application/json");

include "../koneksi.php"; // ✅ path benar

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kode        = $_POST['KODE_PRODUK'] ?? '';
    $nama        = $_POST['NAMA_PRODUK'] ?? '';
    $harga_modal = $_POST['HARGA_MODAL'] ?? 0;
    $harga_jual  = $_POST['HARGA_JUAL']  ?? 0;
    $stok        = $_POST['STOK']        ?? 0;
    $kategori    = $_POST['KATEGORI']    ?? 'Makanan';
    $id_toko     = $_POST['id_toko']     ?? null; // ✅ ambil id_toko

    // Validasi id_toko wajib ada
    if (empty($id_toko)) {
        echo json_encode(["status" => false, "message" => "id_toko wajib ada, silakan login ulang"]);
        exit;
    }

    // Handle upload gambar
    $gambar = null;
    if (isset($_FILES['GAMBAR']) && $_FILES['GAMBAR']['error'] == 0) {
        $target_dir = "../uploads/produk/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $ext    = pathinfo($_FILES["GAMBAR"]["name"], PATHINFO_EXTENSION);
        $gambar = time() . "_" . uniqid() . "." . $ext;
        if (!move_uploaded_file($_FILES["GAMBAR"]["tmp_name"], $target_dir . $gambar)) {
            echo json_encode(["status" => false, "message" => "Upload gambar gagal"]);
            exit;
        }
    }

    // ✅ INSERT dengan id_toko agar produk terikat ke toko
    $stmt = $conn->prepare(
        "INSERT INTO produk (KODE_PRODUK, NAMA_PRODUK, HARGA_MODAL, HARGA_JUAL, STOK, KATEGORI, GAMBAR, id_toko)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("ssddissi", $kode, $nama, $harga_modal, $harga_jual, $stok, $kategori, $gambar, $id_toko);

    if ($stmt->execute()) {
        echo json_encode(["status" => true, "message" => "Produk ditambahkan", "gambar" => $gambar]);
    } else {
        // Cek duplikat kode produk
        if ($conn->errno == 1062) {
            echo json_encode(["status" => false, "message" => "Kode produk sudah ada, gunakan kode lain"]);
        } else {
            echo json_encode(["status" => false, "message" => "Gagal tambah produk: " . $stmt->error]);
        }
    }
} else {
    echo json_encode(["status" => false, "message" => "Metode request tidak valid"]);
}
?>
