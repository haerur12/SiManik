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

// 3. Rata-rata Kelas (Weighted) - ✅ PERBAIKAN: Gunakan SUBQUERY
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

// 5. Top 5 Siswa dengan Weighted Scoring
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

// 6. Siswa Perlu Bimbingan (Nilai D/E ATAU Alpa > 3)
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

// 7. Rata-rata per Mapel (untuk chart)
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
    <title>Dashboard Monitoring - SiManik Modern</title>
    
    <!-- Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts: Plus Jakarta Sans (Modern & Clean) -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a',
                        },
                        accent: {
                            400: '#fbbf24',
                            500: '#f59e0b',
                            600: '#d97706',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        
        .glass-header {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(226, 232, 240, 0.6);
        }
        
        .card-hover {
            transition: all 0.3s ease;
        }
        .card-hover:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 font-sans antialiased selection:bg-brand-100 selection:text-brand-900">

    <div class="flex h-screen overflow-hidden">
        
        <!-- Sidebar -->
        <aside id="sidebar" class="hidden md:flex flex-col w-72 bg-slate-900 text-white h-screen shadow-2xl fixed z-30 transition-all duration-300">
            <div class="h-20 flex items-center px-8 border-b border-slate-800 bg-slate-950">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-brand-600 to-brand-800 flex items-center justify-center shadow-lg shadow-brand-900/50">
                        <i class="fas fa-graduation-cap text-accent-400 text-lg"></i>
                    </div>
                    <div>
                        <h1 class="font-bold text-lg tracking-tight leading-none">SiManik</h1>
                        <p class="text-[10px] text-slate-400 font-medium tracking-wider uppercase">SDN CURUG 01 BOJONGSARI</p>
                    </div>
                </div>
            </div>

            <nav class="flex-1 overflow-y-auto py-6 px-4 space-y-1">
                <p class="px-4 text-xs font-semibold text-slate-500 uppercase tracking-widest mb-4">Menu Utama</p>
                
                <a href="dashboard.php?kelas=<?= $kelas_id ?>" class="flex items-center gap-4 px-4 py-3.5 bg-gradient-to-r from-brand-700 to-brand-600 text-white rounded-xl shadow-md shadow-brand-900/40 transition-all group relative overflow-hidden">
                    <div class="absolute inset-0 bg-white/10 translate-x-full group-hover:translate-x-0 transition-transform duration-300"></div>
                    <i class="fas fa-home w-5 text-center text-sm"></i>
                    <span class="font-medium relative z-10">Dashboard</span>
                </a>

                <a href="import_excel.php" class="flex items-center gap-4 px-4 py-3.5 text-slate-400 hover:text-white hover:bg-slate-800 rounded-xl transition-all group">
                    <i class="fas fa-file-excel w-5 text-center group-hover:text-accent-400 transition-colors"></i>
                    <span class="font-medium">Import Data</span>
                </a>

                <a href="leger.php?kelas=<?= $kelas_id ?>" class="flex items-center gap-4 px-4 py-3.5 text-slate-400 hover:text-white hover:bg-slate-800 rounded-xl transition-all group">
                    <i class="fas fa-book-open w-5 text-center group-hover:text-accent-400 transition-colors"></i>
                    <span class="font-medium">Leger Nilai</span>
                </a>

                <a href="grafik_nilai.php?kelas=<?= $kelas_id ?>" class="flex items-center gap-4 px-4 py-3.5 text-slate-400 hover:text-white hover:bg-slate-800 rounded-xl transition-all group">
                    <i class="fas fa-chart-pie w-5 text-center group-hover:text-accent-400 transition-colors"></i>
                    <span class="font-medium">Analitik Grafik</span>
                </a>

                <div class="my-4 border-t border-slate-800"></div>
                <p class="px-4 text-xs font-semibold text-slate-500 uppercase tracking-widest mb-4">Manajemen</p>
                
                <a href="#" class="flex items-center gap-4 px-4 py-3.5 text-slate-400 hover:text-white hover:bg-slate-800 rounded-xl transition-all group">
                    <i class="fas fa-users w-5 text-center group-hover:text-accent-400 transition-colors"></i>
                    <span class="font-medium">Data Siswa</span>
                </a>
                
                <a href="#" class="flex items-center gap-4 px-4 py-3.5 text-slate-400 hover:text-white hover:bg-slate-800 rounded-xl transition-all group">
                    <i class="fas fa-cog w-5 text-center group-hover:text-accent-400 transition-colors"></i>
                    <span class="font-medium">Pengaturan</span>
                </a>
            </nav>

            <div class="p-4 border-t border-slate-800 bg-slate-950">
                <div class="flex items-center gap-3">
                    <img src="https://ui-avatars.com/api/?name=Wali+Kelas&background=f59e0b&color=fff" alt="Avatar" class="w-9 h-9 rounded-full ring-2 ring-slate-700">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-white truncate">Wali Kelas 4A</p>
                        <p class="text-xs text-slate-500 truncate">Admin SDN Curug 01</p>
                    </div>
                    <a href="logout.php" class="text-slate-500 hover:text-red-400 transition-colors">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
        </aside>

        <div id="sidebarOverlay" class="fixed inset-0 bg-slate-900/50 z-20 hidden md:hidden glass-header" onclick="toggleSidebar()"></div>

        <!-- Main Content -->
        <main class="flex-1 flex flex-col h-screen overflow-hidden relative md:ml-72 transition-all duration-300">
            
            <header class="glass-header h-20 px-6 md:px-8 flex items-center justify-between sticky top-0 z-20">
                <div class="flex items-center gap-4">
                    <button onclick="toggleSidebar()" class="md:hidden w-10 h-10 flex items-center justify-center bg-white rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 shadow-sm">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div>
                        <h2 class="text-xl font-bold text-slate-800">Dashboard</h2>
                        <p class="text-xs text-slate-500">Ringkasan data akademik kelas <?= htmlspecialchars($kelas_info['nama_kelas']) ?></p>
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <div class="hidden md:flex items-center bg-slate-100 rounded-lg p-1 border border-slate-200">
                        <?php for($i=1; $i<=4; $i++): ?>
                        <a href="?kelas=<?= $i ?>" class="px-3 py-1.5 text-xs font-semibold <?= $kelas_id==$i ? 'bg-white text-brand-700 rounded shadow-sm' : 'text-slate-500 hover:text-slate-700' ?>">4<?= chr(64+$i) ?></a>
                        <?php endfor; ?>
                    </div>

                    <button class="w-10 h-10 flex items-center justify-center bg-white rounded-full border border-slate-200 text-slate-600 hover:text-brand-600 hover:border-brand-200 transition-all relative shadow-sm group">
                        <i class="fas fa-bell group-hover:animate-swing"></i>
                        <span class="absolute top-2 right-2.5 w-2 h-2 bg-red-500 rounded-full border border-white"></span>
                    </button>
                </div>
            </header>

            <div class="flex-1 overflow-y-auto p-6 md:p-8 pb-20">
                
                <!-- Welcome Banner -->
                <div class="bg-gradient-to-r from-brand-900 to-brand-700 rounded-2xl p-6 md:p-8 text-white relative overflow-hidden shadow-lg shadow-brand-900/20 mb-8">
                    <div class="absolute top-0 right-0 w-64 h-64 bg-accent-500/20 rounded-full blur-3xl -mr-16 -mt-16"></div>
                    <div class="absolute bottom-0 left-0 w-40 h-40 bg-white/10 rounded-full blur-2xl -ml-10 -mb-10"></div>
                    
                    <div class="relative z-10 flex flex-col md:flex-row justify-between items-start md:items-end gap-4">
                        <div>
                            <h3 class="text-2md font-bold mb-2">Selamat Datang, Wali Kelas! 👋</h3>
                            <p class="text-brand-100 max-w-lg">Pantau perkembangan siswa kelas <?= htmlspecialchars($kelas_info['nama_kelas']) ?> dengan mudah. Sistem penilaian menggunakan bobot: 70% Akademik, 15% Kehadiran, 15% Ekstrakurikuler.</p>
                        </div>
                        <div class="flex gap-3">
                            <a href="export_excel.php?kelas=<?= $kelas_id ?>" class="bg-white/20 hover:bg-white/30 text-white px-4 py-2 rounded-lg text-sm font-medium backdrop-blur-sm transition-all border border-white/20">
                                <i class="fas fa-print mr-2"></i> Cetak Laporan
                            </a>
                            <a href="import_excel.php" class="bg-accent-500 hover:bg-accent-600 text-white px-4 py-2 rounded-lg text-sm font-medium shadow-lg shadow-accent-500/30 transition-all">
                                <i class="fas fa-upload mr-2"></i> Import Nilai
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <!-- Card 1: Total Siswa -->
                    <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm card-hover group">
                        <div class="flex justify-between items-start mb-4">
                            <div class="p-3 bg-blue-50 rounded-xl group-hover:bg-blue-100 transition-colors">
                                <i class="fas fa-users text-brand-600 text-xl"></i>
                            </div>
                        </div>
                        <h4 class="text-3xl font-bold text-slate-800"><?= $total_siswa ?></h4>
                        <p class="text-sm text-slate-500 mt-1">Total Siswa Terdaftar</p>
                    </div>

                    <!-- Card 2: Rata-rata Nilai (Weighted) -->
                    <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm card-hover group">
                        <div class="flex justify-between items-start mb-4">
                            <div class="p-3 bg-amber-50 rounded-xl group-hover:bg-amber-100 transition-colors">
                                <i class="fas fa-trophy text-accent-500 text-xl"></i>
                            </div>
                        </div>
                        <h4 class="text-3xl font-bold text-slate-800"><?= number_format($kelas_avg, 1) ?></h4>
                        <p class="text-sm text-slate-500 mt-1">Rata-rata Nilai Kelas (Weighted)</p>
                    </div>

                    <!-- Card 3: Kehadiran -->
                    <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm card-hover group">
                        <div class="flex justify-between items-start mb-4">
                            <div class="p-3 bg-emerald-50 rounded-xl group-hover:bg-emerald-100 transition-colors">
                                <i class="fas fa-check-circle text-emerald-500 text-xl"></i>
                            </div>
                        </div>
                        <?php
                        $total_hadir = $kehadiran_stats['total_hadir'] ?? 0;
                        $total_tidak_hadir = ($kehadiran_stats['total_sakit'] ?? 0) + ($kehadiran_stats['total_izin'] ?? 0) + ($kehadiran_stats['total_alpa'] ?? 0);
                        $persen_hadir = $total_hadir + $total_tidak_hadir > 0 ? round(($total_hadir / ($total_hadir + $total_tidak_hadir)) * 100, 1) : 0;
                        ?>
                        <h4 class="text-3xl font-bold text-slate-800"><?= $persen_hadir ?>%</h4>
                        <p class="text-sm text-slate-500 mt-1">Tingkat Kehadiran</p>
                    </div>

                    <!-- Card 4: Siswa Perlu Bimbingan -->
                    <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm card-hover group">
                        <div class="flex justify-between items-start mb-4">
                            <div class="p-3 bg-red-50 rounded-xl group-hover:bg-red-100 transition-colors">
                                <i class="fas fa-exclamation-triangle text-red-500 text-xl"></i>
                            </div>
                        </div>
                        <h4 class="text-3xl font-bold text-slate-800"><?= $perlu_bimbingan ?></h4>
                        <p class="text-sm text-slate-500 mt-1">Siswa Perlu Bimbingan</p>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                    <!-- Main Chart: Rata-rata per Mapel -->
                    <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm lg:col-span-2">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="font-bold text-slate-800">Grafik Rata-rata Nilai per Mapel</h3>
                            <select class="bg-slate-50 border border-slate-200 text-xs rounded-lg px-2 py-1 text-slate-600 focus:outline-none focus:ring-2 focus:ring-brand-500">
                                <option><?= htmlspecialchars($kelas_info['semester']) ?></option>
                                <option>Genap</option>
                            </select>
                        </div>
                        <div class="h-64 w-full">
                            <canvas id="barChart"></canvas>
                        </div>
                    </div>

                    <!-- Side Chart: Distribusi Nilai -->
                    <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="font-bold text-slate-800">Distribusi Nilai</h3>
                            <button class="text-slate-400 hover:text-brand-600 transition-colors"><i class="fas fa-ellipsis-h"></i></button>
                        </div>
                        <div class="h-48 w-full flex justify-center">
                            <canvas id="doughnutChart"></canvas>
                        </div>
                        <div class="mt-4 grid grid-cols-2 gap-2 text-xs text-slate-500">
                            <div class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-emerald-500"></span> A (90-100)</div>
                            <div class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-blue-500"></span> B (80-89)</div>
                            <div class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-amber-500"></span> C (70-79)</div>
                            <div class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-red-500"></span> D/E (<70)</div>
                        </div>
                    </div>
                </div>

                <!-- Top Students Table -->
                <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm xl:col-span-2 overflow-hidden">
                        <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                            <h3 class="font-bold text-slate-800 flex items-center gap-2">
                                <i class="fas fa-medal text-accent-500"></i> Top 5 Siswa Berprestasi
                            </h3>
                            <a href="leger.php?kelas=<?= $kelas_id ?>" class="text-xs text-brand-600 font-semibold hover:underline">Lihat Semua Siswa</a>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left">
                                <thead class="text-xs text-slate-500 uppercase bg-slate-50">
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
                                            'A' => 'bg-emerald-100 text-emerald-700',
                                            'B' => 'bg-blue-100 text-blue-700',
                                            'C' => 'bg-amber-100 text-amber-700',
                                            default => 'bg-red-100 text-red-700'
                                        };
                                        $rankBadge = match($no) {
                                            1 => 'bg-accent-500 text-white',
                                            2 => 'bg-slate-400 text-white',
                                            3 => 'bg-amber-700 text-white',
                                            default => 'bg-slate-200 text-slate-700'
                                        };
                                    ?>
                                    <tr class="hover:bg-slate-50 transition-colors">
                                        <td class="px-6 py-4">
                                            <div class="flex items-center justify-center w-7 h-7 <?= $rankBadge ?> rounded-full font-bold text-xs">#<?= $no++ ?></div>
                                        </td>
                                        <td class="px-6 py-4 font-medium text-slate-800"><?= htmlspecialchars(strtoupper($row['nama_siswa'])) ?></td>
                                        <td class="px-6 py-4 text-center font-bold text-brand-700"><?= number_format($final, 2) ?></td>
                                        <td class="px-6 py-4 text-center"><span class="px-2 py-1 <?= $badgeClass ?> rounded-full text-[10px] font-bold"><?= $predikat ?></span></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="p-4 bg-slate-50 text-xs text-slate-500 border-t border-slate-100">
                            <i class="fas fa-info-circle mr-1"></i> Nilai dihitung dengan bobot: 70% Akademik + 15% Kehadiran + 15% Ekstrakurikuler
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6 flex flex-col justify-between">
                        <div>
                            <h3 class="font-bold text-slate-800 mb-4">Aksi Cepat</h3>
                            <div class="space-y-3">
                                <a href="import_excel.php" class="w-full flex items-center gap-3 p-3 rounded-xl bg-slate-50 hover:bg-brand-50 hover:text-brand-700 text-slate-600 transition-all group">
                                    <div class="w-8 h-8 rounded-lg bg-white shadow-sm flex items-center justify-center text-slate-500 group-hover:text-brand-500">
                                        <i class="fas fa-file-import"></i>
                                    </div>
                                    <span class="text-sm font-medium">Import Data Excel</span>
                                    <i class="fas fa-chevron-right ml-auto text-xs opacity-0 group-hover:opacity-100 transition-opacity"></i>
                                </a>
                                <a href="export_excel.php?kelas=<?= $kelas_id ?>" class="w-full flex items-center gap-3 p-3 rounded-xl bg-slate-50 hover:bg-brand-50 hover:text-brand-700 text-slate-600 transition-all group">
                                    <div class="w-8 h-8 rounded-lg bg-white shadow-sm flex items-center justify-center text-slate-500 group-hover:text-brand-500">
                                        <i class="fas fa-print"></i>
                                    </div>
                                    <span class="text-sm font-medium">Cetak Leger Kelas</span>
                                    <i class="fas fa-chevron-right ml-auto text-xs opacity-0 group-hover:opacity-100 transition-opacity"></i>
                                </a>
                                <a href="grafik_nilai.php?kelas=<?= $kelas_id ?>" class="w-full flex items-center gap-3 p-3 rounded-xl bg-slate-50 hover:bg-brand-50 hover:text-brand-700 text-slate-600 transition-all group">
                                    <div class="w-8 h-8 rounded-lg bg-white shadow-sm flex items-center justify-center text-slate-500 group-hover:text-brand-500">
                                        <i class="fas fa-chart-bar"></i>
                                    </div>
                                    <span class="text-sm font-medium">Lihat Analitik Lengkap</span>
                                    <i class="fas fa-chevron-right ml-auto text-xs opacity-0 group-hover:opacity-100 transition-opacity"></i>
                                </a>
                            </div>
                        </div>
                        
                        <div class="mt-6 bg-gradient-to-br from-brand-800 to-brand-900 rounded-xl p-4 text-white relative overflow-hidden">
                            <i class="fas fa-info-circle absolute -bottom-4 -right-4 text-6xl text-white/10"></i>
                            <h4 class="font-bold text-sm mb-1">Info Wali Kelas</h4>
                            <p class="text-xs text-brand-200"><?= htmlspecialchars($kelas_info['wali_kelas']) ?></p>
                            <p class="text-[10px] text-brand-300 mt-1">Kelas <?= htmlspecialchars($kelas_info['nama_kelas']) ?> - TP <?= htmlspecialchars($kelas_info['tahun_ajaran']) ?></p>
                        </div>
                    </div>
                </div>

                <footer class="mt-12 text-center text-xs text-slate-400 pb-4">
                    &copy; <?= date('Y') ?> SDN Curug 01 Bojongsari. All rights reserved.
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
            new Chart(ctxBar, {
                type: 'bar',
                data: {
                    labels: <?= json_encode(array_column($mapel_data, 'label')) ?>,
                    datasets: [{
                        label: 'Rata-rata Nilai',
                        data: <?= json_encode(array_column($mapel_data, 'val')) ?>,
                        backgroundColor: '#3b82f6',
                        hoverBackgroundColor: '#2563eb',
                        borderRadius: 6,
                        barPercentage: 0.6,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#1e293b',
                            padding: 12,
                            titleFont: { size: 13, family: "'Plus Jakarta Sans'" },
                            bodyFont: { size: 12, family: "'Plus Jakarta Sans'" },
                            cornerRadius: 8,
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            grid: { color: '#f1f5f9' },
                            ticks: { font: { family: "'Plus Jakarta Sans'" } }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { font: { family: "'Plus Jakarta Sans'" } }
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
                        backgroundColor: ['#10b981', '#3b82f6', '#f59e0b', '#ef4444'],
                        borderWidth: 0,
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '75%',
                    plugins: { legend: { display: false } }
                }
            });
        });
    </script>
</body>
</html>