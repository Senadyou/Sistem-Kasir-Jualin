<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: GET, POST, DELETE");
header("Access-Control-Allow-Headers: Content-Type");

include "../koneksi.php";

$method = $_SERVER['REQUEST_METHOD'];

// ── GET: ambil semua user di suatu toko ──
if ($method === "GET") {
    $id_toko = $_GET['id_toko'] ?? null;
    if (!$id_toko) {
        echo json_encode(["error" => "id_toko wajib diisi"]);
        exit;
    }
    // ✅ Tabel users
    $stmt = $conn->prepare("SELECT USER_ID, NAMA, ROLE FROM users WHERE id_toko = ? ORDER BY ROLE, NAMA");
    $stmt->bind_param("i", $id_toko);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    echo json_encode($data);
    exit;
}

// ── POST: tambah user baru ke toko tertentu ──
if ($method === "POST") {
    $data    = json_decode(file_get_contents("php://input"), true);
    $nama    = trim($data['nama'] ?? '');
    $pass    = trim($data['password'] ?? '');
    $role    = $data['role'] ?? 'Kasir';
    if (!in_array($role, ['Kasir', 'Admin', 'Owner'])) { $role = 'Kasir'; }
    $id_toko = $data['id_toko'] ?? null;

    if (!$nama || !$pass || !$id_toko) {
        echo json_encode(["status" => false, "message" => "Nama, password, dan id_toko wajib diisi"]);
        exit;
    }

    // Cek duplikat nama ✅
    $cek = $conn->prepare("SELECT USER_ID FROM users WHERE NAMA = ?");
    $cek->bind_param("s", $nama);
    $cek->execute();
    if ($cek->get_result()->num_rows > 0) {
        echo json_encode(["status" => false, "message" => "Nama '$nama' sudah digunakan"]);
        exit;
    }

    // ✅ Pakai password_hash bcrypt (sama dengan login.php)
    $pass_hash = password_hash($pass, PASSWORD_BCRYPT);
    $token     = bin2hex(random_bytes(32));

    $stmt = $conn->prepare("INSERT INTO users (NAMA, PASSWORD, ROLE, id_toko, api_token) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssis", $nama, $pass_hash, $role, $id_toko, $token);
    $stmt->execute();

    echo json_encode(["status" => true, "message" => "User berhasil ditambahkan"]);
    exit;
}

// ── DELETE: hapus user ──
if ($method === "DELETE") {
    $data    = json_decode(file_get_contents("php://input"), true);
    $user_id = $data['USER_ID'] ?? null;
    if (!$user_id) {
        echo json_encode(["status" => false, "message" => "USER_ID wajib diisi"]);
        exit;
    }
    // ✅ Tabel users
    $stmt = $conn->prepare("DELETE FROM users WHERE USER_ID = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    echo json_encode(["status" => true, "message" => "User berhasil dihapus"]);
    exit;
}

echo json_encode(["status" => false, "message" => "Method tidak dikenali"]);
$conn->close();
?>