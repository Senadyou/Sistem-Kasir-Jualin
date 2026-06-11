<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type");

require_once __DIR__ . '/../koneksi.php';
if (!isset($conn)) { http_response_code(500); die(json_encode(["error" => "Koneksi DB gagal"])); }

$method  = $_SERVER['REQUEST_METHOD'];
$id_toko = isset($_GET['id_toko']) ? intval($_GET['id_toko']) : 0;

// ── GET: ambil semua bahan baku milik toko ──
if ($method === 'GET') {
    if (!$id_toko) {
        echo json_encode(["error" => "id_toko wajib diisi"]);
        exit;
    }

    $sql  = "SELECT * FROM bahan_baku WHERE id_toko = ? ORDER BY nama_bahan ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_toko);
    $stmt->execute();
    $res  = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    echo json_encode($rows);
    exit;
}

// ── POST: tambah bahan baku ──
if ($method === 'POST') {
    $data        = json_decode(file_get_contents("php://input"), true);
    $id_toko     = intval($data['id_toko']     ?? 0);
    $nama_bahan  = trim($data['nama_bahan']    ?? '');
    $satuan      = trim($data['satuan']        ?? '');
    $stok        = floatval($data['stok']      ?? 0);
    $stok_min    = floatval($data['stok_minimum'] ?? 0);

    if (!$id_toko || !$nama_bahan || !$satuan) {
        echo json_encode(["status" => false, "message" => "id_toko, nama_bahan, dan satuan wajib diisi"]);
        exit;
    }

    // Cek duplikat nama bahan di toko yang sama
    $cek = $conn->prepare("SELECT id_bahan FROM bahan_baku WHERE id_toko = ? AND nama_bahan = ?");
    $cek->bind_param("is", $id_toko, $nama_bahan);
    $cek->execute();
    if ($cek->get_result()->num_rows > 0) {
        echo json_encode(["status" => false, "message" => "Bahan '$nama_bahan' sudah ada"]);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO bahan_baku (id_toko, nama_bahan, satuan, stok, stok_minimum) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issdd", $id_toko, $nama_bahan, $satuan, $stok, $stok_min);
    $stmt->execute();

    echo json_encode([
        "status"   => true,
        "message"  => "Bahan baku berhasil ditambahkan",
        "id_bahan" => $conn->insert_id
    ]);
    exit;
}

// ── PUT: edit bahan baku ──
if ($method === 'PUT') {
    $data       = json_decode(file_get_contents("php://input"), true);
    $id_bahan   = intval($data['id_bahan']     ?? 0);
    $nama_bahan = trim($data['nama_bahan']     ?? '');
    $satuan     = trim($data['satuan']         ?? '');
    $stok       = floatval($data['stok']       ?? 0);
    $stok_min   = floatval($data['stok_minimum'] ?? 0);

    if (!$id_bahan || !$nama_bahan || !$satuan) {
        echo json_encode(["status" => false, "message" => "id_bahan, nama_bahan, dan satuan wajib diisi"]);
        exit;
    }

    $stmt = $conn->prepare("UPDATE bahan_baku SET nama_bahan = ?, satuan = ?, stok = ?, stok_minimum = ? WHERE id_bahan = ?");
    $stmt->bind_param("ssddi", $nama_bahan, $satuan, $stok, $stok_min, $id_bahan);
    $stmt->execute();

    echo json_encode(["status" => true, "message" => "Bahan baku berhasil diperbarui"]);
    exit;
}

// ── DELETE: hapus bahan baku ──
if ($method === 'DELETE') {
    $data     = json_decode(file_get_contents("php://input"), true);
    $id_bahan = intval($data['id_bahan'] ?? 0);

    if (!$id_bahan) {
        echo json_encode(["status" => false, "message" => "id_bahan wajib diisi"]);
        exit;
    }

    // Hapus resep yang memakai bahan ini dulu
    $stmt = $conn->prepare("DELETE FROM resep_produk WHERE id_bahan = ?");
    $stmt->bind_param("i", $id_bahan);
    $stmt->execute();

    // Hapus bahan
    $stmt = $conn->prepare("DELETE FROM bahan_baku WHERE id_bahan = ?");
    $stmt->bind_param("i", $id_bahan);
    $stmt->execute();

    echo json_encode(["status" => true, "message" => "Bahan baku berhasil dihapus"]);
    exit;
}

echo json_encode(["status" => false, "message" => "Method tidak dikenali"]);
$conn->close();
?>