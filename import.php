<?php
require_once 'config/database.php';
require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

$database = new Database();
$db = $database->getConnection();

$message = '';
$error = '';
$success_count = 0;
$failed_count = 0;
$log_details = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['excel_file'])) {
    $file = $_FILES['excel_file'];

    // Validasi file
    $allowed_extensions = ['xlsx', 'xls', 'csv'];
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($file_extension, $allowed_extensions)) {
        $error = "Format file tidak didukung. Gunakan file Excel (.xlsx, .xls) atau CSV.";
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $error = "Terjadi kesalahan saat upload file.";
    } else {
        try {
            // Upload file
            $upload_dir = 'uploads/temp/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $file_name = time() . '_' . basename($file['name']);
            $file_path = $upload_dir . $file_name;

            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                // Load Excel file
                $spreadsheet = IOFactory::load($file_path);
                $worksheet = $spreadsheet->getActiveSheet();
                $highestRow = $worksheet->getHighestRow();
                $highestColumn = $worksheet->getHighestColumn();
                $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

                // Start transaction
                $db->beginTransaction();

                // Process each row starting from row 2 (assuming row 1 is header)
                for ($row = 2; $row <= $highestRow; $row++) {
                    try {
                        $kodemk = $worksheet->getCell('B' . $row)->getValue();
                        $namamk = $worksheet->getCell('C' . $row)->getValue();
                        $sem = $worksheet->getCell('D' . $row)->getValue();
                        $sks = $worksheet->getCell('E' . $row)->getValue();
                        $jenis_mk = $worksheet->getCell('F' . $row)->getValue();
                        $prodi = $worksheet->getCell('G' . $row)->getValue();
                        $dosen = $worksheet->getCell('H' . $row)->getValue();
                        $tahun = $worksheet->getCell('I' . $row)->getValue();
                        // Skip empty rows
                        if (empty($kodemk) || empty($namamk)) {
                            continue;
                        }

                        // Check if record exists
                        $check_query = "SELECT id FROM kurikulum WHERE kodemk = :kodemk AND tahun = :tahun AND prodi= :prodi";
                        $check_stmt = $db->prepare($check_query);
                        $check_stmt->bindParam(':kodemk', $kodemk);
                        $check_stmt->bindParam(':tahun', $tahun);
                        $check_stmt->bindParam(':prodi', $prodi);
                        $check_stmt->execute();

                        if ($check_stmt->rowCount() > 0) {
                            // Update existing record
                            $query = "UPDATE kurikulum SET 
                                      namamk = :namamk,
                                      prodi = :prodi,
                                      dosen = :dosen,
                                      sem = :sem,
                                      jenismk = :jenismk,
                                      sks = :sks
                                      WHERE kodemk = :kodemk AND tahun = :tahun AND prodi = :prodi AND id=" . $check_stmt->fetchColumn();
                            $operation = 'UPDATE';
                        } else {
                            // Insert new record
                            $query = "INSERT INTO kurikulum 
                                     (kodemk, namamk, prodi, dosen, sem, jenismk, sks, tahun, surattugas) 
                                     VALUES 
                                     (:kodemk, :namamk, :prodi, :dosen, :sem, :jenismk, :sks, :tahun, '')";
                            $operation = 'INSERT';
                        }

                        $stmt = $db->prepare($query);
                        $stmt->bindParam(':kodemk', $kodemk);
                        $stmt->bindParam(':namamk', $namamk);
                        $stmt->bindParam(':prodi', $prodi);
                        $stmt->bindParam(':dosen', $dosen);
                        $stmt->bindParam(':sem', $sem);
                        $stmt->bindParam(':jenismk', $jenis_mk);
                        $stmt->bindParam(':sks', $sks);
                        $stmt->bindParam(':tahun', $tahun);

                        if ($stmt->execute()) {
                            $success_count++;
                            $log_details[] = [
                                'status' => 'success',
                                'kodemk' => $kodemk,
                                'operation' => $operation,
                                'message' => 'Berhasil di' . ($operation == 'INSERT' ? 'tambahkan' : 'perbarui')
                            ];
                        } else {
                            $failed_count++;
                            $log_details[] = [
                                'status' => 'error',
                                'kodemk' => $kodemk,
                                'operation' => $operation,
                                'message' => 'Gagal diproses'
                            ];
                        }
                    } catch (Exception $e) {
                        $failed_count++;
                        $log_details[] = [
                            'status' => 'error',
                            'kodemk' => $kodemk ?? 'Unknown',
                            'operation' => 'ERROR',
                            'message' => 'Error: ' . $e->getMessage()
                        ];
                    }
                }

                // Commit transaction
                $db->commit();

                $message = "Import selesai. Berhasil: $success_count, Gagal: $failed_count";

                // Clean up uploaded file
                unlink($file_path);
            } else {
                $error = "Gagal mengupload file.";
            }
        } catch (Exception $e) {
            // Rollback transaction on error
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error = "Terjadi kesalahan: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Kurikulum</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .upload-area {
            border: 2px dashed #007bff;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            background-color: #f8f9fa;
            cursor: pointer;
            transition: all 0.3s;
        }

        .upload-area:hover {
            background-color: #e9ecef;
            border-color: #0056b3;
        }

        .upload-area.dragover {
            background-color: #d4edda;
            border-color: #28a745;
        }

        .log-success {
            color: #28a745;
            background-color: #d4edda;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.9em;
        }

        .log-error {
            color: #dc3545;
            background-color: #f8d7da;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.9em;
        }
    </style>
</head>

<body>
    <div class="container mt-5">
        <div class="card shadow">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Import Kurikulum dari Excel</h4>
                <a href="index.php" class="btn btn-light btn-sm">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <?php if ($message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0">Upload File Excel</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" enctype="multipart/form-data" id="uploadForm">
                                    <div class="upload-area mb-3" id="dropArea">
                                        <i class="fas fa-cloud-upload-alt fa-3x text-primary mb-3"></i>
                                        <h5>Drag & Drop file Excel di sini</h5>
                                        <p class="text-muted">atau</p>
                                        <input type="file" name="excel_file" id="fileInput" class="d-none" accept=".xlsx,.xls,.csv">
                                        <label for="fileInput" class="btn btn-primary">
                                            <i class="fas fa-folder-open"></i> Pilih File
                                        </label>
                                        <p class="mt-3 mb-0 text-muted">
                                            Format: .xlsx, .xls, atau .csv<br>
                                            Maksimal: 10MB
                                        </p>
                                    </div>
                                    <div id="fileInfo" class="d-none mb-3">
                                        <div class="alert alert-info">
                                            <i class="fas fa-file-excel"></i>
                                            <span id="fileName"></span>
                                            <button type="button" class="btn-close float-end" onclick="clearFile()"></button>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-success w-100" id="submitBtn" disabled>
                                        <i class="fas fa-upload"></i> Proses Import
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header bg-warning">
                                <h5 class="mb-0">Petunjuk Import</h5>
                            </div>
                            <div class="card-body">
                                <ol>
                                    <li>Pastikan file Excel mengikuti template yang disediakan</li>
                                    <li>Kolom harus sesuai urutan: No, Kode MK, Nama MK, Semester, SKS, Jenis MK, Prodi, Dosen, Tahun Akademik</li>
                                    <li>Data yang sudah ada akan diupdate berdasarkan Kode MK dan Tahun</li>
                                    <li>Data baru akan ditambahkan ke database</li>
                                    <li>Pastikan koneksi internet stabil selama proses import</li>
                                </ol>
                                <a href="template.php" class="btn btn-outline-primary">
                                    <i class="fas fa-download"></i> Download Template
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-secondary text-white">
                                <h5 class="mb-0">Log Import</h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($log_details)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Status</th>
                                                    <th>Kode MK</th>
                                                    <th>Operasi</th>
                                                    <th>Keterangan</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($log_details as $log): ?>
                                                    <tr>
                                                        <td>
                                                            <?php if ($log['status'] == 'success'): ?>
                                                                <span class="badge bg-success">Success</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-danger">Error</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($log['kodemk']); ?></td>
                                                        <td><?php echo $log['operation']; ?></td>
                                                        <td><?php echo $log['message']; ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center text-muted py-5">
                                        <i class="fas fa-history fa-3x mb-3"></i>
                                        <p>Belum ada log import. Upload file untuk memulai.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer">
                                <div class="row">
                                    <div class="col-6">
                                        <div class="text-center p-2 bg-success text-white rounded">
                                            <h6 class="mb-0">Berhasil</h6>
                                            <h4 class="mb-0"><?php echo $success_count; ?></h4>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="text-center p-2 bg-danger text-white rounded">
                                            <h6 class="mb-0">Gagal</h6>
                                            <h4 class="mb-0"><?php echo $failed_count; ?></h4>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const dropArea = document.getElementById('dropArea');
        const fileInput = document.getElementById('fileInput');
        const fileInfo = document.getElementById('fileInfo');
        const fileName = document.getElementById('fileName');
        const submitBtn = document.getElementById('submitBtn');

        // Drag and drop functionality
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            dropArea.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, unhighlight, false);
        });

        function highlight() {
            dropArea.classList.add('dragover');
        }

        function unhighlight() {
            dropArea.classList.remove('dragover');
        }

        dropArea.addEventListener('drop', handleDrop, false);

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            handleFiles(files);
        }

        fileInput.addEventListener('change', function() {
            handleFiles(this.files);
        });

        function handleFiles(files) {
            if (files.length > 0) {
                const file = files[0];
                const validExtensions = ['.xlsx', '.xls', '.csv'];
                const fileExt = '.' + file.name.split('.').pop().toLowerCase();

                if (validExtensions.includes(fileExt)) {
                    fileName.textContent = file.name + ' (' + formatFileSize(file.size) + ')';
                    fileInfo.classList.remove('d-none');
                    submitBtn.disabled = false;
                    fileInput.files = files;
                } else {
                    alert('Format file tidak didukung. Gunakan file Excel (.xlsx, .xls) atau CSV.');
                }
            }
        }

        function clearFile() {
            fileInput.value = '';
            fileInfo.classList.add('d-none');
            submitBtn.disabled = true;
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
    </script>
</body>

</html>