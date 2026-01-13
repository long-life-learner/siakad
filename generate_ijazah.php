<?php
ob_start();
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
require_once "dompdf/autoload.inc.php";
require_once "config/database.php";
// Use Database helper (PDO)
$database = new Database();
$db = $database->getConnection();

use Dompdf\Dompdf;

// ========================
// PARAMETER
// ========================
$mode  = $_GET['mode'] ?? 'massal';       // massal | nim | nama
$prodi = $_GET['prodi'] ?? '';
$tahun = $_GET['tahun'] ?? '';
$nim   = $_GET['nim'] ?? '';
$nama  = $_GET['nama'] ?? '';

$lulus = $_GET['tgl_lulus'];
$cetak = $_GET['tgl_cetak'];

function indoTanggal($tgl)
{
    $bulan = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember'
    ];

    $x = explode("-", $tgl);
    return $x[2] . " " . $bulan[(int)$x[1]] . " " . $x[0];
}

$lulus = indoTanggal(date("Y-m-d", strtotime($lulus ?? $lulus)));
$cetak = indoTanggal(date("Y-m-d", strtotime($cetak)));


// ========================
// PERSIAPAN QUERY
// ========================
$query = "";
$params = [];
$types  = "";
$namaFile = "Ijazah_";
if ($mode === "massal") {
    $query = "SELECT a.nim, nama, programstudi, tempatlahir, tanggallahir, status, tahunmasuk, a.nik as nik , a.nomor_ijazah as nomor_ijazah
              FROM mahasiswa
                JOIN after_graduate as a ON mahasiswa.nim = a.nim
              WHERE status='Lulus' AND programstudi=? AND tahunmasuk=?
              ORDER BY nama ASC";
    $params = [$prodi, $tahun];
    $types  = "ss";
    $namaFile .= $tahun . "_" . $prodi;
} else if ($mode === "nim") {
    $query = "SELECT a.nim, nama, programstudi, tempatlahir, tanggallahir, status, tahunmasuk, a.nik as nik , a.nomor_ijazah as nomor_ijazah
              FROM mahasiswa JOIN after_graduate as a ON mahasiswa.nim = a.nim WHERE mahasiswa.nim=? LIMIT 1";
    $params = [$nim];
    $types  = "s";
    $namaFile .= $nim;
} else if ($mode === "nama") {
    $query = "SELECT a.nim, nama, programstudi, tempatlahir, tanggallahir, status, tahunmasuk, a.nik as nik , a.nomor_ijazah as nomor_ijazah
              FROM mahasiswa JOIN after_graduate AS a  ON mahasiswa.nim = a.nim WHERE nama LIKE ? ORDER BY nama ASC";
    $params = ["%" . $nama . "%"];
    $types  = "s";
    $namaFile .= preg_replace('/\s+/', '_', $nama);
}


// ========================
// EXECUTE QUERY (PDO)
// ========================
try {
    $stmt = $db->prepare($query);
    // execute with params
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die('Query error: ' . $e->getMessage());
}

if (!$rows) {
    die("DATA TIDAK DITEMUKAN");
}
function h($s)
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}


$css = '
    <style>
        body { margin:0; padding:0; font-family: "Times New Roman", serif; color:#000; }
        @font-face {
            font-family: "BrushScript";
            src: url("./fonts/Brush Script Bold.ttf") format("truetype");
            font-weight: normal;
            font-style: normal;
        }
        .page {
            // width: 842px;
            // height: 595px;
            margin: 0 auto;
            // box-sizing: border-box;
            page-break-after: always;
        }
        /* layout simple, center-aligned like JRXML Anda */
        .center { text-align:center; }

        .line-space { margin: 6px 0; }

        .nama { font-family: "BrushScript", serif; font-size:28pt; }

        .gelar { font-family: "BrushScript", serif; font-size:24pt; }

        .start {margin-top: 177px;}

        .noijazah,
        .pin {
                    margin-left: 499px;
                    text-align: right;
                    font-size: 11pt !important;
                }

        .signature { margin-top:20px; font-size:14pt; margin-left: 281px;text-align: center; }

        .standar {
                    font-size: 14pt !important;
                }
        .back-table { font-size:12pt; margin-top:40px; margin-left:60px; }

        .signature-back { margin-top:40px; margin-left:60px; font-size:12pt; }
        .rect { width:6cm; height:6cm; border:1px solid #000; float:right; margin-right:40px; text-align:center; font-size:10pt; position: relative; top: 350px; }

    </style>';

$htmlParts = [];
$htmlParts[] = "<html><head><meta charset='utf-8'>{$css}</head><body>";
$noUrut = 1;
$prodiSingkat = '';
$akre = '';
$prodi = '';
// loop tiap mahasiswa
foreach ($rows as $r) {
    // format tanggal lahir jika ada
    $tgl_lahir_str = $r['tanggallahir'];

    if ($tgl_lahir_str) {
        // coba format yyyy-mm-dd -> dd MMMMM yyyy (Indonesia)
        $timestamp = strtotime($tgl_lahir_str);
        if ($timestamp !== false) {
            $day = date('d', $timestamp);
            $month = date('n', $timestamp);
            $year = date('Y', $timestamp);

            $bulanIndo = [
                '',
                'Januari',
                'Februari',
                'Maret',
                'April',
                'Mei',
                'Juni',
                'Juli',
                'Agustus',
                'September',
                'Oktober',
                'November',
                'Desember'
            ];

            $tgl_lahir = $day . ' ' . $bulanIndo[$month] . ' ' . $year;
        } else {
            $tgl_lahir = h($tgl_lahir_str);
        }
    } else {
        $tgl_lahir = '';
    }


    $r['programstudi'] = trim($r['programstudi']);
    if ($r['programstudi'] == "Teknik Elektronika") {
        $prodi = "Teknik Elektronika";
        $akre = "LAM Teknik No. 0094/SK/LAM Teknik/VD3/IV/2024";
        $prodiSingkat = "TE";
    } else if ($r['programstudi'] == "Teknik Mesin") {
        $prodi = "Teknik Mesin";
        $akre = "LAM Teknik No. 0093/SK/LAM Teknik/VD3/IV/2024";
        $prodiSingkat = "TM";
    } else if ($r['programstudi'] == "Teknologi Industri" || $r['programstudi'] == "Teknik Industri") {
        $prodi = "Teknologi Industri";
        $akre = "BAN-PT No. 2454/SK/BAN-PT/Akred/Dipl-III/IV/2021";
        $prodiSingkat = "TI";
    }

    // halaman depan (front)
    $front = "
        <div class='page'>
            <div class='noijazah'>No Ijazah: {$noUrut}/PGT/{$prodiSingkat}/" . date('Y') . "</div>
            <div class='pin'>PIN: {$r['nomor_ijazah']}</div>

            <div class='center line-space standar start'>Dengan ini menyatakan bahwa</div>

            <div class='nama center'>{$r['nama']}</div>

            <div class='center line-space standar'>NIM / NIK : {$r['nim']} / {$r['nik']}</div>

            <div class='center line-space standar'>Lahir di {$r['tempatlahir']} pada tanggal {$tgl_lahir}</div>

            <div class='center line-space standar'>Telah menyelesaikan dengan baik dan memenuhi segala syarat jenjang pendidikan Diploma III pada</div>
            <div class='center line-space standar'>Program Studi {$prodi}, Terakreditasi {$akre}</div>
            <div class='center line-space standar'>Serta dinyatakan lulus pada tanggal {$lulus} sehingga kepadanya diberikan sebutan</div>
            <div class='gelar center'>Ahli Madya (A.Md.)</div>
            <div class='center line-space standar'>Dengan segala hak, wewenang, dan kewajiban yang melekat pada sebutan tersebut.</div>
            <div class='center line-space standar'>Diberikan di Tangerang pada tanggal {$cetak}</div>

            <div class='signature'>Direktur<br><br><br><br><u>Dr. Dra. Ita Mariza, M.M.</u></div>
        </div>";



    $back = "
        <div class='page'>
            <div class='rect'>6 x 6 cm</div>

            <div class='back-table'>
                <div><strong>Pemilik ijazah ini dinyatakan lulus</strong></div>

                <div style='margin-top:18px; font-size:12pt;'><span style='display:inline-block; width:240px;'>Nama</span> : <strong>{$r['nama']}</strong></div>

                <div style='margin-top:6px; font-size:12pt;'><span style='display:inline-block; width:240px;'>Nomor Induk Mahasiswa</span> : {$r['nim']}</div>

                <div style='margin-top:6px; font-size:12pt;'><span style='display:inline-block; width:240px;'>Tanggal Lulus</span> : {$lulus}</div>

                <div class='signature-back' style='margin-top:449px;'>
                    <div style='width:270px; border-bottom:1px solid #000; text-align:center;'>{$r['nama']}</div>
                </div>
            </div>
        </div>";

    $htmlParts[] = $front;
    $htmlParts[] = $back;
    $noUrut++;
}

// penutup html
$htmlParts[] = "</body></html>";

$fullHtml = implode("\n", $htmlParts);

try {

    // Render PDF
    $dompdf = new Dompdf();
    $dompdf->loadHtml($fullHtml);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();
    // $dompdf->stream("Ijazah.pdf", ["Attachment" => false]);

    // Bersihkan semua buffer sebelum stream
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $namaFile . '.pdf"');
    echo $dompdf->output();
    // close DB / cleanup PDO objects
    $stmt = null;
    $db = null;
    exit;
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
