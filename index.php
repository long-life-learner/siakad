<?php
require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

$totals = [
    'mahasiswa' => 0,
    'mahasiswa_aktif' => 0,
    'kurikulum' => 0,
    'after_graduate' => 0,
];

try {
    $totals['mahasiswa'] = (int) $db->query('SELECT COUNT(*) FROM mahasiswa')->fetchColumn();
    $totals['mahasiswa_aktif'] = (int) $db->query('SELECT COUNT(*) FROM mahasiswa WHERE status = "Aktif"')->fetchColumn();
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

// Hitung jumlah mahasiswa per angkatan -> jurusan -> jenis_kelamin
$counts = [];
$counts_by_angkatan = [];
try {
    $stmt = $db->query("SELECT tahunmasuk, programstudi, trim(jeniskelamin) as jeniskelamin, COUNT(*) as cnt FROM mahasiswa GROUP BY tahunmasuk, programstudi, jeniskelamin ORDER BY tahunmasuk DESC, programstudi");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $angk = $row['tahunmasuk'] ?? 'Unknown';
        $jur = $row['programstudi'] ?? 'Unknown';
        $jk  = trim($row['jeniskelamin']) ?? 'Unknown';

        if (!isset($counts[$angk])) {
            $counts[$angk] = [];
        }
        if (!isset($counts[$angk][$jur])) {
            $counts[$angk][$jur] = ['total' => 0, 'by_gender' => []];
        }

        $counts[$angk][$jur]['total'] += (int)$row['cnt'];
        if (!isset($counts[$angk][$jur]['by_gender'][$jk])) {
            $counts[$angk][$jur]['by_gender'][$jk] = 0;
        }
        $counts[$angk][$jur]['by_gender'][$jk] += (int)$row['cnt'];

        // Hitung juga per angkatan saja
        if (!isset($counts_by_angkatan[$angk])) {
            $counts_by_angkatan[$angk] = ['total' => 0, 'by_gender' => []];
        }
        $counts_by_angkatan[$angk]['total'] += (int)$row['cnt'];
        if (!isset($counts_by_angkatan[$angk]['by_gender'][$jk])) {
            $counts_by_angkatan[$angk]['by_gender'][$jk] = 0;
        }
        $counts_by_angkatan[$angk]['by_gender'][$jk] += (int)$row['cnt'];
    }
} catch (Exception $e) {
    // Jika query gagal, biarkan $counts tetap kosong
    die('Error: ' . $e->getMessage());
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
                        <h6 class="card-title">Total Mahasiswa (Mahasiswa Aktif)</h6>
                        <h2><?php echo htmlspecialchars($totals['mahasiswa']);  ?> (<?php echo htmlspecialchars($totals['mahasiswa_aktif']); ?>)</h2>
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

        <?php if (!empty($counts)): ?>
            <div class="card mt-4">
                <div class="card-header">Ringkasan Mahasiswa per Angkatan</div>
                <div class="card-body">
                    <div class="table-responsive mb-3">
                        <table class="table table-sm table-striped align-middle">
                            <thead>
                                <tr>
                                    <th>Angkatan</th>
                                    <th>Total</th>
                                    <th>Per Jenis Kelamin</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($counts_by_angkatan as $angk => $data): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($angk); ?></strong></td>
                                        <td><strong><?php echo htmlspecialchars($data['total']); ?></strong></td>
                                        <td>
                                            <?php foreach ($data['by_gender'] as $jk => $v): ?>
                                                <span class="badge bg-primary me-1"><?php echo htmlspecialchars($jk); ?>: <?php echo htmlspecialchars($v); ?></span>
                                            <?php endforeach; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header">Detail Mahasiswa per Angkatan &amp; Jurusan</div>
                <div class="card-body">
                    <?php foreach ($counts as $angk => $jurusans): ?>
                        <h6 class="mb-2">Angkatan <?php echo htmlspecialchars($angk); ?></h6>
                        <div class="table-responsive mb-3">
                            <table class="table table-sm table-striped align-middle">
                                <thead>
                                    <tr>
                                        <th>Jurusan</th>
                                        <th>Total</th>
                                        <th>Per Jenis Kelamin</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($jurusans as $jur => $data): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($jur); ?></td>
                                            <td><?php echo htmlspecialchars($data['total']); ?></td>
                                            <td>
                                                <?php foreach ($data['by_gender'] as $jk => $v): ?>
                                                    <span class="badge bg-secondary me-1"><?php echo htmlspecialchars($jk); ?>: <?php echo htmlspecialchars($v); ?></span>
                                                <?php endforeach; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- <div class="mt-4 text-muted small">Jika Anda ingin menambahkan fitur baru ke Dashboard, beri tahu saya fitur apa yang diinginkan.</div> -->
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>