<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

include "../koneksi.php";

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    // ✅ READ - Ambil user berdasarkan id_toko
    case 'GET':
        $id_toko = $_GET['id_toko'] ?? null;
        if (isset($_GET['id'])) {
            $id = intval($_GET['id']);
            $stmt = $conn->prepare("SELECT USER_ID, NAMA, ROLE, id_toko FROM users WHERE USER_ID=?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            echo json_encode($result->fetch_assoc());
        } elseif ($id_toko) {
            $stmt = $conn->prepare("SELECT USER_ID, NAMA, ROLE, id_toko FROM users WHERE id_toko=?");
            $stmt->bind_param("i", $id_toko);
            $stmt->execute();
            $result = $stmt->get_result();
            $users = [];
            while ($row = $result->fetch_assoc()) { $users[] = $row; }
            echo json_encode($users);
        } else {
            $result = $conn->query("SELECT USER_ID, NAMA, ROLE, id_toko FROM users");
            $users = [];
            while ($row = $result->fetch_assoc()) { $users[] = $row; }
            echo json_encode($users);
        }
        break;











// ✅ CREATE - Tambah user dengan id_toko
case 'POST':
    $raw = file_get_contents("php://input");
    $data = json_decode($raw, true);

    if ($data && isset($data['NAMA'])) {
        $nama     = $data['NAMA'];
        $password = $data['PASSWORD'] ?? '';
        $role     = $data['ROLE'] ?? 'Kasir';
        $id_toko  = $data['id_toko'] ?? null; // ✅ ambil id_toko
    } else {
        $nama     = $_POST['nama'] ?? '';
        $password = $_POST['password'] ?? '';
        $role     = $_POST['role'] ?? 'Kasir';
        $id_toko  = $_POST['id_toko'] ?? null;
    }

    if (empty($nama) || empty($password)) {
        echo json_encode(["status" => false, "message" => "Nama dan password wajib diisi"]);
        break;
    }

    if (empty($id_toko)) {
        echo json_encode(["status" => false, "message" => "id_toko wajib ada"]);
        break;
    }

    $hashed = password_hash($password, PASSWORD_DEFAULT);

    // ✅ INSERT dengan id_toko
    $stmt = $conn->prepare("INSERT INTO users (NAMA, PASSWORD, ROLE, id_toko) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sssi", $nama, $hashed, $role, $id_toko);

    if ($stmt->execute()) {
        echo json_encode(["status" => true, "message" => "User ditambahkan"]);
    } else {
        echo json_encode(["status" => false, "message" => "Gagal tambah user: " . $stmt->error]);
    }
    break;












    // 🔹 UPDATE - Ubah user
    case 'PUT':
        $data = json_decode(file_get_contents("php://input"), true);

        if (!$data || !isset($data['USER_ID'])) {
            echo json_encode(["status" => false, "message" => "USER_ID wajib ada"]);
            break;
        }

        $id = intval($data['USER_ID']);
        $nama = $data['NAMA'];
        $role = $data['ROLE'];

        if (!empty($data['PASSWORD'])) {
            $password = password_hash($data['PASSWORD'], PASSWORD_BCRYPT);
            $stmt = $conn->prepare("UPDATE users SET NAMA=?, PASSWORD=?, ROLE=? WHERE USER_ID=?");
            $stmt->bind_param("sssi", $nama, $password, $role, $id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET NAMA=?, ROLE=? WHERE USER_ID=?");
            $stmt->bind_param("ssi", $nama, $role, $id);
        }

        if ($stmt->execute()) {
            echo json_encode(["status" => true, "message" => "User diupdate"]);
        } else {
            echo json_encode(["status" => false, "message" => "Gagal update: " . $stmt->error]);
        }
        break;











    // 🔹 DELETE - Hapus user
    case 'DELETE':
        $data = json_decode(file_get_contents("php://input"), true);

        if (!$data || !isset($data['USER_ID'])) {
            echo json_encode(["status" => false, "message" => "USER_ID wajib ada"]);
            break;
        }

        $id = intval($data['USER_ID']);

        $stmt = $conn->prepare("DELETE FROM users WHERE USER_ID=?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            echo json_encode(["status" => true, "message" => "User dihapus"]);
        } else {
            echo json_encode(["status" => false, "message" => "Gagal hapus: " . $stmt->error]);
        }
        break;

    default:
        echo json_encode(["message" => "Method tidak diizinkan"]);
}
?>
