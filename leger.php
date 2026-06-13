<?php
require 'config.php';

// Cek session
if(!isset($_SESSION['login'])) {
    header("Location: index.php");
    exit;
}

// Ambil ID Kelas dari URL (default 1 jika tidak ada)
$kelas_id = isset($_GET['kelas']) ? (int)$_GET['kelas'] : 1;

// Ambil info kelas
$kelas_info = mysqli_query($conn, "SELECT * FROM kelas WHERE id = $kelas_id")->fetch_assoc();

// Ambil daftar siswa
$siswa_list = mysqli_query($conn, "SELECT * FROM siswa WHERE kelas_id = $kelas_id ORDER BY nama_siswa ASC");

// Sidebar variables
$current_page = basename($_SERVER['PHP_SELF']);
function isActive($page, $current) {
    return $page === $current ? 'active' : '';
}
$user_name = $_SESSION['nama_lengkap'] ?? 'Wali Kelas';
$user_level = ucfirst($_SESSION['level'] ?? 'guru');

// Fungsi helper untuk warna badge nilai
function getGradeColor($nilai) {
    if (empty($nilai)) return 'text-slate-400';
    if ($nilai >= 90) return 'bg-gradient-to-br from-emerald-400 to-emerald-600 text-white border-emerald-500';
    if ($nilai >= 80) return 'bg-gradient-to-br from-blue-400 to-blue-600 text-white border-blue-500';
    if ($nilai >= 70) return 'bg-gradient-to-br from-amber-400 to-amber-600 text-white border-amber-500';
    if ($nilai >= 60) return 'bg-gradient-to-br from-orange-400 to-orange-600 text-white border-orange-500';
    return 'bg-gradient-to-br from-red-400 to-red-600 text-white border-red-500';
}

function getPredikatLabel($nilai) {
    if (empty($nilai)) return '-';
    if ($nilai >= 90) return 'A';
    if ($nilai >= 80) return 'B';
    if ($nilai >= 70) return 'C';
    if ($nilai >= 60) return 'D';
    return 'E';
}

// Mapping kode mapel untuk iterasi tabel
$mapel_list = ['PAIDBP', 'PAKDBP', 'PPDK', 'BI', 'MU', 'IPADSI', 'PJODK', 'ING', 'MLBD', 'SR', 'GKS', 'SB'];
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leger Nilai - Kelas <?= htmlspecialchars($kelas_info['nama_kelas']) ?></title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Font -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] },
                    colors: {
                        primary: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            200: '#bae6fd',
                            300: '#7dd3fc',
                            400: '#38bdf8',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                            800: '#075985',
                            900: '#0c4a6e',
                        },
                        accent: {
                            400: '#facc15',
                            500: '#eab308',
                            600: '#ca8a04',
                        }
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease-out',
                        'slide-up': 'slideUp 0.6s ease-out',
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' },
                        },
                        slideUp: {
                            '0%': { transform: 'translateY(20px)', opacity: '0' },
                            '100%': { transform: 'translateY(0)', opacity: '1' },
                        }
                    }
                }
            }
        }
    </script>
    <style>
        * {
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* Scrollbar custom */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { 
            background: linear-gradient(180deg, #0ea5e9, #0284c7); 
            border-radius: 10px; 
        }
        ::-webkit-scrollbar-thumb:hover { background: linear-gradient(180deg, #0284c7, #0369a1); }

        .glass-header {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border-bottom: 1px solid rgba(226, 232, 240, 0.5);
        }

        /* Sticky Columns Logic */
        .sticky-col-1 { position: sticky; left: 0; z-index: 10; background: white; }
        .sticky-col-2 { position: sticky; left: 48px; z-index: 10; background: white; }
        .sticky-col-3 { position: sticky; left: 240px; z-index: 10; background: white; }
        
        th.sticky-col-1, th.sticky-col-2, th.sticky-col-3 {
            background: linear-gradient(135deg, #0c4a6e 0%, #075985 100%);
            z-index: 20;
        }

        .sticky-col-1::after,
        .sticky-col-2::after,
        .sticky-col-3::after {
            content: '';
            position: absolute;
            top: 0;
            right: -8px;
            bottom: 0;
            width: 8px;
            background: linear-gradient(to right, rgba(0,0,0,0.08), transparent);
            pointer-events: none;
        }

        th.sticky-col-1::after,
        th.sticky-col-2::after,
        th.sticky-col-3::after {
            background: linear-gradient(to right, rgba(12, 74, 110, 0.3), transparent);
        }

        tr:hover .sticky-col-1,
        tr:hover .sticky-col-2,
        tr:hover .sticky-col-3 {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
        }

        /* Table Header Gradient */
        .table-header-gradient {
            background: linear-gradient(135deg, #0c4a6e 0%, #075985 50%, #0369a1 100%);
        }

        .table-header-sub {
            background: linear-gradient(135deg, #075985 0%, #0369a1 100%);
        }

        /* Mesh gradient for info banner */
        .mesh-gradient {
            background-color: #0ea5e9;
            background-image: 
                radial-gradient(at 40% 20%, #38bdf8 0px, transparent 50%),
                radial-gradient(at 80% 0%, #0284c7 0px, transparent 50%),
                radial-gradient(at 0% 50%, #0ea5e9 0px, transparent 50%),
                radial-gradient(at 80% 50%, #0369a1 0px, transparent 50%),
                radial-gradient(at 0% 100%, #0ea5e9 0px, transparent 50%);
        }

        .card-hover {
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .card-hover:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px -12px rgba(0, 0, 0, 0.15);
        }

        .sidebar-item {
            position: relative;
            overflow: hidden;
        }

        .sidebar-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 3px;
            background: linear-gradient(180deg, #0ea5e9, #0284c7);
            transform: scaleY(0);
            transition: transform 0.3s ease;
        }

        .sidebar-item:hover::before,
        .sidebar-item.active::before {
            transform: scaleY(1);
        }

        .sidebar-item.active {
            background: linear-gradient(to right, rgba(14, 165, 233, 0.1), transparent);
        }

        .sidebar-item.active .w-8 {
            background: linear-gradient(135deg, #0ea5e9, #0284c7) !important;
        }

        .sidebar-item.active i {
            color: white !important;
        }

        /* Badge animation */
        .badge-value {
            transition: all 0.2s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .badge-value:hover {
            transform: scale(1.15);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        /* Table row hover */
        .table-row-hover {
            transition: all 0.2s ease;
        }

        .table-row-hover:hover {
            background: linear-gradient(90deg, rgba(14, 165, 233, 0.05) 0%, rgba(14, 165, 233, 0.02) 100%);
        }

        /* Scrollbar untuk sidebar */
        aside::-webkit-scrollbar { width: 4px; }
        aside::-webkit-scrollbar-track { background: transparent; }
        aside::-webkit-scrollbar-thumb { 
            background: rgba(14, 165, 233, 0.3); 
            border-radius: 10px; 
        }
        aside::-webkit-scrollbar-thumb:hover { 
            background: rgba(14, 165, 233, 0.5); 
        }

        /* Print Styles */
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; padding: 0 !important; margin: 0 !important; }
            .main-wrapper { margin: 0 !important; padding: 0 !important; overflow: visible !important; }
            
            /* Hide Sidebar & Header in print */
            aside, header { display: none !important; }
            
            /* Table adjustments for print */
            .table-container { overflow: visible !important; max-height: none !important; }
            .sticky-col-1, .sticky-col-2, .sticky-col-3, 
            th.sticky-col-1, th.sticky-col-2, th.sticky-col-3 { position: static !important; background: transparent !important; }
            .sticky-col-1::after, .sticky-col-2::after, .sticky-col-3::after { display: none !important; }
            
            /* Reduce font size and padding */
            table { font-size: 8pt !important; }
            th, td { padding: 2px 4px !important; border: 1px solid #cbd5e1 !important; }
            
            /* Badge colors */
            .badge-value { 
                border: 1px solid #000 !important; 
                background: #fff !important; 
                color: #000 !important; 
                font-weight: bold;
                box-shadow: none !important;
            }

            .info-banner { 
                background: white !important; 
                color: black !important;
                border: 2px solid black !important;
            }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 via-blue-50/30 to-slate-50 text-slate-800 font-sans antialiased selection:bg-primary-100 selection:text-primary-900">

<div class="flex h-screen overflow-hidden">
    
    <!-- Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Mobile Sidebar Overlay -->
    <div id="sidebarOverlay" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-20 hidden md:hidden" onclick="toggleSidebar()"></div>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col h-screen overflow-hidden relative md:ml-72 transition-all duration-300 main-wrapper">
        
        <!-- Header -->
        <header class="glass-header h-20 px-6 md:px-8 flex items-center justify-between sticky top-0 z-20">
            <div class="flex items-center gap-4">
                <button onclick="toggleSidebar()" class="md:hidden w-10 h-10 flex items-center justify-center bg-white rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 shadow-sm transition-all">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <h2 class="text-xl font-bold text-slate-800">Leger Nilai</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Tabel nilai lengkap kelas <?= htmlspecialchars($kelas_info['nama_kelas']) ?></p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <div class="hidden md:flex items-center bg-white rounded-xl p-1 border border-slate-200 shadow-sm">
                    <?php for($i=1; $i<=4; $i++): ?>
                    <a href="?kelas=<?= $i ?>" class="px-4 py-2 text-xs font-semibold rounded-lg transition-all <?= $kelas_id==$i ? 'bg-gradient-to-r from-primary-500 to-primary-600 text-white shadow-md shadow-primary-500/30' : 'text-slate-600 hover:text-primary-600 hover:bg-slate-50' ?>">
                        4<?= chr(64+$i) ?>
                    </a>
                    <?php endfor; ?>
                </div>
                <button onclick="window.print()" class="no-print flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 text-white rounded-xl text-sm font-semibold transition-all shadow-lg shadow-emerald-500/30 hover:shadow-xl hover:shadow-emerald-500/40 active:scale-95">
                    <i class="fas fa-print"></i> Cetak
                </button>
                <a href="dashboard.php?kelas=<?= $kelas_id ?>" class="no-print flex items-center gap-2 px-4 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 hover:border-primary-300 text-slate-700 rounded-xl text-sm font-semibold transition-all shadow-sm group">
                    <i class="fas fa-arrow-left group-hover:-translate-x-1 transition-transform"></i> Kembali
                </a>
            </div>
        </header>

        <!-- Scrollable Content -->
        <div class="flex-1 overflow-y-auto p-6 md:p-8 pb-20">
            
            <!-- Info Header -->
            <div class="no-print mb-6 relative rounded-2xl p-6 text-white overflow-hidden shadow-2xl shadow-primary-900/20 animate-fade-in mesh-gradient">
                <div class="absolute inset-0 bg-gradient-to-br from-primary-700/90 via-primary-800/90 to-primary-900/90"></div>
                <div class="absolute top-0 right-0 w-64 h-64 bg-accent-400/20 rounded-full blur-3xl -mr-20 -mt-20"></div>
                <div class="absolute bottom-0 left-0 w-48 h-48 bg-white/10 rounded-full blur-3xl -ml-16 -mb-16"></div>
                
                <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-5">
                    <div class="flex items-start gap-4">
                        <div class="w-14 h-14 rounded-2xl bg-white/10 flex items-center justify-center flex-shrink-0 backdrop-blur-sm border border-white/20 shadow-xl">
                            <i class="fas fa-file-alt text-accent-400 text-2xl"></i>
                        </div>
                        <div>
                            <div class="inline-flex items-center gap-2 bg-white/10 backdrop-blur-sm px-3 py-1 rounded-full border border-white/20 mb-2">
                                <i class="fas fa-book-open text-accent-400 text-xs"></i>
                                <span class="text-xs font-medium">Dokumen Resmi</span>
                            </div>
                            <h3 class="font-bold text-2xl mb-1">LEGER NILAI RAPOR SISWA</h3>
                            <p class="text-primary-100 text-sm">SDN Curug 01 Bojongsari  |  Tahun Pelajaran 2025/2026  |  Semester Ganjil</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-4 bg-white/10 backdrop-blur-sm rounded-xl p-4 border border-white/20">
                        <div class="text-right">
                            <p class="text-xs text-primary-200 uppercase font-semibold">Wali Kelas</p>
                            <p class="font-bold text-white text-sm"><?= htmlspecialchars($kelas_info['wali_kelas']) ?></p>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-accent-400 to-accent-600 flex items-center justify-center shadow-lg">
                            <i class="fas fa-user-tie text-white"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Table Container -->
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden relative animate-slide-up">
                <div class="table-container overflow-x-auto max-h-[75vh]">
                    <table class="w-full text-sm text-left border-collapse">
                        <thead class="text-slate-200 uppercase text-[10px] tracking-wider sticky top-0 z-30">
                            <!-- Header Row 1 -->
                            <tr class="table-header-gradient">
                                <th rowspan="2" class="sticky-col-1 px-3 py-3 border-r border-primary-800/50 text-center w-12">
                                    <div class="flex items-center justify-center gap-1">
                                        <i class="fas fa-hashtag text-accent-400 text-[9px]"></i>
                                        <span>NO</span>
                                    </div>
                                </th>
                                <th rowspan="2" class="sticky-col-2 px-4 py-3 border-r border-primary-800/50 text-left min-w-[200px]">
                                    <div class="flex items-center gap-2">
                                        <i class="fas fa-user text-accent-400 text-[9px]"></i>
                                        <span>NAMA SISWA</span>
                                    </div>
                                </th>
                                <th rowspan="2" class="sticky-col-3 px-4 py-3 border-r border-primary-800/50 text-center w-24">
                                    <div class="flex items-center justify-center gap-1">
                                        <i class="fas fa-id-card text-accent-400 text-[9px]"></i>
                                        <span>NISN</span>
                                    </div>
                                </th>
                                <th colspan="12" class="px-4 py-2 border-r border-primary-800/50 text-center font-bold">
                                    <div class="flex items-center justify-center gap-2">
                                        <i class="fas fa-book text-accent-400 text-[9px]"></i>
                                        <span>MATA PELAJARAN</span>
                                    </div>
                                </th>
                                <th colspan="3" class="px-4 py-2 border-r border-primary-800/50 text-center font-bold">
                                    <div class="flex items-center justify-center gap-2">
                                        <i class="fas fa-calendar-check text-accent-400 text-[9px]"></i>
                                        <span>KEHADIRAN</span>
                                    </div>
                                </th>
                                <th colspan="1" class="px-4 py-2 text-center font-bold">
                                    <div class="flex items-center justify-center gap-2">
                                        <i class="fas fa-medal text-accent-400 text-[9px]"></i>
                                        <span>EKSTRAKURIKULER</span>
                                    </div>
                                </th>
                            </tr>
                            <!-- Header Row 2 -->
                            <tr class="text-[10px] table-header-sub">
                                <th class="px-2 py-2.5 border-r border-primary-800/30 min-w-[40px]">PAI</th>
                                <th class="px-2 py-2.5 border-r border-primary-800/30 min-w-[40px]">PAK</th>
                                <th class="px-2 py-2.5 border-r border-primary-800/30 min-w-[40px]">PPKn</th>
                                <th class="px-2 py-2.5 border-r border-primary-800/30 min-w-[40px]">Indo</th>
                                <th class="px-2 py-2.5 border-r border-primary-800/30 min-w-[40px]">MTk</th>
                                <th class="px-2 py-2.5 border-r border-primary-800/30 min-w-[40px]">IPAS</th>
                                <th class="px-2 py-2.5 border-r border-primary-800/30 min-w-[40px]">PJOK</th>
                                <th class="px-2 py-2.5 border-r border-primary-800/30 min-w-[40px]">Ing</th>
                                <th class="px-2 py-2.5 border-r border-primary-800/30 min-w-[40px]">ML</th>
                                <th class="px-2 py-2.5 border-r border-primary-800/30 min-w-[40px]">SR</th>
                                <th class="px-2 py-2.5 border-r border-primary-800/30 min-w-[40px]">GKS</th>
                                <th class="px-2 py-2.5 border-r border-primary-800/30 min-w-[40px]">SB</th>
                                <th class="px-2 py-2.5 border-r border-primary-800/30 min-w-[40px]">
                                    <span class="text-orange-300">S</span>
                                </th>
                                <th class="px-2 py-2.5 border-r border-primary-800/30 min-w-[40px]">
                                    <span class="text-blue-300">I</span>
                                </th>
                                <th class="px-2 py-2.5 border-r border-primary-800/30 min-w-[40px]">
                                    <span class="text-red-300">A</span>
                                </th>
                                <th class="px-2 py-2.5 min-w-[60px]">Pramuka</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs">
                            <?php
                            $no = 1;
                            while($siswa = mysqli_fetch_assoc($siswa_list)):
                                // Ambil Nilai
                                $nilai_query = mysqli_query($conn, "SELECT n.nilai_angka, m.kode_mapel FROM nilai n JOIN mata_pelajaran m ON n.mapel_id = m.id WHERE n.siswa_id = {$siswa['id']} AND n.kelas_id = $kelas_id");
                                $nilai_data = [];
                                while($n = mysqli_fetch_assoc($nilai_query)) {
                                    $nilai_data[$n['kode_mapel']] = $n['nilai_angka'];
                                }
                                
                                // Ambil Kehadiran
                                $kehadiran = mysqli_query($conn, "SELECT * FROM kehadiran WHERE siswa_id = {$siswa['id']} AND kelas_id = $kelas_id")->fetch_assoc() ?? ['sakit'=>0, 'izin'=>0, 'alpa'=>0];
                                
                                // Ambil Ekstra
                                $ekstra = mysqli_query($conn, "SELECT predikat FROM ekstrakurikuler WHERE siswa_id = {$siswa['id']} LIMIT 1")->fetch_assoc() ?? ['predikat'=>'B'];
                            ?>
                            <tr class="table-row-hover group">
                                <td class="sticky-col-1 px-3 py-3 border-r border-slate-200 bg-white text-center text-slate-500 font-semibold">
                                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-slate-100 text-slate-600 text-[10px] font-bold group-hover:bg-primary-100 group-hover:text-primary-700 transition-colors">
                                        <?= $no++ ?>
                                    </span>
                                </td>
                                <td class="sticky-col-2 px-4 py-3 border-r border-slate-200 bg-white font-semibold text-slate-800 text-left whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($siswa['nama_siswa']) ?>&background=0ea5e9&color=fff&bold=true&size=32" alt="Avatar" class="w-7 h-7 rounded-full ring-2 ring-primary-100">
                                        <span><?= strtoupper($siswa['nama_siswa']) ?></span>
                                    </div>
                                </td>
                                <td class="sticky-col-3 px-4 py-3 border-r border-slate-200 bg-white text-center text-slate-600 font-mono text-[11px]"><?= $siswa['nisn'] ?></td>
                                
                                <?php foreach($mapel_list as $mapel): 
                                    $nilai = $nilai_data[$mapel] ?? null;
                                ?>
                                <td class="px-2 py-3 border-r border-slate-100 text-center">
                                    <?php if($nilai !== null): ?>
                                    <span class="badge-value inline-flex items-center justify-center w-9 h-9 rounded-lg text-[11px] font-bold border <?= getGradeColor($nilai) ?>">
                                        <?= $nilai ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="text-slate-300">-</span>
                                    <?php endif; ?>
                                </td>
                                <?php endforeach; ?>
                                
                                <td class="px-2 py-3 border-r border-slate-100 text-center">
                                    <?php if(($kehadiran['sakit'] ?? 0) > 0): ?>
                                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-orange-100 text-orange-700 text-[11px] font-bold border border-orange-200">
                                        <?= $kehadiran['sakit'] ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="text-slate-400">0</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-2 py-3 border-r border-slate-100 text-center">
                                    <?php if(($kehadiran['izin'] ?? 0) > 0): ?>
                                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-blue-100 text-blue-700 text-[11px] font-bold border border-blue-200">
                                        <?= $kehadiran['izin'] ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="text-slate-400">0</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-2 py-3 border-r border-slate-100 text-center">
                                    <?php if(($kehadiran['alpa'] ?? 0) > 0): ?>
                                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-red-100 text-red-700 text-[11px] font-bold border border-red-200">
                                        <?= $kehadiran['alpa'] ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="text-slate-400">0</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-2 py-3 text-center">
                                    <?php
                                    $pred = $ekstra['predikat'] ?? 'B';
                                    $extraColor = match($pred) {
                                        'A' => 'bg-gradient-to-br from-emerald-400 to-emerald-600 text-white',
                                        'B' => 'bg-gradient-to-br from-blue-400 to-blue-600 text-white',
                                        'C' => 'bg-gradient-to-br from-amber-400 to-amber-600 text-white',
                                        default => 'bg-gradient-to-br from-slate-300 to-slate-500 text-white'
                                    };
                                    ?>
                                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-[11px] font-bold <?= $extraColor ?> shadow-md">
                                        <?= $pred ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Table Footer Info -->
                <div class="no-print px-6 py-3 bg-gradient-to-r from-slate-50 to-white border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-info-circle text-primary-500"></i>
                        <span>Total <strong class="text-slate-700"><?= mysqli_num_rows($siswa_list) ?></strong> siswa ditampilkan</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <i class="fas fa-arrows-alt-h text-primary-500"></i>
                        <span>Scroll horizontal untuk melihat semua kolom</span>
                    </div>
                </div>
            </div>

            <!-- Legend -->
            <div class="no-print mt-6 bg-white rounded-2xl border border-slate-100 shadow-sm p-6 animate-slide-up" style="animation-delay: 0.2s;">
                <div class="flex items-center gap-3 mb-5">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center shadow-lg shadow-primary-500/30">
                        <i class="fas fa-info-circle text-white"></i>
                    </div>
                    <div>
                        <h4 class="font-bold text-slate-800 text-lg">Keterangan</h4>
                        <p class="text-xs text-slate-500">Penjelasan kode mata pelajaran dan rentang nilai</p>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 text-xs">
                    <div class="space-y-2">
                        <div class="flex items-center gap-2 p-2.5 rounded-lg bg-slate-50 border border-slate-100 hover:bg-primary-50 hover:border-primary-200 transition-colors">
                            <span class="w-1 h-8 rounded-full bg-gradient-to-b from-primary-400 to-primary-600"></span>
                            <div>
                                <strong class="text-slate-800 block">PAI</strong>
                                <span class="text-slate-500 text-[10px]">Agama Islam & BP</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 p-2.5 rounded-lg bg-slate-50 border border-slate-100 hover:bg-primary-50 hover:border-primary-200 transition-colors">
                            <span class="w-1 h-8 rounded-full bg-gradient-to-b from-primary-400 to-primary-600"></span>
                            <div>
                                <strong class="text-slate-800 block">PAK</strong>
                                <span class="text-slate-500 text-[10px]">Agama Kristen & BP</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 p-2.5 rounded-lg bg-slate-50 border border-slate-100 hover:bg-primary-50 hover:border-primary-200 transition-colors">
                            <span class="w-1 h-8 rounded-full bg-gradient-to-b from-primary-400 to-primary-600"></span>
                            <div>
                                <strong class="text-slate-800 block">PPKn</strong>
                                <span class="text-slate-500 text-[10px]">Pendidikan Pancasila</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 p-2.5 rounded-lg bg-slate-50 border border-slate-100 hover:bg-primary-50 hover:border-primary-200 transition-colors">
                            <span class="w-1 h-8 rounded-full bg-gradient-to-b from-primary-400 to-primary-600"></span>
                            <div>
                                <strong class="text-slate-800 block">Indo</strong>
                                <span class="text-slate-500 text-[10px]">Bahasa Indonesia</span>
                            </div>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <div class="flex items-center gap-2 p-2.5 rounded-lg bg-slate-50 border border-slate-100 hover:bg-primary-50 hover:border-primary-200 transition-colors">
                            <span class="w-1 h-8 rounded-full bg-gradient-to-b from-primary-400 to-primary-600"></span>
                            <div>
                                <strong class="text-slate-800 block">MTk</strong>
                                <span class="text-slate-500 text-[10px]">Matematika</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 p-2.5 rounded-lg bg-slate-50 border border-slate-100 hover:bg-primary-50 hover:border-primary-200 transition-colors">
                            <span class="w-1 h-8 rounded-full bg-gradient-to-b from-primary-400 to-primary-600"></span>
                            <div>
                                <strong class="text-slate-800 block">IPAS</strong>
                                <span class="text-slate-500 text-[10px]">IPA & Sosial</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 p-2.5 rounded-lg bg-slate-50 border border-slate-100 hover:bg-primary-50 hover:border-primary-200 transition-colors">
                            <span class="w-1 h-8 rounded-full bg-gradient-to-b from-primary-400 to-primary-600"></span>
                            <div>
                                <strong class="text-slate-800 block">PJOK</strong>
                                <span class="text-slate-500 text-[10px]">Jasmani & Kesehatan</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 p-2.5 rounded-lg bg-slate-50 border border-slate-100 hover:bg-primary-50 hover:border-primary-200 transition-colors">
                            <span class="w-1 h-8 rounded-full bg-gradient-to-b from-primary-400 to-primary-600"></span>
                            <div>
                                <strong class="text-slate-800 block">Ing</strong>
                                <span class="text-slate-500 text-[10px]">Bahasa Inggris</span>
                            </div>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <div class="flex items-center gap-2 p-2.5 rounded-lg bg-slate-50 border border-slate-100 hover:bg-primary-50 hover:border-primary-200 transition-colors">
                            <span class="w-1 h-8 rounded-full bg-gradient-to-b from-primary-400 to-primary-600"></span>
                            <div>
                                <strong class="text-slate-800 block">ML</strong>
                                <span class="text-slate-500 text-[10px]">Mulok Bahasa Daerah</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 p-2.5 rounded-lg bg-slate-50 border border-slate-100 hover:bg-primary-50 hover:border-primary-200 transition-colors">
                            <span class="w-1 h-8 rounded-full bg-gradient-to-b from-primary-400 to-primary-600"></span>
                            <div>
                                <strong class="text-slate-800 block">SR</strong>
                                <span class="text-slate-500 text-[10px]">Seni Rupa</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 p-2.5 rounded-lg bg-slate-50 border border-slate-100 hover:bg-primary-50 hover:border-primary-200 transition-colors">
                            <span class="w-1 h-8 rounded-full bg-gradient-to-b from-primary-400 to-primary-600"></span>
                            <div>
                                <strong class="text-slate-800 block">GKS</strong>
                                <span class="text-slate-500 text-[10px]">Guru Kelas</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 p-2.5 rounded-lg bg-slate-50 border border-slate-100 hover:bg-primary-50 hover:border-primary-200 transition-colors">
                            <span class="w-1 h-8 rounded-full bg-gradient-to-b from-primary-400 to-primary-600"></span>
                            <div>
                                <strong class="text-slate-800 block">SB</strong>
                                <span class="text-slate-500 text-[10px]">Seni Budaya</span>
                            </div>
                        </div>
                    </div>
                    <div class="p-4 rounded-xl bg-gradient-to-br from-amber-50 via-yellow-50 to-orange-50 border-2 border-amber-200">
                        <div class="flex items-center gap-2 mb-3">
                            <div class="w-7 h-7 rounded-lg bg-gradient-to-br from-amber-400 to-amber-600 flex items-center justify-center">
                                <i class="fas fa-lightbulb text-white text-xs"></i>
                            </div>
                            <strong class="text-amber-900 text-sm">Rentang Nilai</strong>
                        </div>
                        <div class="space-y-1.5 mt-2">
                            <div class="flex items-center gap-2">
                                <span class="w-3 h-3 rounded bg-gradient-to-br from-emerald-400 to-emerald-600"></span>
                                <span class="text-amber-900 font-semibold text-[10px]">90-100: Sangat Baik</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="w-3 h-3 rounded bg-gradient-to-br from-blue-400 to-blue-600"></span>
                                <span class="text-amber-900 font-semibold text-[10px]">80-89: Baik</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="w-3 h-3 rounded bg-gradient-to-br from-amber-400 to-amber-600"></span>
                                <span class="text-amber-900 font-semibold text-[10px]">70-79: Cukup</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="w-3 h-3 rounded bg-gradient-to-br from-orange-400 to-orange-600"></span>
                                <span class="text-amber-900 font-semibold text-[10px]">60-69: Kurang</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="w-3 h-3 rounded bg-gradient-to-br from-red-400 to-red-600"></span>
                                <span class="text-amber-900 font-semibold text-[10px]">&lt;60: Perlu Bimbingan</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <footer class="no-print mt-8 text-center text-xs text-slate-500 pb-4">
                <div class="flex items-center justify-center gap-2 mb-2">
                    <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center">
                        <i class="fas fa-graduation-cap text-white text-xs"></i>
                    </div>
                </div>
                <p class="font-medium">&copy; <?= date('Y') ?> SDN Curug 01 Bojongsari. All rights reserved.</p>
                <p class="text-slate-400 mt-1">Sistem Monitoring Akademik Terpadu</p>
            </footer>
        </div>
    </main>
</div>

<script>
    // Toggle Sidebar Mobile
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        sidebar.classList.toggle('hidden');
        sidebar.classList.toggle('fixed');
        sidebar.classList.toggle('inset-0');
        overlay.classList.toggle('hidden');
    }

    // Close sidebar when clicking overlay
    document.getElementById('sidebarOverlay')?.addEventListener('click', toggleSidebar);

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