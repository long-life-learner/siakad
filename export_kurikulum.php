<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Get filter from URL
$filter_tahun = isset($_GET['tahun']) ? $_GET['tahun'] : '';
$filter_prodi = isset($_GET['prodi']) ? $_GET['prodi'] : '';
$filter_kodemk = isset($_GET['kodemk']) ? $_GET['kodemk'] : '';
$filter_namamk = isset($_GET['namamk']) ? $_GET['namamk'] : '';

// Query with filter
$query = "SELECT * FROM kurikulum WHERE 1=1";
$params = [];

if ($filter_tahun) {
    $query .= " AND tahun = :tahun";
    $params[':tahun'] = $filter_tahun;
}

if ($filter_prodi) {
    $query .= " AND prodi LIKE :prodi";
    $params[':prodi'] = "%$filter_prodi%";
}

if ($filter_kodemk) {
    $query .= " AND kodemk LIKE :kodemk";
    $params[':kodemk'] = "%$filter_kodemk%";
}

if ($filter_namamk) {
    $query .= " AND namamk LIKE :namamk";
    $params[':namamk'] = "%$filter_namamk%";
}

$query .= " ORDER BY tahun DESC, sem, kodemk";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

$headers = [
    'A' => 'No',
    'B' => 'Kode MK',
    'C' => 'Nama MK',
    'D' => 'Program Studi',
    'E' => 'Dosen',
    'F' => 'Semester',
    'G' => 'SKS',
    'H' => 'Tahun Akademik',
    'I' => 'Surat Tugas',
];

foreach ($headers as $col => $text) {
    $sheet->setCellValue($col . '1', $text);
}

$rowNum = 2;
$no = 1;
foreach ($data as $row) {
    $sheet->setCellValue('A' . $rowNum, $no);
    $sheet->setCellValue('B' . $rowNum, $row['kodemk']);
    $sheet->setCellValue('C' . $rowNum, $row['namamk']);
    $sheet->setCellValue('D' . $rowNum, $row['prodi']);
    $sheet->setCellValue('E' . $rowNum, $row['dosen']);
    $sheet->setCellValue('F' . $rowNum, $row['sem']);
    $sheet->setCellValue('G' . $rowNum, $row['sks']);
    $sheet->setCellValue('H' . $rowNum, $row['tahun']);
    $sheet->setCellValue('I' . $rowNum, $row['surattugas']);
    $rowNum++;
    $no++;
}

// Auto-size columns
foreach (range('A', 'I') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Stream XLSX to browser
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="kurikulum_' . date('YmdHis') . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
