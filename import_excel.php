<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Data Excel - SiManik</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] },
                    colors: {
                        brand: {
                            50: '#eff6ff', 100: '#dbeafe', 500: '#3b82f6',
                            600: '#2563eb', 700: '#1d4ed8', 800: '#1e40af', 900: '#1e3a8a'
                        },
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
        .glass-header {
            background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px); border-bottom: 1px solid rgba(226, 232, 240, 0.6);
        }
        .drag-active { border-color: #3b82f6 !important; background-color: #eff6ff !important; transform: scale(1.02); }
        .pulse-ring { animation: pulse-ring 1.5s cubic-bezier(0.455, 0.03, 0.515, 0.955) infinite; }
        @keyframes pulse-ring { 0% { transform: scale(0.9); opacity: 0.5; } 50% { transform: scale(1); opacity: 0.8; } 100% { transform: scale(0.9); opacity: 0.5; } }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 font-sans antialiased">

<div class="flex h-screen overflow-hidden">
    <!-- Sidebar -->
    <aside class="hidden md:flex flex-col w-72 bg-slate-900 text-white h-screen fixed z-30 shadow-2xl">
        <div class="h-20 flex items-center px-8 border-b border-slate-800 bg-slate-950">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-brand-600 to-brand-800 flex items-center justify-center">
                    <i class="fas fa-graduation-cap text-accent-400 text-lg"></i>
                </div>
                <div>
                    <h1 class="font-bold text-lg tracking-tight">SiManik</h1>
                    <p class="text-[10px] text-slate-400 font-medium tracking-wider">Monitoring Nilai</p>
                </div>
            </div>
        </div>
        <nav class="flex-1 overflow-y-auto py-6 px-4 space-y-1">
            <p class="px-4 text-xs font-semibold text-slate-500 uppercase tracking-widest mb-4">Menu Utama</p>
            <a href="dashboard.php" class="flex items-center gap-4 px-4 py-3 text-slate-400 hover:text-white hover:bg-slate-800 rounded-xl transition-all">
                <i class="fas fa-home w-5 text-center"></i><span class="font-medium">Dashboard</span>
            </a>
            <a href="#" class="flex items-center gap-4 px-4 py-3 bg-gradient-to-r from-brand-700 to-brand-600 text-white rounded-xl shadow-md shadow-brand-900/40">
                <i class="fas fa-file-excel w-5 text-center"></i><span class="font-medium relative">Import Data <span class="absolute -top-1 -right-1 w-2 h-2 bg-accent-400 rounded-full"></span></span>
            </a>
            <a href="leger.php" class="flex items-center gap-4 px-4 py-3 text-slate-400 hover:text-white hover:bg-slate-800 rounded-xl transition-all">
                <i class="fas fa-book-open w-5 text-center"></i><span class="font-medium">Leger Nilai</span>
            </a>
            <a href="grafik_nilai.php" class="flex items-center gap-4 px-4 py-3 text-slate-400 hover:text-white hover:bg-slate-800 rounded-xl transition-all">
                <i class="fas fa-chart-pie w-5 text-center"></i><span class="font-medium">Analitik Grafik</span>
            </a>
        </nav>
        <div class="p-4 border-t border-slate-800 bg-slate-950">
            <div class="flex items-center gap-3">
                <img src="https://ui-avatars.com/api/?name=Wali+Kelas&background=f59e0b&color=fff" class="w-9 h-9 rounded-full ring-2 ring-slate-700">
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-white">Wali Kelas 4A</p>
                    <p class="text-xs text-slate-500">Admin SDN Curug 01</p>
                </div>
                <a href="logout.php" class="text-slate-500 hover:text-red-400"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col h-screen overflow-hidden md:ml-72">
        <!-- Header -->
        <header class="glass-header h-16 px-6 md:px-8 flex items-center justify-between sticky top-0 z-20">
            <div class="flex items-center gap-3">
                <h2 class="text-lg font-bold text-slate-800">Import Data Excel</h2>
                <span class="px-2 py-0.5 bg-brand-50 text-brand-700 rounded text-[10px] font-bold">BETA</span>
            </div>
            <a href="dashboard.php" class="flex items-center gap-2 text-sm text-slate-600 hover:text-brand-600 transition-colors">
                <i class="fas fa-arrow-left text-xs"></i><span class="hidden md:inline">Kembali ke Dashboard</span>
            </a>
        </header>

        <!-- Scrollable Content -->
        <div class="flex-1 overflow-y-auto p-6 md:p-8 pb-20">
            <div class="max-w-5xl mx-auto space-y-8">
                
                <!-- Info Banner -->
                <div class="bg-gradient-to-r from-brand-900 to-brand-700 rounded-2xl p-6 text-white relative overflow-hidden shadow-lg shadow-brand-900/20">
                    <div class="absolute top-0 right-0 w-48 h-48 bg-accent-500/20 rounded-full blur-3xl -mr-12 -mt-12"></div>
                    <div class="relative z-10 flex items-start gap-4">
                        <div class="w-12 h-12 rounded-xl bg-white/10 flex items-center justify-center flex-shrink-0 backdrop-blur-sm">
                            <i class="fas fa-info-circle text-accent-400 text-xl"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-lg mb-2">Panduan Import Data</h3>
                            <ul class="space-y-2 text-brand-100 text-sm">
                                <li class="flex items-start gap-2"><i class="fas fa-check-circle mt-0.5 text-accent-400"></i> Pastikan file Excel sesuai format (seperti leger kelas)</li>
                                <li class="flex items-start gap-2"><i class="fas fa-check-circle mt-0.5 text-accent-400"></i> Data siswa dimulai dari <strong class="text-white">baris ke-7</strong></li>
                                <li class="flex items-start gap-2"><i class="fas fa-check-circle mt-0.5 text-accent-400"></i> Kolom PAKDBP boleh kosong (untuk siswa Muslim)</li>
                                <li class="flex items-start gap-2"><i class="fas fa-check-circle mt-0.5 text-accent-400"></i> Pilih kelas tujuan sebelum upload file</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Upload Form -->
                    <div class="lg:col-span-2 space-y-6">
                        <form id="importForm" action="import_excel.php" method="POST" enctype="multipart/form-data" class="space-y-6">
                            
                            <!-- Kelas Selector -->
                            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                                <label class="block text-sm font-semibold text-slate-700 mb-3">
                                    <i class="fas fa-chalkboard-teacher text-brand-600 mr-2"></i>Pilih Kelas Tujuan
                                </label>
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                    <label class="cursor-pointer">
                                        <input type="radio" name="kelas_id" value="1" class="hidden peer" checked>
                                        <div class="peer-checked:border-brand-500 peer-checked:bg-brand-50 peer-checked:text-brand-700 border border-slate-200 rounded-xl p-4 text-center hover:border-brand-300 transition-all">
                                            <i class="fas fa-school text-lg mb-1 block opacity-50 peer-checked:opacity-100"></i>
                                            <span class="font-bold text-sm">Kelas 4A</span>
                                        </div>
                                    </label>
                                    <label class="cursor-pointer">
                                        <input type="radio" name="kelas_id" value="2" class="hidden peer">
                                        <div class="peer-checked:border-brand-500 peer-checked:bg-brand-50 peer-checked:text-brand-700 border border-slate-200 rounded-xl p-4 text-center hover:border-brand-300 transition-all">
                                            <i class="fas fa-school text-lg mb-1 block opacity-50 peer-checked:opacity-100"></i>
                                            <span class="font-bold text-sm">Kelas 4B</span>
                                        </div>
                                    </label>
                                    <label class="cursor-pointer">
                                        <input type="radio" name="kelas_id" value="3" class="hidden peer">
                                        <div class="peer-checked:border-brand-500 peer-checked:bg-brand-50 peer-checked:text-brand-700 border border-slate-200 rounded-xl p-4 text-center hover:border-brand-300 transition-all">
                                            <i class="fas fa-school text-lg mb-1 block opacity-50 peer-checked:opacity-100"></i>
                                            <span class="font-bold text-sm">Kelas 4C</span>
                                        </div>
                                    </label>
                                    <label class="cursor-pointer">
                                        <input type="radio" name="kelas_id" value="4" class="hidden peer">
                                        <div class="peer-checked:border-brand-500 peer-checked:bg-brand-50 peer-checked:text-brand-700 border border-slate-200 rounded-xl p-4 text-center hover:border-brand-300 transition-all">
                                            <i class="fas fa-school text-lg mb-1 block opacity-50 peer-checked:opacity-100"></i>
                                            <span class="font-bold text-sm">Kelas 4D</span>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <!-- Drag & Drop Upload -->
                            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                                <label class="block text-sm font-semibold text-slate-700 mb-3">
                                    <i class="fas fa-cloud-upload-alt text-brand-600 mr-2"></i>Upload File Excel
                                </label>
                                
                                <div id="dropZone" class="border-2 border-dashed border-slate-300 rounded-xl p-8 text-center cursor-pointer hover:border-brand-400 hover:bg-brand-50/50 transition-all relative group">
                                    <input type="file" name="file_excel" id="fileInput" accept=".xlsx,.xls" class="hidden" required>
                                    
                                    <div id="uploadPrompt">
                                        <div class="w-16 h-16 mx-auto bg-slate-100 rounded-full flex items-center justify-center mb-4 group-hover:bg-brand-100 transition-colors">
                                            <i class="fas fa-file-excel text-slate-400 text-2xl group-hover:text-brand-600 transition-colors"></i>
                                        </div>
                                        <p class="font-semibold text-slate-700 mb-1">Klik atau drag & drop file Excel di sini</p>
                                        <p class="text-sm text-slate-500">Format: .xlsx atau .xls (Maks. 10MB)</p>
                                        <div class="mt-4">
                                            <button type="button" class="px-4 py-2 bg-brand-600 text-white rounded-lg text-sm font-medium hover:bg-brand-700 transition-colors shadow-sm shadow-brand-200">
                                                <i class="fas fa-folder-open mr-2"></i>Pilih File
                                            </button>
                                        </div>
                                    </div>

                                    <div id="fileSelected" class="hidden">
                                        <div class="flex items-center justify-center gap-4 mb-4">
                                            <div class="w-12 h-12 bg-emerald-100 rounded-xl flex items-center justify-center">
                                                <i class="fas fa-file-excel text-emerald-600 text-xl"></i>
                                            </div>
                                            <div class="text-left">
                                                <p id="fileName" class="font-semibold text-slate-800">filename.xlsx</p>
                                                <p id="fileSize" class="text-xs text-slate-500">0 KB</p>
                                            </div>
                                        </div>
                                        <button type="button" id="removeFile" class="text-sm text-red-500 hover:text-red-600 font-medium">
                                            <i class="fas fa-trash mr-1"></i>Hapus & Pilih Ulang
                                        </button>
                                    </div>
                                </div>

                                <!-- Validation Badge -->
                                <div id="validationBadge" class="mt-3 hidden">
                                    <div class="flex items-center gap-2 text-sm">
                                        <i class="fas fa-check-circle text-emerald-500"></i>
                                        <span class="text-emerald-600 font-medium">File valid & siap diimport</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex flex-col sm:flex-row gap-3">
                                <button type="submit" id="submitBtn" disabled class="flex-1 py-3 bg-slate-300 text-white rounded-xl font-semibold text-sm transition-all cursor-not-allowed flex items-center justify-center gap-2">
                                    <i class="fas fa-upload"></i> Proses Import
                                </button>
                                <a href="dashboard.php" class="flex-1 py-3 bg-white border border-slate-300 text-slate-700 rounded-xl font-semibold text-sm hover:bg-slate-50 transition-all flex items-center justify-center gap-2">
                                    <i class="fas fa-arrow-left"></i> Batal
                                </a>
                            </div>
                        </form>
                    </div>

                    <!-- Sidebar Info -->
                    <div class="space-y-6">
                        <!-- Format Mapel -->
                        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                            <h3 class="font-bold text-slate-800 mb-4 flex items-center gap-2">
                                <i class="fas fa-list-ol text-brand-600"></i> Format Kolom
                            </h3>
                            <div class="space-y-2 text-xs">
                                <div class="flex justify-between py-2 border-b border-slate-100">
                                    <span class="text-slate-500">Kolom A-D</span>
                                    <span class="font-medium text-slate-700">NO, NAMA, NISN, NIS</span>
                                </div>
                                <div class="flex justify-between py-2 border-b border-slate-100">
                                    <span class="text-slate-500">Kolom E</span>
                                    <span class="font-medium text-slate-700">PAI & Budi Pekerti</span>
                                </div>
                                <div class="flex justify-between py-2 border-b border-slate-100">
                                    <span class="text-slate-500">Kolom F</span>
                                    <span class="font-medium text-slate-700">PAK & Budi Pekerti</span>
                                </div>
                                <div class="flex justify-between py-2 border-b border-slate-100">
                                    <span class="text-slate-500">Kolom G-J</span>
                                    <span class="font-medium text-slate-700">PPKn, B.Indo, MTk, IPAS</span>
                                </div>
                                <div class="flex justify-between py-2 border-b border-slate-100">
                                    <span class="text-slate-500">Kolom K-P</span>
                                    <span class="font-medium text-slate-700">PJOK, B.Ing, Mulok, SBdP</span>
                                </div>
                                <div class="flex justify-between py-2 border-b border-slate-100">
                                    <span class="text-slate-500">Kolom Q-S</span>
                                    <span class="font-medium text-slate-700">Sakit, Izin, Alpa</span>
                                </div>
                                <div class="flex justify-between py-2">
                                    <span class="text-slate-500">Kolom T</span>
                                    <span class="font-medium text-slate-700">Ekskul (Pramuka)</span>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Tips -->
                        <div class="bg-amber-50 rounded-2xl border border-amber-200 p-5">
                            <h3 class="font-bold text-amber-800 mb-3 flex items-center gap-2">
                                <i class="fas fa-lightbulb text-amber-600"></i> Tips Import
                            </h3>
                            <ul class="space-y-3 text-sm text-amber-900">
                                <li class="flex items-start gap-2">
                                    <span class="bg-amber-100 text-amber-600 w-5 h-5 rounded-full flex items-center justify-center flex-shrink-0 text-[10px] font-bold">1</span>
                                    <span>Jika NISN sudah ada di database, data akan di<strong>update</strong> otomatis</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <span class="bg-amber-100 text-amber-600 w-5 h-5 rounded-full flex items-center justify-center flex-shrink-0 text-[10px] font-bold">2</span>
                                    <span>Kosongkan kolom PAKDBP untuk siswa Muslim</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <span class="bg-amber-100 text-amber-600 w-5 h-5 rounded-full flex items-center justify-center flex-shrink-0 text-[10px] font-bold">3</span>
                                    <span>Pastikan tidak ada baris kosong di antara data siswa</span>
                                </li>
                            </ul>
                        </div>

                        <!-- Contact Support -->
                        <div class="bg-slate-800 rounded-2xl p-5 text-white">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center">
                                    <i class="fas fa-headset text-accent-400"></i>
                                </div>
                                <h3 class="font-bold">Butuh Bantuan?</h3>
                            </div>
                            <p class="text-sm text-slate-400 mb-3">Jika mengalami kendala saat import data, silakan hubungi admin.</p>
                            <button class="w-full py-2 bg-white/10 hover:bg-white/20 rounded-lg text-sm font-medium transition-colors border border-white/10">
                                <i class="fas fa-envelope mr-2"></i>Hubungi Admin
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
    // Elements
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');
    const uploadPrompt = document.getElementById('uploadPrompt');
    const fileSelected = document.getElementById('fileSelected');
    const fileName = document.getElementById('fileName');
    const fileSize = document.getElementById('fileSize');
    const removeFile = document.getElementById('removeFile');
    const validationBadge = document.getElementById('validationBadge');
    const submitBtn = document.getElementById('submitBtn');

    // Click to upload
    dropZone.addEventListener('click', () => {
        if (!fileSelected.classList.contains('hidden')) return;
        fileInput.click();
    });

    // Drag & Drop events
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

        // Validation
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

        // Show file info
        fileName.textContent = file.name;
        fileSize.textContent = (file.size / 1024).toFixed(1) + ' KB';

        uploadPrompt.classList.add('hidden');
        fileSelected.classList.remove('hidden');
        validationBadge.classList.remove('hidden');

        // Enable submit button
        submitBtn.disabled = false;
        submitBtn.classList.remove('bg-slate-300', 'cursor-not-allowed');
        submitBtn.classList.add('bg-brand-600', 'hover:bg-brand-700', 'shadow-md', 'shadow-brand-200', 'active:scale-[0.98]');
    }

    removeFile.addEventListener('click', (e) => {
        e.stopPropagation();
        fileInput.value = '';
        uploadPrompt.classList.remove('hidden');
        fileSelected.classList.add('hidden');
        validationBadge.classList.add('hidden');
        submitBtn.disabled = true;
        submitBtn.classList.add('bg-slate-300', 'cursor-not-allowed');
        submitBtn.classList.remove('bg-brand-600', 'hover:bg-brand-700', 'shadow-md', 'shadow-brand-200', 'active:scale-[0.98]');
    });

    // Submit animation
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

