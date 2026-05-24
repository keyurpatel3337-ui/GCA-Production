<?php require_once dirname(__DIR__) . '/common/constants.php';
require_once ENV_CONFIG_FILE; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Computer Lab</title>
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

    <!-- Computer Lab Content Section -->
    <section class="relative py-12 md:py-24 overflow-hidden bg-gradient-to-b from-white via-cyan-50/30 to-white">
        <!-- Decorative background elements -->
        <div class="absolute top-0 right-0 w-96 h-96 bg-cyan-100 rounded-full translate-x-1/2 -translate-y-1/2 opacity-20 animate-float css-computer-lab-f165c4"></div>
        <div class="absolute bottom-0 left-0 w-64 h-64 bg-blue-100 rounded-full -translate-x-1/2 translate-y-1/2 opacity-20 animate-float css-computer-lab-bd845f"></div>
        <div class="absolute top-1/4 left-10 w-40 h-40 bg-indigo-100 rounded-full opacity-15 animate-float css-computer-lab-cffe64"></div>

        <div class="container mx-auto px-4 relative z-10">
            <!-- Page Header -->
            <div class="text-center mb-12 md:mb-20">
                <div class="inline-block px-3 md:px-4 py-1 md:py-1.5 mb-3 md:mb-4 bg-cyan-50 text-cyan-600 rounded-full text-xs md:text-sm font-bold tracking-wide uppercase">
                    Digital Excellence
                </div>
                <h1 class="text-3xl md:text-4xl lg:text-6xl font-black mb-3 md:mb-4">
                    <span class="bg-clip-text text-transparent bg-gradient-to-r from-cyan-600 to-blue-600">State-of-the-Art Computer Lab</span>
                </h1>
                <p class="text-gray-500 text-sm md:text-base lg:text-lg max-w-2xl mx-auto px-4">Empowering students with cutting-edge technology and seamless connectivity for a digital future.</p>
            </div>

            <!-- Main Content Card -->
            <div class="max-w-6xl mx-auto mb-12 md:mb-24">
                <div class="bg-white rounded-[2rem] md:rounded-[2.5rem] shadow-2xl overflow-hidden">
                    <div class="flex flex-col lg:flex-row">
                        <!-- Image Side -->
                        <div class="lg:w-1/2 p-4 md:p-6 lg:p-8 bg-gradient-to-br from-cyan-50 to-blue-50/50 flex items-center justify-center">
                            <div class="relative group">
                                <div class="absolute -inset-1 bg-gradient-to-r from-cyan-600 to-blue-600 rounded-2xl opacity-25 group-hover:opacity-40 blur transition duration-300"></div>
                                <img src="../assets/images/com.jpg" alt="Computer Lab" class="relative rounded-2xl w-full h-auto object-cover border-4 border-white shadow-xl">
                            </div>
                        </div>

                        <!-- Content Side -->
                        <div class="lg:w-1/2 p-6 md:p-8 lg:p-12 flex flex-col justify-center">
                            <h2 class="text-2xl md:text-3xl font-bold text-gray-900 mb-6">Digital Learning Hub</h2>
                            <div class="space-y-4 md:space-y-6">
                                <p class="text-gray-700 text-sm md:text-base lg:text-lg leading-relaxed">
                                    In today's fast-paced world, computer literacy is a fundamental necessity. Gyanmanjari Vidyapith provides an immersive environment where students grow alongside the latest technological advancements.
                                </p>
                                <p class="text-gray-700 text-sm md:text-base lg:text-lg leading-relaxed">
                                    Our infrastructure features <span class="text-cyan-600 font-bold text-lg">50+ high-performance computers</span>, interconnected via a robust Local Area Network (LAN) and high-speed enterprise WiFi, ensuring seamless access across the campus.
                                </p>
                                <div class="bg-blue-50 p-6 rounded-2xl border border-blue-100 flex items-start space-x-4">
                                    <div class="p-3 bg-white rounded-xl shadow-sm">
                                        <i class="fas fa-wifi text-blue-600 text-xl"></i>
                                    </div>
                                    <p class="text-sm text-blue-900">
                                        Full campus WiFi connectivity and an interactive web portal for real-time results and updates.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Key Features Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 max-w-6xl mx-auto">
                <div class="bg-white p-6 rounded-[2rem] shadow-lg border border-cyan-50 hover:-translate-y-2 transition-transform duration-300">
                    <div class="w-12 h-12 bg-cyan-100 rounded-xl flex items-center justify-center mb-4">
                        <i class="fas fa-microchip text-cyan-600"></i>
                    </div>
                    <h3 class="font-bold text-gray-900 mb-2">Advanced Hardware</h3>
                    <p class="text-gray-500 text-sm">Latest generation systems and peripherals for optimal performance.</p>
                </div>
                <div class="bg-white p-6 rounded-[2rem] shadow-lg border border-blue-50 hover:-translate-y-2 transition-transform duration-300">
                    <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center mb-4">
                        <i class="fas fa-globe text-blue-600"></i>
                    </div>
                    <h3 class="font-bold text-gray-900 mb-2">Global Connectivity</h3>
                    <p class="text-gray-500 text-sm">High-speed internet access for research and global learning.</p>
                </div>
                <div class="bg-white p-6 rounded-[2rem] shadow-lg border border-indigo-50 hover:-translate-y-2 transition-transform duration-300">
                    <div class="w-12 h-12 bg-indigo-100 rounded-xl flex items-center justify-center mb-4">
                        <i class="fas fa-project-diagram text-indigo-600"></i>
                    </div>
                    <h3 class="font-bold text-gray-900 mb-2">Robust LAN</h3>
                    <p class="text-gray-500 text-sm">Seamless data access and sharing through our campus network.</p>
                </div>
                <div class="bg-white p-6 rounded-[2rem] shadow-lg border border-purple-50 hover:-translate-y-2 transition-transform duration-300">
                    <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center mb-4">
                        <i class="fas fa-users-cog text-purple-600"></i>
                    </div>
                    <h3 class="font-bold text-gray-900 mb-2">Interactive Tech</h3>
                    <p class="text-gray-500 text-sm">Multimedia kits and LCD projectors for an engaging experience.</p>
                </div>
            </div>
        </div>
    </section>


    <!-- Footer -->
    <?php include __DIR__ . '/../include/public-footer.php'; ?>

</body>

</html>
