<?php
// Download template Excel untuk import after_graduate

require_once "vendor/autoload.php";

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Buat spreadsheet baru
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle("Template Import");

// Header
$sheet->setCellValue('A1', 'NIM');
$sheet->setCellValue('B1', 'Nomor Ijazah');
$sheet->setCellValue('C1', 'NIK');

// Style header
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'border' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
];

$sheet->getStyle('A1:C1')->applyFromArray($headerStyle);

// Set lebar kolom
$sheet->getColumnDimension('A')->setWidth(15);
$sheet->getColumnDimension('B')->setWidth(20);
$sheet->getColumnDimension('C')->setWidth(20);

// Contoh data (baris 2-4)
$examples = [
    ['2021001', 'IJZ-2024-001', '3201234567890123'],
    ['2021002', 'IJZ-2024-002', '3201234567890124'],
    ['2021003', 'IJZ-2024-003', '3201234567890125']
];

$row = 2;
foreach ($examples as $example) {
    $sheet->setCellValue('A' . $row, $example[0]);
    $sheet->setCellValue('B' . $row, $example[1]);
    $sheet->setCellValue('C' . $row, $example[2]);

    // Style data row
    $sheet->getStyle('A' . $row . ':C' . $row)->applyFromArray([
        'border' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT]
    ]);

    $row++;
}

// Freeze header row
$sheet->freezePane('A2');

// Output file
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="Template Import Data Setelah Lulus ' . time() . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
