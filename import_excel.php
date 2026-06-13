<?php
require 'config.php';
if(!isset($_SESSION['login'])) {
    header("Location: index.php");
    exit;
}

$success = false;
$error = '';
$kelas_id = isset($_GET['kelas']) ? (int)$_GET['kelas'] : 1;

// ============================================
// 🔥 AMBIL SEMUA KELAS DARI DATABASE (DINAMIS)
// ============================================
$kelas_list_query = mysqli_query($conn, "SELECT * FROM kelas ORDER BY id ASC");
$kelas_array = [];
while($k = mysqli_fetch_assoc($kelas_list_query)) {
    $kelas_array[] = $k;
}
$total_kelas = count($kelas_array);

// Sidebar variables
$current_page = basename($_SERVER['PHP_SELF']);
function isActive($page, $current) {
    return $page === $current ? 'active' : '';
}
$user_name = $_SESSION['nama_lengkap'] ?? 'Wali Kelas';
$user_level = ucfirst($_SESSION['level'] ?? 'guru');

if(isset($_POST['import'])) {
    $kelas_id = (int)$_POST['kelas_id'];
    
    if(!isset($_FILES['file_excel']) || $_FILES['file_excel']['error'] == 4) {
        $error = '❌ Silakan pilih file Excel!';
    } else {
        require_once 'vendor/autoload.php';
        $file = $_FILES['file_excel']['tmp_name'];
        
        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();
            $data_count = 0;
            
            foreach($rows as $key => $row) {
                if($key < 6) continue;
                if(empty($row[1])) continue;
                if(strpos($row[1], ':') !== false) continue;
                if(!is_numeric(trim($row[0]))) continue;
                
                $nisn_raw = trim($row[2]);
                $nisn_digits = preg_replace('/[^0-9]/', '', $nisn_raw);
                if(empty($nisn_digits) || strlen($nisn_digits) < 10) continue;
                
                $nama_siswa = mysqli_real_escape_string($conn, trim($row[1]));
                $nisn = mysqli_real_escape_string($conn, $nisn_raw);
                $nis = mysqli_real_escape_string($conn, trim($row[3]));
                
                $siswa = mysqli_query($conn, "SELECT id FROM siswa WHERE nisn = '$nisn'");
                if(mysqli_num_rows($siswa) > 0) {
                    $siswa_id = mysqli_fetch_assoc($siswa)['id'];
                } else {
                    mysqli_query($conn, "INSERT INTO siswa SET
                        nisn='$nisn',
                        nis='$nis',
                        nama_siswa='$nama_siswa',
                        kelas_id=$kelas_id,
                        jenis_kelamin='L'
                    ");
                    $siswa_id = mysqli_insert_id($conn);
                }
                
                $mapel_map = [
                    4  => ['id' => 1, 'kode' => 'PAIDBP'],
                    5  => ['id' => 2, 'kode' => 'PAKDBP'],
                    6  => ['id' => 3, 'kode' => 'PPDK'],
                    7  => ['id' => 4, 'kode' => 'BI'],
                    8  => ['id' => 5, 'kode' => 'MU'],
                    9  => ['id' => 6, 'kode' => 'IPADSI'],
                    10 => ['id' => 7, 'kode' => 'PJODK'],
                    11 => ['id' => 8, 'kode' => 'ING'],
                    12 => ['id' => 9, 'kode' => 'MLBD'],
                    13 => ['id' => 10, 'kode' => 'SR'],
                    14 => ['id' => 11, 'kode' => 'GKS'],
                    15 => ['id' => 12, 'kode' => 'SB']
                ];
                
                foreach($mapel_map as $index => $info) {
                    $nilai = isset($row[$index]) ? trim($row[$index]) : '';
                    if(!empty($nilai) && is_numeric($nilai)) {
                        $predikat = $nilai >= 90 ? 'A' : ($nilai >= 80 ? 'B' : ($nilai >= 70 ? 'C' : 'D'));
                        $check = mysqli_query($conn, "SELECT id FROM nilai WHERE siswa_id=$siswa_id AND mapel_id={$info['id']} AND kelas_id=$kelas_id");
                        if(mysqli_num_rows($check) > 0) {
                            mysqli_query($conn, "UPDATE nilai SET nilai_angka=$nilai, predikat='$predikat' WHERE siswa_id=$siswa_id AND mapel_id={$info['id']} AND kelas_id=$kelas_id");
                        } else {
                            mysqli_query($conn, "INSERT INTO nilai SET
                                siswa_id=$siswa_id,
                                mapel_id={$info['id']},
                                kelas_id=$kelas_id,
                                nilai_angka=$nilai,
                                predikat='$predikat',
                                semester='Ganjil',
                                tahun_ajaran='2025/2026'
                            ");
                        }
                    }
                }
                
                $sakit = isset($row[16]) ? (int)$row[16] : 0;
                $izin = isset($row[17]) ? (int)$row[17] : 0;
                $alpa = isset($row[18]) ? (int)$row[18] : 0;
                $hadir = max(0, 100 - ($sakit + $izin + $alpa));
                
                $check = mysqli_query($conn, "SELECT id FROM kehadiran WHERE siswa_id=$siswa_id AND kelas_id=$kelas_id");
                if(mysqli_num_rows($check) > 0) {
                    mysqli_query($conn, "UPDATE kehadiran SET sakit=$sakit, izin=$izin, alpa=$alpa, hadir=$hadir WHERE siswa_id=$siswa_id AND kelas_id=$kelas_id");
                } else {
                    mysqli_query($conn, "INSERT INTO kehadiran SET
                        siswa_id=$siswa_id,
                        kelas_id=$kelas_id,
                        sakit=$sakit,
                        izin=$izin,
                        alpa=$alpa,
                        hadir=$hadir,
                        semester='Ganjil',
                        tahun_ajaran='2025/2026'
                    ");
                }
                
                $ekstra_predikat = isset($row[19]) ? trim($row[19]) : 'B';
                $check = mysqli_query($conn, "SELECT id FROM ekstrakurikuler WHERE siswa_id=$siswa_id");
                if(mysqli_num_rows($check) > 0) {
                    mysqli_query($conn, "UPDATE ekstrakurikuler SET predikat='$ekstra_predikat', nama_ekstra='PRAMUKA SIAGA' WHERE siswa_id=$siswa_id");
                } else {
                    mysqli_query($conn, "INSERT INTO ekstrakurikuler SET
                        siswa_id=$siswa_id,
                        nama_ekstra='PRAMUKA SIAGA',
                        predikat='$ekstra_predikat',
                        semester='Ganjil',
                        tahun_ajaran='2025/2026'
                    ");
                }
                
                $data_count++;
            }
            
            if($data_count > 0) {
                $success = true;
                $success_msg = "✅ Import berhasil! $data_count data siswa diproses.";
            } else {
                $error = "❌ Tidak ada data yang diimport. Pastikan format Excel sesuai!";
            }
        } catch(Exception $e) {
            $error = "❌ Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Data Excel - SiManik</title>
    
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
                        'bounce-in': 'bounceIn 0.6s ease-out',
                    },
                    keyframes: {
                        fadeIn: { '0%': { opacity: '0' }, '100%': { opacity: '1' } },
                        slideUp: { '0%': { transform: 'translateY(20px)', opacity: '0' }, '100%': { transform: 'translateY(0)', opacity: '1' } },
                        scaleIn: { '0%': { transform: 'scale(0.95)', opacity: '0' }, '100%': { transform: 'scale(1)', opacity: '1' } },
                        bounceIn: { '0%': { transform: 'scale(0.3)', opacity: '0' }, '50%': { transform: 'scale(1.05)' }, '70%': { transform: 'scale(0.9)' }, '100%': { transform: 'scale(1)', opacity: '1' } }
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
        
        .drag-active { 
            border-color: #0ea5e9 !important; 
            background: linear-gradient(135deg, rgba(14, 165, 233, 0.1) 0%, rgba(2, 132, 199, 0.1) 100%) !important; 
            transform: scale(1.02); 
            box-shadow: 0 0 0 4px rgba(14, 165, 233, 0.1);
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

        .upload-zone { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); }
        .upload-zone:hover { background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); border-color: #0ea5e9; }

        .file-icon { animation: float 3s ease-in-out infinite; }
        @keyframes float { 0%, 100% { transform: translateY(0px); } 50% { transform: translateY(-10px); } }

        .notification-enter { animation: slideInRight 0.4s ease-out; }
        @keyframes slideInRight { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        /* Kelas Card Styling */
        .kelas-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        .kelas-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 25px -5px rgba(14, 165, 233, 0.2);
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
        
        <!-- Header -->
        <header class="glass-header h-20 px-6 md:px-8 flex items-center justify-between sticky top-0 z-20">
            <div class="flex items-center gap-4">
                <button onclick="toggleSidebar()" class="md:hidden w-10 h-10 flex items-center justify-center bg-white rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 shadow-sm transition-all">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <h2 class="text-xl font-bold text-slate-800">Import Data Excel</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Upload file leger untuk import nilai siswa</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <a href="dashboard.php?kelas=<?= $kelas_id ?>" class="flex items-center gap-2 px-4 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 hover:border-primary-300 text-slate-700 rounded-xl text-sm font-semibold transition-all shadow-sm group">
                    <i class="fas fa-arrow-left group-hover:-translate-x-1 transition-transform"></i> Kembali
                </a>
            </div>
        </header>

        <!-- Scrollable Content -->
        <div class="flex-1 overflow-y-auto p-6 md:p-8 pb-20">
            
            <!-- Notifications -->
            <?php if($success): ?>
            <div class="mb-6 p-5 bg-gradient-to-r from-emerald-50 to-green-50 border border-emerald-200 text-emerald-700 rounded-2xl flex items-center gap-4 notification-enter shadow-lg shadow-emerald-500/10">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-emerald-400 to-emerald-600 flex items-center justify-center flex-shrink-0 shadow-lg shadow-emerald-500/30">
                    <i class="fas fa-check-circle text-white text-xl"></i>
                </div>
                <div class="flex-1">
                    <p class="font-bold text-emerald-900">Import Berhasil!</p>
                    <span class="text-sm font-medium"><?= $success_msg ?></span>
                </div>
                <button onclick="this.parentElement.remove()" class="text-emerald-400 hover:text-emerald-600 p-2 hover:bg-emerald-100 rounded-lg transition-all">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <?php endif; ?>
            <?php if($error): ?>
            <div class="mb-6 p-5 bg-gradient-to-r from-red-50 to-rose-50 border border-red-200 text-red-700 rounded-2xl flex items-center gap-4 notification-enter shadow-lg shadow-red-500/10">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-red-400 to-red-600 flex items-center justify-center flex-shrink-0 shadow-lg shadow-red-500/30">
                    <i class="fas fa-exclamation-circle text-white text-xl"></i>
                </div>
                <div class="flex-1">
                    <p class="font-bold text-red-900">Import Gagal!</p>
                    <span class="text-sm font-medium"><?= $error ?></span>
                </div>
                <button onclick="this.parentElement.remove()" class="text-red-400 hover:text-red-600 p-2 hover:bg-red-100 rounded-lg transition-all">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <?php endif; ?>

            <div class="max-w-6xl mx-auto space-y-8">
                
                <!-- Info Banner -->
                <div class="relative rounded-3xl p-8 text-white overflow-hidden shadow-2xl shadow-primary-900/20 animate-fade-in mesh-gradient">
                    <div class="absolute inset-0 bg-gradient-to-br from-primary-600/90 via-primary-700/90 to-primary-900/90"></div>
                    <div class="absolute top-0 right-0 w-96 h-96 bg-accent-400/20 rounded-full blur-3xl -mr-32 -mt-32 animate-pulse"></div>
                    <div class="absolute bottom-0 left-0 w-64 h-64 bg-white/10 rounded-full blur-3xl -ml-20 -mb-20"></div>
                    
                    <div class="relative z-10 flex items-start gap-5">
                        <div class="w-14 h-14 rounded-2xl bg-white/10 flex items-center justify-center flex-shrink-0 backdrop-blur-sm border border-white/20 shadow-xl">
                            <i class="fas fa-info-circle text-accent-400 text-2xl"></i>
                        </div>
                        <div class="flex-1">
                            <h3 class="font-bold text-2xl mb-3">Panduan Import Data</h3>
                            <ul class="space-y-2.5 text-primary-100 text-sm">
                                <li class="flex items-start gap-3">
                                    <i class="fas fa-check-circle mt-0.5 text-accent-400 flex-shrink-0"></i>
                                    <span>Pastikan file Excel sesuai format (seperti leger kelas)</span>
                                </li>
                                <li class="flex items-start gap-3">
                                    <i class="fas fa-check-circle mt-0.5 text-accent-400 flex-shrink-0"></i>
                                    <span>Data siswa dimulai dari <strong class="text-white font-bold">baris ke-7</strong></span>
                                </li>
                                <li class="flex items-start gap-3">
                                    <i class="fas fa-check-circle mt-0.5 text-accent-400 flex-shrink-0"></i>
                                    <span>Kolom PAKDBP boleh kosong (untuk siswa Muslim)</span>
                                </li>
                                <li class="flex items-start gap-3">
                                    <i class="fas fa-check-circle mt-0.5 text-accent-400 flex-shrink-0"></i>
                                    <span>Pilih kelas tujuan sebelum upload file</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Upload Form -->
                    <div class="lg:col-span-2 space-y-6">
                        <form id="importForm" action="import_excel.php" method="POST" enctype="multipart/form-data" class="space-y-6">
                            
                            <!-- ============================================ -->
                            <!-- 🔥 KELAS SELECTOR - DINAMIS DARI DATABASE -->
                            <!-- ============================================ -->
                            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6 card-hover animate-slide-up" style="animation-delay: 0.1s;">
                                <div class="flex items-center justify-between mb-4">
                                    <label class="block text-sm font-bold text-slate-800 flex items-center gap-2">
                                        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center shadow-lg shadow-primary-500/30">
                                            <i class="fas fa-chalkboard-teacher text-white text-sm"></i>
                                        </div>
                                        <span>Pilih Kelas Tujuan</span>
                                    </label>
                                    <span class="text-xs font-semibold text-primary-600 bg-primary-50 px-3 py-1 rounded-full border border-primary-200">
                                        <i class="fas fa-database mr-1"></i>
                                        <?= $total_kelas ?> Kelas Tersedia
                                    </span>
                                </div>

                                <?php if($total_kelas > 0): ?>
                                <!-- Grid dinamis: 2 kolom di mobile, 3 di tablet, 4 di desktop -->
                                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                                    <?php 
                                    $color_variants = [
                                        ['from' => 'from-blue-50', 'to' => 'to-cyan-50', 'icon' => 'text-blue-600', 'ring' => 'ring-blue-500'],
                                        ['from' => 'from-purple-50', 'to' => 'to-pink-50', 'icon' => 'text-purple-600', 'ring' => 'ring-purple-500'],
                                        ['from' => 'from-emerald-50', 'to' => 'to-teal-50', 'icon' => 'text-emerald-600', 'ring' => 'ring-emerald-500'],
                                        ['from' => 'from-amber-50', 'to' => 'to-orange-50', 'icon' => 'text-amber-600', 'ring' => 'ring-amber-500'],
                                        ['from' => 'from-rose-50', 'to' => 'to-red-50', 'icon' => 'text-rose-600', 'ring' => 'ring-rose-500'],
                                        ['from' => 'from-indigo-50', 'to' => 'to-violet-50', 'icon' => 'text-indigo-600', 'ring' => 'ring-indigo-500'],
                                    ];
                                    $idx = 0;
                                    foreach($kelas_array as $k): 
                                        $color = $color_variants[$idx % count($color_variants)];
                                        $is_selected = ($kelas_id == $k['id']);
                                        $idx++;
                                    ?>
                                    <label class="cursor-pointer group">
                                        <input type="radio" name="kelas_id" value="<?= $k['id'] ?>" class="hidden peer" <?= $is_selected?'checked':'' ?>>
                                        <div class="kelas-card peer-checked:border-primary-500 peer-checked:bg-gradient-to-br peer-checked:<?= $color['from'] ?> peer-checked:<?= $color['to'] ?> peer-checked:text-primary-700 border-2 border-slate-200 rounded-xl p-4 text-center hover:border-primary-300 relative overflow-hidden">
                                            <!-- Checkmark saat selected -->
                                            <?php if($is_selected): ?>
                                            <div class="absolute top-2 right-2 w-5 h-5 bg-primary-500 rounded-full flex items-center justify-center shadow-lg">
                                                <i class="fas fa-check text-white text-[10px]"></i>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <!-- Icon -->
                                            <div class="w-10 h-10 mx-auto mb-2 rounded-lg bg-gradient-to-br <?= $color['from'] ?> to-white flex items-center justify-center peer-checked:shadow-md transition-all">
                                                <i class="fas fa-school <?= $color['icon'] ?> text-lg"></i>
                                            </div>
                                            
                                            <!-- Nama Kelas -->
                                            <span class="font-bold text-sm block relative z-10"><?= htmlspecialchars($k['nama_kelas']) ?></span>
                                            
                                            <!-- Info Tambahan (opsional) -->
                                            <?php if(!empty($k['wali_kelas'])): ?>
                                            <span class="text-[10px] text-slate-500 mt-1 block truncate">
                                                <i class="fas fa-user-tie mr-1"></i><?= htmlspecialchars($k['wali_kelas']) ?>
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                    </label>
                                    <?php endforeach; ?>
                                </div>

                                <!-- Info Helper -->
                                <div class="mt-4 p-3 bg-gradient-to-r from-primary-50 to-blue-50 border border-primary-200 rounded-xl flex items-start gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-primary-500 to-primary-600 flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-lightbulb text-white text-xs"></i>
                                    </div>
                                    <div class="flex-1">
                                        <p class="text-xs font-semibold text-primary-900">Kelas tidak ada dalam daftar?</p>
                                        <p class="text-[11px] text-primary-700 mt-0.5">Tambahkan kelas baru melalui menu <strong>Pengaturan</strong> atau hubungi administrator untuk menambahkan data kelas ke database.</p>
                                    </div>
                                </div>
                                <?php else: ?>
                                <!-- Empty State jika belum ada kelas -->
                                <div class="p-8 text-center bg-gradient-to-br from-slate-50 to-slate-100 rounded-xl border-2 border-dashed border-slate-300">
                                    <div class="w-16 h-16 mx-auto bg-gradient-to-br from-slate-200 to-slate-300 rounded-2xl flex items-center justify-center mb-4">
                                        <i class="fas fa-school text-3xl text-slate-400"></i>
                                    </div>
                                    <p class="font-bold text-slate-700 mb-1">Belum Ada Kelas Terdaftar</p>
                                    <p class="text-sm text-slate-500 mb-4">Silakan tambahkan data kelas terlebih dahulu melalui menu Pengaturan.</p>
                                    <a href="pengaturan.php" class="inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-primary-500 to-primary-600 text-white rounded-xl text-sm font-semibold shadow-lg shadow-primary-500/30 hover:shadow-xl transition-all">
                                        <i class="fas fa-plus"></i> Tambah Kelas
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Drag & Drop Upload -->
                            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6 card-hover animate-slide-up" style="animation-delay: 0.2s;">
                                <label class="block text-sm font-bold text-slate-800 mb-4 flex items-center gap-2">
                                    <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center shadow-lg shadow-primary-500/30">
                                        <i class="fas fa-cloud-upload-alt text-white text-sm"></i>
                                    </div>
                                    <span>Upload File Excel</span>
                                </label>
                                
                                <div id="dropZone" class="upload-zone border-2 border-dashed border-slate-300 rounded-2xl p-10 text-center cursor-pointer relative group">
                                    <input type="file" name="file_excel" id="fileInput" accept=".xlsx,.xls" class="hidden" required>
                                    
                                    <div id="uploadPrompt">
                                        <div class="file-icon w-20 h-20 mx-auto bg-gradient-to-br from-primary-100 to-blue-100 rounded-2xl flex items-center justify-center mb-5 group-hover:from-primary-200 group-hover:to-blue-200 transition-all shadow-lg">
                                            <i class="fas fa-file-excel text-primary-600 text-3xl"></i>
                                        </div>
                                        <p class="font-bold text-slate-800 mb-2 text-lg">Klik atau drag & drop file Excel di sini</p>
                                        <p class="text-sm text-slate-500 mb-5">Format: .xlsx atau .xls (Maks. 10MB)</p>
                                        <div class="mt-5">
                                            <button type="button" class="px-6 py-3 bg-gradient-to-r from-primary-500 to-primary-600 text-white rounded-xl text-sm font-semibold hover:from-primary-600 hover:to-primary-700 transition-all shadow-lg shadow-primary-500/30 hover:shadow-xl hover:shadow-primary-500/40 active:scale-95">
                                                <i class="fas fa-folder-open mr-2"></i>Pilih File
                                            </button>
                                        </div>
                                    </div>

                                    <div id="fileSelected" class="hidden">
                                        <div class="flex items-center justify-center gap-5 mb-5">
                                            <div class="w-16 h-16 bg-gradient-to-br from-emerald-100 to-green-100 rounded-2xl flex items-center justify-center shadow-lg animate-bounce-in">
                                                <i class="fas fa-file-excel text-emerald-600 text-2xl"></i>
                                            </div>
                                            <div class="text-left">
                                                <p id="fileName" class="font-bold text-slate-800 text-lg">filename.xlsx</p>
                                                <p id="fileSize" class="text-sm text-slate-500 mt-1">0 KB</p>
                                                <div class="mt-2 flex items-center gap-2">
                                                    <i class="fas fa-check-circle text-emerald-500 text-xs"></i>
                                                    <span class="text-xs text-emerald-600 font-semibold">File valid</span>
                                                </div>
                                            </div>
                                        </div>
                                        <button type="button" id="removeFile" class="text-sm text-red-500 hover:text-red-600 font-semibold inline-flex items-center gap-2 px-4 py-2 hover:bg-red-50 rounded-lg transition-all">
                                            <i class="fas fa-trash"></i>Hapus & Pilih Ulang
                                        </button>
                                    </div>
                                </div>

                                <div id="validationBadge" class="mt-4 hidden">
                                    <div class="flex items-center gap-3 p-3 bg-gradient-to-r from-emerald-50 to-green-50 border border-emerald-200 rounded-xl">
                                        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-emerald-400 to-emerald-600 flex items-center justify-center flex-shrink-0">
                                            <i class="fas fa-check text-white text-xs"></i>
                                        </div>
                                        <span class="text-emerald-700 font-semibold text-sm">File valid & siap diimport</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex flex-col sm:flex-row gap-3 animate-slide-up" style="animation-delay: 0.3s;">
                                <button type="submit" name="import" id="submitBtn" disabled class="flex-1 py-4 bg-slate-300 text-white rounded-xl font-bold text-sm transition-all cursor-not-allowed flex items-center justify-center gap-2 shadow-lg">
                                    <i class="fas fa-upload"></i> Proses Import
                                </button>
                                <a href="dashboard.php?kelas=<?= $kelas_id ?>" class="flex-1 py-4 bg-white border-2 border-slate-200 hover:bg-slate-50 hover:border-slate-300 text-slate-700 rounded-xl font-bold text-sm transition-all flex items-center justify-center gap-2 shadow-sm">
                                    <i class="fas fa-arrow-left"></i> Batal
                                </a>
                            </div>
                        </form>
                    </div>

                    <!-- Sidebar Info -->
                    <div class="space-y-6">
                        <!-- Format Mapel -->
                        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6 card-hover animate-slide-up" style="animation-delay: 0.4s;">
                            <h3 class="font-bold text-slate-800 mb-5 flex items-center gap-3">
                                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center shadow-lg shadow-primary-500/30">
                                    <i class="fas fa-list-ol text-white text-sm"></i>
                                </div>
                                <span>Format Kolom</span>
                            </h3>
                            <div class="space-y-2 text-xs">
                                <div class="flex justify-between items-center py-3 px-3 rounded-lg hover:bg-slate-50 transition-colors border-b border-slate-100">
                                    <span class="text-slate-500 font-medium">Kolom A-D</span>
                                    <span class="font-bold text-slate-800">NO, NAMA, NISN, NIS</span>
                                </div>
                                <div class="flex justify-between items-center py-3 px-3 rounded-lg hover:bg-slate-50 transition-colors border-b border-slate-100">
                                    <span class="text-slate-500 font-medium">Kolom E</span>
                                    <span class="font-bold text-slate-800">PAI & Budi Pekerti</span>
                                </div>
                                <div class="flex justify-between items-center py-3 px-3 rounded-lg hover:bg-slate-50 transition-colors border-b border-slate-100">
                                    <span class="text-slate-500 font-medium">Kolom F</span>
                                    <span class="font-bold text-slate-800">PAK & Budi Pekerti</span>
                                </div>
                                <div class="flex justify-between items-center py-3 px-3 rounded-lg hover:bg-slate-50 transition-colors border-b border-slate-100">
                                    <span class="text-slate-500 font-medium">Kolom G-J</span>
                                    <span class="font-bold text-slate-800">PPKn, B.Indo, MTk, IPAS</span>
                                </div>
                                <div class="flex justify-between items-center py-3 px-3 rounded-lg hover:bg-slate-50 transition-colors border-b border-slate-100">
                                    <span class="text-slate-500 font-medium">Kolom K-P</span>
                                    <span class="font-bold text-slate-800">PJOK, B.Ing, Mulok, SBdP</span>
                                </div>
                                <div class="flex justify-between items-center py-3 px-3 rounded-lg hover:bg-slate-50 transition-colors border-b border-slate-100">
                                    <span class="text-slate-500 font-medium">Kolom Q-S</span>
                                    <span class="font-bold text-slate-800">Sakit, Izin, Alpa</span>
                                </div>
                                <div class="flex justify-between items-center py-3 px-3 rounded-lg hover:bg-slate-50 transition-colors">
                                    <span class="text-slate-500 font-medium">Kolom T</span>
                                    <span class="font-bold text-slate-800">Ekskul (Pramuka)</span>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Tips -->
                        <div class="bg-gradient-to-br from-amber-50 via-yellow-50 to-orange-50 rounded-2xl border-2 border-amber-200 p-6 card-hover animate-slide-up" style="animation-delay: 0.5s;">
                            <h3 class="font-bold text-amber-900 mb-4 flex items-center gap-3">
                                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-amber-400 to-amber-600 flex items-center justify-center shadow-lg shadow-amber-500/30">
                                    <i class="fas fa-lightbulb text-white text-sm"></i>
                                </div>
                                <span>Tips Import</span>
                            </h3>
                            <ul class="space-y-4 text-sm text-amber-900">
                                <li class="flex items-start gap-3">
                                    <span class="bg-gradient-to-br from-amber-400 to-amber-600 text-white w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0 text-xs font-bold shadow-lg">1</span>
                                    <span class="font-medium">Jika NISN sudah ada di database, data akan di<strong class="text-amber-700">update</strong> otomatis</span>
                                </li>
                                <li class="flex items-start gap-3">
                                    <span class="bg-gradient-to-br from-amber-400 to-amber-600 text-white w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0 text-xs font-bold shadow-lg">2</span>
                                    <span class="font-medium">Kosongkan kolom PAKDBP untuk siswa Muslim</span>
                                </li>
                                <li class="flex items-start gap-3">
                                    <span class="bg-gradient-to-br from-amber-400 to-amber-600 text-white w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0 text-xs font-bold shadow-lg">3</span>
                                    <span class="font-medium">Pastikan tidak ada baris kosong di antara data siswa</span>
                                </li>
                            </ul>
                        </div>

                        <!-- Contact Support -->
                        <div class="bg-gradient-to-br from-slate-800 via-slate-900 to-slate-950 rounded-2xl p-6 text-white relative overflow-hidden shadow-2xl card-hover animate-slide-up" style="animation-delay: 0.6s;">
                            <div class="absolute top-0 right-0 w-32 h-32 bg-primary-500/20 rounded-full blur-3xl -mr-16 -mt-16"></div>
                            <i class="fas fa-headset absolute -bottom-4 -right-4 text-7xl text-white/5"></i>
                            <div class="relative">
                                <div class="flex items-center gap-3 mb-4">
                                    <div class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center backdrop-blur-sm border border-white/20">
                                        <i class="fas fa-headset text-accent-400"></i>
                                    </div>
                                    <h3 class="font-bold text-lg">Butuh Bantuan?</h3>
                                </div>
                                <p class="text-sm text-slate-300 mb-4 leading-relaxed">Jika mengalami kendala saat import data, silakan hubungi admin.</p>
                                <a href="mailto:admin@sdncurug01.sch.id" class="w-full py-3 bg-white/10 hover:bg-white/20 backdrop-blur-sm rounded-xl text-sm font-semibold transition-all border border-white/20 hover:border-white/40 text-center block">
                                    <i class="fas fa-envelope mr-2"></i>Hubungi Admin
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
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

    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');
    const uploadPrompt = document.getElementById('uploadPrompt');
    const fileSelected = document.getElementById('fileSelected');
    const fileName = document.getElementById('fileName');
    const fileSize = document.getElementById('fileSize');
    const removeFile = document.getElementById('removeFile');
    const validationBadge = document.getElementById('validationBadge');
    const submitBtn = document.getElementById('submitBtn');

    dropZone.addEventListener('click', () => {
        if (!fileSelected.classList.contains('hidden')) return;
        fileInput.click();
    });

    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, preventDefaults, false);
    });
    function preventDefaults(e) { e.preventDefault(); e.stopPropagation(); }

    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => dropZone.classList.add('drag-active'), false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => dropZone.classList.remove('drag-active'), false);
    });

    dropZone.addEventListener('drop', (e) => {
        const files = e.dataTransfer.files;
        handleFiles(files);
    }, false);

    fileInput.addEventListener('change', () => {
        handleFiles(fileInput.files);
    });

    function handleFiles(files) {
        if (files.length === 0) return;
        const file = files[0];

        const validTypes = [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel'
        ];
        const validExtensions = ['.xlsx', '.xls'];
        const extension = '.' + file.name.split('.').pop().toLowerCase();

        if (!validTypes.includes(file.type) && !validExtensions.includes(extension)) {
            alert('Format file tidak valid. Gunakan .xlsx atau .xls');
            return;
        }

        if (file.size > 10 * 1024 * 1024) {
            alert('Ukuran file maksimal 10MB');
            return;
        }

        fileName.textContent = file.name;
        fileSize.textContent = (file.size / 1024).toFixed(1) + ' KB';

        uploadPrompt.classList.add('hidden');
        fileSelected.classList.remove('hidden');
        validationBadge.classList.remove('hidden');

        submitBtn.disabled = false;
        submitBtn.classList.remove('bg-slate-300', 'cursor-not-allowed');
        submitBtn.classList.add('bg-gradient-to-r', 'from-primary-500', 'to-primary-600', 'hover:from-primary-600', 'hover:to-primary-700', 'shadow-xl', 'shadow-primary-500/40', 'active:scale-95');
    }

    removeFile.addEventListener('click', (e) => {
        e.stopPropagation();
        fileInput.value = '';
        uploadPrompt.classList.remove('hidden');
        fileSelected.classList.add('hidden');
        validationBadge.classList.add('hidden');
        submitBtn.disabled = true;
        submitBtn.classList.add('bg-slate-300', 'cursor-not-allowed');
        submitBtn.classList.remove('bg-gradient-to-r', 'from-primary-500', 'to-primary-600', 'hover:from-primary-600', 'hover:to-primary-700', 'shadow-xl', 'shadow-primary-500/40', 'active:scale-95');
    });

    const form = document.getElementById('importForm');
    form.addEventListener('submit', function(e) {
        if (submitBtn.disabled) {
            e.preventDefault();
            return;
        }
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
        submitBtn.disabled = true;
    });
</script>
</body>
</html>