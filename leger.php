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

// Fungsi helper untuk warna badge nilai
function getGradeColor($nilai) {
    if (empty($nilai)) return 'text-slate-400';
    if ($nilai >= 90) return 'bg-emerald-100 text-emerald-700 border-emerald-200';
    if ($nilai >= 80) return 'bg-blue-100 text-blue-700 border-blue-200';
    if ($nilai >= 70) return 'bg-amber-100 text-amber-700 border-amber-200';
    if ($nilai >= 60) return 'bg-orange-100 text-orange-700 border-orange-200';
    return 'bg-red-100 text-red-700 border-red-200';
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
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leger Nilai - Kelas <?= htmlspecialchars($kelas_info['nama_kelas']) ?></title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Font -->
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
        /* Scrollbar custom */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

        /* Sticky Columns Logic */
        .sticky-col-1 { position: sticky; left: 0; z-index: 10; background: white; }
        .sticky-col-2 { position: sticky; left: 48px; z-index: 10; background: white; }
        .sticky-col-3 { position: sticky; left: 240px; z-index: 10; background: white; }
        
        th.sticky-col-1, th.sticky-col-2, th.sticky-col-3 {
            background: #1e293b; /* Matches table header bg */
            z-index: 20;
        }

        /* Print Styles */
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; padding: 0 !important; margin: 0 !important; }
            .main-wrapper { margin: 0 !important; padding: 0 !important; overflow: visible !important; }
            
            /* Hide Sidebar & Header in print */
            aside, header { display: none !important; }
            
            /* Table adjustments for print */
            .table-container { overflow: visible !important; }
            .sticky-col-1, .sticky-col-2, .sticky-col-3, 
            th.sticky-col-1, th.sticky-col-2, th.sticky-col-3 { position: static !important; background: transparent !important; }
            
            /* Reduce font size and padding */
            table { font-size: 8pt !important; }
            th, td { padding: 2px 4px !important; border: 1px solid #cbd5e1 !important; }
            
            /* Badge colors */
            .badge-value { border: 1px solid #000; background: #fff !important; color: #000 !important; font-weight: bold; }
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
                <div>
                    <h1 class="font-bold text-base tracking-tight leading-none">SiManik</h1>
                    <p class="text-[10px] text-slate-400 font-medium">Monitoring Nilai</p>
                </div>
            </div>
        </div>
        <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1">
            <a href="dashboard.php" class="flex items-center gap-3 px-3 py-2.5 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition-all text-sm">
                <i class="fas fa-home w-5 text-center"></i> Dashboard
            </a>
            <a href="import_excel.php" class="flex items-center gap-3 px-3 py-2.5 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition-all text-sm">
                <i class="fas fa-file-excel w-5 text-center"></i> Import Data
            </a>
            <a href="leger.php?kelas=<?= $kelas_id ?>" class="flex items-center gap-3 px-3 py-2.5 bg-gradient-to-r from-brand-700 to-brand-600 text-white rounded-lg shadow-md text-sm">
                <i class="fas fa-book-open w-5 text-center"></i> Leger Nilai
            </a>
            <a href="grafik_nilai.php" class="flex items-center gap-3 px-3 py-2.5 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition-all text-sm">
                <i class="fas fa-chart-pie w-5 text-center"></i> Analitik Grafik
            </a>
        </nav>
        <div class="p-3 border-t border-slate-800 bg-slate-950">
            <a href="logout.php" class="flex items-center gap-3 px-3 py-2 text-red-400 hover:text-red-300 hover:bg-red-900/20 rounded-lg transition-all text-sm">
                <i class="fas fa-sign-out-alt w-5 text-center"></i> Logout
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col h-screen overflow-hidden md:ml-64 transition-all duration-300 main-wrapper">
        <!-- Header -->
        <header class="no-print bg-white border-b border-slate-200 h-16 px-6 flex items-center justify-between sticky top-0 z-20">
            <div class="flex items-center gap-4">
                <button class="md:hidden text-slate-600"><i class="fas fa-bars"></i></button>
                <h2 class="text-lg font-bold text-slate-800">Leger Nilai</h2>
            </div>
            <div class="flex items-center gap-3">
                <select class="no-print bg-slate-50 border border-slate-300 text-sm rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand-500 cursor-pointer" onchange="window.location='?kelas='+this.value">
                    <option value="1" <?= $kelas_id==1?'selected':'' ?>>Kelas 4A</option>
                    <option value="2" <?= $kelas_id==2?'selected':'' ?>>Kelas 4B</option>
                    <option value="3" <?= $kelas_id==3?'selected':'' ?>>Kelas 4C</option>
                    <option value="4" <?= $kelas_id==4?'selected':'' ?>>Kelas 4D</option>
                </select>
                <button onclick="window.print()" class="flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-medium transition-colors shadow-sm">
                    <i class="fas fa-print"></i> Cetak Leger
                </button>
                <a href="dashboard.php" class="no-print flex items-center gap-2 px-4 py-2 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 rounded-lg text-sm font-medium transition-colors">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </div>
        </header>

        <!-- Scrollable Content -->
        <div class="flex-1 overflow-y-auto p-6 pb-20">
            
            <!-- Info Header -->
            <div class="no-print mb-6 p-5 bg-white rounded-xl border border-slate-200 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h3 class="font-bold text-xl text-slate-800">LEGER NILAI RAPOR SISWA</h3>
                    <p class="text-sm text-slate-500 mt-1">SDN Curug 01 Bojongsari | Tahun Pelajaran 2025/2026 | Semester Ganjil</p>
                </div>
                <div class="flex items-center gap-4">
                    <div class="text-right">
                        <p class="text-xs text-slate-400 uppercase font-semibold">Wali Kelas</p>
                        <p class="font-bold text-slate-800 text-sm">CECEP ROHAEDI, S.Pd</p>
                    </div>
                    <div class="w-10 h-10 rounded-full bg-brand-100 flex items-center justify-center text-brand-600">
                        <i class="fas fa-user-tie"></i>
                    </div>
                </div>
            </div>

            <!-- Table Container -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden relative">
                <div class="table-container overflow-x-auto max-h-[75vh]">
                    <table class="w-full text-sm text-left border-collapse">
                        <thead class="bg-slate-800 text-slate-300 uppercase text-[10px] tracking-wider">
                            <!-- Header Row 1 -->
                            <tr>
                                <th rowspan="2" class="sticky-col-1 px-3 py-3 border-r border-slate-700 bg-slate-800 text-center w-12">NO</th>
                                <th rowspan="2" class="sticky-col-2 px-4 py-3 border-r border-slate-700 bg-slate-800 text-left min-w-[200px]">NAMA SISWA</th>
                                <th rowspan="2" class="sticky-col-3 px-4 py-3 border-r border-slate-700 bg-slate-800 text-center w-24">NISN</th>
                                <th colspan="12" class="px-4 py-2 border-r border-slate-700 bg-slate-700 text-center text-slate-200 font-bold">MATA PELAJARAN</th>
                                <th colspan="3" class="px-4 py-2 border-r border-slate-700 bg-slate-700 text-center text-slate-200 font-bold">KEHADIRAN</th>
                                <th rowspan="2" class="px-4 py-2 bg-slate-700 text-center text-slate-200 font-bold w-24">EKSTRAKULIKULER</th>
                            </tr>
                            <!-- Header Row 2 -->
                            <tr class="text-[10px] bg-slate-750">
                                <th class="px-2 py-2 border-r border-slate-600 min-w-[40px]">PAI</th>
                                <th class="px-2 py-2 border-r border-slate-600 min-w-[40px]">PAK</th>
                                <th class="px-2 py-2 border-r border-slate-600 min-w-[40px]">PPKn</th>
                                <th class="px-2 py-2 border-r border-slate-600 min-w-[40px]">Indo</th>
                                <th class="px-2 py-2 border-r border-slate-600 min-w-[40px]">MTk</th>
                                <th class="px-2 py-2 border-r border-slate-600 min-w-[40px]">IPAS</th>
                                <th class="px-2 py-2 border-r border-slate-600 min-w-[40px]">PJOK</th>
                                <th class="px-2 py-2 border-r border-slate-600 min-w-[40px]">Ing</th>
                                <th class="px-2 py-2 border-r border-slate-600 min-w-[40px]">ML</th>
                                <th class="px-2 py-2 border-r border-slate-600 min-w-[40px]">SR</th>
                                <th class="px-2 py-2 border-r border-slate-600 min-w-[40px]">GKS</th>
                                <th class="px-2 py-2 border-r border-slate-600 min-w-[40px]">SB</th>
                                <th class="px-2 py-2 border-r border-slate-600 min-w-[40px]">S</th>
                                <th class="px-2 py-2 border-r border-slate-600 min-w-[40px]">I</th>
                                <th class="px-2 py-2 border-r border-slate-600 min-w-[40px]">A</th>
                                <th rowspan="2" class="px-2 py-2 border-r border-slate-600 min-w-[40px]">Pramuka</th>
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
                            <tr class="hover:bg-slate-50 transition-colors group">
                                <td class="sticky-col-1 px-3 py-3 border-r border-slate-200 bg-white text-center text-slate-500 group-hover:bg-slate-50"><?= $no++ ?></td>
                                <td class="sticky-col-2 px-4 py-3 border-r border-slate-200 bg-white font-medium text-slate-800 text-left whitespace-nowrap group-hover:bg-slate-50"><?= strtoupper($siswa['nama_siswa']) ?></td>
                                <td class="sticky-col-3 px-4 py-3 border-r border-slate-200 bg-white text-center text-slate-500 font-mono group-hover:bg-slate-50"><?= $siswa['nisn'] ?></td>
                                
                                <?php foreach($mapel_list as $mapel): 
                                    $nilai = $nilai_data[$mapel] ?? null;
                                ?>
                                <td class="px-2 py-3 border-r border-slate-100 text-center group-hover:bg-slate-50">
                                    <?php if($nilai !== null): ?>
                                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-[10px] font-bold border <?= getGradeColor($nilai) ?> badge-value">
                                        <?= $nilai ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="text-slate-300">-</span>
                                    <?php endif; ?>
                                </td>
                                <?php endforeach; ?>
                                
                                <td class="px-2 py-3 border-r border-slate-100 text-center group-hover:bg-slate-50 <?= $kehadiran['sakit']>0?'text-orange-600 font-bold':'' ?>"><?= $kehadiran['sakit'] ?? 0 ?></td>
                                <td class="px-2 py-3 border-r border-slate-100 text-center group-hover:bg-slate-50"><?= $kehadiran['izin'] ?? 0 ?></td>
                                <td class="px-2 py-3 border-r border-slate-100 text-center group-hover:bg-slate-50 <?= ($kehadiran['alpa'] ?? 0) > 0 ? 'text-red-600 font-bold' : '' ?>"><?= $kehadiran['alpa'] ?? 0 ?></td>
                                <td class="px-2 py-3 text-center group-hover:bg-slate-50">
                                    <?php
                                    $pred = $ekstra['predikat'] ?? 'B';
                                    $extraColor = match($pred) {
                                        'A' => 'bg-emerald-100 text-emerald-700',
                                        'B' => 'bg-blue-100 text-blue-700',
                                        'C' => 'bg-amber-100 text-amber-700',
                                        default => 'bg-slate-100 text-slate-500'
                                    };
                                    ?>
                                    <span class="inline-block w-6 h-6 leading-6 rounded text-[10px] font-bold <?= $extraColor ?>"><?= $pred ?></span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Legend -->
            <div class="no-print mt-6 p-5 bg-white rounded-xl border border-slate-200 shadow-sm">
                <h4 class="font-bold text-slate-800 mb-4 flex items-center gap-2"><i class="fas fa-info-circle text-brand-500"></i> KETERANGAN</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 text-xs">
                    <div class="space-y-2">
                        <div class="p-2 border border-slate-100 rounded-lg bg-slate-50"><strong class="text-slate-700">PAI:</strong> Agama Islam & BP</div>
                        <div class="p-2 border border-slate-100 rounded-lg bg-slate-50"><strong class="text-slate-700">PAK:</strong> Agama Kristen & BP</div>
                        <div class="p-2 border border-slate-100 rounded-lg bg-slate-50"><strong class="text-slate-700">PPKn:</strong> Pendidikan Pancasila</div>
                        <div class="p-2 border border-slate-100 rounded-lg bg-slate-50"><strong class="text-slate-700">Indo:</strong> Bahasa Indonesia</div>
                    </div>
                    <div class="space-y-2">
                        <div class="p-2 border border-slate-100 rounded-lg bg-slate-50"><strong class="text-slate-700">MTk:</strong> Matematika</div>
                        <div class="p-2 border border-slate-100 rounded-lg bg-slate-50"><strong class="text-slate-700">IPAS:</strong> IPA & Sosial</div>
                        <div class="p-2 border border-slate-100 rounded-lg bg-slate-50"><strong class="text-slate-700">PJOK:</strong> Jasmani & Kesehatan</div>
                        <div class="p-2 border border-slate-100 rounded-lg bg-slate-50"><strong class="text-slate-700">Ing:</strong> Bahasa Inggris</div>
                    </div>
                    <div class="space-y-2">
                        <div class="p-2 border border-slate-100 rounded-lg bg-slate-50"><strong class="text-slate-700">ML:</strong> Mulok Bahasa Daerah</div>
                        <div class="p-2 border border-slate-100 rounded-lg bg-slate-50"><strong class="text-slate-700">SR:</strong> Seni Rupa</div>
                        <div class="p-2 border border-slate-100 rounded-lg bg-slate-50"><strong class="text-slate-700">GKS:</strong> Guru Kelas</div>
                        <div class="p-2 border border-slate-100 rounded-lg bg-slate-50"><strong class="text-slate-700">SB:</strong> Seni Budaya</div>
                    </div>
                    <div class="p-3 border border-amber-200 rounded-lg bg-amber-50 text-amber-800">
                        <strong class="block mb-1"><i class="fas fa-lightbulb"></i> Rentang Nilai</strong>
                        <div class="flex gap-2 flex-wrap mt-2">
                            <span class="bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded">90-100: Sangat Baik</span>
                            <span class="bg-blue-100 text-blue-800 px-2 py-0.5 rounded">80-89: Baik</span>
                            <span class="bg-amber-100 text-amber-800 px-2 py-0.5 rounded">70-79: Cukup</span>
                            <span class="bg-orange-100 text-orange-800 px-2 py-0.5 rounded">60-69: Kurang</span>
                            <span class="bg-red-100 text-red-800 px-2 py-0.5 rounded"><60: Perlu Bimbingan</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

</body>
</html>