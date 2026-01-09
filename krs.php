<?php
require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

// Ambil filter dari URL
$filter_tahun = isset($_GET['tahun']) ? $_GET['tahun'] : '';
$filter_prodi = isset($_GET['prodi']) ? $_GET['prodi'] : '';
$filter_kodemk = isset($_GET['kodemk']) ? $_GET['kodemk'] : '';
$filter_namamk = isset($_GET['namamk']) ? $_GET['namamk'] : '';

// Query dengan filter
$query = "SELECT * FROM kurikulum WHERE 1=1";
$params = [];

if ($filter_tahun) {
    $query .= " AND tahun = :tahun";
    $params[':tahun'] = $filter_tahun;
} else {
    $subQuery = "SELECT MAX(tahun) FROM kurikulum";
    $stmt = $db->prepare($subQuery);
    $stmt->execute();
    $latestTahun = $stmt->fetchColumn();
    $query .= " AND tahun = :tahun";
    $params[':tahun'] = $latestTahun;
}

if ($filter_prodi) {
    $query .= " AND prodi LIKE :prodi";
    $params[':prodi'] = "%$filter_prodi%";
}

if ($filter_kodemk) {
    $query .= " AND kodemk LIKE :kodemk";
    $params[':kodemk'] = "%$filter_kodemk%";
}

if ($filter_namamk) {
    $query .= " AND namamk LIKE :namamk";
    $params[':namamk'] = "%$filter_namamk%";
}

$query .= " ORDER BY tahun DESC, sem, kodemk ";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$kurikulum = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get filter options
$tahun_options = $database->getTahunAkademik();
$prodi_options = $database->getProgramStudi();

// Hitung total SKS berdasarkan filter
$total_sks = $database->getTotalSKS($filter_tahun, $filter_prodi);
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Kurikulum - Update Aplikasi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <style>
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .filter-card {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .sks-counter {
            font-size: 1.5rem;
            font-weight: bold;
            color: #28a745;
        }

        .btn-group-sm>.btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
    </style>
</head>

<body>
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">Sistem Update Kurikulum dan KRS</h4>
                        <div>
                            <a href="template.php" class="btn btn-light btn-sm me-2">
                                <i class="fas fa-download"></i> Import Kurikulum
                            </a>
                            <!-- <a href="import.php" class="btn btn-warning btn-sm me-2">
                                <i class="fas fa-upload"></i> Import
                            </a> -->
                            <a href="process_krs.php" class="btn btn-success btn-sm">
                                <i class="fas fa-cogs"></i> Generate KRS
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Filter Section -->
                        <div class="filter-card">
                            <h5><i class="fas fa-filter"></i> Filter Data Kurikulum</h5>
                            <form method="GET" action="" class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Tahun Akademik</label>
                                    <select name="tahun" class="form-select select2">
                                        <option value="">Semua Tahun</option>
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
                                    <select name="prodi" class="form-select select2">
                                        <option value="">Semua Prodi</option>
                                        <?php foreach ($prodi_options as $prodi): ?>
                                            <option value="<?php echo $prodi['prodi']; ?>"
                                                <?php echo ($filter_prodi == $prodi['prodi']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($prodi['prodi']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Kode MK</label>
                                    <input type="text" name="kodemk" class="form-control"
                                        value="<?php echo htmlspecialchars($filter_kodemk); ?>"
                                        placeholder="Cari kode...">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Nama MK</label>
                                    <input type="text" name="namamk" class="form-control"
                                        value="<?php echo htmlspecialchars($filter_namamk); ?>"
                                        placeholder="Cari nama...">
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <div class="btn-group w-100">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-search"></i> Filter
                                        </button>
                                        <a href="index.php" class="btn btn-secondary">
                                            <i class="fas fa-redo"></i> Reset
                                        </a>
                                    </div>
                                </div>
                            </form>

                            <!-- SKS Counter -->
                            <div class="row mt-3">
                                <div class="col-md-12">
                                    <div class="alert alert-info d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="fas fa-calculator"></i>
                                            <strong>Total SKS:</strong>
                                            <span class="sks-counter"><?php echo $total_sks; ?></span> SKS
                                            <?php if ($filter_tahun): ?>
                                                <span class="badge bg-primary ms-2">Tahun: <?php echo $filter_tahun; ?></span>
                                            <?php endif; ?>
                                            <?php if ($filter_prodi): ?>
                                                <span class="badge bg-success ms-2">Prodi: <?php echo htmlspecialchars($filter_prodi); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <span class="badge bg-dark">Total Data: <?php echo count($kurikulum); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Statistics Cards -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card bg-primary text-white">
                                    <div class="card-body">
                                        <h5 class="card-title">Total Mata Kuliah</h5>
                                        <h2><?php echo count($kurikulum); ?></h2>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card bg-success text-white">
                                    <div class="card-body">
                                        <h5 class="card-title">Total SKS</h5>
                                        <h2><?php echo $total_sks; ?></h2>
                                    </div>
                                </div>
                            </div>
                            <!-- <div class="col-md-3">
                                <div class="card bg-info text-white">
                                    <div class="card-body">
                                        <h5 class="card-title">Tahun Akademik</h5>
                                        <h2><?php echo count($tahun_options); ?></h2>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-warning text-white">
                                    <div class="card-body">
                                        <h5 class="card-title">Program Studi</h5>
                                        <h2><?php echo count($prodi_options); ?></h2>
                                    </div>
                                </div>
                            </div> -->
                        </div>

                        <!-- Data Table -->
                        <div class="card">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Data Kurikulum</h5>
                                <div>
                                    <button class="btn btn-sm btn-outline-primary" onclick="exportToExcel()">
                                        <i class="fas fa-file-excel"></i> Export
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="kurikulumTable" class="table table-striped table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Kode MK</th>
                                                <th>Nama MK</th>
                                                <th>Prodi</th>
                                                <th>Semester</th>
                                                <th>SKS</th>
                                                <th>Tahun</th>
                                                <th>Dosen</th>
                                                <th>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($kurikulum as $row): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($row['kodemk']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['namamk']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['prodi']); ?></td>
                                                    <td><?php echo $row['sem']; ?></td>
                                                    <td><?php echo $row['sks']; ?></td>
                                                    <td><?php echo $row['tahun']; ?></td>
                                                    <td><?php echo htmlspecialchars($row['dosen']); ?></td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <button class="btn btn-info"
                                                                onclick="viewDetail(<?php echo $row['id']; ?>)"
                                                                title="Detail">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                            <button class="btn btn-warning"
                                                                onclick="editKurikulum(<?php echo $row['id']; ?>)"
                                                                title="Edit">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <button class="btn btn-danger"
                                                                onclick="deleteKurikulum(<?php echo $row['id']; ?>)"
                                                                title="Hapus">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal for Detail -->
    <div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detail Kurikulum</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detailContent">
                    Loading...
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(document).ready(function() {
            // Initialize DataTable
            $('#kurikulumTable').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.11.5/i18n/id.json"
                },
                "pageLength": 25,
                "order": [
                    [0, 'asc']
                ]
            });

            // Initialize Select2
            $('.select2').select2({
                width: '100%'
            });
        });

        function viewDetail(id) {
            $.ajax({
                url: 'ajax_get_kurikulum.php',
                method: 'POST',
                data: {
                    id: id,
                    action: 'detail'
                },
                success: function(response) {
                    $('#detailContent').html(response);
                    $('#detailModal').modal('show');
                }
            });
        }

        function editKurikulum(id) {
            // Implement edit function
            Swal.fire({
                title: 'Edit Kurikulum',
                text: 'Fitur edit akan segera tersedia',
                icon: 'info'
            });
        }

        function deleteKurikulum(id) {
            Swal.fire({
                title: 'Hapus Data?',
                text: "Data yang dihapus tidak dapat dikembalikan!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'ajax_delete_kurikulum.php',
                        method: 'POST',
                        data: {
                            id: id
                        },
                        success: function(response) {
                            location.reload();
                        }
                    });
                }
            });
        }

        function exportToExcel() {
            // Get current filter values
            let params = new URLSearchParams(window.location.search);

            // Redirect to export page
            window.location.href = 'export_kurikulum.php?' + params.toString();
        }
    </script>
</body>

</html>