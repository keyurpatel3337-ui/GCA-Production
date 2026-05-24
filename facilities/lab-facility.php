<?php require_once dirname(__DIR__) . '/common/constants.php';
require_once ENV_CONFIG_FILE; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Facility</title>
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

    <!-- Lab Facility Content Section -->
    <section class="relative py-12 md:py-24 overflow-hidden bg-gradient-to-b from-white via-emerald-50/30 to-white">
        <!-- Decorative background elements -->
        <div class="absolute top-0 right-0 w-96 h-96 bg-emerald-100 rounded-full translate-x-1/2 -translate-y-1/2 opacity-20 animate-float" style="animation-delay: 0s;"></div>
        <div class="absolute bottom-0 left-0 w-64 h-64 bg-teal-100 rounded-full -translate-x-1/2 translate-y-1/2 opacity-20 animate-float" style="animation-delay: 1s;"></div>
        <div class="absolute top-1/4 left-10 w-40 h-40 bg-sky-100 rounded-full opacity-15 animate-float" style="animation-delay: 2s;"></div>

        <div class="container mx-auto px-4 relative z-10">
            <!-- Page Header -->
            <div class="text-center mb-12 md:mb-20">
                <div class="inline-block px-3 md:px-4 py-1 md:py-1.5 mb-3 md:mb-4 bg-emerald-50 text-emerald-600 rounded-full text-xs md:text-sm font-bold tracking-wide uppercase">
                    Scientific Discovery
                </div>
                <h1 class="text-3xl md:text-4xl lg:text-6xl font-black mb-3 md:mb-4">
                    <span class="bg-clip-text text-transparent bg-gradient-to-r from-emerald-600 to-teal-600">Advanced Laboratory Facilities</span>
                </h1>
                <p class="text-gray-500 text-sm md:text-base lg:text-lg max-w-2xl mx-auto px-4">Where theory meets practice: equipping the next generation of scientists with hands-on experience.</p>
            </div>

            <!-- Main Content Card -->
            <div class="max-w-6xl mx-auto mb-12 md:mb-24">
                <div class="bg-white rounded-[2rem] md:rounded-[2.5rem] shadow-2xl overflow-hidden">
                    <div class="flex flex-col lg:flex-row">
                        <!-- Image Side -->
                        <div class="lg:w-1/2 p-4 md:p-6 lg:p-8 bg-gradient-to-br from-emerald-50 to-teal-50/50 flex items-center justify-center">
                            <div class="relative group">
                                <div class="absolute -inset-1 bg-gradient-to-r from-emerald-600 to-teal-600 rounded-2xl opacity-25 group-hover:opacity-40 blur transition duration-300"></div>
                                <img src="../assets/images/leb.jpg" alt="Lab Facility" class="relative rounded-2xl w-full h-auto object-cover border-4 border-white shadow-xl">
                            </div>
                        </div>

                        <!-- Content Side -->
                        <div class="lg:w-1/2 p-6 md:p-8 lg:p-12 flex flex-col justify-center">
                            <h2 class="text-2xl md:text-3xl font-bold text-gray-900 mb-6">The Backbone of Science</h2>
                            <div class="space-y-4 md:space-y-6 text-gray-700 leading-relaxed">
                                <p class="text-sm md:text-base lg:text-lg">
                                    We believe that practical application is the cornerstone of scientific understanding. Gyanmanjari provides fully-equipped, modern laboratories for <span class="text-emerald-600 font-bold">Physics, Chemistry, Biology, and Mathematics</span>.
                                </p>
                                <p class="text-sm md:text-base lg:text-lg">
                                    Managed by expert lab assistants and overseen by experienced faculty, these facilities provide the necessary environment for students to develop essential technical skills and a scientific temper.
                                </p>
                                <div class="bg-emerald-50 p-6 rounded-2xl border border-emerald-100 flex items-start space-x-4">
                                    <div class="p-3 bg-white rounded-xl shadow-sm">
                                        <i class="fas fa-microscope text-emerald-600 text-xl"></i>
                                    </div>
                                    <p class="text-sm text-emerald-900">
                                        Our labs are designed to meet international safety standards and are continuously updated with the latest scientific apparatus.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Lab Categories Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 max-w-6xl mx-auto">
                <div class="bg-white p-8 rounded-[2rem] shadow-lg border border-emerald-50 hover:-translate-y-2 transition-transform duration-300 text-center group">
                    <div class="w-16 h-16 bg-emerald-100 rounded-2xl flex items-center justify-center mx-auto mb-6 group-hover:bg-emerald-600 transition-colors">
                        <i class="fas fa-atom text-emerald-600 text-2xl group-hover:text-white"></i>
                    </div>
                    <h3 class="font-bold text-gray-900">Physics Lab</h3>
                </div>
                <div class="bg-white p-8 rounded-[2rem] shadow-lg border border-teal-50 hover:-translate-y-2 transition-transform duration-300 text-center group">
                    <div class="w-16 h-16 bg-teal-100 rounded-2xl flex items-center justify-center mx-auto mb-6 group-hover:bg-teal-600 transition-colors">
                        <i class="fas fa-vial text-teal-600 text-2xl group-hover:text-white"></i>
                    </div>
                    <h3 class="font-bold text-gray-900">Chemistry Lab</h3>
                </div>
                <div class="bg-white p-8 rounded-[2rem] shadow-lg border border-sky-50 hover:-translate-y-2 transition-transform duration-300 text-center group">
                    <div class="w-16 h-16 bg-sky-100 rounded-2xl flex items-center justify-center mx-auto mb-6 group-hover:bg-sky-600 transition-colors">
                        <i class="fas fa-dna text-sky-600 text-2xl group-hover:text-white"></i>
                    </div>
                    <h3 class="font-bold text-gray-900">Biology Lab</h3>
                </div>
                <div class="bg-white p-8 rounded-[2rem] shadow-lg border border-indigo-50 hover:-translate-y-2 transition-transform duration-300 text-center group">
                    <div class="w-16 h-16 bg-indigo-100 rounded-2xl flex items-center justify-center mx-auto mb-6 group-hover:bg-indigo-600 transition-colors">
                        <i class="fas fa-calculator text-indigo-600 text-2xl group-hover:text-white"></i>
                    </div>
                    <h3 class="font-bold text-gray-900">Maths Lab</h3>
                </div>
            </div>
        </div>
    </section>


            <!-- <div class="text-center py-8 border-t border-gray-200 mt-12">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-800 mb-6">Explore Our Digital World</h2>
                <p class="text-lg text-gray-700 leading-relaxed max-w-2xl mx-auto mb-8">
                    We invite you to visit our computer lab and experience firsthand the resources available to enhance your digital skills and learning journey.
                </p>
                <a href="#" class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-full shadow-lg transition duration-300 ease-in-out transform hover:scale-105">
                    Contact Us for a Tour
                </a>
            </div>
        </div> -->
    </section>

    <!-- Footer -->
    <?php include __DIR__ . '/../include/public-footer.php'; ?>

</body>

</html>
