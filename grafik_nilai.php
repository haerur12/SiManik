<?php
require 'config.php';

if(!isset($_SESSION['login'])) {
    header("Location: index.php");
    exit;
}

$kelas_id = isset($_GET['kelas']) ? (int)$_GET['kelas'] : 1;
$kelas_info = mysqli_query($conn, "SELECT * FROM kelas WHERE id = $kelas_id")->fetch_assoc();

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

// 4. Distribusi Nilai (untuk chart) - tetap pakai nilai akademik murni
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

// 6. Rata-rata per Mapel (untuk chart) - tetap pakai nilai akademik murni
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
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grafik & Analitik - SiManik</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        .glass-header { background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(12px); border-bottom: 1px solid rgba(226, 232, 240, 0.6); }
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            .main-wrapper { margin-left: 0 !important; padding: 0 !important; }
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 font-sans antialiased">

<div class="flex h-screen overflow-hidden">
    <!-- Sidebar -->
    <aside class="no-print hidden md:flex flex-col w-64 bg-slate-900 text-white h-screen fixed z-30 shadow-xl transition-all duration-300">
        <div class="h-16 flex items-center px-6 border-b border-slate-800 bg-slate-950">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-brand-600 to-brand-800 flex items-center justify-center">
                    <i class="fas fa-graduation-cap text-accent-400 text-sm"></i>
                </div>
                <div><h1 class="font-bold text-base tracking-tight">SiManik</h1><p class="text-[10px] text-slate-400">Monitoring Nilai</p></div>
            </div>
        </div>
        <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1">
            <a href="dashboard.php?kelas=<?= $kelas_id ?>" class="flex items-center gap-3 px-3 py-2.5 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition-all text-sm"><i class="fas fa-home w-5 text-center"></i> Dashboard</a>
            <a href="import_excel.php" class="flex items-center gap-3 px-3 py-2.5 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition-all text-sm"><i class="fas fa-file-excel w-5 text-center"></i> Import Data</a>
            <a href="leger.php?kelas=<?= $kelas_id ?>" class="flex items-center gap-3 px-3 py-2.5 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition-all text-sm"><i class="fas fa-book-open w-5 text-center"></i> Leger Nilai</a>
            <a href="grafik_nilai.php?kelas=<?= $kelas_id ?>" class="flex items-center gap-3 px-3 py-2.5 bg-gradient-to-r from-brand-700 to-brand-600 text-white rounded-lg shadow-md text-sm"><i class="fas fa-chart-pie w-5 text-center"></i> Analitik Grafik</a>
        </nav>
        <div class="p-3 border-t border-slate-800 bg-slate-950">
            <a href="logout.php" class="flex items-center gap-3 px-3 py-2 text-red-400 hover:text-red-300 hover:bg-red-900/20 rounded-lg transition-all text-sm"><i class="fas fa-sign-out-alt w-5 text-center"></i> Logout</a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col h-screen overflow-hidden md:ml-64 transition-all duration-300 main-wrapper">
        <!-- Header -->
        <header class="no-print glass-header h-16 px-6 flex items-center justify-between sticky top-0 z-20">
            <div class="flex items-center gap-3">
                <button class="md:hidden text-slate-600" onclick="document.querySelector('aside').classList.toggle('hidden')"><i class="fas fa-bars"></i></button>
                <h2 class="text-lg font-bold text-slate-800">Grafik & Analitik</h2>
            </div>
            <div class="flex items-center gap-3">
                <select class="bg-slate-50 border border-slate-300 text-sm rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand-500 cursor-pointer" onchange="window.location='?kelas='+this.value">
                    <?php for($i=1; $i<=4; $i++): ?>
                        <option value="<?= $i ?>" <?= $kelas_id==$i?'selected':'' ?>>Kelas 4<?= chr(64+$i) ?></option>
                    <?php endfor; ?>
                </select>
                <a href="dashboard.php?kelas=<?= $kelas_id ?>" class="flex items-center gap-2 px-4 py-2 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 rounded-lg text-sm font-medium transition-colors">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </div>
        </header>

        <!-- Scrollable Content -->
        <div class="flex-1 overflow-y-auto p-6 pb-20">
            <!-- Stats Row -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex justify-between items-start">
                        <div><p class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Total Siswa</p><h3 class="text-3xl font-extrabold text-navy-900"><?= $total_siswa ?></h3></div>
                        <i class="fas fa-users w-8 h-8 text-brand-600 opacity-20"></i>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex justify-between items-start">
                        <div><p class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Rata-rata Kelas</p><h3 class="text-3xl font-extrabold text-navy-900"><?= number_format($kelas_avg, 1) ?></h3></div>
                        <i class="fas fa-chart-line w-8 h-8 text-accent-500 opacity-20"></i>
                    </div>
                    <p class="text-[10px] text-slate-400 mt-2">Weighted: 70% Akademik + 15% Absensi + 15% Ekstra</p>
                </div>
                <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex justify-between items-start">
                        <div><p class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Nilai A (Sangat Baik)</p><h3 class="text-3xl font-extrabold text-navy-900"><?= $distribusi_nilai['a_count'] ?></h3></div>
                        <i class="fas fa-trophy w-8 h-8 text-emerald-500 opacity-20"></i>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex justify-between items-start">
                        <div><p class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Perlu Bimbingan</p><h3 class="text-3xl font-extrabold text-navy-900"><?= $perlu_bimbingan ?></h3></div>
                        <i class="fas fa-exclamation-triangle w-8 h-8 text-red-500 opacity-20"></i>
                    </div>
                    <p class="text-[10px] text-slate-400 mt-2">Nilai &lt;70 atau Alpa &gt;3</p>
                </div>
            </div>

            <!-- Charts Row 1 -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6">
                    <h3 class="font-bold text-slate-800 mb-4 flex items-center gap-2"><i class="fas fa-chart-bar text-brand-600"></i> Rata-rata Nilai per Mapel</h3>
                    <div class="h-72 w-full"><canvas id="mapelChart"></canvas></div>
                </div>
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6">
                    <h3 class="font-bold text-slate-800 mb-4 flex items-center gap-2"><i class="fas fa-chart-pie text-accent-500"></i> Distribusi Predikat</h3>
                    <div class="h-72 w-full flex justify-center"><canvas id="distribusiChart"></canvas></div>
                    <div class="mt-4 grid grid-cols-2 gap-2 text-xs text-slate-500">
                        <div class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-emerald-500"></span> A (90-100)</div>
                        <div class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-blue-500"></span> B (80-89)</div>
                        <div class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-amber-500"></span> C (70-79)</div>
                        <div class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-orange-500"></span> D (60-69)</div>
                        <div class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-red-500"></span> E (&lt;60)</div>
                    </div>
                </div>
            </div>

            <!-- Charts Row 2 -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6">
                    <h3 class="font-bold text-slate-800 mb-4 flex items-center gap-2"><i class="fas fa-medal text-amber-500"></i> Top 10 Siswa (Weighted)</h3>
                    <div class="h-72 w-full"><canvas id="topSiswaChart"></canvas></div>
                    <p class="text-[10px] text-slate-400 mt-2 text-center">Nilai Akhir = 70% Akademik + 15% Absensi + 15% Ekstra</p>
                </div>
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6">
                    <h3 class="font-bold text-slate-800 mb-4 flex items-center gap-2"><i class="fas fa-calendar-check text-emerald-500"></i> Statistik Kehadiran</h3>
                    <div class="h-72 w-full"><canvas id="kehadiranChart"></canvas></div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
    // Chart Data Injection
    const mapelLabels = <?= json_encode(array_column($mapel_data, 'label')) ?>;
    const mapelValues = <?= json_encode(array_column($mapel_data, 'val')) ?>;
    
    // Top Siswa: Nama LENGKAP + Nilai FINAL (weighted)
    const topSiswaLabels = [<?php
    mysqli_data_seek($top_siswa, 0);
    $labels = [];
    while($row = mysqli_fetch_assoc($top_siswa)) {
        // Gunakan nama lengkap (tanpa explode)
        $labels[] = "'".addslashes($row['nama_siswa'])."'";
    }
    echo implode(',', $labels);
    ?>];
    
    const topSiswaValues = [<?php
    mysqli_data_seek($top_siswa, 0);
    $values = [];
    while($row = mysqli_fetch_assoc($top_siswa)) {
        // Gunakan final_score yang sudah di-weighted
        $values[] = number_format($row['final_score'], 2);
    }
    echo implode(',', $values);
    ?>];
    
    const distribusiData = [<?= $distribusi_nilai['a_count'] ?? 0 ?>, <?= $distribusi_nilai['b_count'] ?? 0 ?>, <?= $distribusi_nilai['c_count'] ?? 0 ?>, <?= $distribusi_nilai['d_count'] ?? 0 ?>, <?= $distribusi_nilai['e_count'] ?? 0 ?>];
    
    const hadirData = [
        <?= $kehadiran_stats['total_hadir'] ?? 0 ?>,
        <?= $kehadiran_stats['total_sakit'] ?? 0 ?>,
        <?= $kehadiran_stats['total_izin'] ?? 0 ?>,
        <?= $kehadiran_stats['total_alpa'] ?? 0 ?>
    ];

    // Chart Defaults
    Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";
    Chart.defaults.plugins.tooltip.cornerRadius = 8;
    Chart.defaults.plugins.tooltip.padding = 10;
    Chart.defaults.plugins.tooltip.backgroundColor = '#1e293b';

    // 1. Mapel Bar Chart (Nilai Akademik Murni)
    new Chart(document.getElementById('mapelChart'), {
        type: 'bar',
        data: {
            labels: mapelLabels,
            datasets: [{
                label: 'Rata-rata Nilai',
                data: mapelValues,
                backgroundColor: 'rgba(37, 99, 235, 0.85)',
                hoverBackgroundColor: '#2563eb',
                borderRadius: 6,
                barPercentage: 0.6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true, max: 100, grid: { color: '#f1f5f9' }, ticks: { color: '#64748b' } },
                x: { grid: { display: false }, ticks: { color: '#64748b' } }
            },
            plugins: { legend: { display: false } }
        }
    });

    // 2. Distribusi Doughnut (Nilai Akademik Murni)
    new Chart(document.getElementById('distribusiChart'), {
        type: 'doughnut',
        data: {
            labels: ['A', 'B', 'C', 'D', 'E'],
            datasets: [{
                data: distribusiData,
                backgroundColor: ['#10b981', '#3b82f6', '#f59e0b', '#f97316', '#ef4444'],
                borderWidth: 0,
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: { legend: { display: false } }
        }
    });

    // 3. Top Siswa Horizontal Bar (NILAI WEIGHTED + NAMA LENGKAP)
    new Chart(document.getElementById('topSiswaChart'), {
        type: 'bar',
        data: {
            labels: topSiswaLabels,
            datasets: [{
                label: 'Nilai Akhir (Weighted)',
                data: topSiswaValues,
                backgroundColor: 'rgba(245, 158, 11, 0.85)',
                hoverBackgroundColor: '#d97706',
                borderRadius: 4,
                barPercentage: 0.5
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: { beginAtZero: true, max: 100, grid: { color: '#f1f5f9' }, ticks: { color: '#64748b' } },
                y: { grid: { display: false }, ticks: { color: '#334155', font: { weight: '500', size: 10 } } }
            },
            plugins: { 
                legend: { display: false },
                tooltip: {
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
    new Chart(document.getElementById('kehadiranChart'), {
        type: 'pie',
        data: {
            labels: ['Hadir', 'Sakit', 'Izin', 'Alpa'],
            datasets: [{
                data: hadirData,
                backgroundColor: ['#10b981', '#3b82f6', '#f59e0b', '#ef4444'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { padding: 15, usePointStyle: true, pointStyle: 'circle' } }
            }
        }
    });
</script>
</body>
</html>