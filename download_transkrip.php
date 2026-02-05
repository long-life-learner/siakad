<?php
$file = $_GET['file'] ?? '';
$mode = $_GET['mode'] ?? 'preview';

$baseDir = sys_get_temp_dir();
$filePath = realpath($baseDir . DIRECTORY_SEPARATOR . basename($file));

if (!$filePath || !file_exists($filePath)) {
    http_response_code(404);
    exit('File tidak ditemukan');
}

header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($filePath));
header('Accept-Ranges: bytes');

if ($mode === 'download') {
    header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
} else {
    // default: preview
    header('Content-Disposition: inline; filename="' . basename($filePath) . '"');
}

readfile($filePath);
exit;
