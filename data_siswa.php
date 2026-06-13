<?php
require 'config.php';

$debug_mode = false;
if(!isset($_SESSION['login'])) {
    if(isset($_GET['debug']) && $_GET['debug'] === '1') {
        $debug_mode = true;
    } else {
        header("Location: index.php");
        exit;
    }
}

// Sidebar variables
$current_page = basename($_SERVER['PHP_SELF']);
function isActive($page, $current) {
    return $page === $current ? 'active' : '';
}
$user_name = $_SESSION['nama_lengkap'] ?? 'Wali Kelas';
$user_level = ucfirst($_SESSION['level'] ?? 'guru');

// Handle Form Submissions
if(isset($_POST['tambah'])) {
    $nisn = mysqli_real_escape_string($conn, trim($_POST['nisn']));
    $nis = mysqli_real_escape_string($conn, trim($_POST['nis']));
    $nama = mysqli_real_escape_string($conn, trim($_POST['nama_siswa']));
    $kelas_id = (int)$_POST['kelas_id'];
    $jk = $_POST['jenis_kelamin'];
    
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
$kelas_filter = isset($_GET['kelas']) ? (int)$_GET['kelas'] : 1;
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

// Current kelas info (safe)
$kelas_info = null;
if($kelas_filter > 0) {
    $res_k = mysqli_query($conn, "SELECT * FROM kelas WHERE id = $kelas_filter");
    if($res_k && mysqli_num_rows($res_k) > 0) {
        $kelas_info = mysqli_fetch_assoc($res_k);
    }
}
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Siswa - SiManik</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                    },
                    colors: {
                        primary: {
                            50: '#f0f9ff', 100: '#e0f2fe', 200: '#bae6fd', 300: '#7dd3fc',
                            400: '#38bdf8', 500: '#0ea5e9', 600: '#0284c7', 700: '#0369a1',
                            800: '#075985', 900: '#0c4a6e',
                        },
                        accent: {
                            400: '#facc15', 500: '#eab308', 600: '#ca8a04',
                        }
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease-out',
                        'slide-up': 'slideUp 0.6s ease-out',
                        'scale-in': 'scaleIn 0.4s ease-out',
                    },
                    keyframes: {
                        fadeIn: { '0%': { opacity: '0' }, '100%': { opacity: '1' } },
                        slideUp: { '0%': { transform: 'translateY(20px)', opacity: '0' }, '100%': { transform: 'translateY(0)', opacity: '1' } },
                        scaleIn: { '0%': { transform: 'scale(0.95)', opacity: '0' }, '100%': { transform: 'scale(1)', opacity: '1' } }
                    }
                }
            }
        }
    </script>
    <style>
        * { -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; }

        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: linear-gradient(180deg, #0ea5e9, #0284c7); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: linear-gradient(180deg, #0284c7, #0369a1); }
        
        .glass-header {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border-bottom: 1px solid rgba(226, 232, 240, 0.5);
        }
        
        .card-hover { transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
        .card-hover:hover { transform: translateY(-4px); box-shadow: 0 20px 40px -12px rgba(0, 0, 0, 0.15); }
        
        .modal { display: none; }
        .modal.active { display: flex; }
        .fade-in { animation: fadeIn 0.3s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }

        .sidebar-item { position: relative; overflow: hidden; }
        .sidebar-item::before {
            content: ''; position: absolute; left: 0; top: 0; height: 100%; width: 3px;
            background: linear-gradient(180deg, #0ea5e9, #0284c7);
            transform: scaleY(0); transition: transform 0.3s ease;
        }
        .sidebar-item:hover::before, .sidebar-item.active::before { transform: scaleY(1); }
        .sidebar-item.active { background: linear-gradient(to right, rgba(14, 165, 233, 0.1), transparent); }
        .sidebar-item.active .w-8 { background: linear-gradient(135deg, #0ea5e9, #0284c7) !important; }
        .sidebar-item.active i { color: white !important; }

        .table-row-hover { transition: all 0.2s ease; }
        .table-row-hover:hover { background: linear-gradient(90deg, rgba(14, 165, 233, 0.05) 0%, rgba(14, 165, 233, 0.02) 100%); }

        .notification-enter { animation: slideInRight 0.4s ease-out; }
        @keyframes slideInRight { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        aside::-webkit-scrollbar { width: 4px; }
        aside::-webkit-scrollbar-track { background: transparent; }
        aside::-webkit-scrollbar-thumb { background: rgba(14, 165, 233, 0.3); border-radius: 10px; }
        aside::-webkit-scrollbar-thumb:hover { background: rgba(14, 165, 233, 0.5); }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 via-blue-50/30 to-slate-50 text-slate-800 font-sans antialiased selection:bg-primary-100 selection:text-primary-900">

    <div class="flex h-screen overflow-hidden">
        
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <main class="flex-1 flex flex-col h-screen overflow-hidden relative md:ml-72 transition-all duration-300">
            
            <!-- Header -->
            <header class="glass-header h-20 px-6 md:px-8 flex items-center justify-between sticky top-0 z-20">
                <div class="flex items-center gap-4">
                    <button onclick="toggleSidebar()" class="md:hidden w-10 h-10 flex items-center justify-center bg-white rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 shadow-sm transition-all">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div>
                        <h2 class="text-xl font-bold text-slate-800">Data Siswa</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Kelola data siswa kelas <?= htmlspecialchars($kelas_info['nama_kelas'] ?? 'Semua Kelas') ?></p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <div class="hidden md:flex items-center bg-white rounded-xl p-1 border border-slate-200 shadow-sm">
                        <?php for($i=1; $i<=4; $i++): ?>
                        <a href="?kelas=<?= $i ?>" class="px-4 py-2 text-xs font-semibold rounded-lg transition-all <?= $kelas_filter==$i ? 'bg-gradient-to-r from-primary-500 to-primary-600 text-white shadow-md shadow-primary-500/30' : 'text-slate-600 hover:text-primary-600 hover:bg-slate-50' ?>">
                            4<?= chr(64+$i) ?>
                        </a>
                        <?php endfor; ?>
                    </div>
                    <a href="dashboard.php?kelas=<?= $kelas_filter ?>" class="flex items-center gap-2 px-4 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 hover:border-primary-300 text-slate-700 rounded-xl text-sm font-semibold transition-all shadow-sm group">
                        <i class="fas fa-arrow-left group-hover:-translate-x-1 transition-transform"></i> Kembali
                    </a>
                </div>
            </header>

            <!-- Scrollable Content -->
            <div class="flex-1 overflow-y-auto p-6 md:p-8 pb-20">
                
                <!-- Notifications -->
                <?php if(isset($success_msg)): ?>
                <div class="mb-6 p-5 bg-gradient-to-r from-emerald-50 to-green-50 border border-emerald-200 text-emerald-700 rounded-2xl flex items-center gap-4 notification-enter shadow-lg shadow-emerald-500/10">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-emerald-400 to-emerald-600 flex items-center justify-center flex-shrink-0 shadow-lg shadow-emerald-500/30">
                        <i class="fas fa-check-circle text-white text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <p class="font-bold text-emerald-900">Berhasil!</p>
                        <span class="text-sm font-medium"><?= $success_msg ?></span>
                    </div>
                    <button onclick="this.parentElement.remove()" class="text-emerald-400 hover:text-emerald-600 p-2 hover:bg-emerald-100 rounded-lg transition-all">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <?php endif; ?>
                <?php if(isset($error_msg)): ?>
                <div class="mb-6 p-5 bg-gradient-to-r from-red-50 to-rose-50 border border-red-200 text-red-700 rounded-2xl flex items-center gap-4 notification-enter shadow-lg shadow-red-500/10">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-red-400 to-red-600 flex items-center justify-center flex-shrink-0 shadow-lg shadow-red-500/30">
                        <i class="fas fa-exclamation-circle text-white text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <p class="font-bold text-red-900">Gagal!</p>
                        <span class="text-sm font-medium"><?= $error_msg ?></span>
                    </div>
                    <button onclick="this.parentElement.remove()" class="text-red-400 hover:text-red-600 p-2 hover:bg-red-100 rounded-lg transition-all">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <?php endif; ?>
                <?php if(!empty($debug_mode)): ?>
                <div class="mb-6 p-5 bg-gradient-to-r from-yellow-50 to-amber-50 border border-yellow-200 text-yellow-700 rounded-2xl flex items-center gap-4 notification-enter shadow-lg shadow-yellow-500/10">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-yellow-400 to-yellow-600 flex items-center justify-center flex-shrink-0 shadow-lg shadow-yellow-500/30">
                        <i class="fas fa-tools text-white text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <p class="font-bold text-yellow-900">Debug Mode Aktif</p>
                        <span class="text-sm font-medium">Anda melihat halaman tanpa autentikasi. Hapus <code class="bg-yellow-100 px-1 rounded">?debug=1</code> setelah selesai.</span>
                    </div>
                    <button onclick="this.parentElement.remove()" class="text-yellow-400 hover:text-yellow-600 p-2 hover:bg-yellow-100 rounded-lg transition-all">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <?php endif; ?>

                <!-- Filter & Search Bar -->
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6 mb-6 animate-slide-up">
                    <div class="flex items-center gap-3 mb-5">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center shadow-lg shadow-primary-500/30">
                            <i class="fas fa-filter text-white"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-slate-800">Filter & Pencarian</h3>
                            <p class="text-xs text-slate-500 mt-0.5">Cari dan filter data siswa</p>
                        </div>
                    </div>
                    <form method="GET" class="flex flex-col md:flex-row gap-4 items-start md:items-end">
                        <div class="flex-1 w-full">
                            <label class="block text-xs font-semibold text-slate-600 uppercase mb-2 flex items-center gap-2">
                                <i class="fas fa-search text-primary-500"></i>
                                Cari Siswa
                            </label>
                            <div class="relative">
                                <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari nama, NISN, atau NIS..." class="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all">
                            </div>
                        </div>
                        <div class="w-full md:w-56">
                            <label class="block text-xs font-semibold text-slate-600 uppercase mb-2 flex items-center gap-2">
                                <i class="fas fa-school text-primary-500"></i>
                                Filter Kelas
                            </label>
                            <select name="kelas" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 transition-all" onchange="this.form.submit()">
                                <option value="0">Semua Kelas</option>
                                <?php
                                mysqli_data_seek($kelas_list, 0);
                                while($k = mysqli_fetch_assoc($kelas_list)):
                                ?>
                                <option value="<?= $k['id'] ?>" <?= $kelas_filter==$k['id']?'selected':'' ?>><?= htmlspecialchars($k['nama_kelas']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <button type="submit" class="px-6 py-3 bg-gradient-to-r from-primary-500 to-primary-600 hover:from-primary-600 hover:to-primary-700 text-white rounded-xl text-sm font-semibold transition-all shadow-lg shadow-primary-500/30 hover:shadow-xl hover:shadow-primary-500/40 active:scale-95">
                            <i class="fas fa-filter mr-2"></i>Filter
                        </button>
                        <a href="data_siswa.php" class="px-4 py-3 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-sm font-semibold transition-all">
                            <i class="fas fa-redo"></i>
                        </a>
                    </form>
                </div>

                <!-- Table Card -->
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden animate-slide-up" style="animation-delay: 0.1s;">
                    <div class="p-6 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-gradient-to-r from-slate-50 to-white">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center shadow-lg shadow-primary-500/30">
                                <i class="fas fa-list text-white"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-slate-800">Daftar Siswa</h3>
                                <p class="text-xs text-slate-500 mt-0.5">Total <strong class="text-primary-600"><?= $total_rows ?></strong> data siswa</p>
                            </div>
                        </div>
                        <button onclick="openModal('addModal')" class="px-5 py-2.5 bg-gradient-to-r from-accent-500 to-accent-600 hover:from-accent-600 hover:to-accent-700 text-white rounded-xl text-sm font-semibold transition-all shadow-lg shadow-accent-500/30 hover:shadow-xl hover:shadow-accent-500/40 active:scale-95">
                            <i class="fas fa-plus mr-2"></i>Tambah Siswa
                        </button>
                    </div>
                    
                    <?php if(mysqli_num_rows($siswa_list) > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="text-xs text-slate-600 uppercase bg-slate-50/80 backdrop-blur-sm">
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
                                <tr class="table-row-hover">
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-slate-100 text-slate-600 text-xs font-bold">
                                            <?= $no++ ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <img src="https://ui-avatars.com/api/?name=<?= urlencode($s['nama_siswa']) ?>&background=<?= $s['jenis_kelamin']=='L' ? '0ea5e9' : 'ec4899' ?>&color=fff&bold=true&size=40" alt="Avatar" class="w-9 h-9 rounded-full ring-2 ring-primary-100">
                                            <span class="font-semibold text-slate-800"><?= htmlspecialchars($s['nama_siswa']) ?></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="font-mono text-slate-600 bg-slate-50 px-2 py-1 rounded text-xs"><?= htmlspecialchars($s['nisn']) ?></span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="font-mono text-slate-600 bg-slate-50 px-2 py-1 rounded text-xs"><?= htmlspecialchars($s['nis']) ?></span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-3 py-1.5 bg-gradient-to-r from-primary-50 to-blue-50 text-primary-700 rounded-lg text-xs font-semibold border border-primary-200">
                                            <?= htmlspecialchars($s['nama_kelas']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-3 py-1.5 <?= $s['jenis_kelamin']=='L' ? 'bg-gradient-to-r from-blue-100 to-blue-200 text-blue-700 border-blue-300' : 'bg-gradient-to-r from-pink-100 to-pink-200 text-pink-700 border-pink-300' ?> rounded-lg text-xs font-bold border">
                                            <?= $s['jenis_kelamin'] == 'L' ? 'Laki-laki' : 'Perempuan' ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            <button onclick='editSiswa(<?= json_encode($s) ?>)' class="w-9 h-9 flex items-center justify-center bg-gradient-to-br from-amber-50 to-amber-100 hover:from-amber-100 hover:to-amber-200 text-amber-600 rounded-lg transition-all shadow-sm hover:shadow-md" title="Edit">
                                                <i class="fas fa-pen text-xs"></i>
                                            </button>
                                            <a href="?hapus=<?= $s['id'] ?>&kelas=<?= $kelas_filter ?>&search=<?= urlencode($search) ?>&page=<?= $page ?>" onclick="return confirm('Yakin ingin menghapus siswa ini? Data tidak bisa dikembalikan.')" class="w-9 h-9 flex items-center justify-center bg-gradient-to-br from-red-50 to-red-100 hover:from-red-100 hover:to-red-200 text-red-600 rounded-lg transition-all shadow-sm hover:shadow-md" title="Hapus">
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
                    <div class="p-5 border-t border-slate-100 flex items-center justify-between bg-gradient-to-r from-slate-50 to-white">
                        <p class="text-sm text-slate-600">
                            Menampilkan <strong class="text-slate-800"><?= $offset+1 ?></strong> - <strong class="text-slate-800"><?= min($offset+$per_page, $total_rows) ?></strong> dari <strong class="text-primary-600"><?= $total_rows ?></strong> data
                        </p>
                        <div class="flex items-center gap-2">
                            <?php if($page > 1): ?>
                            <a href="?page=<?= $page-1 ?>&kelas=<?= $kelas_filter ?>&search=<?= urlencode($search) ?>" class="w-9 h-9 flex items-center justify-center bg-white border border-slate-200 hover:bg-slate-50 hover:border-primary-300 text-slate-600 rounded-lg text-sm font-medium transition-all shadow-sm">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                            <?php endif; ?>
                            
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            for($i = $start_page; $i <= $end_page; $i++):
                            ?>
                            <a href="?page=<?= $i ?>&kelas=<?= $kelas_filter ?>&search=<?= urlencode($search) ?>" class="w-9 h-9 flex items-center justify-center <?= $i==$page ? 'bg-gradient-to-r from-primary-500 to-primary-600 text-white shadow-md shadow-primary-500/30' : 'bg-white border border-slate-200 hover:bg-slate-50 hover:border-primary-300 text-slate-600' ?> rounded-lg text-sm font-semibold transition-all">
                                <?= $i ?>
                            </a>
                            <?php endfor; ?>
                            
                            <?php if($page < $total_pages): ?>
                            <a href="?page=<?= $page+1 ?>&kelas=<?= $kelas_filter ?>&search=<?= urlencode($search) ?>" class="w-9 h-9 flex items-center justify-center bg-white border border-slate-200 hover:bg-slate-50 hover:border-primary-300 text-slate-600 rounded-lg text-sm font-medium transition-all shadow-sm">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php else: ?>
                    <div class="p-16 text-center">
                        <div class="w-20 h-20 mx-auto bg-gradient-to-br from-slate-100 to-slate-200 rounded-2xl flex items-center justify-center mb-4">
                            <i class="fas fa-users text-4xl text-slate-400"></i>
                        </div>
                        <p class="text-slate-700 font-bold text-lg">Tidak ada data siswa ditemukan</p>
                        <p class="text-slate-500 text-sm mt-2">Coba ubah filter atau tambahkan siswa baru</p>
                        <button onclick="openModal('addModal')" class="mt-6 px-6 py-3 bg-gradient-to-r from-accent-500 to-accent-600 hover:from-accent-600 hover:to-accent-700 text-white rounded-xl text-sm font-semibold transition-all shadow-lg shadow-accent-500/30 hover:shadow-xl hover:shadow-accent-500/40 active:scale-95">
                            <i class="fas fa-plus mr-2"></i>Tambah Siswa Baru
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal: Tambah Siswa -->
    <div id="addModal" class="modal fixed inset-0 z-50 items-center justify-center bg-slate-900/60 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-4 fade-in overflow-hidden">
            <div class="p-6 border-b border-slate-100 flex items-center justify-between bg-gradient-to-r from-primary-50 to-blue-50">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center shadow-lg shadow-primary-500/30">
                        <i class="fas fa-user-plus text-white"></i>
                    </div>
                    <h3 class="text-lg font-bold text-slate-800">Tambah Siswa Baru</h3>
                </div>
                <button onclick="closeModal('addModal')" class="w-8 h-8 flex items-center justify-center hover:bg-slate-100 rounded-lg text-slate-400 hover:text-slate-600 transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2 flex items-center gap-2">
                        <i class="fas fa-user text-primary-500"></i>
                        Nama Siswa <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="nama_siswa" required placeholder="Masukkan nama lengkap" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2 flex items-center gap-2">
                            <i class="fas fa-id-card text-primary-500"></i>
                            NISN <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="nisn" required maxlength="10" placeholder="10 digit" pattern="[0-9]{10}" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all">
                        <p class="text-[10px] text-slate-400 mt-1">Contoh: 3166992248</p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2 flex items-center gap-2">
                            <i class="fas fa-hashtag text-primary-500"></i>
                            NIS
                        </label>
                        <input type="text" name="nis" placeholder="Opsional" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2 flex items-center gap-2">
                            <i class="fas fa-school text-primary-500"></i>
                            Kelas <span class="text-red-500">*</span>
                        </label>
                        <select name="kelas_id" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 transition-all">
                            <option value="">Pilih Kelas</option>
                            <?php
                            mysqli_data_seek($kelas_list, 0);
                            while($k = mysqli_fetch_assoc($kelas_list)):
                            ?>
                            <option value="<?= $k['id'] ?>" <?= $kelas_filter==$k['id']?'selected':'' ?>><?= htmlspecialchars($k['nama_kelas']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2 flex items-center gap-2">
                            <i class="fas fa-venus-mars text-primary-500"></i>
                            Jenis Kelamin <span class="text-red-500">*</span>
                        </label>
                        <select name="jenis_kelamin" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 transition-all">
                            <option value="L">Laki-laki</option>
                            <option value="P">Perempuan</option>
                        </select>
                    </div>
                </div>
                <div class="flex gap-3 pt-4 border-t border-slate-100">
                    <button type="submit" name="tambah" class="flex-1 py-3 bg-gradient-to-r from-primary-500 to-primary-600 hover:from-primary-600 hover:to-primary-700 text-white rounded-xl text-sm font-semibold transition-all shadow-lg shadow-primary-500/30 hover:shadow-xl hover:shadow-primary-500/40 active:scale-95">
                        <i class="fas fa-save mr-2"></i>Simpan
                    </button>
                    <button type="button" onclick="closeModal('addModal')" class="flex-1 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-sm font-semibold transition-all">
                        Batal
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Edit Siswa -->
    <div id="editModal" class="modal fixed inset-0 z-50 items-center justify-center bg-slate-900/60 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-4 fade-in overflow-hidden">
            <div class="p-6 border-b border-slate-100 flex items-center justify-between bg-gradient-to-r from-amber-50 to-yellow-50">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-500 to-amber-600 flex items-center justify-center shadow-lg shadow-amber-500/30">
                        <i class="fas fa-user-edit text-white"></i>
                    </div>
                    <h3 class="text-lg font-bold text-slate-800">Edit Data Siswa</h3>
                </div>
                <button onclick="closeModal('editModal')" class="w-8 h-8 flex items-center justify-center hover:bg-slate-100 rounded-lg text-slate-400 hover:text-slate-600 transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" class="p-6 space-y-4">
                <input type="hidden" name="id" id="edit_id">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2 flex items-center gap-2">
                        <i class="fas fa-user text-amber-500"></i>
                        Nama Siswa <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="nama_siswa" id="edit_nama" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition-all">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2 flex items-center gap-2">
                            <i class="fas fa-id-card text-amber-500"></i>
                            NISN <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="nisn" id="edit_nisn" required maxlength="10" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition-all">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2 flex items-center gap-2">
                            <i class="fas fa-hashtag text-amber-500"></i>
                            NIS
                        </label>
                        <input type="text" name="nis" id="edit_nis" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition-all">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2 flex items-center gap-2">
                            <i class="fas fa-school text-amber-500"></i>
                            Kelas <span class="text-red-500">*</span>
                        </label>
                        <select name="kelas_id" id="edit_kelas" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 transition-all">
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
                        <label class="block text-sm font-semibold text-slate-700 mb-2 flex items-center gap-2">
                            <i class="fas fa-venus-mars text-amber-500"></i>
                            Jenis Kelamin <span class="text-red-500">*</span>
                        </label>
                        <select name="jenis_kelamin" id="edit_jk" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 transition-all">
                            <option value="L">Laki-laki</option>
                            <option value="P">Perempuan</option>
                        </select>
                    </div>
                </div>
                <div class="flex gap-3 pt-4 border-t border-slate-100">
                    <button type="submit" name="edit" class="flex-1 py-3 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-white rounded-xl text-sm font-semibold transition-all shadow-lg shadow-amber-500/30 hover:shadow-xl hover:shadow-amber-500/40 active:scale-95">
                        <i class="fas fa-save mr-2"></i>Update
                    </button>
                    <button type="button" onclick="closeModal('editModal')" class="flex-1 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-sm font-semibold transition-all">
                        Batal
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            sidebar.classList.toggle('hidden');
            sidebar.classList.toggle('fixed');
            sidebar.classList.toggle('inset-0');
            overlay.classList.toggle('hidden');
        }

        function openModal(id) {
            document.getElementById(id).classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
            document.body.style.overflow = '';
        }
        
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
        
        document.addEventListener('keydown', function(e) {
            if(e.key === 'Escape') {
                document.querySelectorAll('.modal.active').forEach(m => {
                    m.classList.remove('active');
                });
                document.body.style.overflow = '';
            }
        });
        
        document.querySelectorAll('.notification-enter').forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                alert.style.transition = 'opacity 0.3s';
                setTimeout(() => alert.remove(), 300);
            }, 5000);
        });
    </script>
</body>
</html>