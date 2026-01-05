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


$stmt = $db->prepare("
    SELECT nim, nama 
    FROM mahasiswa 
    WHERE nama LIKE :nama OR nim LIKE :nim
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
