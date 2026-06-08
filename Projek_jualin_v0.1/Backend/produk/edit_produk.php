<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE");
header("Content-Type: application/json");

include "../koneksi.php"; // ✅ path benar

$kode        = $_POST['KODE_PRODUK'] ?? '';
$nama        = $_POST['NAMA_PRODUK'] ?? '';
$harga_modal = $_POST['HARGA_MODAL'] ?? 0;
$harga_jual  = $_POST['HARGA_JUAL']  ?? 0;
$stok        = $_POST['STOK']        ?? 0;
$kategori    = $_POST['KATEGORI']    ?? 'Makanan';
$id_toko     = $_POST['id_toko']     ?? null; // ✅ ambil id_toko untuk keamanan

if (empty($kode)) {
    echo json_encode(["status" => false, "message" => "KODE_PRODUK wajib ada"]);
    exit;
}

// ✅ Pastikan produk milik toko yang sedang login
if ($id_toko) {
    $cek = $conn->prepare("SELECT KODE_PRODUK FROM produk WHERE KODE_PRODUK=? AND id_toko=?");
    $cek->bind_param("si", $kode, $id_toko);
    $cek->execute();
    if ($cek->get_result()->num_rows === 0) {
        echo json_encode(["status" => false, "message" => "Produk tidak ditemukan atau bukan milik toko ini"]);
        exit;
    }
    $cek->close();
}

if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] == 0) {
    // Update dengan gambar baru
    $target_dir = "../uploads/produk/";
    if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

    $ext         = pathinfo($_FILES["gambar"]["name"], PATHINFO_EXTENSION);
    $gambar      = time() . "_" . uniqid() . "." . $ext;
    $target_file = $target_dir . $gambar;

    if (!move_uploaded_file($_FILES["gambar"]["tmp_name"], $target_file)) {
        echo json_encode(["status" => false, "message" => "Upload gambar gagal"]);
        exit;
    }

    $stmt = $conn->prepare(
        "UPDATE produk SET NAMA_PRODUK=?, HARGA_MODAL=?, HARGA_JUAL=?, STOK=?, KATEGORI=?, GAMBAR=?
         WHERE KODE_PRODUK=?"
    );
    $stmt->bind_param("sddssss", $nama, $harga_modal, $harga_jual, $stok, $kategori, $gambar, $kode);
} else {
    // Update tanpa ganti gambar
    $stmt = $conn->prepare(
        "UPDATE produk SET NAMA_PRODUK=?, HARGA_MODAL=?, HARGA_JUAL=?, STOK=?, KATEGORI=?
         WHERE KODE_PRODUK=?"
    );
    $stmt->bind_param("sddsss", $nama, $harga_modal, $harga_jual, $stok, $kategori, $kode);
}

if ($stmt->execute()) {
    echo json_encode(["status" => true, "message" => "Produk diperbarui"]);
} else {
    echo json_encode(["status" => false, "message" => "Gagal update produk: " . $stmt->error]);
}
?>
