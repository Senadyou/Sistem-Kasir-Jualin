<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
include "../../koneksi.php";

$id_toko = $_GET['id_toko'] ?? null;
if (!$id_toko) { echo json_encode(["error" => "id_toko wajib ada"]); exit; }

// Arus kas manual milik toko ini
$stmt = $conn->prepare(
    "SELECT ID_KAS as id, TANGGAL as tanggal, KETERANGAN as keterangan,
            JENIS as tipe, JUMLAH as jumlah
     FROM arus_kas WHERE id_toko = ?
     ORDER BY TANGGAL DESC, ID_KAS DESC"
);
$stmt->bind_param("i", $id_toko);
$stmt->execute();
$resKas = $stmt->get_result();
$dataKas = [];
while ($row = $resKas->fetch_assoc()) {
    $row['sumber'] = "manual";
    $dataKas[] = $row;
}

// Transaksi penjualan milik toko ini (otomatis = pemasukan)
$stmt2 = $conn->prepare(
    "SELECT ID_TRANSAKSI as id, DATE(TANGGAL) as tanggal,
            CONCAT('Penjualan #', KODE_TRANSAKSI) as keterangan,
            'Pemasukan' as tipe, TOTAL as jumlah
     FROM transaksi WHERE id_toko = ?
     ORDER BY TANGGAL DESC, ID_TRANSAKSI DESC"
);
$stmt2->bind_param("i", $id_toko);
$stmt2->execute();
$resTrans = $stmt2->get_result();
$dataTrans = [];
while ($row = $resTrans->fetch_assoc()) {
    $row['sumber'] = "penjualan";
    $dataTrans[] = $row;
}

// Gabungkan & urutkan
$data = array_merge($dataKas, $dataTrans);
usort($data, fn($a, $b) => strtotime($b['tanggal']) - strtotime($a['tanggal']));
echo json_encode($data);
?>
