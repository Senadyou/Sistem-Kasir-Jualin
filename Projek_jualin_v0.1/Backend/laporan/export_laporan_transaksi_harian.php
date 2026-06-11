<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$type    = isset($_GET['type'])    ? strtolower($_GET['type']) : 'json';
$id_toko = isset($_GET['id_toko']) ? intval($_GET['id_toko'])  : 0;

require_once __DIR__ . '/../koneksi.php';
if (!isset($conn)) { http_response_code(500); die('Koneksi DB gagal'); }

$fpdf_path_candidates = [__DIR__ . '/../fpdf/fpdf.php'];
$fpdf_path = null;
foreach ($fpdf_path_candidates as $p) { if (file_exists($p)) { $fpdf_path = $p; break; } }
if ($type === 'pdf' && !$fpdf_path) { http_response_code(500); die('FPDF tidak ditemukan'); }

// ✅ Query dengan kolom yang benar dan filter id_toko
$sql = "SELECT 
            DATE(t.TANGGAL) AS tanggal,
            COUNT(DISTINCT t.KODE_TRANSAKSI) AS jumlah_transaksi,
            COALESCE(SUM(ti.JUMLAH * ti.HARGA_JUAL), 0) AS total_harian
        FROM transaksi t
        JOIN transaksi_items ti ON t.KODE_TRANSAKSI = ti.KODE_TRANSAKSI
        WHERE t.id_toko = ?
        GROUP BY DATE(t.TANGGAL)
        ORDER BY DATE(t.TANGGAL) DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_toko);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
while ($r = $result->fetch_assoc()) { $rows[] = $r; }

// EXCEL
if ($type === 'excel') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="laporan_transaksi_harian_' . date("Y-m-d") . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Tanggal','Jumlah Transaksi','Total Penjualan Harian (Rp)']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['tanggal'], $r['jumlah_transaksi'],
            number_format($r['total_harian'],0,',','.')
        ]);
    }
    fclose($out); exit;
}

// PDF
if ($type === 'pdf') {
    require_once $fpdf_path;
    if (ob_get_length()) @ob_end_clean();

    $pdf = new FPDF('P','mm','A4');
    $pdf->SetAutoPageBreak(true, 15);
    $pdf->AddPage();

    $logo = __DIR__ . '/../../Frontend/assets/img/logo.png';
    if (file_exists($logo)) $pdf->Image(str_replace('\\','/',$logo), 10, 6, 20);

    $pdf->SetFont('Arial','B',14);
    $pdf->Cell(0,10,'Laporan Transaksi Harian',0,1,'C');
    $pdf->SetFont('Arial','',10);
    $pdf->Cell(0,7,'Dicetak: '.date("d-m-Y H:i"),0,1,'C');
    $pdf->Ln(5);

    $w = [55,55,80];
    $pdf->SetFont('Arial','B',10);
    foreach (['Tanggal','Jumlah Transaksi','Total Penjualan Harian'] as $i=>$h)
        $pdf->Cell($w[$i],8,$h,1,0,'C');
    $pdf->Ln();

    $pdf->SetFont('Arial','',9);
    foreach ($rows as $r) {
        $pdf->Cell($w[0],7,$r['tanggal'],1,0,'C');
        $pdf->Cell($w[1],7,$r['jumlah_transaksi'],1,0,'C');
        $pdf->Cell($w[2],7,'Rp '.number_format($r['total_harian'],0,',','.'),1,1,'R');
    }
    $pdf->Output('I','laporan_transaksi_harian.pdf',true); exit;
}

header('Content-Type: application/json');
echo json_encode($rows);
?>
