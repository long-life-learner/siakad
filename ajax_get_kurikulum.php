<?php
require_once 'config/database.php';

if (isset($_POST['id']) && isset($_POST['action'])) {
    $database = new Database();
    $db = $database->getConnection();

    $id = $_POST['id'];
    $action = $_POST['action'];

    if ($action == 'detail') {
        $query = "SELECT * FROM kurikulum WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data) {
            echo '
            <table class="table table-bordered">
                <tr>
                    <th width="30%">Kode MK</th>
                    <td>' . htmlspecialchars($data['kodemk']) . '</td>
                </tr>
                <tr>
                    <th>Nama MK</th>
                    <td>' . htmlspecialchars($data['namamk']) . '</td>
                </tr>
                <tr>
                    <th>Program Studi</th>
                    <td>' . htmlspecialchars($data['prodi']) . '</td>
                </tr>
                <tr>
                    <th>Dosen</th>
                    <td>' . htmlspecialchars($data['dosen']) . '</td>
                </tr>
                <tr>
                    <th>Semester</th>
                    <td>' . $data['sem'] . '</td>
                </tr>
                <tr>
                    <th>SKS</th>
                    <td>' . $data['sks'] . '</td>
                </tr>
                <tr>
                    <th>Tahun Akademik</th>
                    <td>' . $data['tahun'] . '</td>
                </tr>
                <tr>
                    <th>Surat Tugas</th>
                    <td>' . ($data['surattugas'] ? htmlspecialchars($data['surattugas']) : '-') . '</td>
                </tr>
                <tr>
                    <th>ID</th>
                    <td>' . $data['id'] . '</td>
                </tr>
            </table>';
        } else {
            echo '<div class="alert alert-danger">Data tidak ditemukan</div>';
        }
    }
}
