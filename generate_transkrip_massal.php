<?php
ob_start();
session_start();
require_once "config/database.php";
require_once "dompdf/autoload.inc.php";

use Dompdf\Dompdf;
use Dompdf\Options;

// Database connection
$database = new Database();
$db = $database->getConnection();

// Deklarasi fungsi di luar agar bisa digunakan di berbagai tempat
function tglIndo($date)
{
    $bulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    return date('j', strtotime($date)) . ' ' . $bulan[(int)date('n', strtotime($date))] . ' ' . date('Y', strtotime($date));
}

function h($s)
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

// Get parameters from form
$tahun = $_GET['tahun'] ?? '';
$prodi = $_GET['prodi'] ?? '';
$masuk = $_GET['masuk'] ?? '';
$lulus = $_GET['lulus'] ?? '';
$cetak = $_GET['cetak'] ?? '';

// Validate required parameters
if (empty($tahun) || empty($prodi) || empty($masuk) || empty($lulus) || empty($cetak)) {
    die("Parameter tidak lengkap!");
}

// Create temp directory if not exists
$tempDir = __DIR__ . '/temp_transkrip_' . date('Ymd_His');
if (!file_exists($tempDir)) {
    // Coba dengan permission 0777
    if (!@mkdir($tempDir, 0777, true)) {
        // Jika gagal, coba dengan sys_temp_dir
        $tempDir = sys_get_temp_dir() . '/temp_transkrip_' . date('Ymd_His');
        if (!@mkdir($tempDir, 0777, true)) {
            die("Gagal membuat direktori temporary!");
        }
    }
}

$prodiNum = '';
if (str_ends_with($prodi, 'Industri')) {
    $prodiNum = '03';
} else if (str_ends_with($prodi, 'Mesin')) {
    $prodiNum = '01';
} else if (str_ends_with($prodi, 'Elektronika')) {
    $prodiNum = '02';
} else if (str_ends_with($prodi, 'Informasi')) {
    $prodiNum = '04';
} else {
    die("Program studi tidak dikenali!");
}


// Get all students based on filters
$queryMhs = "SELECT nim, nama FROM mahasiswa 
             WHERE tahunmasuk = ? AND MID(nim,3,2) = ? AND status = 'Lulus'
             ORDER BY nim LIMIT 2";
$stmtMhs = $db->prepare($queryMhs);
$stmtMhs->execute([$tahun, $prodiNum]);
$students = $stmtMhs->fetchAll(PDO::FETCH_ASSOC);

if (empty($students)) {
    die("Tidak ditemukan mahasiswa dengan kriteria tersebut!");
}

$pdfFiles = [];
$successCount = 0;
$errorCount = 0;

// Function to generate single transcript (modified from your code)
function generateSingleTranskrip($db, $nim, $masuk, $lulus, $cetak, $tempDir)
{
    // Use Dompdf locally
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

    // Query nilai & mahasiswa
    $query = "SELECT kodemk, namamk, sks, huruf, ROUND(angka,1) as angka, 
                     mahasiswa.nama, tempatlahir, tanggallahir, 
                     CASE
                        WHEN UPPER(SUBSTRING_INDEX(SUBSTRING_INDEX(programstudi, ' ', 3), ' ', -1)) = 'INDUSTRI' THEN 'Teknologi Industri'
                        ELSE programstudi
                     END AS programstudi,
                     nilai.akademik as ipk
              FROM nilaiakademik 
              INNER JOIN mahasiswa ON mahasiswa.nim = nilaiakademik.nim 
              INNER JOIN nilai ON nilaiakademik.nim = nilai.NIM
              WHERE nilaiakademik.nim = ? AND kodemk != '' AND sks > 0 
              ORDER BY tahunakademik, kodemk";

    try {
        $stmt = $db->prepare($query);
        $stmt->execute([$nim]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return null;
    }

    if (empty($rows)) return null;

    $mhs = $rows[0];

    $total_mk    = count($rows);
    $total_sks   = array_sum(array_column($rows, 'sks'));
    $ipk = $mhs['ipk'] ?? 0;

    $tgl_lahir = $mhs['tempatlahir'] . ', ' . tglIndo($mhs['tanggallahir']);

    // Split data into two columns
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

    $defaultRowMm = 5.5;
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
            @page { size: {$pageWidthMm}mm {$pageHeightMm}mm; margin: 0mm; }
            html, body { margin:0; padding:0; height:100%; }
            body { font-family: 'Times New Roman', serif; color:#000; -webkit-print-color-adjust: exact; }
            .scale-wrap { {$scaleCss} }
            .content { width: {$contentWidthMm}mm; box-sizing: border-box; padding: {$topPaddingMm}mm {$sidePaddingMm}mm 0 {$sidePaddingMm}mm; margin: 0; }
            .header-table { width:100%; border-collapse: collapse; font-size: 11pt; }
            .header-table td { vertical-align: top; padding-top:2px ; padding-bottom:2px; }
            .columns-table { width:100%; border-collapse: collapse; margin-top: 6px; }
            .col-td { vertical-align: top; padding: 0; }
            .score-table { width:100%; border-collapse: collapse; font-size: {$fontPt}pt; }
            .score-table th { background: #808080; color: #fff; padding: 4px; border:1px solid #000; text-align:center; }
            .score-table td { padding: 3px 4px; border:1px solid #000; vertical-align: middle; }
            .score-table td.center { text-align:center; }
            .footer-table { width:100%; margin-top: 6px; font-size: 11pt; border-collapse: collapse; padding: 0; }
            * { -webkit-box-sizing: border-box; box-sizing: border-box; }
            body { -webkit-print-color-adjust: exact; }
        </style>
    ";

    // ---------- Build HTML ----------
    $html = '<!doctype html><html><head><meta charset="utf-8">' . $css . '</head><body>';
    $html .= '<div class="scale-wrap"><div class="content">';

    // Header
    $html .= '<table class="header-table"><tr>';
    $html .= '<td style="width:100px">Nama</td>
    <td style="width:5px">:</td>
    <td style="width:240px">' . h($mhs['nama']) . '</td>';
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
    $html .= '<td class="col-td" style="width:48%; padding-right:6px;">';
    $html .= '<table class="score-table">';
    $html .= '<tr><th style="width:15%;">Kode</th><th style="width:55%;">Mata Kuliah</th><th style="width:10%;">SKS</th><th style="width:10%;">Nilai</th><th style="width:10%;">Mutu</th></tr>';
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
            $fontSize = 'font-size:' . ($fontPt - 1.5) . 'pt !important;';
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
    $html .= '<td style="width:2%"></td>';

    // RIGHT column
    $html .= '<td class="col-td" style="width:48%">';
    $html .= '<table class="score-table">';
    $html .= '<tr><th style="width:15%;">Kode</th><th style="width:55%;">Mata Kuliah</th><th style="width:10%;">SKS</th><th style="width:10%;">Nilai</th><th style="width:10%;">Mutu</th></tr>';
    foreach ($rightRows as $r) {
        $html .= '<tr style="height:' . $rowHeightCss . '">';
        if (strlen($r['kodemk']) > 7) {
            $fontKodeSize = 'font-size:' . ($fontPt - 1) . 'pt !important;';
        } else {
            $fontKodeSize = $fontPt . 'pt;';
        }

        $html .= '<td style="' . $fontKodeSize . '" class="center">' . h($r['kodemk']) . '</td>';

        if (strlen($r['namamk']) > 31) {
            $fontSize = 'font-size:' . ($fontPt - 1.5) . 'pt !important;';
        } else {

            $fontSize = $fontPt . 'pt;';
        }

        $html .= '<td style="' . $fontSize . '">' . $r['namamk'] . '</td>';
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

    // Generate PDF
    $dompdf->loadHtml($html);
    $dompdf->setPaper(array(0, 0, $pageWidthPt, $pageHeightPt), 'portrait');
    $dompdf->render();

    // Save to file
    $output = $dompdf->output();

    $filename = $tempDir . '/transkrip_' . $nim . '.pdf';

    if (file_put_contents($filename, $output)) {
        return $filename;
    }

    return null;
}

// Generate PDF for each student
echo "<h3>Proses Generate Transkrip Massal</h3>";
echo "Total mahasiswa: " . count($students) . "<br><br>";

foreach ($students as $index => $student) {
    $nim = $student['nim'];
    $nama = $student['nama'];

    echo "Memproses " . ($index + 1) . "/" . count($students) . ": $nim - $nama ... ";

    $pdfFile = generateSingleTranskrip($db, $nim, $masuk, $lulus, $cetak, $tempDir);

    if ($pdfFile) {
        $pdfFiles[] = $pdfFile;
        $successCount++;
        echo "<span style='color:green;'>✓ SUKSES</span><br>";
    } else {
        $errorCount++;
        echo "<span style='color:red;'>✗ GAGAL</span><br>";
    }

    // Flush output untuk real-time progress
    flush();
    ob_flush();
}

echo "<br><hr>";
echo "<h4>Ringkasan:</h4>";
echo "Berhasil: $successCount transkrip<br>";
echo "Gagal: $errorCount transkrip<br><br>";

// If no PDFs generated, show error and exit
if (empty($pdfFiles)) {
    echo "<div style='color:red;'><strong>Tidak ada transkrip yang berhasil digenerate!</strong></div>";

    // Clean up temp directory
    if (file_exists($tempDir)) {
        array_map('unlink', glob("$tempDir/*"));
        rmdir($tempDir);
    }

    exit;
}

// Function to merge PDFs
function mergePDFs($pdfFiles, $outputPath)
{

    // Pastikan directory writable
    $outputDir = dirname($outputPath);
    if (!is_writable($outputDir)) {
        // Fallback ke /tmp
        $outputPath = '/tmp/' . basename($outputPath);
    }
    // Try to use FPDI if available
    $fpdiPath = __DIR__ . '/vendor/autoload.php';

    if (file_exists($fpdiPath)) {
        require_once $fpdiPath;

        if (class_exists('\setasign\Fpdi\Fpdi')) {
            $pdf = new \setasign\Fpdi\Fpdi();

            foreach ($pdfFiles as $file) {
                try {
                    $pageCount = $pdf->setSourceFile($file);

                    for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                        $templateId = $pdf->importPage($pageNo);
                        $size = $pdf->getTemplateSize($templateId);

                        // Add a page with the same orientation
                        $orientation = ($size['width'] > $size['height']) ? 'L' : 'P';
                        $pdf->AddPage($orientation, array($size['width'], $size['height']));
                        $pdf->useTemplate($templateId);
                    }
                } catch (Exception $e) {
                    continue;
                }
            }

            $pdf->Output('F', $outputPath);
            return file_exists($outputPath);
        }
    }

    // Alternative: use shell command if available
    return mergePDFsWithCommand($pdfFiles, $outputPath);
}

// Alternative method using shell commands
function mergePDFsWithCommand($pdfFiles, $outputPath)
{
    // Method 1: Try pdftk (if installed on server)
    $filesList = implode(' ', array_map('escapeshellarg', $pdfFiles));

    // Try pdftk
    $cmd = "pdftk $filesList cat output " . escapeshellarg($outputPath) . " 2>&1";
    $result = shell_exec($cmd);

    if (file_exists($outputPath)) return true;

    // Method 2: Try ghostscript (if installed)
    $cmd = "gs -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -sOutputFile=" . escapeshellarg($outputPath) . " $filesList 2>&1";
    $result = shell_exec($cmd);

    if (file_exists($outputPath)) return true;

    // Method 3: Create a ZIP file as fallback
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        $zipPath = str_replace('.pdf', '.zip', $outputPath);

        if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
            foreach ($pdfFiles as $index => $file) {
                $zip->addFile($file, basename($file));
            }
            $zip->close();

            // Rename to PDF (it's actually a zip containing PDFs)
            copy($zipPath, $outputPath);
            unlink($zipPath);

            return file_exists($outputPath);
        }
    }

    return false;
}

// Merge all PDFs into one
// $mergedPdfPath = __DIR__ . '/transkrip_massal_' . $tahun . '_' . $prodi . '_' . date('Ymd_His') . '.pdf';

$filename = 'transkrip_massal_' . $tahun . '_' . $prodi . '_' . date('Ymd_His') . '.pdf';
$mergedPdfPath = '/tmp/' . $filename; // Simpan di /tmp
echo "<br><h4>Menggabungkan PDF...</h4>";

if (mergePDFs($pdfFiles, $mergedPdfPath)) {
    echo "<div style='color:green;'><strong>✓ Semua transkrip berhasil digabungkan!</strong></div><br>";

    $fileSize = filesize($mergedPdfPath);
    $fileSizeMB = round($fileSize / (1024 * 1024), 2);

    echo "File: " . basename($mergedPdfPath) . "<br>";
    echo "Ukuran: $fileSizeMB MB<br>";
    echo "Jumlah halaman: " . count($pdfFiles) . " (satu transkrip per halaman)<br><br>";

    $downloadFilename = basename($mergedPdfPath);
    if (strpos($mergedPdfPath, '/tmp/') === 0) {
        // Jika file di /tmp, perlu proxy download
        echo '<a href="download_transkrip.php?file=' . urlencode(basename($mergedPdfPath)) . '" download>';
    } else {
        echo '<a href="' . basename($mergedPdfPath) . '" download>';
    }

    // Provide download link
    // echo '<a href="' . basename($mergedPdfPath) . '" target="__blank" class="btn btn-success" style="padding:10px 20px; background:#28a745; color:white; text-decoration:none; border-radius:5px;" download>⬇ Download Transkrip Gabungan</a>';
    // echo '&nbsp;&nbsp;';
    // echo '<a href="#" onclick="window.print()" class="btn btn-primary" style="padding:10px 20px; background:#007bff; color:white; text-decoration:none; border-radius:5px;">🖨 Cetak</a><br><br>';

    // Clean up individual PDF files
    foreach ($pdfFiles as $file) {
        if (file_exists($file)) {
            unlink($file);
        }
    }

    // Remove temp directory
    if (file_exists($tempDir)) {
        rmdir($tempDir);
    }

    // Cleanup script
    echo '
    <script>
        function cleanupFile() {
            // AJAX request to cleanup file after download
            var xhr = new XMLHttpRequest();
            xhr.open("GET", "cleanup_transkrip.php?file=' . urlencode(basename($mergedPdfPath)) . '", true);
            xhr.send();
        }
        
        // Cleanup ketika user meninggalkan halaman atau mengklik download
        document.querySelector(\'a[download]\').addEventListener(\'click\', function() {
            setTimeout(cleanupFile, 5000); // Cleanup setelah 5 detik
        });
        
        window.addEventListener(\'beforeunload\', function() {
            cleanupFile();
        });
    </script>';
} else {
    echo "<div style='color:red;'><strong>✗ Gagal menggabungkan PDF!</strong></div><br>";
    echo "Mungkin diperlukan library tambahan. Silakan install salah satu:<br>";
    echo "1. <code>composer require setasign/fpdi</code><br>";
    echo "2. <code>pdftk</code> (di server Linux)<br>";
    echo "3. <code>ghostscript</code> (di server)<br><br>";

    echo "Atau download file satu per satu:<br>";
    echo '<div style="border:1px solid #ccc; padding:10px; max-height:200px; overflow-y:auto;">';

    foreach ($pdfFiles as $file) {
        $filename = basename($file);
        $nim = str_replace(['transkrip_', '.pdf'], '', $filename);
        echo '<a href="' . str_replace(__DIR__ . '/', '', $file) . '" download style="display:block; padding:5px;">' . $filename . '</a>';
    }

    echo '</div><br>';

    // Tautan untuk membersihkan file
    echo '<a href="cleanup_transkrip.php?dir=' . urlencode(basename($tempDir)) . '" style="color:red;">[Hapus file temporary]</a>';
}

// Tambahkan CSS untuk tampilan yang lebih baik
echo '
<style>
    body {
        font-family: Arial, sans-serif;
        margin: 20px;
        line-height: 1.6;
    }
    h3, h4 {
        color: #333;
    }
    .btn {
        padding: 10px 20px;
        border-radius: 5px;
        text-decoration: none;
        display: inline-block;
        margin: 5px;
    }
    .btn-success {
        background: #28a745;
        color: white;
    }
    .btn-primary {
        background: #007bff;
        color: white;
    }
</style>
';
