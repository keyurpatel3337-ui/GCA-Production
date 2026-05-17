<?php require_once dirname(__DIR__) . '/common/constants.php';
require_once ENV_CONFIG_FILE; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrator - Gyanmanjari Vidyapith</title>
    <link rel="icon" type="image/x-icon" href="../assets/images/logogmn.png">
    <!-- Tailwind CSS -->
    <script src="<?php echo BASE_URL; ?>/assets/vendor/tailwind/tailwind.min.js"></script>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/vendor/font-awesome/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- AOS Animation Library -->
    <link href="<?php echo BASE_URL; ?>/assets/vendor/aos/aos.css" rel="stylesheet">
    <!-- Link to external CSS file -->
    <link rel="stylesheet" href="../assets/css/gm-public.css">
    <style>
        /* Ensure content is visible by default - reveal animation is enhancement */
        .reveal-content {
            opacity: 1 !important;
            transform: translateY(0) !important;
        }
    </style>
</head>

<body class="text-gray-800">
    <!-- Header/Navbar -->
    <?php include __DIR__ . '/../include/public-header.php'; ?>

    <!-- Administrator Content Section -->
    <section class="relative py-16 md:py-24 overflow-hidden bg-gradient-to-br from-blue-50 via-indigo-50 to-purple-50">
        <!-- Animated Background Bubbles -->
        <div class="absolute inset-0 pointer-events-none overflow-hidden">
            <div class="absolute top-10 left-10 w-72 h-72 bg-blue-400/10 rounded-full blur-3xl animate-float" style="animation-delay: 0s;"></div>
            <div class="absolute top-1/4 right-20 w-96 h-96 bg-indigo-400/10 rounded-full blur-3xl animate-float" style="animation-delay: 1s;"></div>
            <div class="absolute bottom-20 left-1/4 w-80 h-80 bg-purple-400/10 rounded-full blur-3xl animate-float" style="animation-delay: 2s;"></div>
            <div class="absolute top-1/2 right-1/3 w-64 h-64 bg-blue-300/10 rounded-full blur-3xl animate-float" style="animation-delay: 1.5s;"></div>
            <div class="absolute bottom-1/4 right-10 w-72 h-72 bg-indigo-300/10 rounded-full blur-3xl animate-float" style="animation-delay: 2.5s;"></div>
            <div class="absolute top-3/4 left-1/2 w-56 h-56 bg-purple-300/10 rounded-full blur-3xl animate-float" style="animation-delay: 3s;"></div>
        </div>

        <div class="container mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <!-- Section Title -->
            <div class="text-center mb-16 reveal-content">
                <h1 class="text-4xl sm:text-5xl md:text-6xl font-black mb-4">
                    <span class="bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-600 bg-clip-text text-transparent">Meet Our Esteemed</span>
                </h1>
                <h2 class="text-3xl sm:text-4xl md:text-5xl font-black">
                    <span class="bg-gradient-to-r from-indigo-600 to-blue-600 bg-clip-text text-transparent">Administrator</span>
                </h2>
                <div class="w-24 h-1 bg-gradient-to-r from-blue-600 to-indigo-600 mx-auto mt-6 rounded-full"></div>
            </div>

            <!-- Administrator Card -->
            <div class="max-w-6xl mx-auto reveal-content">
                <div class="relative bg-white/70 backdrop-blur-xl rounded-[2.5rem] shadow-2xl overflow-hidden border border-white/20">
                    <!-- Gradient Background -->
                    <div class="absolute inset-0 bg-gradient-to-br from-blue-500/5 via-indigo-500/5 to-blue-500/5"></div>

                    <!-- Decorative Bubbles inside Card -->
                    <div class="absolute top-10 right-10 w-32 h-32 bg-blue-400/10 rounded-full blur-2xl animate-float" style="animation-delay: 0.5s;"></div>
                    <div class="absolute bottom-20 left-10 w-24 h-24 bg-indigo-400/10 rounded-full blur-2xl animate-float" style="animation-delay: 1.5s;"></div>
                    <div class="absolute top-1/2 right-1/4 w-20 h-20 bg-blue-300/10 rounded-full blur-2xl animate-float" style="animation-delay: 2s;"></div>
                    <div class="absolute bottom-10 right-20 w-16 h-16 bg-indigo-300/10 rounded-full blur-2xl animate-float" style="animation-delay: 2.5s;"></div>

                    <div class="relative p-8 md:p-12 lg:p-16">
                        <div class="flex flex-col lg:flex-row items-center gap-12">
                            <!-- Administrator Image -->
                            <div class="lg:w-2/5 text-center lg:text-left flex-shrink-0">
                                <div class="relative inline-block">
                                    <div class="absolute inset-0 bg-gradient-to-r from-blue-500 to-indigo-600 rounded-full blur-2xl opacity-30 animate-pulse"></div>
                                    <img src="../assets/images/avinashsir.jpeg"
                                        alt="Avinash Patel - Chemistry Adviser"
                                        class="relative rounded-full w-56 h-56 md:w-72 md:h-72 object-cover object-center shadow-2xl border-4 border-white ring-4 ring-blue-500/50 mx-auto">
                                </div>
                                <div class="mt-8">
                                    <h3 class="text-3xl md:text-4xl font-bold">
                                        <span class="bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent">Avinash Patel</span>
                                    </h3>
                                    <p class="text-xl md:text-2xl font-semibold text-blue-600 mt-2">Chemistry Adviser</p>
                                    <div class="flex justify-center lg:justify-start gap-3 mt-4">
                                        <span class="px-4 py-2 bg-gradient-to-r from-blue-500 to-indigo-600 text-white rounded-full text-sm font-medium shadow-lg">
                                            <i class="fas fa-flask mr-2"></i>Chemistry Expert
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Administrator Details -->
                            <div class="lg:w-3/5">
                                <!-- Quote Box -->
                                <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-3xl p-6 md:p-8 mb-8 border border-blue-200/50 shadow-lg">
                                    <div class="flex items-start gap-4">
                                        <div class="flex-shrink-0 w-12 h-12 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center shadow-lg">
                                            <i class="fas fa-quote-left text-white text-xl"></i>
                                        </div>
                                        <div class="flex-1">
                                            <h4 class="text-xl md:text-2xl font-bold mb-3">
                                                <span class="bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent">Center of Excellence</span>
                                            </h4>
                                            <p class="text-gray-700 leading-relaxed text-base md:text-lg">
                                                Gyanmanjari Vidyapith is structured around "Center of Excellence." Our Practice areas operate as a coordinated whole; separate, yet capable of providing integrated service for our student's benefit. This Structure enables us to quickly mobilize the right resources to implement the strategies to develop whenever and wherever students need.
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Key Responsibilities -->
                                <div class="space-y-4">
                                    <h4 class="text-2xl font-bold mb-6">
                                        <span class="bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent">Key Responsibilities</span>
                                    </h4>
                                    <div class="grid gap-4">
                                        <div class="flex items-start gap-4 bg-white/80 backdrop-blur-sm rounded-2xl p-5 border border-blue-100/50 shadow-md hover:shadow-xl transition-all duration-300">
                                            <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-lg flex items-center justify-center flex-shrink-0 shadow-md">
                                                <i class="fas fa-chalkboard-teacher text-white"></i>
                                            </div>
                                            <div>
                                                <h5 class="font-semibold text-gray-800 text-lg mb-1">Academic Oversight</h5>
                                                <p class="text-gray-600 text-sm">Leading chemistry education and curriculum development</p>
                                            </div>
                                        </div>
                                        <div class="flex items-start gap-4 bg-white/80 backdrop-blur-sm rounded-2xl p-5 border border-blue-100/50 shadow-md hover:shadow-xl transition-all duration-300">
                                            <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-lg flex items-center justify-center flex-shrink-0 shadow-md">
                                                <i class="fas fa-users text-white"></i>
                                            </div>
                                            <div>
                                                <h5 class="font-semibold text-gray-800 text-lg mb-1">Student Guidance</h5>
                                                <p class="text-gray-600 text-sm">Mentoring students for competitive exams and career planning</p>
                                            </div>
                                        </div>
                                        <div class="flex items-start gap-4 bg-white/80 backdrop-blur-sm rounded-2xl p-5 border border-blue-100/50 shadow-md hover:shadow-xl transition-all duration-300">
                                            <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-lg flex items-center justify-center flex-shrink-0 shadow-md">
                                                <i class="fas fa-lightbulb text-white"></i>
                                            </div>
                                            <div>
                                                <h5 class="font-semibold text-gray-800 text-lg mb-1">Innovation & Strategy</h5>
                                                <p class="text-gray-600 text-sm">Implementing innovative teaching methods and strategies</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Vision Statement -->
            <div class="max-w-5xl mx-auto mt-16 reveal-content">
                <div class="relative bg-gradient-to-br from-blue-600 via-indigo-600 to-purple-600 rounded-[2.5rem] p-8 md:p-12 shadow-2xl overflow-hidden">
                    <!-- Decorative Bubbles -->
                    <div class="absolute top-5 right-10 w-40 h-40 bg-white/10 rounded-full blur-2xl animate-float" style="animation-delay: 0s;"></div>
                    <div class="absolute bottom-10 left-10 w-32 h-32 bg-white/10 rounded-full blur-2xl animate-float" style="animation-delay: 1s;"></div>
                    <div class="absolute top-1/2 right-1/4 w-24 h-24 bg-white/10 rounded-full blur-2xl animate-float" style="animation-delay: 1.5s;"></div>
                    <div class="absolute bottom-5 right-20 w-48 h-48 bg-white/10 rounded-full blur-2xl animate-float" style="animation-delay: 2s;"></div>
                    <div class="absolute top-10 left-1/3 w-56 h-56 bg-white/10 rounded-full blur-2xl animate-float" style="animation-delay: 2.5s;"></div>

                    <div class="relative z-10">
                        <div class="flex items-center justify-center gap-4 mb-6">
                            <div class="w-16 h-16 bg-white/20 backdrop-blur-sm rounded-2xl flex items-center justify-center shadow-lg">
                                <i class="fas fa-star text-white text-2xl"></i>
                            </div>
                            <h3 class="text-3xl md:text-4xl font-black text-white">Our Commitment</h3>
                        </div>
                        <p class="text-white/95 text-lg md:text-xl leading-relaxed text-center max-w-4xl mx-auto">
                            Under the expert guidance of our Chemistry Adviser, we strive to create an environment where students not only excel academically but also develop critical thinking skills and a passion for scientific inquiry. Our integrated approach ensures every student receives personalized attention and the resources needed to achieve their full potential.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <?php include __DIR__ . '/../include/public-footer.php'; ?>

    <!-- AOS Animation Library -->
    <script src="<?php echo BASE_URL; ?>/assets/vendor/aos/aos.js"></script>
    <script>
        // Initialize AOS
        AOS.init({
            duration: 800,
            once: true,
            offset: 100
        });

        // Scroll-based reveal animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observe all reveal-content elements
        document.addEventListener('DOMContentLoaded', () => {
            const revealElements = document.querySelectorAll('.reveal-content');
            if (revealElements.length > 0) {
                revealElements.forEach(el => {
                    el.style.opacity = '0';
                    el.style.transform = 'translateY(30px)';
                    el.style.transition = 'opacity 0.8s ease-out, transform 0.8s ease-out';
                    observer.observe(el);
                });
            }
        });

        // Fallback: Show all content after 1 second if JavaScript has issues
        setTimeout(() => {
            const hiddenElements = document.querySelectorAll('.reveal-content');
            hiddenElements.forEach(el => {
                if (el.style.opacity === '0') {
                    el.style.opacity = '1';
                    el.style.transform = 'translateY(0)';
                }
            });
        }, 1000);
    </script>
</body>

</html>