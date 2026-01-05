<?php
require_once 'config/database.php';
require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Jika download template
if (isset($_GET['download'])) {
    // header('Content-Type: application/vnd.ms-excel');
    // header('Content-Disposition: attachment;filename="template_import_kurikulum.xlsx"');
    // header('Cache-Control: max-age=0');

    // // Create simple template content
    // $template = "No\tKode MK\tNama MK\tSemester\tSKS\tJenis MK\tProdi\tDosen\tTahun Akademik\n";
    // $template .= "1\tMKB 3112\tGambar Teknik\t1\t2\tWajib\t3 TEKNOLOGI INDUSTRI\tNama Dosen\t20251\n";
    // $template .= "2\tMKK 3101\tKimia Terapan 1\t1\t2\tWajib\t3 TEKNOLOGI INDUSTRI\tNama Dosen\t20251\n";

    // echo $template;

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $sheet->fromArray([
        ['No', 'Kode MK', 'Nama MK', 'Semester', 'SKS', 'Jenis MK', 'Prodi', 'Dosen', 'Tahun Akademik'],
        [1, 'MKB 3112', 'Gambar Teknik', 1, 2, 'Wajib', '3 TEKNOLOGI INDUSTRI', 'Nama Dosen', '20251'],
        [2, 'MKK 3101', 'Kimia Terapan 1', 1, 2, 'Wajib', '3 TEKNOLOGI INDUSTRI', 'Nama Dosen', '20251']
    ]);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="template_import_kurikulum.xlsx"');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Download Template</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <div class="container mt-5">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">Download Template Import Kurikulum</h4>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <h5><i class="fas fa-info-circle"></i> Petunjuk Penggunaan Template:</h5>
                    <ol>
                        <li>Download template Excel</li>
                        <li>Isi data sesuai dengan format kolom yang tersedia</li>
                        <li>Simpan file dengan format .xlsx</li>
                        <li>Upload file melalui halaman Import Kurikulum</li>
                        <li>Pastikan data prodi sesuai dengan yang ada di database</li>
                    </ol>
                </div>

                <div class="card mb-3">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Format Kolom Template</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered">
                            <thead class="table-dark">
                                <tr>
                                    <th>Kolom</th>
                                    <th>Keterangan</th>
                                    <th>Contoh</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Kode MK</td>
                                    <td>Kode mata kuliah (unik)</td>
                                    <td>MKB 3112</td>
                                </tr>
                                <tr>
                                    <td>Nama MK</td>
                                    <td>Nama mata kuliah</td>
                                    <td>Gambar Teknik</td>
                                </tr>
                                <tr>
                                    <td>Semester</td>
                                    <td>Semester (1-8)</td>
                                    <td>1</td>
                                </tr>
                                <tr>
                                    <td>SKS</td>
                                    <td>Jumlah SKS (0-5)</td>
                                    <td>2</td>
                                </tr>
                                <tr>
                                    <td>Jenis MK</td>
                                    <td>Wajib/Pilihan</td>
                                    <td>Wajib</td>
                                </tr>
                                <tr>
                                    <td>Prodi</td>
                                    <td>Karena ada kemungkinan berbeda dosen untuk setiap kelas, maka kolom ini di isi kelas bukan program studi</td>
                                    <td>3 TEKNIK ELEKTRONIKA A</td>
                                </tr>
                                <tr>
                                    <td>Dosen</td>
                                    <td>Nama pengampu</td>
                                    <td>Nama Dosen, S.T., M.T.</td>
                                </tr>
                                <tr>
                                    <td>Tahun Akademik</td>
                                    <td>Format: YYYYS (S=1 ganjil, 2 genap)</td>
                                    <td>20251</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="text-center">
                    <a href="?download=true" class="btn btn-success btn-lg">
                        <i class="fas fa-download"></i> Download Template Excel
                    </a>
                    <a href="import.php" class="btn btn-primary btn-lg ms-3">
                        <i class="fas fa-upload"></i> Ke Halaman Import
                    </a>
                    <a href="index.php" class="btn btn-secondary btn-lg ms-3">
                        <i class="fas fa-home"></i> Kembali ke Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>

</html>