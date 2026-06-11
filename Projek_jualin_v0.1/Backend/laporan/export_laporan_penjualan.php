<?php
require_once __DIR__ . '/../koneksi.php';
if (!isset($conn)) { http_response_code(500); die('Koneksi DB gagal'); }

$type    = isset($_GET['type'])    ? strtolower($_GET['type']) : 'pdf';
$id_toko = isset($_GET['id_toko']) ? intval($_GET['id_toko'])  : 0;

if (!$id_toko) { http_response_code(400); die('id_toko tidak ditemukan. Silakan login ulang.'); }

// ===== AMBIL NAMA TOKO =====
$stmtToko = $conn->prepare("SELECT nama_toko FROM toko WHERE id_toko = ?");
$stmtToko->bind_param("i", $id_toko);
$stmtToko->execute();
$toko      = $stmtToko->get_result()->fetch_assoc();
$nama_toko = $toko ? $toko['nama_toko'] : 'Toko';

// ===== AMBIL DATA PENJUALAN =====
$sql = "SELECT 
            t.KODE_TRANSAKSI,
            COALESCE(p.NAMA_PRODUK, ti.NAMA_PRODUK) AS NAMA_PRODUK,
            ti.JUMLAH,
            (ti.JUMLAH * ti.HARGA_JUAL)             AS TOTAL_HARGA,
            t.TANGGAL
        FROM transaksi t
        JOIN transaksi_items ti ON t.KODE_TRANSAKSI = ti.KODE_TRANSAKSI
        LEFT JOIN produk p ON ti.KODE_PRODUK = p.KODE_PRODUK
        WHERE t.id_toko = ?
        ORDER BY t.TANGGAL DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_toko);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
while ($r = $result->fetch_assoc()) { $rows[] = $r; }
$conn->close();

// ===== HELPER =====
function fmtRp($angka) {
    return "Rp " . number_format($angka, 0, ',', '.');
}

$grandJumlah = array_sum(array_column($rows, 'JUMLAH'));
$grandTotal  = array_sum(array_column($rows, 'TOTAL_HARGA'));
$totalTrx    = count(array_unique(array_column($rows, 'KODE_TRANSAKSI')));

// ===================================================
// ===== EXPORT EXCEL =====
// ===================================================
if ($type === 'excel') {
    header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
    header("Content-Disposition: attachment; filename=laporan_penjualan_" . date("Ymd") . ".xls");
    header("Pragma: no-cache");

    echo "\xEF\xBB\xBF"; // BOM UTF-8 agar Excel tidak rusak

    echo "LAPORAN PENJUALAN\n";
    echo "Toko\t: " . $nama_toko . "\n";
    echo "Dicetak\t: " . date("d/m/Y H:i") . "\n\n";

    echo "Kode Transaksi\tNama Produk\tJumlah\tTotal Harga\tTanggal\n";
    foreach ($rows as $r) {
        echo $r['KODE_TRANSAKSI'] . "\t"
            . $r['NAMA_PRODUK'] . "\t"
            . $r['JUMLAH'] . "\t"
            . $r['TOTAL_HARGA'] . "\t"
            . date("d-m-Y H:i", strtotime($r['TANGGAL'])) . "\n";
    }
    echo "\nTOTAL KESELURUHAN\t\t" . $grandJumlah . "\t" . $grandTotal . "\t\n";
    exit;
}

// ===================================================
// ===== EXPORT PDF (FPDF) =====
// ===================================================
$fpdf_path = __DIR__ . '/../fpdf/fpdf.php';
if (!file_exists($fpdf_path)) {
    http_response_code(500);
    die('FPDF tidak ditemukan di: ' . $fpdf_path);
}

if (ob_get_length()) @ob_end_clean();
require_once $fpdf_path;

class PDF extends FPDF {
    public $judulLaporan = '';
    public $namaToko     = '';

    function Header() {
        $this->SetFillColor(56, 142, 60);
        $this->Rect(0, 0, 297, 22, 'F'); // 297mm = lebar A4 landscape

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
        $this->Rect(0, $this->GetY(), 297, 15, 'F');
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(0, 10,
            'Dicetak: ' . date("d/m/Y H:i") . '   |   Hal. ' . $this->PageNo(),
            0, 0, 'C'
        );
    }
}

$pdf = new PDF('L', 'mm', 'A4'); // Landscape — lebih lebar untuk 6 kolom
$pdf->judulLaporan = 'Laporan Penjualan';
$pdf->namaToko     = $nama_toko;
$pdf->SetMargins(14, 30, 14);
$pdf->SetAutoPageBreak(true, 20);
$pdf->AddPage();

// ----- INFO CETAK -----
$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell(0, 5, 'Tanggal Cetak: ' . date("d/m/Y H:i"), 0, 1, 'R');
$pdf->Ln(2);

// ----- RINGKASAN STAT -----
$statData = [
    ['label' => 'Total Transaksi',   'value' => number_format($totalTrx, 0, ',', '.'),    'color' => [25,118,210]],
    ['label' => 'Total Item Terjual','value' => number_format($grandJumlah, 0, ',', '.'), 'color' => [245,124,0]],
    ['label' => 'Total Penjualan',   'value' => fmtRp($grandTotal),                       'color' => [123,31,162]],
];
$statColW    = (297 - 28) / 3;
$yStatStart  = $pdf->GetY();

foreach ($statData as $i => $s) {
    [$r,$g,$b] = $s['color'];
    $xBox = 14 + ($i * $statColW);

    // ✅ Rect 4 param + style — tanpa parameter ekstra
    $pdf->SetFillColor($r, $g, $b);
    $pdf->Rect($xBox, $yStatStart, $statColW - 3, 18, 'F');

    // Label
    $pdf->SetXY($xBox, $yStatStart + 3);
    $pdf->SetFont('Arial', '', 7);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell($statColW - 3, 5, strtoupper($s['label']), 0, 0, 'C');

    // Nilai
    $pdf->SetXY($xBox, $yStatStart + 9);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell($statColW - 3, 7, $s['value'], 0, 0, 'C');
}

// Pindah cursor ke bawah kotak stat
$pdf->SetXY(14, $yStatStart + 22);
$pdf->Ln(2);

// ----- HEADER TABEL -----
$pdf->SetTextColor(30, 30, 30);
$cols = [
    ['label' => 'No',             'w' => 10, 'align' => 'C'],
    ['label' => 'Kode Transaksi', 'w' => 52, 'align' => 'L'],
    ['label' => 'Nama Produk',    'w' => 80, 'align' => 'L'],
    ['label' => 'Jumlah',         'w' => 22, 'align' => 'C'],
    ['label' => 'Total Harga',    'w' => 48, 'align' => 'R'],
    ['label' => 'Tanggal',        'w' => 47, 'align' => 'C'],
];

$pdf->SetFillColor(56, 142, 60);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 9);
foreach ($cols as $c) {
    $pdf->Cell($c['w'], 8, $c['label'], 0, 0, $c['align'], true);
}
$pdf->Ln();

// ----- BARIS DATA -----
$pdf->SetFont('Arial', '', 8);
$no = 1;
foreach ($rows as $r) {
    $pdf->SetFillColor($no % 2 === 0 ? 232 : 255, $no % 2 === 0 ? 245 : 255, $no % 2 === 0 ? 233 : 255);
    $pdf->SetTextColor(30, 30, 30);

    $nama = mb_strlen($r['NAMA_PRODUK']) > 45
        ? mb_substr($r['NAMA_PRODUK'], 0, 42) . '...'
        : $r['NAMA_PRODUK'];

    $pdf->Cell($cols[0]['w'], 7, $no,                                             0, 0, 'C', true);
    $pdf->Cell($cols[1]['w'], 7, $r['KODE_TRANSAKSI'],                            0, 0, 'L', true);
    $pdf->Cell($cols[2]['w'], 7, $nama,                                           0, 0, 'L', true);
    $pdf->Cell($cols[3]['w'], 7, $r['JUMLAH'],                                    0, 0, 'C', true);
    $pdf->Cell($cols[4]['w'], 7, fmtRp($r['TOTAL_HARGA']),                        0, 0, 'R', true);
    $pdf->Cell($cols[5]['w'], 7, date("d/m/Y H:i", strtotime($r['TANGGAL'])),     0, 0, 'C', true);
    $pdf->Ln();
    $no++;
}

// ----- BARIS TOTAL -----
$pdf->SetFillColor(56, 142, 60);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 9);
$labelW = $cols[0]['w'] + $cols[1]['w'] + $cols[2]['w'];
$pdf->Cell($labelW,        8, 'TOTAL KESELURUHAN',                   0, 0, 'L', true);
$pdf->Cell($cols[3]['w'], 8, number_format($grandJumlah, 0, ',', '.'), 0, 0, 'C', true);
$pdf->Cell($cols[4]['w'], 8, fmtRp($grandTotal),                     0, 0, 'R', true);
$pdf->Cell($cols[5]['w'], 8, '',                                     0, 0, 'C', true);
$pdf->Ln();

// ===== OUTPUT — force download =====
$pdf->Output('D', 'laporan_penjualan_' . date("Ymd_His") . '.pdf');
?>
