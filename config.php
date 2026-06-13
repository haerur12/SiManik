<?php
session_start();

$host = "localhost";
$user = "root";
$pass = "123";
$db   = "db_monitoring_nilai";

$conn = mysqli_connect("localhost", "root", "", "db_monitoring_nilai");
    
if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

// Fungsi get predikat
function getPredikat($nilai) {
    if($nilai >= 90) return 'A';
    elseif($nilai >= 80) return 'B';
    elseif($nilai >= 70) return 'C';
    elseif($nilai >= 60) return 'D';
    else return 'E';
}

// Fungsi format tanggal
function formatTanggal($tanggal) {
    if(empty($tanggal)) return "-";
    $bulan = array(
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    );
    $pecahkan = explode('-', $tanggal);
    return $pecahkan[2] . ' ' . $bulan[(int)$pecahkan[1]] . ' ' . $pecahkan[0];
}
?>