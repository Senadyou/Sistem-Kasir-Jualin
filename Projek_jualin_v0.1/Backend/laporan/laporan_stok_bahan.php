<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

require_once __DIR__ . '/../koneksi.php';
if (!isset($conn)) { http_response_code(500); die(json_encode(["error" => "Koneksi DB gagal"])); }

$type    = isset($_GET['type'])    ? trim($_GET['type'])      : 'stok';
$id_toko = isset($_GET['id_toko']) ? intval($_GET['id_toko']) : 0;

if (!$id_toko) {
    echo json_encode(["error" => "id_toko wajib diisi"]);
    exit;
}

// ── STOK SEMUA BAHAN (dengan info pemakaian total) ──
if ($type === 'stok') {
    $sql = "SELECT
                bb.id_bahan,
                bb.nama_bahan,
                bb.satuan,
                bb.stok,
                bb.stok_minimum,
                bb.created_at,
                COALESCE(SUM(ti.JUMLAH * rp.jumlah_pakai), 0) AS total_terpakai,
                COUNT(DISTINCT rp.KODE_PRODUK)                 AS jumlah_produk_pakai
            FROM bahan_baku bb
            LEFT JOIN resep_produk    rp ON rp.id_bahan       = bb.id_bahan
            LEFT JOIN transaksi_items ti ON ti.KODE_PRODUK    = rp.KODE_PRODUK
            LEFT JOIN transaksi        t ON t.KODE_TRANSAKSI  = ti.KODE_TRANSAKSI
                                        AND t.id_toko         = bb.id_toko
            WHERE bb.id_toko = ?
            GROUP BY bb.id_bahan, bb.nama_bahan, bb.satuan, bb.stok, bb.stok_minimum, bb.created_at
            ORDER BY bb.nama_bahan ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_toko);
    $stmt->execute();
    $rows = [];
    $res  = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    echo json_encode($rows);
    exit;
}

// ── PEMAKAIAN HARIAN (7 / 30 hari terakhir, per bahan) ──
if ($type === 'pemakaian_harian') {
    $sql = "SELECT
                DATE(t.TANGGAL)                                AS tanggal,
                bb.id_bahan,
                bb.nama_bahan,
                bb.satuan,
                COALESCE(SUM(ti.JUMLAH * rp.jumlah_pakai), 0) AS jumlah_pakai
            FROM bahan_baku bb
            JOIN resep_produk    rp ON rp.id_bahan       = bb.id_bahan
            JOIN transaksi_items ti ON ti.KODE_PRODUK    = rp.KODE_PRODUK
            JOIN transaksi        t  ON t.KODE_TRANSAKSI = ti.KODE_TRANSAKSI
                                    AND t.id_toko        = bb.id_toko
            WHERE bb.id_toko = ?
              AND t.TANGGAL >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY DATE(t.TANGGAL), bb.id_bahan, bb.nama_bahan, bb.satuan
            ORDER BY tanggal ASC, bb.nama_bahan ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_toko);
    $stmt->execute();
    $rows = [];
    $res  = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    echo json_encode($rows);
    exit;
}

// ── RINGKASAN PEMAKAIAN PER BAHAN (total per bahan) ──
if ($type === 'ringkasan_pemakaian') {
    $start = isset($_GET['start']) ? $_GET['start'] : null;
    $end   = isset($_GET['end'])   ? $_GET['end']   : null;

    $sql = "SELECT
                bb.id_bahan,
                bb.nama_bahan,
                bb.satuan,
                bb.stok,
                bb.stok_minimum,
                COALESCE(SUM(ti.JUMLAH * rp.jumlah_pakai), 0) AS total_terpakai,
                COUNT(DISTINCT DATE(t.TANGGAL))                AS hari_aktif
            FROM bahan_baku bb
            JOIN resep_produk    rp ON rp.id_bahan       = bb.id_bahan
            JOIN transaksi_items ti ON ti.KODE_PRODUK    = rp.KODE_PRODUK
            JOIN transaksi        t  ON t.KODE_TRANSAKSI = ti.KODE_TRANSAKSI
                                    AND t.id_toko        = bb.id_toko
            WHERE bb.id_toko = ?";
    $params = [$id_toko];
    $types  = "i";

    if ($start) { $sql .= " AND DATE(t.TANGGAL) >= ?"; $params[] = $start; $types .= "s"; }
    if ($end)   { $sql .= " AND DATE(t.TANGGAL) <= ?"; $params[] = $end;   $types .= "s"; }

    $sql .= " GROUP BY bb.id_bahan, bb.nama_bahan, bb.satuan, bb.stok, bb.stok_minimum
              ORDER BY total_terpakai DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = [];
    $res  = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    echo json_encode($rows);
    exit;
}

echo json_encode(["error" => "type '$type' tidak dikenali"]);
$conn->close();
?>
