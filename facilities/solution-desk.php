<?php require_once dirname(__DIR__) . '/common/constants.php';
require_once ENV_CONFIG_FILE; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solution Desk</title>
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

    <!-- Solution Desk Content Section -->
    <section class="relative py-12 md:py-24 overflow-hidden bg-gradient-to-b from-white via-indigo-50/30 to-white">
        <!-- Decorative background elements -->
        <div class="absolute top-0 right-0 w-96 h-96 bg-indigo-100 rounded-full translate-x-1/2 -translate-y-1/2 opacity-20 animate-float css-solution-desk-f165c4"></div>
        <div class="absolute bottom-0 left-0 w-64 h-64 bg-violet-100 rounded-full -translate-x-1/2 translate-y-1/2 opacity-20 animate-float css-solution-desk-bd845f"></div>
        <div class="absolute top-1/4 left-10 w-40 h-40 bg-sky-100 rounded-full opacity-15 animate-float css-solution-desk-cffe64"></div>

        <div class="container mx-auto px-4 relative z-10">
            <!-- Page Header -->
            <div class="text-center mb-12 md:mb-20">
                <div class="inline-block px-3 md:px-4 py-1 md:py-1.5 mb-3 md:mb-4 bg-indigo-50 text-indigo-600 rounded-full text-xs md:text-sm font-bold tracking-wide uppercase">
                    Academic Support
                </div>
                <h1 class="text-3xl md:text-4xl lg:text-6xl font-black mb-3 md:mb-4">
                    <span class="bg-clip-text text-transparent bg-gradient-to-r from-indigo-600 to-violet-600">Difficulty Solution Desk</span>
                </h1>
                <p class="text-gray-500 text-sm md:text-base lg:text-lg max-w-2xl mx-auto px-4">Empowering students to overcome academic hurdles through personalized doubt resolution.</p>
            </div>

            <!-- Main Content Card -->
            <div class="max-w-6xl mx-auto mb-12 md:mb-24">
                <div class="bg-white rounded-[2rem] md:rounded-[2.5rem] shadow-2xl overflow-hidden">
                    <div class="flex flex-col lg:flex-row">
                        <!-- Image Side -->
                        <div class="lg:w-1/2 p-4 md:p-6 lg:p-8 bg-gradient-to-br from-indigo-50 to-violet-50/50 flex items-center justify-center">
                            <div class="relative group">
                                <div class="absolute -inset-1 bg-gradient-to-r from-indigo-600 to-violet-600 rounded-2xl opacity-25 group-hover:opacity-40 blur transition duration-300"></div>
                                <img src="../assets/images/slu.jpg" alt="Solution Desk" class="relative rounded-2xl w-full h-auto object-cover border-4 border-white shadow-xl">
                            </div>
                        </div>

                        <!-- Content Side -->
                        <div class="lg:w-1/2 p-6 md:p-8 lg:p-12 flex flex-col justify-center">
                            <h2 class="text-2xl md:text-3xl font-bold text-gray-900 mb-6">No Doubt Left Behind</h2>
                            <div class="space-y-4 md:space-y-6 text-gray-700 leading-relaxed">
                                <p class="text-sm md:text-base lg:text-lg">
                                    Sometimes students hesitant to ask questions during a live lecture. To bridge this gap, Gyanmanjari has established the <span class="text-indigo-600 font-bold">Difficulty Solution Desk</span>.
                                </p>
                                <p class="text-sm md:text-base lg:text-lg">
                                    This dedicated system ensures that every query, no matter how small, is addressed by subject experts in a comfortable, one-on-one setting.
                                </p>
                                <div class="bg-indigo-50 p-6 rounded-2xl border border-indigo-100 flex items-start space-x-4">
                                    <div class="p-3 bg-white rounded-xl shadow-sm">
                                        <i class="fas fa-lightbulb text-indigo-600 text-xl"></i>
                                    </div>
                                    <p class="text-sm text-indigo-900">
                                        Our Doubt Solution Cell is active throughout the day, providing satisfied solutions to help students grow and excel.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Process Grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-6xl mx-auto">
                <div class="bg-white p-8 rounded-[2rem] shadow-xl border border-indigo-50 hover:-translate-y-2 transition-transform duration-300">
                    <div class="w-14 h-14 bg-indigo-100 rounded-2xl flex items-center justify-center mb-6">
                        <i class="fas fa-question-circle text-indigo-600 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Identify Doubts</h3>
                    <p class="text-gray-500">Note down difficulties during or after lectures across any subject.</p>
                </div>
                <div class="bg-white p-8 rounded-[2rem] shadow-xl border border-violet-50 hover:-translate-y-2 transition-transform duration-300">
                    <div class="w-14 h-14 bg-violet-100 rounded-2xl flex items-center justify-center mb-6">
                        <i class="fas fa-comments text-violet-600 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Expert Interaction</h3>
                    <p class="text-gray-500">Visit the Solution Desk for personalized guidance from faculty experts.</p>
                </div>
                <div class="bg-white p-8 rounded-[2rem] shadow-xl border border-sky-50 hover:-translate-y-2 transition-transform duration-300">
                    <div class="w-14 h-14 bg-sky-100 rounded-2xl flex items-center justify-center mb-6">
                        <i class="fas fa-check-double text-sky-600 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Clear Understanding</h3>
                    <p class="text-gray-500">Gain a full grasp of concepts with satisfied, detailed explanations.</p>
                </div>
            </div>
        </div>
    </section>

    </section>

    <!-- Footer -->
    <?php include __DIR__ . '/../include/public-footer.php'; ?>

</body>

</html>
