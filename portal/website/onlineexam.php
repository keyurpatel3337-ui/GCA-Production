<?php
require_once dirname(dirname(__DIR__)) . '/common/constants.php';
require_once ENV_CONFIG_FILE;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Examination Portal | Gyanmanjari Vidyapith</title>
    <link rel="icon" type="image/x-icon" href="<?php echo BASE_URL; ?>/assets/images/logogmn.png">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

    
</head>

<body class="bg-slate-50 text-slate-900">
    <?php include dirname(dirname(__DIR__)) . '/include/public-header.php'; ?>

    <section class="pt-32 pb-24 exam-gradient text-white">
        <div class="container mx-auto px-4 text-center">
            <h1 class="text-5xl md:text-7xl font-black mb-6">Online <span class="text-indigo-400">Exam</span> Portal
            </h1>
            <p class="text-indigo-200 text-lg md:text-xl max-w-2xl mx-auto mb-12">
                Simulate the real NEET & JEE experience with our robust, high-performance online testing platform.
            </p>

            <div class="max-w-5xl mx-auto relative px-4">
                <div class="monitor-frame bg-slate-800 overflow-hidden aspect-video">
                    <img src="https://images.unsplash.com/photo-1516321318423-f06f85e504b3?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80"
                        alt="Platform Preview" class="w-full h-full object-cover opacity-80">
                    <div class="absolute inset-0 flex items-center justify-center">
                        <a href="#"
                            class="w-20 h-20 bg-white text-indigo-900 rounded-full flex items-center justify-center text-3xl hover:scale-110 transition-transform shadow-2xl">
                            <i class="fas fa-play ml-1"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-24 bg-white">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                <div class="p-8 rounded-3xl bg-slate-50 border border-slate-100">
                    <i class="fas fa-stopwatch text-indigo-600 text-3xl mb-6"></i>
                    <h3 class="text-xl font-bold mb-4">Real-time Timing</h3>
                    <p class="text-slate-500 text-sm">Experience the same pressure as the actual examination with
                        section-wise timers.</p>
                </div>
                <div class="p-8 rounded-3xl bg-slate-50 border border-slate-100">
                    <i class="fas fa-lock text-indigo-600 text-3xl mb-6"></i>
                    <h3 class="text-xl font-bold mb-4">Anti-Cheating</h3>
                    <p class="text-slate-500 text-sm">Advanced proctoring features to ensure fair assessment for every
                        student.</p>
                </div>
                <div class="p-8 rounded-3xl bg-slate-50 border border-slate-100">
                    <i class="fas fa-brain text-indigo-600 text-3xl mb-6"></i>
                    <h3 class="text-xl font-bold mb-4">Detailed Analysis</h3>
                    <p class="text-slate-500 text-sm">Post-exam analytics highlighting your weak zones and time
                        management.</p>
                </div>
                <div class="p-8 rounded-3xl bg-slate-50 border border-slate-100">
                    <i class="fas fa-mobile-alt text-indigo-600 text-3xl mb-6"></i>
                    <h3 class="text-xl font-bold mb-4">Multi-device</h3>
                    <p class="text-slate-500 text-sm">Take tests on your Laptop, Tablet or even your Smartphone
                        seamlessly.</p>
                </div>
            </div>
        </div>
    </section>

    <?php include dirname(dirname(__DIR__)) . '/include/public-footer.php'; ?>
</body>

</html>