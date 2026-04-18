<?php
require 'config.php';

if(!isset($_SESSION['login'])) {
    header("Location: index.php");
    exit;
}

$kelas_id = isset($_GET['kelas']) ? $_GET['kelas'] : 1;
$kelas_info = mysqli_query($conn, "SELECT * FROM kelas WHERE id = $kelas_id")->fetch_assoc();

$siswa_list = mysqli_query($conn, "SELECT * FROM siswa WHERE kelas_id = $kelas_id ORDER BY nama_siswa ASC");

// Fungsi untuk mendapatkan badge warna
function getBadgeClass($nilai) {
    if(empty($nilai)) return '';
    if($nilai >= 90) return 'badge-90';
    if($nilai >= 80) return 'badge-80';
    if($nilai >= 70) return 'badge-70';
    if($nilai >= 60) return 'badge-60';
    return 'badge-below';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leger Nilai - Kelas <?= $kelas_info['nama_kelas'] ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --dark-navy: #0f172a;
            --royal-blue: #1e40af;
            --bright-blue: #3b82f6;
            --gold: #d97706;
            --amber: #f59e0b;
            --slate: #64748b;
            --light-slate: #e2e8f0;
            --white: #ffffff;
            --light-bg: #f1f5f9;
        }
        body { 
            background-color: var(--light-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .sidebar {
            background: linear-gradient(180deg, var(--dark-navy) 0%, #1e293b 100%);
            min-height: 100vh;
            color: var(--white);
            box-shadow: 4px 0 10px rgba(0,0,0,0.1);
        }
        .sidebar-brand {
            padding: 35px 25px;
            text-align: center;
            border-bottom: 2px solid var(--gold);
            background: rgba(217, 119, 6, 0.1);
        }
        .sidebar-brand i { font-size: 45px; color: var(--gold); margin-bottom: 12px; }
        .sidebar-brand h5 { font-weight: 700; font-size: 17px; margin-bottom: 5px; }
        .sidebar-brand small { font-size: 12px; color: var(--slate); }
        .sidebar-menu { padding: 25px 15px; }
        .sidebar-menu a {
            color: rgba(255,255,255,0.85);
            text-decoration: none;
            padding: 15px 20px;
            display: block;
            margin-bottom: 8px;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-size: 14px;
            font-weight: 500;
            border-left: 3px solid transparent;
        }
        .sidebar-menu a:hover {
            background: rgba(255,255,255,0.1);
            color: var(--white);
            border-left-color: var(--gold);
            padding-left: 25px;
        }
        .sidebar-menu a.active {
            background: linear-gradient(90deg, var(--gold) 0%, var(--amber) 100%);
            color: var(--dark-navy);
            font-weight: 700;
        }
        .sidebar-menu a i { margin-right: 12px; width: 22px; text-align: center; }
        .main-content { background-color: var(--light-bg); padding: 30px; }
        .page-header {
            background: linear-gradient(135deg, var(--dark-navy) 0%, var(--royal-blue) 100%);
            color: var(--white);
            padding: 25px 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(30, 64, 175, 0.2);
        }
        .page-header h2 { font-weight: 700; font-size: 26px; margin: 0; }
        .page-header h2 i { margin-right: 12px; color: var(--gold); }
        .card {
            background: var(--white);
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }
        .card-body { padding: 25px; }
        .table-leger {
            font-size: 11px;
            margin-bottom: 0;
        }
        .table-leger thead th {
            background: var(--dark-navy);
            color: var(--white);
            border: 1px solid var(--dark-navy);
            padding: 10px 5px;
            font-weight: 700;
            text-align: center;
            vertical-align: middle;
            white-space: nowrap;
        }
        .table-leger tbody td {
            padding: 8px 5px;
            vertical-align: middle;
            border: 1px solid var(--light-slate);
            text-align: center;
        }
        .table-leger tbody td.text-left {
            text-align: left;
        }
        .table-leger tbody tr:hover {
            background: var(--light-bg);
        }
        .badge {
            padding: 4px 8px;
            font-weight: 600;
            font-size: 10px;
            border-radius: 4px;
        }
        .badge-90 { background: #10b981; color: white; }
        .badge-80 { background: var(--royal-blue); color: white; }
        .badge-70 { background: var(--gold); color: white; }
        .badge-60 { background: #f97316; color: white; }
        .badge-below { background: #ef4444; color: white; }
        .btn { border-radius: 8px; padding: 10px 20px; font-weight: 600; }
        .btn-secondary { background: var(--slate); border: none; color: white; }
        .btn-success { background: #10b981; border: none; }
        .header-info {
            background: var(--white);
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .header-info p { margin: 0; font-size: 14px; }
        .header-info strong { color: var(--dark-navy); }
        @media print {
            .sidebar, .page-header, .no-print { display: none !important; }
            .main-content { padding: 0; }
            .table-leger { font-size: 10px; }
        }
        @media (max-width: 768px) {
            .sidebar { position: fixed; left: -280px; z-index: 1000; transition: all 0.3s; }
            .sidebar.show { left: 0; }
            .main-content { padding: 20px; }
            .table-leger { font-size: 9px; }
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-2 sidebar">
            <div class="sidebar-brand">
                <i class="fas fa-graduation-cap"></i>
                <h5>MONITORING NILAI</h5>
                <small>SDN CURUG 01 BOJONGSARI</small>
            </div>
            <div class="sidebar-menu">
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <a href="import_excel.php"><i class="fas fa-file-excel"></i> Import Excel</a>
                <a href="leger.php?kelas=<?= $kelas_id ?>" class="active"><i class="fas fa-file-alt"></i> Leger Nilai</a>
                <a href="grafik_nilai.php"><i class="fas fa-chart-bar"></i> Grafik Nilai</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-md-10 main-content">
            <div class="page-header no-print">
                <div class="d-flex justify-content-between align-items-center">
                    <h2><i class="fas fa-file-alt"></i> LEGER NILAI - KELAS <?= $kelas_info['nama_kelas'] ?></h2>
                    <div class="d-flex gap-2">
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Kembali
                        </a>
                        <button onclick="window.print()" class="btn btn-success">
                            <i class="fas fa-print"></i> Cetak
                        </button>
                    </div>
                </div>
            </div>

            <!-- Header Info -->
            <div class="header-info no-print">
                <div class="row">
                    <div class="col-md-4">
                        <p><strong>SEKOLAH:</strong> SD NEGERI CURUG 1</p>
                    </div>
                    <div class="col-md-4">
                        <p><strong>KELAS:</strong> Kelas <?= $kelas_info['nama_kelas'] ?></p>
                    </div>
                    <div class="col-md-4">
                        <p><strong>TAHUN PELAJARAN:</strong> <?= $kelas_info['tahun_ajaran'] ?> <?= strtoupper($kelas_info['semester']) ?></p>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-leger table-bordered">
                            <thead>
                                <tr>
                                    <th rowspan="3" style="width: 30px;">NO</th>
                                    <th rowspan="3" style="width: 200px;">NAMA SISWA</th>
                                    <th rowspan="3" style="width: 100px;">NISN</th>
                                    <th rowspan="3" style="width: 100px;">NIS</th>
                                    <th colspan="12">MATA PELAJARAN</th>
                                    <th colspan="3">KETIDAKHADIRAN</th>
                                    <th colspan="3">EKSTRA KURIKULER</th>
                                </tr>
                                <tr>
                                    <th>PAIDBP</th>
                                    <th>PAKDBP</th>
                                    <th>PPDK</th>
                                    <th>BI</th>
                                    <th>MU</th>
                                    <th>IPADSI</th>
                                    <th>PJODK</th>
                                    <th>ING</th>
                                    <th>MLBD</th>
                                    <th>SR</th>
                                    <th>GKS</th>
                                    <th>SB</th>
                                    <th rowspan="2">Sakit</th>
                                    <th rowspan="2">Izin</th>
                                    <th rowspan="2">Alpa</th>
                                    <th rowspan="2">Pramuka</th>
                                </tr>
                                
                            </thead>
                            <tbody>
                                <?php 
                                $no = 1;
                                while($siswa = mysqli_fetch_assoc($siswa_list)): 
                                    // Ambil nilai untuk siswa ini
                                    $nilai_query = mysqli_query($conn, "SELECT n.*, m.kode_mapel 
                                        FROM nilai n 
                                        JOIN mata_pelajaran m ON n.mapel_id = m.id 
                                        WHERE n.siswa_id = {$siswa['id']} AND n.kelas_id = $kelas_id");
                                    
                                    $nilai_data = [];
                                    while($n = mysqli_fetch_assoc($nilai_query)) {
                                        $nilai_data[$n['kode_mapel']] = $n['nilai_angka'];
                                    }
                                    
                                    // Ambil kehadiran
                                    $kehadiran = mysqli_query($conn, "SELECT * FROM kehadiran WHERE siswa_id = {$siswa['id']} AND kelas_id = $kelas_id")->fetch_assoc();
                                    
                                    // Ambil ekstrakurikuler
                                    $ekstra = mysqli_query($conn, "SELECT * FROM ekstrakurikuler WHERE siswa_id = {$siswa['id']}")->fetch_assoc();
                                ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td class="text-left"><strong><?= strtoupper($siswa['nama_siswa']) ?></strong></td>
                                    <td><?= $siswa['nisn'] ?></td>
                                    <td><?= $siswa['nis'] ?></td>
                                    
                                    <!-- Mata Pelajaran (12 Mapel) -->
                                    <?php 
                                    $mapel_list = ['PAIDBP', 'PAKDBP', 'PPDK', 'BI', 'MU', 'IPADSI', 'PJODK', 'ING', 'MLBD', 'SR', 'GKS', 'SB'];
                                    foreach($mapel_list as $mapel): 
                                        $nilai = $nilai_data[$mapel] ?? '';
                                        $badge = getBadgeClass($nilai);
                                    ?>
                                    <td>
                                        <span class="badge <?= $badge ?>"><?= $nilai ?></span>
                                    </td>
                                    <?php endforeach; ?>
                                    
                                    <!-- Kehadiran -->
                                    <td><?= $kehadiran['sakit'] ?? 0 ?></td>
                                    <td><?= $kehadiran['izin'] ?? 0 ?></td>
                                    <td><?= $kehadiran['alpa'] ?? 0 ?></td>
                                    
                                    <!-- Ekstrakurikuler -->
                                    <td><?= $ekstra['predikat'] ?? '-' ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Keterangan Mapel -->
            <div class="card no-print">
                <div class="card-body">
                    <h6><strong>KETERANGAN MATA PELAJARAN:</strong></h6>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>PAIDBP:</strong> Pendidikan Agama Islam dan Budi Pekerti</p>
                            <p><strong>PAKDBP:</strong> Pendidikan Agama Kristen dan Budi Pekerti</p>
                            <p><strong>PPDK:</strong> Pendidikan Pancasila dan Kewarganegaraan</p>
                            <p><strong>BI:</strong> Bahasa Indonesia</p>
                            <p><strong>MU:</strong> Matematika</p>
                            <p><strong>IPADSI:</strong> Ilmu Pengetahuan Alam dan Sosial (IPAS)</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>PJODK:</strong> Pendidikan Jasmani, Olahraga, dan Kesehatan</p>
                            <p><strong>ING:</strong> Bahasa Inggris</p>
                            <p><strong>MLBD:</strong> Muatan Lokal Bahasa Daerah</p>
                            <p><strong>SR:</strong> Seni Rupa</p>
                            <p><strong>GKS:</strong> Guru Kelas SD/MI/SLB</p>
                            <p><strong>SB:</strong> Seni Budaya</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>