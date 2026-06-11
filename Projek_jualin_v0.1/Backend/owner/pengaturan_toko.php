<?php
// Izinkan akses CORS (Cross-Origin Resource Sharing) agar bisa diakses dari frontend
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Hubungkan ke database (Sesuaikan path dan nama file koneksi Anda jika berbeda)
include_once '../koneksi.php'; 

// Jika variabel koneksi database Anda bernama $koneksi (bukan $conn), 
// silakan ubah $conn menjadi $koneksi pada baris-baris di bawah ini.

$method = $_SERVER['REQUEST_METHOD'];

// 1. METODE GET: Digunakan untuk mengambil data PB1 saat halaman dimuat
if ($method === 'GET') {
    if (isset($_GET['id_toko'])) {
        $id_toko = intval($_GET['id_toko']);
        
        $query = "SELECT persentase_pb1 FROM toko WHERE id_toko = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $id_toko);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            echo json_encode([
                "status" => true,
                "persentase_pb1" => $row['persentase_pb1']
            ]);
        } else {
            echo json_encode(["status" => false, "message" => "Data toko tidak ditemukan."]);
        }
        $stmt->close();
    } else {
        echo json_encode(["status" => false, "message" => "ID Toko tidak diberikan."]);
    }
} 
// 2. METODE POST: Digunakan untuk menyimpan/memperbarui data PB1 dari Owner
elseif ($method === 'POST') {
    // Mengambil raw data JSON dari request body
    $data = json_decode(file_get_contents("php://input"));
    
    // Validasi data yang masuk
    if (!empty($data->id_toko) && isset($data->persentase_pb1)) {
        $id_toko = intval($data->id_toko);
        $pb1 = floatval($data->persentase_pb1); // Konversi ke float agar aman masuk ke DECIMAL(5,2)
        
        $query = "UPDATE toko SET persentase_pb1 = ? WHERE id_toko = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("di", $pb1, $id_toko); // 'd' untuk double/float, 'i' untuk integer
        
        if ($stmt->execute()) {
            echo json_encode(["status" => true, "message" => "Persentase Pajak PB1 berhasil diperbarui."]);
        } else {
            echo json_encode(["status" => false, "message" => "Gagal memperbarui data di database."]);
        }
        $stmt->close();
    } else {
        echo json_encode(["status" => false, "message" => "Data yang dikirim tidak lengkap."]);
    }
} 
// Jika metode HTTP tidak dikenali
else {
    echo json_encode(["status" => false, "message" => "Metode request tidak diizinkan."]);
}

$conn->close();
?>