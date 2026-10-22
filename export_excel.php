<?php
require_once 'config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') exit;

$type = $_GET['type'] ?? 'nilai';
if ($type === 'nilai') {
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=Rekap_Nilai_LMS.xls");
    
    $stmt = $pdo->query("SELECT p.*, u.nama_lengkap, u.kelas FROM pengumpulan p JOIN users u ON p.id_siswa = u.id ORDER BY p.mapel, u.kelas");
    $data = $stmt->fetchAll();
    
    echo '<?xml version="1.0"?>';
    echo '<?mso-application progid="Excel.Sheet"?>';
    echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
    echo '<Worksheet ss:Name="Rekap Nilai"><Table>';
    echo '<Row><Cell><Data ss:Type="String">Nama Siswa</Data></Cell><Cell><Data ss:Type="String">Kelas</Data></Cell><Cell><Data ss:Type="String">Mata Pelajaran</Data></Cell><Cell><Data ss:Type="String">Judul Tugas</Data></Cell><Cell><Data ss:Type="String">Nilai</Data></Cell><Cell><Data ss:Type="String">Catatan Guru</Data></Cell></Row>';
    foreach($data as $row) {
        echo '<Row>';
        echo '<Cell><Data ss:Type="String">'.e($row['nama_lengkap']).'</Data></Cell>';
        echo '<Cell><Data ss:Type="String">'.e($row['kelas']).'</Data></Cell>';
        echo '<Cell><Data ss:Type="String">'.e($row['mapel']).'</Data></Cell>';
        echo '<Cell><Data ss:Type="String">'.e($row['judul_tugas']).'</Data></Cell>';
        echo '<Cell><Data ss:Type="Number">'.(int)$row['nilai'].'</Data></Cell>';
        echo '<Cell><Data ss:Type="String">'.e($row['catatan_guru']).'</Data></Cell>';
        echo '</Row>';
    }
    echo '</Table></Worksheet></Workbook>';
} elseif ($type === 'absensi') {
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=Rekap_Absensi_LMS.xls");
    
    $stmt = $pdo->query("SELECT a.*, u.nama_lengkap, u.kelas FROM absensi a JOIN users u ON a.id_siswa = u.id ORDER BY a.tanggal DESC");
    $data = $stmt->fetchAll();
    
    echo '<?xml version="1.0"?>';
    echo '<?mso-application progid="Excel.Sheet"?>';
    echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
    echo '<Worksheet ss:Name="Rekap Absensi"><Table>';
    echo '<Row><Cell><Data ss:Type="String">Tanggal</Data></Cell><Cell><Data ss:Type="String">Nama Siswa</Data></Cell><Cell><Data ss:Type="String">Kelas</Data></Cell><Cell><Data ss:Type="String">Mapel</Data></Cell><Cell><Data ss:Type="String">Keterangan</Data></Cell><Cell><Data ss:Type="String">Catatan</Data></Cell></Row>';
    foreach($data as $row) {
        echo '<Row>';
        echo '<Cell><Data ss:Type="String">'.e($row['tanggal']).'</Data></Cell>';
        echo '<Cell><Data ss:Type="String">'.e($row['nama_lengkap']).'</Data></Cell>';
        echo '<Cell><Data ss:Type="String">'.e($row['kelas']).'</Data></Cell>';
        echo '<Cell><Data ss:Type="String">'.e($row['mapel']).'</Data></Cell>';
        echo '<Cell><Data ss:Type="String">'.e($row['keterangan']).'</Data></Cell>';
        echo '<Cell><Data ss:Type="String">'.e($row['catatan']).'</Data></Cell>';
        echo '</Row>';
    }
    echo '</Table></Worksheet></Workbook>';
}
?>