<?php require_once __DIR__ . '/../common/constants.php';
require_once ENV_CONFIG_FILE; ?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teaching Programs</title>
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

    <!-- Teaching Programs Content Section -->
    <section class="relative py-12 md:py-24 overflow-hidden bg-gradient-to-b from-white via-blue-50/30 to-white">
        <!-- Decorative background elements -->
        <div class="absolute top-0 right-0 w-96 h-96 bg-blue-100 rounded-full translate-x-1/2 -translate-y-1/2 opacity-20 animate-float"
            style="animation-delay: 0s;"></div>
        <div class="absolute bottom-0 left-0 w-64 h-64 bg-indigo-100 rounded-full -translate-x-1/2 translate-y-1/2 opacity-20 animate-float"
            style="animation-delay: 1s;"></div>
        <div class="absolute top-1/4 left-10 w-40 h-40 bg-purple-100 rounded-full opacity-15 animate-float"
            style="animation-delay: 2s;"></div>

        <div class="container mx-auto px-4 relative z-10">
            <!-- Page Header -->
            <div class="text-center mb-12 md:mb-20">
                <div
                    class="inline-block px-3 md:px-4 py-1 md:py-1.5 mb-3 md:mb-4 bg-blue-50 text-blue-600 rounded-full text-xs md:text-sm font-bold tracking-wide uppercase">
                    Academic Journey
                </div>
                <h1 class="text-3xl md:text-4xl lg:text-6xl font-black mb-3 md:mb-4">
                    <span class="bg-clip-text text-transparent bg-gradient-to-r from-blue-600 to-indigo-600">Our
                        Teaching Programs</span>
                </h1>
                <p class="text-gray-500 text-sm md:text-base lg:text-lg max-w-3xl mx-auto px-4">
                    Architecting success through diverse, specialized curriculum paths designed for every stage of a
                    student's development.
                </p>
            </div>

            <div class="max-w-7xl mx-auto space-y-12 md:space-y-24">
                <!-- Program Category: Early Childhood Education -->
                <div
                    class="bg-white rounded-[2rem] md:rounded-[2.5rem] shadow-2xl overflow-hidden border border-blue-50">
                    <div class="flex flex-col lg:flex-row">
                        <div
                            class="lg:w-2/5 p-4 md:p-6 bg-gradient-to-br from-blue-50 to-white flex items-center justify-center">
                            <img src="../assets/images/unnamed.jpg" alt="Primary School"
                                class="rounded-2xl w-full h-full object-cover shadow-lg aspect-video lg:aspect-square">
                        </div>
                        <div class="lg:w-3/5 p-8 md:p-12">
                            <div class="flex items-center space-x-3 mb-6">
                                <span class="p-2 bg-blue-600 text-white rounded-xl"><i class="fas fa-child"></i></span>
                                <h2 class="text-2xl md:text-4xl font-extrabold text-gray-900">NaICE Primary School</h2>
                            </div>
                            <p class="text-gray-500 font-bold mb-4">KG to Std 7 (Eng. & Guj. Medium)</p>
                            <p class="text-gray-700 text-sm md:text-base mb-6 leading-relaxed">
                                Our Early Childhood program is dedicated to nurturing young minds through engaging,
                                age-appropriate activities that build strong foundations in literacy, numeracy, and
                                social skills.
                            </p>
                            <ul class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm md:text-base text-gray-600">
                                <li class="flex items-center"><i class="fas fa-check-circle text-blue-500 mr-2"></i>
                                    Foundational Literacy & Numeracy</li>
                                <li class="flex items-center"><i class="fas fa-check-circle text-blue-500 mr-2"></i>
                                    Communication Skills</li>
                                <li class="flex items-center"><i class="fas fa-check-circle text-blue-500 mr-2"></i>
                                    Hands-on Exploration</li>
                                <li class="flex items-center"><i class="fas fa-check-circle text-blue-500 mr-2"></i>
                                    Nurturing Environment</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Program Category: Secondary Education -->
                <div
                    class="bg-white rounded-[2rem] md:rounded-[2.5rem] shadow-2xl overflow-hidden border border-indigo-50">
                    <div class="flex flex-col lg:flex-row-reverse">
                        <div
                            class="lg:w-2/5 p-4 md:p-6 bg-gradient-to-br from-indigo-50 to-white flex items-center justify-center">
                            <img src="../assets/images/sgmbuilding.jpg" alt="Secondary School"
                                class="rounded-2xl w-full h-full object-cover shadow-lg aspect-video lg:aspect-square">
                        </div>
                        <div class="lg:w-3/5 p-8 md:p-12">
                            <div class="flex items-center space-x-3 mb-6">
                                <span class="p-2 bg-indigo-600 text-white rounded-xl"><i class="fas fa-book"></i></span>
                                <h2 class="text-2xl md:text-4xl font-extrabold text-gray-900">Secondary Education</h2>
                            </div>
                            <p class="text-gray-500 font-bold mb-4">Std 8 to 10 (Guj. Medium)</p>
                            <p class="text-gray-700 text-sm md:text-base mb-6 leading-relaxed">
                                Building upon foundational knowledge with a structured curriculum that encourages deeper
                                understanding and critical thinking through project-based learning.
                            </p>
                            <ul class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm md:text-base text-gray-600">
                                <li class="flex items-center"><i class="fas fa-check-circle text-indigo-500 mr-2"></i>
                                    Conceptual Understanding</li>
                                <li class="flex items-center"><i class="fas fa-check-circle text-indigo-500 mr-2"></i>
                                    Analytical & Reasoning Skills</li>
                                <li class="flex items-center"><i class="fas fa-check-circle text-indigo-500 mr-2"></i>
                                    Guided Research Projects</li>
                                <li class="flex items-center"><i class="fas fa-check-circle text-indigo-500 mr-2"></i>
                                    Group Discussions</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Program Category: Higher Secondary -->
                <div
                    class="bg-white rounded-[2rem] md:rounded-[2.5rem] shadow-2xl overflow-hidden border border-purple-50">
                    <div class="flex flex-col lg:flex-row">
                        <div
                            class="lg:w-2/5 p-4 md:p-6 bg-gradient-to-br from-purple-50 to-white flex items-center justify-center">
                            <img src="../assets/images/gm-building.jpg" alt="Higher Secondary"
                                class="rounded-2xl w-full h-full object-cover shadow-lg aspect-video lg:aspect-square">
                        </div>
                        <div class="lg:w-3/5 p-8 md:p-12">
                            <div class="flex items-center space-x-3 mb-6">
                                <span class="p-2 bg-purple-600 text-white rounded-xl"><i
                                        class="fas fa-flask"></i></span>
                                <h2 class="text-2xl md:text-4xl font-extrabold text-gray-900">Higher Secondary Science
                                </h2>
                            </div>
                            <p class="text-gray-500 font-bold mb-4">Std 11 & 12 (Eng. & Guj. Medium)</p>
                            <p class="text-gray-700 text-sm md:text-base mb-6 leading-relaxed">
                                A comprehensive academic experience preparing students for advanced scientific studies
                                and competitive careers with state-of-the-art laboratory facilities.
                            </p>
                            <ul class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm md:text-base text-gray-600">
                                <li class="flex items-center"><i class="fas fa-check-circle text-purple-500 mr-2"></i>
                                    Physics, Chemistry, Biology, Math</li>
                                <li class="flex items-center"><i class="fas fa-check-circle text-purple-500 mr-2"></i>
                                    Advanced Lab Sessions</li>
                                <li class="flex items-center"><i class="fas fa-check-circle text-purple-500 mr-2"></i>
                                    Board Exam Mastery</li>
                                <li class="flex items-center"><i class="fas fa-check-circle text-purple-500 mr-2"></i>
                                    Career Planning Guidance</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Vision Footer -->
            <div class="max-w-4xl mx-auto mt-24 text-center">
                <div
                    class="bg-gradient-to-r from-blue-600 to-indigo-600 rounded-[2.5rem] p-8 md:p-12 shadow-2xl relative overflow-hidden">
                    <h2 class="text-2xl md:text-3xl font-bold text-white mb-6">Unlocking Every Student's Potential</h2>
                    <p class="text-white/90 text-lg leading-relaxed">
                        Our integrated approach ensures every student receives personalized attention and the resources
                        needed to achieve their full potential.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->

    <?php include __DIR__ . '/../include/public-footer.php'; ?>

</body>

</html>