<?php
require_once dirname(dirname(__DIR__)) . '/common/constants.php';
require_once ENV_CONFIG_FILE;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GMN Revision App | Gyanmanjari Vidyapith</title>
    <link rel="icon" type="image/x-icon" href="<?php echo BASE_URL; ?>/assets/images/logogmn.png">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap"
        rel="stylesheet">

    <style>
        body {
            font-family: 'Outfit', sans-serif;
        }

        .hero-gradient {
            background: radial-gradient(circle at top right, #4f46e5 0%, #1e1b4b 100%);
        }

        .feature-card {
            background: white;
            border: 1px solid #e2e8f0;
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .feature-card:hover {
            transform: translateY(-12px);
            border-color: #4f46e5;
            box-shadow: 0 30px 60px -12px rgba(79, 70, 229, 0.15);
        }

        .animate-pulse-slow {
            animation: pulse-slow 4s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }

        @keyframes pulse-slow {

            0%,
            100% {
                opacity: 0.1;
                transform: scale(1);
            }

            50% {
                opacity: 0.3;
                transform: scale(1.1);
            }
        }
    </style>
</head>

<body class="bg-white text-slate-900 overflow-x-hidden">
    <?php include dirname(dirname(__DIR__)) . '/include/public-header.php'; ?>

    <!-- Hero Section -->
    <section class="relative pt-32 pb-48 hero-gradient text-white overflow-hidden">
        <div class="absolute inset-0 z-0">
            <div
                class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[800px] h-[800px] bg-indigo-500/20 rounded-full blur-[120px] animate-pulse-slow">
            </div>
        </div>

        <div class="container mx-auto px-4 relative z-10">
            <div class="max-w-4xl mx-auto text-center">
                <div
                    class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-500/20 backdrop-blur-md rounded-full text-indigo-200 text-sm font-bold tracking-widest uppercase mb-8 border border-white/10">
                    <span class="w-2 h-2 bg-indigo-400 rounded-full animate-ping"></span>
                    Master Your Exams
                </div>
                <h1 class="text-6xl md:text-8xl font-black mb-8 leading-[1.1] tracking-tight">
                    Smart <span
                        class="bg-clip-text text-transparent bg-gradient-to-b from-white to-indigo-300">Revision</span>
                    Solution
                </h1>
                <p class="text-indigo-100 text-xl font-medium mb-12 max-w-2xl mx-auto leading-relaxed opacity-80">
                    Designed specifically for JEE & NEET aspirants. Tackle complex topics with precision through our
                    specialized GMN Revision Tool.
                </p>

                <div class="flex flex-col sm:flex-row gap-6 justify-center">
                    <a href="#"
                        class="bg-white text-indigo-900 px-12 py-5 rounded-[2rem] font-black text-xl hover:bg-indigo-50 transition-all shadow-2xl flex items-center justify-center gap-4 group">
                        <span>Get Started</span>
                        <i
                            class="fas fa-rocket group-hover:-translate-y-1 group-hover:translate-x-1 transition-transform"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Bottom Curve -->
        <div class="absolute bottom-0 left-0 w-full overflow-hidden leading-none">
            <svg class="relative block w-full h-[150px]" data-name="Layer 1" xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 1200 120" preserveAspectRatio="none">
                <path
                    d="M321.39,56.44c58-10.79,114.16-30.13,172-41.86,82.39-16.72,168.19-17.73,250.45-.39C823.78,31,906.67,72,985.66,92.83c70.05,18.48,146.53,26.09,214.34,3V120H0V0C0,0,180.31,48,321.39,56.44Z"
                    class="fill-white"></path>
            </svg>
        </div>
    </section>

    <!-- Features -->
    <section class="py-24 bg-white -mt-32 relative z-20">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div class="feature-card p-10 rounded-[3rem]">
                    <div
                        class="w-16 h-16 bg-indigo-50 rounded-2xl flex items-center justify-center text-indigo-600 mb-8">
                        <i class="fas fa-layer-group text-2xl"></i>
                    </div>
                    <h3 class="text-2xl font-black mb-4">Topic-wise Practice</h3>
                    <p class="text-slate-500 font-medium leading-relaxed">Break down entire chapters into manageable
                        chunks. Master each topic before moving to the next.</p>
                </div>
                <div class="feature-card p-10 rounded-[3rem]">
                    <div class="w-16 h-16 bg-pink-50 rounded-2xl flex items-center justify-center text-pink-600 mb-8">
                        <i class="fas fa-history text-2xl"></i>
                    </div>
                    <h3 class="text-2xl font-black mb-4">Past Paper Insights</h3>
                    <p class="text-slate-500 font-medium leading-relaxed">Get analyzed insights from the last 15 years
                        of JEE & NEET papers. Know what's important.</p>
                </div>
                <div class="feature-card p-10 rounded-[3rem]">
                    <div
                        class="w-16 h-16 bg-emerald-50 rounded-2xl flex items-center justify-center text-emerald-600 mb-8">
                        <i class="fas fa-bolt text-2xl"></i>
                    </div>
                    <h3 class="text-2xl font-black mb-4">Instant Feedback</h3>
                    <p class="text-slate-500 font-medium leading-relaxed">Submit your answers and get immediate detailed
                        solutions with concept explanation video links.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats -->
    <section class="py-24 border-y border-slate-100 bg-slate-50/50">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
                <div>
                    <p class="text-4xl md:text-5xl font-black text-indigo-600 mb-2">50k+</p>
                    <p class="text-slate-500 font-bold uppercase tracking-widest text-xs">Questions</p>
                </div>
                <div>
                    <p class="text-4xl md:text-5xl font-black text-indigo-600 mb-2">12k+</p>
                    <p class="text-slate-500 font-bold uppercase tracking-widest text-xs">Active Students</p>
                </div>
                <div>
                    <p class="text-4xl md:text-5xl font-black text-indigo-600 mb-2">4.8</p>
                    <p class="text-slate-500 font-bold uppercase tracking-widest text-xs">Rating</p>
                </div>
                <div>
                    <p class="text-4xl md:text-5xl font-black text-indigo-600 mb-2">100%</p>
                    <p class="text-slate-500 font-bold uppercase tracking-widest text-xs">Accurate Data</p>
                </div>
            </div>
        </div>
    </section>

    <?php include dirname(dirname(__DIR__)) . '/include/public-footer.php'; ?>
</body>

</html>