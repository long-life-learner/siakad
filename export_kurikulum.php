<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Get filter from URL
$filter_tahun = isset($_GET['tahun']) ? $_GET['tahun'] : '';
$filter_prodi = isset($_GET['prodi']) ? $_GET['prodi'] : '';
$filter_kodemk = isset($_GET['kodemk']) ? $_GET['kodemk'] : '';
$filter_namamk = isset($_GET['namamk']) ? $_GET['namamk'] : '';

// Query with filter
$query = "SELECT * FROM kurikulum WHERE 1=1";
$params = [];

if ($filter_tahun) {
    $query .= " AND tahun = :tahun";
    $params[':tahun'] = $filter_tahun;
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

$query .= " ORDER BY tahun DESC, sem, kodemk";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment;filename="kurikulum_' . date('YmdHis') . '.xlsx"');
header('Cache-Control: max-age=0');

// Create Excel content
$output = "No\tKode MK\tNama MK\tProgram Studi\tDosen\tSemester\tSKS\tTahun Akademik\tSurat Tugas\n";

$no = 1;
foreach ($data as $row) {
    $output .= $no . "\t";
    $output .= $row['kodemk'] . "\t";
    $output .= $row['namamk'] . "\t";
    $output .= $row['prodi'] . "\t";
    $output .= $row['dosen'] . "\t";
    $output .= $row['sem'] . "\t";
    $output .= $row['sks'] . "\t";
    $output .= $row['tahun'] . "\t";
    $output .= $row['surattugas'] . "\n";
    $no++;
}

echo $output;
exit;
