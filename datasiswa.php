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
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] },
                    colors: {
                        brand: { 50: '#eff6ff', 100: '#dbeafe', 500: '#3b82f6', 600: '#2563eb', 700: '#1d4ed8', 800: '#1e40af', 900: '#1e3a8a' },
                        accent: { 400: '#fbbf24', 500: '#f59e0b', 600: '#d97706' }
                    }
                }
            }
        }
    </script>
    <style>
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .modal { display: none; }
        .modal.active { display: flex; }
        .fade-in { animation: fadeIn 0.3s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 font-sans antialiased">

<div class="flex h-screen overflow-hidden">
    <!-- Sidebar -->
    <aside class="hidden md:flex flex-col w-64 bg-slate-900 text-white h-screen fixed z-30 shadow-xl transition-all duration-300">
        <div class="h-16 flex items-center px-6 border-b border-slate-800 bg-slate-950">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-brand-600 to-brand-800 flex items-center justify-center">
                    <i class="fas fa-graduation-cap text-accent-400 text-sm"></i>
                </div>
                <div><h1 class="font-bold text-base tracking-tight">SiManik</h1><p class="text-[10px] text-slate-400">Monitoring Nilai</p></div>
            </div>
        </div>
        <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1">
            <a href="dashboard.php" class="flex items-center gap-3 px-3 py-2.5 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition-all text-sm"><i class="fas fa-home w-5 text-center"></i> Dashboard</a>
            <a href="import_excel.php" class="flex items-center gap-3 px-3 py-2.5 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition-all text-sm"><i class="fas fa-file-excel w-5 text-center"></i> Import Data</a>
            <a href="leger.php" class="flex items-center gap-3 px-3 py-2.5 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition-all text-sm"><i class="fas fa-book-open w-5 text-center"></i> Leger Nilai</a>
            <a href="grafik_nilai.php" class="flex items-center gap-3 px-3 py-2.5 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition-all text-sm"><i class="fas fa-chart-pie w-5 text-center"></i> Analitik Grafik</a>
            <div class="my-3 border-t border-slate-700"></div>
            <a href="data_siswa.php" class="flex items-center gap-3 px-3 py-2.5 bg-gradient-to-r from-brand-700 to-brand-600 text-white rounded-lg shadow-md text-sm"><i class="fas fa-users w-5 text-center"></i> Data Siswa</a>
        </nav>
        <div class="p-3 border-t border-slate-800 bg-slate-950">
            <a href="logout.php" class="flex items-center gap-3 px-3 py-2 text-red-400 hover:text-red-300 hover:bg-red-900/20 rounded-lg transition-all text-sm"><i class="fas fa-sign-out-alt w-5 text-center"></i> Logout</a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col h-screen overflow-hidden md:ml-64 transition-all duration-300">
        <!-- Header -->
        <header class="bg-white border-b border-slate-200 h-16 px-6 flex items-center justify-between sticky top-0 z-20">
            <div class="flex items-center gap-3">
                <h2 class="text-lg font-bold text-slate-800">Data Siswa</h2>
                <span class="px-2 py-0.5 bg-brand-100 text-brand-700 rounded text-[10px] font-bold"><?= $total_rows ?> Data</span>
            </div>
            <a href="dashboard.php" class="flex items-center gap-2 px-4 py-2 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 rounded-lg text-sm font-medium transition-colors">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
        </header>

        <!-- Content -->
        <div class="flex-1 overflow-y-auto p-6 pb-20">
            
            <!-- Notifications -->
            <?php if(isset($success_msg)): ?>
            <div class="mb-4 p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl flex items-center gap-3">
                <i class="fas fa-check-circle text-xl"></i>
                <span class="font-medium"><?= $success_msg ?></span>
                <button onclick="this.parentElement.remove()" class="ml-auto text-emerald-400 hover:text-emerald-600"><i class="fas fa-times"></i></button>
            </div>
            <?php endif; ?>
            <?php if(isset($error_msg)): ?>
            <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-700 rounded-xl flex items-center gap-3">
                <i class="fas fa-exclamation-circle text-xl"></i>
                <span class="font-medium"><?= $error_msg ?></span>
                <button onclick="this.parentElement.remove()" class="ml-auto text-red-400 hover:text-red-600"><i class="fas fa-times"></i></button>
            </div>
            <?php endif; ?>

            <!-- Filter & Action Bar -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 mb-6">
                <form method="GET" class="flex flex-col md:flex-row gap-4 items-start md:items-end">
                    <div class="flex-1 w-full">
                        <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Cari Siswa</label>
                        <div class="relative">
                            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari nama, NISN, atau NIS..." class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                        </div>
                    </div>
                    <div class="w-full md:w-48">
                        <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Filter Kelas</label>
                        <select name="kelas" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                            <option value="0">Semua Kelas</option>
                            <?php
                            mysqli_data_seek($kelas_list, 0);
                            while($k = mysqli_fetch_assoc($kelas_list)):
                            ?>
                            <option value="<?= $k['id'] ?>" <?= $kelas_filter==$k['id']?'selected':'' ?>><?= htmlspecialchars($k['nama_kelas']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <button type="submit" class="px-6 py-2.5 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-sm font-medium transition-colors">
                        <i class="fas fa-filter mr-2"></i>Filter
                    </button>
                    <a href="data_siswa.php" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-lg text-sm font-medium transition-colors">
                        <i class="fas fa-redo"></i>
                    </a>
                </form>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <h3 class="font-bold text-slate-800 flex items-center gap-2">
                        <i class="fas fa-list text-brand-600"></i> Daftar Siswa
                    </h3>
                    <button onclick="openModal('addModal')" class="px-5 py-2.5 bg-accent-500 hover:bg-accent-600 text-white rounded-lg text-sm font-medium transition-colors shadow-sm shadow-accent-200">
                        <i class="fas fa-plus mr-2"></i>Tambah Siswa
                    </button>
                </div>
                
                <?php if(mysqli_num_rows($siswa_list) > 0): ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-slate-50 text-slate-500 uppercase text-xs">
                            <tr>
                                <th class="px-6 py-4 font-semibold">No</th>
                                <th class="px-6 py-4 font-semibold">Nama Siswa</th>
                                <th class="px-6 py-4 font-semibold">NISN</th>
                                <th class="px-6 py-4 font-semibold">NIS</th>
                                <th class="px-6 py-4 font-semibold">Kelas</th>
                                <th class="px-6 py-4 font-semibold">JK</th>
                                <th class="px-6 py-4 font-semibold text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php
                            $no = $offset + 1;
                            while($s = mysqli_fetch_assoc($siswa_list)):
                            ?>
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-6 py-4 text-slate-400"><?= $no++ ?></td>
                                <td class="px-6 py-4 font-medium text-slate-800"><?= htmlspecialchars($s['nama_siswa']) ?></td>
                                <td class="px-6 py-4 font-mono text-slate-500"><?= htmlspecialchars($s['nisn']) ?></td>
                                <td class="px-6 py-4 font-mono text-slate-500"><?= htmlspecialchars($s['nis']) ?></td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 bg-brand-50 text-brand-700 rounded-md text-xs font-semibold"><?= htmlspecialchars($s['nama_kelas']) ?></span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 <?= $s['jenis_kelamin']=='L' ? 'bg-blue-100 text-blue-700' : 'bg-pink-100 text-pink-700' ?> rounded text-xs font-bold"><?= $s['jenis_kelamin'] ?></span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <button onclick='editSiswa(<?= json_encode($s) ?>)' class="w-8 h-8 flex items-center justify-center bg-amber-50 hover:bg-amber-100 text-amber-600 rounded-lg transition-colors" title="Edit">
                                            <i class="fas fa-pen text-xs"></i>
                                        </button>
                                        <a href="?hapus=<?= $s['id'] ?>&kelas=<?= $kelas_filter ?>&search=<?= urlencode($search) ?>&page=<?= $page ?>" onclick="return confirm('Yakin ingin menghapus siswa ini?')" class="w-8 h-8 flex items-center justify-center bg-red-50 hover:bg-red-100 text-red-600 rounded-lg transition-colors" title="Hapus">
                                            <i class="fas fa-trash text-xs"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if($total_pages > 1): ?>
                <div class="p-5 border-t border-slate-100 flex items-center justify-between">
                    <p class="text-sm text-slate-500">
                        Menampilkan <?= $offset+1 ?> - <?= min($offset+$per_page, $total_rows) ?> dari <?= $total_rows ?> data
                    </p>
                    <div class="flex items-center gap-2">
                        <?php if($page > 1): ?>
                        <a href="?page=<?= $page-1 ?>&kelas=<?= $kelas_filter ?>&search=<?= urlencode($search) ?>" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-lg text-sm font-medium transition-colors">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                        <?php endif; ?>
                        
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        for($i = $start_page; $i <= $end_page; $i++):
                        ?>
                        <a href="?page=<?= $i ?>&kelas=<?= $kelas_filter ?>&search=<?= urlencode($search) ?>" class="px-3 py-1.5 <?= $i==$page ? 'bg-brand-600 text-white' : 'bg-slate-100 hover:bg-slate-200 text-slate-600' ?> rounded-lg text-sm font-medium transition-colors">
                            <?= $i ?>
                        </a>
                        <?php endfor; ?>
                        
                        <?php if($page < $total_pages): ?>
                        <a href="?page=<?= $page+1 ?>&kelas=<?= $kelas_filter ?>&search=<?= urlencode($search) ?>" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-lg text-sm font-medium transition-colors">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php else: ?>
                <div class="p-12 text-center">
                    <i class="fas fa-users text-4xl text-slate-300 mb-4"></i>
                    <p class="text-slate-500 font-medium">Tidak ada data siswa ditemukan</p>
                    <p class="text-slate-400 text-sm mt-1">Coba ubah filter atau tambahkan siswa baru</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- Modal: Tambah Siswa -->
<div id="addModal" class="modal fixed inset-0 z-50 items-center justify-center bg-slate-900/50 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-4 fade-in">
        <div class="p-6 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-lg font-bold text-slate-800"><i class="fas fa-user-plus text-brand-600 mr-2"></i>Tambah Siswa Baru</h3>
            <button onclick="closeModal('addModal')" class="w-8 h-8 flex items-center justify-center hover:bg-slate-100 rounded-lg text-slate-400 hover:text-slate-600 transition-colors">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Nama Siswa <span class="text-red-500">*</span></label>
                <input type="text" name="nama_siswa" required placeholder="Masukkan nama lengkap" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">NISN <span class="text-red-500">*</span></label>
                    <input type="text" name="nisn" required maxlength="10" placeholder="10 digit" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">NIS</label>
                    <input type="text" name="nis" placeholder="Opsional" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Kelas <span class="text-red-500">*</span></label>
                    <select name="kelas_id" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                        <option value="">Pilih Kelas</option>
                        <?php
                        mysqli_data_seek($kelas_list, 0);
                        while($k = mysqli_fetch_assoc($kelas_list)):
                        ?>
                        <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kelas']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Jenis Kelamin <span class="text-red-500">*</span></label>
                    <select name="jenis_kelamin" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                        <option value="L">Laki-laki</option>
                        <option value="P">Perempuan</option>
                    </select>
                </div>
            </div>
            <div class="flex gap-3 pt-4 border-t border-slate-100">
                <button type="submit" name="tambah" class="flex-1 py-2.5 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-sm font-semibold transition-colors">
                    <i class="fas fa-save mr-2"></i>Simpan
                </button>
                <button type="button" onclick="closeModal('addModal')" class="flex-1 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-sm font-semibold transition-colors">
                    Batal
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Siswa -->
<div id="editModal" class="modal fixed inset-0 z-50 items-center justify-center bg-slate-900/50 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-4 fade-in">
        <div class="p-6 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-lg font-bold text-slate-800"><i class="fas fa-user-edit text-amber-500 mr-2"></i>Edit Data Siswa</h3>
            <button onclick="closeModal('editModal')" class="w-8 h-8 flex items-center justify-center hover:bg-slate-100 rounded-lg text-slate-400 hover:text-slate-600 transition-colors">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="id" id="edit_id">
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Nama Siswa <span class="text-red-500">*</span></label>
                <input type="text" name="nama_siswa" id="edit_nama" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">NISN <span class="text-red-500">*</span></label>
                    <input type="text" name="nisn" id="edit_nisn" required maxlength="10" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">NIS</label>
                    <input type="text" name="nis" id="edit_nis" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Kelas <span class="text-red-500">*</span></label>
                    <select name="kelas_id" id="edit_kelas" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                        <option value="">Pilih Kelas</option>
                        <?php
                        mysqli_data_seek($kelas_list, 0);
                        while($k = mysqli_fetch_assoc($kelas_list)):
                        ?>
                        <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kelas']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Jenis Kelamin <span class="text-red-500">*</span></label>
                    <select name="jenis_kelamin" id="edit_jk" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                        <option value="L">Laki-laki</option>
                        <option value="P">Perempuan</option>
                    </select>
                </div>
            </div>
            <div class="flex gap-3 pt-4 border-t border-slate-100">
                <button type="submit" name="edit" class="flex-1 py-2.5 bg-amber-500 hover:bg-amber-600 text-white rounded-lg text-sm font-semibold transition-colors">
                    <i class="fas fa-save mr-2"></i>Update
                </button>
                <button type="button" onclick="closeModal('editModal')" class="flex-1 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-sm font-semibold transition-colors">
                    Batal
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal(id) {
        document.getElementById(id).classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    
    function closeModal(id) {
        document.getElementById(id).classList.remove('active');
        document.body.style.overflow = '';
    }
    
    // Close modal on backdrop click
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if(e.target === this) {
                this.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
    });
    
    function editSiswa(siswa) {
        document.getElementById('edit_id').value = siswa.id;
        document.getElementById('edit_nama').value = siswa.nama_siswa;
        document.getElementById('edit_nisn').value = siswa.nisn;
        document.getElementById('edit_nis').value = siswa.nis;
        document.getElementById('edit_kelas').value = siswa.kelas_id;
        document.getElementById('edit_jk').value = siswa.jenis_kelamin;
        openModal('editModal');
    }
    
    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
        if(e.key === 'Escape') {
            document.querySelectorAll('.modal.active').forEach(m => {
                m.classList.remove('active');
            });
            document.body.style.overflow = '';
        }
    });
</script>
</body>
</html>