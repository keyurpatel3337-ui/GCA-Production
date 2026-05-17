<?php
require_once __DIR__ . '/common/global_firewall.php';
ob_start(); // Start buffering

// Include request sanitizer first - Auto-sanitizes all $_GET, $_POST, $_COOKIE
require_once __DIR__ . '/portal/common/request-sanitizer.php';

// Load constants first
require_once __DIR__ . '/common/constants.php';

// Load environment configuration
require_once ENV_CONFIG_FILE;

// Clean any whitespace emitted by env.config.php or others
if (ob_get_length()) {
  ob_end_clean();
}
ob_start(); // Restart buffering

require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;

// Initialize Database Operations
$dbOps = new DatabaseOperations();

// Fetch Hero Slides - Using Operation.php
try {
  $heroSlides = $dbOps->select(
    'tbl_website_hero',
  ['*'],
  ['is_active' => 1],
    'display_order ASC'
  );
  if ($heroSlides === false) {
    $heroSlides = [];
  }
}
catch (Exception $e) {
  $heroSlides = [];
}

// Fetch Testimonials - Using Operation.php
try {
  $dbTestimonials = $dbOps->select(
    'tbl_website_testimonials',
  ['*'],
  ['is_active' => 1],
    'display_order ASC'
  );
  if ($dbTestimonials === false) {
    $dbTestimonials = [];
  }
}
catch (Exception $e) {
  $dbTestimonials = [];
}

// Fetch Gallery - Using Operation.php
try {
  $dbGallery = $dbOps->select(
    'tbl_website_gallery',
  ['*'],
  ['is_active' => 1],
    'display_order ASC'
  );
  if ($dbGallery === false) {
    $dbGallery = [];
  }
}
catch (Exception $e) {
  $dbGallery = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>GCA</title>
  <link rel="icon" type="image/x-icon" href="<?php echo BASE_URL; ?>/assets/images/logogmn.png">
  <!-- Tailwind CSS -->
  <script src="<?php echo BASE_URL; ?>/assets/vendor/tailwind/tailwind.min.js"></script>
  <!-- Font Awesome -->
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/vendor/font-awesome/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <!-- Swiper CSS -->
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/vendor/swiper/swiper-bundle.min.css" />
  <!-- AOS CSS -->
  <link href="<?php echo BASE_URL; ?>/assets/vendor/aos/aos.css" rel="stylesheet">

  <!-- Link to external CSS file -->
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/gm-public.css">
</head>

<body class="bg-gray-50">
  <!-- Header/Navbar -->
  <?php include __DIR__ . '/include/public-header.php'; ?>

  <!-- Hero Section -->
  <section class="relative overflow-hidden mt-4 md:mt-8 mx-4 md:mx-8 lg:mx-12 rounded-3xl shadow-xl">
    <div class="swiper heroSwiper h-[400px] md:h-[600px]">
      <div class="swiper-wrapper">
        <?php if (!empty($heroSlides)): ?>
          <?php foreach ($heroSlides as $slide): ?>
            <div class="swiper-slide relative">
              <img src="<?php echo htmlspecialchars($slide['image_path'] ?? ''); ?>"
                alt="<?php echo htmlspecialchars($slide['title'] ?? ''); ?>" class="w-full h-full object-cover">
              <?php if ($slide['title'] || $slide['subtitle']): ?>
                <div class="absolute inset-0 bg-black/30 flex items-center justify-center">
                  <div class="text-center text-white px-4">
                    <?php if ($slide['title']): ?>
                      <h1 class="text-3xl md:text-6xl font-bold mb-4 char-animate">
                        <?php echo htmlspecialchars($slide['title'] ?? ''); ?>
                      </h1>
                    <?php
      endif; ?>
                    <?php if ($slide['subtitle']): ?>
                      <p class="text-base md:text-xl mb-8 reveal-content">
                        <?php echo htmlspecialchars($slide['subtitle'] ?? ''); ?>
                      </p>
                    <?php
      endif; ?>
                    <?php if ($slide['button_text'] && $slide['button_link']): ?>
                      <a href="<?php echo htmlspecialchars($slide['button_link'] ?? ''); ?>"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-6 md:px-8 py-2 md:py-3 rounded-full font-semibold transition duration-300 text-sm md:text-base reveal-content"><?php echo htmlspecialchars($slide['button_text'] ?? ''); ?></a>
                    <?php
      endif; ?>
                  </div>
                </div>
              <?php
    endif; ?>
            </div>
          <?php
  endforeach; ?>
        <?php
else: ?>
          <!-- Default Slides if DB is empty -->
          <?php
  require_once __DIR__ . '/include/cms-helper.php';
  $cms_data = get_cms_content('index');
?>
          <div class="swiper-slide relative">
            <img src="assets/images/612 web final.jpg" alt="Slide 1" class="w-full h-full object-cover">
            <div class="absolute inset-0 bg-black/30 flex items-center justify-center">
              <div class="text-center text-white px-4">
                <h1 class="text-3xl md:text-6xl font-bold mb-4 char-animate">
                  <?php echo cms_field('hero_title', 'Excellence in Education', 'span'); ?>
                </h1>
                <p class="text-base md:text-xl mb-8 reveal-content">
                  <?php echo cms_field('hero_subtitle', 'Empowering the next generation of leaders', 'span'); ?>
                </p>
                <a href="11Reg.php"
                  class="bg-blue-600 hover:bg-blue-700 text-white px-6 md:px-8 py-2 md:py-3 rounded-full font-semibold transition duration-300 text-sm md:text-base reveal-content">
                  <?php echo cms_field('hero_cta_text', 'Apply Now', 'span'); ?>
                </a>
              </div>
            </div>
          </div>
          <div class="swiper-slide"><img src="assets/images/WEB-Slide-neet.jpg" alt="Slide 2"
              class="w-full h-full object-cover"></div>
          <div class="swiper-slide"><img src="assets/images/WEB-Slide-jee-mains-2024.jpg" alt="Slide 3"
              class="w-full h-full object-cover"></div>
          <div class="swiper-slide"><img src="assets/images/A GROUP  web.jpg" alt="Slide 4"
              class="w-full h-full object-cover"></div>
          <div class="swiper-slide"><img src="assets/images/gm-building.jpg" alt="Slide 5"
              class="w-full h-full object-cover"></div>
          <div class="swiper-slide"><img src="assets/images/neet2025web.jpg" alt="Slide 8"
              class="w-full h-full object-cover"></div>
          <div class="swiper-slide"><img src="assets/images/WEB SLIDE.jpg" alt="Slide 9"
              class="w-full h-full object-cover"></div>
        <?php
endif; ?>
      </div>
      <div class="swiper-button-next !text-white"></div>
      <div class="swiper-button-prev !text-white"></div>
      <div class="swiper-pagination"></div>
    </div>
  </section>

  <!-- Stats Section -->
  <section
    class="py-6 md:py-12 glass-glossy relative z-20 -mt-10 md:-mt-16 mx-4 md:mx-10 lg:mx-20 rounded-2xl shadow-xl">
    <div class="container mx-auto px-3 md:px-4">
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-8 text-center">
        <div class="reveal-content">
          <div class="text-2xl md:text-3xl lg:text-4xl font-bold text-blue-600 mb-1 md:mb-2 drop-shadow-md"><span
              class="stat-number" data-target="18">0</span>+</div>
          <div class="text-gray-800 font-semibold text-xs md:text-sm lg:text-base leading-tight">Years of Excellence
          </div>
        </div>
        <div class="reveal-content">
          <div class="text-2xl md:text-3xl lg:text-4xl font-bold text-blue-600 mb-1 md:mb-2 drop-shadow-md"><span
              class="stat-number" data-target="5000">0</span>+</div>
          <div class="text-gray-800 font-semibold text-xs md:text-sm lg:text-base leading-tight">Students Enrolled</div>
        </div>
        <div class="reveal-content">
          <div class="text-2xl md:text-3xl lg:text-4xl font-bold text-blue-600 mb-1 md:mb-2 drop-shadow-md"><span
              class="stat-number" data-target="200">0</span>+</div>
          <div class="text-gray-800 font-semibold text-xs md:text-sm lg:text-base leading-tight">Expert Faculty</div>
        </div>
        <div class="reveal-content">
          <div class="text-2xl md:text-3xl lg:text-4xl font-bold text-blue-600 mb-1 md:mb-2 drop-shadow-md"><span
              class="stat-number" data-target="50">0</span>+</div>
          <div class="text-gray-800 font-semibold text-xs md:text-sm lg:text-base leading-tight">Awards Won</div>
        </div>
      </div>
    </div>
  </section>

  <!-- About Us Section -->
  <section class="py-12 md:py-24 overflow-hidden bg-gradient-to-b from-white to-blue-50/30">
    <div class="container mx-auto px-4">
      <div class="flex flex-col lg:flex-row items-center gap-12 lg:gap-20">
        <!-- Image Side -->
        <div class="w-full lg:w-1/2 relative group">
          <div
            class="absolute -top-6 -left-6 w-full h-full border-2 border-blue-100 rounded-3xl -z-10 transition-all duration-500 group-hover:top-0 group-hover:left-0">
          </div>
          <div class="relative rounded-3xl overflow-hidden shadow-2xl reveal-content">
            <img src="assets/images/gmbuilding.jpg" alt="About Us" class="w-full object-cover h-[350px] md:h-[500px]">
            <div
              class="absolute inset-0 bg-gradient-to-t from-black/40 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500">
            </div>
          </div>
          <!-- Floating Badge -->
          <div
            class="absolute -bottom-8 -right-4 md:-right-8 bg-blue-600/80 backdrop-blur-xl p-6 md:p-8 rounded-2xl hidden md:block border border-white/30 shadow-2xl animate-float z-20 reveal-content">
            <div class="text-center">
              <p class="text-3xl md:text-4xl font-extrabold text-white mb-1">18+</p>
              <p class="text-xs md:text-sm text-blue-100 font-bold uppercase tracking-wider">Years of Legacy</p>
            </div>
          </div>
        </div>

        <!-- Content Side -->
        <div class="w-full lg:w-1/2 reveal-content mt-8 lg:mt-0">
          <div
            class="inline-block px-3 md:px-4 py-1 md:py-1.5 mb-3 md:mb-4 bg-blue-50 text-blue-600 rounded-full text-xs md:text-sm font-bold tracking-wide uppercase">
            Since 2006
          </div>
          <h2 class="text-3xl md:text-4xl lg:text-5xl font-black mb-4 md:mb-6 char-animate">
            <span class="bg-clip-text text-transparent bg-gradient-to-r from-blue-600 to-indigo-600">About Our
              Institution</span>
          </h2>

          <div class="space-y-4 md:space-y-6 mb-8 md:mb-10">
            <p class="text-gray-600 text-base md:text-lg leading-relaxed reveal-content">
              In June 2006, <span class="text-blue-600 font-bold">Gyanmanjari Vidyapith</span> was established with a
              visionary goal: to empower the youth through scientific thinking and traditional values.
            </p>
            <p class="text-gray-600 text-base md:text-lg leading-relaxed reveal-content">
              We blend the ancient <span class="italic font-medium text-gray-800">Ashram education system</span> with
              modern pedagogical excellence, creating an environment where students achieve peak academic results while
              building strong character.
            </p>
          </div>

          <!-- Feature Highlights -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 md:gap-6 mb-8 md:mb-10 reveal-content">
            <div
              class="flex items-start gap-3 md:gap-4 p-3 md:p-4 rounded-xl md:rounded-2xl bg-white shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
              <div
                class="w-10 h-10 md:w-12 md:h-12 rounded-lg md:rounded-xl bg-blue-100 flex items-center justify-center text-blue-600 shrink-0">
                <i class="fas fa-graduation-cap text-xl"></i>
              </div>
              <div>
                <h4 class="font-bold text-gray-900 text-sm md:text-base">Expert Mentorship</h4>
                <p class="text-xs md:text-sm text-gray-500">Guidance from industry veterans</p>
              </div>
            </div>
            <div
              class="flex items-start gap-3 md:gap-4 p-3 md:p-4 rounded-xl md:rounded-2xl bg-white shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
              <div
                class="w-10 h-10 md:w-12 md:h-12 rounded-lg md:rounded-xl bg-indigo-100 flex items-center justify-center text-indigo-600 shrink-0">
                <i class="fas fa-microscope text-xl"></i>
              </div>
              <div>
                <h4 class="font-bold text-gray-900 text-sm md:text-base">Modern Labs</h4>
                <p class="text-xs md:text-sm text-gray-500">State-of-the-art research facilities</p>
              </div>
            </div>
          </div>

          <div class="flex flex-wrap items-center gap-4 md:gap-6 reveal-content">
            <a href="about/index.php"
              class="group inline-flex items-center bg-blue-600 text-white font-bold px-6 md:px-8 py-3 md:py-4 rounded-xl md:rounded-2xl text-sm md:text-base hover:bg-blue-700 transition-all duration-300 shadow-lg hover:shadow-blue-200">
              Explore Our Story
              <svg class="w-4 h-4 md:w-5 md:h-5 ml-2 transform group-hover:translate-x-1 transition-transform"
                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3">
                </path>
              </svg>
            </a>
            <div class="flex -space-x-2 md:-space-x-3">
              <img class="w-8 h-8 md:w-10 md:h-10 rounded-full border-2 border-white" src="<?php echo BASE_URL; ?>/assets/images/1.1.png"
                alt="User">
              <img class="w-8 h-8 md:w-10 md:h-10 rounded-full border-2 border-white" src="<?php echo BASE_URL; ?>/assets/images/1.2.png"
                alt="User">
              <img class="w-8 h-8 md:w-10 md:h-10 rounded-full border-2 border-white" src="<?php echo BASE_URL; ?>/assets/images/1.3.png"
                alt="User">
              <div
                class="w-8 h-8 md:w-10 md:h-10 rounded-full border-2 border-white bg-gray-100 flex items-center justify-center text-[9px] md:text-[10px] font-bold text-gray-600">
                +5k</div>
            </div>
            <p class="text-xs md:text-sm text-gray-500 font-medium">Trusted by <span
                class="text-blue-600 font-bold">5000+</span> Students</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Programs/Academics Section -->
  <section class="py-12 md:py-24 bg-white relative overflow-hidden">
    <!-- Decorative background elements -->
    <div class="absolute top-0 left-0 w-64 h-64 bg-blue-50 rounded-full -translate-x-1/2 -translate-y-1/2 opacity-50">
    </div>
    <div
      class="absolute bottom-0 right-0 w-96 h-96 bg-indigo-50 rounded-full translate-x-1/3 translate-y-1/3 opacity-50">
    </div>

    <div class="container mx-auto px-4 relative z-10">
      <div class="text-center mb-12 md:mb-16">
        <div
          class="inline-block px-3 md:px-4 py-1 md:py-1.5 mb-3 md:mb-4 bg-indigo-50 text-indigo-600 rounded-full text-xs md:text-sm font-bold tracking-wide uppercase reveal-content">
          Academic Excellence
        </div>
        <h2 class="text-3xl md:text-4xl lg:text-5xl font-black mb-3 md:mb-4 char-animate">
          <span class="bg-clip-text text-transparent bg-gradient-to-r from-blue-600 to-indigo-600">Our Academic
            Programs</span>
        </h2>
        <p class="text-gray-500 text-sm md:text-base max-w-2xl mx-auto reveal-content px-4">Tailored educational paths
          designed to nurture talent and achieve academic milestones.</p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 md:gap-8 lg:gap-8">
        <!-- Program Card 1 -->
        <div
          class="group bg-white p-6 md:p-8 rounded-[2rem] border border-gray-100 shadow-xl hover:shadow-2xl hover:shadow-blue-100 transition-all duration-500 transform hover:-translate-y-3 reveal-content">
          <div class="relative mb-6">
            <div
              class="w-16 h-16 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center transform transition-transform duration-500 group-hover:scale-110">
              <i class="fas fa-book-open text-2xl"></i>
            </div>
            <div
              class="absolute -top-2 -right-2 px-2 py-0.5 bg-blue-600 text-white text-[9px] font-bold rounded-full uppercase tracking-tighter">
              Foundation</div>
          </div>
          <h3 class="text-xl font-bold text-gray-900 mb-3">Primary Education</h3>
          <p class="text-gray-500 text-sm mb-6 leading-relaxed">
            Building foundational skills with a focus on interactive learning and early cognitive development.
          </p>
          <a href="academics/teaching-program.php"
            class="inline-flex items-center font-bold text-blue-600 text-sm group/link">
            <span class="border-b-2 border-transparent group-hover/link:border-blue-600 transition-all">Learn
              More</span>
            <svg class="w-4 h-4 ml-2 transform transition-transform group-hover/link:translate-x-2"
              fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path>
            </svg>
          </a>
        </div>

        <!-- Program Card 2 -->
        <div
          class="group bg-white p-6 md:p-8 rounded-[2rem] border border-gray-100 shadow-xl hover:shadow-2xl hover:shadow-green-100 transition-all duration-500 transform hover:-translate-y-3 reveal-content">
          <div class="relative mb-6">
            <div
              class="w-16 h-16 bg-green-50 text-green-600 rounded-xl flex items-center justify-center transform transition-transform duration-500 group-hover:scale-110">
              <i class="fas fa-school text-2xl"></i>
            </div>
            <div
              class="absolute -top-2 -right-2 px-2 py-0.5 bg-green-600 text-white text-[9px] font-bold rounded-full uppercase tracking-tighter">
              Core</div>
          </div>
          <h3 class="text-xl font-bold text-gray-900 mb-3">Secondary Education</h3>
          <p class="text-gray-500 text-sm mb-6 leading-relaxed">
            Comprehensive curriculum preparing students for higher studies and diverse career paths.
          </p>
          <a href="academics/teaching-program.php"
            class="inline-flex items-center font-bold text-green-600 text-sm group/link">
            <span class="border-b-2 border-transparent group-hover/link:border-green-600 transition-all">Learn
              More</span>
            <svg class="w-4 h-4 ml-2 transform transition-transform group-hover/link:translate-x-2"
              fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path>
            </svg>
          </a>
        </div>

        <!-- Program Card 3 -->
        <div
          class="group bg-white p-6 md:p-8 rounded-[2rem] border border-gray-100 shadow-xl hover:shadow-2xl hover:shadow-purple-100 transition-all duration-500 transform hover:-translate-y-3 reveal-content">
          <div class="relative mb-6">
            <div
              class="w-16 h-16 bg-purple-50 text-purple-600 rounded-xl flex items-center justify-center transform transition-transform duration-500 group-hover:scale-110">
              <i class="fas fa-user-graduate text-2xl"></i>
            </div>
            <div
              class="absolute -top-2 -right-2 px-2 py-0.5 bg-purple-600 text-white text-[9px] font-bold rounded-full uppercase tracking-tighter">
              Competitive</div>
          </div>
          <h3 class="text-xl font-bold text-gray-900 mb-3">Higher Education Prep</h3>
          <p class="text-gray-500 text-sm mb-6 leading-relaxed">
            Specialized preparation for JEE (Engineering) and NEET (Medical) with advanced pedagogical models.
          </p>
          <div class="flex gap-4">
            <a href="academics/jee.php" class="text-xs font-bold text-blue-600 hover:underline">JEE</a>
            <a href="academics/neet.php" class="text-xs font-bold text-red-600 hover:underline">NEET</a>
          </div>
        </div>

        <!-- Program Card 4 -->
        <div
          class="group bg-white p-6 md:p-8 rounded-[2rem] border border-gray-100 shadow-xl hover:shadow-2xl hover:shadow-orange-100 transition-all duration-500 transform hover:-translate-y-3 reveal-content">
          <div class="relative mb-6">
            <div
              class="w-16 h-16 bg-orange-50 text-orange-600 rounded-xl flex items-center justify-center transform transition-transform duration-500 group-hover:scale-110">
              <i class="fas fa-laptop-house text-2xl"></i>
            </div>
            <div
              class="absolute -top-2 -right-2 px-2 py-0.5 bg-orange-600 text-white text-[9px] font-bold rounded-full uppercase tracking-tighter">
              Distance</div>
          </div>
          <h3 class="text-xl font-bold text-gray-900 mb-3">DLP Program</h3>
          <p class="text-gray-500 text-sm mb-6 leading-relaxed">
            Specialized Distance Learning Programs for remote students to access quality education anywhere.
          </p>
          <a href="academics/dlp.php"
            class="inline-flex items-center font-bold text-orange-600 text-sm group/link">
            <span class="border-b-2 border-transparent group-hover/link:border-orange-600 transition-all">Explore DLP</span>
            <svg class="w-4 h-4 ml-2 transform transition-transform group-hover/link:translate-x-2"
              fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path>
            </svg>
          </a>
        </div>
      </div>
      </div>
    </div>
  </section>

  <!-- Location Section -->
  <section class="py-12 md:py-24 bg-white overflow-hidden relative">
    <!-- Decorative background elements -->
    <div class="absolute top-0 left-0 w-64 h-64 bg-blue-50 rounded-full -translate-x-1/2 -translate-y-1/2 opacity-50">
    </div>
    <div
      class="absolute bottom-0 right-0 w-96 h-96 bg-indigo-50 rounded-full translate-x-1/3 translate-y-1/3 opacity-50">
    </div>

    <div class="container mx-auto px-4 relative z-10">
      <div class="text-center mb-12 md:mb-16">
        <div
          class="inline-block px-3 md:px-4 py-1 md:py-1.5 mb-3 md:mb-4 bg-blue-50 text-blue-600 rounded-full text-xs md:text-sm font-bold tracking-wide uppercase reveal-content">
          Global Reach
        </div>
        <h2 class="text-3xl md:text-4xl lg:text-5xl font-black mb-3 md:mb-4 char-animate">
          <span class="bg-clip-text text-transparent bg-gradient-to-r from-blue-600 to-indigo-600">Our Presence</span>
        </h2>
        <p class="text-gray-500 text-sm md:text-base max-w-2xl mx-auto reveal-content px-4">Spreading excellence across
          multiple locations to empower students everywhere.</p>
      </div>
      <div class="swiper locationSwiper !overflow-visible reveal-content">
        <div class="swiper-wrapper !ease-linear">
          <?php
$locations = [
  ['name' => 'Ahmedabad', 'img' => 'AHMEDABAD.png'],
  ['name' => 'Bhuj', 'img' => 'BHUJ.png'],
  ['name' => 'Gandhinagar', 'img' => 'GANDHINAGAR.png'],
  ['name' => 'Rajkot', 'img' => 'RAJKOT GCI.png'],
  ['name' => 'Himmatnagar', 'img' => 'HIMMATNAGAR.png'],
  ['name' => 'Junagadh', 'img' => 'JUNAGADH.png'],
  ['name' => 'Vadodara', 'img' => 'VADODARA BHAYLI.png'],
];
foreach ($locations as $loc) {
  echo '
                        <div class="swiper-slide">
                            <div class="bg-white rounded-[2rem] p-6 text-center border border-gray-100 shadow-lg hover:shadow-2xl hover:border-blue-200 transition-all duration-500 h-full flex flex-col justify-between group">
                                <div class="bg-gray-50 rounded-2xl p-6 mb-6 flex-grow flex items-center justify-center overflow-hidden relative">
                                    <div class="absolute inset-0 bg-blue-600/0 group-hover:bg-blue-600/5 transition-colors duration-500"></div>
                                    <img src="assets/images/' . $loc['img'] . '" alt="' . $loc['name'] . '" class="w-full h-48 md:h-56 object-contain transform group-hover:scale-110 transition-transform duration-700">
                                </div>
                                <h3 class="text-2xl font-bold text-gray-900 group-hover:text-blue-600 transition-colors">' . $loc['name'] . '</h3>
                            </div>
                        </div>';
}
?>
        </div>
      </div>
    </div>
  </section>

  <!-- Facilities Section -->
  <section class="py-12 md:py-24 bg-blue-600 relative overflow-hidden">
    <!-- Decorative background elements -->
    <div class="absolute top-0 left-0 w-64 h-64 bg-blue-500 rounded-full -translate-x-1/2 -translate-y-1/2 opacity-20">
    </div>
    <div
      class="absolute bottom-0 right-0 w-96 h-96 bg-blue-700 rounded-full translate-x-1/3 translate-y-1/3 opacity-20">
    </div>

    <div class="container mx-auto px-4 relative z-10">
      <?php include __DIR__ . '/portal/website/facilities.php'; ?>
    </div>
  </section>

  <!-- Testimonials Section -->
  <section class="py-12 md:py-24 bg-gray-50 relative overflow-hidden">
    <!-- Decorative background elements -->
    <div class="absolute top-0 right-0 w-64 h-64 bg-blue-100 rounded-full translate-x-1/2 -translate-y-1/2 opacity-30">
    </div>
    <div
      class="absolute bottom-0 left-0 w-96 h-96 bg-indigo-100 rounded-full -translate-x-1/3 translate-y-1/3 opacity-30">
    </div>

    <div class="container mx-auto px-4 relative z-10">
      <div class="text-center mb-12 md:mb-16">
        <div
          class="inline-block px-3 md:px-4 py-1 md:py-1.5 mb-3 md:mb-4 bg-blue-50 text-blue-600 rounded-full text-xs md:text-sm font-bold tracking-wide uppercase reveal-content">
          Success Stories
        </div>
        <h2 class="text-3xl md:text-4xl lg:text-5xl font-black mb-3 md:mb-4 char-animate">
          <span class="bg-clip-text text-transparent bg-gradient-to-r from-blue-600 to-indigo-600">What Our Students
            Say</span>
        </h2>
        <p class="text-gray-500 text-sm md:text-base max-w-2xl mx-auto reveal-content px-4">Real experiences from
          students who transformed their futures with us.</p>
      </div>
      <div class="swiper testimonialSwiper !overflow-visible reveal-content">
        <div class="swiper-wrapper !ease-linear">
          <?php
if (!empty($dbTestimonials)) {
  foreach ($dbTestimonials as $t) {
    echo '
                    <div class="swiper-slide">
                        <div class="relative group p-2">
                            <!-- Animated Border Effect -->
                            <div class="absolute -inset-1 bg-gradient-to-r from-blue-600 to-cyan-400 rounded-2xl blur opacity-25 group-hover:opacity-75 transition duration-1000 group-hover:duration-200"></div>
                            
                            <div class="relative aspect-video rounded-xl overflow-hidden shadow-xl bg-black">
                                <iframe class="w-full h-full" 
                                    src="https://www.youtube.com/embed/' . htmlspecialchars($t['youtube_video_id'] ?? '') . '?controls=1&rel=0&modestbranding=1&showinfo=0&iv_load_policy=3" 
                                    title="' . htmlspecialchars($t['student_name'] ?? '') . '" 
                                    frameborder="0" 
                     lipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" 
                                    allowfullscreen>
                                </iframe>
                                <div class="absolute bottom-0 left-0 right-0 bg-black/60 text-white p-2 text-sm opacity-0 group-hover:opacity-100 transition-opacity">
                                    ' . htmlspecialchars($t['student_name'] ?? '') . '
                                </div>
                            </div>
                        </div>
                    </div>';
  }
}
else {
  // Default Testimonials
  $videos = [
    "XfVi2eTvHgo",
    "9I8TE20Kt_s",
    "YTV_o_uzX_M",
    "s8xm04pCMcg",
    "kMgmvn5VFkU",
    "ze6Ci2TMT10",
    "vEIqRjzWfm4",
    "EqjfeKpiL_w"
  ];
  foreach ($videos as $vid) {
    echo '
                            <div class="swiper-slide">
                                <div class="relative group p-2">
                                    <div class="absolute -inset-1 bg-gradient-to-r from-blue-600 to-cyan-400 rounded-2xl blur opacity-25 group-hover:opacity-75 transition duration-1000 group-hover:duration-200"></div>
                                    <div class="relative aspect-video rounded-xl overflow-hidden shadow-xl bg-black">
                                        <iframe class="w-full h-full" 
                                            src="https://www.youtube.com/embed/' . $vid . '?controls=1&rel=0&modestbranding=1&showinfo=0&iv_load_policy=3" 
                                            title="YouTube video player" 
                                            frameborder="0" 
                                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" 
                                            allowfullscreen>
                                        </iframe>
                                    </div>
                                </div>
                            </div>';
  }
}
?>
        </div>
      </div>
    </div>
  </section>

  <!-- Photo Gallery -->
  <section class="py-12 md:py-24 bg-blue-600 overflow-hidden relative">
    <!-- Decorative background elements -->
    <div class="absolute top-0 right-0 w-96 h-96 bg-blue-500 rounded-full translate-x-1/2 -translate-y-1/2 opacity-20">
    </div>
    <div
      class="absolute bottom-0 left-0 w-64 h-64 bg-blue-700 rounded-full -translate-x-1/2 translate-y-1/2 opacity-20">
    </div>

    <div class="container mx-auto px-4 relative z-10">
      <div class="text-center mb-12 md:mb-16">
        <div
          class="inline-block px-3 md:px-4 py-1 md:py-1.5 mb-3 md:mb-4 bg-white/10 text-white rounded-full text-xs md:text-sm font-bold tracking-wide uppercase reveal-content">
          Our Memories
        </div>
        <h2 class="text-3xl md:text-4xl lg:text-5xl font-black text-white mb-3 md:mb-4 char-animate">
          <span class="bg-clip-text text-transparent bg-gradient-to-r from-white to-blue-100">Photo Gallery</span>
        </h2>
        <div class="w-24 h-1 bg-white/30 mx-auto rounded-full reveal-content"></div>
      </div>

      <div class="swiper gallerySwiper !overflow-visible reveal-content">
        <div class="swiper-wrapper">
          <?php
if (!empty($dbGallery)) {
  foreach ($dbGallery as $img) {
    echo '
                        <div class="swiper-slide w-64 md:w-80">
                            <div class="relative group aspect-square rounded-full overflow-hidden border-4 border-white/20 shadow-2xl transform transition-all duration-500">
                                <img src="' . htmlspecialchars($img['image_path'] ?? '') . '" alt="' . htmlspecialchars($img['alt_text'] ?? '') . '" class="w-full h-full object-cover">
                                <div class="absolute inset-0 bg-blue-600/20 group-hover:bg-transparent transition-colors duration-300"></div>
                            </div>
                        </div>';
  }
}
else {
  $galleryImages = [
    ['src' => '1.jpg', 'alt' => 'Gallery 1'],
    ['src' => '2.jpg', 'alt' => 'Gallery 2'],
    ['src' => '3.jpg', 'alt' => 'Gallery 3'],
    ['src' => '4.jpg', 'alt' => 'Gallery 4'],
    ['src' => '5.jpg', 'alt' => 'Gallery 5'],
    ['src' => '6.jpg', 'alt' => 'Gallery 6'],
    ['src' => '7.jpg', 'alt' => 'Gallery 7'],
    ['src' => '8.jpg', 'alt' => 'Gallery 8'],
    ['src' => '9.jpg', 'alt' => 'Gallery 9'],
    ['src' => '10.jpeg', 'alt' => 'Gallery 10'],
    ['src' => '11.jpeg', 'alt' => 'Gallery 11'],
    ['src' => '12.jpeg', 'alt' => 'Gallery 12'],
  ];
  foreach ($galleryImages as $img) {
    echo '
                            <div class="swiper-slide w-64 md:w-80">
                                <div class="relative group aspect-square rounded-full overflow-hidden border-4 border-white/20 shadow-2xl transform transition-all duration-500">
                                    <img src="assets/images/' . $img['src'] . '" alt="' . $img['alt'] . '" class="w-full h-full object-cover">
                                    <div class="absolute inset-0 bg-blue-600/20 group-hover:bg-transparent transition-colors duration-300"></div>
                                </div>
                            </div>';
  }
}
?>
        </div>
        <!-- Navigation buttons -->
        <div class="swiper-button-next !text-white !-right-4 md:!-right-10"></div>
        <div class="swiper-button-prev !text-white !-left-4 md:!-left-10"></div>
      </div>
      <div class="text-center mt-12 md:mt-20">
        <a href="portal/website/gallery.php"
          class="group inline-flex items-center bg-white text-blue-600 hover:bg-blue-50 px-6 md:px-10 py-3 md:py-4 rounded-full text-sm md:text-base font-bold transition-all duration-300 shadow-xl hover:shadow-2xl transform hover:-translate-y-1">
          <span>View Full Gallery</span>
          <i class="fas fa-arrow-right ml-2 md:ml-3 transform transition-transform group-hover:translate-x-2"></i>
        </a>
      </div>
    </div>
  </section>

  <!-- Admissions & Portals Section -->
  <section class="py-12 md:py-24 bg-gray-50 overflow-hidden relative">
    <div class="container mx-auto px-4 relative z-10">
      <div class="text-center mb-12 md:mb-16">
        <div class="inline-block px-4 py-1.5 mb-4 bg-blue-50 text-blue-600 rounded-full text-sm font-bold tracking-wide uppercase reveal-content">
          Join Us Today
        </div>
        <h2 class="text-3xl md:text-4xl lg:text-5xl font-black mb-4 char-animate">
          <span class="bg-clip-text text-transparent bg-gradient-to-r from-blue-600 to-indigo-600">Admissions & Portals</span>
        </h2>
        <p class="text-gray-500 text-sm md:text-base max-w-2xl mx-auto reveal-content">Secure your future with our specialized educational paths and easy-to-use digital portals.</p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
        <!-- Admission Card 1 -->
        <div class="bg-white p-8 rounded-[2rem] border border-gray-100 shadow-xl hover:shadow-2xl transition-all duration-500 reveal-content">
          <h3 class="text-xl font-bold text-gray-900 mb-4">11th Admission 2026</h3>
          <p class="text-gray-500 text-sm mb-6">Active registration for the 2026-27 academic year. Start your journey with us.</p>
          <a href="https://gyanmanjarividyapith.edu.in/11Reg.php" class="inline-flex items-center bg-blue-600 text-white px-6 py-3 rounded-xl font-bold text-sm hover:bg-blue-700 transition-colors">
            Register Now
          </a>
        </div>

        <!-- Admission Card 2 -->
        <div class="bg-white p-8 rounded-[2rem] border border-gray-100 shadow-xl hover:shadow-2xl transition-all duration-500 reveal-content">
          <h3 class="text-xl font-bold text-gray-900 mb-4">Re-Neet Inquiry</h3>
          <p class="text-gray-500 text-sm mb-6">Dedicated support and specialized coaching for NEET repeaters. Excel in 2027.</p>
          <a href="reneet-admission.php" class="inline-flex items-center bg-indigo-600 text-white px-6 py-3 rounded-xl font-bold text-sm hover:bg-indigo-700 transition-colors">
            Inquiry Now
          </a>
        </div>

        <!-- Portals Card -->
        <div class="bg-white p-8 rounded-[2rem] border border-gray-100 shadow-xl hover:shadow-2xl transition-all duration-500 reveal-content">
          <h3 class="text-xl font-bold text-gray-900 mb-4">Student & Admin Portals</h3>
          <p class="text-gray-500 text-sm mb-6">Access your academic records, fees, and administrative services online.</p>
          <div class="flex flex-col gap-3">
            <a href="portal/modules/student-portal/student-login.php" class="inline-flex items-center text-blue-600 font-bold text-sm hover:underline">
              <i class="fas fa-user-graduate mr-2"></i> Student Portal
            </a>
            <a href="portal/login.php" class="inline-flex items-center text-gray-600 font-bold text-sm hover:underline">
              <i class="fas fa-sign-in-alt mr-2"></i> Admin Portal
            </a>
          </div>
        </div>
      </div>

      <div class="mt-12 text-center reveal-content">
        <p class="text-gray-500 text-sm mb-4">Already admitted? Pay your fees online easily.</p>
        <a href="https://forms.eduqfix.com/mahtmast/add" class="inline-flex items-center bg-green-600 text-white px-8 py-3 rounded-full font-bold text-sm hover:bg-green-700 transition-colors shadow-lg shadow-green-100">
          <i class="fas fa-credit-card mr-2"></i> Online Admission Fees
        </a>
      </div>
    </div>
  </section>

  <!-- Footer -->
  <?php include __DIR__ . '/include/public-footer.php'; ?>

  <!-- Swiper JS -->
  <script src="<?php echo BASE_URL; ?>/assets/vendor/swiper/swiper-bundle.min.js"></script>
  <!-- AOS JS -->
  <script src="<?php echo BASE_URL; ?>/assets/vendor/aos/aos.js"></script>
  <script>
    // Initialize AOS
    AOS.init({
      duration: 1000,
      once: true,
      offset: 100
    });

    // Hero Swiper
    new Swiper(".heroSwiper", {
      loop: true,
      autoplay: {
        delay: 5000
      },
      navigation: {
        nextEl: ".swiper-button-next",
        prevEl: ".swiper-button-prev"
      },
      pagination: {
        el: ".swiper-pagination",
        clickable: true
      },
      effect: "fade",
      fadeEffect: {
        crossFade: true
      }
    });

    // Location Swiper
    new Swiper(".locationSwiper", {
      slidesPerView: 1.5,
      spaceBetween: 20,
      loop: true,
      speed: 5000,
      allowTouchMove: false,
      autoplay: {
        delay: 0,
        disableOnInteraction: false,
      },
      freeMode: true,
      breakpoints: {
        640: {
          slidesPerView: 2.5,
          spaceBetween: 30
        },
        1024: {
          slidesPerView: 4.5,
          spaceBetween: 40
        }
      }
    });

    // Testimonial Swiper
    new Swiper(".testimonialSwiper", {
      slidesPerView: 1.2,
      spaceBetween: 20,
      loop: true,
      speed: 8000,
      allowTouchMove: false,
      autoplay: {
        delay: 0,
        disableOnInteraction: false,
      },
      freeMode: true,
      breakpoints: {
        768: {
          slidesPerView: 2.2,
          spaceBetween: 30
        },
        1024: {
          slidesPerView: 2.5,
          spaceBetween: 40
        }
      }
    });

    // Gallery Swiper
    const gallerySwiper = new Swiper(".gallerySwiper", {
      effect: "coverflow",
      grabCursor: true,
      centeredSlides: true,
      slidesPerView: "auto",
      loop: true,
      observer: true,
      observeParents: true,
      watchSlidesProgress: true,
      coverflowEffect: {
        rotate: 0,
        stretch: 0,
        depth: 100,
        modifier: 2.5,
        slideShadows: false,
      },
      navigation: {
        nextEl: ".swiper-button-next",
        prevEl: ".swiper-button-prev",
      },
      autoplay: {
        delay: 3000,
        disableOnInteraction: false,
      }
    });

    // Number Increment Animation
    const stats = document.querySelectorAll('.stat-number');

    const animate = (el) => {
      const target = +el.getAttribute('data-target');
      const duration = 2000; // 2 seconds
      const start = 0;
      let startTime = null;

      const step = (timestamp) => {
        if (!startTime) startTime = timestamp;
        const progress = Math.min((timestamp - startTime) / duration, 1);
        el.innerText = Math.floor(progress * (target - start) + start);
        if (progress < 1) {
          window.requestAnimationFrame(step);
        } else {
          el.innerText = target;
        }
      };

      window.requestAnimationFrame(step);
    };

    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          // If it's the stats section, animate numbers
          if (entry.target.classList.contains('glass-glossy')) {
            stats.forEach(stat => animate(stat));
          }

          // Trigger reveal-content for this section immediately on scroll
          entry.target.querySelectorAll('.reveal-content').forEach(content => {
            content.classList.add('active');
            // If it's a swiper container, update it
            if (content.classList.contains('swiper')) {
              setTimeout(() => {
                if (content.swiper) {
                  content.swiper.update();
                  content.swiper.slideToLoop(content.swiper.realIndex, 0);
                }
              }, 500);
            }
          });

          observer.unobserve(entry.target);
        }
      });
    }, {
      threshold: 0.1
    });

    // Observe all sections and the hero slide
    document.querySelectorAll('section, .swiper-slide').forEach(section => {
      observer.observe(section);
    });

    // Character by Character Animation for Titles
    const charAnimateTitles = document.querySelectorAll('.char-animate');

    charAnimateTitles.forEach(title => {
      const text = title.textContent.trim().replace(/\s+/g, ' ');
      title.textContent = '';

      // Split text into characters and wrap in spans
      [...text].forEach((char, i) => {
        const span = document.createElement('span');
        span.textContent = char === ' ' ? '\u00A0' : char; // Use non-breaking space for spaces
        span.className = 'char';
        span.style.transitionDelay = `${i * 50}ms`;
        title.appendChild(span);
      });

      // Observe the title to trigger animation
      const titleObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            entry.target.querySelectorAll('.char').forEach(c => c.classList.add('animated'));
            titleObserver.unobserve(entry.target);
          }
        });
      }, {
        threshold: 0.2
      });

      titleObserver.observe(title);
    });
  </script>
</body>

</html>itle);
    });
  </script>
</body>

</html> 0.2
      });

      titleObserver.observe(title);
    });
  </script>
</body>

</html>