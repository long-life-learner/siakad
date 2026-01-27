<?php
session_start();

$file = $_GET['file'] ?? '';
$file = basename($file); // cegah path traversal

if ($file === '') {
    http_response_code(400);
    die("File tidak valid.");
}

$tmpBase = rtrim(sys_get_temp_dir(), '/\\');
$fullPath = $tmpBase . DIRECTORY_SEPARATOR . $file;

if (!file_exists($fullPath)) {
    http_response_code(404);
    die("File tidak ditemukan atau sudah dibersihkan.");
}

header('Content-Description: File Transfer');
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $file . '"');
header('Content-Length: ' . filesize($fullPath));
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

readfile($fullPath);
exit;
