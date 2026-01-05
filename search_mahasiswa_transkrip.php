<?php
header("Content-Type: application/json");

require_once "config/database.php";
$database = new Database();
$db = $database->getConnection();


$q = $_GET['q'] ?? '';

if ($q === '') {
    echo json_encode([]);
    exit;
}

// Hanya tampilkan mahasiswa yang memiliki nilai akademik (sudah ada di tabel nilaiakademik)
$stmt = $db->prepare("
    SELECT DISTINCT m.nim, m.nama 
    FROM mahasiswa m
    INNER JOIN nilaiakademik n ON m.nim = n.nim
    WHERE (m.nama LIKE :nama OR m.nim LIKE :nim)
    AND m.status = 'Lulus'
    LIMIT 20
");

$like = "%$q%";
$stmt->bindValue(":nama", $like);
$stmt->bindValue(":nim", $like);
$stmt->execute();
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);

$data = [];
foreach ($result as $row) {
    $data[] = [
        "id" => $row['nim'],
        "text" => $row['nim'] . ' - ' . $row['nama']
    ];
}

echo json_encode($data);
