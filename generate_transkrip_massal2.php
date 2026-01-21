<?php
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
    mkdir($tempDir, 0777, true);
}

// Get all students based on filters
$queryMhs = "SELECT nim, nama FROM mahasiswa 
             WHERE tahunmasuk = ? AND programstudi = ?
             ORDER BY nim LIMIT 1";
$stmtMhs = $db->prepare($queryMhs);
$stmtMhs->execute([$tahun, $prodi]);
$students = $stmtMhs->fetchAll(PDO::FETCH_ASSOC);

if (empty($students)) {
    die("Tidak ditemukan mahasiswa dengan kriteria tersebut!");
}

$pdfFiles = [];
$successCount = 0;
$errorCount = 0;

// Function to calculate dynamic font size based on number of courses
function calculateFontSize($totalCourses)
{
    // Base font size for normal case
    $baseFontSize = 10; // pt

    // Jika mata kuliah sedikit, gunakan font normal
    if ($totalCourses <= 25) {
        return $baseFontSize;
    }

    // Jika mata kuliah banyak, kurangi font size
    if ($totalCourses <= 30) {
        return 9.5;
    }

    if ($totalCourses <= 35) {
        return 9;
    }

    if ($totalCourses <= 40) {
        return 8.5;
    }

    if ($totalCourses <= 45) {
        return 8;
    }

    if ($totalCourses <= 50) {
        return 7.5;
    }

    // Minimum font size untuk keterbacaan
    return 7;
}

// Function to calculate row height based on font size
function calculateRowHeight($fontSize)
{
    // Base row height for font size 10pt
    $baseHeight = '15px';

    // Adjust row height based on font size
    if ($fontSize >= 10) {
        return '15px';
    } elseif ($fontSize >= 9) {
        return '14px';
    } elseif ($fontSize >= 8) {
        return '13px';
    } else {
        return '12px';
    }
}

// Function to generate single transcript
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
                     mahasiswa.nama, tempatlahir, tanggallahir, programstudi,
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

    // Calculate dynamic font size based on number of courses
    $fontSize = calculateFontSize($total_mk);
    $rowHeight = calculateRowHeight($fontSize);

    // Split data into two columns
    $half      = (int)ceil($total_mk / 2);
    $leftRows  = array_slice($rows, 0, $half);
    $rightRows = array_slice($rows, $half);

    // ---------- CSS dengan font size dinamis ----------
    $css = "
        <style>
            /* Page settings - A4 portrait */
            @page { 
                size: A4 portrait; 
                margin: 15mm 10mm 15mm 10mm; /* Atur margin untuk ruang yang cukup */
            }
            
            /* General styles */
            body { 
                font-family: 'Times New Roman', serif; 
                font-size: {$fontSize}pt; 
                line-height: 1.2; 
                margin: 0; 
                padding: 0; 
                color: #000; 
            }
            
            /* Header table styles */
            .header-table { 
                width: 100%; 
                border-collapse: collapse; 
                margin-bottom: 10px; 
                font-size: 11pt; /* Header tetap ukuran normal */
            }
            .header-table td { 
                vertical-align: top; 
                padding: 2px 0; 
            }
            
            /* Two-column container */
            .columns-container { 
                width: 100%; 
                margin-bottom: 10px; 
                page-break-inside: avoid; /* Hindari pemisahan di tengah tabel */
            }
            
            /* Left and right columns */
            .left-column { 
                width: 48%; 
                float: left; 
                margin-right: 2%; 
            }
            .right-column { 
                width: 48%; 
                float: right; 
            }
            .clearfix::after {
                content: '';
                display: table;
                clear: both;
            }
            
            /* Score table styles */
            .score-table { 
                width: 100%; 
                border-collapse: collapse; 
                font-size: {$fontSize}pt; 
                table-layout: fixed; /* Agar kolom konsisten */
            }
            .score-table th { 
                background: #808080; 
                color: #fff; 
                padding: 4px 2px; 
                border: 1px solid #000; 
                text-align: center; 
                font-weight: bold; 
                font-size: {$fontSize}pt; 
            }
            .score-table td { 
                padding: 3px 2px; 
                border: 1px solid #000; 
                vertical-align: middle; 
                height: {$rowHeight}; 
                overflow: hidden; 
                text-overflow: ellipsis; 
            }
            .score-table td.center { 
                text-align: center; 
            }
            
            /* Column widths */
            .col-kode { width: 15%; }
            .col-mk { width: 55%; }
            .col-sks { width: 10%; }
            .col-nilai { width: 10%; }
            .col-mutu { width: 10%; }
            
            /* Footer styles */
            .footer-container { 
                width: 100%; 
                margin-top: 15px; 
                page-break-inside: avoid; 
            }
            .footer-left { 
                width: 60%; 
                float: left; 
            }
            .footer-right { 
                width: 40%; 
                float: right; 
                text-align: center; 
            }
            
            /* Print optimizations */
            @media print {
                body { -webkit-print-color-adjust: exact; }
                .columns-container, .footer-container { page-break-inside: avoid; }
            }
            
            /* Utility classes */
            .text-bold { font-weight: bold; }
            .text-center { text-align: center; }
            .mb-10 { margin-bottom: 10px; }
            .mt-10 { margin-top: 10px; }
        </style>
    ";

    // ---------- Build HTML ----------
    $html = '<!doctype html><html><head><meta charset="utf-8">' . $css . '</head><body>';

    // Header section
    $html .= '<table class="header-table">';
    $html .= '<tr>';
    $html .= '<td style="width: 80px">Nama</td><td style="width: 5px">:</td><td style="width: 250px">' . h($mhs['nama']) . '</td>';
    $html .= '<td style="width: 150px">Tempat/Tanggal Lahir</td><td style="width: 5px">:</td><td>' . h($tgl_lahir) . '</td>';
    $html .= '</tr>';
    $html .= '<tr>';
    $html .= '<td>NIM</td><td>:</td><td>' . h($nim) . '</td>';
    $html .= '<td>Tanggal Masuk</td><td>:</td><td>' . tglIndo($masuk) . '</td>';
    $html .= '</tr>';
    $html .= '<tr>';
    $html .= '<td>Program Studi</td><td>:</td><td>' . h($mhs['programstudi']) . '</td>';
    $html .= '<td>Tanggal Lulus</td><td>:</td><td>' . tglIndo($lulus) . '</td>';
    $html .= '</tr>';
    $html .= '</table>';

    // Two-column content
    $html .= '<div class="columns-container">';

    // Left column
    $html .= '<div class="left-column">';
    $html .= '<table class="score-table">';
    $html .= '<tr>';
    $html .= '<th class="col-kode">Kode</th>';
    $html .= '<th class="col-mk">Mata Kuliah</th>';
    $html .= '<th class="col-sks">SKS</th>';
    $html .= '<th class="col-nilai">Nilai</th>';
    $html .= '<th class="col-mutu">Mutu</th>';
    $html .= '</tr>';

    foreach ($leftRows as $r) {
        $html .= '<tr>';
        $html .= '<td class="center col-kode">' . h($r['kodemk']) . '</td>';
        $html .= '<td class="col-mk">' . h($r['namamk']) . '</td>';
        $html .= '<td class="center col-sks">' . $r['sks'] . '</td>';
        $html .= '<td class="center col-nilai">' . h($r['huruf']) . '</td>';
        $html .= '<td class="center col-mutu">' . $r['angka'] . '</td>';
        $html .= '</tr>';
    }

    $html .= '</table>';
    $html .= '</div>'; // end left-column

    // Right column
    $html .= '<div class="right-column">';
    $html .= '<table class="score-table">';
    $html .= '<tr>';
    $html .= '<th class="col-kode">Kode</th>';
    $html .= '<th class="col-mk">Mata Kuliah</th>';
    $html .= '<th class="col-sks">SKS</th>';
    $html .= '<th class="col-nilai">Nilai</th>';
    $html .= '<th class="col-mutu">Mutu</th>';
    $html .= '</tr>';

    foreach ($rightRows as $r) {
        $html .= '<tr>';
        $html .= '<td class="center col-kode">' . h($r['kodemk']) . '</td>';
        $html .= '<td class="col-mk">' . h($r['namamk']) . '</td>';
        $html .= '<td class="center col-sks">' . $r['sks'] . '</td>';
        $html .= '<td class="center col-nilai">' . h($r['huruf']) . '</td>';
        $html .= '<td class="center col-mutu">' . $r['angka'] . '</td>';
        $html .= '</tr>';
    }

    $html .= '</table>';
    $html .= '</div>'; // end right-column

    $html .= '<div class="clearfix"></div>';
    $html .= '</div>'; // end columns-container

    // Footer section
    $html .= '<div class="footer-container">';
    $html .= '<div class="footer-left">';
    $html .= 'Total Mata Kuliah : ' . $total_mk . '<br>';
    $html .= 'Total SKS : ' . $total_sks . '<br>';
    $html .= 'Indeks Prestasi Kumulatif (IPK) : ' . number_format($ipk, 2) . '';
    $html .= '</div>';

    $html .= '<div class="footer-right">';
    $html .= 'Tangerang, ' . tglIndo($cetak) . '<br>';
    $html .= 'Direktur<br>';
    $html .= '<div style="height: 60px"></div>';
    $html .= '<span class="text-bold" style="text-decoration: underline;">Dr. Dra. Ita Mariza, M.M.</span>';
    $html .= '</div>';

    $html .= '<div class="clearfix"></div>';
    $html .= '</div>'; // end footer-container

    $html .= '</body></html>';

    // Generate PDF
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');

    // Optimize for single page
    $dompdf->set_option('isPhpEnabled', true);
    $dompdf->set_option('isHtml5ParserEnabled', true);

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
echo "<div style='font-family: Arial, sans-serif; padding: 20px;'>";
echo "<h3>Proses Generate Transkrip Massal</h3>";
echo "<p>Total mahasiswa: " . count($students) . "</p>";
echo "<div style='border: 1px solid #ddd; padding: 10px; border-radius: 5px; background: #f9f9f9;'>";

foreach ($students as $index => $student) {
    $nim = $student['nim'];
    $nama = $student['nama'];

    echo "<div style='margin-bottom: 5px;'>";
    echo "<strong>" . ($index + 1) . "/" . count($students) . ":</strong> $nim - $nama ... ";

    $pdfFile = generateSingleTranskrip($db, $nim, $masuk, $lulus, $cetak, $tempDir);

    if ($pdfFile) {
        $pdfFiles[] = $pdfFile;
        $successCount++;
        echo "<span style='color:green; font-weight:bold;'>✓ SUKSES</span>";
    } else {
        $errorCount++;
        echo "<span style='color:red; font-weight:bold;'>✗ GAGAL</span>";
    }

    echo "</div>";

    // Flush output untuk real-time progress
    flush();
    ob_flush();
}

echo "</div>"; // end progress container
echo "<br><hr>";
echo "<h4>Ringkasan:</h4>";
echo "<p><strong>Berhasil:</strong> $successCount transkrip</p>";
echo "<p><strong>Gagal:</strong> $errorCount transkrip</p><br>";

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
$mergedPdfPath = __DIR__ . '/transkrip_massal_' . $tahun . '_' . $prodi . '_' . date('Ymd_His') . '.pdf';

echo "<br><h4>Menggabungkan PDF...</h4>";

if (mergePDFs($pdfFiles, $mergedPdfPath)) {
    echo "<div style='color:green;'><strong>✓ Semua transkrip berhasil digabungkan!</strong></div><br>";

    $fileSize = filesize($mergedPdfPath);
    $fileSizeMB = round($fileSize / (1024 * 1024), 2);

    echo "File: " . basename($mergedPdfPath) . "<br>";
    echo "Ukuran: $fileSizeMB MB<br>";
    echo "Jumlah halaman: " . count($pdfFiles) . " (satu transkrip per halaman)<br><br>";

    // Provide download link
    echo '<a href="' . basename($mergedPdfPath) . '" class="btn btn-success" style="padding:10px 20px; background:#28a745; color:white; text-decoration:none; border-radius:5px;" download>⬇ Download Transkrip Gabungan</a>';
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

// Add CSS for buttons
echo "
<style>
    .btn-download {
        display: inline-block;
        padding: 12px 24px;
        background: #28a745;
        color: white;
        text-decoration: none;
        border-radius: 5px;
        font-weight: bold;
        border: none;
        cursor: pointer;
        transition: background 0.3s;
    }
    .btn-download:hover {
        background: #218838;
        text-decoration: none;
        color: white;
    }
    
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        line-height: 1.6;
        color: #333;
        max-width: 1000px;
        margin: 0 auto;
        padding: 20px;
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    }
    
    h3, h4 {
        color: #2c3e50;
        border-bottom: 2px solid #3498db;
        padding-bottom: 10px;
    }
    
    hr {
        border: none;
        height: 1px;
        background: linear-gradient(to right, transparent, #3498db, transparent);
        margin: 30px 0;
    }
</style>
";

echo "</div>"; // end main container
