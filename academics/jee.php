<?php require_once __DIR__ . '/../common/constants.php';
require_once ENV_CONFIG_FILE; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JEE Preparation</title>
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

    <!-- JEE Preparation Content Section -->
    <section class="relative py-12 md:py-24 overflow-hidden bg-gradient-to-b from-white via-blue-50/30 to-white">
        <!-- Decorative background elements -->
        <div class="absolute top-0 right-0 w-96 h-96 bg-blue-100 rounded-full translate-x-1/2 -translate-y-1/2 opacity-20 animate-float css-jee-f165c4"></div>
        <div class="absolute bottom-0 left-0 w-64 h-64 bg-indigo-100 rounded-full -translate-x-1/2 translate-y-1/2 opacity-20 animate-float css-jee-bd845f"></div>
        <div class="absolute top-1/4 left-10 w-40 h-40 bg-purple-100 rounded-full opacity-15 animate-float css-jee-cffe64"></div>
        <div class="absolute top-1/3 right-20 w-32 h-32 bg-blue-200 rounded-full opacity-10 animate-float css-jee-c84216"></div>
        <div class="absolute bottom-1/4 right-1/4 w-48 h-48 bg-indigo-100 rounded-full opacity-15 animate-float css-jee-6da30b"></div>

        <div class="container mx-auto px-4 relative z-10">
            <!-- Page Header -->
            <div class="text-center mb-12 md:mb-20">
                <div
                    class="inline-block px-3 md:px-4 py-1 md:py-1.5 mb-3 md:mb-4 bg-blue-50 text-blue-600 rounded-full text-xs md:text-sm font-bold tracking-wide uppercase">
                    Competitive Excellence
                </div>
                <h1 class="text-3xl md:text-4xl lg:text-6xl font-black mb-3 md:mb-4">
                    <span class="bg-clip-text text-transparent bg-gradient-to-r from-blue-600 to-indigo-600">JEE
                        Preparation Program</span>
                </h1>
                <p class="text-gray-500 text-sm md:text-base lg:text-lg max-w-2xl mx-auto px-4">Empowering students to
                    conquer national-level engineering entrance exams with confidence.</p>
            </div>

            <!-- Main Content Card -->
            <div class="max-w-6xl mx-auto mb-12 md:mb-24">
                <div class="bg-white rounded-[2rem] md:rounded-[2.5rem] shadow-2xl overflow-hidden">
                    <div class="flex flex-col lg:flex-row">
                        <!-- Image Side -->
                        <div
                            class="lg:w-1/2 p-4 md:p-6 lg:p-8 bg-gradient-to-br from-blue-50 to-indigo-50/50 flex items-center justify-center">
                            <div class="relative group">
                                <div
                                    class="absolute -inset-1 bg-gradient-to-r from-blue-600 to-indigo-600 rounded-2xl opacity-25 group-hover:opacity-40 blur transition duration-300">
                                </div>
                                <img src="../assets/images/jee result.jpeg" alt="JEE Success"
                                    class="relative rounded-2xl w-full h-auto object-cover border-4 border-white shadow-xl">
                            </div>
                        </div>

                        <!-- Content Side -->
                        <div class="lg:w-1/2 p-6 md:p-8 lg:p-12 flex flex-col justify-center">
                            <h2 class="text-2xl md:text-3xl font-bold text-gray-900 mb-6">Mastering Joint Entrance
                                Examination</h2>
                            <div class="space-y-4 md:space-y-6">
                                <p class="text-gray-700 text-sm md:text-base lg:text-lg leading-relaxed">
                                    Gyanmanjari Vidyapith takes the initiative for the students' future careers. We
                                    offer dedicated classes for the <span
                                        class="text-blue-600 font-semibold text-lg">JEE exam</span> (for Group A) and
                                    NEET (for Group B), ensuring every student receives specialized attention.
                                </p>
                                <p class="text-gray-700 text-sm md:text-base lg:text-lg leading-relaxed">
                                    Our platform features highly expert faculty with <span
                                        class="text-indigo-600 font-semibold">15-20 years of experience</span> in
                                    national-level examinations, providing a full-fledged ecosystem for science
                                    aspirants.
                                </p>

                                <div class="grid grid-cols-2 gap-4 mt-8">
                                    <div class="bg-blue-50 p-4 rounded-2xl border border-blue-100">
                                        <div class="text-blue-600 font-black text-2xl mb-1">D</div>
                                        <div class="text-xs font-bold uppercase tracking-wider text-gray-500">Dedication
                                        </div>
                                    </div>
                                    <div class="bg-indigo-50 p-4 rounded-2xl border border-indigo-100">
                                        <div class="text-indigo-600 font-black text-2xl mb-1">R</div>
                                        <div class="text-xs font-bold uppercase tracking-wider text-gray-500">Respect
                                        </div>
                                    </div>
                                    <div class="bg-purple-50 p-4 rounded-2xl border border-purple-100">
                                        <div class="text-purple-600 font-black text-2xl mb-1">E</div>
                                        <div class="text-xs font-bold uppercase tracking-wider text-gray-500">Education
                                        </div>
                                    </div>
                                    <div class="bg-blue-50 p-4 rounded-2xl border border-blue-100">
                                        <div class="text-blue-600 font-black text-2xl mb-1">A</div>
                                        <div class="text-xs font-bold uppercase tracking-wider text-gray-500">Attitude
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Achievement Section -->
            <div class="max-w-4xl mx-auto mb-12 md:mb-24 text-center">
                <div
                    class="bg-gradient-to-br from-gray-900 to-indigo-950 rounded-[2rem] md:rounded-[2.5rem] p-8 md:p-12 shadow-2xl relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-64 h-64 bg-blue-500/10 rounded-full blur-3xl"></div>
                    <div class="absolute bottom-0 left-0 w-64 h-64 bg-indigo-500/10 rounded-full blur-3xl"></div>

                    <div class="relative z-10">
                        <div class="inline-block p-3 bg-blue-500/20 rounded-2xl mb-6">
                            <i class="fas fa-trophy text-blue-400 text-3xl"></i>
                        </div>
                        <h2 class="text-2xl md:text-4xl font-bold text-white mb-6">Legacy of Victory</h2>
                        <p class="text-gray-300 text-base md:text-lg leading-relaxed mb-8">
                            We take pride in our consistent results across Gujarat. In 2012, our students achieved
                            remarkable victory in AIEEE, and our success story continues.
                        </p>
                        <div class="flex flex-wrap justify-center gap-6">
                            <div
                                class="bg-white/10 backdrop-blur-md rounded-2xl p-6 border border-white/10 flex-1 min-w-[200px]">
                                <div class="text-3xl font-black text-white mb-2">58+</div>
                                <div class="text-blue-300 text-sm font-bold uppercase tracking-widest">Students Selected
                                </div>
                            </div>
                            <div
                                class="bg-white/10 backdrop-blur-md rounded-2xl p-6 border border-white/10 flex-1 min-w-[200px]">
                                <div class="text-3xl font-black text-white mb-2">Top Rank</div>
                                <div class="text-blue-300 text-sm font-bold uppercase tracking-widest">Doshi Chintan
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Call to Action -->
            <div class="text-center">
                <h2 class="text-2xl md:text-3xl font-bold text-gray-900 mb-6">Begin Your Engineering Journey Today</h2>
                <a href="../contact.php"
                    class="inline-flex items-center justify-center px-8 py-4 bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-bold rounded-full shadow-lg hover:shadow-2xl hover:scale-105 transition-all duration-300 group">
                    <span>Enrol in JEE Program</span>
                    <i class="fas fa-arrow-right ml-3 group-hover:translate-x-2 transition-transform"></i>
                </a>
            </div>
        </div>
    </section>


    <!-- Footer -->
    <?php include __DIR__ . '/../include/public-footer.php'; ?>

</body>

</html>