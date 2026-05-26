<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

include "../koneksi.php";

$data = json_decode(file_get_contents("php://input"), true);
$username = $data['username'] ?? '';
$password = $data['password'] ?? '';

if (!$username || !$password) {
    echo json_encode(["status" => false, "message" => "Username dan password wajib diisi"]);
    exit;
}

$sql = "SELECT * FROM superadmin WHERE username = ? AND password = MD5(?) LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $username, $password);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $row = $result->fetch_assoc();
    echo json_encode([
        "status"   => true,
        "id"       => $row['id'],
        "username" => $row['username']
    ]);
} else {
    echo json_encode(["status" => false, "message" => "Username atau password salah"]);
}

$conn->close();
?>
