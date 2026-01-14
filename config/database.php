<?php
class Database
{
    private $host = "192.168.83.245";
    private $db_name = "sisfo";
    private $username = "ridwanarif";
    private $password = "ridwanarif";
    public $conn;

    public function getConnection()
    {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("set names utf8");
        } catch (PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        return $this->conn;
    }

    // Fungsi untuk mendapatkan tahun akademik unik
    public function getTahunAkademik()
    {
        $query = "SELECT DISTINCT tahun FROM kurikulum ORDER BY tahun DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Fungsi untuk mendapatkan program studi unik
    public function getKelas()
    {
        $query = "SELECT DISTINCT kelas FROM mahasiswa where kelas LIKE '%TEKNIK%' OR kelas LIKE '%TEKNOLOGI%' ORDER BY kelas";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getProgramStudi()
    {
        $query = "SELECT DISTINCT programstudi as prodi FROM mahasiswa ORDER BY programstudi";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    // Fungsi untuk menghitung total SKS
    public function getTotalSKS($tahun = null, $prodi = null)
    {
        $query = "SELECT SUM(sks) as total_sks FROM kurikulum WHERE 1=1";
        $params = [];

        if ($tahun) {
            $query .= " AND tahun = :tahun";
            $params[':tahun'] = $tahun;
        } else {
            // Jika tahun tidak diberikan, ambil tahun akademik terbaru
            $subQuery = "SELECT MAX(tahun) FROM kurikulum";
            $stmt = $this->conn->prepare($subQuery);
            $stmt->execute();
            $latestTahun = $stmt->fetchColumn();
            $query .= " AND tahun = :tahun";
            $params[':tahun'] = $latestTahun;
        }

        if ($prodi) {
            $query .= " AND prodi = :prodi";
            $params[':prodi'] = $prodi;
        }

        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total_sks'] ?: 0;
    }
}
