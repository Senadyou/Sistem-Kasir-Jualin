<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: GET, POST, DELETE");
header("Access-Control-Allow-Headers: Content-Type");

require_once __DIR__ . '/../koneksi.php';
if (!isset($conn)) { http_response_code(500); die(json_encode(["error" => "Koneksi DB gagal"])); }

$method = $_SERVER['REQUEST_METHOD'];

// ── GET: ambil resep untuk 1 produk ──
if ($method === 'GET') {
    $kode_produk = isset($_GET['kode_produk']) ? intval($_GET['kode_produk']) : 0;
    $id_toko     = isset($_GET['id_toko'])     ? intval($_GET['id_toko'])     : 0;

    // Jika minta semua produk beserta status punya resep atau tidak (untuk halaman kelola)
    if ($id_toko && !$kode_produk) {
        $sql = "SELECT 
                    p.KODE_PRODUK,
                    p.NAMA_PRODUK,
                    p.KATEGORI,
                    COUNT(rp.id_resep) AS jumlah_bahan
                FROM produk p
                LEFT JOIN resep_produk rp ON rp.KODE_PRODUK = p.KODE_PRODUK
                WHERE p.id_toko = ?
                GROUP BY p.KODE_PRODUK, p.NAMA_PRODUK, p.KATEGORI
                ORDER BY p.NAMA_PRODUK ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id_toko);
        $stmt->execute();
        $rows = [];
        $res  = $stmt->get_result();
        while ($r = $res->fetch_assoc()) $rows[] = $r;
        echo json_encode($rows);
        exit;
    }

    // Ambil detail resep 1 produk
    if (!$kode_produk) {
        echo json_encode(["error" => "kode_produk atau id_toko wajib diisi"]);
        exit;
    }

    $sql = "SELECT 
                rp.id_resep,
                rp.KODE_PRODUK,
                rp.id_bahan,
                rp.jumlah_pakai,
                bb.nama_bahan,
                bb.satuan,
                bb.stok
            FROM resep_produk rp
            JOIN bahan_baku bb ON bb.id_bahan = rp.id_bahan
            WHERE rp.KODE_PRODUK = ?
            ORDER BY bb.nama_bahan ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $kode_produk);
    $stmt->execute();
    $rows = [];
    $res  = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    echo json_encode($rows);
    exit;
}

// ── POST: tambah bahan ke resep produk ──
if ($method === 'POST') {
    $data        = json_decode(file_get_contents("php://input"), true);
    $kode_produk = intval($data['KODE_PRODUK']  ?? 0);
    $id_bahan    = intval($data['id_bahan']      ?? 0);
    $jumlah      = floatval($data['jumlah_pakai'] ?? 0);

    if (!$kode_produk || !$id_bahan || $jumlah <= 0) {
        echo json_encode(["status" => false, "message" => "KODE_PRODUK, id_bahan, dan jumlah_pakai wajib diisi"]);
        exit;
    }

    // Cek duplikat bahan di resep yang sama
    $cek = $conn->prepare("SELECT id_resep FROM resep_produk WHERE KODE_PRODUK = ? AND id_bahan = ?");
    $cek->bind_param("ii", $kode_produk, $id_bahan);
    $cek->execute();
    if ($cek->get_result()->num_rows > 0) {
        echo json_encode(["status" => false, "message" => "Bahan ini sudah ada di resep produk tersebut"]);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO resep_produk (KODE_PRODUK, id_bahan, jumlah_pakai) VALUES (?, ?, ?)");
    $stmt->bind_param("iid", $kode_produk, $id_bahan, $jumlah);
    $stmt->execute();

    echo json_encode(["status" => true, "message" => "Bahan berhasil ditambahkan ke resep"]);
    exit;
}

// ── DELETE: hapus 1 bahan dari resep ──
if ($method === 'DELETE') {
    $data     = json_decode(file_get_contents("php://input"), true);
    $id_resep = intval($data['id_resep'] ?? 0);

    if (!$id_resep) {
        echo json_encode(["status" => false, "message" => "id_resep wajib diisi"]);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM resep_produk WHERE id_resep = ?");
    $stmt->bind_param("i", $id_resep);
    $stmt->execute();

    echo json_encode(["status" => true, "message" => "Bahan berhasil dihapus dari resep"]);
    exit;
}

echo json_encode(["status" => false, "message" => "Method tidak dikenali"]);
$conn->close();
?>