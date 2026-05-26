<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

include "../koneksi.php";

function generateToken($length = 64) {
    return bin2hex(random_bytes($length / 2));
}

$data = json_decode(file_get_contents("php://input"), true);
$nama = $data['NAMA'] ?? '';
$password = $data['PASSWORD'] ?? '';
$role = $data['ROLE'] ?? 'user';

if (empty($nama) || empty($password)) {
    echo json_encode(["status" => false, "message" => "Nama dan password wajib diisi"]);
    exit;
}

// hash password
$hashed = password_hash($password, PASSWORD_DEFAULT);

// generate token sekali saja
$token = generateToken();

// simpan user
$stmt = $conn->prepare("INSERT INTO users (NAMA, PASSWORD, ROLE, api_token) VALUES (?,?,?,?)");
$stmt->bind_param("ssss", $nama, $hashed, $role, $token);

if ($stmt->execute()) {
    echo json_encode([
        "status" => true,
        "message" => "Registrasi berhasil",
        "token" => $token
    ]);
} else {
    echo json_encode(["status" => false, "message" => "Registrasi gagal"]);
}
