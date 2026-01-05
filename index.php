<?php
require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

$totals = [
    'mahasiswa' => 0,
    'kurikulum' => 0,
    'after_graduate' => 0,
];

try {
    $totals['mahasiswa'] = (int) $db->query('SELECT COUNT(*) FROM mahasiswa')->fetchColumn();
} catch (Exception $e) {
}
try {
    $totals['kurikulum'] = (int) $db->query('SELECT COUNT(*) FROM kurikulum')->fetchColumn();
} catch (Exception $e) {
}
try {
    $totals['after_graduate'] = (int) $db->query('SELECT COUNT(*) FROM after_graduate')->fetchColumn();
} catch (Exception $e) {
}
?>
<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard SIAKAD - Politeknik Gajah Tunggal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff
        }

        .feature-btn {
            min-width: 160px
        }
    </style>
</head>

<body>
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="m-0">Dashboard SIAKAD - Politeknik Gajah Tunggal</h3>
            <!-- <small class="text-muted">Fitur terkait SIAKAD berkumpul di sini</small> -->
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-title">Total Mahasiswa</h6>
                        <h2><?php echo htmlspecialchars($totals['mahasiswa']); ?></h2>
                        <!-- <a href="index.php" class="btn btn-sm btn-outline-primary mt-2">Kelola Mahasiswa</a> -->
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-title">Total Mata Kuliah (Kurikulum)</h6>
                        <h2><?php echo htmlspecialchars($totals['kurikulum']); ?></h2>
                        <!-- <a href="index.php" class="btn btn-sm btn-outline-primary mt-2">Update Kurikulum & KRS</a> -->
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-title">After Graduate Records</h6>
                        <h2><?php echo htmlspecialchars($totals['after_graduate']); ?></h2>
                        <!-- <a href="import_after_graduate.php" class="btn btn-sm btn-outline-primary mt-2">Import Graduate Data</a> -->
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>Quick Actions</div>
                <div class="text-end"><small class="text-muted">Akses cepat ke fitur penting</small></div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <!-- <div class="col-sm-6 col-md-3 d-flex">
                        <a href="index_ijazah.php" class="btn btn-outline-success feature-btn w-100">Generate Ijazah</a>
                    </div>
                    <div class="col-sm-6 col-md-3 d-flex">
                        <a href="generate_transkrip.php" class="btn btn-outline-info feature-btn w-100">Generate Transkrip</a>
                    </div> -->
                    <div class="col-sm-6 col-md-6 d-flex">
                        <a href="./krs.php" class="btn btn-outline-primary feature-btn w-100">Update Kurikulum & KRS</a>
                    </div>
                    <div class="col-sm-6 col-md-6 d-flex">
                        <a href="./ijazah_dan_transkrip.php" class="btn btn-outline-warning feature-btn w-100">Generate Ijazah dan Transkrip</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- <div class="mt-4 text-muted small">Jika Anda ingin menambahkan fitur baru ke Dashboard, beri tahu saya fitur apa yang diinginkan.</div> -->
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>