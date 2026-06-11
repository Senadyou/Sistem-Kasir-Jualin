<?php
require_once __DIR__ . '/../koneksi.php';
if (!isset($conn)) { http_response_code(500); die('Koneksi DB gagal'); }

$type    = isset($_GET['type'])    ? strtolower($_GET['type']) : 'pdf';
$id_toko = isset($_GET['id_toko']) ? intval($_GET['id_toko'])  : 0;

if (!$id_toko) { http_response_code(400); die('id_toko tidak ditemukan. Silakan login ulang.'); }

// ===== NAMA TOKO =====
$stmtToko = $conn->prepare("SELECT nama_toko FROM toko WHERE id_toko = ?");
$stmtToko->bind_param("i", $id_toko);
$stmtToko->execute();
$toko      = $stmtToko->get_result()->fetch_assoc();
$nama_toko = $toko ? $toko['nama_toko'] : 'Toko';

// ===== DATA LABA RUGI BULANAN =====
$sql = "SELECT
            DATE_FORMAT(t.TANGGAL, '%Y-%m')             AS bulan,
            SUM(ti.JUMLAH * ti.HARGA_JUAL)              AS total_penjualan,
            SUM(ti.JUMLAH * ti.HARGA_MODAL)             AS total_modal,
            SUM(ti.JUMLAH * ti.HARGA_JUAL)
              - SUM(ti.JUMLAH * ti.HARGA_MODAL)         AS laba
        FROM transaksi t
        JOIN transaksi_items ti ON t.KODE_TRANSAKSI = ti.KODE_TRANSAKSI
        WHERE t.id_toko = ?
        GROUP BY DATE_FORMAT(t.TANGGAL, '%Y-%m')
        ORDER BY bulan ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_toko);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
while ($r = $result->fetch_assoc()) { $rows[] = $r; }
$conn->close();

// ===== HELPER =====
function fmtRp($angka) {
    return "Rp " . number_format(abs($angka), 0, ',', '.');
}
function fmtBulan($str) {
    $names = ["Jan","Feb","Mar","Apr","Mei","Jun","Jul","Agu","Sep","Okt","Nov","Des"];
    $parts = explode("-", $str);
    if (count($parts) < 2) return $str;
    return $names[intval($parts[1]) - 1] . " " . $parts[0];
}

$grandPenjualan = array_sum(array_column($rows, 'total_penjualan'));
$grandModal     = array_sum(array_column($rows, 'total_modal'));
$grandLaba      = array_sum(array_column($rows, 'laba'));
$isRugiTotal    = $grandLaba < 0;

// ===================================================
// EXPORT EXCEL
// ===================================================
if ($type === 'excel') {
    header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
    header("Content-Disposition: attachment; filename=laporan_laba_rugi_bulanan_" . date("Ymd") . ".xls");
    header("Pragma: no-cache");
    echo "\xEF\xBB\xBF";

    echo "LAPORAN LABA RUGI BULANAN\n";
    echo "Toko\t: " . $nama_toko . "\n";
    echo "Dicetak\t: " . date("d/m/Y H:i") . "\n";
    echo "Total Bulan\t: " . count($rows) . " bulan\n\n";

    echo "Bulan\tTotal Penjualan (Rp)\tTotal Modal (Rp)\tLaba / Rugi (Rp)\tStatus\n";
    foreach ($rows as $r) {
        $status = ($r['laba'] >= 0) ? "UNTUNG" : "RUGI";
        echo fmtBulan($r['bulan']) . "\t"
            . number_format($r['total_penjualan'], 0, ',', '.') . "\t"
            . number_format($r['total_modal'],     0, ',', '.') . "\t"
            . number_format($r['laba'],            0, ',', '.') . "\t"
            . $status . "\n";
    }
    echo "\nTOTAL KESELURUHAN\t"
        . number_format($grandPenjualan, 0, ',', '.') . "\t"
        . number_format($grandModal,     0, ',', '.') . "\t"
        . number_format($grandLaba,      0, ',', '.') . "\t"
        . ($isRugiTotal ? "RUGI" : "UNTUNG") . "\n";
    exit;
}

// ===================================================
// EXPORT PDF
// ===================================================
$fpdf_path = __DIR__ . '/../fpdf/fpdf.php';
if (!file_exists($fpdf_path)) { http_response_code(500); die('FPDF tidak ditemukan'); }
if (ob_get_length()) @ob_end_clean();
require_once $fpdf_path;

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
        $this->Cell(0, 10, 'Dicetak: ' . date("d/m/Y H:i") . '   |   Hal. ' . $this->PageNo(), 0, 0, 'C');
    }
}

$pdf = new PDF('P', 'mm', 'A4');
$pdf->judulLaporan = 'Laporan Laba Rugi Bulanan';
$pdf->namaToko     = $nama_toko;
$pdf->SetMargins(14, 30, 14);
$pdf->SetAutoPageBreak(true, 20);
$pdf->AddPage();

// Info cetak
$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell(0, 5, 'Tanggal Cetak: ' . date("d/m/Y H:i"), 0, 1, 'R');
$pdf->Ln(2);

// 3 STAT BOXES
$colW       = (210 - 28) / 3;
$yStatStart = $pdf->GetY();
$statData = [
    ['label' => 'Total Penjualan', 'value' => fmtRp($grandPenjualan), 'color' => [56, 142,  60]],
    ['label' => 'Total Modal',     'value' => fmtRp($grandModal),     'color' => [245, 124,   0]],
    ['label' => 'Total Bulan',     'value' => count($rows) . ' bulan','color' => [25,  118, 210]],
];
foreach ($statData as $i => $s) {
    [$r,$g,$b] = $s['color'];
    $xBox = 14 + ($i * $colW);
    $pdf->SetFillColor($r, $g, $b);
    $pdf->Rect($xBox, $yStatStart, $colW - 3, 18, 'F');
    $pdf->SetXY($xBox, $yStatStart + 3);
    $pdf->SetFont('Arial', '', 7);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell($colW - 3, 5, strtoupper($s['label']), 0, 0, 'C');
    $pdf->SetXY($xBox, $yStatStart + 9);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell($colW - 3, 7, $s['value'], 0, 0, 'C');
}
$pdf->SetXY(14, $yStatStart + 21);

// KOTAK TOTAL LABA/RUGI (full width)
$yS = $pdf->GetY();
if ($isRugiTotal) {
    $pdf->SetFillColor(198, 40, 40);
} else {
    $pdf->SetFillColor(123, 31, 162);
}
$pdf->Rect(14, $yS, 182, 16, 'F');
$pdf->SetXY(14, $yS + 2);
$pdf->SetFont('Arial', '', 8);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(182, 5, $isRugiTotal ? 'TOTAL RUGI KESELURUHAN' : 'TOTAL LABA KESELURUHAN', 0, 0, 'C');
$pdf->SetXY(14, $yS + 8);
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(182, 6, ($isRugiTotal ? '- ' : '+ ') . fmtRp($grandLaba), 0, 0, 'C');
$pdf->SetXY(14, $yS + 20);
$pdf->Ln(4);

// HEADER TABEL — 8+42+44+44+38+6 = 182mm
$cols = [
    ['label' => 'No',              'w' =>  8, 'align' => 'C'],
    ['label' => 'Bulan',           'w' => 42, 'align' => 'L'],
    ['label' => 'Total Penjualan', 'w' => 44, 'align' => 'R'],
    ['label' => 'Total Modal',     'w' => 44, 'align' => 'R'],
    ['label' => 'Laba / Rugi',     'w' => 38, 'align' => 'R'],
    ['label' => 'Status',          'w' =>  6, 'align' => 'C'],
];
$pdf->SetFillColor(56, 142, 60);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 9);
foreach ($cols as $c) {
    $pdf->Cell($c['w'], 8, $c['label'], 0, 0, $c['align'], true);
}
$pdf->Ln();

// BARIS DATA
$pdf->SetFont('Arial', '', 9);
$no = 1;
foreach ($rows as $r) {
    $laba   = floatval($r['laba']);
    $isRugi = $laba < 0;

    if ($isRugi) {
        $pdf->SetFillColor(255, 235, 238);
    } elseif ($no % 2 === 0) {
        $pdf->SetFillColor(232, 245, 233);
    } else {
        $pdf->SetFillColor(255, 255, 255);
    }
    $pdf->SetTextColor(30, 30, 30);

    $pdf->Cell($cols[0]['w'], 7, $no,                                    0, 0, 'C', true);
    $pdf->Cell($cols[1]['w'], 7, fmtBulan($r['bulan']),                  0, 0, 'L', true);
    $pdf->Cell($cols[2]['w'], 7, fmtRp($r['total_penjualan']),           0, 0, 'R', true);
    $pdf->Cell($cols[3]['w'], 7, fmtRp($r['total_modal']),               0, 0, 'R', true);

    // Warna teks laba/rugi
    if ($isRugi) {
        $pdf->SetTextColor(198, 40, 40);
    } else {
        $pdf->SetTextColor(27, 94, 32);
    }
    $pdf->Cell($cols[4]['w'], 7, ($isRugi ? '- ' : '+ ') . fmtRp($laba), 0, 0, 'R', true);

    $pdf->SetTextColor(30, 30, 30);
    $pdf->Cell($cols[5]['w'], 7, ($isRugi ? 'v' : '^'),                   0, 0, 'C', true);
    $pdf->Ln();
    $no++;
}

// BARIS TOTAL
if ($isRugiTotal) {
    $pdf->SetFillColor(198, 40, 40);
} else {
    $pdf->SetFillColor(56, 142, 60);
}
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 9);
$labelW = $cols[0]['w'] + $cols[1]['w'];
$pdf->Cell($labelW,        8, 'TOTAL KESELURUHAN',                              0, 0, 'L', true);
$pdf->Cell($cols[2]['w'], 8, fmtRp($grandPenjualan),                            0, 0, 'R', true);
$pdf->Cell($cols[3]['w'], 8, fmtRp($grandModal),                                0, 0, 'R', true);
$pdf->Cell($cols[4]['w'], 8, ($isRugiTotal ? '- ' : '+ ') . fmtRp($grandLaba), 0, 0, 'R', true);
$pdf->Cell($cols[5]['w'], 8, ($isRugiTotal ? 'RUGI' : 'LABA'),                  0, 0, 'C', true);
$pdf->Ln();

// KETERANGAN
$pdf->Ln(4);
$pdf->SetFont('Arial', 'I', 8);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell(0, 5, 'Keterangan: Baris merah = bulan rugi  |  ^ = untung  |  v = rugi', 0, 1, 'L');

$pdf->Output('D', 'laporan_laba_rugi_bulanan_' . date("Ymd_His") . '.pdf');
?>
