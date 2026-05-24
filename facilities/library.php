<?php require_once dirname(__DIR__) . '/common/constants.php';
require_once ENV_CONFIG_FILE; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library</title>
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

    <!-- Library Content Section -->
    <section class="relative py-12 md:py-24 overflow-hidden bg-gradient-to-b from-white via-amber-50/30 to-white">
        <!-- Decorative background elements -->
        <div class="absolute top-0 right-0 w-96 h-96 bg-amber-100 rounded-full translate-x-1/2 -translate-y-1/2 opacity-20 animate-float css-library-f165c4"></div>
        <div class="absolute bottom-0 left-0 w-64 h-64 bg-orange-100 rounded-full -translate-x-1/2 translate-y-1/2 opacity-20 animate-float css-library-bd845f"></div>
        <div class="absolute top-1/4 left-10 w-40 h-40 bg-yellow-100 rounded-full opacity-15 animate-float css-library-cffe64"></div>

        <div class="container mx-auto px-4 relative z-10">
            <!-- Page Header -->
            <div class="text-center mb-12 md:mb-20">
                <div class="inline-block px-3 md:px-4 py-1 md:py-1.5 mb-3 md:mb-4 bg-amber-50 text-amber-600 rounded-full text-xs md:text-sm font-bold tracking-wide uppercase">
                    Knowledge Hub
                </div>
                <h1 class="text-3xl md:text-4xl lg:text-6xl font-black mb-3 md:mb-4">
                    <span class="bg-clip-text text-transparent bg-gradient-to-r from-amber-600 to-orange-600">Our State-of-the-Art Library</span>
                </h1>
                <p class="text-gray-500 text-sm md:text-base lg:text-lg max-w-2xl mx-auto px-4">A sanctuary of learning, inquiry, and intellectual growth for the students of today.</p>
            </div>

            <!-- Main Content Card -->
            <div class="max-w-6xl mx-auto mb-12 md:mb-24">
                <div class="bg-white rounded-[2rem] md:rounded-[2.5rem] shadow-2xl overflow-hidden">
                    <div class="flex flex-col lg:flex-row">
                        <!-- Image Side -->
                        <div class="lg:w-1/2 p-4 md:p-6 lg:p-8 bg-gradient-to-br from-amber-50 to-orange-50/50 flex items-center justify-center">
                            <div class="relative group">
                                <div class="absolute -inset-1 bg-gradient-to-r from-amber-600 to-orange-600 rounded-2xl opacity-25 group-hover:opacity-40 blur transition duration-300"></div>
                                <img src="../assets/images/2024-04-02-51-12-1.jpg" alt="Library" class="relative rounded-2xl w-full h-auto object-cover border-4 border-white shadow-xl">
                            </div>
                        </div>

                        <!-- Content Side -->
                        <div class="lg:w-1/2 p-6 md:p-8 lg:p-12 flex flex-col justify-center">
                            <h2 class="text-2xl md:text-3xl font-bold text-gray-900 mb-6">Food for Thought</h2>
                            <div class="space-y-4 md:space-y-6">
                                <p class="text-gray-700 text-sm md:text-base lg:text-lg leading-relaxed">
                                    Gyanmanjari Vidyapith features <span class="text-amber-600 font-semibold">450 partially isolated self-study tables</span>, specifically designed for Std 11-12 Science students to focus during their crucial self-study hours.
                                </p>
                                <p class="text-gray-700 text-sm md:text-base lg:text-lg leading-relaxed">
                                    Our library offers a rich menu of knowledge across General Knowledge, Science, Commerce, Computers, and more. With a constant addition of the latest journals and periodicals, we ensure our "menu" of knowledge is always fresh and engaging.
                                </p>
                                <div class="bg-amber-50 p-6 rounded-2xl border border-amber-100 flex items-start space-x-4">
                                    <div class="p-3 bg-white rounded-xl shadow-sm">
                                        <i class="fas fa-newspaper text-amber-600 text-xl"></i>
                                    </div>
                                    <p class="text-sm text-amber-900">
                                        Daily newspapers and latest novels are provided to keep students updated and foster a diverse reading habit.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Key Highlights -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-6xl mx-auto">
                <div class="bg-white p-8 rounded-[2rem] shadow-xl border border-amber-50 group hover:bg-amber-600 transition-all duration-500">
                    <div class="w-14 h-14 bg-amber-100 rounded-xl flex items-center justify-center mb-6 group-hover:bg-white/20">
                        <i class="fas fa-book-reader text-amber-600 text-2xl group-hover:text-white"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3 group-hover:text-white">Silent Study</h3>
                    <p class="text-gray-500 group-hover:text-amber-50">Private study zones tailored for deep concentration and academic success.</p>
                </div>
                <div class="bg-white p-8 rounded-[2rem] shadow-xl border border-amber-50 group hover:bg-orange-600 transition-all duration-500">
                    <div class="w-14 h-14 bg-orange-100 rounded-xl flex items-center justify-center mb-6 group-hover:bg-white/20">
                        <i class="fas fa-atlas text-orange-600 text-2xl group-hover:text-white"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3 group-hover:text-white">Vast Collection</h3>
                    <p class="text-gray-500 group-hover:text-orange-50">Comprehensive resources ranging from academic texts to contemporary literature.</p>
                </div>
                <div class="bg-white p-8 rounded-[2rem] shadow-xl border border-amber-50 group hover:bg-yellow-600 transition-all duration-500">
                    <div class="w-14 h-14 bg-yellow-100 rounded-xl flex items-center justify-center mb-6 group-hover:bg-white/20">
                        <i class="fas fa-search text-yellow-600 text-2xl group-hover:text-white"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3 group-hover:text-white">Daily Updates</h3>
                    <p class="text-gray-500 group-hover:text-yellow-50">Stay informed with daily national and local newspapers and periodicals.</p>
                </div>
            </div>
        </div>
    </section>


    <!-- Footer -->
    <?php include __DIR__ . '/../include/public-footer.php'; ?>

</body>

</html>
