<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();


// tahun masuk
$query = "SELECT DISTINCT tahunmasuk FROM mahasiswa ORDER BY tahunmasuk DESC LIMIT 4";
$stmt = $db->prepare($query);
$stmt->execute();
$tahun =  $stmt->fetchAll(PDO::FETCH_ASSOC);

// program studi
$query2 = "SELECT DISTINCT programstudi FROM mahasiswa ORDER BY programstudi";;
$stmt2 = $db->prepare($query2);
$stmt2->execute();
$prodi =  $stmt2->fetchAll(PDO::FETCH_ASSOC);

?>


<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Cetak Ijazah & Transkrip</title>

    <!-- Bootstrap -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">

    <!-- Select2 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <style>
        body {
            background: #f7f7f7;
            padding: 30px;
        }

        .card {
            border-radius: 12px;
        }
    </style>
</head>

<body>

    <div class="container">
        <div class="card p-4 shadow">
            <h3 class="mb-3">📄 Sistem Cetak Ijazah & Transkrip</h3>

            <!-- Tabs -->
            <ul class="nav nav-tabs mb-3" id="myTab" role="tablist">
                <li class="nav-item"><a class="nav-link" id="import-tab" data-bs-toggle="tab" href="#import">Langkah 1 : Import Data</a></li>
                <li class="nav-item"><a class="nav-link active" id="massal-tab" data-bs-toggle="tab" href="#massal">Cetak Massal Ijazah</a></li>
                <li class="nav-item"><a class="nav-link" id="individu-tab" data-bs-toggle="tab" href="#individu">Cetak Ijazah</a></li>
                <li class="nav-item"><a class="nav-link" id="transkrip-massal-tab" data-bs-toggle="tab" href="#transkrip-massal">Cetak Massal Transkrip</a></li>
                <li class="nav-item"><a class="nav-link" id="transkrip-tab" data-bs-toggle="tab" href="#transkrip">Cetak Transkrip</a></li>

            </ul>

            <div class="tab-content">

                <!-- =========================== -->
                <!-- TAB 1: CETAK MASSAL IJAZAH  -->
                <!-- =========================== -->
                <div class="tab-pane fade show active" id="massal">

                    <div class="row">
                        <div class="col-3">
                            <form action="generate_ijazah.php" method="GET" target="_blank">

                                <input type="hidden" name="mode" value="massal">

                                <!-- Tahun Masuk -->
                                <div class="mb-3">
                                    <label class="form-label">Tahun Masuk</label>
                                    <select name="tahun" class="form-select" required>
                                        <option value="">-- Pilih Tahun --</option>
                                        <?php
                                        $tahun = $db->prepare("SELECT DISTINCT tahunmasuk FROM mahasiswa ORDER BY tahunmasuk DESC LIMIT 4");
                                        $tahun->execute();
                                        $tahun = $tahun->fetchAll(PDO::FETCH_ASSOC);

                                        ?>

                                        <?php
                                        foreach ($tahun as $t): ?>
                                            <option value="<?= $t['tahunmasuk'] ?>"><?= $t['tahunmasuk'] ?></option>
                                        <?php endforeach; ?>

                                    </select>
                                </div>

                                <!-- Prodi -->
                                <div class="mb-3">
                                    <label class="form-label">Program Studi</label>
                                    <select name="prodi" class="form-select" required>
                                        <option value="">-- Pilih Prodi --</option>
                                        <?php
                                        // Reset hasil query untuk dipakai lagi
                                        $prodi = $db->prepare("SELECT DISTINCT
                                            CASE
                                                WHEN UPPER(SUBSTRING_INDEX(SUBSTRING_INDEX(programstudi, ' ', 3), ' ', -1)) = 'INDUSTRI'
                                                    THEN 'Teknologi Industri'
                                                ELSE programstudi
                                            END AS programstudi
                                        FROM mahasiswa
                                        ORDER BY programstudi;");
                                        $prodi->execute();
                                        $prodi = $prodi->fetchAll(PDO::FETCH_ASSOC);
                                        ?>

                                        <?php foreach ($prodi as $p): ?>
                                            <option value="<?= $p['programstudi'] ?>"><?= $p['programstudi'] ?></option>
                                        <?php endforeach; ?>

                                    </select>
                                </div>

                                <!-- Tanggal Lulus -->
                                <div class="mb-3">
                                    <label class="form-label">Tanggal Lulus</label>
                                    <input type="date" name="tgl_lulus" class="form-control" required>
                                </div>

                                <!-- Tanggal Cetak -->
                                <div class="mb-3">
                                    <label class="form-label">Tanggal Cetak</label>
                                    <input type="date" name="tgl_cetak" class="form-control" required>
                                </div>

                                <button class="btn btn-primary mt-3" type="submit">Cetak Massal Ijazah</button>
                            </form>
                        </div>
                    </div>
                </div>


                <!-- =========================== -->
                <!-- TAB 2: CETAK IJAZAH INDIVIDU -->
                <!-- =========================== -->
                <div class="tab-pane fade" id="individu">

                    <div class="row">
                        <div class="col-3">
                            <form action="generate_ijazah.php" method="GET" target="_blank">

                                <input type="hidden" name="mode" value="nim">

                                <!-- Select2 -->
                                <div class="mb-3">
                                    <label class="form-label">Cari Mahasiswa (Nama / NIM)</label>
                                    <select id="select-mhs" name="nim" class="form-select" required></select>
                                </div>

                                <!-- Tanggal Lulus -->
                                <div class="mb-3">
                                    <label class="form-label">Tanggal Lulus</label>
                                    <input type="date" name="tgl_lulus" class="form-control" required>
                                </div>

                                <!-- Tanggal Cetak -->
                                <div class="mb-3">
                                    <label class="form-label">Tanggal Cetak</label>
                                    <input type="date" name="tgl_cetak" class="form-control" required>
                                </div>

                                <button class="btn btn-success mt-3" type="submit">Cetak Ijazah</button>
                            </form>
                        </div>
                    </div>

                </div>

                <!-- =========================== -->
                <!-- TAB 3: CETAK MASSAL TRANSKRIP -->
                <!-- =========================== -->
                <div class="tab-pane fade" id="transkrip-massal">

                    <div class="row">
                        <div class="col-3">
                            <form action="generate_transkrip_massal.php" method="GET" target="_blank">

                                <!-- Tahun Masuk -->
                                <div class="mb-3">
                                    <label class="form-label">Tahun Masuk</label>
                                    <select name="tahun" class="form-select" required>
                                        <option value="">-- Pilih Tahun --</option>
                                        <?php
                                        $tahun = $db->prepare("SELECT DISTINCT tahunmasuk FROM mahasiswa ORDER BY tahunmasuk DESC LIMIT 4");
                                        $tahun->execute();
                                        $tahun = $tahun->fetchAll(PDO::FETCH_ASSOC);
                                        ?>
                                        <?php
                                        foreach ($tahun as $t): ?>
                                            <option value="<?= $t['tahunmasuk'] ?>"><?= $t['tahunmasuk'] ?></option>
                                        <?php endforeach; ?>

                                    </select>
                                </div>

                                <!-- Prodi -->
                                <div class="mb-3">
                                    <label class="form-label">Program Studi</label>
                                    <select name="prodi" class="form-select" required>
                                        <option value="">-- Pilih Prodi --</option>
                                        <?php
                                        $prodi = $db->prepare("SELECT DISTINCT
                                            CASE
                                                WHEN UPPER(SUBSTRING_INDEX(SUBSTRING_INDEX(programstudi, ' ', 3), ' ', -1)) = 'INDUSTRI'
                                                    THEN 'Teknologi Industri'
                                                ELSE programstudi
                                            END AS programstudi
                                        FROM mahasiswa
                                        ORDER BY programstudi;");
                                        $prodi->execute();
                                        ?>
                                        <?php foreach ($prodi as $p): ?>
                                            <option value="<?= $p['programstudi'] ?>"><?= $p['programstudi'] ?></option>
                                        <?php endforeach; ?>

                                    </select>
                                </div>

                                <!-- Tanggal Masuk -->
                                <div class="mb-3">
                                    <label class="form-label">Tanggal Masuk</label>
                                    <input type="date" name="masuk" class="form-control" required>
                                </div>

                                <!-- Tanggal Lulus -->
                                <div class="mb-3">
                                    <label class="form-label">Tanggal Lulus</label>
                                    <input type="date" name="lulus" class="form-control" required>
                                </div>

                                <!-- Tanggal Cetak -->
                                <div class="mb-3">
                                    <label class="form-label">Tanggal Cetak</label>
                                    <input type="date" name="cetak" class="form-control" required>
                                </div>

                                <button class="btn btn-warning mt-3" type="submit">Cetak Massal Transkrip</button>
                            </form>
                        </div>
                    </div>

                </div>

                <!-- =========================== -->
                <!-- TAB 4: CETAK TRANSKRIP INDIVIDU -->
                <!-- =========================== -->
                <div class="tab-pane fade" id="transkrip">

                    <div class="row">
                        <div class="col-3">
                            <form action="generate_transkrip.php" method="GET" target="_blank">

                                <!-- Select2 -->
                                <div class="mb-3">
                                    <label class="form-label">Cari Mahasiswa (Nama / NIM)</label>
                                    <select id="select-mhs-transkrip" name="nim" class="form-select" required></select>
                                </div>

                                <!-- Tanggal Masuk -->
                                <div class="mb-3">
                                    <label class="form-label">Tanggal Masuk</label>
                                    <input type="date" name="masuk" class="form-control" required>
                                </div>

                                <!-- Tanggal Lulus -->
                                <div class="mb-3">
                                    <label class="form-label">Tanggal Lulus</label>
                                    <input type="date" name="lulus" class="form-control" required>
                                </div>

                                <!-- Tanggal Cetak -->
                                <div class="mb-3">
                                    <label class="form-label">Tanggal Cetak</label>
                                    <input type="date" name="cetak" class="form-control" required>
                                </div>

                                <button class="btn btn-info mt-3" type="submit">Cetak Transkrip</button>
                            </form>
                        </div>
                    </div>

                </div>

                <!-- =========================== -->
                <!-- TAB 5: IMPORT DATA GRADUATE -->
                <!-- =========================== -->
                <div class="tab-pane fade" id="import">

                    <div class="row">
                        <div class="col-6">
                            <h5>📥 Import Data After Graduate</h5>
                            <p class="text-muted">Import data NIM, Nomor Ijazah, dan NIK dari file Excel</p>

                            <div class="alert alert-info" role="alert">
                                <strong>Petunjuk:</strong>
                                <ul class="mb-0 mt-2">
                                    <li>Unduh template Excel terlebih dahulu</li>
                                    <li>Isi data sesuai dengan format yang disediakan</li>
                                    <li>Pastikan NIM ada di database mahasiswa</li>
                                    <li>Upload file Excel untuk import</li>
                                </ul>
                            </div>

                            <!-- Download Template -->
                            <div class="mb-3">
                                <a href="download_template.php" class="btn btn-outline-primary">
                                    📥 Download Template Excel
                                </a>
                            </div>

                            <!-- Upload Form -->
                            <form id="importForm" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label class="form-label">Pilih File Excel (.xlsx)</label>
                                    <input type="file" name="file" class="form-control" accept=".xlsx" required>
                                    <small class="text-muted">Format: .xlsx (Excel 2007 keatas)</small>
                                </div>

                                <button type="submit" class="btn btn-success">Upload & Import</button>
                                <button type="reset" class="btn btn-secondary">Reset</button>
                            </form>

                            <!-- Progress Bar -->
                            <div id="progressContainer" class="mt-3" style="display: none;">
                                <div class="progress">
                                    <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                                </div>
                                <p id="progressText" class="text-muted mt-2"></p>
                            </div>

                            <!-- Result Alert -->
                            <div id="resultAlert" class="mt-3" style="display: none;"></div>

                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- JS -->
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

        <script>
            // Select2 untuk Ijazah
            $("#select-mhs").select2({
                placeholder: "Ketik nama atau NIM...",
                minimumInputLength: 2,
                ajax: {
                    url: "search_mahasiswa.php",
                    dataType: "json",
                    delay: 250,
                    method: "GET",
                    data: function(params) {
                        return {
                            q: params.term
                        };
                    },
                    processResults: function(data) {

                        return {
                            results: data
                        };
                    }
                }
            });

            // Select2 untuk Transkrip
            $("#select-mhs-transkrip").select2({
                placeholder: "Ketik nama atau NIM...",
                minimumInputLength: 2,
                ajax: {
                    url: "search_mahasiswa_transkrip.php",
                    dataType: "json",
                    delay: 250,
                    method: "GET",
                    data: function(params) {
                        return {
                            q: params.term
                        };
                    },
                    processResults: function(data) {

                        return {
                            results: data
                        };
                    }
                }
            });
            // Import Excel Handler
            $("#importForm").on("submit", function(e) {
                e.preventDefault();

                var fileInput = $("input[name='file']")[0];
                if (!fileInput.files.length) {
                    alert("Pilih file terlebih dahulu");
                    return;
                }

                var formData = new FormData();
                formData.append("file", fileInput.files[0]);

                // Tampilkan progress bar
                $("#progressContainer").show();
                $("#progressBar").css("width", "0%");
                $("#progressText").text("Mengunggah file...");
                $("#resultAlert").hide();

                $.ajax({
                    url: "import_after_graduate.php",
                    type: "POST",
                    data: formData,
                    contentType: false,
                    processData: false,
                    xhr: function() {
                        var xhr = new window.XMLHttpRequest();
                        xhr.upload.addEventListener("progress", function(evt) {
                            if (evt.lengthComputable) {
                                var percentComplete = (evt.loaded / evt.total) * 100;
                                $("#progressBar").css("width", percentComplete + "%");
                                $("#progressText").text("Upload: " + Math.round(percentComplete) + "%");
                            }
                        }, false);
                        return xhr;
                    },
                    success: function(response) {
                        var result = JSON.parse(response);
                        $("#progressBar").css("width", "100%");
                        $("#progressText").text("Selesai!");

                        if (result.success) {
                            $("#resultAlert").html(
                                '<div class="alert alert-success alert-dismissible fade show" role="alert">' +
                                '<strong>✓ Berhasil!</strong><br>' +
                                'Data berhasil diimport: <strong>' + result.imported + '</strong> baris<br>' +
                                (result.skipped > 0 ? 'Dilewati (NIM tidak ditemukan): <strong>' + result.skipped + '</strong>' : '') +
                                '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' +
                                '</div>'
                            );
                        } else {
                            $("#resultAlert").html(
                                '<div class="alert alert-danger alert-dismissible fade show" role="alert">' +
                                '<strong>✗ Error!</strong><br>' +
                                result.message +
                                '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' +
                                '</div>'
                            );
                        }
                        $("#resultAlert").show();

                        // Reset form setelah 2 detik
                        setTimeout(function() {
                            $("#importForm")[0].reset();
                            $("#progressContainer").hide();
                        }, 2000);
                    },
                    error: function() {
                        $("#resultAlert").html(
                            '<div class="alert alert-danger alert-dismissible fade show" role="alert">' +
                            '<strong>✗ Error!</strong> Gagal mengupload file' +
                            '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' +
                            '</div>'
                        );
                        $("#resultAlert").show();
                        $("#progressContainer").hide();
                    }
                });
            });
        </script>

</body>

</html>