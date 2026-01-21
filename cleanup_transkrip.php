<?php
// cleanup_transkrip.php
session_start();

// Security check - hanya izinkan dari aplikasi yang sama
$referer = $_SERVER['HTTP_REFERER'] ?? '';
if (strpos($referer, $_SERVER['HTTP_HOST']) === false) {
    die("Akses ditolak!");
}

// Fungsi untuk force delete
function deleteDirectoryForce($dir)
{
    if (!file_exists($dir)) {
        return "Directory tidak ditemukan";
    }

    // Gunakan system command untuk Linux/Docker
    exec("rm -rf " . escapeshellarg($dir) . " 2>&1", $output, $returnCode);

    if ($returnCode === 0) {
        return "Directory berhasil dihapus";
    } else {
        // Coba dengan chmod dulu
        exec("chmod -R 777 " . escapeshellarg($dir) . " 2>&1");
        exec("rm -rf " . escapeshellarg($dir) . " 2>&1", $output, $returnCode);

        if ($returnCode === 0) {
            return "Directory berhasil dihapus setelah chmod";
        } else {
            return "Gagal menghapus directory. Return code: $returnCode";
        }
    }
}

// Hapus file PDF gabungan dari /tmp
if (isset($_GET['file'])) {
    $file = basename($_GET['file']);
    $filePath = '/tmp/' . $file;

    if (file_exists($filePath) && strpos($file, 'transkrip_massal_') === 0) {
        if (unlink($filePath)) {
            echo "File $file telah dihapus dari /tmp.";
        } else {
            // Force delete
            exec("rm -f " . escapeshellarg($filePath) . " 2>&1");
            echo "File $file telah dihapus (force).";
        }
    }
}

// Hapus direktori temporary
if (isset($_GET['dir'])) {
    $dir = basename($_GET['dir']);
    $dirPath = '/tmp/' . $dir;

    if (file_exists($dirPath) && is_dir($dirPath) && strpos($dir, 'temp_transkrip_') === 0) {
        echo deleteDirectoryForce($dirPath);
    }
}
