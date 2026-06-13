<?php
require 'config.php';
if(!isset($_SESSION['login'])) {
    header("Location: index.php");
    exit;
}

$kelas_id = isset($_GET['kelas']) ? $_GET['kelas'] : 1;
$kelas_info = mysqli_query($conn, "SELECT * FROM kelas WHERE id = $kelas_id")->fetch_assoc();

// Handle delete
if(isset($_GET['hapus'])) {
    $id = $_GET['hapus'];
    mysqli_query($conn, "DELETE FROM siswa WHERE id = $id");
    header("Location: data_siswa.php?kelas=$kelas_id");
    exit;
}

// Get siswa list
$siswa_list = mysqli_query($conn, "SELECT * FROM siswa WHERE kelas_id = $kelas_id ORDER BY nama_siswa ASC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Siswa - Monitoring Nilai</title>
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
        .card-header {
            background: linear-gradient(135deg, var(--dark-navy) 0%, var(--royal-blue) 100%);
            color: var(--white);
            padding: 18px 25px;
            font-weight: 700;
            font-size: 16px;
            border-radius: 12px 12px 0 0 !important;
        }
        .card-header i { margin-right: 10px; color: var(--gold); }
        .card-body { padding: 25px; }
        .table { margin-bottom: 0; }
        .table thead th {
            background: var(--dark-navy);
            color: var(--white);
            border: none;
            padding: 15px;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .table tbody td {
            padding: 15px;
            vertical-align: middle;
            border-color: var(--light-slate);
            font-size: 14px;
        }
        .table tbody tr:hover { background: var(--light-bg); }
        .badge { padding: 8px 14px; font-weight: 600; font-size: 12px; border-radius: 6px; }
        .badge-success { background: #10b981; color: white; }
        .badge-warning { background: var(--gold); color: white; }
        .badge-danger { background: #ef4444; color: white; }
        .badge-primary { background: var(--royal-blue); color: white; }
        .btn { border-radius: 8px; padding: 10px 20px; font-weight: 600; font-size: 14px; transition: all 0.3s; }
        .btn-primary {
            background: linear-gradient(135deg, var(--royal-blue) 0%, var(--bright-blue) 100%);
            border: none;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, var(--bright-blue) 0%, var(--royal-blue) 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(30, 64, 175, 0.3);
        }
        .btn-warning { background: var(--gold); border: none; color: white; }
        .btn-warning:hover { background: #b45309; color: white; }
        .btn-danger { background: #ef4444; border: none; color: white; }
        .btn-secondary { background: var(--slate); border: none; color: white; }
        @media (max-width: 768px) {
            .sidebar { position: fixed; left: -280px; z-index: 1000; transition: all 0.3s; }
            .sidebar.show { left: 0; }
            .main-content { padding: 20px; }
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="col-md-10 main-content">
            <div class="page-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h2><i class="fas fa-users"></i> DATA SISWA - KELAS <?= $kelas_info['nama_kelas'] ?></h2>
                    <div class="d-flex gap-2">
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Kembali
                        </a>
                        <select class="form-select form-select-sm" style="width: 150px; background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.3);" onchange="window.location='?kelas='+this.value">
                            <option value="1" <?= $kelas_id==1?'selected':'' ?>>Kelas 4A</option>
                            <option value="2" <?= $kelas_id==2?'selected':'' ?>>Kelas 4B</option>
                            <option value="3" <?= $kelas_id==3?'selected':'' ?>>Kelas 4C</option>
                            <option value="4" <?= $kelas_id==4?'selected':'' ?>>Kelas 4D</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-list"></i> DAFTAR SISWA</span>
                    <button class="btn btn-warning btn-sm">
                        <i class="fas fa-plus"></i> Tambah Siswa
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>NISN</th>
                                    <th>NIS</th>
                                    <th>Nama Siswa</th>
                                    <th>Jenis Kelamin</th>
                                    <th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $no = 1;
                                while($siswa = mysqli_fetch_assoc($siswa_list)):
                                ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><?= $siswa['nisn'] ?></td>
                                    <td><?= $siswa['nis'] ?></td>
                                    <td><strong><?= $siswa['nama_siswa'] ?></strong></td>
                                    <td><?= $siswa['jenis_kelamin'] == 'L' ? 'Laki-laki' : 'Perempuan' ?></td>
                                    <td class="text-center">
                                        <button class="btn btn-warning btn-sm">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="?hapus=<?= $siswa['id'] ?>&kelas=<?= $kelas_id ?>" class="btn btn-danger btn-sm" onclick="return confirm('Yakin ingin menghapus?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>