<?php
require 'config.php';

if(!isset($_SESSION['login'])) {
    header("Location: index.php");
    exit;
}

$kelas_id = isset($_GET['kelas']) ? (int)$_GET['kelas'] : 1;
$kelas_info = mysqli_query($conn, "SELECT * FROM kelas WHERE id = $kelas_id")->fetch_assoc();

// 1. Total Siswa
$total_siswa = mysqli_query($conn, "SELECT COUNT(*) as total FROM siswa WHERE kelas_id = $kelas_id")->fetch_assoc()['total'] ?? 0;

// 2. Statistik Kehadiran
$kehadiran_stats = mysqli_query($conn, "
    SELECT SUM(sakit) as total_sakit, SUM(izin) as total_izin, SUM(alpa) as total_alpa, SUM(hadir) as total_hadir
    FROM kehadiran WHERE kelas_id = $kelas_id
")->fetch_assoc() ?? ['total_sakit'=>0, 'total_izin'=>0, 'total_alpa'=>0, 'total_hadir'=>0];

// 3. Rata-rata Kelas (Weighted)
$kelas_avg_query = mysqli_query($conn, "
SELECT ROUND(AVG(final_score), 1) as kelas_avg
FROM (
    SELECT 
        s.id,
        (
            COALESCE((SELECT AVG(nilai_angka) FROM nilai WHERE siswa_id = s.id AND kelas_id = $kelas_id), 0) * 0.70 +
            GREATEST(0, 100 - (COALESCE((SELECT alpa FROM kehadiran WHERE siswa_id = s.id AND kelas_id = $kelas_id), 0)*5 + 
                               COALESCE((SELECT izin FROM kehadiran WHERE siswa_id = s.id AND kelas_id = $kelas_id), 0)*2 + 
                               COALESCE((SELECT sakit FROM kehadiran WHERE siswa_id = s.id AND kelas_id = $kelas_id), 0)*1)
            ) * 0.15 +
            CASE 
                WHEN (SELECT predikat FROM ekstrakurikuler WHERE siswa_id = s.id LIMIT 1) = 'A' THEN 90
                WHEN (SELECT predikat FROM ekstrakurikuler WHERE siswa_id = s.id LIMIT 1) = 'B' THEN 80
                WHEN (SELECT predikat FROM ekstrakurikuler WHERE siswa_id = s.id LIMIT 1) = 'C' THEN 70
                ELSE 60
            END * 0.15
        ) as final_score
    FROM siswa s
    WHERE s.kelas_id = $kelas_id
) as student_scores
");
$kelas_avg = mysqli_fetch_assoc($kelas_avg_query)['kelas_avg'] ?? 0;

// 4. Distribusi Nilai
$distribusi_nilai = mysqli_query($conn, "
SELECT
    SUM(CASE WHEN nilai_angka >= 90 THEN 1 ELSE 0 END) as a_count,
    SUM(CASE WHEN nilai_angka >= 80 AND nilai_angka < 90 THEN 1 ELSE 0 END) as b_count,
    SUM(CASE WHEN nilai_angka >= 70 AND nilai_angka < 80 THEN 1 ELSE 0 END) as c_count,
    SUM(CASE WHEN nilai_angka >= 60 AND nilai_angka < 70 THEN 1 ELSE 0 END) as d_count,
    SUM(CASE WHEN nilai_angka < 60 THEN 1 ELSE 0 END) as e_count
FROM nilai WHERE kelas_id = $kelas_id
")->fetch_assoc() ?? ['a_count'=>0, 'b_count'=>0, 'c_count'=>0, 'd_count'=>0, 'e_count'=>0];

// 5. Top 5 Siswa
$top_siswa = mysqli_query($conn, "
SELECT 
    s.id,
    s.nama_siswa,
    ROUND(
        (COALESCE((SELECT AVG(nilai_angka) FROM nilai WHERE siswa_id = s.id AND kelas_id = $kelas_id), 0) * 0.70) +
        (GREATEST(0, 100 - (COALESCE((SELECT alpa FROM kehadiran WHERE siswa_id = s.id AND kelas_id = $kelas_id), 0)*5 + 
                           COALESCE((SELECT izin FROM kehadiran WHERE siswa_id = s.id AND kelas_id = $kelas_id), 0)*2 + 
                           COALESCE((SELECT sakit FROM kehadiran WHERE siswa_id = s.id AND kelas_id = $kelas_id), 0)*1)
        ) * 0.15) +
        (CASE 
            WHEN (SELECT predikat FROM ekstrakurikuler WHERE siswa_id = s.id LIMIT 1) = 'A' THEN 90
            WHEN (SELECT predikat FROM ekstrakurikuler WHERE siswa_id = s.id LIMIT 1) = 'B' THEN 80
            WHEN (SELECT predikat FROM ekstrakurikuler WHERE siswa_id = s.id LIMIT 1) = 'C' THEN 70
            ELSE 60
         END * 0.15)
    , 2) as final_score
FROM siswa s
WHERE s.kelas_id = $kelas_id
ORDER BY final_score DESC
LIMIT 5
");

// 6. Siswa Perlu Bimbingan
$perlu_bimbingan = mysqli_query($conn, "
    SELECT COUNT(DISTINCT s.id) as total
    FROM siswa s
    LEFT JOIN nilai n ON s.id = n.siswa_id AND n.kelas_id = $kelas_id
    LEFT JOIN kehadiran k ON s.id = k.siswa_id AND k.kelas_id = $kelas_id
    WHERE s.kelas_id = $kelas_id
    AND (
        (n.nilai_angka < 70) OR (k.alpa > 3)
    )
")->fetch_assoc()['total'] ?? 0;

// 7. Rata-rata per Mapel
$mapel_result = mysqli_query($conn, "
    SELECT m.kode_mapel, m.nama_mapel, AVG(n.nilai_angka) as rata_rata
    FROM nilai n
    JOIN mata_pelajaran m ON n.mapel_id = m.id
    WHERE n.kelas_id = $kelas_id
    GROUP BY m.id
    ORDER BY m.kode_mapel ASC
");
$mapel_data = [];
while($row = mysqli_fetch_assoc($mapel_result)) {
    $mapel_data[] = ['label' => $row['kode_mapel'], 'val' => round($row['rata_rata'], 2)];
}

function getPredikatLabel($nilai) {
    if($nilai >= 90) return 'A';
    if($nilai >= 80) return 'B';
    if($nilai >= 70) return 'C';
    if($nilai >= 60) return 'D';
    return 'E';
}

// Sidebar variables
$current_page = basename($_SERVER['PHP_SELF']);
function isActive($page, $current) {
    return $page === $current ? 'active' : '';
}
$user_name = $_SESSION['nama_lengkap'] ?? 'Wali Kelas';
$user_level = ucfirst($_SESSION['level'] ?? 'guru');
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Monitoring - SiManik</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                        'scale-in': 'scaleIn 0.4s ease-out',
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' },
                        },
                        slideUp: {
                            '0%': { transform: 'translateY(20px)', opacity: '0' },
                            '100%': { transform: 'translateY(0)', opacity: '1' },
                        },
                        scaleIn: {
                            '0%': { transform: 'scale(0.95)', opacity: '0' },
                            '100%': { transform: 'scale(1)', opacity: '1' },
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
        
        .card-hover {
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .card-hover:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px -12px rgba(0, 0, 0, 0.15);
        }

        .gradient-text {
            background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-card-gradient {
            background: linear-gradient(135deg, rgba(14, 165, 233, 0.05) 0%, rgba(2, 132, 199, 0.05) 100%);
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

        .chart-container {
            position: relative;
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        }

        @keyframes pulse-slow {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        .animate-pulse-slow {
            animation: pulse-slow 3s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }

        .mesh-gradient {
            background-color: #0ea5e9;
            background-image: 
                radial-gradient(at 40% 20%, #38bdf8 0px, transparent 50%),
                radial-gradient(at 80% 0%, #0284c7 0px, transparent 50%),
                radial-gradient(at 0% 50%, #0ea5e9 0px, transparent 50%),
                radial-gradient(at 80% 50%, #0369a1 0px, transparent 50%),
                radial-gradient(at 0% 100%, #0ea5e9 0px, transparent 50%),
                radial-gradient(at 80% 100%, #0284c7 0px, transparent 50%),
                radial-gradient(at 0% 0%, #38bdf8 0px, transparent 50%);
        }

        .table-row-hover {
            transition: all 0.2s ease;
        }

        .table-row-hover:hover {
            background: linear-gradient(90deg, rgba(14, 165, 233, 0.05) 0%, rgba(14, 165, 233, 0.02) 100%);
            transform: scale(1.01);
        }

        .badge-glow {
            box-shadow: 0 0 20px rgba(14, 165, 233, 0.3);
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
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 via-blue-50/30 to-slate-50 text-slate-800 font-sans antialiased selection:bg-primary-100 selection:text-primary-900">

    <div class="flex h-screen overflow-hidden">
        
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <main class="flex-1 flex flex-col h-screen overflow-hidden relative md:ml-72 transition-all duration-300">
            
            <header class="glass-header h-20 px-6 md:px-8 flex items-center justify-between sticky top-0 z-20">
                <div class="flex items-center gap-4">
                    <button onclick="toggleSidebar()" class="md:hidden w-10 h-10 flex items-center justify-center bg-white rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 shadow-sm transition-all">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div>
                        <h2 class="text-xl font-bold text-slate-800">Dashboard</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Monitoring Kelas <?= htmlspecialchars($kelas_info['nama_kelas']) ?></p>
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

                    <button class="w-10 h-10 flex items-center justify-center bg-white rounded-xl border border-slate-200 text-slate-600 hover:text-primary-600 hover:border-primary-300 transition-all relative shadow-sm group">
                        <i class="fas fa-bell group-hover:scale-110 transition-transform"></i>
                        <span class="absolute top-2 right-2.5 w-2.5 h-2.5 bg-red-500 rounded-full border-2 border-white animate-pulse"></span>
                    </button>
                </div>
            </header>

            <div class="flex-1 overflow-y-auto p-6 md:p-8 pb-20">
                
                <!-- Welcome Banner -->
                <div class="relative rounded-3xl p-8 md:p-10 text-white overflow-hidden shadow-2xl shadow-primary-900/20 mb-8 animate-fade-in mesh-gradient">
                    <div class="absolute inset-0 bg-gradient-to-br from-primary-600/90 via-primary-700/90 to-primary-900/90"></div>
                    <div class="absolute top-0 right-0 w-96 h-96 bg-accent-400/20 rounded-full blur-3xl -mr-32 -mt-32 animate-pulse-slow"></div>
                    <div class="absolute bottom-0 left-0 w-64 h-64 bg-white/10 rounded-full blur-3xl -ml-20 -mb-20"></div>
                    
                    <div class="relative z-10 flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
                        <div class="space-y-3">
                            <div class="inline-flex items-center gap-2 bg-white/10 backdrop-blur-sm px-3 py-1.5 rounded-full border border-white/20">
                                <i class="fas fa-sparkles text-accent-400 text-xs"></i>
                                <span class="text-xs font-medium">Monitoring Aktif</span>
                            </div>
                            <h3 class="text-3xl md:text-4xl font-bold leading-tight">Selamat Datang, Wali Kelas! 👋</h3>
                            <p class="text-primary-100 max-w-xl text-sm md:text-base leading-relaxed">
                                Pantau perkembangan siswa kelas <span class="font-semibold text-white"><?= htmlspecialchars($kelas_info['nama_kelas']) ?></span> dengan mudah. 
                                Sistem penilaian menggunakan bobot: <span class="font-semibold">70% Akademik</span>, <span class="font-semibold">15% Kehadiran</span>, <span class="font-semibold">15% Ekstrakurikuler</span>.
                            </p>
                        </div>
                        <div class="flex flex-col sm:flex-row gap-3">
                            <a href="export_excel.php?kelas=<?= $kelas_id ?>" class="group bg-white/10 hover:bg-white/20 backdrop-blur-sm text-white px-5 py-3 rounded-xl text-sm font-semibold transition-all border border-white/20 hover:border-white/40 flex items-center gap-2">
                                <i class="fas fa-print group-hover:scale-110 transition-transform"></i>
                                <span>Cetak Laporan</span>
                            </a>
                            <a href="import_excel.php" class="group bg-accent-500 hover:bg-accent-600 text-white px-5 py-3 rounded-xl text-sm font-semibold shadow-xl shadow-accent-500/40 transition-all flex items-center gap-2">
                                <i class="fas fa-upload group-hover:scale-110 transition-transform"></i>
                                <span>Import Nilai</span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <!-- Card 1: Total Siswa -->
                    <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm card-hover group relative overflow-hidden animate-slide-up" style="animation-delay: 0.1s;">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-br from-blue-500/10 to-transparent rounded-full blur-2xl -mr-16 -mt-16"></div>
                        <div class="relative">
                            <div class="flex justify-between items-start mb-4">
                                <div class="p-3.5 bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl group-hover:from-blue-100 group-hover:to-blue-200 transition-all shadow-sm">
                                    <i class="fas fa-users text-blue-600 text-xl"></i>
                                </div>
                                <span class="text-xs font-semibold text-blue-600 bg-blue-50 px-2 py-1 rounded-lg">Aktif</span>
                            </div>
                            <h4 class="text-4xl font-bold text-slate-800 mt-4"><?= $total_siswa ?></h4>
                            <p class="text-sm text-slate-500 mt-2 font-medium">Total Siswa Terdaftar</p>
                        </div>
                    </div>

                    <!-- Card 2: Rata-rata Nilai -->
                    <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm card-hover group relative overflow-hidden animate-slide-up" style="animation-delay: 0.2s;">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-br from-amber-500/10 to-transparent rounded-full blur-2xl -mr-16 -mt-16"></div>
                        <div class="relative">
                            <div class="flex justify-between items-start mb-4">
                                <div class="p-3.5 bg-gradient-to-br from-amber-50 to-amber-100 rounded-xl group-hover:from-amber-100 group-hover:to-amber-200 transition-all shadow-sm">
                                    <i class="fas fa-trophy text-amber-600 text-xl"></i>
                                </div>
                                <span class="text-xs font-semibold text-amber-600 bg-amber-50 px-2 py-1 rounded-lg">Weighted</span>
                            </div>
                            <h4 class="text-4xl font-bold text-slate-800 mt-4"><?= number_format($kelas_avg, 1) ?></h4>
                            <p class="text-sm text-slate-500 mt-2 font-medium">Rata-rata Nilai Kelas</p>
                        </div>
                    </div>

                    <!-- Card 3: Kehadiran -->
                    <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm card-hover group relative overflow-hidden animate-slide-up" style="animation-delay: 0.3s;">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-br from-emerald-500/10 to-transparent rounded-full blur-2xl -mr-16 -mt-16"></div>
                        <?php
                        $total_hadir = $kehadiran_stats['total_hadir'] ?? 0;
                        $total_tidak_hadir = ($kehadiran_stats['total_sakit'] ?? 0) + ($kehadiran_stats['total_izin'] ?? 0) + ($kehadiran_stats['total_alpa'] ?? 0);
                        $persen_hadir = $total_hadir + $total_tidak_hadir > 0 ? round(($total_hadir / ($total_hadir + $total_tidak_hadir)) * 100, 1) : 0;
                        ?>
                        <div class="relative">
                            <div class="flex justify-between items-start mb-4">
                                <div class="p-3.5 bg-gradient-to-br from-emerald-50 to-emerald-100 rounded-xl group-hover:from-emerald-100 group-hover:to-emerald-200 transition-all shadow-sm">
                                    <i class="fas fa-check-circle text-emerald-600 text-xl"></i>
                                </div>
                                <span class="text-xs font-semibold text-emerald-600 bg-emerald-50 px-2 py-1 rounded-lg"><?= $persen_hadir ?>%</span>
                            </div>
                            <h4 class="text-4xl font-bold text-slate-800 mt-4"><?= $persen_hadir ?>%</h4>
                            <p class="text-sm text-slate-500 mt-2 font-medium">Tingkat Kehadiran</p>
                        </div>
                    </div>

                    <!-- Card 4: Perlu Bimbingan -->
                    <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm card-hover group relative overflow-hidden animate-slide-up" style="animation-delay: 0.4s;">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-br from-red-500/10 to-transparent rounded-full blur-2xl -mr-16 -mt-16"></div>
                        <div class="relative">
                            <div class="flex justify-between items-start mb-4">
                                <div class="p-3.5 bg-gradient-to-br from-red-50 to-red-100 rounded-xl group-hover:from-red-100 group-hover:to-red-200 transition-all shadow-sm">
                                    <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                                </div>
                                <span class="text-xs font-semibold text-red-600 bg-red-50 px-2 py-1 rounded-lg">Alert</span>
                            </div>
                            <h4 class="text-4xl font-bold text-slate-800 mt-4"><?= $perlu_bimbingan ?></h4>
                            <p class="text-sm text-slate-500 mt-2 font-medium">Siswa Perlu Bimbingan</p>
                        </div>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                    <!-- Main Chart -->
                    <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm lg:col-span-2 animate-slide-up" style="animation-delay: 0.5s;">
                        <div class="flex justify-between items-center mb-6">
                            <div>
                                <h3 class="font-bold text-slate-800 text-lg">Grafik Rata-rata Nilai per Mapel</h3>
                                <p class="text-xs text-slate-500 mt-1">Performa akademik berdasarkan mata pelajaran</p>
                            </div>
                            <select class="bg-slate-50 border border-slate-200 text-xs rounded-lg px-3 py-2 text-slate-600 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent font-medium">
                                <option><?= htmlspecialchars($kelas_info['semester']) ?></option>
                                <option>Genap</option>
                            </select>
                        </div>
                        <div class="h-72 w-full chart-container rounded-xl p-4">
                            <canvas id="barChart"></canvas>
                        </div>
                    </div>

                    <!-- Side Chart -->
                    <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm animate-slide-up" style="animation-delay: 0.6s;">
                        <div class="flex justify-between items-center mb-6">
                            <div>
                                <h3 class="font-bold text-slate-800 text-lg">Distribusi Nilai</h3>
                                <p class="text-xs text-slate-500 mt-1">Sebaran predikat siswa</p>
                            </div>
                            <button class="text-slate-400 hover:text-primary-600 transition-colors p-2 hover:bg-slate-50 rounded-lg">
                                <i class="fas fa-ellipsis-h"></i>
                            </button>
                        </div>
                        <div class="h-56 w-full flex justify-center items-center">
                            <canvas id="doughnutChart"></canvas>
                        </div>
                        <div class="mt-6 space-y-2">
                            <div class="flex items-center justify-between p-2 rounded-lg hover:bg-slate-50 transition-colors">
                                <div class="flex items-center gap-2">
                                    <span class="w-3 h-3 rounded-full bg-gradient-to-br from-emerald-400 to-emerald-600"></span>
                                    <span class="text-xs font-medium text-slate-700">A (90-100)</span>
                                </div>
                                <span class="text-xs font-bold text-slate-800"><?= $distribusi_nilai['a_count'] ?? 0 ?></span>
                            </div>
                            <div class="flex items-center justify-between p-2 rounded-lg hover:bg-slate-50 transition-colors">
                                <div class="flex items-center gap-2">
                                    <span class="w-3 h-3 rounded-full bg-gradient-to-br from-blue-400 to-blue-600"></span>
                                    <span class="text-xs font-medium text-slate-700">B (80-89)</span>
                                </div>
                                <span class="text-xs font-bold text-slate-800"><?= $distribusi_nilai['b_count'] ?? 0 ?></span>
                            </div>
                            <div class="flex items-center justify-between p-2 rounded-lg hover:bg-slate-50 transition-colors">
                                <div class="flex items-center gap-2">
                                    <span class="w-3 h-3 rounded-full bg-gradient-to-br from-amber-400 to-amber-600"></span>
                                    <span class="text-xs font-medium text-slate-700">C (70-79)</span>
                                </div>
                                <span class="text-xs font-bold text-slate-800"><?= $distribusi_nilai['c_count'] ?? 0 ?></span>
                            </div>
                            <div class="flex items-center justify-between p-2 rounded-lg hover:bg-slate-50 transition-colors">
                                <div class="flex items-center gap-2">
                                    <span class="w-3 h-3 rounded-full bg-gradient-to-br from-red-400 to-red-600"></span>
                                    <span class="text-xs font-medium text-slate-700">D/E (<70)</span>
                                </div>
                                <span class="text-xs font-bold text-slate-800"><?= ($distribusi_nilai['d_count'] ?? 0) + ($distribusi_nilai['e_count'] ?? 0) ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top Students & Quick Actions -->
                <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm xl:col-span-2 overflow-hidden animate-slide-up" style="animation-delay: 0.7s;">
                        <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-gradient-to-r from-slate-50 to-white">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-accent-400 to-accent-600 flex items-center justify-center shadow-lg shadow-accent-500/30">
                                    <i class="fas fa-medal text-white"></i>
                                </div>
                                <div>
                                    <h3 class="font-bold text-slate-800">Top 5 Siswa Berprestasi</h3>
                                    <p class="text-xs text-slate-500 mt-0.5">Berdasarkan nilai akhir terbobot</p>
                                </div>
                            </div>
                            <a href="leger.php?kelas=<?= $kelas_id ?>" class="text-xs text-primary-600 font-semibold hover:text-primary-700 flex items-center gap-1 group">
                                Lihat Semua
                                <i class="fas fa-arrow-right text-xs group-hover:translate-x-1 transition-transform"></i>
                            </a>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left">
                                <thead class="text-xs text-slate-600 uppercase bg-slate-50/80 backdrop-blur-sm">
                                    <tr>
                                        <th class="px-6 py-4 font-semibold">Peringkat</th>
                                        <th class="px-6 py-4 font-semibold">Nama Siswa</th>
                                        <th class="px-6 py-4 font-semibold text-center">Nilai Akhir</th>
                                        <th class="px-6 py-4 font-semibold text-center">Predikat</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <?php
                                    $no = 1;
                                    while($row = mysqli_fetch_assoc($top_siswa)):
                                        $final = $row['final_score'];
                                        $predikat = getPredikatLabel($final);
                                        $badgeClass = match($predikat) {
                                            'A' => 'bg-gradient-to-br from-emerald-400 to-emerald-600 text-white',
                                            'B' => 'bg-gradient-to-br from-blue-400 to-blue-600 text-white',
                                            'C' => 'bg-gradient-to-br from-amber-400 to-amber-600 text-white',
                                            default => 'bg-gradient-to-br from-red-400 to-red-600 text-white'
                                        };
                                        $rankIcon = match($no) {
                                            1 => 'fa-crown',
                                            2 => 'fa-medal',
                                            3 => 'fa-award',
                                            default => 'fa-star'
                                        };
                                        $rankColor = match($no) {
                                            1 => 'from-accent-400 to-accent-600',
                                            2 => 'from-slate-300 to-slate-500',
                                            3 => 'from-amber-600 to-amber-800',
                                            default => 'from-slate-200 to-slate-400'
                                        };
                                    ?>
                                    <tr class="table-row-hover">
                                        <td class="px-6 py-4">
                                            <div class="flex items-center justify-center w-9 h-9 bg-gradient-to-br <?= $rankColor ?> rounded-xl font-bold text-xs text-white shadow-lg">
                                                <i class="fas <?= $rankIcon ?>"></i>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-3">
                                                <img src="https://ui-avatars.com/api/?name=<?= urlencode($row['nama_siswa']) ?>&background=0ea5e9&color=fff&bold=true" alt="Avatar" class="w-9 h-9 rounded-full ring-2 ring-primary-100">
                                                <span class="font-semibold text-slate-800"><?= htmlspecialchars(strtoupper($row['nama_siswa'])) ?></span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <span class="font-bold text-lg gradient-text"><?= number_format($final, 2) ?></span>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <span class="px-3 py-1.5 <?= $badgeClass ?> rounded-lg text-xs font-bold shadow-md"><?= $predikat ?></span>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="p-4 bg-gradient-to-r from-slate-50 to-white text-xs text-slate-600 border-t border-slate-100 flex items-center gap-2">
                            <i class="fas fa-info-circle text-primary-500"></i>
                            <span>Nilai dihitung dengan bobot: 70% Akademik + 15% Kehadiran + 15% Ekstrakurikuler</span>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6 flex flex-col justify-between animate-slide-up" style="animation-delay: 0.8s;">
                        <div>
                            <div class="flex items-center gap-3 mb-6">
                                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center shadow-lg shadow-primary-500/30">
                                    <i class="fas fa-bolt text-white"></i>
                                </div>
                                <div>
                                    <h3 class="font-bold text-slate-800">Aksi Cepat</h3>
                                    <p class="text-xs text-slate-500 mt-0.5">Akses fitur utama</p>
                                </div>
                            </div>
                            <div class="space-y-3">
                                <a href="import_excel.php" class="w-full flex items-center gap-3 p-4 rounded-xl bg-gradient-to-r from-slate-50 to-white hover:from-primary-50 hover:to-blue-50 hover:border-primary-200 border border-slate-100 text-slate-700 transition-all group">
                                    <div class="w-10 h-10 rounded-xl bg-white shadow-sm flex items-center justify-center text-primary-600 group-hover:shadow-md transition-all">
                                        <i class="fas fa-file-import"></i>
                                    </div>
                                    <span class="text-sm font-semibold flex-1">Import Data Excel</span>
                                    <i class="fas fa-chevron-right text-xs opacity-0 group-hover:opacity-100 group-hover:translate-x-1 transition-all text-primary-600"></i>
                                </a>
                                <a href="export_excel.php?kelas=<?= $kelas_id ?>" class="w-full flex items-center gap-3 p-4 rounded-xl bg-gradient-to-r from-slate-50 to-white hover:from-primary-50 hover:to-blue-50 hover:border-primary-200 border border-slate-100 text-slate-700 transition-all group">
                                    <div class="w-10 h-10 rounded-xl bg-white shadow-sm flex items-center justify-center text-primary-600 group-hover:shadow-md transition-all">
                                        <i class="fas fa-print"></i>
                                    </div>
                                    <span class="text-sm font-semibold flex-1">Cetak Leger Kelas</span>
                                    <i class="fas fa-chevron-right text-xs opacity-0 group-hover:opacity-100 group-hover:translate-x-1 transition-all text-primary-600"></i>
                                </a>
                                <a href="grafik_nilai.php?kelas=<?= $kelas_id ?>" class="w-full flex items-center gap-3 p-4 rounded-xl bg-gradient-to-r from-slate-50 to-white hover:from-primary-50 hover:to-blue-50 hover:border-primary-200 border border-slate-100 text-slate-700 transition-all group">
                                    <div class="w-10 h-10 rounded-xl bg-white shadow-sm flex items-center justify-center text-primary-600 group-hover:shadow-md transition-all">
                                        <i class="fas fa-chart-bar"></i>
                                    </div>
                                    <span class="text-sm font-semibold flex-1">Lihat Analitik Lengkap</span>
                                    <i class="fas fa-chevron-right text-xs opacity-0 group-hover:opacity-100 group-hover:translate-x-1 transition-all text-primary-600"></i>
                                </a>
                            </div>
                        </div>
                        
                        <div class="mt-6 bg-gradient-to-br from-primary-700 via-primary-800 to-primary-900 rounded-2xl p-5 text-white relative overflow-hidden shadow-xl shadow-primary-900/30">
                            <div class="absolute top-0 right-0 w-32 h-32 bg-accent-400/20 rounded-full blur-2xl -mr-16 -mt-16"></div>
                            <i class="fas fa-user-tie absolute -bottom-4 -right-4 text-7xl text-white/10"></i>
                            <div class="relative">
                                <div class="flex items-center gap-2 mb-3">
                                    <i class="fas fa-info-circle text-accent-400"></i>
                                    <h4 class="font-bold text-sm">Info Wali Kelas</h4>
                                </div>
                                <p class="text-base font-bold mb-1"><?= htmlspecialchars($kelas_info['wali_kelas']) ?></p>
                                <p class="text-xs text-primary-200">Kelas <?= htmlspecialchars($kelas_info['nama_kelas']) ?></p>
                                <p class="text-xs text-primary-300 mt-1">TP <?= htmlspecialchars($kelas_info['tahun_ajaran']) ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <footer class="mt-12 text-center text-xs text-slate-500 pb-4">
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
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            sidebar.classList.toggle('hidden');
            sidebar.classList.toggle('fixed');
            sidebar.classList.toggle('inset-0');
            overlay.classList.toggle('hidden');
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Chart 1: Rata-rata per Mapel
            const ctxBar = document.getElementById('barChart').getContext('2d');
            const gradient = ctxBar.createLinearGradient(0, 0, 0, 400);
            gradient.addColorStop(0, 'rgba(14, 165, 233, 0.8)');
            gradient.addColorStop(1, 'rgba(2, 132, 199, 0.6)');

            new Chart(ctxBar, {
                type: 'bar',
                data: {
                    labels: <?= json_encode(array_column($mapel_data, 'label')) ?>,
                    datasets: [{
                        label: 'Rata-rata Nilai',
                        data: <?= json_encode(array_column($mapel_data, 'val')) ?>,
                        backgroundColor: gradient,
                        hoverBackgroundColor: 'rgba(2, 132, 199, 0.9)',
                        borderRadius: 12,
                        borderSkipped: false,
                        barPercentage: 0.7,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(15, 23, 42, 0.95)',
                            padding: 12,
                            titleFont: { size: 13, family: "'Plus Jakarta Sans'", weight: '600' },
                            bodyFont: { size: 12, family: "'Plus Jakarta Sans'" },
                            cornerRadius: 12,
                            displayColors: false,
                            callbacks: {
                                label: function(context) {
                                    return 'Nilai: ' + context.parsed.y;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            grid: { 
                                color: 'rgba(226, 232, 240, 0.5)',
                                drawBorder: false
                            },
                            ticks: { 
                                font: { family: "'Plus Jakarta Sans'", weight: '500' },
                                color: '#64748b',
                                padding: 8
                            }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { 
                                font: { family: "'Plus Jakarta Sans'", weight: '600' },
                                color: '#475569',
                                padding: 8
                            }
                        }
                    }
                }
            });

            // Chart 2: Distribusi Nilai
            const ctxDoughnut = document.getElementById('doughnutChart').getContext('2d');
            new Chart(ctxDoughnut, {
                type: 'doughnut',
                data: {
                    labels: ['A', 'B', 'C', 'D/E'],
                    datasets: [{
                        data: [
                            <?= $distribusi_nilai['a_count'] ?? 0 ?>,
                            <?= $distribusi_nilai['b_count'] ?? 0 ?>,
                            <?= $distribusi_nilai['c_count'] ?? 0 ?>,
                            <?= ($distribusi_nilai['d_count'] ?? 0) + ($distribusi_nilai['e_count'] ?? 0) ?>
                        ],
                        backgroundColor: [
                            'rgba(16, 185, 129, 0.9)',
                            'rgba(59, 130, 246, 0.9)',
                            'rgba(245, 158, 11, 0.9)',
                            'rgba(239, 68, 68, 0.9)'
                        ],
                        borderWidth: 0,
                        hoverOffset: 8,
                        spacing: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '70%',
                    plugins: { 
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(15, 23, 42, 0.95)',
                            padding: 12,
                            titleFont: { size: 13, family: "'Plus Jakarta Sans'", weight: '600' },
                            bodyFont: { size: 12, family: "'Plus Jakarta Sans'" },
                            cornerRadius: 12,
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>