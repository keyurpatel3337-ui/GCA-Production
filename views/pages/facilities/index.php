<?php require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once ENV_CONFIG_FILE; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Our Facilities - Gyanmanjari Vidyapith</title>
    <link rel="icon" type="image/x-icon" href="../assets/images/logogmn.png">
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Link to external CSS file -->
    <link rel="stylesheet" href="../assets/css/gm-public.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .active-tab {
            background: linear-gradient(135deg, #2563eb, #4f46e5);
            color: white !important;
            box-shadow: 0 10px 15px -3px rgba(37, 99, 235, 0.4);
            transform: translateY(-2px);
        }
    </style>
</head>

<body class="text-gray-800">
    <!-- Header/Navbar -->
    <?php include __DIR__ . '/../../../include/public-header.php'; ?>

    <!-- Facilities Index Content -->
    <section class="relative py-12 md:py-24 overflow-hidden bg-gradient-to-b from-white via-blue-50/30 to-white">
        <!-- Decorative background elements -->
        <div class="absolute top-0 right-0 w-96 h-96 bg-blue-100 rounded-full translate-x-1/2 -translate-y-1/2 opacity-20 animate-float" style="animation-delay: 0s;"></div>
        <div class="absolute bottom-0 left-0 w-64 h-64 bg-indigo-100 rounded-full -translate-x-1/2 translate-y-1/2 opacity-20 animate-float" style="animation-delay: 1s;"></div>

        <div class="container mx-auto px-4 relative z-10">
            <!-- Page Header -->
            <div class="text-center mb-12 md:mb-20">
                <div class="inline-block px-4 py-1.5 mb-4 bg-blue-50 text-blue-600 rounded-full text-sm font-bold tracking-wide uppercase">
                    World-Class Infrastructure
                </div>
                <h1 class="text-4xl md:text-5xl lg:text-7xl font-black mb-6">
                    <span class="bg-clip-text text-transparent bg-gradient-to-r from-blue-600 to-indigo-600">Our Facilities</span>
                </h1>
                <p class="text-gray-500 text-lg md:text-xl max-w-2xl mx-auto px-4">Experience an environment designed to nurture talent and foster innovation at every step.</p>
            </div>

            <!-- Facilities Explorer Interface -->
            <div class="max-w-6xl mx-auto">
                <div class="bg-white rounded-[2.5rem] shadow-2xl overflow-hidden border border-blue-50 p-6 md:p-10">
                    <!-- Tab Buttons Grid -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-10 md:mb-16">
                        <button class="facility-btn active-tab p-4 rounded-2xl border border-gray-100 flex flex-col items-center gap-3 transition-all duration-300 hover:border-blue-200" data-content="computer_lab" data-url="computer-lab.php">
                            <i class="fas fa-desktop text-2xl"></i>
                            <span class="font-bold text-sm">Computer Lab</span>
                        </button>
                        <button class="facility-btn bg-white p-4 rounded-2xl border border-gray-100 flex flex-col items-center gap-3 transition-all duration-300 hover:border-blue-200" data-content="library" data-url="library.php">
                            <i class="fas fa-book text-2xl"></i>
                            <span class="font-bold text-sm">Library</span>
                        </button>
                        <button class="facility-btn bg-white p-4 rounded-2xl border border-gray-100 flex flex-col items-center gap-3 transition-all duration-300 hover:border-blue-200" data-content="lab_facility" data-url="lab-facility.php">
                            <i class="fas fa-flask text-2xl"></i>
                            <span class="font-bold text-sm">Science Labs</span>
                        </button>
                        <button class="facility-btn bg-white p-4 rounded-2xl border border-gray-100 flex flex-col items-center gap-3 transition-all duration-300 hover:border-blue-200" data-content="playground" data-url="playground.php">
                            <i class="fas fa-running text-2xl"></i>
                            <span class="font-bold text-sm">Playground</span>
                        </button>
                        <button class="facility-btn bg-white p-4 rounded-2xl border border-gray-100 flex flex-col items-center gap-3 transition-all duration-300 hover:border-blue-200" data-content="solution_desk" data-url="solution-desk.php">
                            <i class="fas fa-headset text-2xl"></i>
                            <span class="font-bold text-sm">Solution Desk</span>
                        </button>
                        <button class="facility-btn bg-white p-4 rounded-2xl border border-gray-100 flex flex-col items-center gap-3 transition-all duration-300 hover:border-blue-200" data-content="dining_hall" data-url="dining-hall.php">
                            <i class="fas fa-utensils text-2xl"></i>
                            <span class="font-bold text-sm">Dining Hall</span>
                        </button>
                        <button class="facility-btn bg-white p-4 rounded-2xl border border-gray-100 flex flex-col items-center gap-3 transition-all duration-300 hover:border-blue-200" data-content="counseling" data-url="counselling.php">
                            <i class="fas fa-user-friends text-2xl"></i>
                            <span class="font-bold text-sm">Counseling</span>
                        </button>
                        <button class="facility-btn bg-white p-4 rounded-2xl border border-gray-100 flex flex-col items-center gap-3 transition-all duration-300 hover:border-blue-200" data-content="security" data-url="safety-security.php">
                            <i class="fas fa-shield-alt text-2xl"></i>
                            <span class="font-bold text-sm">Security</span>
                        </button>
                    </div>

                    <!-- Dynamic Content Area -->
                    <div id="facility-display" class="flex flex-col lg:flex-row gap-8 items-center animate-in fade-in slide-in-from-bottom-4 duration-500">
                        <div class="lg:w-1/2">
                            <div class="relative group">
                                <div class="absolute -inset-1 bg-gradient-to-r from-blue-600 to-indigo-600 rounded-3xl opacity-20 blur transition duration-300"></div>
                                <img id="facility-image" src="../assets/images/computerlab.JPG" alt="Facility Image" class="relative rounded-3xl w-full h-80 object-cover shadow-xl">
                            </div>
                        </div>
                        <div class="lg:w-1/2 p-4 md:p-8">
                            <h2 id="facility-title" class="text-3xl font-bold text-gray-900 mb-6">Computer Lab</h2>
                            <p id="facility-text" class="text-gray-600 text-lg leading-relaxed mb-8">
                                Now a-days computer has become a necessity for every child in today's world. And this is what Gyanmanjari wants their students to grow with. Computer in GM Bhavnagar is fun as we impart education through interactive techniques using computer CDs, LCD Projectors and Multimedia.
                            </p>
                            <a id="facility-link" href="computer-lab.php" class="inline-flex items-center space-x-2 bg-blue-600 text-white font-bold py-3 px-8 rounded-full shadow-lg hover:bg-blue-700 transition duration-300 transform hover:scale-105">
                                <span>Explore Details</span>
                                <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <?php include __DIR__ . '/../../../include/public-footer.php'; ?>

    <script>
        const facilityData = {
            computer_lab: {
                title: "Computer Lab",
                image: "../assets/images/computerlab.JPG",
                text: "Now a-days computer has become a necessity for every child in today's world. Computer education at GM Bhavnagar is interactive and fun, utilizing advanced multimedia tools.",
                url: "computer-lab.php"
            },
            library: {
                title: "Library",
                image: "../assets/images/lib.JPG",
                text: "Our library features partially isolated self-study tables, providing a perfect environment for 11th and 12th Science students to focus during their study hours.",
                url: "library.php"
            },
            lab_facility: {
                title: "Science Labs",
                image: "../assets/images/physiclab.jpg",
                text: "Practicals are the backbone of science. We provide state-of-the-art laboratories for Physics, Chemistry, Biology, and Maths, guided by expert assistants.",
                url: "lab-facility.php"
            },
            playground: {
                title: "Playground",
                image: "../assets/images/playground.JPG",
                text: "Health is Wealth. Our expansive playground supports training in Athletics, Basketball, Cricket, Football, Skating, Karate, and more under expert guidance.",
                url: "playground.php"
            },
            solution_desk: {
                title: "Solution Desk",
                image: "../assets/images/slu.jpg",
                text: "Our Difficulty Solution Desk is a unique system designed to help students overcome their fears and solve academic queries in a personalized manner.",
                url: "solution-desk.php"
            },
            dining_hall: {
                title: "Dining Hall",
                image: "../assets/images/dininghall.jpg",
                text: "A huge, hygienic dining facility providing well-planned meals. Here, trustees, teachers, and students all share the same nutritious food together.",
                url: "dining-hall.php"
            },
            counseling: {
                title: "Counseling",
                image: "../assets/images/counseling.JPG",
                text: "We provide professional psychological support and academic guidance based on modern theories of motivation and personal development.",
                url: "counselling.php"
            },
            security: {
                title: "Safety & Security",
                image: "../assets/images/shopping.webp",
                text: "Uncompromised security with 24/7 CCTV surveillance, fire safety systems, and well-trained security personnel ensuring a safe campus environment.",
                url: "safety-security.php"
            }
        };

        $('.facility-btn').click(function () {
            $('.facility-btn').removeClass('active-tab bg-white').addClass('bg-white');
            $(this).removeClass('bg-white').addClass('active-tab');

            const key = $(this).data('content');
            const data = facilityData[key];

            $('#facility-display').fadeOut(200, function() {
                $('#facility-title').text(data.title);
                $('#facility-text').text(data.text);
                $('#facility-image').attr('src', data.image);
                $('#facility-link').attr('href', data.url);
                $(this).fadeIn(200);
            });
        });
    </script>
</body>

</html>