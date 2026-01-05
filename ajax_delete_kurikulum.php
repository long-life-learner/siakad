<?php
require_once 'config/database.php';

if (isset($_POST['id'])) {
    $database = new Database();
    $db = $database->getConnection();

    $id = $_POST['id'];

    try {
        $query = "DELETE FROM kurikulum WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $id);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Data berhasil dihapus']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal menghapus data']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}
