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

// ===== AMBIL DATA STOK =====
$stmt = $conn->prepare(
    "SELECT KODE_PRODUK, NAMA_PRODUK, KATEGORI, STOK, HARGA_JUAL
     FROM produk WHERE id_toko = ? ORDER BY NAMA_PRODUK ASC"
);
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
$STOK_WARN = 5;

$totalStok  = array_sum(array_column($rows, 'STOK'));
$stokWarn   = count(array_filter($rows, fn($r) => intval($r['STOK']) <= $STOK_WARN));
$rataStok   = count($rows) ? round($totalStok / count($rows)) : 0;

// ===================================================
// ===== EXPORT EXCEL =====
// ===================================================
if ($type === 'excel') {
    header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
    header("Content-Disposition: attachment; filename=laporan_stok_" . date("Ymd") . ".xls");
    header("Pragma: no-cache");

    echo "\xEF\xBB\xBF"; // BOM UTF-8

    echo "LAPORAN STOK PRODUK\n";
    echo "Toko\t: " . $nama_toko . "\n";
    echo "Dicetak\t: " . date("d/m/Y H:i") . "\n";
    echo "Total Produk\t: " . count($rows) . "\n";
    echo "Total Stok\t: " . $totalStok . "\n\n";

    echo "Kode Produk\tNama Produk\tKategori\tStok\tHarga Jual\tStatus\n";
    foreach ($rows as $r) {
        $status = intval($r['STOK']) <= 0
            ? "HABIS"
            : (intval($r['STOK']) <= $STOK_WARN ? "MENIPIS" : "AMAN");
        echo $r['KODE_PRODUK'] . "\t"
            . $r['NAMA_PRODUK'] . "\t"
            . ($r['KATEGORI'] ?? '-') . "\t"
            . $r['STOK'] . "\t"
            . $r['HARGA_JUAL'] . "\t"
            . $status . "\n";
    }
    echo "\nTOTAL\t\t\t" . $totalStok . "\t\t\n";
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

$pdf = new PDF('P', 'mm', 'A4'); // Portrait — cukup untuk 5 kolom
$pdf->judulLaporan = 'Laporan Stok Produk';
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
    ['label' => 'Total Produk',  'value' => count($rows) . ' produk',             'color' => [56,142,60]],
    ['label' => 'Total Stok',    'value' => number_format($totalStok,0,',','.'),   'color' => [25,118,210]],
    ['label' => 'Stok Menipis',  'value' => $stokWarn . ' produk',                'color' => [229,57,53]],
];
$colW       = (210 - 28) / 3;
$yStatStart = $pdf->GetY();

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
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell($colW - 3, 7, $s['value'], 0, 0, 'C');
}

$pdf->SetXY(14, $yStatStart + 21);

// Kotak rata-rata stok (full width)
$yS = $pdf->GetY();
$pdf->SetFillColor(123, 31, 162);
$pdf->Rect(14, $yS, 182, 16, 'F');

$pdf->SetXY(14, $yS + 2);
$pdf->SetFont('Arial', '', 8);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(182, 5, 'RATA-RATA STOK PER PRODUK', 0, 0, 'C');

$pdf->SetXY(14, $yS + 8);
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(182, 6, $rataStok . ' unit', 0, 0, 'C');

$pdf->SetXY(14, $yS + 20);
$pdf->Ln(4);

// ----- HEADER TABEL -----
$pdf->SetTextColor(30, 30, 30);
// Total = 182mm (A4 portrait: 210 - margin 14 kiri - 14 kanan = 182) ✅
$cols = [
    ['label' => 'No',          'w' =>  8, 'align' => 'C'],
    ['label' => 'Kode',        'w' => 18, 'align' => 'C'],
    ['label' => 'Nama Produk', 'w' => 72, 'align' => 'L'],
    ['label' => 'Kategori',    'w' => 30, 'align' => 'C'],
    ['label' => 'Stok',        'w' => 16, 'align' => 'C'],
    ['label' => 'Harga Jual',  'w' => 38, 'align' => 'R'],
]; // 8+18+72+30+16+38 = 182

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
    $stok    = intval($r['STOK']);
    $isHabis = $stok <= 0;
    $isWarn  = $stok > 0 && $stok <= $STOK_WARN;

    // Row background
    if ($isHabis) {
        $pdf->SetFillColor(255, 235, 238); // merah muda
    } elseif ($isWarn) {
        $pdf->SetFillColor(255, 243, 224); // oranye muda
    } elseif ($no % 2 === 0) {
        $pdf->SetFillColor(232, 245, 233); // hijau muda
    } else {
        $pdf->SetFillColor(255, 255, 255);
    }

    $pdf->SetTextColor(30, 30, 30);

    $nama = mb_strlen($r['NAMA_PRODUK']) > 48
        ? mb_substr($r['NAMA_PRODUK'], 0, 45) . '...'
        : $r['NAMA_PRODUK'];

    // Warna stok merah jika habis/warn
    $pdf->Cell($cols[0]['w'], 7, $no,                              0, 0, 'C', true);
    $pdf->Cell($cols[1]['w'], 7, $r['KODE_PRODUK'],                0, 0, 'C', true);
    $pdf->Cell($cols[2]['w'], 7, $nama,                            0, 0, 'L', true);
    $pdf->Cell($cols[3]['w'], 7, $r['KATEGORI'] ?? '-',            0, 0, 'C', true);

    // Kolom stok: warna teks merah/oranye jika menipis/habis
    if ($isHabis) {
        $pdf->SetTextColor(198, 40, 40);
    } elseif ($isWarn) {
        $pdf->SetTextColor(230, 81, 0);
    }
    $stokLabel = $stok . ($isHabis ? ' !' : ($isWarn ? ' !' : ''));
    $pdf->Cell($cols[4]['w'], 7, $stokLabel, 0, 0, 'C', true);
    $pdf->SetTextColor(30, 30, 30);

    $pdf->Cell($cols[5]['w'], 7, fmtRp($r['HARGA_JUAL']),          0, 0, 'R', true);
    $pdf->Ln();
    $no++;
}

// ----- BARIS TOTAL -----
$pdf->SetFillColor(56, 142, 60);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 9);
$labelW = $cols[0]['w'] + $cols[1]['w'] + $cols[2]['w'] + $cols[3]['w'];
$pdf->Cell($labelW,       8, 'TOTAL STOK KESELURUHAN', 0, 0, 'L', true);
$pdf->Cell($cols[4]['w'], 8, number_format($totalStok, 0, ',', '.'), 0, 0, 'C', true);
$pdf->Cell($cols[5]['w'], 8, '',                        0, 0, 'R', true);
$pdf->Ln();

// ===== KETERANGAN WARNA =====
$pdf->Ln(4);
$pdf->SetFont('Arial', 'I', 8);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell(0, 5, 'Keterangan: Baris merah = stok habis  |  Baris oranye = stok menipis (≤ ' . $STOK_WARN . ')', 0, 1, 'L');

// ===== OUTPUT =====
$pdf->Output('D', 'laporan_stok_' . date("Ymd_His") . '.pdf');
?>
