<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);

require_once "dompdf/autoload.inc.php";
require_once "config/database.php";

use Dompdf\Dompdf;

// ========================
// KONEKSI DATABASE
// ========================
$database = new Database();
$db = $database->getConnection();

// ========================
// PARAMETER
// ========================
$mode  = $_GET['mode']  ?? 'massal';
$prodi = $_GET['prodi'] ?? '';
$tahun = $_GET['tahun'] ?? '';
$nim   = $_GET['nim']   ?? '';
$nama  = $_GET['nama']  ?? '';

$lulus = $_GET['tgl_lulus'] ?? '';
$cetak = $_GET['tgl_cetak'] ?? '';

// ========================
// HELPER
// ========================
function indoTanggal($tgl)
{
    if (!$tgl) return '';
    $bulan = [
        1 => 'Januari',
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
    $x = explode('-', $tgl);
    return $x[2] . ' ' . $bulan[(int)$x[1]] . ' ' . $x[0];
}

$lulus = indoTanggal(date('Y-m-d', strtotime($lulus)));
$cetak = indoTanggal(date('Y-m-d', strtotime($cetak)));

function h($s)
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

// ========================
// QUERY
// ========================
$namaFile = "Ijazah_";
$params   = [];

if ($mode === "massal") {
    $query = "
        SELECT a.nim, nama, programstudi, tempatlahir, tanggallahir,
               a.nik, a.nomor_ijazah
        FROM mahasiswa
        JOIN after_graduate a ON mahasiswa.nim = a.nim
        WHERE status='Lulus' AND programstudi=? AND tahunmasuk=?
        ORDER BY nama ASC
    ";
    $params = [$prodi, $tahun];
    $namaFile .= "{$tahun}_{$prodi}";
} elseif ($mode === "nim") {
    $query = "
        SELECT a.nim, nama, programstudi, tempatlahir, tanggallahir,
               a.nik, a.nomor_ijazah
        FROM mahasiswa
        JOIN after_graduate a ON mahasiswa.nim = a.nim
        WHERE mahasiswa.nim=? AND status='Lulus'
        LIMIT 1
    ";
    $params = [$nim];
    $namaFile .= $nim;
} else {
    $query = "
        SELECT a.nim, nama, programstudi, tempatlahir, tanggallahir,
               a.nik, a.nomor_ijazah
        FROM mahasiswa
        JOIN after_graduate a ON mahasiswa.nim = a.nim
        WHERE nama LIKE ? AND status='Lulus'
        ORDER BY nama ASC
    ";
    $params = ['%' . $nama . '%'];
    $namaFile .= preg_replace('/\s+/', '_', $nama);
}

// ========================
// EXECUTE QUERY
// ========================
$stmt = $db->prepare($query);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$rows) {
    die("DATA TIDAK DITEMUKAN");
}



$html = "<html><head><meta charset='utf-8'>{$css}</head><body>";

$noUrut = 1;

foreach ($rows as $r) {

    $tglLahir = indoTanggal($r['tanggallahir']);

    // Mapping Prodi
    switch (trim($r['programstudi'])) {
        case 'Teknik Elektronika':
            $prodiNama = 'Teknik Elektronika';
            $akre = 'LAM Teknik No. 0094/SK/LAM Teknik/VD3/IV/2024';
            $singkat = 'TE';
            break;
        case 'Teknik Mesin':
            $prodiNama = 'Teknik Mesin';
            $akre = 'LAM Teknik No. 0093/SK/LAM Teknik/VD3/IV/2024';
            $singkat = 'TM';
            break;
        default:
            $prodiNama = 'Teknologi Industri';
            $akre = 'BAN-PT No. 2454/SK/BAN-PT/Akred/Dipl-III/IV/2021';
            $singkat = 'TI';
    }

    /* FRONT */
    $html .= "
    <div class='page'>
        <div class='noijazah'>No Ijazah: {$noUrut}/PGT/{$singkat}/" . date('Y') . "</div>
        <div class='pin'>PIN: {$r['nomor_ijazah']}</div>

        <div class='center start'>Dengan ini menyatakan bahwa</div>
        <div class='nama center'>{$r['nama']}</div>
        <div class='center'>NIM / NIK : {$r['nim']} / {$r['nik']}</div>
        <div class='center'>Lahir di {$r['tempatlahir']} pada {$tglLahir}</div>
        <div class='center'>Program Studi {$prodiNama}, Terakreditasi {$akre}</div>
        <div class='center'>Lulus pada tanggal {$lulus}</div>
        <div class='gelar center'>Ahli Madya (A.Md.)</div>
        <div class='center'>Diberikan di Tangerang, {$cetak}</div>

        <div class='signature'>
            Direktur<br><br><br><br>
            <u>Dr. Dra. Ita Mariza, M.M.</u>
        </div>
    </div>

    <div class='page'>
        <div class='rect'>6 x 6 cm</div>
        <div style='margin-top:40px;margin-left:60px'>
            <strong>Nama:</strong> {$r['nama']}<br>
            <strong>NIM:</strong> {$r['nim']}<br>
            <strong>Tanggal Lulus:</strong> {$lulus}
        </div>
    </div>
    ";

    $noUrut++;
}

$html .= "</body></html>";

// ========================
// RENDER PDF
// ========================
$dompdf = new Dompdf([
    'defaultFont' => 'Times-Roman',
    'isRemoteEnabled' => true
]);

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

// ========================
// OUTPUT (AMAN DI CHROME)
// ========================
$dompdf->stream(
    preg_replace('/[^A-Za-z0-9_\-]/', '_', $namaFile) . ".pdf",
    ["Attachment" => false] // true = download, false = preview
);

exit;
