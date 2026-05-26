<?php
header("Content-Type: application/json");

include "../koneksi.php"; // ✅ path benar

$headers     = apache_request_headers();
$authHeader  = $headers['Authorization'] ?? '';

if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    echo json_encode(["status" => false, "message" => "Unauthorized: Token tidak ditemukan"]);
    http_response_code(401);
    exit;
}

$token = $matches[1];

// ✅ Cek token dan ambil id_toko sekaligus
$stmt = $conn->prepare("SELECT * FROM users WHERE api_token=? LIMIT 1");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["status" => false, "message" => "Unauthorized: Token tidak valid"]);
    http_response_code(401);
    exit;
}

// Token valid — data user tersedia untuk file yang include auth.php
$authUser = $result->fetch_assoc();
// $authUser['id_toko'] bisa dipakai di file yang include auth.php
?>
