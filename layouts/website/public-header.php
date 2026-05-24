<?php
require_once __DIR__ . '/site-config.php';

$currentPage = basename($_SERVER['PHP_SELF']);
$currentDir = basename(dirname($_SERVER['PHP_SELF']));

// Define page arrays for active menu highlighting
$aboutPages = ['founder.php', 'administrator.php', 'advisory-committee.php', 'index.php'];
$academicsPages = ['teaching-program.php', 'jee.php', 'neet.php', 'dlp.php'];
$facilitiesPages = ['computer-lab.php', 'lab-facility.php', 'playground.php', 'solution-desk.php', 'dining-hall.php', 'library.php', 'counselling.php', 'safety-security.php', 'index.php'];
$dlpPages = ['dlp.php'];
$otherAppPages = ['alumni.php', 'applogin.php', 'revisionapp.php', 'onlineexam.php', 'gallery.php'];

$isAboutActive = ($currentDir == 'about' && in_array($currentPage, $aboutPages));
$isAcademicsActive = ($currentDir == 'academics' && in_array($currentPage, $academicsPages));
$isFacilitiesActive = ($currentDir == 'facilities' && in_array($currentPage, $facilitiesPages));
$isDlpActive = ($currentDir == 'academics' && in_array($currentPage, $dlpPages));
$isOtherAppActive = in_array($currentPage, $otherAppPages);
?>


<header class="navbar-glass py-2 md:py-3 sticky top-0 z-50 transition-all duration-300">
    <!-- Cursor Trail Animation -->
    <div class="cursor-trail-container">
        <div class="cursor-glow" id="cursor-glow"></div>
        <!-- Animated Background Orbs -->
        <!-- <div class="navbar-orb navbar-orb-1"></div> -->
        <div class="navbar-orb navbar-orb-2"></div>
        <div class="navbar-orb navbar-orb-3"></div>
    </div>

    <nav
        class="nav-links-container w-full px-3 md:px-6 py-1 md:py-2 flex flex-wrap justify-between items-center relative">
        <!-- Logo with Responsive Images -->
        <a href="<?php echo BASE_URL; ?>/index.php"
            class="flex items-center space-x-2 md:space-x-3 text-xl md:text-2xl font-bold text-blue-700 relative z-10">
            <!-- Desktop Logo -->
            <img src="<?php echo BASE_URL; ?>/assets/images/logogmn.png" alt="Gyanmanjari Vidyapith"
                class="logo-desktop h-12 md:h-16 lg:h-20 w-auto transition-all duration-300">
            <!-- Mobile/Tablet Logo Icon -->
            <img src="<?php echo BASE_URL; ?>/assets/images/logo-icon.png" alt="Gyanmanjari Vidyapith"
                class="logo-mobile h-12 w-auto transition-all duration-300">
        </a>

        <!-- Mobile Menu Button -->
        <button id="mobile-menu-button"
            class="lg:hidden mobile-menu-btn focus:outline-none transition-colors duration-300">
            <i class="fas fa-bars text-xl text-gray-700" id="menu-icon"></i>
        </button>

        <!-- Navigation Menu -->
        <ul id="main-nav"
            class="hidden lg:flex flex-col lg:flex-row gap-0.5 lg:gap-0.5 xl:gap-1 items-start lg:items-center w-full lg:w-auto mt-3 lg:mt-0 lg:bg-transparent lg:backdrop-blur-none p-4 lg:p-0 rounded-2xl lg:rounded-none shadow-2xl lg:shadow-none border border-white/30 lg:border-none max-h-[80vh] overflow-y-auto lg:overflow-visible css-public-header-f9e6a4">

            <!-- 11th Admission -->
            <li class="w-full lg:w-auto">
                <a href="https://gyanmanjarividyapith.edu.in/11Reg.php"
                    class="nav-link-premium <?= ($currentPage == '11Reg.php') ? 'nav-link-active-premium' : '' ?> gap-2">
                    11th Admission 2026
                    <span class="new-badge">NEW</span>
                </a>
            </li>

            <!-- Home -->
            <li class="w-full lg:w-auto">
                <a href="<?php echo BASE_URL; ?>/index.php"
                    class="nav-link-premium <?= ($currentPage == 'index.php' && $currentDir != 'about' && $currentDir != 'facilities') ? 'nav-link-active-premium' : '' ?>">
                    Home
                </a>
            </li>

            <!-- About Us Dropdown -->
            <li class="relative nav-item-dropdown w-full lg:w-auto">
                <button type="button"
                    class="nav-link-premium <?= $isAboutActive ? 'nav-link-active-premium' : '' ?> w-full justify-between lg:justify-start gap-1"
                    onclick="toggleDropdown(this)">
                    About Us
                    <svg class="w-4 h-4 dropdown-arrow" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                            d="M5.23 7.21a.75.75 0 011.06.02L10 10.939l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.25a.75.75 0 01-1.06 0L5.25 8.27a.75.75 0 01-.02-1.06z"
                            clip-rule="evenodd" />
                    </svg>
                </button>
                <ul class="dropdown-menu">
                    <li><a href="<?php echo BASE_URL; ?>/about/founder.php"
                            class="dropdown-link-premium <?= ($currentPage == 'founder.php') ? 'dropdown-link-active-premium' : '' ?>"><i
                                class="fas fa-user-tie mr-3 text-blue-500"></i>Founder</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/about/administrator.php"
                            class="dropdown-link-premium <?= ($currentPage == 'administrator.php') ? 'dropdown-link-active-premium' : '' ?>"><i
                                class="fas fa-user-cog mr-3 text-indigo-500"></i>Administrator</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/about/advisory-committee.php"
                            class="dropdown-link-premium <?= ($currentPage == 'advisory-committee.php') ? 'dropdown-link-active-premium' : '' ?>"><i
                                class="fas fa-users mr-3 text-purple-500"></i>Advisory Committee</a></li>
                </ul>
            </li>

            <!-- Academics Dropdown -->
            <li class="relative nav-item-dropdown w-full lg:w-auto">
                <button type="button"
                    class="nav-link-premium <?= $isAcademicsActive ? 'nav-link-active-premium' : '' ?> w-full justify-between lg:justify-start gap-1"
                    onclick="toggleDropdown(this)">
                    Academics
                    <svg class="w-4 h-4 dropdown-arrow" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                            d="M5.23 7.21a.75.75 0 011.06.02L10 10.939l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.25a.75.75 0 01-1.06 0L5.25 8.27a.75.75 0 01-.02-1.06z"
                            clip-rule="evenodd" />
                    </svg>
                </button>
                <ul class="dropdown-menu">
                    <li><a href="<?php echo BASE_URL; ?>/academics/teaching-program.php"
                            class="dropdown-link-premium <?= ($currentPage == 'teaching-program.php') ? 'dropdown-link-active-premium' : '' ?>"><i
                                class="fas fa-chalkboard-teacher mr-3 text-blue-500"></i>Teaching Program</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/academics/jee.php"
                            class="dropdown-link-premium <?= ($currentPage == 'jee.php') ? 'dropdown-link-active-premium' : '' ?>"><i
                                class="fas fa-atom mr-3 text-indigo-500"></i>JEE</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/academics/neet.php"
                            class="dropdown-link-premium <?= ($currentPage == 'neet.php') ? 'dropdown-link-active-premium' : '' ?>"><i
                                class="fas fa-heartbeat mr-3 text-red-500"></i>NEET</a></li>
                </ul>
            </li>

            <!-- Facilities Dropdown -->
            <li class="relative nav-item-dropdown w-full lg:w-auto">
                <button type="button"
                    class="nav-link-premium <?= $isFacilitiesActive ? 'nav-link-active-premium' : '' ?> w-full justify-between lg:justify-start gap-1"
                    onclick="toggleDropdown(this)">
                    Facilities
                    <svg class="w-4 h-4 dropdown-arrow" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                            d="M5.23 7.21a.75.75 0 011.06.02L10 10.939l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.25a.75.75 0 01-1.06 0L5.25 8.27a.75.75 0 01-.02-1.06z"
                            clip-rule="evenodd" />
                    </svg>
                </button>
                <ul class="dropdown-menu">
                    <li><a href="<?php echo BASE_URL; ?>/facilities/computer-lab.php"
                            class="dropdown-link-premium <?= ($currentPage == 'computer-lab.php') ? 'dropdown-link-active-premium' : '' ?>"><i
                                class="fas fa-desktop mr-3 text-blue-500"></i>Computer Lab</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/facilities/lab-facility.php"
                            class="dropdown-link-premium <?= ($currentPage == 'lab-facility.php') ? 'dropdown-link-active-premium' : '' ?>"><i
                                class="fas fa-flask mr-3 text-green-500"></i>Lab Facility</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/facilities/playground.php"
                            class="dropdown-link-premium <?= ($currentPage == 'playground.php') ? 'dropdown-link-active-premium' : '' ?>"><i
                                class="fas fa-futbol mr-3 text-orange-500"></i>Play Ground</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/facilities/solution-desk.php"
                            class="dropdown-link-premium <?= ($currentPage == 'solution-desk.php') ? 'dropdown-link-active-premium' : '' ?>"><i
                                class="fas fa-question-circle mr-3 text-purple-500"></i>Solution Desk</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/facilities/dining-hall.php"
                            class="dropdown-link-premium <?= ($currentPage == 'dining-hall.php') ? 'dropdown-link-active-premium' : '' ?>"><i
                                class="fas fa-utensils mr-3 text-yellow-500"></i>Dining Hall</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/facilities/library.php"
                            class="dropdown-link-premium <?= ($currentPage == 'library.php') ? 'dropdown-link-active-premium' : '' ?>"><i
                                class="fas fa-book mr-3 text-red-500"></i>Library</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/facilities/counselling.php"
                            class="dropdown-link-premium <?= ($currentPage == 'counselling.php') ? 'dropdown-link-active-premium' : '' ?>"><i
                                class="fas fa-comments mr-3 text-teal-500"></i>Counselling</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/facilities/safety-security.php"
                            class="dropdown-link-premium <?= ($currentPage == 'safety-security.php') ? 'dropdown-link-active-premium' : '' ?>"><i
                                class="fas fa-shield-alt mr-3 text-indigo-500"></i>Safety & Security</a></li>
                </ul>
            </li>

            <!-- DLP Dropdown -->
            <li class="relative nav-item-dropdown w-full lg:w-auto">
                <button type="button"
                    class="nav-link-premium <?= $isDlpActive ? 'nav-link-active-premium' : '' ?> w-full justify-between lg:justify-start gap-1"
                    onclick="toggleDropdown(this)">
                    DLP
                    <svg class="w-4 h-4 dropdown-arrow" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                            d="M5.23 7.21a.75.75 0 011.06.02L10 10.939l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.25a.75.75 0 01-1.06 0L5.25 8.27a.75.75 0 01-.02-1.06z"
                            clip-rule="evenodd" />
                    </svg>
                </button>
                <ul class="dropdown-menu">
                    <li><a href="<?php echo BASE_URL; ?>/academics/dlp.php"
                            class="dropdown-link-premium <?= ($currentPage == 'dlp.php') ? 'dropdown-link-active-premium' : '' ?>"><i
                                class="fas fa-laptop-code mr-3 text-blue-500"></i>What is DLP</a></li>
                </ul>
            </li>

            <!-- Others Dropdown -->
            <li class="relative nav-item-dropdown w-full lg:w-auto">
                <button type="button"
                    class="nav-link-premium <?= $isOtherAppActive ? 'nav-link-active-premium' : '' ?> w-full justify-between lg:justify-start gap-1"
                    onclick="toggleDropdown(this)">
                    Others
                    <svg class="w-4 h-4 dropdown-arrow" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                            d="M5.23 7.21a.75.75 0 011.06.02L10 10.939l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.25a.75.75 0 01-1.06 0L5.25 8.27a.75.75 0 01-.02-1.06z"
                            clip-rule="evenodd" />
                    </svg>
                </button>
                <ul class="dropdown-menu">
                    <li><a href="https://alumni.gyanmanjari.com/"
                            class="dropdown-link-premium <?= ($currentPage == 'alumni.php') ? 'dropdown-link-active-premium' : '' ?>"><i
                                class="fas fa-graduation-cap mr-3 text-blue-500"></i>Alumni</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/archive/applogin.php"
                            class="dropdown-link-premium <?= ($currentPage == 'applogin.php') ? 'dropdown-link-active-premium' : '' ?>"><i
                                class="fas fa-mobile-alt mr-3 text-green-500"></i>App Login</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/portal/website/revisionapp.php"
                            class="dropdown-link-premium <?= ($currentPage == 'revisionapp.php') ? 'dropdown-link-active-premium' : '' ?>"><i
                                class="fas fa-sync-alt mr-3 text-purple-500"></i>Revision App</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/portal/website/onlineexam.php"
                            class="dropdown-link-premium <?= ($currentPage == 'onlineexam.php') ? 'dropdown-link-active-premium' : '' ?>"><i
                                class="fas fa-clipboard-list mr-3 text-orange-500"></i>Online Exam</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/portal/website/gallery.php"
                            class="dropdown-link-premium <?= ($currentPage == 'gallery.php') ? 'dropdown-link-active-premium' : '' ?>"><i
                                class="fas fa-images mr-3 text-pink-500"></i>Gallery</a></li>
                    <li><a href="https://drive.google.com/file/d/13RLbTjEeMSwjZ0ORJb95ftJOH-CkexIq/view"
                            class="dropdown-link-premium"><i class="fas fa-file-pdf mr-3 text-red-500"></i>Brochure</a>
                    </li>
                </ul>
            </li>

            <!-- Contact Us -->
            <li class="w-full lg:w-auto">
                <a href="<?php echo BASE_URL; ?>/portal/website/contact.php"
                    class="nav-link-premium <?= ($currentPage == 'contact.php') ? 'nav-link-active-premium' : '' ?>">
                    Contact Us
                </a>
            </li>

            <!-- Re-NEET PAYlink -->
            <li class="w-full lg:w-auto">
                <a href="https://forms.eduqfix.com/mahtmast/add" class="nav-link-premium">
                    Re-NEET PAYlink
                </a>
            </li>

            <!-- CTA Buttons -->
            <li class="w-full lg:w-auto mt-2 lg:mt-0">
                <a href="<?php echo BASE_URL; ?>/portal/modules/student-portal/student-login.php"
                    class="nav-cta-btn nav-cta-student w-full lg:w-auto">
                    <i class="fas fa-user-graduate mr-2"></i>
                    Student Portal
                </a>
            </li>
            <li class="w-full lg:w-auto mt-2 lg:mt-0">
                <a href="<?php echo BASE_URL; ?>/portal/login.php" class="nav-cta-btn nav-cta-admin w-full lg:w-auto">
                    <i class="fas fa-sign-in-alt mr-2"></i>
                    Admin Portal
                </a>
            </li>
        </ul>
    </nav>
</header>

<script>
    // Cursor Glow Effect
    const header = document.querySelector('.navbar-glass');
    const cursorGlow = document.getElementById('cursor-glow');

    if (header && cursorGlow) {
        header.addEventListener('mousemove', (e) => {
            const rect = header.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            cursorGlow.style.left = x + 'px';
            cursorGlow.style.top = y + 'px';
        });

        header.addEventListener('mouseenter', () => {
            cursorGlow.style.opacity = '1';
        });

        header.addEventListener('mouseleave', () => {
            cursorGlow.style.opacity = '0';
        });
    }

    // Mobile menu toggle
    const menuBtn = document.getElementById('mobile-menu-button');
    const mainNav = document.getElementById('main-nav');
    const menuIcon = document.getElementById('menu-icon');

    if (menuBtn && mainNav && menuIcon) {
        menuBtn.addEventListener('click', () => {
            mainNav.classList.toggle('hidden');
            mainNav.classList.toggle('mobile-active');

            if (mainNav.classList.contains('mobile-active')) {
                menuIcon.classList.remove('fa-bars');
                menuIcon.classList.add('fa-times');
            } else {
                menuIcon.classList.remove('fa-times');
                menuIcon.classList.add('fa-bars');
            }
        });
    }

    // Unified dropdown toggle (works for mobile only, desktop uses hover)
    function toggleDropdown(button) {
        if (window.innerWidth >= 1024) return; // Desktop uses hover

        const dropdown = button.nextElementSibling;
        const arrow = button.querySelector('.dropdown-arrow');
        if (!dropdown) return;

        const isActive = dropdown.classList.contains('active');

        // Close all other dropdowns
        document.querySelectorAll('.dropdown-menu').forEach(el => {
            el.classList.remove('active');
        });
        document.querySelectorAll('.dropdown-arrow').forEach(el => {
            el.classList.remove('rotate');
        });

        // Toggle current dropdown
        if (!isActive) {
            dropdown.classList.add('active');
            if (arrow) arrow.classList.add('rotate');
        }
    }

    // Close mobile menu on window resize
    window.addEventListener('resize', () => {
        if (window.innerWidth >= 1024 && mainNav) {
            mainNav.classList.remove('mobile-active');
            mainNav.classList.add('hidden');
            if (menuIcon) {
                menuIcon.classList.remove('fa-times');
                menuIcon.classList.add('fa-bars');
            }
            // Close all mobile dropdowns
            document.querySelectorAll('.dropdown-menu').forEach(el => {
                el.classList.remove('active');
            });
            document.querySelectorAll('.dropdown-arrow').forEach(el => {
                el.classList.remove('rotate');
            });
        }
    });
</script>