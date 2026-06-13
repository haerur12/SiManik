<?php
require 'config.php';

if(!isset($_SESSION['login'])) {
    header("Location: index.php");
    exit;
}

// Handle Form Submissions
if(isset($_POST['tambah'])) {
    $nisn = mysqli_real_escape_string($conn, trim($_POST['nisn']));
    $nis = mysqli_real_escape_string($conn, trim($_POST['nis']));
    $nama = mysqli_real_escape_string($conn, trim($_POST['nama_siswa']));
    $kelas_id = (int)$_POST['kelas_id'];
    $jk = $_POST['jenis_kelamin'];
    
    // Check duplicate NISN
    $check = mysqli_query($conn, "SELECT id FROM siswa WHERE nisn = '$nisn'");
    if(mysqli_num_rows($check) > 0) {
        $error_msg = "❌ NISN sudah terdaftar!";
    } else {
        mysqli_query($conn, "INSERT INTO siswa (nisn, nis, nama_siswa, kelas_id, jenis_kelamin) VALUES ('$nisn', '$nis', '$nama', $kelas_id, '$jk')");
        $success_msg = "✅ Siswa berhasil ditambahkan!";
    }
}

if(isset($_POST['edit'])) {
    $id = (int)$_POST['id'];
    $nisn = mysqli_real_escape_string($conn, trim($_POST['nisn']));
    $nis = mysqli_real_escape_string($conn, trim($_POST['nis']));
    $nama = mysqli_real_escape_string($conn, trim($_POST['nama_siswa']));
    $kelas_id = (int)$_POST['kelas_id'];
    $jk = $_POST['jenis_kelamin'];
    
    mysqli_query($conn, "UPDATE siswa SET nisn='$nisn', nis='$nis', nama_siswa='$nama', kelas_id=$kelas_id, jenis_kelamin='$jk' WHERE id=$id");
    $success_msg = "✅ Data siswa berhasil diupdate!";
}

if(isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    mysqli_query($conn, "DELETE FROM siswa WHERE id=$id");
    $success_msg = "✅ Siswa berhasil dihapus!";
}

// Filter & Search
$kelas_filter = isset($_GET['kelas']) ? (int)$_GET['kelas'] : 0;
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';

$where_clause = "1=1";
if($kelas_filter > 0) $where_clause .= " AND s.kelas_id = $kelas_filter";
if(!empty($search)) $where_clause .= " AND (s.nama_siswa LIKE '%$search%' OR s.nisn LIKE '%$search%' OR s.nis LIKE '%$search%')";

// Pagination
$per_page = 15;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $per_page;

$total_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM siswa s JOIN kelas k ON s.kelas_id = k.id WHERE $where_clause");
$total_rows = mysqli_fetch_assoc($total_query)['total'];
$total_pages = ceil($total_rows / $per_page);

// Get Students
$siswa_list = mysqli_query($conn, "
SELECT s.*, k.nama_kelas 
FROM siswa s 
JOIN kelas k ON s.kelas_id = k.id 
WHERE $where_clause 
ORDER BY k.nama_kelas ASC, s.nama_siswa ASC 
LIMIT $per_page OFFSET $offset
");

// Kelas List
$kelas_list = mysqli_query($conn, "SELECT * FROM kelas ORDER BY id ASC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Siswa - SiManik</title>
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
        .badge-l { background: #3b82f6; color: white; }
        .badge-p { background: #ec4899; color: white; }
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
        .btn-danger:hover { background: #dc2626; color: white; }
        .btn-secondary { background: var(--slate); border: none; color: white; }
        .form-control:focus, .form-select:focus {
            border-color: var(--royal-blue);
            box-shadow: 0 0 0 0.25rem rgba(30, 64, 175, 0.15);
        }
        .modal-content { border-radius: 12px; border: none; }
        .modal-header {
            background: linear-gradient(135deg, var(--dark-navy) 0%, var(--royal-blue) 100%);
            color: white;
            border-radius: 12px 12px 0 0 !important;
        }
        .modal-header .btn-close { filter: brightness(0) invert(1); }
        .pagination .page-link {
            color: var(--royal-blue);
            border-color: var(--light-slate);
        }
        .pagination .page-item.active .page-link {
            background: var(--royal-blue);
            border-color: var(--royal-blue);
        }
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
        <div class="col-md-2 sidebar">
            <div class="sidebar-brand">
                <i class="fas fa-graduation-cap"></i>
                <h5>MONITORING NILAI</h5>
                <small>SDN CURUG 01 BOJONGSARI</small>
            </div>
            <div class="sidebar-menu">
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <a href="import_excel.php"><i class="fas fa-file-excel"></i> Import Excel</a>
                <a href="leger.php"><i class="fas fa-file-alt"></i> Leger Nilai</a>
                <a href="grafik_nilai.php"><i class="fas fa-chart-bar"></i> Grafik Nilai</a>
                <a href="data_siswa.php" class="active"><i class="fas fa-users"></i> Data Siswa</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-md-10 main-content">
            <div class="page-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h2><i class="fas fa-users"></i> DATA SISWA</h2>
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>
                </div>
            </div>

            <!-- Notifications -->
            <?php if(isset($success_msg)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?= $success_msg ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            <?php if(isset($error_msg)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?= $error_msg ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Filter & Search -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Cari Siswa</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" name="search" class="form-control" 
                                       value="<?= htmlspecialchars($search) ?>" 
                                       placeholder="Nama, NISN, atau NIS...">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Filter Kelas</label>
                            <select name="kelas" class="form-select">
                                <option value="0">Semua Kelas</option>
                                <?php
                                mysqli_data_seek($kelas_list, 0);
                                while($k = mysqli_fetch_assoc($kelas_list)):
                                ?>
                                <option value="<?= $k['id'] ?>" <?= $kelas_filter==$k['id']?'selected':'' ?>>
                                    <?= htmlspecialchars($k['nama_kelas']) ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-grow-1">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                            <a href="data_siswa.php" class="btn btn-secondary">
                                <i class="fas fa-redo"></i>
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Table -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-list"></i> Daftar Siswa (<?= $total_rows ?> Data)</span>
                    <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#modalTambah">
                        <i class="fas fa-plus"></i> Tambah Siswa
                    </button>
                </div>
                <div class="card-body">
                    <?php if(mysqli_num_rows($siswa_list) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama Siswa</th>
                                    <th>NISN</th>
                                    <th>NIS</th>
                                    <th>Kelas</th>
                                    <th>JK</th>
                                    <th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $no = $offset + 1;
                                while($s = mysqli_fetch_assoc($siswa_list)):
                                ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><strong><?= htmlspecialchars($s['nama_siswa']) ?></strong></td>
                                    <td class="font-monospace"><?= htmlspecialchars($s['nisn']) ?></td>
                                    <td class="font-monospace"><?= htmlspecialchars($s['nis']) ?></td>
                                    <td><span class="badge bg-primary"><?= htmlspecialchars($s['nama_kelas']) ?></span></td>
                                    <td>
                                        <span class="badge <?= $s['jenis_kelamin']=='L' ? 'badge-l' : 'badge-p' ?>">
                                            <?= $s['jenis_kelamin'] == 'L' ? 'Laki-laki' : 'Perempuan' ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-warning me-1" 
                                                onclick="editSiswa(<?= json_encode($s) ?>)"
                                                data-bs-toggle="modal" data-bs-target="#modalEdit">
                                            <i class="fas fa-pen"></i>
                                        </button>
                                        <a href="?hapus=<?= $s['id'] ?>&kelas=<?= $kelas_filter ?>&search=<?= urlencode($search) ?>&page=<?= $page ?>" 
                                           class="btn btn-sm btn-danger"
                                           onclick="return confirm('Yakin ingin menghapus siswa ini?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if($total_pages > 1): ?>
                    <nav class="mt-4">
                        <ul class="pagination justify-content-center mb-0">
                            <?php if($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page-1 ?>&kelas=<?= $kelas_filter ?>&search=<?= urlencode($search) ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                            <?php endif; ?>
                            
                            <?php
                            $start = max(1, $page - 2);
                            $end = min($total_pages, $page + 2);
                            for($i = $start; $i <= $end; $i++):
                            ?>
                            <li class="page-item <?= $i==$page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&kelas=<?= $kelas_filter ?>&search=<?= urlencode($search) ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                            <?php endfor; ?>
                            
                            <?php if($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page+1 ?>&kelas=<?= $kelas_filter ?>&search=<?= urlencode($search) ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>

                    <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-users text-slate fs-1 text-muted mb-3"></i>
                        <p class="text-muted">Tidak ada data siswa ditemukan</p>
                        <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#modalTambah">
                            <i class="fas fa-plus"></i> Tambah Siswa Baru
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Tambah Siswa -->
<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Tambah Siswa Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Siswa <span class="text-danger">*</span></label>
                        <input type="text" name="nama_siswa" class="form-control" required 
                               placeholder="Masukkan nama lengkap">
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">NISN <span class="text-danger">*</span></label>
                            <input type="text" name="nisn" class="form-control" required maxlength="10" 
                                   placeholder="10 digit" pattern="[0-9]{10}">
                            <small class="text-muted">Contoh: 3166992248</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">NIS</label>
                            <input type="text" name="nis" class="form-control" placeholder="Opsional">
                        </div>
                    </div>
                    <div class="row g-3 mt-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Kelas <span class="text-danger">*</span></label>
                            <select name="kelas_id" class="form-select" required>
                                <option value="">Pilih Kelas</option>
                                <?php
                                mysqli_data_seek($kelas_list, 0);
                                while($k = mysqli_fetch_assoc($kelas_list)):
                                ?>
                                <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kelas']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Jenis Kelamin <span class="text-danger">*</span></label>
                            <select name="jenis_kelamin" class="form-select" required>
                                <option value="L">Laki-laki</option>
                                <option value="P">Perempuan</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="tambah" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit Siswa -->
<div class="modal fade" id="modalEdit" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-edit me-2"></i>Edit Data Siswa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Siswa <span class="text-danger">*</span></label>
                        <input type="text" name="nama_siswa" id="edit_nama" class="form-control" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">NISN <span class="text-danger">*</span></label>
                            <input type="text" name="nisn" id="edit_nisn" class="form-control" required maxlength="10">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">NIS</label>
                            <input type="text" name="nis" id="edit_nis" class="form-control">
                        </div>
                    </div>
                    <div class="row g-3 mt-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Kelas <span class="text-danger">*</span></label>
                            <select name="kelas_id" id="edit_kelas" class="form-select" required>
                                <option value="">Pilih Kelas</option>
                                <?php
                                mysqli_data_seek($kelas_list, 0);
                                while($k = mysqli_fetch_assoc($kelas_list)):
                                ?>
                                <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kelas']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Jenis Kelamin <span class="text-danger">*</span></label>
                            <select name="jenis_kelamin" id="edit_jk" class="form-select" required>
                                <option value="L">Laki-laki</option>
                                <option value="P">Perempuan</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="edit" class="btn btn-warning">
                        <i class="fas fa-save me-1"></i> Update
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function editSiswa(siswa) {
    document.getElementById('edit_id').value = siswa.id;
    document.getElementById('edit_nama').value = siswa.nama_siswa;
    document.getElementById('edit_nisn').value = siswa.nisn;
    document.getElementById('edit_nis').value = siswa.nis;
    document.getElementById('edit_kelas').value = siswa.kelas_id;
    document.getElementById('edit_jk').value = siswa.jenis_kelamin;
}

// Auto-hide alerts after 5 seconds
document.querySelectorAll('.alert').forEach(alert => {
    setTimeout(() => {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    }, 5000);
});
</script>
</body>
</html>