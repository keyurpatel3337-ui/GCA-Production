<?php require_once dirname(__DIR__) . '/common/constants.php';
require_once ENV_CONFIG_FILE; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Counselling</title>
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

    <!-- Counselling Content Section -->
    <section class="relative py-12 md:py-24 overflow-hidden bg-gradient-to-b from-white via-rose-50/30 to-white">
        <!-- Decorative background elements -->
        <div class="absolute top-0 right-0 w-96 h-96 bg-rose-100 rounded-full translate-x-1/2 -translate-y-1/2 opacity-20 animate-float" style="animation-delay: 0s;"></div>
        <div class="absolute bottom-0 left-0 w-64 h-64 bg-indigo-100 rounded-full -translate-x-1/2 translate-y-1/2 opacity-20 animate-float" style="animation-delay: 1s;"></div>
        <div class="absolute top-1/4 left-10 w-40 h-40 bg-pink-100 rounded-full opacity-15 animate-float" style="animation-delay: 2s;"></div>

        <div class="container mx-auto px-4 relative z-10">
            <!-- Page Header -->
            <div class="text-center mb-12 md:mb-20">
                <div class="inline-block px-3 md:px-4 py-1 md:py-1.5 mb-3 md:mb-4 bg-rose-50 text-rose-600 rounded-full text-xs md:text-sm font-bold tracking-wide uppercase">
                    Student Well-being
                </div>
                <h1 class="text-3xl md:text-4xl lg:text-6xl font-black mb-3 md:mb-4">
                    <span class="bg-clip-text text-transparent bg-gradient-to-r from-rose-600 to-indigo-600">Personalized Counselling</span>
                </h1>
                <p class="text-gray-500 text-sm md:text-base lg:text-lg max-w-2xl mx-auto px-4">Nurturing minds, building confidence, and empowering students to overcome every challenge.</p>
            </div>

            <!-- Main Content Card -->
            <div class="max-w-6xl mx-auto mb-12 md:mb-24">
                <div class="bg-white rounded-[2rem] md:rounded-[2.5rem] shadow-2xl overflow-hidden">
                    <div class="flex flex-col lg:flex-row">
                        <!-- Image Side -->
                        <div class="lg:w-1/2 p-4 md:p-6 lg:p-8 bg-gradient-to-br from-rose-50 to-indigo-50/50 flex items-center justify-center">
                            <div class="relative group">
                                <div class="absolute -inset-1 bg-gradient-to-r from-rose-600 to-indigo-600 rounded-2xl opacity-25 group-hover:opacity-40 blur transition duration-300"></div>
                                <img src="../assets/images/coun.jpg" alt="Counselling" class="relative rounded-2xl w-full h-auto object-cover border-4 border-white shadow-xl">
                            </div>
                        </div>

                        <!-- Content Side -->
                        <div class="lg:w-1/2 p-6 md:p-8 lg:p-12 flex flex-col justify-center">
                            <h2 class="text-2xl md:text-3xl font-bold text-gray-900 mb-6">Empathy & Support</h2>
                            <div class="space-y-4 md:space-y-6">
                                <p class="text-gray-700 text-sm md:text-base lg:text-lg leading-relaxed">
                                    Inspired by Maslow's Hierarchy of Needs, we believe that emotional security and self-actualization are vital for academic success.
                                </p>
                                <p class="text-gray-700 text-sm md:text-base lg:text-lg leading-relaxed">
                                    Whether it's subject-related fear, personal challenges, or a lack of confidence, our dedicated counsellors provide a safe space for students to express themselves and find solutions.
                                </p>
                                <div class="bg-rose-50 p-6 rounded-2xl border border-rose-100 flex items-start space-x-4">
                                    <div class="p-3 bg-white rounded-xl shadow-sm">
                                        <i class="fas fa-heart text-rose-600 text-xl"></i>
                                    </div>
                                    <p class="text-sm text-rose-900">
                                        We focus on building basic instinct power and resilience, preparing students for the competitive world.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Philosophy Grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-6xl mx-auto">
                <div class="bg-white p-8 rounded-[2rem] shadow-xl border border-rose-50 hover:bg-rose-600 group transition-all duration-500">
                    <div class="w-14 h-14 bg-rose-100 rounded-2xl flex items-center justify-center mb-6 group-hover:bg-white/20">
                        <i class="fas fa-brain text-rose-600 text-2xl group-hover:text-white"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3 group-hover:text-white">Mental Clarity</h3>
                    <p class="text-gray-500 group-hover:text-rose-50">Overcoming subject fears and negative thoughts through structured guidance.</p>
                </div>
                <div class="bg-white p-8 rounded-[2rem] shadow-xl border border-indigo-50 hover:bg-indigo-600 group transition-all duration-500">
                    <div class="w-14 h-14 bg-indigo-100 rounded-2xl flex items-center justify-center mb-6 group-hover:bg-white/20">
                        <i class="fas fa-smile text-indigo-600 text-2xl group-hover:text-white"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3 group-hover:text-white">Self Confidence</h3>
                    <p class="text-gray-500 group-hover:text-indigo-50">Boosting morale and helping students recognize their true potential.</p>
                </div>
                <div class="bg-white p-8 rounded-[2rem] shadow-xl border border-pink-50 hover:bg-pink-600 group transition-all duration-500">
                    <div class="w-14 h-14 bg-pink-100 rounded-2xl flex items-center justify-center mb-6 group-hover:bg-white/20">
                        <i class="fas fa-lightbulb text-pink-600 text-2xl group-hover:text-white"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3 group-hover:text-white">Motivation</h3>
                    <p class="text-gray-500 group-hover:text-pink-50">Empowering student minds for exploring opportunities in a competitive world.</p>
                </div>
            </div>
        </div>
    </section>


        
    <!-- Footer -->
    <?php include __DIR__ . '/../include/public-footer.php'; ?>

</body>

</html>
