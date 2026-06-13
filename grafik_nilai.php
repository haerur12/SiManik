<?php
require 'config.php';
if(!isset($_SESSION['login'])) {
    header("Location: index.php");
    exit;
}

// Filter Kelas
$kelas_id = isset($_GET['kelas']) ? (int)$_GET['kelas'] : 1;
$kelas_info = mysqli_query($conn, "SELECT * FROM kelas WHERE id = $kelas_id")->fetch_assoc();

// Sidebar variables
$current_page = basename($_SERVER['PHP_SELF']);
function isActive($page, $current) {
    return $page === $current ? 'active' : '';
}
$user_name = $_SESSION['nama_lengkap'] ?? 'Wali Kelas';
$user_level = ucfirst($_SESSION['level'] ?? 'guru');

// ============================================
// 🔢 WEIGHTED SCORING CALCULATION
// Rumus: Nilai Akhir = (Akademik×70%) + (Absensi×15%) + (Ekskul×15%)
// Absensi = 100 - (Alpa×5 + Izin×2 + Sakit×1)
// Ekskul: A=90, B=80, C=70, else=60
// ============================================

// 1. Total Siswa
$total_siswa = mysqli_query($conn, "SELECT COUNT(*) as total FROM siswa WHERE kelas_id = $kelas_id")->fetch_assoc()['total'] ?? 0;

// 2. Statistik Kehadiran
$kehadiran_stats = mysqli_query($conn, "
    SELECT SUM(sakit) as total_sakit, SUM(izin) as total_izin, SUM(alpa) as total_alpa, SUM(hadir) as total_hadir
    FROM kehadiran WHERE kelas_id = $kelas_id
")->fetch_assoc() ?? ['total_sakit'=>0, 'total_izin'=>0, 'total_alpa'=>0, 'total_hadir'=>0];

// 3. Rata-rata Kelas (Weighted) - SUBQUERY untuk menghindari nested aggregate
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

// 4. Distribusi Nilai (untuk chart)
$distribusi_nilai = mysqli_query($conn, "
SELECT
    SUM(CASE WHEN nilai_angka >= 90 THEN 1 ELSE 0 END) as a_count,
    SUM(CASE WHEN nilai_angka >= 80 AND nilai_angka < 90 THEN 1 ELSE 0 END) as b_count,
    SUM(CASE WHEN nilai_angka >= 70 AND nilai_angka < 80 THEN 1 ELSE 0 END) as c_count,
    SUM(CASE WHEN nilai_angka >= 60 AND nilai_angka < 70 THEN 1 ELSE 0 END) as d_count,
    SUM(CASE WHEN nilai_angka < 60 THEN 1 ELSE 0 END) as e_count
FROM nilai WHERE kelas_id = $kelas_id
")->fetch_assoc() ?? ['a_count'=>0, 'b_count'=>0, 'c_count'=>0, 'd_count'=>0, 'e_count'=>0];

// 5. Top 10 Siswa dengan Weighted Scoring - NAMA LENGKAP
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
LIMIT 10
");

// 6. Rata-rata per Mapel (untuk chart)
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

// 7. Siswa Perlu Bimbingan (Nilai D/E ATAU Alpa > 3)
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

// Helper function untuk predikat
function getPredikatLabel($nilai) {
    if($nilai >= 90) return 'A';
    if($nilai >= 80) return 'B';
    if($nilai >= 70) return 'C';
    if($nilai >= 60) return 'D';
    return 'E';
}
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grafik & Analitik - SiManik</title>
    
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
        
        .card-hover { transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
        .card-hover:hover { transform: translateY(-4px); box-shadow: 0 20px 40px -12px rgba(0, 0, 0, 0.15); }
        
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

        .chart-container { position: relative; background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%); }

        .sidebar-item { position: relative; overflow: hidden; }
        .sidebar-item::before { content: ''; position: absolute; left: 0; top: 0; height: 100%; width: 3px; background: linear-gradient(180deg, #0ea5e9, #0284c7); transform: scaleY(0); transition: transform 0.3s ease; }
        .sidebar-item:hover::before, .sidebar-item.active::before { transform: scaleY(1); }
        .sidebar-item.active { background: linear-gradient(to right, rgba(14, 165, 233, 0.1), transparent); }
        .sidebar-item.active .w-8 { background: linear-gradient(135deg, #0ea5e9, #0284c7) !important; }
        .sidebar-item.active i { color: white !important; }

        /* Scrollbar untuk sidebar */
        aside::-webkit-scrollbar { width: 4px; }
        aside::-webkit-scrollbar-track { background: transparent; }
        aside::-webkit-scrollbar-thumb { background: rgba(14, 165, 233, 0.3); border-radius: 10px; }
        aside::-webkit-scrollbar-thumb:hover { background: rgba(14, 165, 233, 0.5); }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 via-blue-50/30 to-slate-50 text-slate-800 font-sans antialiased selection:bg-primary-100 selection:text-primary-900">

    <div class="flex h-screen overflow-hidden">
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
                        <h2 class="text-xl font-bold text-slate-800">Grafik & Analitik</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Visualisasi data akademik kelas <?= htmlspecialchars($kelas_info['nama_kelas']) ?></p>
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
                    <a href="dashboard.php?kelas=<?= $kelas_id ?>" class="flex items-center gap-2 px-4 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 hover:border-primary-300 text-slate-700 rounded-xl text-sm font-semibold transition-all shadow-sm group">
                        <i class="fas fa-arrow-left group-hover:-translate-x-1 transition-transform"></i> Kembali
                    </a>
                </div>
            </header>

            <!-- Scrollable Content -->
            <div class="flex-1 overflow-y-auto p-6 md:p-8 pb-20">
                
                <!-- Stats Row -->
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

                    <!-- Card 2: Rata-rata Kelas (Weighted) -->
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
                            <p class="text-[10px] text-slate-400 mt-1">70% Akademik + 15% Absensi + 15% Ekstra</p>
                        </div>
                    </div>

                    <!-- Card 3: Nilai A (Sangat Baik) -->
                    <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm card-hover group relative overflow-hidden animate-slide-up" style="animation-delay: 0.3s;">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-br from-emerald-500/10 to-transparent rounded-full blur-2xl -mr-16 -mt-16"></div>
                        <div class="relative">
                            <div class="flex justify-between items-start mb-4">
                                <div class="p-3.5 bg-gradient-to-br from-emerald-50 to-emerald-100 rounded-xl group-hover:from-emerald-100 group-hover:to-emerald-200 transition-all shadow-sm">
                                    <i class="fas fa-award text-emerald-600 text-xl"></i>
                                </div>
                                <span class="text-xs font-semibold text-emerald-600 bg-emerald-50 px-2 py-1 rounded-lg">Excellent</span>
                            </div>
                            <h4 class="text-4xl font-bold text-slate-800 mt-4"><?= $distribusi_nilai['a_count'] ?? 0 ?></h4>
                            <p class="text-sm text-slate-500 mt-2 font-medium">Siswa dengan Predikat A</p>
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
                            <p class="text-sm text-slate-500 mt-2 font-medium">Perlu Bimbingan Khusus</p>
                            <p class="text-[10px] text-slate-400 mt-1">Nilai &lt;70 atau Alpa &gt;3</p>
                        </div>
                    </div>
                </div>

                <!-- Charts Row 1 -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <!-- Main Chart: Rata-rata per Mapel -->
                    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6 animate-slide-up" style="animation-delay: 0.5s;">
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
                            <canvas id="mapelChart"></canvas>
                        </div>
                    </div>

                    <!-- Side Chart: Distribusi Nilai -->
                    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6 animate-slide-up" style="animation-delay: 0.6s;">
                        <div class="flex justify-between items-center mb-6">
                            <div>
                                <h3 class="font-bold text-slate-800 text-lg">Distribusi Predikat</h3>
                                <p class="text-xs text-slate-500 mt-1">Sebaran nilai siswa</p>
                            </div>
                            <button class="text-slate-400 hover:text-primary-600 transition-colors p-2 hover:bg-slate-50 rounded-lg">
                                <i class="fas fa-ellipsis-h"></i>
                            </button>
                        </div>
                        <div class="h-64 w-full flex justify-center items-center">
                            <canvas id="distribusiChart"></canvas>
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
                                    <span class="w-3 h-3 rounded-full bg-gradient-to-br from-orange-400 to-orange-600"></span>
                                    <span class="text-xs font-medium text-slate-700">D (60-69)</span>
                                </div>
                                <span class="text-xs font-bold text-slate-800"><?= $distribusi_nilai['d_count'] ?? 0 ?></span>
                            </div>
                            <div class="flex items-center justify-between p-2 rounded-lg hover:bg-slate-50 transition-colors">
                                <div class="flex items-center gap-2">
                                    <span class="w-3 h-3 rounded-full bg-gradient-to-br from-red-400 to-red-600"></span>
                                    <span class="text-xs font-medium text-slate-700">E (&lt;60)</span>
                                </div>
                                <span class="text-xs font-bold text-slate-800"><?= $distribusi_nilai['e_count'] ?? 0 ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row 2 -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Top Siswa Horizontal Bar -->
                    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6 animate-slide-up" style="animation-delay: 0.7s;">
                        <div class="flex justify-between items-center mb-6">
                            <div>
                                <h3 class="font-bold text-slate-800 text-lg flex items-center gap-2">
                                    <i class="fas fa-medal text-accent-500"></i>
                                    Top 10 Siswa (Weighted)
                                </h3>
                                <p class="text-xs text-slate-500 mt-1">Peringkat berdasarkan nilai akhir terbobot</p>
                            </div>
                            <button class="text-slate-400 hover:text-primary-600 transition-colors p-2 hover:bg-slate-50 rounded-lg">
                                <i class="fas fa-ellipsis-h"></i>
                            </button>
                        </div>
                        <div class="h-80 w-full chart-container rounded-xl p-4">
                            <canvas id="topSiswaChart"></canvas>
                        </div>
                        <div class="mt-4 p-3 bg-gradient-to-r from-slate-50 to-white rounded-lg border border-slate-100">
                            <p class="text-[10px] text-slate-500 text-center flex items-center justify-center gap-2">
                                <i class="fas fa-info-circle text-primary-500"></i>
                                Nilai dihitung: 70% Akademik + 15% Absensi + 15% Ekstra
                            </p>
                        </div>
                    </div>

                    <!-- Kehadiran Pie Chart -->
                    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6 animate-slide-up" style="animation-delay: 0.8s;">
                        <div class="flex justify-between items-center mb-6">
                            <div>
                                <h3 class="font-bold text-slate-800 text-lg flex items-center gap-2">
                                    <i class="fas fa-calendar-check text-emerald-500"></i>
                                    Statistik Kehadiran
                                </h3>
                                <p class="text-xs text-slate-500 mt-1">Distribusi kehadiran siswa</p>
                            </div>
                            <button class="text-slate-400 hover:text-primary-600 transition-colors p-2 hover:bg-slate-50 rounded-lg">
                                <i class="fas fa-ellipsis-h"></i>
                            </button>
                        </div>
                        <div class="h-80 w-full flex justify-center items-center chart-container rounded-xl p-4">
                            <canvas id="kehadiranChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Info Footer -->
                <div class="mt-8 bg-white rounded-2xl border border-slate-100 shadow-sm p-6 animate-slide-up" style="animation-delay: 0.9s;">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center shadow-lg shadow-primary-500/30">
                            <i class="fas fa-calculator text-white"></i>
                        </div>
                        <div>
                            <h4 class="font-bold text-slate-800 text-lg">Rumus Perhitungan Weighted Scoring</h4>
                            <p class="text-xs text-slate-500 mt-0.5">Metode penilaian akhir siswa</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="p-5 bg-gradient-to-br from-blue-50 to-blue-100/50 rounded-xl border border-blue-200 hover:shadow-lg transition-all group">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center shadow-lg shadow-blue-500/30">
                                    <i class="fas fa-book text-white"></i>
                                </div>
                                <div>
                                    <p class="font-bold text-slate-800">Nilai Akademik</p>
                                    <p class="text-2xl font-bold gradient-text">70%</p>
                                </div>
                            </div>
                            <p class="text-sm text-slate-600 leading-relaxed">Rata-rata nilai dari semua mata pelajaran yang diambil siswa</p>
                        </div>
                        <div class="p-5 bg-gradient-to-br from-emerald-50 to-emerald-100/50 rounded-xl border border-emerald-200 hover:shadow-lg transition-all group">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-emerald-500 to-emerald-600 flex items-center justify-center shadow-lg shadow-emerald-500/30">
                                    <i class="fas fa-check-circle text-white"></i>
                                </div>
                                <div>
                                    <p class="font-bold text-slate-800">Kehadiran</p>
                                    <p class="text-2xl font-bold gradient-text">15%</p>
                                </div>
                            </div>
                            <p class="text-sm text-slate-600 leading-relaxed">100 - (Alpa×5 + Izin×2 + Sakit×1)</p>
                        </div>
                        <div class="p-5 bg-gradient-to-br from-amber-50 to-amber-100/50 rounded-xl border border-amber-200 hover:shadow-lg transition-all group">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-amber-500 to-amber-600 flex items-center justify-center shadow-lg shadow-amber-500/30">
                                    <i class="fas fa-trophy text-white"></i>
                                </div>
                                <div>
                                    <p class="font-bold text-slate-800">Ekstrakurikuler</p>
                                    <p class="text-2xl font-bold gradient-text">15%</p>
                                </div>
                            </div>
                            <p class="text-sm text-slate-600 leading-relaxed">A=90, B=80, C=70, lainnya=60</p>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <footer class="mt-8 text-center text-xs text-slate-500 pb-4">
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

        // Chart Data Injection
        const mapelLabels = <?= json_encode(array_column($mapel_data, 'label')) ?>;
        const mapelValues = <?= json_encode(array_column($mapel_data, 'val')) ?>;
        
        // Top Siswa: Nama LENGKAP + Nilai FINAL (weighted)
        const topSiswaLabels = [<?php
        mysqli_data_seek($top_siswa, 0);
        $labels = [];
        while($row = mysqli_fetch_assoc($top_siswa)) {
            $labels[] = "'".addslashes($row['nama_siswa'])."'";
        }
        echo implode(',', $labels);
        ?>];
        
        const topSiswaValues = [<?php
        mysqli_data_seek($top_siswa, 0);
        $values = [];
        while($row = mysqli_fetch_assoc($top_siswa)) {
            $values[] = number_format($row['final_score'], 2);
        }
        echo implode(',', $values);
        ?>];
        
        const distribusiData = [
            <?= $distribusi_nilai['a_count'] ?? 0 ?>,
            <?= $distribusi_nilai['b_count'] ?? 0 ?>,
            <?= $distribusi_nilai['c_count'] ?? 0 ?>,
            <?= $distribusi_nilai['d_count'] ?? 0 ?>,
            <?= $distribusi_nilai['e_count'] ?? 0 ?>
        ];
        
        const hadirData = [
            <?= $kehadiran_stats['total_hadir'] ?? 0 ?>,
            <?= $kehadiran_stats['total_sakit'] ?? 0 ?>,
            <?= $kehadiran_stats['total_izin'] ?? 0 ?>,
            <?= $kehadiran_stats['total_alpa'] ?? 0 ?>
        ];

        // Chart Defaults
        Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";
        Chart.defaults.plugins.tooltip.cornerRadius = 12;
        Chart.defaults.plugins.tooltip.padding = 12;
        Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(15, 23, 42, 0.95)';
        Chart.defaults.plugins.tooltip.titleFont = { size: 13, weight: '600' };
        Chart.defaults.plugins.tooltip.bodyFont = { size: 12 };

        document.addEventListener('DOMContentLoaded', function() {
            // 1. Mapel Bar Chart (Nilai Akademik Murni)
            const ctxMapel = document.getElementById('mapelChart').getContext('2d');
            const gradientMapel = ctxMapel.createLinearGradient(0, 0, 0, 400);
            gradientMapel.addColorStop(0, 'rgba(14, 165, 233, 0.8)');
            gradientMapel.addColorStop(1, 'rgba(2, 132, 199, 0.6)');

            new Chart(ctxMapel, {
                type: 'bar',
                data: {
                    labels: mapelLabels,
                    datasets: [{
                        label: 'Rata-rata Nilai',
                        data: mapelValues,
                        backgroundColor: gradientMapel,
                        hoverBackgroundColor: 'rgba(2, 132, 199, 0.9)',
                        borderRadius: 12,
                        borderSkipped: false,
                        barPercentage: 0.7
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: { 
                            beginAtZero: true, 
                            max: 100, 
                            grid: { color: 'rgba(226, 232, 240, 0.5)', drawBorder: false }, 
                            ticks: { 
                                color: '#64748b',
                                font: { weight: '500' },
                                padding: 8
                            } 
                        },
                        x: { 
                            grid: { display: false }, 
                            ticks: { 
                                color: '#475569',
                                font: { weight: '600' },
                                padding: 8
                            } 
                        }
                    },
                    plugins: { 
                        legend: { display: false },
                        tooltip: {
                            displayColors: false,
                            callbacks: {
                                label: function(context) {
                                    return 'Nilai: ' + context.parsed.y;
                                }
                            }
                        }
                    }
                }
            });

            // 2. Distribusi Doughnut (Nilai Akademik Murni)
            const ctxDistribusi = document.getElementById('distribusiChart').getContext('2d');
            new Chart(ctxDistribusi, {
                type: 'doughnut',
                data: {
                    labels: ['A', 'B', 'C', 'D', 'E'],
                    datasets: [{
                        data: distribusiData,
                        backgroundColor: [
                            'rgba(16, 185, 129, 0.9)',
                            'rgba(59, 130, 246, 0.9)',
                            'rgba(245, 158, 11, 0.9)',
                            'rgba(249, 115, 22, 0.9)',
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
                            callbacks: {
                                label: function(context) {
                                    return context.label + ': ' + context.parsed + ' siswa';
                                }
                            }
                        }
                    }
                }
            });

            // 3. Top Siswa Horizontal Bar (NILAI WEIGHTED + NAMA LENGKAP)
            const ctxTopSiswa = document.getElementById('topSiswaChart').getContext('2d');
            const gradientTop = ctxTopSiswa.createLinearGradient(0, 0, 400, 0);
            gradientTop.addColorStop(0, 'rgba(234, 179, 8, 0.8)');
            gradientTop.addColorStop(1, 'rgba(202, 138, 4, 0.9)');

            new Chart(ctxTopSiswa, {
                type: 'bar',
                data: {
                    labels: topSiswaLabels,
                    datasets: [{
                        label: 'Nilai Akhir (Weighted)',
                        data: topSiswaValues,
                        backgroundColor: gradientTop,
                        hoverBackgroundColor: 'rgba(202, 138, 4, 1)',
                        borderRadius: 8,
                        borderSkipped: false,
                        barPercentage: 0.6
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: { 
                            beginAtZero: true, 
                            max: 100, 
                            grid: { color: 'rgba(226, 232, 240, 0.5)', drawBorder: false }, 
                            ticks: { 
                                color: '#64748b',
                                font: { weight: '500' },
                                padding: 8
                            } 
                        },
                        y: { 
                            grid: { display: false }, 
                            ticks: { 
                                color: '#334155', 
                                font: { weight: '600', size: 11 },
                                padding: 8
                            } 
                        }
                    },
                    plugins: { 
                        legend: { display: false },
                        tooltip: {
                            displayColors: false,
                            callbacks: {
                                label: function(context) {
                                    return 'Nilai Akhir: ' + context.parsed.x;
                                }
                            }
                        }
                    }
                }
            });

            // 4. Kehadiran Pie
            const ctxKehadiran = document.getElementById('kehadiranChart').getContext('2d');
            new Chart(ctxKehadiran, {
                type: 'pie',
                data: {
                    labels: ['Hadir', 'Sakit', 'Izin', 'Alpa'],
                    datasets: [{
                        data: hadirData,
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
                    plugins: {
                        legend: { 
                            position: 'bottom', 
                            labels: { 
                                padding: 20, 
                                usePointStyle: true, 
                                pointStyle: 'circle',
                                font: { weight: '600', size: 12 },
                                color: '#475569'
                            } 
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.label + ': ' + context.parsed + ' hari';
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>