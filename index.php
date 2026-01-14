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
    $stmt = $db->query("SELECT tahunmasuk, programstudi, jeniskelamin, COUNT(*) as cnt FROM mahasiswa GROUP BY tahunmasuk, programstudi, jeniskelamin ORDER BY tahunmasuk DESC, programstudi");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $angk = $row['tahunmasuk'] ?? 'Unknown';
        $jur = $row['programstudi'] ?? 'Unknown';

        // Normalisasi jeniskelamin: trim + samakan variasi ke dua label standar
        $raw_jk = isset($row['jeniskelamin']) ? trim($row['jeniskelamin']) : '';
        $jk_lower = strtolower($raw_jk);
        if ($jk_lower === '' || $jk_lower === null) {
            $jk = 'Unknown';
        } elseif (preg_match('/\b(laki|lk|pria|laki2|laki-?laki|male)\b/', $jk_lower)) {
            $jk = 'Laki-laki';
        } elseif (preg_match('/\b(wanita|perempuan|perempuann|female|cewek)\b/', $jk_lower)) {
            $jk = 'Perempuan';
        } else {
            $jk = ucwords($jk_lower);
        }

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
                <div>Menu</div>
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

            <?php
            // Persiapkan data untuk Chart.js
            $angkatan_labels = array_values(array_keys($counts_by_angkatan));
            $angkatan_totals = [];
            foreach ($angkatan_labels as $angk) {
                $angkatan_totals[] = (int)$counts_by_angkatan[$angk]['total'];
            }

            // Kumpulkan semua gender yang ada
            $gender_set = [];
            foreach ($counts_by_angkatan as $angk => $data) {
                foreach ($data['by_gender'] as $g => $v) {
                    $gender_set[trim($g)] = true;
                }
            }
            $genders = array_values(array_keys($gender_set));

            // Siapkan data per gender per angkatan
            $gender_data = [];
            foreach ($genders as $g) {
                $row = [];
                foreach ($angkatan_labels as $angk) {
                    $row[] = isset($counts_by_angkatan[$angk]['by_gender'][$g]) ? (int)$counts_by_angkatan[$angk]['by_gender'][$g] : 0;
                }
                $gender_data[$g] = $row;
            }

            // Siapkan data jurusan per angkatan dan juga breakdown gender per jurusan per angkatan
            $jurusan_by_angkatan = [];
            $jurusan_gender_by_angkatan = [];

            foreach ($counts as $angk => $jurusans) {
                $labels = [];
                $data = [];
                foreach ($jurusans as $jur => $d) {
                    $labels[] = $jur;
                    $data[] = (int)$d['total'];
                }
                $jurusan_by_angkatan[$angk] = ['labels' => $labels, 'data' => $data];

                // gender counts aligned with $labels
                $jurusan_gender_by_angkatan[$angk] = ['labels' => $labels, 'genders' => []];
                foreach ($genders as $g) {
                    $arr = [];
                    foreach ($jurusans as $jur => $d) {
                        $arr[] = isset($d['by_gender'][$g]) ? (int)$d['by_gender'][$g] : 0;
                    }
                    $jurusan_gender_by_angkatan[$angk]['genders'][$g] = $arr;
                }
            }
            ?>

            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">Total Mahasiswa per Angkatan</div>
                        <div class="card-body">
                            <canvas id="chartAngkatan" height="220"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">Distribusi Jenis Kelamin per Angkatan (Stacked)</div>
                        <div class="card-body">
                            <canvas id="chartGenderStacked" height="220"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>Detail Per Jurusan (Pilih Angkatan)</div>
                    <div class="d-flex align-items-center gap-2">
                        <select id="selectAngkatan" class="form-select form-select-sm" style="width:220px;">
                            <?php foreach ($angkatan_labels as $a): ?>
                                <option value="<?php echo htmlspecialchars($a); ?>"><?php echo htmlspecialchars($a); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-check form-switch ms-2">
                            <input class="form-check-input" type="checkbox" id="toggleGenderView">
                            <label class="form-check-label" for="toggleGenderView" style="font-size:0.9rem;">Tampilkan per Gender</label>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <canvas id="chartJurusan" height="160"></canvas>
                </div>
            </div>

            <!-- Data untuk JS -->
            <script>
                const angkatanLabels = <?php echo json_encode($angkatan_labels, JSON_UNESCAPED_UNICODE); ?>;
                const angkatanTotals = <?php echo json_encode($angkatan_totals); ?>;
                const genders = <?php echo json_encode($genders, JSON_UNESCAPED_UNICODE); ?>;
                const genderData = <?php echo json_encode($gender_data, JSON_UNESCAPED_UNICODE); ?>;
                const jurusanByAngkatan = <?php echo json_encode($jurusan_by_angkatan, JSON_UNESCAPED_UNICODE); ?>;
                const jurusanGenderByAngkatan = <?php echo json_encode($jurusan_gender_by_angkatan, JSON_UNESCAPED_UNICODE); ?>;
            </script>

            <!-- Chart.js CDN -->
            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

            <script>
                // const palette = ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#6f42c1', '#fd7e14', '#20c997'];
                const palette = ['#e74a3b', '#6f42c1', '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#fd7e14', '#20c997'];

                // Chart Total per Angkatan
                const ctxA = document.getElementById('chartAngkatan').getContext('2d');
                new Chart(ctxA, {
                    type: 'bar',
                    data: {
                        labels: angkatanLabels,
                        datasets: [{
                            label: 'Total Mahasiswa',
                            data: angkatanTotals,
                            backgroundColor: palette[0],
                            borderColor: palette[0],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Jumlah Mahasiswa'
                                }
                            },
                            x: {
                                title: {
                                    display: true,
                                    text: 'Angkatan'
                                }
                            }
                        }
                    }
                });

                // Stacked Chart per Gender per Angkatan
                const ctxG = document.getElementById('chartGenderStacked').getContext('2d');
                const genderDatasets = genders.map((g, idx) => ({
                    label: g || 'Unknown',
                    data: genderData[g] || [],
                    backgroundColor: palette[(idx + 1) % palette.length],
                    stack: 'Stack 0'
                }));
                new Chart(ctxG, {
                    type: 'bar',
                    data: {
                        labels: angkatanLabels,
                        datasets: genderDatasets
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'top'
                            }
                        },
                        scales: {
                            x: {
                                stacked: true,
                                title: {
                                    display: true,
                                    text: 'Angkatan'
                                }
                            },
                            y: {
                                stacked: true,
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Jumlah Mahasiswa'
                                }
                            }
                        }
                    }
                });

                // Jurusan per Angkatan (dinamis) — dapat tampil total atau per gender (stacked)
                const ctxJ = document.getElementById('chartJurusan').getContext('2d');
                let jurusanChart = null;

                function renderJurusanChart(angk, byGender = false) {
                    const infoTotal = jurusanByAngkatan[angk] || {
                        labels: [],
                        data: []
                    };
                    const infoGender = jurusanGenderByAngkatan[angk] || {
                        labels: [],
                        genders: {}
                    };

                    if (!byGender) {
                        const data = {
                            labels: infoTotal.labels,
                            datasets: [{
                                label: 'Total Mahasiswa',
                                data: infoTotal.data,
                                backgroundColor: infoTotal.labels.map((_, i) => palette[i % palette.length])
                            }]
                        };
                        const opts = {
                            responsive: true,
                            plugins: {
                                legend: {
                                    display: false
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    title: {
                                        display: true,
                                        text: 'Total'
                                    }
                                }
                            }
                        };

                        if (jurusanChart) jurusanChart.destroy();
                        jurusanChart = new Chart(ctxJ, {
                            type: 'bar',
                            data: data,
                            options: opts
                        });
                    } else {
                        // build datasets per gender
                        const datasets = [];
                        let idx = 0;
                        for (const g of genders) {
                            const d = infoGender.genders[g] || [];
                            datasets.push({
                                label: g || 'Unknown',
                                data: d,
                                backgroundColor: palette[idx % palette.length],
                                stack: 'Stack 0'
                            });
                            idx++;
                        }
                        const data = {
                            labels: infoGender.labels,
                            datasets
                        };
                        const opts = {
                            responsive: true,
                            plugins: {
                                legend: {
                                    position: 'top'
                                }
                            },
                            scales: {
                                x: {
                                    stacked: true
                                },
                                y: {
                                    stacked: true,
                                    beginAtZero: true,
                                    title: {
                                        display: true,
                                        text: 'Jumlah'
                                    }
                                }
                            }
                        };
                        if (jurusanChart) jurusanChart.destroy();
                        jurusanChart = new Chart(ctxJ, {
                            type: 'bar',
                            data: data,
                            options: opts
                        });
                    }
                }

                // Init jurusan chart dengan angkatan pertama
                if (angkatanLabels.length) {
                    const first = angkatanLabels[0];
                    renderJurusanChart(first, false);
                    document.getElementById('selectAngkatan').value = first;
                }

                // Interaksi dropdown & toggle
                const selectAng = document.getElementById('selectAngkatan');
                const toggleGender = document.getElementById('toggleGenderView');

                selectAng.addEventListener('change', function() {
                    renderJurusanChart(this.value, toggleGender.checked);
                });
                toggleGender.addEventListener('change', function() {
                    renderJurusanChart(selectAng.value, this.checked);
                });
            </script>

        <?php endif; ?>

        <!-- <div class="mt-4 text-muted small">Jika Anda ingin menambahkan fitur baru ke Dashboard, beri tahu saya fitur apa yang diinginkan.</div> -->
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>