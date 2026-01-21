<?php
// cleanup_transkrip.php
session_start();

// Security check - hanya izinkan dari aplikasi yang sama
$referer = $_SERVER['HTTP_REFERER'] ?? '';
if (strpos($referer, $_SERVER['HTTP_HOST']) === false) {
    die("Akses ditolak!");
}

// Hapus file PDF gabungan
if (isset($_GET['file'])) {
    $file = basename($_GET['file']);
    $filePath = __DIR__ . '/' . $file;

    if (file_exists($filePath) && strpos($file, 'transkrip_massal_') === 0) {
        unlink($filePath);
        echo "File $file telah dihapus.";
    }
}

// Hapus direktori temporary
if (isset($_GET['dir'])) {
    $dir = basename($_GET['dir']);
    $dirPath = __DIR__ . '/' . $dir;

    if (file_exists($dirPath) && is_dir($dirPath) && strpos($dir, 'temp_transkrip_') === 0) {
        // Hapus semua file dalam direktori
        $files = glob($dirPath . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($dirPath);
        echo "Direktori temporary telah dihapus.";
    }
}
