<?php
include "../koneksi.php";
require_once __DIR__ . "/../fpdf/fpdf.php";

$type    = $_GET['type']    ?? 'pdf';
$id_toko = $_GET['id_toko'] ?? null;

if (!$id_toko) {
    die("id_toko tidak ditemukan. Silakan login ulang.");
}

// ===== AMBIL NAMA TOKO =====
$stmtToko = $conn->prepare("SELECT nama_toko FROM toko WHERE id_toko = ?");
$stmtToko->bind_param("i", $id_toko);
$stmtToko->execute();
$toko      = $stmtToko->get_result()->fetch_assoc();
$nama_toko = $toko ? $toko['nama_toko'] : "Toko";

// ===== AMBIL DATA PENJUALAN HARIAN =====
$sql = "SELECT
            DATE(t.TANGGAL)                         AS TANGGAL,
            COUNT(DISTINCT t.KODE_TRANSAKSI)        AS TOTAL_TRANSAKSI,
            SUM(ti.JUMLAH)                          AS TOTAL_PRODUK_TERJUAL,
            SUM(ti.JUMLAH * ti.HARGA_JUAL)          AS TOTAL_PENJUALAN
        FROM transaksi t
        JOIN transaksi_items ti ON t.KODE_TRANSAKSI = ti.KODE_TRANSAKSI
        WHERE t.id_toko = ?
        GROUP BY DATE(t.TANGGAL)
        ORDER BY TANGGAL ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_toko);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}
$conn->close();

// ===== HELPER =====
function fmtTgl($str) {
    $bulan = ["Jan","Feb","Mar","Apr","Mei","Jun","Jul","Agu","Sep","Okt","Nov","Des"];
    $ts    = strtotime($str);
    return date("d", $ts) . " " . $bulan[intval(date("m", $ts)) - 1] . " " . date("Y", $ts);
}
function fmtRp($angka) {
    return "Rp " . number_format($angka, 0, ',', '.');
}

// ===== GRAND TOTAL =====
$grandTrx    = array_sum(array_column($data, 'TOTAL_TRANSAKSI'));
$grandProduk = array_sum(array_column($data, 'TOTAL_PRODUK_TERJUAL'));
$grandJual   = array_sum(array_column($data, 'TOTAL_PENJUALAN'));

// ===================================================
// ===== EXPORT EXCEL =====
// ===================================================
if ($type === 'excel') {
    header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
    header("Content-Disposition: attachment; filename=laporan_penjualan_harian_" . date("Ymd") . ".xls");
    header("Pragma: no-cache");

    echo "\xEF\xBB\xBF"; // BOM UTF-8

    echo "LAPORAN PENJUALAN PRODUK HARIAN\n";
    echo "Toko\t: " . $nama_toko . "\n";
    echo "Dicetak\t: " . date("d/m/Y H:i") . "\n\n";

    echo "Tanggal\tTotal Transaksi\tTotal Produk Terjual\tTotal Penjualan\n";
    foreach ($data as $row) {
        echo $row['TANGGAL'] . "\t"
            . $row['TOTAL_TRANSAKSI'] . "\t"
            . $row['TOTAL_PRODUK_TERJUAL'] . "\t"
            . $row['TOTAL_PENJUALAN'] . "\n";
    }
    echo "\nTOTAL\t" . $grandTrx . "\t" . $grandProduk . "\t" . $grandJual . "\n";
    exit;
}

// ===================================================
// ===== EXPORT PDF =====
// ===================================================
class PDF extends FPDF {
    public $judulLaporan = '';
    public $namaToko     = '';

    function Header() {
        $this->SetFillColor(56, 142, 60);
        $this->Rect(0, 0, 210, 22, 'F');

        $this->SetFont('Arial', 'B', 14);
        $this->SetTextColor(255, 255, 255);
        $this->SetXY(10, 5);
        $this->Cell(0, 8, 'JUALIN - ' . strtoupper($this->judulLaporan), 0, 1, 'C');

        $this->SetFont('Arial', '', 9);
        $this->SetXY(10, 13);
        $this->Cell(0, 6, $this->namaToko, 0, 1, 'C');

        $this->SetTextColor(30, 30, 30);
        $this->Ln(6);
    }

    function Footer() {
        $this->SetY(-13);
        $this->SetFillColor(56, 142, 60);
        $this->Rect(0, $this->GetY(), 210, 15, 'F');
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(0, 10,
            'Dicetak: ' . date("d/m/Y H:i") . '   |   Hal. ' . $this->PageNo(),
            0, 0, 'C'
        );
    }
}

$pdf = new PDF('P', 'mm', 'A4');
$pdf->judulLaporan = 'Laporan Penjualan Produk Harian';
$pdf->namaToko     = $nama_toko;
$pdf->SetMargins(14, 28, 14);
$pdf->SetAutoPageBreak(true, 20);
$pdf->AddPage();

// ----- INFO CETAK -----
$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell(0, 5, 'Tanggal Cetak: ' . date("d/m/Y H:i"), 0, 1, 'R');
$pdf->Ln(2);

// ----- STAT BOX (3 kotak berjajar) -----
// colW = (210 - 28margin) / 3 = 60.67
$colW      = (210 - 28) / 3;
$statData  = [
    ['label' => 'Total Hari Data',      'value' => count($data) . ' Hari',                'color' => [30, 136, 229]],
    ['label' => 'Total Transaksi',      'value' => number_format($grandTrx, 0, ',', '.'), 'color' => [245, 124, 0]],
    ['label' => 'Total Produk Terjual', 'value' => number_format($grandProduk, 0, ',', '.'), 'color' => [56, 142, 60]],
];
$yStatStart = $pdf->GetY();

foreach ($statData as $i => $s) {
    [$r, $g, $b] = $s['color'];
    $xBox = 14 + ($i * $colW);

    $pdf->SetFillColor($r, $g, $b);
    $pdf->Rect($xBox, $yStatStart, $colW - 3, 18, 'F');

    $pdf->SetXY($xBox, $yStatStart + 3);
    $pdf->SetFont('Arial', '', 7);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell($colW - 3, 5, strtoupper($s['label']), 0, 0, 'C');

    $pdf->SetXY($xBox, $yStatStart + 9);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell($colW - 3, 7, $s['value'], 0, 0, 'C');
}

// Kotak total penjualan full-width
$pdf->SetXY(14, $yStatStart + 21);
$yS = $pdf->GetY();
$pdf->SetFillColor(123, 31, 162);
$pdf->Rect(14, $yS, 182, 16, 'F');

$pdf->SetXY(14, $yS + 2);
$pdf->SetFont('Arial', '', 8);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(182, 5, 'TOTAL PENJUALAN KESELURUHAN', 0, 0, 'C');

$pdf->SetXY(14, $yS + 8);
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(182, 6, fmtRp($grandJual), 0, 0, 'C');

$pdf->SetXY(14, $yS + 20);
$pdf->Ln(4);

// ----- TABEL -----
// Kolom: No(8) + Tanggal(38) + Total Trx(36) + Produk Terjual(40) + Total Penjualan(60) = 182mm ✅
$cols = [
    ['label' => 'No',                  'w' => 8,  'align' => 'C'],
    ['label' => 'Tanggal',             'w' => 38, 'align' => 'L'],
    ['label' => 'Total Transaksi',     'w' => 36, 'align' => 'C'],
    ['label' => 'Produk Terjual',      'w' => 40, 'align' => 'C'],
    ['label' => 'Total Penjualan',     'w' => 60, 'align' => 'R'],
];

// Header kolom
$pdf->SetFillColor(56, 142, 60);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 9);
foreach ($cols as $c) {
    $pdf->Cell($c['w'], 8, $c['label'], 0, 0, $c['align'], true);
}
$pdf->Ln();

// Baris data
$pdf->SetFont('Arial', '', 9);
$no = 1;
foreach ($data as $row) {
    if ($no % 2 === 0) {
        $pdf->SetFillColor(232, 245, 233);
    } else {
        $pdf->SetFillColor(255, 255, 255);
    }
    $pdf->SetTextColor(30, 30, 30);

    $pdf->Cell($cols[0]['w'], 7, $no,                                                  0, 0, 'C', true);
    $pdf->Cell($cols[1]['w'], 7, fmtTgl($row['TANGGAL']),                              0, 0, 'L', true);
    $pdf->Cell($cols[2]['w'], 7, number_format($row['TOTAL_TRANSAKSI'], 0, ',', '.'),  0, 0, 'C', true);
    $pdf->Cell($cols[3]['w'], 7, number_format($row['TOTAL_PRODUK_TERJUAL'], 0, ',', '.'), 0, 0, 'C', true);
    $pdf->Cell($cols[4]['w'], 7, fmtRp($row['TOTAL_PENJUALAN']),                      0, 0, 'R', true);
    $pdf->Ln();
    $no++;
}

// Baris total
$pdf->SetFillColor(56, 142, 60);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell($cols[0]['w'] + $cols[1]['w'], 8, 'TOTAL KESELURUHAN',                   0, 0, 'L', true);
$pdf->Cell($cols[2]['w'],                 8, number_format($grandTrx, 0, ',', '.'), 0, 0, 'C', true);
$pdf->Cell($cols[3]['w'],                 8, number_format($grandProduk, 0, ',', '.'), 0, 0, 'C', true);
$pdf->Cell($cols[4]['w'],                 8, fmtRp($grandJual),                     0, 0, 'R', true);
$pdf->Ln();

// ===== OUTPUT =====
$filename = "laporan_penjualan_harian_" . date("Ymd_His") . ".pdf";
$pdf->Output('D', $filename);
?>
