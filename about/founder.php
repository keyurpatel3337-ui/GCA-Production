<?php require_once dirname(__DIR__) . '/common/constants.php';
require_once ENV_CONFIG_FILE; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Founders</title>
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

    <!-- Founders Content Section -->
    <section class="relative py-12 md:py-24 overflow-hidden bg-gradient-to-b from-white via-blue-50/30 to-white">
        <!-- Decorative background elements -->
        <div class="absolute top-0 right-0 w-96 h-96 bg-blue-100 rounded-full translate-x-1/2 -translate-y-1/2 opacity-20 animate-float" style="animation-delay: 0s;"></div>
        <div class="absolute bottom-0 left-0 w-64 h-64 bg-indigo-100 rounded-full -translate-x-1/2 translate-y-1/2 opacity-20 animate-float" style="animation-delay: 1s;"></div>
        <div class="absolute top-1/4 left-10 w-40 h-40 bg-purple-100 rounded-full opacity-15 animate-float" style="animation-delay: 2s;"></div>
        <div class="absolute top-1/3 right-20 w-32 h-32 bg-blue-200 rounded-full opacity-10 animate-float" style="animation-delay: 3s;"></div>
        <div class="absolute bottom-1/4 right-1/4 w-48 h-48 bg-indigo-100 rounded-full opacity-15 animate-float" style="animation-delay: 1.5s;"></div>
        <div class="absolute top-2/3 left-1/4 w-36 h-36 bg-purple-200 rounded-full opacity-10 animate-float" style="animation-delay: 2.5s;"></div>

        <div class="container mx-auto px-4 relative z-10">
            <!-- Page Header -->
            <div class="text-center mb-12 md:mb-20">
                <div class="inline-block px-3 md:px-4 py-1 md:py-1.5 mb-3 md:mb-4 bg-blue-50 text-blue-600 rounded-full text-xs md:text-sm font-bold tracking-wide uppercase">
                    Leadership
                </div>
                <h1 class="text-3xl md:text-4xl lg:text-6xl font-black mb-3 md:mb-4">
                    <span class="bg-clip-text text-transparent bg-gradient-to-r from-blue-600 to-indigo-600">Meet Our Esteemed Founders</span>
                </h1>
                <p class="text-gray-500 text-sm md:text-base lg:text-lg max-w-2xl mx-auto px-4">Visionary leaders who shaped the foundation of excellence</p>
            </div>

            <!-- Founder 1 - M.M Nakrani -->
            <div class="max-w-6xl mx-auto mb-12 md:mb-24">
                <div class="bg-white rounded-[2rem] md:rounded-[2.5rem] shadow-2xl overflow-hidden">
                    <div class="flex flex-col lg:flex-row">
                        <!-- Image Side -->
                        <div class="lg:w-2/5 p-6 md:p-8 lg:p-12 bg-gradient-to-br from-blue-50 to-blue-100/50 flex flex-col items-center justify-center relative">
                            <div class="absolute top-0 left-0 w-32 h-32 bg-blue-200 rounded-full -translate-x-1/2 -translate-y-1/2 opacity-30 animate-float" style="animation-delay: 0s;"></div>
                            <div class="absolute bottom-0 right-0 w-24 h-24 bg-indigo-200 rounded-full translate-x-1/2 translate-y-1/2 opacity-30 animate-float" style="animation-delay: 1.5s;"></div>
                            <div class="absolute top-1/4 right-4 w-16 h-16 bg-blue-300 rounded-full opacity-20 animate-float" style="animation-delay: 2s;"></div>
                            <div class="absolute bottom-1/4 left-4 w-20 h-20 bg-indigo-300 rounded-full opacity-25 animate-float" style="animation-delay: 3s;"></div>

                            <div class="relative group">
                                <div class="absolute -inset-1 bg-gradient-to-r from-blue-600 to-indigo-600 rounded-full opacity-75 group-hover:opacity-100 blur transition duration-300"></div>
                                <img src="../assets/images/nakranisir.jpeg" alt="M.M Nakrani" class="relative rounded-full w-40 h-40 md:w-56 md:h-56 lg:w-64 lg:h-64 object-cover object-center border-4 border-white shadow-xl">
                            </div>

                            <div class="text-center mt-6 md:mt-8 relative z-10">
                                <h2 class="text-2xl md:text-3xl lg:text-4xl font-bold text-gray-900 mb-2">M.M Nakrani</h2>
                                <div class="inline-block px-3 md:px-4 py-1.5 md:py-2 bg-blue-600 text-white rounded-full text-xs md:text-sm lg:text-base font-bold shadow-lg">
                                    Founder Trustee
                                </div>
                                <div class="mt-3 md:mt-4 px-3 md:px-4 py-1.5 md:py-2 bg-white/80 backdrop-blur-sm rounded-full text-xs md:text-sm font-semibold text-blue-600 inline-block">
                                    <i class="fas fa-atom mr-1 md:mr-2"></i>Physics Adviser
                                </div>
                            </div>
                        </div>

                        <!-- Content Side -->
                        <div class="lg:w-3/5 p-6 md:p-8 lg:p-12 flex flex-col justify-center">
                            <div class="space-y-4 md:space-y-6">
                                <p class="text-gray-700 text-sm md:text-base lg:text-lg leading-relaxed">
                                    M.M. Nakrani has been a <span class="text-blue-600 font-semibold">visionary force</span> behind the institution, dedicated to preparing students not just for exams, but for life itself. He believes that students must strive with determination to achieve their goals, while teachers and parents should act as supportive facilitators in this journey.
                                </p>

                                <div class="relative pl-4 md:pl-6 border-l-4 border-blue-600 bg-blue-50 p-4 md:p-6 rounded-r-xl md:rounded-r-2xl">
                                    <i class="fas fa-quote-left text-blue-600 text-xl md:text-2xl absolute -left-2 md:-left-3 -top-2 bg-white rounded-full p-1.5 md:p-2"></i>
                                    <p class="text-gray-800 text-base md:text-lg lg:text-xl font-medium italic leading-relaxed">
                                        May God grant everyone the strength and wisdom to nurture children towards a smooth and successful life.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Founder 2 - V.J Purohit -->
            <div class="max-w-6xl mx-auto mb-12 md:mb-16">
                <div class="bg-white rounded-[2rem] md:rounded-[2.5rem] shadow-2xl overflow-hidden">
                    <div class="flex flex-col lg:flex-row-reverse">
                        <!-- Image Side -->
                        <div class="lg:w-2/5 p-6 md:p-8 lg:p-12 bg-gradient-to-br from-purple-50 to-purple-100/50 flex flex-col items-center justify-center relative">
                            <div class="absolute top-0 right-0 w-32 h-32 bg-purple-200 rounded-full translate-x-1/2 -translate-y-1/2 opacity-30 animate-float" style="animation-delay: 0.5s;"></div>
                            <div class="absolute bottom-0 left-0 w-24 h-24 bg-indigo-200 rounded-full -translate-x-1/2 translate-y-1/2 opacity-30 animate-float" style="animation-delay: 2s;"></div>
                            <div class="absolute top-1/4 left-4 w-16 h-16 bg-purple-300 rounded-full opacity-20 animate-float" style="animation-delay: 2.5s;"></div>
                            <div class="absolute bottom-1/4 right-4 w-20 h-20 bg-indigo-300 rounded-full opacity-25 animate-float" style="animation-delay: 1s;"></div>

                            <div class="relative group">
                                <div class="absolute -inset-1 bg-gradient-to-r from-purple-600 to-indigo-600 rounded-full opacity-75 group-hover:opacity-100 blur transition duration-300"></div>
                                <img src="../assets/images/purohitsir.jpg" alt="V.J Purohit" class="relative rounded-full w-40 h-40 md:w-56 md:h-56 lg:w-64 lg:h-64 object-cover object-center border-4 border-white shadow-xl">
                            </div>

                            <div class="text-center mt-6 md:mt-8 relative z-10">
                                <h2 class="text-2xl md:text-3xl lg:text-4xl font-bold text-gray-900 mb-2">V.J Purohit</h2>
                                <div class="inline-block px-3 md:px-4 py-1.5 md:py-2 bg-purple-600 text-white rounded-full text-xs md:text-sm lg:text-base font-bold shadow-lg">
                                    President, Founder Trustee
                                </div>
                                <div class="mt-3 md:mt-4 px-3 md:px-4 py-1.5 md:py-2 bg-white/80 backdrop-blur-sm rounded-full text-xs md:text-sm font-semibold text-purple-600 inline-block">
                                    <i class="fas fa-calculator mr-1 md:mr-2"></i>Mathematics Adviser
                                </div>
                            </div>
                        </div>

                        <!-- Content Side -->
                        <div class="lg:w-3/5 p-6 md:p-8 lg:p-12 flex flex-col justify-center">
                            <div class="space-y-4 md:space-y-6">
                                <p class="text-gray-700 text-sm md:text-base lg:text-lg leading-relaxed">
                                    An educational institute must act as a <span class="text-purple-600 font-semibold">'Centre for Excellence'</span> and strive to shape the destiny of generation next. I am sure and confident that Gyanmanjari Parivar as a whole will put best efforts and employ latest ways and means to shape the future of GMites.
                                </p>

                                <div class="relative pl-4 md:pl-6 border-l-4 border-purple-600 bg-purple-50 p-4 md:p-6 rounded-r-xl md:rounded-r-2xl">
                                    <i class="fas fa-quote-left text-purple-600 text-xl md:text-2xl absolute -left-2 md:-left-3 -top-2 bg-white rounded-full p-1.5 md:p-2"></i>
                                    <p class="text-gray-800 text-base md:text-lg lg:text-xl font-medium italic leading-relaxed">
                                        I am hopeful of full support from parents-students in particular and society at large in fulfilling our objectives. Best wishes for all students.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Vision Statement -->
            <div class="max-w-4xl mx-auto mt-12 md:mt-24 text-center">
                <div class="bg-gradient-to-r from-blue-600 to-indigo-600 rounded-[2rem] md:rounded-[2.5rem] p-6 md:p-8 lg:p-12 shadow-2xl relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-64 h-64 bg-white/10 rounded-full -translate-x-1/2 -translate-y-1/2 animate-float" style="animation-delay: 0s;"></div>
                    <div class="absolute bottom-0 right-0 w-48 h-48 bg-white/10 rounded-full translate-x-1/2 translate-y-1/2 animate-float" style="animation-delay: 1.5s;"></div>
                    <div class="absolute top-1/2 left-10 w-32 h-32 bg-white/10 rounded-full animate-float" style="animation-delay: 3s;"></div>
                    <div class="absolute top-1/3 right-10 w-40 h-40 bg-white/10 rounded-full animate-float" style="animation-delay: 2s;"></div>
                    <div class="absolute bottom-1/4 left-1/3 w-24 h-24 bg-white/10 rounded-full animate-float" style="animation-delay: 2.5s;"></div>

                    <div class="relative z-10">
                        <div class="w-12 h-12 md:w-16 md:h-16 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-4 md:mb-6">
                            <i class="fas fa-lightbulb text-white text-2xl md:text-3xl"></i>
                        </div>
                        <h2 class="text-2xl md:text-3xl lg:text-4xl font-bold text-white mb-4 md:mb-6">Our Shared Vision</h2>
                        <p class="text-white/90 text-base md:text-lg lg:text-xl leading-relaxed">
                            Together, our founders envisioned a place where every student's potential is unlocked, where knowledge is a journey of discovery, and where dedication guides every step towards excellence.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <?php include __DIR__ . '/../include/public-footer.php'; ?>
</body>

</html>