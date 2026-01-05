<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
require_once "config/database.php";
// gunakan Database helper (PDO)
$database = new Database();
$db = $database->getConnection();

// Gunakan PhpSpreadsheet jika tersedia, jika tidak gunakan method alternatif
// Pastikan sudah install: composer require phpoffice/phpspreadsheet
require_once "vendor/autoload.php";

use PhpOffice\PhpSpreadsheet\IOFactory;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!isset($_FILES['file'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'File tidak ditemukan']);
    exit;
}

$file = $_FILES['file'];

// Validasi file
if ($file['error'] !== UPLOAD_ERR_OK) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error saat upload: ' . $file['error']]);
    exit;
}

$fileName = $file['name'];
$fileTmp = $file['tmp_name'];
$fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

// Cek ekstensi
if ($fileExt !== 'xlsx') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'File harus berformat .xlsx']);
    exit;
}

try {
    // Load spreadsheet
    $spreadsheet = IOFactory::load($fileTmp);
    $worksheet = $spreadsheet->getActiveSheet();

    $imported = 0;
    $skipped = 0;
    $errors = [];

    // Loop dari baris 2 (baris 1 adalah header)
    foreach ($worksheet->getRowIterator(2) as $row) {
        $cellIterator = $row->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(false);

        $cells = [];
        foreach ($cellIterator as $cell) {
            $cells[] = $cell->getValue();
        }

        // Kolom: NIM, Nomor Ijazah, NIK
        $nim = trim($cells[0] ?? '');
        $nomor_ijazah = trim($cells[1] ?? '');
        $nik = trim($cells[2] ?? '');

        // Skip jika NIM kosong
        if (empty($nim)) {
            continue;
        }

        // Validasi NIM ada di database (PDO)
        try {
            $checkNim = $db->prepare("SELECT nim FROM mahasiswa WHERE nim = :nim");
            $checkNim->execute([':nim' => $nim]);
            $found = $checkNim->fetch(PDO::FETCH_ASSOC);
            if (!$found) {
                $skipped++;
                $errors[] = "NIM $nim tidak ditemukan di database";
                continue;
            }

            // Insert atau update ke tabel after_graduate
            $stmt = $db->prepare(
                "INSERT INTO after_graduate (nim, nomor_ijazah, nik, created_at, updated_at)
                 VALUES (:nim, :noijazah, :nik, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE nomor_ijazah = :noijazah, nik = :nik, updated_at = NOW()"
            );

            $res = $stmt->execute([
                ':nim' => $nim,
                ':noijazah' => $nomor_ijazah,
                ':nik' => $nik
            ]);

            if ($res) {
                $imported++;
            } else {
                $errInfo = $stmt->errorInfo();
                $errors[] = "Gagal insert NIM $nim: " . ($errInfo[2] ?? 'Unknown error');
            }
        } catch (Exception $e) {
            $errors[] = 'Error DB: ' . $e->getMessage();
            continue;
        }
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'imported' => $imported,
        'skipped' => $skipped,
        'message' => "Import selesai: $imported data berhasil" . ($skipped > 0 ? ", $skipped dilewati" : '')
    ]);
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

exit;
