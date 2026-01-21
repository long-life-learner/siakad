<?php
// download_transkrip.php
session_start();

$file = $_GET['file'] ?? '';
if (empty($file)) {
    die("File tidak ditemukan");
}

// Security: hanya izinkan file transkrip
if (!preg_match('/^transkrip_massal_.*\.pdf$/', $file)) {
    die("File tidak diizinkan");
}

$filepath = '/tmp/' . $file;

if (!file_exists($filepath)) {
    die("File tidak ditemukan");
}

// Set headers untuk download
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $file . '"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

readfile($filepath);

// Hapus file setelah didownload
unlink($filepath);
exit;
