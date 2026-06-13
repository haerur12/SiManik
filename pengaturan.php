<?php
require 'config.php';

// ============================================
// 🔐 AUTH & SECURITY
// ============================================
if(!isset($_SESSION['login'])) {
    header("Location: index.php");
    exit;
}

// Hanya admin yang bisa akses
if(isset($_SESSION['level']) && $_SESSION['level'] !== 'admin') {
    die('<div style="font-family:sans-serif;padding:40px;text-align:center;">
        <h1 style="color:#ef4444;">⛔ Akses Ditolak</h1>
        <p>Hanya administrator yang dapat mengakses halaman ini.</p>
        <a href="dashboard.php" style="color:#0ea5e9;">← Kembali ke Dashboard</a>
    </div>');
}

// CSRF Token
if(empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

function verifyCSRF($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// ============================================
// 🎯 HANDLE ACTIONS
// ============================================
$success_msg = '';
$error_msg = '';

// === PENGATURAN SEKOLAH ===
if(isset($_POST['update_sekolah']) && verifyCSRF($_POST['csrf_token'] ?? '')) {
    $fields = ['nama_sekolah', 'alamat_sekolah', 'kepala_sekolah', 'nip_kepsek', 'email_sekolah', 'tahun_ajaran', 'semester_aktif'];
    foreach($fields as $field) {
        if(isset($_POST[$field])) {
            $val = mysqli_real_escape_string($conn, trim($_POST[$field]));
            mysqli_query($conn, "UPDATE settings SET setting_value='$val' WHERE setting_key='$field'");
        }
    }
    
    // Handle logo upload
    if(isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'svg'];
        $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        if(in_array($ext, $allowed) && $_FILES['logo']['size'] <= 2 * 1024 * 1024) {
            $new_name = 'logo_' . time() . '.' . $ext;
            if(move_uploaded_file($_FILES['logo']['tmp_name'], 'assets/' . $new_name)) {
                mysqli_query($conn, "UPDATE settings SET setting_value='$new_name' WHERE setting_key='logo_sekolah'");
            }
        } else {
            $error_msg = "Logo harus JPG/PNG/SVG dan maksimal 2MB";
        }
    }
    
    if(empty($error_msg)) $success_msg = "✅ Pengaturan sekolah berhasil diperbarui!";
}

// === MANAJEMEN KELAS ===
if(isset($_POST['add_kelas']) && verifyCSRF($_POST['csrf_token'] ?? '')) {
    $nama = mysqli_real_escape_string($conn, trim($_POST['nama_kelas']));
    $wali = mysqli_real_escape_string($conn, trim($_POST['wali_kelas']));
    $sem = mysqli_real_escape_string($conn, trim($_POST['semester']));
    $ta = mysqli_real_escape_string($conn, trim($_POST['tahun_ajaran']));
    
    if(!empty($nama)) {
        mysqli_query($conn, "INSERT INTO kelas (nama_kelas, wali_kelas, semester, tahun_ajaran) VALUES ('$nama', '$wali', '$sem', '$ta')");
        $success_msg = "✅ Kelas '$nama' berhasil ditambahkan!";
    }
}

if(isset($_POST['edit_kelas']) && verifyCSRF($_POST['csrf_token'] ?? '')) {
    $id = (int)$_POST['id'];
    $nama = mysqli_real_escape_string($conn, trim($_POST['nama_kelas']));
    $wali = mysqli_real_escape_string($conn, trim($_POST['wali_kelas']));
    $sem = mysqli_real_escape_string($conn, trim($_POST['semester']));
    $ta = mysqli_real_escape_string($conn, trim($_POST['tahun_ajaran']));
    
    mysqli_query($conn, "UPDATE kelas SET nama_kelas='$nama', wali_kelas='$wali', semester='$sem', tahun_ajaran='$ta' WHERE id=$id");
    $success_msg = "✅ Kelas berhasil diperbarui!";
}

if(isset($_GET['hapus_kelas']) && verifyCSRF($_GET['token'] ?? '')) {
    $id = (int)$_GET['hapus_kelas'];
    // Cek apakah ada siswa di kelas ini
    $check = mysqli_query($conn, "SELECT COUNT(*) as total FROM siswa WHERE kelas_id=$id");
    $total = mysqli_fetch_assoc($check)['total'];
    if($total > 0) {
        $error_msg = "❌ Tidak dapat menghapus kelas yang masih memiliki $total siswa. Pindahkan siswa terlebih dahulu.";
    } else {
        mysqli_query($conn, "DELETE FROM kelas WHERE id=$id");
        $success_msg = "✅ Kelas berhasil dihapus!";
    }
}

// === MANAJEMEN MATA PELAJARAN ===
if(isset($_POST['add_mapel']) && verifyCSRF($_POST['csrf_token'] ?? '')) {
    $kode = mysqli_real_escape_string($conn, trim($_POST['kode_mapel']));
    $nama = mysqli_real_escape_string($conn, trim($_POST['nama_mapel']));
    $kategori = mysqli_real_escape_string($conn, trim($_POST['kategori']));
    $urutan = (int)$_POST['urutan'];
    
    if(!empty($kode) && !empty($nama)) {
        mysqli_query($conn, "INSERT INTO mata_pelajaran (kode_mapel, nama_mapel, kategori, urutan) VALUES ('$kode', '$nama', '$kategori', $urutan)");
        $success_msg = "✅ Mata pelajaran '$nama' berhasil ditambahkan!";
    }
}

if(isset($_POST['edit_mapel']) && verifyCSRF($_POST['csrf_token'] ?? '')) {
    $id = (int)$_POST['id'];
    $kode = mysqli_real_escape_string($conn, trim($_POST['kode_mapel']));
    $nama = mysqli_real_escape_string($conn, trim($_POST['nama_mapel']));
    $kategori = mysqli_real_escape_string($conn, trim($_POST['kategori']));
    $urutan = (int)$_POST['urutan'];
    
    mysqli_query($conn, "UPDATE mata_pelajaran SET kode_mapel='$kode', nama_mapel='$nama', kategori='$kategori', urutan=$urutan WHERE id=$id");
    $success_msg = "✅ Mata pelajaran berhasil diperbarui!";
}

if(isset($_GET['hapus_mapel']) && verifyCSRF($_GET['token'] ?? '')) {
    $id = (int)$_GET['hapus_mapel'];
    $check = mysqli_query($conn, "SELECT COUNT(*) as total FROM nilai WHERE mapel_id=$id");
    $total = mysqli_fetch_assoc($check)['total'];
    if($total > 0) {
        $error_msg = "❌ Tidak dapat menghapus mapel yang masih memiliki $total data nilai.";
    } else {
        mysqli_query($conn, "DELETE FROM mata_pelajaran WHERE id=$id");
        $success_msg = "✅ Mata pelajaran berhasil dihapus!";
    }
}

// === MANAJEMEN USER ===
if(isset($_POST['add_user']) && verifyCSRF($_POST['csrf_token'] ?? '')) {
    $username = mysqli_real_escape_string($conn, trim($_POST['username']));
    $nama = mysqli_real_escape_string($conn, trim($_POST['nama_lengkap']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $level = mysqli_real_escape_string($conn, trim($_POST['level']));
    
    $check = mysqli_query($conn, "SELECT id FROM users WHERE username='$username'");
    if(mysqli_num_rows($check) > 0) {
        $error_msg = "❌ Username '$username' sudah terdaftar!";
    } else {
        mysqli_query($conn, "INSERT INTO users (username, password, nama_lengkap, email, level, status) VALUES ('$username', '$password', '$nama', '$email', '$level', 'active')");
        $success_msg = "✅ User '$nama' berhasil ditambahkan!";
    }
}

if(isset($_POST['edit_user']) && verifyCSRF($_POST['csrf_token'] ?? '')) {
    $id = (int)$_POST['id'];
    $username = mysqli_real_escape_string($conn, trim($_POST['username']));
    $nama = mysqli_real_escape_string($conn, trim($_POST['nama_lengkap']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $level = mysqli_real_escape_string($conn, trim($_POST['level']));
    $status = mysqli_real_escape_string($conn, trim($_POST['status']));
    
    $sql = "UPDATE users SET username='$username', nama_lengkap='$nama', email='$email', level='$level', status='$status'";
    if(!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $sql .= ", password='$password'";
    }
    $sql .= " WHERE id=$id";
    mysqli_query($conn, $sql);
    $success_msg = "✅ User berhasil diperbarui!";
}

if(isset($_GET['hapus_user']) && verifyCSRF($_GET['token'] ?? '')) {
    $id = (int)$_GET['hapus_user'];
    if($id == $_SESSION['user_id']) {
        $error_msg = "❌ Tidak dapat menghapus akun Anda sendiri!";
    } else {
        mysqli_query($conn, "DELETE FROM users WHERE id=$id");
        $success_msg = "✅ User berhasil dihapus!";
    }
}

// === UBAH PASSWORD ===
if(isset($_POST['change_password']) && verifyCSRF($_POST['csrf_token'] ?? '')) {
    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];
    
    $user = mysqli_query($conn, "SELECT * FROM users WHERE id={$_SESSION['user_id']}")->fetch_assoc();
    
    if(!password_verify($current, $user['password'])) {
        $error_msg = "❌ Password lama salah!";
    } elseif(strlen($new) < 6) {
        $error_msg = "❌ Password baru minimal 6 karakter!";
    } elseif($new !== $confirm) {
        $error_msg = "❌ Konfirmasi password tidak cocok!";
    } else {
        $new_hash = password_hash($new, PASSWORD_DEFAULT);
        mysqli_query($conn, "UPDATE users SET password='$new_hash' WHERE id={$_SESSION['user_id']}");
        $success_msg = "✅ Password berhasil diubah!";
    }
}

// ============================================
// 📊 FETCH DATA
// ============================================
// Settings
$settings = [];
$settings_result = mysqli_query($conn, "SELECT setting_key, setting_value FROM settings");
while($row = mysqli_fetch_assoc($settings_result)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Kelas
$kelas_list = mysqli_query($conn, "SELECT k.*, (SELECT COUNT(*) FROM siswa WHERE kelas_id=k.id) as total_siswa FROM kelas k ORDER BY k.id ASC");

// Mata Pelajaran
$mapel_list = mysqli_query($conn, "SELECT * FROM mata_pelajaran ORDER BY urutan ASC, kode_mapel ASC");

// Users
$user_list = mysqli_query($conn, "SELECT * FROM users ORDER BY level ASC, nama_lengkap ASC");

// Stats
$stats = [
    'total_kelas' => mysqli_num_rows($kelas_list),
    'total_mapel' => mysqli_num_rows($mapel_list),
    'total_user' => mysqli_num_rows($user_list),
    'total_siswa' => mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM siswa"))['total']
];

// Current page for sidebar
$current_page = 'pengaturan.php';
$kelas_id = 1;
function isActive($page, $current) { return $page === $current ? 'active' : ''; }
$user_name = $_SESSION['nama_lengkap'] ?? 'Admin';
$user_level = ucfirst($_SESSION['level'] ?? 'admin');
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Sistem - SiManik</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] },
                    colors: {
                        primary: {
                            50: '#f0f9ff', 100: '#e0f2fe', 200: '#bae6fd', 300: '#7dd3fc',
                            400: '#38bdf8', 500: '#0ea5e9', 600: '#0284c7', 700: '#0369a1',
                            800: '#075985', 900: '#0c4a6e',
                        },
                        accent: { 400: '#facc15', 500: '#eab308', 600: '#ca8a04' }
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease-out',
                        'slide-up': 'slideUp 0.6s ease-out',
                    },
                    keyframes: {
                        fadeIn: { '0%': { opacity: '0' }, '100%': { opacity: '1' } },
                        slideUp: { '0%': { transform: 'translateY(20px)', opacity: '0' }, '100%': { transform: 'translateY(0)', opacity: '1' } }
                    }
                }
            }
        }
    </script>
    <style>
        * { -webkit-font-smoothing: antialiased; }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: linear-gradient(180deg, #0ea5e9, #0284c7); border-radius: 10px; }
        
        .glass-header {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(20px) saturate(180%);
            border-bottom: 1px solid rgba(226, 232, 240, 0.5);
        }

        .sidebar-item { position: relative; overflow: hidden; }
        .sidebar-item::before {
            content: ''; position: absolute; left: 0; top: 50%; transform: translateY(-50%);
            height: 0; width: 3px; background: linear-gradient(180deg, #0ea5e9, #0284c7);
            border-radius: 0 3px 3px 0; transition: height 0.3s ease;
        }
        .sidebar-item:hover::before { height: 60%; }
        .sidebar-item.active::before { height: 70%; }
        .sidebar-item.active { background: linear-gradient(to right, rgba(14, 165, 233, 0.08), rgba(14, 165, 233, 0.02)); color: #0369a1 !important; }
        .sidebar-item.active .w-8 { background: linear-gradient(135deg, #0ea5e9, #0284c7) !important; box-shadow: 0 4px 12px -2px rgba(14, 165, 233, 0.4); }
        .sidebar-item.active .w-8 i { color: white !important; }
        .sidebar-item.active span { color: #0369a1 !important; font-weight: 600; }

        /* Tab Styles */
        .tab-btn { transition: all 0.3s ease; position: relative; }
        .tab-btn.active {
            background: linear-gradient(135deg, #0ea5e9, #0284c7);
            color: white;
            box-shadow: 0 10px 20px -5px rgba(14, 165, 233, 0.4);
        }
        .tab-btn.active::after {
            content: ''; position: absolute; bottom: -8px; left: 50%; transform: translateX(-50%);
            width: 0; height: 0; border-left: 8px solid transparent; border-right: 8px solid transparent;
            border-top: 8px solid #0284c7;
        }

        .tab-content { display: none; animation: fadeIn 0.4s ease-out; }
        .tab-content.active { display: block; }

        /* Modal */
        .modal { display: none; }
        .modal.active { display: flex; }
        .modal-backdrop { animation: fadeIn 0.2s ease-out; }
        .modal-content { animation: slideUp 0.3s ease-out; }

        /* Table row hover */
        .table-row-hover { transition: all 0.2s ease; }
        .table-row-hover:hover { background: linear-gradient(90deg, rgba(14, 165, 233, 0.05) 0%, rgba(14, 165, 233, 0.02) 100%); }

        /* Notification */
        .notification-enter { animation: slideInRight 0.4s ease-out; }
        @keyframes slideInRight { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        /* Badge */
        .badge-pulse { animation: badgePulse 2s ease-in-out infinite; }
        @keyframes badgePulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.6; } }

        aside::-webkit-scrollbar { width: 4px; }
        aside::-webkit-scrollbar-thumb { background: rgba(14, 165, 233, 0.2); border-radius: 10px; }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 via-blue-50/30 to-slate-50 text-slate-800 font-sans antialiased">

<div class="flex h-screen overflow-hidden">
    
    <!-- Sidebar -->
    <aside id="sidebar" class="hidden md:flex flex-col w-72 bg-white text-slate-700 h-screen border-r border-slate-200/80 fixed z-30 transition-all duration-300">
        <div class="h-20 flex items-center px-6 border-b border-slate-100">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-primary-500 to-primary-600 flex items-center justify-center shadow-md shadow-primary-500/30">
                    <i class="fas fa-graduation-cap text-white text-lg"></i>
                </div>
                <div>
                    <h1 class="font-bold text-lg tracking-tight leading-none text-slate-800">SiManik</h1>
                    <p class="text-[10px] text-slate-400 font-medium tracking-wider uppercase mt-0.5">SDN CURUG 01</p>
                </div>
            </div>
        </div>

        <nav class="flex-1 overflow-y-auto py-6 px-4 space-y-1">
            <p class="px-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-3 flex items-center gap-2">
                <i class="fas fa-compass text-primary-500 text-[9px]"></i> Menu Utama
            </p>
            
            <a href="dashboard.php?kelas=1" class="sidebar-item flex items-center gap-3 px-3 py-2.5 text-slate-600 hover:text-primary-700 hover:bg-primary-50/60 rounded-xl transition-all group">
                <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center group-hover:bg-primary-100 transition-all">
                    <i class="fas fa-home w-4 text-center text-sm text-slate-500 group-hover:text-primary-600"></i>
                </div>
                <span class="font-medium text-sm">Dashboard</span>
            </a>

            <a href="import_excel.php" class="sidebar-item flex items-center gap-3 px-3 py-2.5 text-slate-600 hover:text-emerald-700 hover:bg-emerald-50/60 rounded-xl transition-all group">
                <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center group-hover:bg-emerald-100 transition-all">
                    <i class="fas fa-file-excel w-4 text-center text-sm text-slate-500 group-hover:text-emerald-600"></i>
                </div>
                <span class="font-medium text-sm">Import Data</span>
            </a>

            <a href="leger.php?kelas=1" class="sidebar-item flex items-center gap-3 px-3 py-2.5 text-slate-600 hover:text-blue-700 hover:bg-blue-50/60 rounded-xl transition-all group">
                <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center group-hover:bg-blue-100 transition-all">
                    <i class="fas fa-book-open w-4 text-center text-sm text-slate-500 group-hover:text-blue-600"></i>
                </div>
                <span class="font-medium text-sm">Leger Nilai</span>
            </a>

            <a href="grafik_nilai.php?kelas=1" class="sidebar-item flex items-center gap-3 px-3 py-2.5 text-slate-600 hover:text-purple-700 hover:bg-purple-50/60 rounded-xl transition-all group">
                <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center group-hover:bg-purple-100 transition-all">
                    <i class="fas fa-chart-pie w-4 text-center text-sm text-slate-500 group-hover:text-purple-600"></i>
                </div>
                <span class="font-medium text-sm">Analitik Grafik</span>
            </a>

            <div class="my-5 border-t border-slate-100"></div>
            
            <p class="px-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-3 flex items-center gap-2">
                <i class="fas fa-cogs text-accent-500 text-[9px]"></i> Manajemen
            </p>
            
            <a href="data_siswa.php?kelas=1" class="sidebar-item flex items-center gap-3 px-3 py-2.5 text-slate-600 hover:text-amber-700 hover:bg-amber-50/60 rounded-xl transition-all group">
                <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center group-hover:bg-amber-100 transition-all">
                    <i class="fas fa-users w-4 text-center text-sm text-slate-500 group-hover:text-amber-600"></i>
                </div>
                <span class="font-medium text-sm">Data Siswa</span>
            </a>
            
            <a href="pengaturan.php" class="sidebar-item active flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all group">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center transition-all">
                    <i class="fas fa-cog w-4 text-center text-sm"></i>
                </div>
                <span class="font-medium text-sm">Pengaturan</span>
                <span class="ml-auto px-2 py-0.5 bg-primary-100 text-primary-700 text-[9px] font-bold rounded-full">ADMIN</span>
            </a>
        </nav>

        <div class="p-4 border-t border-slate-100">
            <div class="flex items-center gap-3 p-2.5 rounded-xl hover:bg-slate-50 transition-all cursor-pointer group">
                <div class="relative">
                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($user_name) ?>&background=0ea5e9&color=fff&bold=true" alt="Avatar" class="w-10 h-10 rounded-full ring-2 ring-primary-100">
                    <div class="absolute bottom-0 right-0 w-3 h-3 bg-green-500 rounded-full border-2 border-white"></div>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-slate-800 truncate"><?= htmlspecialchars($user_name) ?></p>
                    <p class="text-xs text-slate-500 truncate"><?= $user_level ?></p>
                </div>
                <a href="logout.php" class="text-slate-400 hover:text-red-500 transition-colors p-2 hover:bg-red-50 rounded-lg" title="Logout">
                    <i class="fas fa-sign-out-alt text-sm"></i>
                </a>
            </div>
        </div>
    </aside>

    <div id="sidebarOverlay" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-20 hidden md:hidden" onclick="toggleSidebar()"></div>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col h-screen overflow-hidden relative md:ml-72">
        
        <!-- Header -->
        <header class="glass-header h-20 px-6 md:px-8 flex items-center justify-between sticky top-0 z-20">
            <div class="flex items-center gap-4">
                <button onclick="toggleSidebar()" class="md:hidden w-10 h-10 flex items-center justify-center bg-white rounded-xl border border-slate-200 text-slate-600">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <span class="px-2 py-0.5 bg-primary-100 text-primary-700 text-[10px] font-bold rounded-full uppercase tracking-wider">
                            <i class="fas fa-shield-alt mr-1"></i>Administrator
                        </span>
                    </div>
                    <h2 class="text-xl font-bold text-slate-800">Pengaturan Sistem</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Kelola konfigurasi sistem SiManik</p>
                </div>
            </div>
            <a href="dashboard.php" class="flex items-center gap-2 px-4 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 hover:border-primary-300 text-slate-700 rounded-xl text-sm font-semibold transition-all shadow-sm group">
                <i class="fas fa-arrow-left group-hover:-translate-x-1 transition-transform"></i> Kembali
            </a>
        </header>

        <!-- Scrollable Content -->
        <div class="flex-1 overflow-y-auto p-6 md:p-8 pb-20">
            
            <!-- Notifications -->
            <?php if($success_msg): ?>
            <div class="mb-6 p-5 bg-gradient-to-r from-emerald-50 to-green-50 border border-emerald-200 text-emerald-700 rounded-2xl flex items-center gap-4 notification-enter shadow-lg shadow-emerald-500/10">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-emerald-400 to-emerald-600 flex items-center justify-center flex-shrink-0 shadow-lg shadow-emerald-500/30">
                    <i class="fas fa-check-circle text-white text-xl"></i>
                </div>
                <div class="flex-1"><p class="font-bold text-emerald-900">Berhasil!</p><span class="text-sm font-medium"><?= $success_msg ?></span></div>
                <button onclick="this.parentElement.remove()" class="text-emerald-400 hover:text-emerald-600 p-2 hover:bg-emerald-100 rounded-lg"><i class="fas fa-times"></i></button>
            </div>
            <?php endif; ?>
            <?php if($error_msg): ?>
            <div class="mb-6 p-5 bg-gradient-to-r from-red-50 to-rose-50 border border-red-200 text-red-700 rounded-2xl flex items-center gap-4 notification-enter shadow-lg shadow-red-500/10">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-red-400 to-red-600 flex items-center justify-center flex-shrink-0 shadow-lg shadow-red-500/30">
                    <i class="fas fa-exclamation-circle text-white text-xl"></i>
                </div>
                <div class="flex-1"><p class="font-bold text-red-900">Gagal!</p><span class="text-sm font-medium"><?= $error_msg ?></span></div>
                <button onclick="this.parentElement.remove()" class="text-red-400 hover:text-red-600 p-2 hover:bg-red-100 rounded-lg"><i class="fas fa-times"></i></button>
            </div>
            <?php endif; ?>

            <!-- Stats Overview -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6 animate-slide-up">
                <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary-500 to-primary-600 flex items-center justify-center shadow-md shadow-primary-500/30">
                            <i class="fas fa-school text-white"></i>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500">Total Kelas</p>
                            <p class="text-xl font-bold text-slate-800"><?= $stats['total_kelas'] ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-500 to-emerald-600 flex items-center justify-center shadow-md shadow-emerald-500/30">
                            <i class="fas fa-book text-white"></i>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500">Mata Pelajaran</p>
                            <p class="text-xl font-bold text-slate-800"><?= $stats['total_mapel'] ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-500 to-amber-600 flex items-center justify-center shadow-md shadow-amber-500/30">
                            <i class="fas fa-users-cog text-white"></i>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500">Total User</p>
                            <p class="text-xl font-bold text-slate-800"><?= $stats['total_user'] ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-purple-500 to-purple-600 flex items-center justify-center shadow-md shadow-purple-500/30">
                            <i class="fas fa-user-graduate text-white"></i>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500">Total Siswa</p>
                            <p class="text-xl font-bold text-slate-800"><?= $stats['total_siswa'] ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab Navigation -->
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden animate-slide-up" style="animation-delay: 0.1s;">
                <div class="p-4 border-b border-slate-100 bg-gradient-to-r from-slate-50 to-white">
                    <div class="flex flex-wrap gap-2">
                        <button onclick="switchTab('sekolah')" class="tab-btn active px-4 py-2.5 rounded-xl text-sm font-semibold flex items-center gap-2 bg-slate-100 text-slate-600">
                            <i class="fas fa-school"></i> <span class="hidden sm:inline">Pengaturan Sekolah</span><span class="sm:hidden">Sekolah</span>
                        </button>
                        <button onclick="switchTab('kelas')" class="tab-btn px-4 py-2.5 rounded-xl text-sm font-semibold flex items-center gap-2 bg-slate-100 text-slate-600">
                            <i class="fas fa-chalkboard"></i> <span class="hidden sm:inline">Manajemen Kelas</span><span class="sm:hidden">Kelas</span>
                            <span class="px-1.5 py-0.5 bg-primary-100 text-primary-700 text-[10px] font-bold rounded-full"><?= $stats['total_kelas'] ?></span>
                        </button>
                        <button onclick="switchTab('mapel')" class="tab-btn px-4 py-2.5 rounded-xl text-sm font-semibold flex items-center gap-2 bg-slate-100 text-slate-600">
                            <i class="fas fa-book-open"></i> <span class="hidden sm:inline">Mata Pelajaran</span><span class="sm:hidden">Mapel</span>
                            <span class="px-1.5 py-0.5 bg-emerald-100 text-emerald-700 text-[10px] font-bold rounded-full"><?= $stats['total_mapel'] ?></span>
                        </button>
                        <button onclick="switchTab('user')" class="tab-btn px-4 py-2.5 rounded-xl text-sm font-semibold flex items-center gap-2 bg-slate-100 text-slate-600">
                            <i class="fas fa-users-cog"></i> <span class="hidden sm:inline">Manajemen User</span><span class="sm:hidden">User</span>
                            <span class="px-1.5 py-0.5 bg-amber-100 text-amber-700 text-[10px] font-bold rounded-full"><?= $stats['total_user'] ?></span>
                        </button>
                        <button onclick="switchTab('password')" class="tab-btn px-4 py-2.5 rounded-xl text-sm font-semibold flex items-center gap-2 bg-slate-100 text-slate-600">
                            <i class="fas fa-key"></i> <span class="hidden sm:inline">Ubah Password</span><span class="sm:hidden">Password</span>
                        </button>
                    </div>
                </div>

                <div class="p-6 md:p-8">
                    
                    <!-- TAB 1: PENGATURAN SEKOLAH -->
                    <div id="tab-sekolah" class="tab-content active">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-primary-500 to-primary-600 flex items-center justify-center shadow-lg shadow-primary-500/30">
                                <i class="fas fa-school text-white text-lg"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-slate-800 text-lg">Pengaturan Sekolah</h3>
                                <p class="text-xs text-slate-500">Informasi identitas sekolah dan tahun ajaran</p>
                            </div>
                        </div>

                        <form method="POST" enctype="multipart/form-data" class="space-y-5">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 flex items-center gap-2">
                                        <i class="fas fa-building text-primary-500"></i> Nama Sekolah
                                    </label>
                                    <input type="text" name="nama_sekolah" value="<?= htmlspecialchars($settings['nama_sekolah'] ?? '') ?>" class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-200 rounded-xl text-sm focus:outline-none focus:border-primary-500 focus:bg-white transition-all" required>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 flex items-center gap-2">
                                        <i class="fas fa-envelope text-primary-500"></i> Email Sekolah
                                    </label>
                                    <input type="email" name="email_sekolah" value="<?= htmlspecialchars($settings['email_sekolah'] ?? '') ?>" class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-200 rounded-xl text-sm focus:outline-none focus:border-primary-500 focus:bg-white transition-all">
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 flex items-center gap-2">
                                    <i class="fas fa-map-marker-alt text-primary-500"></i> Alamat Sekolah
                                </label>
                                <textarea name="alamat_sekolah" rows="2" class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-200 rounded-xl text-sm focus:outline-none focus:border-primary-500 focus:bg-white transition-all"><?= htmlspecialchars($settings['alamat_sekolah'] ?? '') ?></textarea>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 flex items-center gap-2">
                                        <i class="fas fa-user-tie text-primary-500"></i> Kepala Sekolah
                                    </label>
                                    <input type="text" name="kepala_sekolah" value="<?= htmlspecialchars($settings['kepala_sekolah'] ?? '') ?>" class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-200 rounded-xl text-sm focus:outline-none focus:border-primary-500 focus:bg-white transition-all">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 flex items-center gap-2">
                                        <i class="fas fa-id-card text-primary-500"></i> NIP Kepala Sekolah
                                    </label>
                                    <input type="text" name="nip_kepsek" value="<?= htmlspecialchars($settings['nip_kepsek'] ?? '') ?>" class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-200 rounded-xl text-sm focus:outline-none focus:border-primary-500 focus:bg-white transition-all">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 flex items-center gap-2">
                                        <i class="fas fa-calendar-alt text-primary-500"></i> Tahun Ajaran
                                    </label>
                                    <input type="text" name="tahun_ajaran" value="<?= htmlspecialchars($settings['tahun_ajaran'] ?? '') ?>" placeholder="2025/2026" class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-200 rounded-xl text-sm focus:outline-none focus:border-primary-500 focus:bg-white transition-all">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 flex items-center gap-2">
                                        <i class="fas fa-semester text-primary-500"></i> Semester Aktif
                                    </label>
                                    <select name="semester_aktif" class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-200 rounded-xl text-sm focus:outline-none focus:border-primary-500 transition-all">
                                        <option value="Ganjil" <?= ($settings['semester_aktif'] ?? '') == 'Ganjil' ? 'selected' : '' ?>>Ganjil</option>
                                        <option value="Genap" <?= ($settings['semester_aktif'] ?? '') == 'Genap' ? 'selected' : '' ?>>Genap</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 flex items-center gap-2">
                                    <i class="fas fa-image text-primary-500"></i> Logo Sekolah
                                </label>
                                <div class="flex items-center gap-4">
                                    <div class="w-20 h-20 rounded-xl bg-slate-100 border-2 border-dashed border-slate-300 flex items-center justify-center overflow-hidden">
                                        <?php 
                                        $logo = $settings['logo_sekolah'] ?? '';
                                        if($logo && file_exists('assets/' . $logo)): 
                                        ?>
                                            <img src="assets/<?= $logo ?>" alt="Logo" class="w-full h-full object-contain">
                                        <?php else: ?>
                                            <i class="fas fa-image text-3xl text-slate-300"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-1">
                                        <input type="file" name="logo" accept="image/*" class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100">
                                        <p class="text-[11px] text-slate-400 mt-1">JPG, PNG, atau SVG. Maks 2MB.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-4 border-t border-slate-100 flex justify-end">
                                <button type="submit" name="update_sekolah" class="px-6 py-3 bg-gradient-to-r from-primary-500 to-primary-600 hover:from-primary-600 hover:to-primary-700 text-white rounded-xl text-sm font-semibold shadow-lg shadow-primary-500/30 hover:shadow-xl transition-all active:scale-95">
                                    <i class="fas fa-save mr-2"></i>Simpan Pengaturan
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- TAB 2: MANAJEMEN KELAS -->
                    <div id="tab-kelas" class="tab-content">
                        <div class="flex items-center justify-between mb-6">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-primary-500 to-primary-600 flex items-center justify-center shadow-lg shadow-primary-500/30">
                                    <i class="fas fa-chalkboard text-white text-lg"></i>
                                </div>
                                <div>
                                    <h3 class="font-bold text-slate-800 text-lg">Manajemen Kelas</h3>
                                    <p class="text-xs text-slate-500">Kelola data kelas dan wali kelas</p>
                                </div>
                            </div>
                            <button onclick="openModal('modalAddKelas')" class="px-5 py-2.5 bg-gradient-to-r from-accent-500 to-accent-600 hover:from-accent-600 hover:to-accent-700 text-white rounded-xl text-sm font-semibold shadow-lg shadow-accent-500/30 transition-all active:scale-95">
                                <i class="fas fa-plus mr-2"></i>Tambah Kelas
                            </button>
                        </div>

                        <div class="overflow-x-auto rounded-xl border border-slate-200">
                            <table class="w-full text-sm">
                                <thead class="bg-slate-50 text-xs text-slate-600 uppercase">
                                    <tr>
                                        <th class="px-4 py-3 text-left">No</th>
                                        <th class="px-4 py-3 text-left">Nama Kelas</th>
                                        <th class="px-4 py-3 text-left">Wali Kelas</th>
                                        <th class="px-4 py-3 text-center">Siswa</th>
                                        <th class="px-4 py-3 text-left">Semester</th>
                                        <th class="px-4 py-3 text-left">Tahun Ajaran</th>
                                        <th class="px-4 py-3 text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <?php 
                                    $no = 1;
                                    mysqli_data_seek($kelas_list, 0);
                                    while($k = mysqli_fetch_assoc($kelas_list)): 
                                    ?>
                                    <tr class="table-row-hover">
                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-slate-100 text-slate-600 text-xs font-bold"><?= $no++ ?></span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="font-semibold text-slate-800"><?= htmlspecialchars($k['nama_kelas']) ?></span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-2">
                                                <img src="https://ui-avatars.com/api/?name=<?= urlencode($k['wali_kelas'] ?? 'Wali') ?>&background=0ea5e9&color=fff&size=32" class="w-7 h-7 rounded-full">
                                                <span class="text-slate-600"><?= htmlspecialchars($k['wali_kelas'] ?? '-') ?></span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <span class="px-2.5 py-1 bg-primary-50 text-primary-700 rounded-lg text-xs font-bold"><?= $k['total_siswa'] ?> siswa</span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="px-2.5 py-1 bg-blue-50 text-blue-700 rounded-lg text-xs font-semibold"><?= htmlspecialchars($k['semester'] ?? 'Ganjil') ?></span>
                                        </td>
                                        <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($k['tahun_ajaran'] ?? '-') ?></td>
                                        <td class="px-4 py-3 text-center">
                                            <div class="flex items-center justify-center gap-2">
                                                <button onclick='editKelas(<?= json_encode($k) ?>)' class="w-8 h-8 flex items-center justify-center bg-amber-50 hover:bg-amber-100 text-amber-600 rounded-lg transition-all" title="Edit">
                                                    <i class="fas fa-pen text-xs"></i>
                                                </button>
                                                <a href="?hapus_kelas=<?= $k['id'] ?>&token=<?= $csrf_token ?>" onclick="return confirm('Yakin ingin menghapus kelas <?= htmlspecialchars($k['nama_kelas']) ?>?')" class="w-8 h-8 flex items-center justify-center bg-red-50 hover:bg-red-100 text-red-600 rounded-lg transition-all" title="Hapus">
                                                    <i class="fas fa-trash text-xs"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                    <?php if(mysqli_num_rows($kelas_list) == 0): ?>
                                    <tr><td colspan="7" class="px-4 py-12 text-center text-slate-400"><i class="fas fa-inbox text-3xl mb-2 block"></i>Belum ada data kelas</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- TAB 3: MATA PELAJARAN -->
                    <div id="tab-mapel" class="tab-content">
                        <div class="flex items-center justify-between mb-6">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-emerald-500 to-emerald-600 flex items-center justify-center shadow-lg shadow-emerald-500/30">
                                    <i class="fas fa-book-open text-white text-lg"></i>
                                </div>
                                <div>
                                    <h3 class="font-bold text-slate-800 text-lg">Manajemen Mata Pelajaran</h3>
                                    <p class="text-xs text-slate-500">Kelola daftar mata pelajaran</p>
                                </div>
                            </div>
                            <button onclick="openModal('modalAddMapel')" class="px-5 py-2.5 bg-gradient-to-r from-accent-500 to-accent-600 hover:from-accent-600 hover:to-accent-700 text-white rounded-xl text-sm font-semibold shadow-lg shadow-accent-500/30 transition-all active:scale-95">
                                <i class="fas fa-plus mr-2"></i>Tambah Mapel
                            </button>
                        </div>

                        <div class="overflow-x-auto rounded-xl border border-slate-200">
                            <table class="w-full text-sm">
                                <thead class="bg-slate-50 text-xs text-slate-600 uppercase">
                                    <tr>
                                        <th class="px-4 py-3 text-left">Urutan</th>
                                        <th class="px-4 py-3 text-left">Kode</th>
                                        <th class="px-4 py-3 text-left">Nama Mata Pelajaran</th>
                                        <th class="px-4 py-3 text-center">Kategori</th>
                                        <th class="px-4 py-3 text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <?php 
                                    mysqli_data_seek($mapel_list, 0);
                                    while($m = mysqli_fetch_assoc($mapel_list)): 
                                        $katColor = match($m['kategori'] ?? 'Wajib') {
                                            'Wajib' => 'bg-primary-50 text-primary-700 border-primary-200',
                                            'Mulok' => 'bg-purple-50 text-purple-700 border-purple-200',
                                            'Ekstra' => 'bg-amber-50 text-amber-700 border-amber-200',
                                            default => 'bg-slate-50 text-slate-700'
                                        };
                                    ?>
                                    <tr class="table-row-hover">
                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-slate-100 text-slate-600 text-xs font-bold"><?= $m['urutan'] ?? 0 ?></span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="font-mono text-xs bg-slate-100 px-2 py-1 rounded"><?= htmlspecialchars($m['kode_mapel']) ?></span>
                                        </td>
                                        <td class="px-4 py-3 font-semibold text-slate-800"><?= htmlspecialchars($m['nama_mapel']) ?></td>
                                        <td class="px-4 py-3 text-center">
                                            <span class="px-2.5 py-1 <?= $katColor ?> rounded-lg text-xs font-semibold border"><?= htmlspecialchars($m['kategori'] ?? 'Wajib') ?></span>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <div class="flex items-center justify-center gap-2">
                                                <button onclick='editMapel(<?= json_encode($m) ?>)' class="w-8 h-8 flex items-center justify-center bg-amber-50 hover:bg-amber-100 text-amber-600 rounded-lg transition-all"><i class="fas fa-pen text-xs"></i></button>
                                                <a href="?hapus_mapel=<?= $m['id'] ?>&token=<?= $csrf_token ?>" onclick="return confirm('Yakin ingin menghapus mapel ini?')" class="w-8 h-8 flex items-center justify-center bg-red-50 hover:bg-red-100 text-red-600 rounded-lg transition-all"><i class="fas fa-trash text-xs"></i></a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- TAB 4: MANAJEMEN USER -->
                    <div id="tab-user" class="tab-content">
                        <div class="flex items-center justify-between mb-6">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-amber-500 to-amber-600 flex items-center justify-center shadow-lg shadow-amber-500/30">
                                    <i class="fas fa-users-cog text-white text-lg"></i>
                                </div>
                                <div>
                                    <h3 class="font-bold text-slate-800 text-lg">Manajemen User</h3>
                                    <p class="text-xs text-slate-500">Kelola akun pengguna sistem</p>
                                </div>
                            </div>
                            <button onclick="openModal('modalAddUser')" class="px-5 py-2.5 bg-gradient-to-r from-accent-500 to-accent-600 hover:from-accent-600 hover:to-accent-700 text-white rounded-xl text-sm font-semibold shadow-lg shadow-accent-500/30 transition-all active:scale-95">
                                <i class="fas fa-plus mr-2"></i>Tambah User
                            </button>
                        </div>

                        <div class="overflow-x-auto rounded-xl border border-slate-200">
                            <table class="w-full text-sm">
                                <thead class="bg-slate-50 text-xs text-slate-600 uppercase">
                                    <tr>
                                        <th class="px-4 py-3 text-left">User</th>
                                        <th class="px-4 py-3 text-left">Username</th>
                                        <th class="px-4 py-3 text-left">Email</th>
                                        <th class="px-4 py-3 text-center">Level</th>
                                        <th class="px-4 py-3 text-center">Status</th>
                                        <th class="px-4 py-3 text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <?php 
                                    mysqli_data_seek($user_list, 0);
                                    while($u = mysqli_fetch_assoc($user_list)): 
                                        $levelColor = match($u['level']) {
                                            'admin' => 'bg-red-50 text-red-700 border-red-200',
                                            'guru' => 'bg-blue-50 text-blue-700 border-blue-200',
                                            'wali_kelas' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                            default => 'bg-slate-50 text-slate-700'
                                        };
                                        $statusColor = ($u['status'] ?? 'active') == 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500';
                                        $is_self = $u['id'] == $_SESSION['user_id'];
                                    ?>
                                    <tr class="table-row-hover <?= $is_self ? 'bg-primary-50/30' : '' ?>">
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-3">
                                                <img src="https://ui-avatars.com/api/?name=<?= urlencode($u['nama_lengkap']) ?>&background=0ea5e9&color=fff&size=40" class="w-9 h-9 rounded-full ring-2 ring-primary-100">
                                                <div>
                                                    <p class="font-semibold text-slate-800"><?= htmlspecialchars($u['nama_lengkap']) ?>
                                                        <?php if($is_self): ?><span class="ml-1 text-[10px] text-primary-600">(Anda)</span><?php endif; ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 font-mono text-xs text-slate-600"><?= htmlspecialchars($u['username']) ?></td>
                                        <td class="px-4 py-3 text-slate-600 text-xs"><?= htmlspecialchars($u['email'] ?? '-') ?></td>
                                        <td class="px-4 py-3 text-center">
                                            <span class="px-2.5 py-1 <?= $levelColor ?> rounded-lg text-xs font-semibold border uppercase"><?= htmlspecialchars($u['level']) ?></span>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <span class="px-2.5 py-1 <?= $statusColor ?> rounded-lg text-xs font-semibold inline-flex items-center gap-1">
                                                <span class="w-1.5 h-1.5 rounded-full <?= ($u['status'] ?? 'active') == 'active' ? 'bg-emerald-500' : 'bg-slate-400' ?>"></span>
                                                <?= ucfirst($u['status'] ?? 'active') ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <div class="flex items-center justify-center gap-2">
                                                <button onclick='editUser(<?= json_encode($u) ?>)' class="w-8 h-8 flex items-center justify-center bg-amber-50 hover:bg-amber-100 text-amber-600 rounded-lg transition-all"><i class="fas fa-pen text-xs"></i></button>
                                                <?php if(!$is_self): ?>
                                                <a href="?hapus_user=<?= $u['id'] ?>&token=<?= $csrf_token ?>" onclick="return confirm('Yakin ingin menghapus user ini?')" class="w-8 h-8 flex items-center justify-center bg-red-50 hover:bg-red-100 text-red-600 rounded-lg transition-all"><i class="fas fa-trash text-xs"></i></a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- TAB 5: UBAH PASSWORD -->
                    <div id="tab-password" class="tab-content">
                        <div class="max-w-xl mx-auto">
                            <div class="flex items-center gap-3 mb-6">
                                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-red-500 to-red-600 flex items-center justify-center shadow-lg shadow-red-500/30">
                                    <i class="fas fa-key text-white text-lg"></i>
                                </div>
                                <div>
                                    <h3 class="font-bold text-slate-800 text-lg">Ubah Password</h3>
                                    <p class="text-xs text-slate-500">Ganti password akun Anda</p>
                                </div>
                            </div>

                            <div class="bg-gradient-to-br from-amber-50 to-yellow-50 border border-amber-200 rounded-xl p-4 mb-6 flex items-start gap-3">
                                <i class="fas fa-shield-alt text-amber-600 mt-0.5"></i>
                                <div class="text-xs text-amber-900">
                                    <p class="font-bold mb-1">Tips Keamanan:</p>
                                    <ul class="list-disc list-inside space-y-0.5 text-amber-800">
                                        <li>Gunakan minimal 6 karakter</li>
                                        <li>Kombinasikan huruf, angka, dan simbol</li>
                                        <li>Jangan gunakan password yang sama dengan akun lain</li>
                                    </ul>
                                </div>
                            </div>

                            <form method="POST" class="space-y-5 bg-white p-6 rounded-xl border border-slate-200">
                                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 flex items-center gap-2">
                                        <i class="fas fa-lock text-slate-400"></i> Password Lama
                                    </label>
                                    <input type="password" name="current_password" required class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-200 rounded-xl text-sm focus:outline-none focus:border-primary-500 focus:bg-white transition-all">
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 flex items-center gap-2">
                                        <i class="fas fa-key text-slate-400"></i> Password Baru
                                    </label>
                                    <input type="password" name="new_password" required minlength="6" class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-200 rounded-xl text-sm focus:outline-none focus:border-primary-500 focus:bg-white transition-all">
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 flex items-center gap-2">
                                        <i class="fas fa-check-circle text-slate-400"></i> Konfirmasi Password Baru
                                    </label>
                                    <input type="password" name="confirm_password" required minlength="6" class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-200 rounded-xl text-sm focus:outline-none focus:border-primary-500 focus:bg-white transition-all">
                                </div>

                                <div class="pt-4 border-t border-slate-100">
                                    <button type="submit" name="change_password" class="w-full px-6 py-3 bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white rounded-xl text-sm font-semibold shadow-lg shadow-red-500/30 transition-all active:scale-95">
                                        <i class="fas fa-save mr-2"></i>Ubah Password
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </main>
</div>

<!-- ============================================ -->
<!-- MODALS -->
<!-- ============================================ -->

<!-- Modal: Tambah Kelas -->
<div id="modalAddKelas" class="modal fixed inset-0 z-50 items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
    <div class="modal-content bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex items-center justify-between bg-gradient-to-r from-primary-50 to-blue-50">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary-500 to-primary-600 flex items-center justify-center shadow-md">
                    <i class="fas fa-plus text-white"></i>
                </div>
                <h3 class="text-lg font-bold text-slate-800">Tambah Kelas Baru</h3>
            </div>
            <button onclick="closeModal('modalAddKelas')" class="w-8 h-8 flex items-center justify-center hover:bg-slate-100 rounded-lg text-slate-400"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Nama Kelas <span class="text-red-500">*</span></label>
                <input type="text" name="nama_kelas" required placeholder="Contoh: Kelas 1A" class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-200 rounded-xl text-sm focus:outline-none focus:border-primary-500 transition-all">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Wali Kelas</label>
                <input type="text" name="wali_kelas" placeholder="Nama wali kelas" class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-200 rounded-xl text-sm focus:outline-none focus:border-primary-500 transition-all">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Semester</label>
                    <select name="semester" class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-200 rounded-xl text-sm focus:outline-none focus:border-primary-500 transition-all">
                        <option value="Ganjil">Ganjil</option>
                        <option value="Genap">Genap</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Tahun Ajaran</label>
                    <input type="text" name="tahun_ajaran" value="<?= htmlspecialchars($settings['tahun_ajaran'] ?? '2025/2026') ?>" class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-200 rounded-xl text-sm focus:outline-none focus:border-primary-500 transition-all">
                </div>
            </div>
            <div class="flex gap-3 pt-4 border-t border-slate-100">
                <button type="submit" name="add_kelas" class="flex-1 py-3 bg-gradient-to-r from-primary-500 to-primary-600 text-white rounded-xl text-sm font-semibold shadow-lg shadow-primary-500/30"><i class="fas fa-save mr-2"></i>Simpan</button>
                <button type="button" onclick="closeModal('modalAddKelas')" class="flex-1 py-3 bg-slate-100 text-slate-700 rounded-xl text-sm font-semibold">Batal</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Kelas -->
<div id="modalEditKelas" class="modal fixed inset-0 z-50 items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
    <div class="modal-content bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex items-center justify-between bg-gradient-to-r from-amber-50 to-yellow-50">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-500 to-amber-600 flex items-center justify-center shadow-md">
                    <i class="fas fa-pen text-white"></i>
                </div>
                <h3 class="text-lg font-bold text-slate-800">Edit Kelas</h3>
            </div>
            <button onclick="closeModal('modalEditKelas')" class="w-8 h-8 flex items-center justify-center hover:bg-slate-100 rounded-lg text-slate-400"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <input type="hidden" name="id" id="edit_kelas_id">
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Nama Kelas <span class="text-red-500">*</span></label>
                <input type="text" name="nama_kelas" id="edit_kelas_nama" required class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-200 rounded-xl text-sm focus:outline-none focus:border-amber-500 transition-all">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Wali Kelas</label>
                <input type="text" name="wali_kelas" id="edit_kelas_wali" class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-200 rounded-xl text-sm focus:outline-none focus:border-amber-500 transition-all">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Semester</label>
                    <select name="semester" id="edit_kelas_semester" class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-200 rounded-xl text-sm focus:outline-none focus:border-amber-500 transition-all">
                        <option value="Ganjil">Ganjil</option>
                        <option value="Genap">Genap</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Tahun Ajaran</label>
                    <input type="text" name="tahun_ajaran" id="edit_kelas_ta" class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-200 rounded-xl text-sm focus:outline-none focus:border-amber-500 transition-all">
                </div>
            </div>
            <div class="flex gap-3 pt-4 border-t border-slate-100">
                <button type="submit" name="edit_kelas" class="flex-1 py-3 bg-gradient-to-r from-amber-500 to-amber-600 text-white rounded-xl text-sm font-semibold shadow-lg shadow-amber-500/30"><i class="fas fa-save mr-2"></i>Update</button>
                <button type="button" onclick="closeModal('modalEditKelas')" class="flex-1 py-3 bg-slate-100 text-slate-700 rounded-xl text-sm font-semibold">Batal</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Tambah Mapel -->
<div id="modalAddMapel" class="modal fixed inset-0 z-50 items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
    <div class="modal-content bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex items-center justify-between bg-gradient-to-r from-emerald-50 to-green-50">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-500 to-emerald-600 flex items-center justify-center shadow-md">
                    <i class="fas fa-plus text-white"></i>
                </div>
                <h3 class="text-lg font-bold text-slate-800">Tambah Mata Pelajaran</h3>
            </div>
            <button onclick="closeModal('modalAddMapel')" class="w-8 h-8 flex items-center justify-center hover:bg-slate-100 rounded-lg text-slate-400"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Kode Mapel <span class="text-red-500">*</span></label>
                    <input type="text" name="kode_mapel" required placeholder="BI" maxlength="10" class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-200 rounded-xl text-sm font-mono focus:outline-none focus:border-emerald-500 transition-all">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Urutan</label>
                    <input type="number" name="urutan" value="0" min="0" class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-200 rounded-xl text-sm focus:outline-none focus:border-emerald-500 transition-all">
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Nama Mata Pelajaran <span class="text-red-500">*</span></label>
                <input type="text" name="nama_mapel" required placeholder="Bahasa Indonesia" class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-200 rounded-xl text-sm focus:outline-none focus:border-emerald-500 transition-all">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Kategori</label>
                <select name="kategori" class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-200 rounded-xl text-sm focus:outline-none focus:border-emerald-500 transition-all">
                    <option value="Wajib">Wajib</option>
                    <option value="Mulok">Mulok (Muatan Lokal)</option>
                    <option value="Ekstra">Ekstrakurikuler</option>
                </select>
            </div>
            <div class="flex gap-3 pt-4 border-t border-slate-100">
                <button type="submit" name="add_mapel" class="flex-1 py-3 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white rounded-xl text-sm font-semibold shadow-lg shadow-emerald-500/30"><i class="fas fa-save mr-2"></i>Simpan</button>
                <button type="button" onclick="closeModal('modalAddMapel')" class="flex-1 py-3 bg-slate-100 text-slate-700 rounded-xl text-sm font-semibold">Batal</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Mapel -->
<div id="modalEditMapel" class="modal fixed inset-0 z-50 items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
    <div class="modal-content bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex items-center justify-between bg-gradient-to-r from-amber-50 to-yellow-50">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-500 to-amber-600 flex items-center justify-center shadow-md">
                    <i class="fas fa-pen text-white"></i>
                </div>
                <h3 class="text-lg font-bold text-slate-800">Edit Mata Pelajaran</h3>
            </div>
            <button onclick="closeModal('modalEditMapel')" class="w-8 h-8 flex items-center justify-center hover:bg-slate-100 rounded-lg text-slate-400"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <input type="hidden" name="id" id="edit_mapel_id">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Kode Mapel</label>
                    <input type="text" name="kode_mapel" id="edit_mapel_kode" required class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-200 rounded-xl text-sm font-mono focus:outline-none focus:border-amber-500 transition-all">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Urutan</label>
                    <input type="number" name="urutan" id="edit_mapel_urutan" min="0" class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-200 rounded-xl text-sm focus:outline-none focus:border-amber-500 transition-all">
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Nama Mata Pelajaran</label>
                <input type="text" name="nama_mapel" id="edit_mapel_nama" required class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-200 rounded-xl text-sm focus:outline-none focus:border-amber-500 transition-all">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Kategori</label>
                <select name="kategori" id="edit_mapel_kategori" class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-200 rounded-xl text-sm focus:outline-none focus:border-amber-500 transition-all">
                    <option value="Wajib">Wajib</option>
                    <option value="Mulok">Mulok</option>
                    <option value="Ekstra">Ekstrakurikuler</option>
                </select>
            </div>
            <div class="flex gap-3 pt-4 border-t border-slate-100">
                <button type="submit" name="edit_mapel" class="flex-1 py-3 bg-gradient-to-r from-amber-500 to-amber-600 text-white rounded-xl text-sm font-semibold shadow-lg shadow-amber-500/30"><i class="fas fa-save mr-2"></i>Update</button>
                <button type="button" onclick="closeModal('modalEditMapel')" class="flex-1 py-3 bg-slate-100 text-slate-700 rounded-xl text-sm font-semibold">Batal</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Tambah User -->
<div id="modalAddUser" class="modal fixed inset-0 z-50 items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
    <div class="modal-content bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex items-center justify-between bg-gradient-to-r from-amber-50 to-yellow-50">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-500 to-amber-600 flex items-center justify-center shadow-md">
                    <i class="fas fa-user-plus text-white"></i>
                </div>
                <h3 class="text-lg font-bold text-slate-800">Tambah User Baru</h3>
            </div>
            <button onclick="closeModal('modalAddUser')" class="w-8 h-8 flex items-center justify-center hover:bg-slate-100 rounded-lg text-slate-400"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Nama Lengkap <span class="text-red-500">*</span></label>
                    <input type="text" name="nama_lengkap" required class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-200 rounded-xl text-sm focus:outline-none focus:border-amber-500 transition-all">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Username <span class="text-red-500">*</span></label>
                    <input type="text" name="username" required class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-200 rounded-xl text-sm font-mono focus:outline-none focus:border-amber-500 transition-all">
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Email</label>
                <input type="email" name="email" class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-200 rounded-xl text-sm focus:outline-none focus:border-amber-500 transition-all">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Password <span class="text-red-500">*</span></label>
                    <input type="password" name="password" required minlength="6" class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-200 rounded-xl text-sm focus:outline-none focus:border-amber-500 transition-all">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Level <span class="text-red-500">*</span></label>
                    <select name="level" required class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-200 rounded-xl text-sm focus:outline-none focus:border-amber-500 transition-all">
                        <option value="admin">Admin</option>
                        <option value="guru">Guru</option>
                        <option value="wali_kelas">Wali Kelas</option>
                    </select>
                </div>
            </div>
            <div class="flex gap-3 pt-4 border-t border-slate-100">
                <button type="submit" name="add_user" class="flex-1 py-3 bg-gradient-to-r from-amber-500 to-amber-600 text-white rounded-xl text-sm font-semibold shadow-lg shadow-amber-500/30"><i class="fas fa-save mr-2"></i>Simpan</button>
                <button type="button" onclick="closeModal('modalAddUser')" class="flex-1 py-3 bg-slate-100 text-slate-700 rounded-xl text-sm font-semibold">Batal</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit User -->
<div id="modalEditUser" class="modal fixed inset-0 z-50 items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
    <div class="modal-content bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex items-center justify-between bg-gradient-to-r from-amber-50 to-yellow-50">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-500 to-amber-600 flex items-center justify-center shadow-md">
                    <i class="fas fa-user-edit text-white"></i>
                </div>
                <h3 class="text-lg font-bold text-slate-800">Edit User</h3>
            </div>
            <button onclick="closeModal('modalEditUser')" class="w-8 h-8 flex items-center justify-center hover:bg-slate-100 rounded-lg text-slate-400"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <input type="hidden" name="id" id="edit_user_id">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Nama Lengkap</label>
                    <input type="text" name="nama_lengkap" id="edit_user_nama" required class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-200 rounded-xl text-sm focus:outline-none focus:border-amber-500 transition-all">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Username</label>
                    <input type="text" name="username" id="edit_user_username" required class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-200 rounded-xl text-sm font-mono focus:outline-none focus:border-amber-500 transition-all">
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Email</label>
                <input type="email" name="email" id="edit_user_email" class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-200 rounded-xl text-sm focus:outline-none focus:border-amber-500 transition-all">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Password Baru <span class="text-slate-400 normal-case font-normal text-[10px]">(kosongkan jika tidak diubah)</span></label>
                <input type="password" name="password" minlength="6" class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-200 rounded-xl text-sm focus:outline-none focus:border-amber-500 transition-all">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Level</label>
                    <select name="level" id="edit_user_level" required class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-200 rounded-xl text-sm focus:outline-none focus:border-amber-500 transition-all">
                        <option value="admin">Admin</option>
                        <option value="guru">Guru</option>
                        <option value="wali_kelas">Wali Kelas</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Status</label>
                    <select name="status" id="edit_user_status" class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-200 rounded-xl text-sm focus:outline-none focus:border-amber-500 transition-all">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="flex gap-3 pt-4 border-t border-slate-100">
                <button type="submit" name="edit_user" class="flex-1 py-3 bg-gradient-to-r from-amber-500 to-amber-600 text-white rounded-xl text-sm font-semibold shadow-lg shadow-amber-500/30"><i class="fas fa-save mr-2"></i>Update</button>
                <button type="button" onclick="closeModal('modalEditUser')" class="flex-1 py-3 bg-slate-100 text-slate-700 rounded-xl text-sm font-semibold">Batal</button>
            </div>
        </form>
    </div>
</div>

<script>
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('hidden');
        document.getElementById('sidebarOverlay').classList.toggle('hidden');
    }

    // Tab switching
    function switchTab(tabName) {
        document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
        
        event.currentTarget.classList.add('active');
        document.getElementById('tab-' + tabName).classList.add('active');
    }

    // Modal functions
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

    // Edit Kelas
    function editKelas(k) {
        document.getElementById('edit_kelas_id').value = k.id;
        document.getElementById('edit_kelas_nama').value = k.nama_kelas;
        document.getElementById('edit_kelas_wali').value = k.wali_kelas || '';
        document.getElementById('edit_kelas_semester').value = k.semester || 'Ganjil';
        document.getElementById('edit_kelas_ta').value = k.tahun_ajaran || '';
        openModal('modalEditKelas');
    }

    // Edit Mapel
    function editMapel(m) {
        document.getElementById('edit_mapel_id').value = m.id;
        document.getElementById('edit_mapel_kode').value = m.kode_mapel;
        document.getElementById('edit_mapel_nama').value = m.nama_mapel;
        document.getElementById('edit_mapel_kategori').value = m.kategori || 'Wajib';
        document.getElementById('edit_mapel_urutan').value = m.urutan || 0;
        openModal('modalEditMapel');
    }

    // Edit User
    function editUser(u) {
        document.getElementById('edit_user_id').value = u.id;
        document.getElementById('edit_user_nama').value = u.nama_lengkap;
        document.getElementById('edit_user_username').value = u.username;
        document.getElementById('edit_user_email').value = u.email || '';
        document.getElementById('edit_user_level').value = u.level;
        document.getElementById('edit_user_status').value = u.status || 'active';
        openModal('modalEditUser');
    }

    // Close on ESC
    document.addEventListener('keydown', function(e) {
        if(e.key === 'Escape') {
            document.querySelectorAll('.modal.active').forEach(m => {
                m.classList.remove('active');
            });
            document.body.style.overflow = '';
        }
    });

    // Auto-hide notifications
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