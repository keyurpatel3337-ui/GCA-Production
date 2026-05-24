<?php require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once ENV_CONFIG_FILE; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advisory Committee - Gyanmanjari Vidyapith</title>
    <link rel="icon" type="image/x-icon" href="../assets/images/logogmn.png">
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- AOS Animation Library -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <!-- Link to external CSS file -->
    <link rel="stylesheet" href="../assets/css/gm-public.css">
    
</head>

<body class="text-gray-800">
    <!-- Header/Navbar -->
    <?php include __DIR__ . '/../../../include/public-header.php'; ?>

    <!-- Advisory Committee Content Section -->
    <section class="relative py-16 md:py-24 overflow-hidden bg-gradient-to-br from-blue-50 via-indigo-50 to-purple-50">
        <!-- Animated Background Bubbles -->
        <div class="absolute inset-0 pointer-events-none overflow-hidden">
            <div class="absolute top-10 left-10 w-72 h-72 bg-blue-400/10 rounded-full blur-3xl animate-float css-advisory-committee-f165c4"></div>
            <div class="absolute top-1/4 right-20 w-96 h-96 bg-indigo-400/10 rounded-full blur-3xl animate-float css-advisory-committee-bd845f"></div>
            <div class="absolute bottom-20 left-1/4 w-80 h-80 bg-purple-400/10 rounded-full blur-3xl animate-float css-advisory-committee-cffe64"></div>
            <div class="absolute top-1/2 right-1/3 w-64 h-64 bg-blue-300/10 rounded-full blur-3xl animate-float css-advisory-committee-6da30b"></div>
            <div class="absolute bottom-1/4 right-10 w-72 h-72 bg-indigo-300/10 rounded-full blur-3xl animate-float css-advisory-committee-fb66aa"></div>
            <div class="absolute top-3/4 left-1/2 w-56 h-56 bg-purple-300/10 rounded-full blur-3xl animate-float css-advisory-committee-c84216"></div>
        </div>

        <div class="container mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <!-- Section Title -->
            <div class="text-center mb-16 reveal-content">
                <h1 class="text-4xl sm:text-5xl md:text-6xl font-black mb-4">
                    <span class="bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-600 bg-clip-text text-transparent">Meet Our</span>
                </h1>
                <h2 class="text-3xl sm:text-4xl md:text-5xl font-black">
                    <span class="bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent">Advisory Committee</span>
                </h2>
                <div class="w-24 h-1 bg-gradient-to-r from-blue-600 to-purple-600 mx-auto mt-6 rounded-full"></div>
            </div>

            <!-- Advisor 1 - Chemistry Advisor (Blue Theme) -->
            <div class="max-w-6xl mx-auto mb-16 reveal-content">
                <div class="relative bg-white/70 backdrop-blur-xl rounded-[2.5rem] shadow-2xl overflow-hidden border border-white/20">
                    <!-- Gradient Background -->
                    <div class="absolute inset-0 bg-gradient-to-br from-blue-500/5 via-indigo-500/5 to-blue-500/5"></div>

                    <!-- Decorative Bubbles inside Card -->
                    <div class="absolute top-10 right-10 w-32 h-32 bg-blue-400/10 rounded-full blur-2xl animate-float css-advisory-committee-623c98"></div>
                    <div class="absolute bottom-20 left-10 w-24 h-24 bg-indigo-400/10 rounded-full blur-2xl animate-float css-advisory-committee-6da30b"></div>
                    <div class="absolute top-1/2 right-1/4 w-20 h-20 bg-blue-300/10 rounded-full blur-2xl animate-float css-advisory-committee-cffe64"></div>
                    <div class="absolute bottom-10 right-20 w-16 h-16 bg-indigo-300/10 rounded-full blur-2xl animate-float css-advisory-committee-fb66aa"></div>

                    <div class="relative p-8 md:p-12 lg:p-16">
                        <div class="flex flex-col lg:flex-row items-center gap-12">
                            <!-- Advisor Image -->
                            <div class="lg:w-2/5 text-center lg:text-left flex-shrink-0">
                                <div class="relative inline-block">
                                    <div class="absolute inset-0 bg-gradient-to-r from-blue-500 to-indigo-600 rounded-full blur-2xl opacity-30 animate-pulse"></div>
                                    <img src="../assets/images/avinashsir.jpeg"
                                        alt="Avinash Patel - Chemistry Advisor"
                                        class="relative rounded-full w-56 h-56 md:w-72 md:h-72 object-cover object-center shadow-2xl border-4 border-white ring-4 ring-blue-500/50 mx-auto">
                                </div>
                                <div class="mt-8">
                                    <h3 class="text-3xl md:text-4xl font-bold">
                                        <span class="bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent">Avinash Patel</span>
                                    </h3>
                                    <p class="text-xl md:text-2xl font-semibold text-blue-600 mt-2">Chemistry Advisor</p>
                                    <div class="flex justify-center lg:justify-start gap-3 mt-4">
                                        <span class="px-4 py-2 bg-gradient-to-r from-blue-500 to-indigo-600 text-white rounded-full text-sm font-medium shadow-lg">
                                            <i class="fas fa-flask mr-2"></i>Science Expert
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Advisor Quote -->
                            <div class="lg:w-3/5">
                                <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-3xl p-6 md:p-8 border border-blue-200/50 shadow-lg">
                                    <div class="flex items-start gap-4">
                                        <div class="flex-shrink-0 w-12 h-12 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center shadow-lg">
                                            <i class="fas fa-quote-left text-white text-xl"></i>
                                        </div>
                                        <div class="flex-1">
                                            <h4 class="text-xl md:text-2xl font-bold mb-4">
                                                <span class="bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent">Vision for Science Education</span>
                                            </h4>
                                            <p class="text-gray-700 leading-relaxed text-base md:text-lg mb-4">
                                                The study of science gives sharp vision of life. It also strengthens your mind-power as well as your career.
                                            </p>
                                            <div class="flex items-center gap-2 text-blue-600 font-medium">
                                                <i class="fas fa-atom"></i>
                                                <span class="text-sm">Empowering minds through scientific inquiry</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Advisor 2 - Biology Advisor (Purple Theme) -->
            <div class="max-w-6xl mx-auto reveal-content">
                <div class="relative bg-white/70 backdrop-blur-xl rounded-[2.5rem] shadow-2xl overflow-hidden border border-white/20">
                    <!-- Gradient Background -->
                    <div class="absolute inset-0 bg-gradient-to-br from-purple-500/5 via-indigo-500/5 to-purple-500/5"></div>

                    <!-- Decorative Bubbles inside Card -->
                    <div class="absolute top-10 left-10 w-32 h-32 bg-purple-400/10 rounded-full blur-2xl animate-float css-advisory-committee-623c98"></div>
                    <div class="absolute bottom-20 right-10 w-24 h-24 bg-indigo-400/10 rounded-full blur-2xl animate-float css-advisory-committee-6da30b"></div>
                    <div class="absolute top-1/2 left-1/4 w-20 h-20 bg-purple-300/10 rounded-full blur-2xl animate-float css-advisory-committee-cffe64"></div>
                    <div class="absolute bottom-10 left-20 w-16 h-16 bg-indigo-300/10 rounded-full blur-2xl animate-float css-advisory-committee-fb66aa"></div>

                    <div class="relative p-8 md:p-12 lg:p-16">
                        <div class="flex flex-col lg:flex-row-reverse items-center gap-12">
                            <!-- Advisor Image -->
                            <div class="lg:w-2/5 text-center lg:text-right flex-shrink-0">
                                <div class="relative inline-block">
                                    <div class="absolute inset-0 bg-gradient-to-r from-purple-500 to-indigo-600 rounded-full blur-2xl opacity-30 animate-pulse"></div>
                                    <img src="../assets/images/umangsir.JPG"
                                        alt="Umang Andharia - Biology Advisor"
                                        class="relative rounded-full w-56 h-56 md:w-72 md:h-72 object-cover object-center shadow-2xl border-4 border-white ring-4 ring-purple-500/50 mx-auto">
                                </div>
                                <div class="mt-8">
                                    <h3 class="text-3xl md:text-4xl font-bold">
                                        <span class="bg-gradient-to-r from-purple-600 to-indigo-600 bg-clip-text text-transparent">Umang Andharia</span>
                                    </h3>
                                    <p class="text-xl md:text-2xl font-semibold text-purple-600 mt-2">Biology Advisor</p>
                                    <div class="flex justify-center lg:justify-end gap-3 mt-4">
                                        <span class="px-4 py-2 bg-gradient-to-r from-purple-500 to-indigo-600 text-white rounded-full text-sm font-medium shadow-lg">
                                            <i class="fas fa-dna mr-2"></i>Life Sciences Expert
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Advisor Quote -->
                            <div class="lg:w-3/5">
                                <div class="bg-gradient-to-br from-purple-50 to-indigo-50 rounded-3xl p-6 md:p-8 border border-purple-200/50 shadow-lg">
                                    <div class="flex items-start gap-4">
                                        <div class="flex-shrink-0 w-12 h-12 bg-gradient-to-br from-purple-500 to-indigo-600 rounded-xl flex items-center justify-center shadow-lg">
                                            <i class="fas fa-quote-left text-white text-xl"></i>
                                        </div>
                                        <div class="flex-1">
                                            <h4 class="text-xl md:text-2xl font-bold mb-4">
                                                <span class="bg-gradient-to-r from-purple-600 to-indigo-600 bg-clip-text text-transparent">Philosophy of Biology</span>
                                            </h4>
                                            <p class="text-gray-700 leading-relaxed text-base md:text-lg mb-3">
                                                Through the knowledge of BIOLOGY we can save our society from ANDHASHRADDHA and fatal diseases like Cancer, AIDS, high blood pressure, diabetes etc. and many more.
                                            </p>
                                            <p class="text-gray-700 leading-relaxed text-base md:text-lg mb-4">
                                                BIOLOGY is the best way to know you and your surroundings. There is no one in the world like you. You are unique. Believe in you. Every student should put his best efforts to succeed in life.
                                            </p>
                                            <div class="flex items-center gap-2 text-purple-600 font-medium">
                                                <i class="fas fa-heartbeat"></i>
                                                <span class="text-sm">Understanding life, empowering students</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mission Statement -->
            <div class="max-w-5xl mx-auto mt-16 reveal-content">
                <div class="relative bg-gradient-to-br from-blue-600 via-indigo-600 to-purple-600 rounded-[2.5rem] p-8 md:p-12 shadow-2xl overflow-hidden">
                    <!-- Decorative Bubbles -->
                    <div class="absolute top-5 right-10 w-40 h-40 bg-white/10 rounded-full blur-2xl animate-float css-advisory-committee-f165c4"></div>
                    <div class="absolute bottom-10 left-10 w-32 h-32 bg-white/10 rounded-full blur-2xl animate-float css-advisory-committee-bd845f"></div>
                    <div class="absolute top-1/2 right-1/4 w-24 h-24 bg-white/10 rounded-full blur-2xl animate-float css-advisory-committee-6da30b"></div>
                    <div class="absolute bottom-5 right-20 w-48 h-48 bg-white/10 rounded-full blur-2xl animate-float css-advisory-committee-cffe64"></div>
                    <div class="absolute top-10 left-1/3 w-56 h-56 bg-white/10 rounded-full blur-2xl animate-float css-advisory-committee-fb66aa"></div>

                    <div class="relative z-10">
                        <div class="flex items-center justify-center gap-4 mb-6">
                            <div class="w-16 h-16 bg-white/20 backdrop-blur-sm rounded-2xl flex items-center justify-center shadow-lg">
                                <i class="fas fa-users text-white text-2xl"></i>
                            </div>
                            <h3 class="text-3xl md:text-4xl font-black text-white">Our Advisory Mission</h3>
                        </div>
                        <p class="text-white/95 text-lg md:text-xl leading-relaxed text-center max-w-4xl mx-auto">
                            Our advisory committee brings together expertise from chemistry and biology to guide students towards academic excellence and scientific discovery. Through their dedicated mentorship, we empower every student to unlock their potential and contribute meaningfully to society through the power of science.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <?php include __DIR__ . '/../../../include/public-footer.php'; ?>

    <!-- AOS Animation Library -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
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