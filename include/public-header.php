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
<style>
    /* ===================================
       Premium Glassmorphic Navbar Styles
       =================================== */

    /* Google Fonts for Consistency */
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');

    /* Navbar Container */
    .navbar-glass {
        background: rgba(255, 255, 255, 0.75);
        -webkit-backdrop-filter: blur(20px);
        backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.5);
        box-shadow:
            0 8px 32px rgba(31, 38, 135, 0.15),
            0 4px 12px rgba(0, 0, 0, 0.05),
            inset 0 0 80px rgba(255, 255, 255, 0.1);
        position: relative;
        overflow: visible;
    }

    /* Cursor Trail Animation Container */
    .cursor-trail-container {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        pointer-events: none;
        overflow: hidden;
        z-index: 0;
    }

    .cursor-glow {
        position: absolute;
        width: 300px;
        height: 300px;
        background: radial-gradient(circle, rgba(59, 130, 246, 0.15) 0%, rgba(99, 102, 241, 0.08) 30%, transparent 70%);
        border-radius: 50%;
        transform: translate(-50%, -50%);
        pointer-events: none;
        transition: all 0.15s ease-out;
        opacity: 0;
    }

    .navbar-glass:hover .cursor-glow {
        opacity: 1;
    }

    /* Animated Background Orbs */
    .navbar-orb {
        position: absolute;
        border-radius: 50%;
        filter: blur(40px);
        opacity: 0.3;
        animation: float-orb 8s ease-in-out infinite;
        z-index: 0;
    }

    .navbar-orb-1 {
        width: 100px;
        height: 100px;
        background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
        top: -30px;
        left: 10%;
        animation-delay: 0s;
    }

    .navbar-orb-2 {
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, #06b6d4 0%, #3b82f6 100%);
        top: -20px;
        right: 20%;
        animation-delay: -2s;
    }

    .navbar-orb-3 {
        width: 60px;
        height: 60px;
        background: linear-gradient(135deg, #8b5cf6 0%, #ec4899 100%);
        bottom: -20px;
        left: 40%;
        animation-delay: -4s;
    }

    @keyframes float-orb {

        0%,
        100% {
            transform: translateY(0) translateX(0) scale(1);
        }

        25% {
            transform: translateY(-10px) translateX(5px) scale(1.05);
        }

        50% {
            transform: translateY(5px) translateX(-5px) scale(0.95);
        }

        75% {
            transform: translateY(-5px) translateX(10px) scale(1.02);
        }
    }

    /* Navigation Links Container */
    .nav-links-container {
        position: relative;
        z-index: 10;
    }

    /* Premium Nav Link Styling */
    .nav-link-premium {
        position: relative;
        display: flex;
        align-items: center;
        padding: 0.5rem 0.875rem;
        font-weight: 700;
        font-size: 0.8125rem;
        color: #4b5563;
        text-decoration: none;
        border-radius: 10px;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        overflow: hidden;
        background: transparent;
        cursor: pointer;
        border: none;
        font-family: 'Poppins', sans-serif;
        white-space: nowrap;
    }

    .nav-link-premium::before {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(99, 102, 241, 0.1) 100%);
        border-radius: 12px;
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .nav-link-premium::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 50%;
        width: 0;
        height: 2px;
        background: linear-gradient(90deg, #3b82f6, #8b5cf6);
        transition: all 0.3s ease;
        transform: translateX(-50%);
        border-radius: 2px;
    }

    .nav-link-premium:hover {
        color: #2563eb;
        transform: translateY(-2px);
    }

    .nav-link-premium:hover::before {
        opacity: 1;
    }

    .nav-link-premium:hover::after {
        width: 60%;
    }

    /* Active Link State */
    .nav-link-active-premium {
        color: #2563eb !important;
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.15) 0%, rgba(99, 102, 241, 0.1) 100%);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
    }

    .nav-link-active-premium::after {
        width: 60%;
    }

    /* Unified Dropdown Styling */
    .dropdown-menu {
        min-width: 220px;
        background: rgba(255, 255, 255, 0.95);
        -webkit-backdrop-filter: blur(20px);
        backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.5);
        border-radius: 16px;
        box-shadow:
            0 20px 40px rgba(0, 0, 0, 0.15),
            0 10px 20px rgba(0, 0, 0, 0.1);
        padding: 0.5rem;
        z-index: 9999;
        list-style: none;
    }

    /* Desktop: absolute positioned dropdown */
    @media (min-width: 1280px) {
        .dropdown-menu {
            position: absolute;
            top: 100%;
            left: 0;
            opacity: 0;
            visibility: hidden;
            transform: translateY(10px) scale(0.95);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .nav-item-dropdown:hover .dropdown-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0) scale(1);
        }
    }

    /* Mobile: collapsible dropdown */
    @media (max-width: 1279px) {
        .dropdown-menu {
            display: none;
            position: static;
            width: 100%;
            margin-top: 0.5rem;
            margin-left: 0.75rem;
            background: rgba(249, 250, 251, 0.8);
            border-left: 2px solid #60a5fa;
            box-shadow: none;
        }

        .dropdown-menu.active {
            display: block;
        }
    }

    .dropdown-link-premium {
        display: flex;
        align-items: center;
        padding: 0.75rem 1rem;
        font-weight: 700;
        font-size: 0.875rem;
        color: #4b5563;
        text-decoration: none;
        border-radius: 10px;
        transition: all 0.2s ease;
        margin: 0.25rem 0;
    }

    .dropdown-link-premium:hover {
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(99, 102, 241, 0.1) 100%);
        color: #2563eb;
        transform: translateX(5px);
    }

    .dropdown-link-active-premium {
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.15) 0%, rgba(99, 102, 241, 0.1) 100%);
        color: #2563eb;
        border-left: 3px solid #3b82f6;
    }

    /* CTA Buttons */
    .nav-cta-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.625rem 1.25rem;
        font-weight: 600;
        font-size: 0.875rem;
        border-radius: 12px;
        text-decoration: none;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
    }

    .nav-cta-btn::before {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(45deg, transparent 30%, rgba(255, 255, 255, 0.3) 50%, transparent 70%);
        transform: translateX(-100%);
        transition: transform 0.6s ease;
    }

    .nav-cta-btn:hover::before {
        transform: translateX(100%);
    }

    .nav-cta-student {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
    }

    .nav-cta-student:hover {
        box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
        transform: translateY(-2px);
    }

    .nav-cta-admin {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: white;
        box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
    }

    .nav-cta-admin:hover {
        box-shadow: 0 8px 25px rgba(59, 130, 246, 0.4);
        transform: translateY(-2px);
    }

    .nav-cta-parent {
        background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%);
        color: white;
        box-shadow: 0 4px 15px rgba(79, 70, 229, 0.3);
    }

    .nav-cta-parent:hover {
        box-shadow: 0 8px 25px rgba(79, 70, 229, 0.4);
        transform: translateY(-2px);
    }

    /* NEW Badge Animation */
    .new-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.125rem 0.5rem;
        font-size: 0.625rem;
        font-weight: 700;
        background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
        color: white;
        border-radius: 20px;
        animation: pulse-badge 2s ease-in-out infinite;
        box-shadow: 0 2px 8px rgba(59, 130, 246, 0.4);
    }

    @keyframes pulse-badge {

        0%,
        100% {
            transform: scale(1);
            opacity: 1;
        }

        50% {
            transform: scale(1.1);
            opacity: 0.8;
        }
    }

    /* Mobile Menu Styles */
    .mobile-menu-glass {
        background: rgba(255, 255, 255, 0.95);
        -webkit-backdrop-filter: blur(20px);
        backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.5);
        border-radius: 20px;
        box-shadow:
            0 20px 50px rgba(0, 0, 0, 0.15),
            0 10px 25px rgba(0, 0, 0, 0.1);
    }

    #main-nav.mobile-active {
        display: flex !important;
        animation: slideDown 0.3s ease-out forwards;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Mobile Menu Button Animation */
    .mobile-menu-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 44px;
        height: 44px;
        border-radius: 12px;
        background: rgba(255, 255, 255, 0.5);
        border: 1px solid rgba(255, 255, 255, 0.5);
        transition: all 0.3s ease;
    }

    .mobile-menu-btn:hover {
        background: rgba(59, 130, 246, 0.1);
        transform: scale(1.05);
    }

    /* Custom scrollbar for mobile menu */
    #main-nav::-webkit-scrollbar {
        width: 4px;
    }

    #main-nav::-webkit-scrollbar-thumb {
        background: linear-gradient(135deg, #3b82f6, #8b5cf6);
        border-radius: 10px;
    }

    /* Global reset */
    html,
    body {
        margin: 0 !important;
        padding: 0 !important;
        overflow-x: hidden;
    }

    header.sticky {
        top: 0 !important;
        margin-top: 0 !important;
    }

    /* Responsive Logo */
    .logo-desktop {
        display: block;
    }

    .logo-laptop {
        display: none;
    }

    .logo-mobile {
        display: none;
    }

    /* 1366px Optimization */
    @media (min-width: 1280px) and (max-width: 1366px) {
        .logo-desktop {
            display: none;
        }

        .logo-laptop {
            display: block;
        }
    }

    @media (max-width: 1279px) {
        .logo-desktop {
            display: none;
        }

        .logo-laptop {
            display: none;
        }

        .logo-mobile {
            display: block;
        }
    }

    /* Dropdown Arrow Animation */
    .dropdown-arrow {
        transition: transform 0.3s ease;
    }

    .nav-item-dropdown:hover .dropdown-arrow,
    .dropdown-arrow.rotate {
        transform: rotate(180deg);
    }

    /* Force hide mobile menu button on desktop */
    @media (min-width: 1280px) {
        .mobile-menu-btn {
            display: none !important;
        }

        .xl\:hidden {
            display: none !important;
        }
    }

    /* 1366px Laptop & Small Screen Optimization */
    @media (min-width: 1280px) and (max-width: 1440px) {
        .nav-link-premium {
            padding: 0.5rem 0.65rem !important;
            font-size: 0.72rem !important;
        }

        .nav-cta-btn {
            padding: 0.4rem 0.8rem !important;
            font-size: 0.75rem !important;
        }

        #main-nav {
            gap: 0.15rem !important;
        }

        .logo-desktop {
            height: 3.5rem !important;
        }

        .new-badge {
            padding: 0.1rem 0.3rem !important;
            font-size: 0.55rem !important;
        }
    }
</style>

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
            <!-- Desktop Logo (Large Screens) -->
            <img src="<?php echo BASE_URL; ?>/assets/images/logogmn.png" alt="GCA"
                class="logo-desktop h-12 md:h-16 lg:h-20 w-auto transition-all duration-300">
            <!-- Laptop Logo (1366px) -->
            <img src="<?php echo BASE_URL; ?>/assets/images/logogmn.png" alt="GCA"
                class="logo-laptop h-12 md:h-16 lg:h-20 w-auto transition-all duration-300">
            <!-- Mobile/Tablet Logo Icon -->
            <img src="<?php echo BASE_URL; ?>/assets/images/logo-icon.png" alt="GCA"
                class="logo-mobile h-12 w-auto transition-all duration-300">
        </a>

        <!-- Mobile Menu Button -->
        <button id="mobile-menu-button"
            class="xl:hidden mobile-menu-btn focus:outline-none transition-colors duration-300">
            <i class="fas fa-bars text-xl text-gray-700" id="menu-icon"></i>
        </button>

        <!-- Navigation Menu -->
        <ul id="main-nav"
            class="hidden xl:flex flex-col xl:flex-row gap-0.5 xl:gap-0.5 xl:gap-1 items-start xl:items-center w-full xl:w-auto mt-3 xl:mt-0 xl:bg-transparent xl:backdrop-blur-none p-4 xl:p-0 rounded-2xl xl:rounded-none shadow-2xl xl:shadow-none border border-white/30 xl:border-none max-h-[80vh] overflow-y-auto xl:overflow-visible"
            style="background: transparent;">

            <!-- 11th Admission 2026 -->
            <li class="w-full xl:w-auto">
                <a href="https://gyanmanjarividyapith.edu.in/11Reg.php"
                    class="nav-link-premium <?= ($currentPage == '11Reg.php') ? 'nav-link-active-premium' : '' ?> gap-2">
                    11th Admission 2026
                    <span class="new-badge">NEW</span>
                </a>
            </li>

            <!-- Re-Neet Inquiry -->
            <li class="w-full xl:w-auto">
                <a href="<?php echo BASE_URL; ?>/reneet-admission.php"
                    class="nav-link-premium <?= ($currentPage == 'reneet-admission.php') ? 'nav-link-active-premium' : '' ?> gap-2">
                    Re-Neet Inquiry
                </a>
            </li>

            <!-- Home -->
            <li class="w-full xl:w-auto">
                <a href="<?php echo BASE_URL; ?>/index.php"
                    class="nav-link-premium <?= ($currentPage == 'index.php' && $currentDir != 'about' && $currentDir != 'facilities') ? 'nav-link-active-premium' : '' ?>">
                    Home
                </a>
            </li>

            <!-- About Us Dropdown -->
            <li class="relative nav-item-dropdown w-full xl:w-auto">
                <button type="button"
                    class="nav-link-premium <?= $isAboutActive ? 'nav-link-active-premium' : '' ?> w-full justify-between xl:justify-start gap-1"
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
            <li class="relative nav-item-dropdown w-full xl:w-auto">
                <button type="button"
                    class="nav-link-premium <?= $isAcademicsActive ? 'nav-link-active-premium' : '' ?> w-full justify-between xl:justify-start gap-1"
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
            <li class="relative nav-item-dropdown w-full xl:w-auto">
                <button type="button"
                    class="nav-link-premium <?= $isFacilitiesActive ? 'nav-link-active-premium' : '' ?> w-full justify-between xl:justify-start gap-1"
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
            <li class="relative nav-item-dropdown w-full xl:w-auto">
                <button type="button"
                    class="nav-link-premium <?= $isDlpActive ? 'nav-link-active-premium' : '' ?> w-full justify-between xl:justify-start gap-1"
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
            <li class="relative nav-item-dropdown w-full xl:w-auto">
                <button type="button"
                    class="nav-link-premium <?= $isOtherAppActive ? 'nav-link-active-premium' : '' ?> w-full justify-between xl:justify-start gap-1"
                    onclick="toggleDropdown(this)">
                    Others
                    <svg class="w-4 h-4 dropdown-arrow" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                            d="M5.23 7.21a.75.75 0 011.06.02L10 10.939l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.25a.75.75 0 01-1.06 0L5.25 8.27a.75.75 0 01-.02-1.06z"
                            clip-rule="evenodd" />
                    </svg>
                </button>
                <ul class="dropdown-menu">
                    <li><a href="https://alumni.gyanmanjarividyapith.edu.in/"
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
            <li class="w-full xl:w-auto">
                <a href="<?php echo BASE_URL; ?>/portal/website/contact.php"
                    class="nav-link-premium <?= ($currentPage == 'contact.php') ? 'nav-link-active-premium' : '' ?>">
                    Contact Us
                </a>
            </li>

            <!-- Admission Fees -->
            <li class="w-full xl:w-auto">
                <a href="https://forms.eduqfix.com/mahtmast/add" class="nav-link-premium">
                    Admission Fees
                </a>
            </li>

            <!-- CTA Buttons -->
            <li class="w-full xl:w-auto mt-2 xl:mt-0">
                <a href="<?php echo BASE_URL; ?>/portal/modules/student-portal/student-login.php"
                    class="nav-cta-btn nav-cta-student w-full xl:w-auto">
                    <i class="fas fa-user-graduate mr-2"></i>
                    Student Portal
                </a>
            </li>
            <li class="w-full xl:w-auto mt-2 xl:mt-0">
                <a href="<?php echo BASE_URL; ?>/portal/parent-login.php"
                    class="nav-cta-btn nav-cta-parent w-full xl:w-auto">
                    <i class="fas fa-user-friends mr-2"></i>
                    Parent Portal
                </a>
            </li>
            <li class="w-full xl:w-auto mt-2 xl:mt-0">
                <a href="<?php echo PORTAL_URL; ?>/login.php" class="nav-cta-btn nav-cta-admin w-full xl:w-auto">
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
        if (window.innerWidth >= 1280) return; // Desktop uses hover

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
        if (window.innerWidth >= 1280 && mainNav) {
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