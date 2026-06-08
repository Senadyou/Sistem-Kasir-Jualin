<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

include "../koneksi.php";

function generateToken($length = 64) {
    return bin2hex(random_bytes($length / 2));
}

$data = json_decode(file_get_contents("php://input"), true);
$nama = $data['NAMA'] ?? '';
$password = $data['PASSWORD'] ?? '';

$stmt = $conn->prepare("SELECT * FROM users WHERE NAMA=? LIMIT 1");
$stmt->bind_param("s", $nama);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($user && password_verify($password, $user['PASSWORD'])) {

    // ✅ ambil token lama, atau buat baru kalau kosong
    $token = $user['api_token'];
    if (empty($token)) {
        $token = generateToken();
        $update = $conn->prepare("UPDATE users SET api_token=? WHERE USER_ID=?");
        $update->bind_param("si", $token, $user['USER_ID']);
        $update->execute();
    }

    echo json_encode([
        "status" => true,
        "message" => "Login berhasil",
        "USER_ID" => $user['USER_ID'],
        "NAMA" => $user['NAMA'],
        "ROLE" => $user['ROLE'],
        "id_toko" => $user['id_toko'], // ✅ kirim id_toko ke frontend
        "token" => $token
    ]);
    
} else {
    echo json_encode([
        "status" => false,
        "message" => "Nama atau password salah"
    ]);
}
