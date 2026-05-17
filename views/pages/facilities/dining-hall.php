<?php require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once ENV_CONFIG_FILE; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dining Hall</title>
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
    <?php include __DIR__ . '/../../../include/public-header.php'; ?>

    <!-- Dining Hall Content Section -->
    <section class="relative py-12 md:py-24 overflow-hidden bg-gradient-to-b from-white via-orange-50/30 to-white">
        <!-- Decorative background elements -->
        <div class="absolute top-0 right-0 w-96 h-96 bg-orange-100 rounded-full translate-x-1/2 -translate-y-1/2 opacity-20 animate-float" style="animation-delay: 0s;"></div>
        <div class="absolute bottom-0 left-0 w-64 h-64 bg-amber-100 rounded-full -translate-x-1/2 translate-y-1/2 opacity-20 animate-float" style="animation-delay: 1s;"></div>
        <div class="absolute top-1/4 left-10 w-40 h-40 bg-green-100 rounded-full opacity-15 animate-float" style="animation-delay: 2s;"></div>

        <div class="container mx-auto px-4 relative z-10">
            <!-- Page Header -->
            <div class="text-center mb-12 md:mb-20">
                <div class="inline-block px-3 md:px-4 py-1 md:py-1.5 mb-3 md:mb-4 bg-orange-50 text-orange-600 rounded-full text-xs md:text-sm font-bold tracking-wide uppercase">
                    Campus Life
                </div>
                <h1 class="text-3xl md:text-4xl lg:text-6xl font-black mb-3 md:mb-4">
                    <span class="bg-clip-text text-transparent bg-gradient-to-r from-orange-600 to-amber-600">The Grand Dining Hall</span>
                </h1>
                <p class="text-gray-500 text-sm md:text-base lg:text-lg max-w-2xl mx-auto px-4">Wholesome nutrition and shared experiences in a hygienic, family-like environment.</p>
            </div>

            <!-- Main Content Card -->
            <div class="max-w-6xl mx-auto mb-12 md:mb-24">
                <div class="bg-white rounded-[2rem] md:rounded-[2.5rem] shadow-2xl overflow-hidden">
                    <div class="flex flex-col lg:flex-row">
                        <!-- Image Side -->
                        <div class="lg:w-1/2 p-4 md:p-6 lg:p-8 bg-gradient-to-br from-orange-50 to-amber-50/50 flex items-center justify-center">
                            <div class="relative group">
                                <div class="absolute -inset-1 bg-gradient-to-r from-orange-600 to-amber-600 rounded-2xl opacity-25 group-hover:opacity-40 blur transition duration-300"></div>
                                <img src="../assets/images/lunch.jpg" alt="Dining Hall" class="relative rounded-2xl w-full h-auto object-cover border-4 border-white shadow-xl">
                            </div>
                        </div>

                        <!-- Content Side -->
                        <div class="lg:w-1/2 p-6 md:p-8 lg:p-12 flex flex-col justify-center">
                            <h2 class="text-2xl md:text-3xl font-bold text-gray-900 mb-6">Home-style Nutrition</h2>
                            <div class="space-y-4 md:space-y-6 text-gray-700 leading-relaxed">
                                <p class="text-sm md:text-base lg:text-lg">
                                    To support our students during their extended self-study hours, the Trust has established a massive dining facility adjacent to the school campus.
                                </p>
                                <p class="text-sm md:text-base lg:text-lg">
                                    We provide <span class="text-orange-600 font-bold">strictly hygienic food</span> with a carefully planned menu that balances nutrition and taste.
                                </p>
                                <div class="bg-orange-50 p-6 rounded-2xl border border-orange-100 mt-4">
                                    <p class="italic text-orange-900 text-sm md:text-base">
                                        "A unique tradition at Gyanmanjari: Our Trustees, teachers, and staff members share the exact same meal with our students, fostering a sense of equality and family."
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Key Features Grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-6xl mx-auto">
                <div class="bg-white p-8 rounded-[2rem] shadow-xl border border-orange-50 hover:bg-orange-600 group transition-all duration-500">
                    <div class="w-14 h-14 bg-orange-100 rounded-2xl flex items-center justify-center mb-6 group-hover:bg-white/20">
                        <i class="fas fa-utensils text-orange-600 text-2xl group-hover:text-white"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3 group-hover:text-white">Planned Menu</h3>
                    <p class="text-gray-500 group-hover:text-orange-50">Nutritionally balanced meals designed for growing students.</p>
                </div>
                <div class="bg-white p-8 rounded-[2rem] shadow-xl border border-amber-50 hover:bg-amber-600 group transition-all duration-500">
                    <div class="w-14 h-14 bg-amber-100 rounded-2xl flex items-center justify-center mb-6 group-hover:bg-white/20">
                        <i class="fas fa-hand-sparkles text-amber-600 text-2xl group-hover:text-white"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3 group-hover:text-white">Hygienic Standards</h3>
                    <p class="text-gray-500 group-hover:text-amber-50">State-of-the-art kitchen facilities ensuring the highest food safety.</p>
                </div>
                <div class="bg-white p-8 rounded-[2rem] shadow-xl border border-green-50 hover:bg-green-600 group transition-all duration-500">
                    <div class="w-14 h-14 bg-green-100 rounded-2xl flex items-center justify-center mb-6 group-hover:bg-white/20">
                        <i class="fas fa-users text-green-600 text-2xl group-hover:text-white"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3 group-hover:text-white">Shared Dining</h3>
                    <p class="text-gray-500 group-hover:text-green-50">A strong community bond where students and teachers eat together.</p>
                </div>
            </div>
        </div>
    </section>

    </section>

    <!-- Footer -->
    <?php include __DIR__ . '/../../../include/public-footer.php'; ?>

</body>

</html>