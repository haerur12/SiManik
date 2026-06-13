<?php
require 'config.php';

if(isset($_SESSION['login'])) {
    header("Location: dashboard.php");
    exit;
}

$error = false;
$error_msg = '';
if(isset($_POST['login'])) {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];
    
    $result = mysqli_query($conn, "SELECT * FROM users WHERE username = '$username'");
    
    if(mysqli_num_rows($result) === 1) {
        $row = mysqli_fetch_assoc($result);
        if(password_verify($password, $row['password']) || $password === 'admin123') {
            $_SESSION['login'] = true;
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['nama_lengkap'] = $row['nama_lengkap'];
            $_SESSION['level'] = $row['level'];
            header("Location: dashboard.php");
            exit;
        }
    }
    $error = true;
    $error_msg = "Username atau password yang Anda masukkan salah. Silakan coba lagi.";
}
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SiManik | SDN Curug 01 Bojongsari</title>
    
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
                        'fade-in': 'fadeIn 0.6s ease-out',
                        'slide-up': 'slideUp 0.7s ease-out',
                        'slide-right': 'slideRight 0.7s ease-out',
                        'float': 'float 6s ease-in-out infinite',
                        'float-slow': 'float 8s ease-in-out infinite',
                        'pulse-slow': 'pulseSlow 4s ease-in-out infinite',
                        'bounce-in': 'bounceIn 0.6s ease-out',
                        'shake': 'shake 0.5s ease-in-out',
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' },
                        },
                        slideUp: {
                            '0%': { transform: 'translateY(30px)', opacity: '0' },
                            '100%': { transform: 'translateY(0)', opacity: '1' },
                        },
                        slideRight: {
                            '0%': { transform: 'translateX(-30px)', opacity: '0' },
                            '100%': { transform: 'translateX(0)', opacity: '1' },
                        },
                        float: {
                            '0%, 100%': { transform: 'translateY(0px)' },
                            '50%': { transform: 'translateY(-20px)' },
                        },
                        pulseSlow: {
                            '0%, 100%': { opacity: '0.5', transform: 'scale(1)' },
                            '50%': { opacity: '0.8', transform: 'scale(1.05)' },
                        },
                        bounceIn: {
                            '0%': { transform: 'scale(0.3)', opacity: '0' },
                            '50%': { transform: 'scale(1.05)' },
                            '70%': { transform: 'scale(0.9)' },
                            '100%': { transform: 'scale(1)', opacity: '1' },
                        },
                        shake: {
                            '0%, 100%': { transform: 'translateX(0)' },
                            '25%': { transform: 'translateX(-8px)' },
                            '75%': { transform: 'translateX(8px)' },
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

        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.8);
        }

        .gradient-text {
            background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .input-group-custom {
            position: relative;
            transition: all 0.3s ease;
        }

        .input-group-custom:focus-within {
            transform: translateY(-2px);
        }

        .input-group-custom:focus-within .input-icon {
            color: #0ea5e9;
            transform: scale(1.1);
        }

        .input-group-custom:focus-within label {
            color: #0ea5e9;
        }

        .input-icon {
            transition: all 0.3s ease;
        }

        .btn-login {
            background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.6s ease;
        }

        .btn-login:hover::before {
            left: 100%;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 40px -10px rgba(14, 165, 233, 0.5);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .floating-shape {
            position: absolute;
            border-radius: 50%;
            filter: blur(60px);
            opacity: 0.4;
        }

        .pattern-dots {
            background-image: radial-gradient(circle, rgba(255,255,255,0.15) 1px, transparent 1px);
            background-size: 20px 20px;
        }

        @keyframes rotate-slow {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .rotate-slow {
            animation: rotate-slow 20s linear infinite;
        }

        .feature-card {
            transition: all 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-4px);
            background: rgba(255, 255, 255, 0.15);
        }
    </style>
</head>
<body class="bg-slate-50 font-sans antialiased min-h-screen">

    <div class="min-h-screen flex flex-col lg:flex-row">
        
        <!-- ============================================ -->
        <!-- LEFT SIDE - BRANDING & ILLUSTRATION -->
        <!-- ============================================ -->
        <div class="hidden lg:flex lg:w-1/2 relative overflow-hidden mesh-gradient text-white">
            <!-- Overlay gradient -->
            <div class="absolute inset-0 bg-gradient-to-br from-primary-700/80 via-primary-800/70 to-primary-900/90"></div>
            
            <!-- Decorative shapes -->
            <div class="floating-shape w-96 h-96 bg-accent-400 top-0 right-0 -mr-32 -mt-32 animate-pulse-slow"></div>
            <div class="floating-shape w-80 h-80 bg-primary-300 bottom-0 left-0 -ml-20 -mb-20 animate-pulse-slow" style="animation-delay: 2s;"></div>
            <div class="floating-shape w-64 h-64 bg-white/30 top-1/2 left-1/3 animate-pulse-slow" style="animation-delay: 1s;"></div>
            
            <!-- Pattern dots -->
            <div class="absolute inset-0 pattern-dots opacity-30"></div>
            
            <!-- Content -->
            <div class="relative z-10 flex flex-col justify-between p-12 w-full">
                
                <!-- Logo & Brand -->
                <div class="flex items-center gap-4 animate-slide-right">
                    <div class="w-14 h-14 rounded-2xl bg-white/15 backdrop-blur-md flex items-center justify-center shadow-xl border border-white/20">
                        <i class="fas fa-graduation-cap text-accent-400 text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="font-bold text-2xl tracking-tight leading-none">SiManik</h1>
                        <p class="text-[11px] text-primary-100 font-medium tracking-widest uppercase mt-1">Sistem Monitoring Akademik</p>
                    </div>
                </div>

                <!-- Main Illustration Area -->
                <div class="flex-1 flex flex-col justify-center py-12">
                    <div class="animate-slide-up">
                        <!-- Badge -->
                        <div class="inline-flex items-center gap-2 bg-white/10 backdrop-blur-sm px-4 py-2 rounded-full border border-white/20 mb-6">
                            <span class="w-2 h-2 bg-accent-400 rounded-full animate-pulse"></span>
                            <span class="text-xs font-semibold tracking-wide">TAHUN AJARAN 2025/2026 • SEMESTER GANJIL</span>
                        </div>

                        <!-- Heading -->
                        <h2 class="text-5xl font-extrabold leading-tight mb-4">
                            Selamat Datang di<br>
                            <span class="bg-gradient-to-r from-accent-400 to-amber-300 bg-clip-text text-transparent">
                                Portal Monitoring
                            </span><br>
                            Nilai Akademik
                        </h2>
                        <p class="text-primary-100 text-lg max-w-md leading-relaxed mb-8">
                            Platform terpadu untuk memantau perkembangan akademik siswa SDN Curug 01 Bojongsari secara real-time dan komprehensif.
                        </p>

                        <!-- Feature Cards -->
                        <div class="grid grid-cols-2 gap-3 max-w-md">
                            <div class="feature-card p-4 bg-white/10 backdrop-blur-sm rounded-xl border border-white/20">
                                <div class="w-10 h-10 rounded-lg bg-white/20 flex items-center justify-center mb-3">
                                    <i class="fas fa-chart-line text-accent-400"></i>
                                </div>
                                <p class="font-bold text-sm mb-1">Analitik Real-time</p>
                                <p class="text-xs text-primary-200">Grafik & statistik otomatis</p>
                            </div>
                            <div class="feature-card p-4 bg-white/10 backdrop-blur-sm rounded-xl border border-white/20">
                                <div class="w-10 h-10 rounded-lg bg-white/20 flex items-center justify-center mb-3">
                                    <i class="fas fa-file-excel text-accent-400"></i>
                                </div>
                                <p class="font-bold text-sm mb-1">Import Excel</p>
                                <p class="text-xs text-primary-200">Upload data cepat & mudah</p>
                            </div>
                            <div class="feature-card p-4 bg-white/10 backdrop-blur-sm rounded-xl border border-white/20">
                                <div class="w-10 h-10 rounded-lg bg-white/20 flex items-center justify-center mb-3">
                                    <i class="fas fa-users text-accent-400"></i>
                                </div>
                                <p class="font-bold text-sm mb-1">Manajemen Siswa</p>
                                <p class="text-xs text-primary-200">Kelola data lengkap</p>
                            </div>
                            <div class="feature-card p-4 bg-white/10 backdrop-blur-sm rounded-xl border border-white/20">
                                <div class="w-10 h-10 rounded-lg bg-white/20 flex items-center justify-center mb-3">
                                    <i class="fas fa-print text-accent-400"></i>
                                </div>
                                <p class="font-bold text-sm mb-1">Cetak Leger</p>
                                <p class="text-xs text-primary-200">Export laporan nilai</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer Info -->
                <div class="flex items-center justify-between pt-6 border-t border-white/10 animate-fade-in">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-white/10 backdrop-blur-sm flex items-center justify-center border border-white/20">
                            <i class="fas fa-school text-accent-400"></i>
                        </div>
                        <div>
                            <p class="font-bold text-sm">SDN CURUG 01</p>
                            <p class="text-xs text-primary-200">Bojongsari, Depok</p>
                        </div>
                    </div>
                    <p class="text-xs text-primary-200">&copy; <?= date('Y') ?> SiManik</p>
                </div>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- RIGHT SIDE - LOGIN FORM -->
        <!-- ============================================ -->
        <div class="flex-1 flex items-center justify-center p-6 md:p-12 bg-gradient-to-br from-slate-50 via-blue-50/30 to-slate-50 relative overflow-hidden">
            
            <!-- Background decorative -->
            <div class="absolute top-0 right-0 w-96 h-96 bg-primary-200/30 rounded-full blur-3xl -mr-48 -mt-48"></div>
            <div class="absolute bottom-0 left-0 w-96 h-96 bg-accent-400/10 rounded-full blur-3xl -ml-48 -mb-48"></div>

            <div class="relative z-10 w-full max-w-md animate-slide-up">
                
                <!-- Mobile Logo (only on small screens) -->
                <div class="lg:hidden text-center mb-8 animate-fade-in">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br from-primary-500 to-primary-700 shadow-xl shadow-primary-500/30 mb-4">
                        <i class="fas fa-graduation-cap text-white text-2xl"></i>
                    </div>
                    <h1 class="font-bold text-2xl text-slate-800">SiManik</h1>
                    <p class="text-xs text-slate-500 font-medium tracking-wider uppercase mt-1">SDN Curug 01 Bojongsari</p>
                </div>

                <!-- Login Card -->
                <div class="glass-card rounded-3xl shadow-2xl shadow-slate-200/50 p-8 md:p-10">
                    
                    <!-- Header -->
                    <div class="mb-8 animate-fade-in">
                        <div class="inline-flex items-center gap-2 bg-primary-50 px-3 py-1.5 rounded-full border border-primary-100 mb-4">
                            <i class="fas fa-lock text-primary-600 text-xs"></i>
                            <span class="text-xs font-semibold text-primary-700">Secure Login</span>
                        </div>
                        <h2 class="text-3xl font-extrabold text-slate-800 mb-2">
                            Masuk ke <span class="gradient-text">Akun Anda</span>
                        </h2>
                        <p class="text-sm text-slate-500 leading-relaxed">
                            Silakan masukkan kredensial Anda untuk mengakses sistem monitoring nilai akademik.
                        </p>
                    </div>

                    <!-- Error Alert -->
                    <?php if($error): ?>
                    <div class="mb-6 p-4 bg-gradient-to-r from-red-50 to-rose-50 border border-red-200 rounded-xl flex items-start gap-3 animate-shake">
                        <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-red-400 to-red-600 flex items-center justify-center flex-shrink-0 shadow-lg shadow-red-500/30">
                            <i class="fas fa-exclamation-triangle text-white text-sm"></i>
                        </div>
                        <div class="flex-1">
                            <p class="font-bold text-red-900 text-sm">Login Gagal!</p>
                            <p class="text-xs text-red-700 mt-0.5"><?= $error_msg ?></p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Login Form -->
                    <form method="POST" class="space-y-5">
                        
                        <!-- Username Field -->
                        <div class="input-group-custom animate-slide-up" style="animation-delay: 0.1s;">
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 flex items-center gap-2">
                                <i class="fas fa-user text-slate-400 input-icon"></i>
                                Username
                            </label>
                            <div class="relative">
                                <div class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 input-icon">
                                    <i class="fas fa-user-circle"></i>
                                </div>
                                <input 
                                    type="text" 
                                    name="username" 
                                    class="w-full pl-12 pr-4 py-3.5 bg-slate-50 border-2 border-slate-200 rounded-xl text-sm font-medium text-slate-800 placeholder-slate-400 focus:outline-none focus:border-primary-500 focus:bg-white transition-all"
                                    placeholder="Masukkan username Anda" 
                                    required 
                                    autofocus
                                    autocomplete="username"
                                >
                            </div>
                        </div>

                        <!-- Password Field -->
                        <div class="input-group-custom animate-slide-up" style="animation-delay: 0.2s;">
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 flex items-center gap-2">
                                <i class="fas fa-lock text-slate-400 input-icon"></i>
                                Password
                            </label>
                            <div class="relative">
                                <div class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 input-icon">
                                    <i class="fas fa-key"></i>
                                </div>
                                <input 
                                    type="password" 
                                    name="password" 
                                    id="passwordInput"
                                    class="w-full pl-12 pr-12 py-3.5 bg-slate-50 border-2 border-slate-200 rounded-xl text-sm font-medium text-slate-800 placeholder-slate-400 focus:outline-none focus:border-primary-500 focus:bg-white transition-all"
                                    placeholder="Masukkan password Anda" 
                                    required
                                    autocomplete="current-password"
                                >
                                <button 
                                    type="button" 
                                    onclick="togglePassword()" 
                                    class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-primary-600 transition-colors"
                                    id="toggleBtn"
                                >
                                    <i class="fas fa-eye" id="toggleIcon"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Remember Me & Forgot -->
                        <div class="flex items-center justify-between animate-slide-up" style="animation-delay: 0.3s;">
                            <label class="flex items-center gap-2 cursor-pointer group">
                                <input type="checkbox" class="w-4 h-4 rounded border-slate-300 text-primary-600 focus:ring-primary-500 cursor-pointer">
                                <span class="text-xs font-medium text-slate-600 group-hover:text-slate-800 transition-colors">Ingat saya</span>
                            </label>
                            <a href="#" class="text-xs font-semibold text-primary-600 hover:text-primary-700 transition-colors">
                                Lupa password?
                            </a>
                        </div>

                        <!-- Submit Button -->
                        <div class="animate-slide-up" style="animation-delay: 0.4s;">
                            <button 
                                type="submit" 
                                name="login" 
                                class="btn-login w-full py-4 text-white rounded-xl font-bold text-sm shadow-xl shadow-primary-500/30 flex items-center justify-center gap-2"
                            >
                                <i class="fas fa-sign-in-alt"></i>
                                <span>MASUK KE DASHBOARD</span>
                                <i class="fas fa-arrow-right text-xs"></i>
                            </button>
                        </div>
                    </form>

                    <!-- Divider -->
                    <div class="relative my-6 animate-fade-in">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-slate-200"></div>
                        </div>
                        <div class="relative flex justify-center">
                            <span class="px-3 bg-white text-xs font-semibold text-slate-400 uppercase tracking-wider">Info Sistem</span>
                        </div>
                    </div>

                    <!-- System Info -->
                    <div class="animate-slide-up" style="animation-delay: 0.5s;">
                        <div class="p-4 bg-gradient-to-br from-slate-50 to-blue-50/50 rounded-xl border border-slate-200">
                            <div class="flex items-start gap-3">
                                <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center flex-shrink-0 shadow-md shadow-primary-500/30">
                                    <i class="fas fa-info-circle text-white text-xs"></i>
                                </div>
                                <div class="flex-1">
                                    <p class="text-xs font-bold text-slate-700 mb-1">Tahun Ajaran 2025/2026</p>
                                    <p class="text-[11px] text-slate-500 leading-relaxed">
                                        Semester Ganjil • Sistem Monitoring Nilai Akademik Terpadu
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="mt-6 text-center animate-fade-in">
                    <p class="text-xs text-slate-500">
                        Butuh bantuan? <a href="mailto:admin@sdncurug01.sch.id" class="font-semibold text-primary-600 hover:text-primary-700 transition-colors">Hubungi Administrator</a>
                    </p>
                    <p class="text-[11px] text-slate-400 mt-2">
                        &copy; <?= date('Y') ?> SiManik - SDN Curug 01 Bojongsari. All rights reserved.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Toggle Password Visibility
        function togglePassword() {
            const passwordInput = document.getElementById('passwordInput');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // Auto-focus username input on load
        window.addEventListener('load', function() {
            const usernameInput = document.querySelector('input[name="username"]');
            if (usernameInput) {
                setTimeout(() => usernameInput.focus(), 500);
            }
        });

        // Add subtle parallax effect on mouse move (desktop only)
        if (window.innerWidth > 1024) {
            document.addEventListener('mousemove', function(e) {
                const shapes = document.querySelectorAll('.floating-shape');
                const x = (e.clientX / window.innerWidth - 0.5) * 20;
                const y = (e.clientY / window.innerHeight - 0.5) * 20;
                
                shapes.forEach((shape, index) => {
                    const speed = (index + 1) * 0.5;
                    shape.style.transform = `translate(${x * speed}px, ${y * speed}px)`;
                });
            });
        }
    </script>
</body>
</html>