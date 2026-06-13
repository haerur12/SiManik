<?php
// Ambil kelas_id dari URL jika ada
$kelas_id = isset($kelas_id) ? (int)$kelas_id : (isset($kelas_filter) ? (int)$kelas_filter : (isset($_GET['kelas']) ? (int)$_GET['kelas'] : 1));
$kelas_filter = $kelas_filter ?? $kelas_id; // Untuk kompatibilitas
$current_page = basename($_SERVER['PHP_SELF']);

// Helper function untuk cek active state (guard against redeclare)
if(!function_exists('isActive')) {
    function isActive($page, $current) {
        return $page === $current ? 'active' : '';
    }
}

// Ambil info user dari session (jika ada)
$user_name = $_SESSION['nama_lengkap'] ?? 'Wali Kelas';
$user_level = ucfirst($_SESSION['level'] ?? 'guru');
?>

<!-- Sidebar - Light Theme -->
<aside id="sidebar" class="hidden md:flex flex-col w-72 bg-white text-slate-700 h-screen border-r border-slate-200/80 fixed z-30 transition-all duration-300">
    
    <!-- Brand Header -->
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

    <!-- Navigation -->
    <nav class="flex-1 overflow-y-auto py-6 px-4 space-y-1">
        
        <!-- Menu Utama Section -->
        <p class="px-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-3 flex items-center gap-2">
            <i class="fas fa-compass text-primary-500 text-[9px]"></i>
            Menu Utama
        </p>
        
        <a href="dashboard.php?kelas=<?= $kelas_id ?>" class="sidebar-item <?= isActive('dashboard.php', $current_page) ?> flex items-center gap-3 px-3 py-2.5 text-slate-600 hover:text-primary-700 hover:bg-primary-50/60 rounded-xl transition-all group">
            <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center group-hover:bg-primary-100 transition-all">
                <i class="fas fa-home w-4 text-center text-sm text-slate-500 group-hover:text-primary-600 transition-colors"></i>
            </div>
            <span class="font-medium text-sm">Dashboard</span>
            <?php if($current_page == 'dashboard.php'): ?>
            <span class="ml-auto w-1.5 h-1.5 bg-primary-500 rounded-full animate-pulse"></span>
            <?php endif; ?>
        </a>

        <a href="import_excel.php" class="sidebar-item <?= isActive('import_excel.php', $current_page) ?> flex items-center gap-3 px-3 py-2.5 text-slate-600 hover:text-emerald-700 hover:bg-emerald-50/60 rounded-xl transition-all group">
            <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center group-hover:bg-emerald-100 transition-all">
                <i class="fas fa-file-excel w-4 text-center text-sm text-slate-500 group-hover:text-emerald-600 transition-colors"></i>
            </div>
            <span class="font-medium text-sm">Import Data</span>
            <?php if($current_page == 'import_excel.php'): ?>
            <span class="ml-auto px-2 py-0.5 bg-emerald-100 text-emerald-700 text-[9px] font-bold rounded-full">NEW</span>
            <?php endif; ?>
        </a>

        <a href="leger.php?kelas=<?= $kelas_id ?>" class="sidebar-item <?= isActive('leger.php', $current_page) ?> flex items-center gap-3 px-3 py-2.5 text-slate-600 hover:text-blue-700 hover:bg-blue-50/60 rounded-xl transition-all group">
            <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center group-hover:bg-blue-100 transition-all">
                <i class="fas fa-book-open w-4 text-center text-sm text-slate-500 group-hover:text-blue-600 transition-colors"></i>
            </div>
            <span class="font-medium text-sm">Leger Nilai</span>
            <?php if($current_page == 'leger.php'): ?>
            <span class="ml-auto w-1.5 h-1.5 bg-blue-500 rounded-full animate-pulse"></span>
            <?php endif; ?>
        </a>

        <a href="grafik_nilai.php?kelas=<?= $kelas_id ?>" class="sidebar-item <?= isActive('grafik_nilai.php', $current_page) ?> flex items-center gap-3 px-3 py-2.5 text-slate-600 hover:text-purple-700 hover:bg-purple-50/60 rounded-xl transition-all group">
            <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center group-hover:bg-purple-100 transition-all">
                <i class="fas fa-chart-pie w-4 text-center text-sm text-slate-500 group-hover:text-purple-600 transition-colors"></i>
            </div>
            <span class="font-medium text-sm">Analitik Grafik</span>
            <?php if($current_page == 'grafik_nilai.php'): ?>
            <span class="ml-auto w-1.5 h-1.5 bg-purple-500 rounded-full animate-pulse"></span>
            <?php endif; ?>
        </a>

        <!-- Divider -->
        <div class="my-5 border-t border-slate-100"></div>
        
        <!-- Manajemen Section -->
        <p class="px-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-3 flex items-center gap-2">
            <i class="fas fa-cogs text-accent-500 text-[9px]"></i>
            Manajemen
        </p>
        
        <a href="data_siswa.php?kelas=<?= $kelas_id ?>" class="sidebar-item <?= isActive('data_siswa.php', $current_page) ?> flex items-center gap-3 px-3 py-2.5 text-slate-600 hover:text-amber-700 hover:bg-amber-50/60 rounded-xl transition-all group">
            <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center group-hover:bg-amber-100 transition-all">
                <i class="fas fa-users w-4 text-center text-sm text-slate-500 group-hover:text-amber-600 transition-colors"></i>
            </div>
            <span class="font-medium text-sm">Data Siswa</span>
            <?php if($current_page == 'data_siswa.php'): ?>
            <span class="ml-auto w-1.5 h-1.5 bg-amber-500 rounded-full animate-pulse"></span>
            <?php endif; ?>
        </a>
        
        <a href="pengaturan.php" class="sidebar-item <?= isActive('pengaturan.php', $current_page) ?> flex items-center gap-3 px-3 py-2.5 text-slate-600 hover:text-slate-800 hover:bg-slate-100 rounded-xl transition-all group">
            <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center group-hover:bg-slate-200 transition-all">
                <i class="fas fa-cog w-4 text-center text-sm text-slate-500 group-hover:text-slate-700 transition-colors"></i>
            </div>
            <span class="font-medium text-sm">Pengaturan</span>
        </a>

        <!-- Quick Stats -->
        <div class="my-5 border-t border-slate-100"></div>
        <div class="mx-2 p-4 bg-gradient-to-br from-primary-50 to-blue-50 rounded-xl border border-primary-100">
            <div class="flex items-center gap-2 mb-3">
                <div class="w-6 h-6 rounded-md bg-gradient-to-br from-primary-500 to-primary-600 flex items-center justify-center">
                    <i class="fas fa-chart-line text-white text-[10px]"></i>
                </div>
                <span class="text-xs font-bold text-primary-900">Statistik Cepat</span>
            </div>
            <div class="space-y-2.5">
                <div class="flex items-center justify-between text-xs">
                    <span class="text-slate-600 font-medium">Total Siswa</span>
                    <span class="font-bold text-slate-800 bg-white px-2 py-0.5 rounded-md shadow-sm">
                        <?php
                        if(isset($conn)) {
                            $total = mysqli_query($conn, "SELECT COUNT(*) as total FROM siswa WHERE kelas_id = $kelas_id");
                            echo mysqli_fetch_assoc($total)['total'] ?? '0';
                        } else {
                            echo '-';
                        }
                        ?>
                    </span>
                </div>
                <div class="flex items-center justify-between text-xs">
                    <span class="text-slate-600 font-medium">Kelas Aktif</span>
                    <span class="font-bold text-primary-700 bg-white px-2 py-0.5 rounded-md shadow-sm">4<?= chr(64+$kelas_id) ?></span>
                </div>
            </div>
        </div>
    </nav>

    <!-- User Profile Footer -->
    <div class="p-4 border-t border-slate-100">
        <div class="flex items-center gap-3 p-2.5 rounded-xl hover:bg-slate-50 transition-all cursor-pointer group">
            <div class="relative">
                <img src="https://ui-avatars.com/api/?name=<?= urlencode($user_name) ?>&background=0ea5e9&color=fff&bold=true" alt="Avatar" class="w-10 h-10 rounded-full ring-2 ring-primary-100 group-hover:ring-primary-200 transition-all">
                <div class="absolute bottom-0 right-0 w-3 h-3 bg-green-500 rounded-full border-2 border-white"></div>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-slate-800 truncate"><?= htmlspecialchars($user_name) ?></p>
                <p class="text-xs text-slate-500 truncate"><?= $user_level ?> SDN Curug 01</p>
            </div>
            <a href="logout.php" class="text-slate-400 hover:text-red-500 transition-colors p-2 hover:bg-red-50 rounded-lg" title="Logout">
                <i class="fas fa-sign-out-alt text-sm"></i>
            </a>
        </div>
    </div>
</aside>

<!-- Mobile Sidebar Overlay -->
<div id="sidebarOverlay" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-20 hidden md:hidden" onclick="toggleSidebar()"></div>

<style>
    .sidebar-item {
        position: relative;
        overflow: hidden;
    }

    .sidebar-item::before {
        content: '';
        position: absolute;
        left: 0;
        top: 50%;
        transform: translateY(-50%);
        height: 0;
        width: 3px;
        background: linear-gradient(180deg, #0ea5e9, #0284c7);
        border-radius: 0 3px 3px 0;
        transition: height 0.3s ease;
    }

    .sidebar-item:hover::before {
        height: 60%;
    }

    .sidebar-item.active::before {
        height: 70%;
    }

    .sidebar-item.active {
        background: linear-gradient(to right, rgba(14, 165, 233, 0.08), rgba(14, 165, 233, 0.02));
        color: #0369a1 !important;
    }

    .sidebar-item.active .w-8 {
        background: linear-gradient(135deg, #0ea5e9, #0284c7) !important;
        box-shadow: 0 4px 12px -2px rgba(14, 165, 233, 0.4);
    }

    .sidebar-item.active .w-8 i {
        color: white !important;
    }

    .sidebar-item.active span {
        color: #0369a1 !important;
        font-weight: 600;
    }

    /* Scrollbar untuk sidebar */
    aside::-webkit-scrollbar { width: 4px; }
    aside::-webkit-scrollbar-track { background: transparent; }
    aside::-webkit-scrollbar-thumb { 
        background: rgba(14, 165, 233, 0.2); 
        border-radius: 10px; 
    }
    aside::-webkit-scrollbar-thumb:hover { 
        background: rgba(14, 165, 233, 0.4); 
    }

    /* Smooth transition untuk semua sidebar items */
    .sidebar-item * {
        transition: all 0.2s ease;
    }
</style>