<?php require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once ENV_CONFIG_FILE; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Playground</title>
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

    <!-- Playground Content Section -->
    <section class="relative py-12 md:py-24 overflow-hidden bg-gradient-to-b from-white via-green-50/30 to-white">
        <!-- Decorative background elements -->
        <div class="absolute top-0 right-0 w-96 h-96 bg-green-100 rounded-full translate-x-1/2 -translate-y-1/2 opacity-20 animate-float" style="animation-delay: 0s;"></div>
        <div class="absolute bottom-0 left-0 w-64 h-64 bg-lime-100 rounded-full -translate-x-1/2 translate-y-1/2 opacity-20 animate-float" style="animation-delay: 1s;"></div>
        <div class="absolute top-1/4 left-10 w-40 h-40 bg-yellow-100 rounded-full opacity-15 animate-float" style="animation-delay: 2s;"></div>

        <div class="container mx-auto px-4 relative z-10">
            <!-- Page Header -->
            <div class="text-center mb-12 md:mb-20">
                <div class="inline-block px-3 md:px-4 py-1 md:py-1.5 mb-3 md:mb-4 bg-green-50 text-green-600 rounded-full text-xs md:text-sm font-bold tracking-wide uppercase">
                    Physical Excellence
                </div>
                <h1 class="text-3xl md:text-4xl lg:text-6xl font-black mb-3 md:mb-4">
                    <span class="bg-clip-text text-transparent bg-gradient-to-r from-green-600 to-lime-600">Our Expansive Playground</span>
                </h1>
                <p class="text-gray-500 text-sm md:text-base lg:text-lg max-w-2xl mx-auto px-4">Cultivating health, teamwork, and a competitive spirit through world-class sports facilities.</p>
            </div>

            <!-- Main Content Card -->
            <div class="max-w-6xl mx-auto mb-12 md:mb-24">
                <div class="bg-white rounded-[2rem] md:rounded-[2.5rem] shadow-2xl overflow-hidden">
                    <div class="flex flex-col lg:flex-row">
                        <!-- Image Side -->
                        <div class="lg:w-1/2 p-4 md:p-6 lg:p-8 bg-gradient-to-br from-green-50 to-lime-50/50 flex items-center justify-center">
                            <div class="relative group">
                                <div class="absolute -inset-1 bg-gradient-to-r from-green-600 to-lime-600 rounded-2xl opacity-25 group-hover:opacity-40 blur transition duration-300"></div>
                                <img src="../assets/images/playground.JPG" alt="Playground" class="relative rounded-2xl w-full h-auto object-cover border-4 border-white shadow-xl">
                            </div>
                        </div>

                        <!-- Content Side -->
                        <div class="lg:w-1/2 p-6 md:p-8 lg:p-12 flex flex-col justify-center">
                            <h2 class="text-2xl md:text-3xl font-bold text-gray-900 mb-6">Health is Wealth</h2>
                            <div class="space-y-4 md:space-y-6 text-gray-700 leading-relaxed">
                                <p class="text-sm md:text-base lg:text-lg">
                                    A healthy body leads to a healthy mind. At Gyanmanjari, we provide extensive training and coaching in a wide array of sports including Athletics, Basketball, Cricket, and Football.
                                </p>
                                <p class="text-sm md:text-base lg:text-lg">
                                    Our students also excel in traditional sports like Kabaddi and Kho-Kho, as well as modern disciplines like Skating, Karate, and Yoga.
                                </p>
                                <div class="bg-green-50 p-6 rounded-2xl border border-green-100 flex items-start space-x-4">
                                    <div class="p-3 bg-white rounded-xl shadow-sm">
                                        <i class="fas fa-trophy text-green-600 text-xl"></i>
                                    </div>
                                    <p class="text-sm text-green-900">
                                        Every activity is conducted under the expert guidance of qualified and experienced sports teachers.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sports Categories -->
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 max-w-6xl mx-auto">
                <div class="bg-white p-6 rounded-2xl shadow-md border border-green-50 text-center hover:bg-green-600 hover:text-white transition-all cursor-default">
                    <i class="fas fa-running text-2xl mb-2"></i>
                    <p class="font-bold text-sm">Athletics</p>
                </div>
                <div class="bg-white p-6 rounded-2xl shadow-md border border-green-50 text-center hover:bg-green-600 hover:text-white transition-all cursor-default">
                    <i class="fas fa-basketball-ball text-2xl mb-2"></i>
                    <p class="font-bold text-sm">Basketball</p>
                </div>
                <div class="bg-white p-6 rounded-2xl shadow-md border border-green-50 text-center hover:bg-green-600 hover:text-white transition-all cursor-default">
                    <i class="fas fa-baseball-ball text-2xl mb-2"></i>
                    <p class="font-bold text-sm">Cricket</p>
                </div>
                <div class="bg-white p-6 rounded-2xl shadow-md border border-green-50 text-center hover:bg-green-600 hover:text-white transition-all cursor-default">
                    <i class="fas fa-futbol text-2xl mb-2"></i>
                    <p class="font-bold text-sm">Football</p>
                </div>
                <div class="bg-white p-6 rounded-2xl shadow-md border border-green-50 text-center hover:bg-green-600 hover:text-white transition-all cursor-default">
                    <i class="fas fa-skating text-2xl mb-2"></i>
                    <p class="font-bold text-sm">Skating</p>
                </div>
                <div class="bg-white p-6 rounded-2xl shadow-md border border-green-50 text-center hover:bg-green-600 hover:text-white transition-all cursor-default">
                    <i class="fas fa-spa text-2xl mb-2"></i>
                    <p class="font-bold text-sm">Yoga</p>
                </div>
            </div>
        </div>
    </section>

    </section>

    <!-- Footer -->
    <?php include __DIR__ . '/../../../include/public-footer.php'; ?>

</body>

</html>