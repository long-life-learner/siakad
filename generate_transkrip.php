<?php
ob_start();
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
require_once "dompdf/autoload.inc.php";
require_once "config/database.php";
// Use Database helper (PDO)
$database = new Database();
$db = $database->getConnection();

use Dompdf\Dompdf;
use Dompdf\Options;

// Dompdf options
$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'Times New Roman');

// A4 size in inches
$pageWidthIn  = 8.27;
$pageHeightIn = 11.69;

$pageWidthPt  = $pageWidthIn  * 72;
$pageHeightPt = $pageHeightIn * 72;

$dompdf = new Dompdf($options);

// PARAMETERS
$nim   = $_GET['nim'] ?? '';
$masuk = $_GET['masuk'] ?? '2021-01-26';
$lulus = $_GET['lulus'] ?? '2024-12-08';
$cetak = $_GET['cetak'] ?? date('Y-m-d');

function tglIndo($date)
{
    $bulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    return date('j', strtotime($date)) . ' ' . $bulan[(int)date('n', strtotime($date))] . ' ' . date('Y', strtotime($date));
}
function h($s)
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

// Query nilai & mahasiswa
$query = "SELECT kodemk, namamk, sks, huruf, ROUND(angka,1) as angka, 
                 mahasiswa.nama, tempatlahir, tanggallahir, CASE
                        WHEN UPPER(SUBSTRING_INDEX(SUBSTRING_INDEX(programstudi, ' ', 3), ' ', -1)) = 'INDUSTRI' THEN 'Teknologi Industri'
                        ELSE programstudi
                     END AS programstudi,
                 nilai.akademik as ipk
          FROM nilaiakademik 
          INNER JOIN mahasiswa ON mahasiswa.nim = nilaiakademik.nim 
          INNER JOIN nilai ON nilaiakademik.nim = nilai.NIM
          WHERE nilaiakademik.nim = ? AND kodemk != '' AND sks > 0 AND mahasiswa.status = 'Lulus'
          ORDER BY tahunakademik, kodemk";

try {
    $stmt = $db->prepare($query);
    $stmt->execute([$nim]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die('Query error: ' . $e->getMessage());
}

if (empty($rows)) die("Data tidak ditemukan");

$mhs = $rows[0];

$total_mk    = count($rows);
$total_sks   = array_sum(array_column($rows, 'sks'));
$ipk = $mhs['ipk'] ?? 0;

$tgl_lahir = $mhs['tempatlahir'] . ', ' . tglIndo($mhs['tanggallahir']);

// Split data into two columns (left/top then right)
$half      = (int)ceil($total_mk / 2);
$leftRows  = array_slice($rows, 0, $half);
$rightRows = array_slice($rows, $half);

// ---------- Dynamic layout calculation ----------
$inchToMm = 25.4;
$pageHeightMm = $pageHeightIn * $inchToMm;
$pageWidthMm  = $pageWidthIn  * $inchToMm;

$topPaddingMm = 40;
$sidePaddingMm = 5;

$headerAreaMm = $topPaddingMm + 5;
$footerAreaMm = 65;
$availableTableMm = $pageHeightMm - $headerAreaMm - $footerAreaMm;
if ($availableTableMm < 40) $availableTableMm = 40;

$rowsPerColNeeded = max(1, (int)ceil($total_mk / 2));

$defaultRowMm = 5.22;
$minRowMm = 3.0;

$tableHeaderExtraMm = 8;
$requiredTableMmDefault = $rowsPerColNeeded * $defaultRowMm + $tableHeaderExtraMm;
$requiredTableMmMin = $rowsPerColNeeded * $minRowMm + $tableHeaderExtraMm;

$scale = 1.0;
$rowMmToUse = $defaultRowMm;

if ($requiredTableMmDefault <= $availableTableMm) {
    $rowMmToUse = $defaultRowMm;
    $scale = 1.0;
} elseif ($requiredTableMmMin <= $availableTableMm) {
    $rowMmToUse = ($availableTableMm - $tableHeaderExtraMm) / $rowsPerColNeeded;
    if ($rowMmToUse < $minRowMm) $rowMmToUse = $minRowMm;
    $scale = 1.0;
} else {
    $rowMmToUse = $minRowMm;
    $scale = $availableTableMm / $requiredTableMmDefault;
    if ($scale < 0.35) $scale = 0.35;
}

$baseRowMm = 5.5;
$baseFontPt = 10;
$fontPt = max(7, round($baseFontPt * ($rowMmToUse / $baseRowMm)));

$contentWidthMm = $pageWidthMm - ($sidePaddingMm * 2);

$scaleCss = ($scale < 1.0) ? 'transform: scale(' . number_format($scale, 3, '.', '') . '); transform-origin: top left; width: calc(' . $contentWidthMm . 'mm / ' . number_format($scale, 6, '.', '') . ');' : '';

// ---------- CSS ----------
$css = "
        <style>
            @page { size: {$pageWidthMm}mm {$pageHeightMm}mm; margin: 0mm 5mm 0mm 5mm; }
            html, body { margin:0; padding:0; height:100%; }
            body { font-family: 'Times New Roman', serif; color:#000; -webkit-print-color-adjust: exact; }
            .scale-wrap { {$scaleCss} margin: 0 auto !important; }
            .content { width: 100%; box-sizing: border-box; padding: {$topPaddingMm}mm 3.5mm 0mm 3.5mm; margin: 0; }
            .header-table { width:100%; border-collapse: collapse; font-size: 11pt; }
            .header-table td { vertical-align: top; padding-top:2px ; padding-bottom:2px; }
            .columns-table { width:100%; border-collapse: collapse; margin-top: 6px; }
            .col-td { vertical-align: top; padding: 0; }
            .score-table { width:100%; border-collapse: collapse; font-size: {$fontPt}pt; }
            .score-table th { background: #808080; color: #fff; padding: 4px; border:1px solid #000; text-align:center; }
            .score-table td { padding: 3px 4px; border:1px solid #000; vertical-align: middle; }
            .score-table td.center { text-align:center; }
            .footer-table { width:100%; margin-top: 10px; font-size: 11pt; border-collapse: collapse; padding: 0; }
            * { -webkit-box-sizing: border-box; box-sizing: border-box; }
            body { -webkit-print-color-adjust: exact; }
        </style>
    ";

// ---------- Build HTML ----------
$html = '<!doctype html><html><head><meta charset="utf-8">' . $css . '</head><body>';
// $html .= '<div class="scale-wrap"><div class="content">';
$html .= '<div ><div class="content">';

// Header
$html .= '<table class="header-table"><tr>';
$html .= '<td style="width:100px">Nama</td>
                <td style="width:5px">:</td>
                <td style="width:275px">' . h($mhs['nama']) . '</td>';
$html .= '<td style="width:135px">Tempat/Tanggal Lahir</td><td style="width:5px">:</td><td>' . $tgl_lahir . '</td>';
$html .= '</tr><tr>';
$html .= '<td>NIM</td><td>:</td><td>' . h($nim) . '</td>';
$html .= '<td>Tanggal Masuk</td><td>:</td><td>' . tglIndo($masuk) . '</td>';
$html .= '</tr><tr>';
$html .= '<td>Program Studi</td><td>:</td><td>' . h($mhs['programstudi']) . '</td>';
$html .= '<td>Tanggal Lulus</td><td>:</td><td>' . tglIndo($lulus) . '</td>';
$html .= '</tr></table>';

// Table rows height
$rowHeightCss = number_format($rowMmToUse, 2, '.', '') . 'mm';

// Two-column tables
$html .= '<table class="columns-table"><tr>';

// LEFT column
$html .= '<td class="col-td" style="width:49%;">';
$html .= '<table class="score-table">';
$html .= '<tr><th style="width:15%;">Kode</th><th style="width: 55%;">Mata Kuliah</th><th style="width:10%;">SKS</th><th style="width:10%;">Nilai</th><th style="width:10%;">Mutu</th></tr>';
foreach ($leftRows as $r) {
    $html .= '<tr style="height:' . $rowHeightCss . '">';
    if (strlen($r['kodemk']) > 7) {
        $fontKodeSize = 'font-size:' . ($fontPt - 1) . 'pt !important;';
    } else {
        $fontKodeSize = $fontPt . 'pt;';
    }

    $html .= '<td style="' . $fontKodeSize . '" class="center">' . h($r['kodemk']) . '</td>';

    if (strlen($r['namamk']) > 31) {
        // $shortName = substr($r['namamk'], 0, 29) . '...';
        $fontSize = 'font-size:' . ($fontPt - 1) . 'pt !important;';
    } else {
        // $shortName = $r['namamk'];
        $fontSize = $fontPt . 'pt;';
    }

    $html .= '<td style="' . $fontSize . '">' . $r['namamk'] . '</td>';
    $html .= '<td class="center">' . $r['sks'] . '</td>';
    $html .= '<td class="center">' . h($r['huruf']) . '</td>';
    $html .= '<td class="center">' . $r['angka'] . '</td>';
    $html .= '</tr>';
}
$html .= '</table></td>';

// spacer
$html .= '<td style="width:1%"></td>';

// RIGHT column
$html .= '<td class="col-td" style="width:49%">';
$html .= '<table class="score-table">';
$html .= '<tr><th style="width:15%;">Kode</th><th style="width: 55%;">Mata Kuliah</th><th style="width:10%;">SKS</th><th style="width:10%;">Nilai</th><th style="width:10%;">Mutu</th></tr>';
foreach ($rightRows as $r) {
    $html .= '<tr style="height:' . $rowHeightCss . '">';
    if (strlen($r['kodemk']) > 7) {
        $fontKodeSize = 'font-size:' . ($fontPt - 1) . 'pt !important;';
    } else {
        $fontKodeSize = $fontPt . 'pt;';
    }

    $html .= '<td style="' . $fontKodeSize . '" class="center">' . h($r['kodemk']) . '</td>';

    if (strlen($r['namamk']) > 31) {
        $fontSize = 'font-size:' . ($fontPt - 1) . 'pt !important;';
    } else {

        $fontSize = $fontPt . 'pt;';
    }

    $html .= '<td style="' . $fontSize . ';">' . $r['namamk'] . '</td>';
    $html .= '<td class="center">' . $r['sks'] . '</td>';
    $html .= '<td class="center">' . h($r['huruf']) . '</td>';
    $html .= '<td class="center">' . $r['angka'] . '</td>';
    $html .= '</tr>';
}
$html .= '</table></td>';
$html .= '</tr></table>';

// Footer
$html .= '<table class="footer-table"><tr>';
$html .= '<td style="width:235px; vertical-align:top; line-height:1.6">';
$html .= 'Total Mata Kuliah : ' . $total_mk . '<br>';
$html .= 'Total SKS : ' . $total_sks . '<br>';
$html .= 'Indeks Prestasi Kumulatif (IPK) : ' . $ipk . '';
$html .= '</td>';
$html .= '<td style="text-align:center; vertical-align:top; padding-left: 20px">';
$html .= ' <div style="text-align: center; margin-right: 120px;">Tangerang, ' . tglIndo($cetak) . '<br>Direktur<br><div style="height:80px"></div><span style="text-decoration:underline;font-weight:bold">Dr. Dra. Ita Mariza, M.M.</span> </div>';
$html .= '</td></tr></table>';

$html .= '</div></div>';
$html .= '</body></html>';

// Load HTML and set custom paper (A4) with zero margins
$dompdf->loadHtml($html);
$dompdf->setPaper(array(0, 0, $pageWidthPt, $pageHeightPt), 'portrait');
$dompdf->render();

// Stream inline
$dompdf->stream("Transkrip_{$nim}.pdf", ["Attachment" => false]);
exit;
