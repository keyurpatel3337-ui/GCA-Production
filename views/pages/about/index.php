<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us</title>
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
    <!-- Header/Navbar (Simplified for About Page) -->
    <?php include dirname(__DIR__) . '/include/public-header.php'; ?>

    <!-- About Us Content Section -->
    <section class="relative py-12 md:py-24 overflow-hidden bg-gradient-to-b from-white via-blue-50/30 to-white">
        <!-- Decorative background elements -->
        <div class="absolute top-0 right-0 w-96 h-96 bg-blue-100 rounded-full translate-x-1/2 -translate-y-1/2 opacity-20 animate-float" style="animation-delay: 0s;"></div>
        <div class="absolute bottom-0 left-0 w-64 h-64 bg-indigo-100 rounded-full -translate-x-1/2 translate-y-1/2 opacity-20 animate-float" style="animation-delay: 1s;"></div>
        <div class="absolute top-1/2 right-10 w-40 h-40 bg-sky-100 rounded-full opacity-15 animate-float" style="animation-delay: 2s;"></div>

        <div class="container mx-auto px-4 relative z-10">
            <!-- Page Header -->
            <div class="text-center mb-16 md:mb-24">
                <div class="inline-block px-4 py-1.5 mb-4 bg-blue-50 text-blue-600 rounded-full text-sm font-bold tracking-wide uppercase">
                    Our Legacy
                </div>
                <h1 class="text-4xl md:text-5xl lg:text-7xl font-black mb-6">
                    <span class="bg-clip-text text-transparent bg-gradient-to-r from-blue-600 to-indigo-600">About Our Institution</span>
                </h1>
                <p class="text-gray-500 text-lg md:text-xl max-w-3xl mx-auto">A sanctuary of learning where heritage meets innovation, dedicated to shaping the leaders of tomorrow.</p>
            </div>

            <!-- History Section -->
            <div class="max-w-6xl mx-auto mb-20 md:mb-32">
                <div class="bg-white rounded-[2.5rem] shadow-2xl overflow-hidden border border-blue-50">
                    <div class="flex flex-col lg:flex-row">
                        <div class="lg:w-1/2 relative group overflow-hidden">
                            <img src="../assets/images/gmbuilding.jpg" alt="Our History" class="w-full h-full object-cover transition duration-700 group-hover:scale-110 min-h-[400px]">
                            <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500 flex items-end p-8">
                                <p class="text-white font-medium">Est. 2006</p>
                            </div>
                        </div>
                        <div class="lg:w-1/2 p-8 md:p-12 lg:p-16 flex flex-col justify-center">
                            <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-6 relative">
                                Our History
                                <div class="absolute -bottom-2 left-0 w-12 h-1 bg-blue-600 rounded-full"></div>
                            </h2>
                            <p class="text-gray-700 text-lg leading-relaxed mb-6">
                                Established in June 2006, our institution began with a profound vision: to provide a nurturing and intellectually stimulating environment where students could thrive.
                            </p>
                            <p class="text-gray-700 text-lg leading-relaxed">
                                Over the decades, we have consistently adapted to the evolving educational landscape, integrating modern pedagogical approaches while retaining our core values of discipline, integrity, and social responsibility.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mission & Vision Cards -->
            <div class="max-w-6xl mx-auto mb-20 md:mb-32 grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Mission -->
                <div class="bg-gradient-to-br from-blue-600 to-blue-700 rounded-[2.5rem] p-10 md:p-14 text-white shadow-2xl relative overflow-hidden group">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full translate-x-1/2 -translate-y-1/2 group-hover:scale-150 transition duration-700"></div>
                    <div class="relative z-10">
                        <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center mb-8">
                            <i class="fas fa-bullseye text-3xl"></i>
                        </div>
                        <h2 class="text-3xl md:text-4xl font-bold mb-6">Our Mission</h2>
                        <p class="text-xl leading-relaxed text-blue-50 italic">
                            "To be the center for elevating humanity through Science Success, Spirituality, Nationality and Modern Science and Technology."
                        </p>
                    </div>
                </div>
                <!-- Vision -->
                <div class="bg-gradient-to-br from-indigo-600 to-indigo-700 rounded-[2.5rem] p-10 md:p-14 text-white shadow-2xl relative overflow-hidden group">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full translate-x-1/2 -translate-y-1/2 group-hover:scale-150 transition duration-700"></div>
                    <div class="relative z-10">
                        <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center mb-8">
                            <i class="fas fa-eye text-3xl"></i>
                        </div>
                        <h2 class="text-3xl md:text-4xl font-bold mb-6">Our Vision</h2>
                        <p class="text-xl leading-relaxed text-indigo-50 italic">
                            "To achieve the envisaged vision, we will strive to prepare standard practices and execute them with best practices and all stakeholders will inculcate character to make our vision possible."
                        </p>
                    </div>
                </div>
            </div>

            <!-- Highlights (Quality Policy & GCA) -->
            <div class="max-w-6xl mx-auto mb-20 md:mb-32">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div class="bg-white p-8 md:p-12 rounded-[2.5rem] shadow-xl border border-blue-50 flex flex-col items-center text-center">
                        <div class="w-20 h-20 bg-blue-50 rounded-full flex items-center justify-center mb-6">
                            <i class="fas fa-award text-blue-600 text-3xl"></i>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-4">Quality Policy</h3>
                        <p class="text-gray-600 text-lg italic">"All-round personality development of students."</p>
                    </div>
                    <div class="bg-white p-8 md:p-12 rounded-[2.5rem] shadow-xl border border-indigo-50 flex flex-col items-center text-center">
                        <div class="w-20 h-20 bg-indigo-50 rounded-full flex items-center justify-center mb-6">
                            <i class="fas fa-graduation-cap text-indigo-600 text-3xl"></i>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-4">GCA</h3>
                        <p class="text-gray-600 text-lg">Gyanmanjari Career Academy – Coaching students across Gujarat to secure top national ranks.</p>
                    </div>
                </div>
            </div>

            <!-- Core Values -->
            <div class="max-w-6xl mx-auto">
                <h2 class="text-4xl font-bold text-gray-900 mb-12 text-center">Our Core Values</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <div class="bg-white p-8 rounded-[2rem] shadow-lg border border-gray-100 hover:shadow-2xl hover:-translate-y-2 transition-all duration-300 group">
                        <div class="w-14 h-14 bg-blue-50 rounded-2xl flex items-center justify-center mb-6 mx-auto group-hover:bg-blue-600 transition-colors duration-300">
                            <i class="fas fa-star text-blue-600 group-hover:text-white text-2xl"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-3 text-center">Excellence</h3>
                        <p class="text-gray-500 leading-relaxed text-center">Committed to the highest standards in academics and personal development.</p>
                    </div>
                    <div class="bg-white p-8 rounded-[2rem] shadow-lg border border-gray-100 hover:shadow-2xl hover:-translate-y-2 transition-all duration-300 group">
                        <div class="w-14 h-14 bg-indigo-50 rounded-2xl flex items-center justify-center mb-6 mx-auto group-hover:bg-indigo-600 transition-colors duration-300">
                            <i class="fas fa-handshake text-indigo-600 group-hover:text-white text-2xl"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-3 text-center">Integrity</h3>
                        <p class="text-gray-500 leading-relaxed text-center">Upholding honesty, respect, and ethical conduct in all endeavors.</p>
                    </div>
                    <div class="bg-white p-8 rounded-[2rem] shadow-lg border border-gray-100 hover:shadow-2xl hover:-translate-y-2 transition-all duration-300 group">
                        <div class="w-14 h-14 bg-sky-50 rounded-2xl flex items-center justify-center mb-6 mx-auto group-hover:bg-sky-600 transition-colors duration-300">
                            <i class="fas fa-lightbulb text-sky-600 group-hover:text-white text-2xl"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-3 text-center">Innovation</h3>
                        <p class="text-gray-500 leading-relaxed text-center">Encouraging creativity, critical thinking, and forward-thinking.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>


    <!-- Footer -->
    <!-- <footer class="bg-gray-900 text-gray-300 py-12 rounded-t-lg mx-2 md:mx-4 lg:mx-8">
        <div class="container mx-auto px-4 grid grid-cols-1 md:grid-cols-3 gap-8"> -->
    <!-- About -->
    <!-- <div>
                <h3 class="text-xl font-semibold text-white mb-4">About Us</h3>
                <p class="text-sm leading-relaxed">
                    Committed to providing high-quality education and fostering an environment of growth and learning.
                </p>
            </div> -->

    <!-- Quick Links -->
    <!-- <div>
                <h3 class="text-xl font-semibold text-white mb-4">Quick Links</h3>
                <ul class="space-y-2">
                    <li><a href="index.html" class="hover:text-white transition duration-300 ease-in-out text-sm">Home</a></li>
                    <li><a href="about-us.html" class="hover:text-white transition duration-300 ease-in-out text-sm">About Us</a></li>
                    <li><a href="#" class="hover:text-white transition duration-300 ease-in-out text-sm">Academics</a></li>
                    <li><a href="#" class="hover:text-white transition duration-300 ease-in-out text-sm">Contact Us</a></li>
                </ul>
            </div> -->

    <!-- Contact Info -->
    <!-- <div>
                <h3 class="text-xl font-semibold text-white mb-4">Contact Info</h3>
                <p class="text-sm">
                    123 Institution Lane,<br>
                    City, State 12345, Country<br>
                    Email: info@example.com<br>
                    Phone: +1 (123) 456-7890
                </p>
                <div class="flex space-x-4 mt-4">
                    <a href="#" class="text-gray-400 hover:text-white transition duration-300 ease-in-out"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="text-gray-400 hover:text-white transition duration-300 ease-in-out"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="text-gray-400 hover:text-white transition duration-300 ease-in-out"><i class="fab fa-linkedin-in"></i></a>
                    <a href="#" class="text-gray-400 hover:text-white transition duration-300 ease-in-out"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
        </div>
        <div class="border-t border-gray-700 mt-8 pt-8 text-center text-sm">
            &copy; 2023 Our Institution. All rights reserved.
        </div>
    </footer> -->
    <?php include dirname(__DIR__) . '/include/public-footer.php'; ?>

</body>

</html>