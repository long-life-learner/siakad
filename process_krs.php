<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

$message = '';
$error = '';
$stats = [
    'total_mahasiswa' => 0,
    'total_matakuliah' => 0,
    'krs_created' => 0,
    'krs_updated' => 0,
    'failed' => 0,
    'details' => []
];

// Get options for filters
$tahun_options = $database->getTahunAkademik();
$prodi_options = $database->getProgramStudi();
$kelas_options = $database->getKelas();

// Get filter values from POST or set defaults
$filter_tahun = isset($_POST['filter_tahun']) ? $_POST['filter_tahun'] : '';
$filter_prodi = isset($_POST['filter_prodi']) ? $_POST['filter_prodi'] : '';
$filter_kelas = isset($_POST['filter_kelas']) ? $_POST['filter_kelas'] : '';
$filter_nim = isset($_POST['filter_nim']) ? $_POST['filter_nim'] : '';
$process_type = isset($_POST['process_type']) ? $_POST['process_type'] : 'all';

// Fungsi untuk generate KRS dengan berbagai filter
function generateKRS($db, $filter_tahun, $filter_prodi, $filter_kelas, $filter_nim, $process_type, &$stats)
{
    // Jika tidak ada tahun, ambil yang terbaru
    if (empty($filter_tahun)) {
        $tahun_query = "SELECT DISTINCT tahun FROM kurikulum ORDER BY tahun DESC LIMIT 1";
        $tahun_stmt = $db->prepare($tahun_query);
        $tahun_stmt->execute();
        $filter_tahun = $tahun_stmt->fetchColumn();
    }

    if (!$filter_tahun) {
        return "Tidak ada tahun akademik ditemukan!";
    }

    // Mulai transaction
    $db->beginTransaction();

    try {
        // Query mahasiswa berdasarkan filter
        $mahasiswa_query = "SELECT nim, nama, kelas, programstudi, tahunmasuk FROM mahasiswa WHERE status = 'Aktif'";
        $params = [];

        if (!empty($filter_prodi) && $process_type != 'single') {
            $mahasiswa_query .= " AND programstudi = :prodi";
            $params[':prodi'] = $filter_prodi;
        }

        if (!empty($filter_kelas) && $process_type != 'single') {
            $mahasiswa_query .= " AND kelas = :kelas";
            $params[':kelas'] = $filter_kelas;
        }

        if (!empty($filter_nim)) {
            $mahasiswa_query .= " AND nim = :nim";
            $params[':nim'] = $filter_nim;
        }

        $mahasiswa_stmt = $db->prepare($mahasiswa_query);
        foreach ($params as $key => $value) {
            $mahasiswa_stmt->bindValue($key, $value);
        }
        $mahasiswa_stmt->execute();
        $mahasiswa = $mahasiswa_stmt->fetchAll(PDO::FETCH_ASSOC);

        $stats['total_mahasiswa'] = count($mahasiswa);

        if ($stats['total_mahasiswa'] == 0) {
            return "Tidak ada mahasiswa ditemukan dengan filter yang diberikan!";
        }

        foreach ($mahasiswa as $mhs) {
            // Tentukan semester mahasiswa (logika sederhana)
            // Bisa disesuaikan dengan kebutuhan
            $semester_mhs = calculateSemester($mhs['tahunmasuk'], $filter_tahun);

            // Query matakuliah berdasarkan filter
            $mk_query = "SELECT * FROM kurikulum 
                        WHERE tahun = :tahun 
                        AND sem = :semester
                        AND TRIM(UPPER(prodi)) = :prodi";
            $mk_params = [
                ':tahun' => $filter_tahun,
                ':semester' => $semester_mhs,
                ':prodi' => trim(strtoupper($mhs['kelas']))
            ];

            // Tambah filter prodi jika dipilih
            // if (!empty($filter_prodi) && $process_type != 'single') {
            //     $mk_query .= " AND TRIM(UPPER(prodi)) =  :prodi";
            //     $mk_params[':prodi'] =  trim(strtoupper($filter_prodi));
            // } 

            // elseif ($process_type == 'single') {
            // Jika single student, gunakan prodi dari mahasiswa
            // $mk_query .= " AND TRIM(UPPER(prodi)) =  :prodi";
            // $mk_params[':prodi'] =  trim(strtoupper($mhs['kelas']));
            // }

            // $mk_query .= " AND (namamk NOT LIKE '%Praktik%' OR namamk LIKE '%Praktik Kerja Lapangan%')";

            $mk_stmt = $db->prepare($mk_query);
            foreach ($mk_params as $key => $value) {
                $mk_stmt->bindValue($key, $value);
            }
            $mk_stmt->execute();
            $matakuliah = $mk_stmt->fetchAll(PDO::FETCH_ASSOC);
            // var_dump($matakuliah);
            // die();
            $stats['total_matakuliah'] += count($matakuliah);

            foreach ($matakuliah as $mk) {
                // Check if KRS already exists
                $check_krs = "SELECT id FROM nilaiakademik 
                            WHERE nim = :nim 
                            AND kodemk = :kodemk 
                            AND tahunakademik = :tahunakademik";

                $check_stmt = $db->prepare($check_krs);
                $check_stmt->bindParam(':nim', $mhs['nim']);
                $check_stmt->bindParam(':kodemk', $mk['kodemk']);
                $check_stmt->bindParam(':tahunakademik', $filter_tahun);
                $check_stmt->execute();

                if ($check_stmt->rowCount() > 0) {
                    // Update existing KRS
                    $query = "UPDATE nilaiakademik SET
                             nama = :nama,
                             namamk = :namamk,
                             sks = :sks,
                             prodi = :prodi,
                             dosen = :dosen,
                             kelas = :kelas,
                             status_konfirmasi = 'Menunggu Dikirim'
                             WHERE nim = :nim 
                             AND kodemk = :kodemk 
                             AND tahunakademik = :tahunakademik";
                    $operation = 'UPDATE';
                } else {
                    // Insert new KRS
                    $query = "INSERT INTO nilaiakademik 
                             (nim, nama, kodemk, namamk, sks, tahunakademik, prodi, dosen, kelas, uts, uas, tugas, kuis, sikap, akhir, huruf, angka, status, statusmk, status_konfirmasi) 
                             VALUES 
                             (:nim, :nama, :kodemk, :namamk, :sks, :tahunakademik, :prodi, :dosen, :kelas, '', '', '', '', '0', '0', '', '0', '', '', 'Menunggu Dikirim')";
                    $operation = 'INSERT';
                }

                $stmt = $db->prepare($query);
                $stmt->bindParam(':nim', $mhs['nim']);
                $stmt->bindParam(':nama', $mhs['nama']);
                $stmt->bindParam(':kodemk', $mk['kodemk']);
                $stmt->bindParam(':namamk', $mk['namamk']);
                $stmt->bindParam(':sks', $mk['sks']);
                $stmt->bindParam(':tahunakademik', $filter_tahun);
                $stmt->bindParam(':prodi', $mhs['programstudi']);
                $stmt->bindParam(':dosen', $mk['dosen']);
                $stmt->bindParam(':kelas', $mhs['kelas']);

                if ($stmt->execute()) {
                    if ($operation == 'INSERT') {
                        $stats['krs_created']++;
                    } else {
                        $stats['krs_updated']++;
                    }

                    $stats['details'][] = [
                        'nim' => $mhs['nim'],
                        'nama' => $mhs['nama'],
                        'kodemk' => $mk['kodemk'],
                        'namamk' => $mk['namamk'],
                        'operation' => $operation,
                        'status' => 'success'
                    ];
                } else {
                    $stats['failed']++;
                    $stats['details'][] = [
                        'nim' => $mhs['nim'],
                        'nama' => $mhs['nama'],
                        'kodemk' => $mk['kodemk'],
                        'namamk' => $mk['namamk'],
                        'operation' => 'ERROR',
                        'status' => 'failed'
                    ];
                }
            }
        }

        // Commit transaction
        $db->commit();

        return "success";
    } catch (Exception $e) {
        $db->rollBack();
        return "Error: " . $e->getMessage();
    }
}

// Fungsi untuk menghitung semester
function calculateSemester($tahun_masuk, $tahun_akademik)
{
    // Logika sederhana: asumsi 2 semester per tahun
    $tahun_masuk_int = intval(substr($tahun_masuk, 0, 4));
    $tahun_akademik_int = intval(substr($tahun_akademik, 0, 4));
    $semester_akademik = substr($tahun_akademik, -1);

    $selisih_tahun = $tahun_akademik_int - $tahun_masuk_int;
    $semester = ($selisih_tahun * 2) + ($semester_akademik == '2' ? 0 : -1);

    return max(1, $semester); // Minimal semester 1
}

// Handle form submission for batch processing
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['process_krs'])) {
        $result = generateKRS($db, $filter_tahun, $filter_prodi, $filter_kelas, $filter_nim, $process_type, $stats);

        if ($result == "success") {
            $message = "Batch processing KRS selesai!";
        } else {
            $error = $result;
        }
    }
}

// Get summary data
$summary_query = "SELECT 
    (SELECT COUNT(*) FROM mahasiswa WHERE status = 'Aktif') as total_mahasiswa,
    (SELECT COUNT(*) FROM kurikulum) as total_kurikulum,
    (SELECT COUNT(*) FROM nilaiakademik WHERE status_konfirmasi = 'Menunggu Dikirim') as total_krs_pending";

$summary_stmt = $db->prepare($summary_query);
$summary_stmt->execute();
$summary = $summary_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Batch Process KRS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <style>
        .filter-section {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #dee2e6;
        }

        .process-card {
            border-left: 5px solid #dc3545;
        }

        .tab-content {
            padding: 20px;
            border: 1px solid #dee2e6;
            border-top: none;
            border-radius: 0 0 10px 10px;
        }

        .nav-tabs .nav-link.active {
            background-color: #fff;
            border-bottom-color: #fff;
            font-weight: bold;
        }

        .preview-box {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 10px;
        }
    </style>
</head>

<body>
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">Batch Processing - Generate KRS</h4>
                        <a href="index.php" class="btn btn-light btn-sm">
                            <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
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
                                <p class="mb-0 mt-2">
                                    <strong>Detail Proses:</strong><br>
                                    • Total Mahasiswa: <?php echo $stats['total_mahasiswa']; ?><br>
                                    • KRS Baru: <?php echo $stats['krs_created']; ?><br>
                                    • KRS Diperbarui: <?php echo $stats['krs_updated']; ?><br>
                                    • Gagal: <?php echo $stats['failed']; ?>
                                </p>
                            </div>
                        <?php endif; ?>

                        <!-- Filter Section -->
                        <div class="filter-section">
                            <h5><i class="fas fa-filter"></i> Filter Generate KRS</h5>
                            <form method="POST" id="filterForm">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label">Tahun Akademik *</label>
                                        <select name="filter_tahun" class="form-select select2" required>
                                            <option value="">Pilih Tahun</option>
                                            <?php foreach ($tahun_options as $tahun): ?>
                                                <option value="<?php echo $tahun['tahun']; ?>"
                                                    <?php echo ($filter_tahun == $tahun['tahun']) ? 'selected' : ''; ?>>
                                                    <?php echo $tahun['tahun']; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Program Studi</label>
                                        <select name="filter_prodi" class="form-select select2">
                                            <option value="">Semua Prodi</option>
                                            <?php foreach ($prodi_options as $prodi): ?>
                                                <option value="<?php echo $prodi['prodi']; ?>"
                                                    <?php echo ($filter_prodi == $prodi['prodi']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($prodi['prodi']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Kelas</label>
                                        <select name="filter_kelas" class="form-select select2">
                                            <option value="">Semua Kelas</option>
                                            <?php foreach ($kelas_options as $kelas): ?>
                                                <option value="<?php echo $kelas['kelas']; ?>"
                                                    <?php echo ($filter_kelas == $kelas['kelas']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($kelas['kelas']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">NIM (Opsional)</label>
                                        <input type="text" name="filter_nim" class="form-control"
                                            value="<?php echo htmlspecialchars($filter_nim); ?>"
                                            placeholder="Masukkan NIM">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Tipe Proses</label>
                                        <select name="process_type" class="form-select">
                                            <option value="all" <?php echo ($process_type == 'all') ? 'selected' : ''; ?>>Semua Mahasiswa</option>
                                            <option value="single" <?php echo ($process_type == 'single') ? 'selected' : ''; ?>>Per NIM</option>
                                            <option value="prodi" <?php echo ($process_type == 'prodi') ? 'selected' : ''; ?>>Per Prodi</option>
                                            <option value="kelas" <?php echo ($process_type == 'kelas') ? 'selected' : ''; ?>>Per Kelas</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="row mt-3">
                                    <div class="col-md-12">
                                        <div class="alert alert-info">
                                            <strong><i class="fas fa-info-circle"></i> Informasi:</strong>
                                            <ul class="mb-0">
                                                <li><strong>Tahun + Prodi:</strong> Generate KRS untuk semua mahasiswa di prodi tertentu pada tahun akademik tertentu</li>
                                                <li><strong>Tahun saja:</strong> Generate KRS untuk semua mahasiswa aktif pada tahun akademik tertentu</li>
                                                <li><strong>Prodi saja:</strong> Generate KRS untuk semua mahasiswa di prodi tertentu (tahun terbaru)</li>
                                                <li><strong>NIM:</strong> Generate KRS untuk mahasiswa tertentu saja</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- Tabs for different process types -->
                        <ul class="nav nav-tabs" id="processTab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="batch-tab" data-bs-toggle="tab"
                                    data-bs-target="#batch" type="button" role="tab">
                                    <i class="fas fa-cogs"></i> Batch Process
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="preview-tab" data-bs-toggle="tab"
                                    data-bs-target="#preview" type="button" role="tab">
                                    <i class="fas fa-eye"></i> Preview Data
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="stats-tab" data-bs-toggle="tab"
                                    data-bs-target="#stats" type="button" role="tab">
                                    <i class="fas fa-chart-bar"></i> Statistik
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content" id="processTabContent">
                            <!-- Batch Process Tab -->
                            <div class="tab-pane fade show active" id="batch" role="tabpanel">
                                <div class="card process-card">
                                    <div class="card-header bg-danger text-white">
                                        <h5 class="mb-0">Batch Process KRS</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="alert alert-warning">
                                            <h5><i class="fas fa-exclamation-triangle"></i> PERHATIAN</h5>
                                            <p>Proses ini akan:</p>
                                            <ul>
                                                <li>Generate KRS berdasarkan filter yang dipilih</li>
                                                <li>Mengambil data kurikulum sesuai tahun dan prodi</li>
                                                <li>Membuat atau memperbarui record di tabel nilaiakademik</li>
                                                <li>Data yang sudah ada akan diupdate</li>
                                                <li>Pastikan backup database sudah dilakukan</li>
                                            </ul>
                                        </div>

                                        <div class="text-center">
                                            <button type="button" class="btn btn-danger btn-lg"
                                                onclick="confirmProcess()"
                                                style="padding: 15px 40px; font-size: 1.2rem;">
                                                <i class="fas fa-cogs"></i> JALANKAN BATCH PROCESS KRS
                                            </button>
                                            <p class="text-muted mt-2">
                                                Proses akan dijalankan berdasarkan filter yang dipilih di atas
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Preview Tab -->
                            <div class="tab-pane fade" id="preview" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h5>Preview Mahasiswa</h5>
                                        <div class="preview-box">
                                            <?php
                                            // Preview mahasiswa berdasarkan filter
                                            $preview_mhs_query = "SELECT nim, nama, programstudi, kelas 
                                                                FROM mahasiswa 
                                                                WHERE status = 'Aktif' 
                                                                LIMIT 10";
                                            $preview_mhs_stmt = $db->prepare($preview_mhs_query);
                                            $preview_mhs_stmt->execute();
                                            $preview_mhs = $preview_mhs_stmt->fetchAll(PDO::FETCH_ASSOC);
                                            ?>
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>NIM</th>
                                                        <th>Nama</th>
                                                        <th>Prodi</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($preview_mhs as $mhs): ?>
                                                        <tr>
                                                            <td><?php echo $mhs['nim']; ?></td>
                                                            <td><?php echo htmlspecialchars($mhs['nama']); ?></td>
                                                            <td><?php echo htmlspecialchars($mhs['programstudi']); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <h5>Preview Kurikulum</h5>
                                        <div class="preview-box">
                                            <?php
                                            // Preview kurikulum berdasarkan filter
                                            $preview_kur_query = "SELECT kodemk, namamk, sem, sks, prodi 
                                                                FROM kurikulum 
                                                                WHERE tahun = :tahun 
                                                                LIMIT 10";
                                            $preview_kur_stmt = $db->prepare($preview_kur_query);
                                            $preview_kur_stmt->bindValue(':tahun', $filter_tahun ?: $tahun_options[0]['tahun']);
                                            $preview_kur_stmt->execute();
                                            $preview_kur = $preview_kur_stmt->fetchAll(PDO::FETCH_ASSOC);
                                            ?>
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Kode</th>
                                                        <th>Mata Kuliah</th>
                                                        <th>Sem</th>
                                                        <th>SKS</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($preview_kur as $kur): ?>
                                                        <tr>
                                                            <td><?php echo $kur['kodemk']; ?></td>
                                                            <td><?php echo htmlspecialchars($kur['namamk']); ?></td>
                                                            <td><?php echo $kur['sem']; ?></td>
                                                            <td><?php echo $kur['sks']; ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Stats Tab -->
                            <div class="tab-pane fade" id="stats" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="card bg-primary text-white text-center">
                                            <div class="card-body">
                                                <h5>Total Mahasiswa</h5>
                                                <h2><?php echo $summary['total_mahasiswa']; ?></h2>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card bg-info text-white text-center">
                                            <div class="card-body">
                                                <h5>Total Kurikulum</h5>
                                                <h2><?php echo $summary['total_kurikulum']; ?></h2>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card bg-warning text-white text-center">
                                            <div class="card-body">
                                                <h5>KRS Pending</h5>
                                                <h2><?php echo $summary['total_krs_pending']; ?></h2>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card bg-success text-white text-center">
                                            <div class="card-body">
                                                <h5>Tahun Aktif</h5>
                                                <h2>
                                                    <?php
                                                    echo $filter_tahun ?: (isset($tahun_options[0]['tahun']) ? $tahun_options[0]['tahun'] : '-');
                                                    ?>
                                                </h2>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($stats['details'])): ?>
                            <div class="card mt-4">
                                <div class="card-header bg-secondary text-white">
                                    <h5 class="mb-0">Detail Proses</h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>NIM</th>
                                                    <th>Nama</th>
                                                    <th>Kode MK</th>
                                                    <th>Operasi</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($stats['details'] as $detail): ?>
                                                    <tr>
                                                        <td><?php echo $detail['nim']; ?></td>
                                                        <td><?php echo $detail['nama']; ?></td>
                                                        <td><?php echo $detail['kodemk']; ?></td>
                                                        <td>
                                                            <?php if ($detail['operation'] == 'INSERT'): ?>
                                                                <span class="badge bg-success">BARU</span>
                                                            <?php elseif ($detail['operation'] == 'UPDATE'): ?>
                                                                <span class="badge bg-warning">UPDATE</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-danger">ERROR</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($detail['status'] == 'success'): ?>
                                                                <span class="badge bg-success">Success</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-danger">Failed</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title">Konfirmasi Batch Process</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <h6><i class="fas fa-exclamation-triangle"></i> PERINGATAN</h6>
                        <p>Anda akan menjalankan batch process dengan parameter berikut:</p>
                        <ul>
                            <li><strong>Tahun Akademik:</strong> <span id="confirmTahun"></span></li>
                            <li><strong>Program Studi:</strong> <span id="confirmProdi"></span></li>
                            <li><strong>NIM:</strong> <span id="confirmNIM"></span></li>
                            <li><strong>Tipe Proses:</strong> <span id="confirmType"></span></li>
                        </ul>
                        <p class="mb-0">Proses ini mungkin memakan waktu beberapa menit. Pastikan backup database sudah dilakukan.</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-danger" onclick="submitProcess()">Ya, Jalankan Process</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(document).ready(function() {
            $('.select2').select2({
                width: '100%'
            });
        });

        function confirmProcess() {
            // Get form values
            const tahun = $('select[name="filter_tahun"]').val();
            const prodi = $('select[name="filter_prodi"]').val();
            const nim = $('input[name="filter_nim"]').val();
            const type = $('select[name="process_type"]').val();

            if (!tahun) {
                Swal.fire({
                    title: 'Peringatan',
                    text: 'Tahun Akademik harus dipilih!',
                    icon: 'warning'
                });
                return;
            }

            // Set confirmation values
            $('#confirmTahun').text(tahun || 'Semua');
            $('#confirmProdi').text(prodi || 'Semua');
            $('#confirmNIM').text(nim || 'Semua');
            $('#confirmType').text(type === 'all' ? 'Semua Mahasiswa' :
                type === 'single' ? 'Per NIM' : 'Per Prodi');

            // Show modal
            $('#confirmModal').modal('show');
        }

        function submitProcess() {
            // Create hidden form and submit
            const form = document.getElementById('filterForm');
            const submitBtn = document.createElement('input');
            submitBtn.type = 'hidden';
            submitBtn.name = 'process_krs';
            submitBtn.value = '1';
            form.appendChild(submitBtn);

            // Show loading
            Swal.fire({
                title: 'Memproses...',
                text: 'Sedang menjalankan batch process. Harap tunggu...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Submit form
            form.submit();
        }
    </script>
</body>

</html>