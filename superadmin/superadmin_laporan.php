<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");

// Error reporting ke JSON bukan ke HTML
set_error_handler(function($errno, $errstr) {
    echo json_encode(["error" => "PHP Error: $errstr (errno $errno)"]);
    exit;
});

$koneksi_path = __DIR__ . "/../koneksi.php";
if (!file_exists($koneksi_path)) {
    echo json_encode(["error" => "koneksi.php tidak ditemukan di: $koneksi_path"]);
    exit;
}
include $koneksi_path;

if (!isset($conn)) {
    echo json_encode(["error" => "Koneksi DB gagal — variabel \$conn tidak tersedia"]);
    exit;
}
if ($conn->connect_error) {
    echo json_encode(["error" => "DB connect error: " . $conn->connect_error]);
    exit;
}

$type    = isset($_GET['type'])    ? trim($_GET['type'])       : 'ringkasan';
$id_toko = isset($_GET['id_toko']) ? intval($_GET['id_toko'])  : 0;

// ── RINGKASAN SEMUA TOKO ──
if ($type === 'ringkasan') {
    $sql = "SELECT
                t.id_toko,
                t.nama_toko,
                t.kota,
                COALESCE(SUM(ti.JUMLAH * ti.HARGA_JUAL), 0)
                    AS total_penjualan,
                COALESCE(SUM(ti.JUMLAH * ti.HARGA_MODAL), 0)
                    AS total_modal,
                COALESCE(
                    SUM(ti.JUMLAH * ti.HARGA_JUAL)
                    - SUM(ti.JUMLAH * ti.HARGA_MODAL), 0)
                    AS total_laba,
                COUNT(DISTINCT trx.ID_TRANSAKSI) AS total_transaksi
            FROM toko t
            LEFT JOIN transaksi      trx ON trx.id_toko        = t.id_toko
            LEFT JOIN transaksi_items ti  ON ti.KODE_TRANSAKSI = trx.KODE_TRANSAKSI
            GROUP BY t.id_toko, t.nama_toko, t.kota
            ORDER BY total_penjualan DESC";

    $result = $conn->query($sql);
    if (!$result) {
        echo json_encode(["error" => "Query ringkasan gagal: " . $conn->error]);
        exit;
    }
    $rows = [];
    while ($r = $result->fetch_assoc()) $rows[] = $r;
    echo json_encode($rows);
    exit;
}

// Validasi id_toko untuk endpoint lain
if (!$id_toko) {
    echo json_encode(["error" => "id_toko wajib diisi untuk type=$type"]);
    exit;
}

// ── PENJUALAN HARIAN ──
if ($type === 'penjualan_harian') {
    $sql = "SELECT
                DATE(t.TANGGAL)                              AS tanggal,
                COUNT(DISTINCT t.ID_TRANSAKSI)               AS total_transaksi,
                COALESCE(SUM(ti.JUMLAH), 0)                  AS total_produk,
                COALESCE(SUM(ti.JUMLAH * ti.HARGA_JUAL), 0) AS total_penjualan
            FROM transaksi t
            JOIN transaksi_items ti ON ti.KODE_TRANSAKSI = t.KODE_TRANSAKSI
            WHERE t.id_toko = ?
            GROUP BY DATE(t.TANGGAL)
            ORDER BY tanggal ASC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) { echo json_encode(["error" => $conn->error]); exit; }
    $stmt->bind_param("i", $id_toko);
    $stmt->execute();
    $rows = [];
    $res  = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    echo json_encode($rows);
    exit;
}

// ── LABA RUGI HARIAN ──
if ($type === 'laba_rugi_harian') {
    $sql = "SELECT
                DATE(t.TANGGAL)                                            AS tanggal,
                COALESCE(SUM(ti.JUMLAH * ti.HARGA_JUAL),  0)              AS total_penjualan,
                COALESCE(SUM(ti.JUMLAH * ti.HARGA_MODAL), 0)              AS total_modal,
                COALESCE(SUM(ti.JUMLAH * ti.HARGA_JUAL)
                    - SUM(ti.JUMLAH * ti.HARGA_MODAL),     0)             AS laba
            FROM transaksi t
            JOIN transaksi_items ti ON ti.KODE_TRANSAKSI = t.KODE_TRANSAKSI
            WHERE t.id_toko = ?
            GROUP BY DATE(t.TANGGAL)
            ORDER BY tanggal ASC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) { echo json_encode(["error" => $conn->error]); exit; }
    $stmt->bind_param("i", $id_toko);
    $stmt->execute();
    $rows = [];
    $res  = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    echo json_encode($rows);
    exit;
}

// ── PENJUALAN BULANAN ──
if ($type === 'penjualan_bulanan') {
    $sql = "SELECT
                DATE_FORMAT(t.TANGGAL, '%Y-%m')              AS bulan,
                COUNT(DISTINCT t.ID_TRANSAKSI)               AS total_transaksi,
                COALESCE(SUM(ti.JUMLAH), 0)                  AS total_produk,
                COALESCE(SUM(ti.JUMLAH * ti.HARGA_JUAL), 0) AS total_penjualan
            FROM transaksi t
            JOIN transaksi_items ti ON ti.KODE_TRANSAKSI = t.KODE_TRANSAKSI
            WHERE t.id_toko = ?
            GROUP BY DATE_FORMAT(t.TANGGAL, '%Y-%m')
            ORDER BY bulan ASC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) { echo json_encode(["error" => $conn->error]); exit; }
    $stmt->bind_param("i", $id_toko);
    $stmt->execute();
    $rows = [];
    $res  = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    echo json_encode($rows);
    exit;
}

// ── LABA RUGI BULANAN ──
if ($type === 'laba_rugi_bulanan') {
    $sql = "SELECT
                DATE_FORMAT(t.TANGGAL, '%Y-%m')                            AS bulan,
                COALESCE(SUM(ti.JUMLAH * ti.HARGA_JUAL),  0)              AS total_penjualan,
                COALESCE(SUM(ti.JUMLAH * ti.HARGA_MODAL), 0)              AS total_modal,
                COALESCE(SUM(ti.JUMLAH * ti.HARGA_JUAL)
                    - SUM(ti.JUMLAH * ti.HARGA_MODAL),     0)             AS laba
            FROM transaksi t
            JOIN transaksi_items ti ON ti.KODE_TRANSAKSI = t.KODE_TRANSAKSI
            WHERE t.id_toko = ?
            GROUP BY DATE_FORMAT(t.TANGGAL, '%Y-%m')
            ORDER BY bulan ASC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) { echo json_encode(["error" => $conn->error]); exit; }
    $stmt->bind_param("i", $id_toko);
    $stmt->execute();
    $rows = [];
    $res  = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    echo json_encode($rows);
    exit;
}

echo json_encode(["error" => "type '$type' tidak dikenali. Gunakan: ringkasan, penjualan_harian, laba_rugi_harian, penjualan_bulanan, laba_rugi_bulanan"]);
$conn->close();
?>
