<?php require_once dirname(__DIR__) . '/common/constants.php';
require_once ENV_CONFIG_FILE; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Safety & Security</title>
    <link rel="icon" type="image/x-icon" href="../assets/images/logogmn.png">
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Link to external CSS file -->
    <link rel="stylesheet" href="../assets/css/gm-public.css">
</head>

<body class="text-gray-800">
    <!-- Header/Navbar -->
    <?php include __DIR__ . '/../include/public-header.php'; ?>

    <!-- Safety & Security Content Section -->
    <section class="relative py-12 md:py-24 overflow-hidden bg-gradient-to-b from-white via-slate-50/30 to-white">
        <!-- Decorative background elements -->
        <div class="absolute top-0 right-0 w-96 h-96 bg-slate-100 rounded-full translate-x-1/2 -translate-y-1/2 opacity-20 animate-float" style="animation-delay: 0s;"></div>
        <div class="absolute bottom-0 left-0 w-64 h-64 bg-blue-100 rounded-full -translate-x-1/2 translate-y-1/2 opacity-20 animate-float" style="animation-delay: 1s;"></div>
        <div class="absolute top-1/4 left-10 w-40 h-40 bg-red-100 rounded-full opacity-10 animate-float" style="animation-delay: 2s;"></div>

        <div class="container mx-auto px-4 relative z-10">
            <!-- Page Header -->
            <div class="text-center mb-12 md:mb-20">
                <div class="inline-block px-3 md:px-4 py-1 md:py-1.5 mb-3 md:mb-4 bg-slate-50 text-slate-600 rounded-full text-xs md:text-sm font-bold tracking-wide uppercase">
                    Campus Protection
                </div>
                <h1 class="text-3xl md:text-4xl lg:text-6xl font-black mb-3 md:mb-4">
                    <span class="bg-clip-text text-transparent bg-gradient-to-r from-slate-700 to-blue-700">Uncompromised Safety & Security</span>
                </h1>
                <p class="text-gray-500 text-sm md:text-base lg:text-lg max-w-2xl mx-auto px-4">Providing a secure sanctuary where students can focus entirely on their growth and learning.</p>
            </div>

            <!-- Main Content Card -->
            <div class="max-w-6xl mx-auto mb-12 md:mb-24">
                <div class="bg-white rounded-[2rem] md:rounded-[2.5rem] shadow-2xl overflow-hidden">
                    <div class="flex flex-col lg:flex-row">
                        <!-- Image Side -->
                        <div class="lg:w-1/2 p-4 md:p-6 lg:p-8 bg-gradient-to-br from-slate-100 to-blue-50/50 flex items-center justify-center">
                            <div class="relative group">
                                <div class="absolute -inset-1 bg-gradient-to-r from-slate-600 to-blue-600 rounded-2xl opacity-25 group-hover:opacity-40 blur transition duration-300"></div>
                                <img src="../assets/images/shopping.webp" alt="Safety and Security" class="relative rounded-2xl w-full h-auto object-cover border-4 border-white shadow-xl">
                            </div>
                        </div>

                        <!-- Content Side -->
                        <div class="lg:w-1/2 p-6 md:p-8 lg:p-12 flex flex-col justify-center">
                            <h2 class="text-2xl md:text-3xl font-bold text-gray-900 mb-6">A Secure Learning Environment</h2>
                            <div class="space-y-4 md:space-y-6 text-gray-700 leading-relaxed">
                                <p class="text-sm md:text-base lg:text-lg">
                                    The safety of our students is our highest priority. We have implemented a multi-layered security system that ensures a protected environment 24/7.
                                </p>
                                <p class="text-sm md:text-base lg:text-lg">
                                    From high-definition surveillance to trained security personnel, every aspect of our campus is designed to be a safe haven for academic discovery.
                                </p>
                                <div class="bg-slate-50 p-6 rounded-2xl border border-slate-100 flex items-start space-x-4">
                                    <div class="p-3 bg-white rounded-xl shadow-sm">
                                        <i class="fas fa-shield-alt text-blue-600 text-xl"></i>
                                    </div>
                                    <p class="text-sm text-slate-900">
                                        Comprehensive emergency protocols and regular safety drills are part of our core campus management.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Security Grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-6xl mx-auto">
                <div class="bg-white p-8 rounded-[2rem] shadow-xl border border-slate-50 hover:bg-slate-800 group transition-all duration-500">
                    <div class="w-14 h-14 bg-slate-100 rounded-2xl flex items-center justify-center mb-6 group-hover:bg-white/20">
                        <i class="fas fa-video text-slate-600 text-2xl group-hover:text-white"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3 group-hover:text-white">CCTV Surveillance</h3>
                    <p class="text-gray-500 group-hover:text-slate-300">Continuous monitoring of all common areas and entrances for maximum safety.</p>
                </div>
                <div class="bg-white p-8 rounded-[2rem] shadow-xl border border-blue-50 hover:bg-blue-600 group transition-all duration-500">
                    <div class="w-14 h-14 bg-blue-100 rounded-2xl flex items-center justify-center mb-6 group-hover:bg-white/20">
                        <i class="fas fa-user-shield text-blue-600 text-2xl group-hover:text-white"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3 group-hover:text-white">Trained Personnel</h3>
                    <p class="text-gray-500 group-hover:text-blue-50">Professional security staff ensuring controlled access and campus safety.</p>
                </div>
                <div class="bg-white p-8 rounded-[2rem] shadow-xl border border-red-50 hover:bg-red-600 group transition-all duration-500">
                    <div class="w-14 h-14 bg-red-100 rounded-2xl flex items-center justify-center mb-6 group-hover:bg-white/20">
                        <i class="fas fa-fire-extinguisher text-red-600 text-2xl group-hover:text-white"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3 group-hover:text-white">Fire Safety</h3>
                    <p class="text-gray-500 group-hover:text-red-50">Advanced fire detection and suppression systems installed campus-wide.</p>
                </div>
            </div>
        </div>
    </section>

    </section>

    <!-- Footer -->
    <?php include __DIR__ . '/../include/public-footer.php'; ?>

</body>

</html>
