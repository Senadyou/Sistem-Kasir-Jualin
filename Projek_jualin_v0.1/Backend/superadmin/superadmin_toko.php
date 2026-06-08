<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type");

include "../koneksi.php";

$method = $_SERVER['REQUEST_METHOD'];

// ── GET ──
if ($method === "GET") {
    $id = $_GET['id'] ?? null;

    if ($id) {
        $stmt = $conn->prepare("SELECT * FROM toko WHERE id_toko = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        echo json_encode($result->fetch_assoc());
    } else {
        // ✅ Pakai tabel "users" (bukan "user")
        $sql = "SELECT 
                    t.*,
                    COUNT(DISTINCT u.USER_ID) AS jumlah_user,
                    COUNT(DISTINCT p.KODE_PRODUK) AS jumlah_produk
                FROM toko t
                LEFT JOIN users u ON u.id_toko = t.id_toko
                LEFT JOIN produk p ON p.id_toko = t.id_toko
                GROUP BY t.id_toko
                ORDER BY t.id_toko ASC";

        $result = $conn->query($sql);
        if (!$result) {
            echo json_encode(["error" => $conn->error]);
            exit;
        }

        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = [
                "id_toko"       => $row["id_toko"],
                "nama_toko"     => $row["nama_toko"],
                "kota"          => $row["kota"] ?? "",
                "alamat"        => $row["alamat"] ?? "",
                "created_at"    => $row["created_at"] ?? "-",
                "jumlah_user"   => (int)($row["jumlah_user"] ?? 0),
                "jumlah_produk" => (int)($row["jumlah_produk"] ?? 0),
            ];
        }
        echo json_encode($data);
    }
    exit;
}

// ── POST: tambah toko + buat akun admin ──
if ($method === "POST") {
    $data       = json_decode(file_get_contents("php://input"), true);
    $nama_toko  = trim($data['nama_toko'] ?? '');
    $alamat     = trim($data['alamat'] ?? '');
    $kota       = trim($data['kota'] ?? '');
    $admin_nama = trim($data['admin_nama'] ?? '');
    $admin_pass = trim($data['admin_pass'] ?? '');

    if (!$nama_toko || !$admin_nama || !$admin_pass) {
        echo json_encode(["status" => false, "message" => "Nama toko, nama admin, dan password wajib diisi"]);
        exit;
    }

    // Cek duplikat nama admin di tabel users ✅
    $cek = $conn->prepare("SELECT USER_ID FROM users WHERE NAMA = ?");
    $cek->bind_param("s", $admin_nama);
    $cek->execute();
    if ($cek->get_result()->num_rows > 0) {
        echo json_encode(["status" => false, "message" => "Nama admin '$admin_nama' sudah digunakan"]);
        exit;
    }

    $conn->begin_transaction();
    try {
        // 1. Insert toko
        $stmt = $conn->prepare("INSERT INTO toko (nama_toko, alamat, kota) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $nama_toko, $alamat, $kota);
        $stmt->execute();
        $id_toko_baru = $conn->insert_id;

        // 2. Insert akun admin — pakai password_hash (bcrypt) ✅
        $pass_hash = password_hash($admin_pass, PASSWORD_BCRYPT);
        $role      = "Admin";
        $token     = bin2hex(random_bytes(32));
        $stmt2 = $conn->prepare("INSERT INTO users (NAMA, PASSWORD, ROLE, id_toko, api_token) VALUES (?, ?, ?, ?, ?)");
        $stmt2->bind_param("sssis", $admin_nama, $pass_hash, $role, $id_toko_baru, $token);
        $stmt2->execute();

        $conn->commit();
        echo json_encode([
            "status"  => true,
            "message" => "Toko berhasil ditambahkan",
            "id_toko" => $id_toko_baru
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(["status" => false, "message" => "Gagal: " . $e->getMessage()]);
    }
    exit;
}

// ── PUT: edit toko ──
if ($method === "PUT") {
    $data      = json_decode(file_get_contents("php://input"), true);
    $id_toko   = $data['id_toko'] ?? null;
    $nama_toko = trim($data['nama_toko'] ?? '');
    $alamat    = trim($data['alamat'] ?? '');
    $kota      = trim($data['kota'] ?? '');

    if (!$id_toko || !$nama_toko) {
        echo json_encode(["status" => false, "message" => "id_toko dan nama_toko wajib diisi"]);
        exit;
    }

    $stmt = $conn->prepare("UPDATE toko SET nama_toko = ?, alamat = ?, kota = ? WHERE id_toko = ?");
    $stmt->bind_param("sssi", $nama_toko, $alamat, $kota, $id_toko);
    $stmt->execute();
    echo json_encode(["status" => true, "message" => "Toko berhasil diperbarui"]);
    exit;
}

// ── DELETE: hapus toko beserta semua data terkait ──
if ($method === "DELETE") {
    $data    = json_decode(file_get_contents("php://input"), true);
    $id_toko = $data['id_toko'] ?? null;

    if (!$id_toko) {
        echo json_encode(["status" => false, "message" => "id_toko wajib diisi"]);
        exit;
    }

    $conn->begin_transaction();
    try {
        // 1. Hapus transaksi_items (via transaksi milik toko ini)
        //    FK transaksi_items → transaksi (ON DELETE CASCADE sudah ada),
        //    tapi kita hapus manual agar aman di semua konfigurasi
        $conn->query("SET FOREIGN_KEY_CHECKS = 0");

        // Hapus urut dari tabel anak ke induk
        $tables = ["arus_kas", "transaksi_items", "transaksi", "produk", "users"];
        foreach ($tables as $tbl) {
            if ($tbl === "transaksi_items") {
                // transaksi_items tidak punya kolom id_toko langsung,
                // hapus via KODE_TRANSAKSI yang ada di transaksi milik toko ini
                $stmt = $conn->prepare(
                    "DELETE ti FROM transaksi_items ti
                     JOIN transaksi t ON ti.KODE_TRANSAKSI = t.KODE_TRANSAKSI
                     WHERE t.id_toko = ?"
                );
            } else {
                $stmt = $conn->prepare("DELETE FROM `$tbl` WHERE id_toko = ?");
            }
            $stmt->bind_param("i", $id_toko);
            $stmt->execute();
        }

        // Terakhir hapus toko
        $stmt = $conn->prepare("DELETE FROM toko WHERE id_toko = ?");
        $stmt->bind_param("i", $id_toko);
        $stmt->execute();

        $conn->query("SET FOREIGN_KEY_CHECKS = 1");
        $conn->commit();

        echo json_encode(["status" => true, "message" => "Toko dan semua data terkait berhasil dihapus"]);
    } catch (Exception $e) {
        $conn->rollback();
        $conn->query("SET FOREIGN_KEY_CHECKS = 1");
        echo json_encode(["status" => false, "message" => "Gagal menghapus: " . $e->getMessage()]);
    }
    exit;
}

echo json_encode(["status" => false, "message" => "Method tidak dikenali"]);
$conn->close();
?>
