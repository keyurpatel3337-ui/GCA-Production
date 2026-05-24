<?php require_once __DIR__ . '/../common/constants.php';
require_once ENV_CONFIG_FILE; ?>
<footer class="relative mt-20 bg-slate-50 text-slate-700 pt-16 pb-8 overflow-hidden border-t border-slate-200">
    <!-- Subtle background decorative elements -->
    <div class="absolute top-0 left-0 w-full h-[1px] bg-white"></div>
    <div class="absolute -bottom-24 -right-24 w-80 h-80 bg-blue-500/5 rounded-full blur-[100px]"></div>
    <div class="absolute -top-24 -left-24 w-60 h-60 bg-indigo-500/5 rounded-full blur-[100px]"></div>

    <div class="container mx-auto px-4 md:px-6 lg:px-8 relative z-10">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-12 mb-16">

            <!-- About & Logo -->
            <div class="lg:col-span-1">
                <div class="mb-6">
                    <!-- Logo is now perfectly visible on the light off-white background -->
                    <img src="<?php echo BASE_URL; ?>/assets/images/logogmn.png" alt="Gyanmanjari"
                        class="h-14 md:h-16 w-auto">
                </div>
                <p class="text-sm leading-relaxed text-slate-500 mb-8 max-w-sm">
                    Empowering students through innovation, character, and scientific excellence. A journey of
                    educational transformation since 2006.
                </p>
                <div class="flex space-x-5">
                    <a href="https://www.facebook.com/gyanmanjaribvn/"
                        class="text-slate-400 hover:text-blue-600 transition-all duration-300 transform hover:scale-110">
                        <i class="fab fa-facebook-f text-lg"></i>
                    </a>
                    <a href="https://www.instagram.com/gyanmanjari.vidyapith/?hl=en"
                        class="text-slate-400 hover:text-pink-600 transition-all duration-300 transform hover:scale-110">
                        <i class="fab fa-instagram text-lg"></i>
                    </a>
                    <a href="https://in.linkedin.com/company/gyanmanjari-vidyapith"
                        class="text-slate-400 hover:text-blue-800 transition-all duration-300 transform hover:scale-110">
                        <i class="fab fa-linkedin-in text-lg"></i>
                    </a>
                    <a href="#"
                        class="text-slate-400 hover:text-sky-500 transition-all duration-300 transform hover:scale-110">
                        <i class="fab fa-twitter text-lg"></i>
                    </a>
                </div>
            </div>

            <!-- Quick Links -->
            <div>
                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-widest mb-8">
                    Quick Links
                </h3>
                <ul class="space-y-4">
                    <li><a href="<?php echo BASE_URL; ?>/about/founder.php"
                            class="text-sm text-slate-600 hover:text-blue-600 transition-colors duration-200">Founder
                            Profile</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/about/administrator.php"
                            class="text-sm text-slate-600 hover:text-blue-600 transition-colors duration-200">Administrator</a>
                    </li>
                    <li><a href="<?php echo BASE_URL; ?>/portal/website/contact.php"
                            class="text-sm text-slate-600 hover:text-blue-600 transition-colors duration-200">Contact
                            Us</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/portal/website/privacy-policy.php"
                            class="text-sm text-slate-600 hover:text-blue-600 transition-colors duration-200">Privacy
                            Policy</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/portal/website/digital-wallet-policy.php"
                            class="text-sm text-slate-600 hover:text-blue-600 transition-colors duration-200">Wallet
                            Policy</a></li>
                </ul>
            </div>

            <!-- Programs -->
            <div>
                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-widest mb-8">
                    Programs
                </h3>
                <ul class="space-y-4">
                    <li><a href="<?php echo BASE_URL; ?>/academics/teaching-program.php"
                            class="text-sm text-slate-600 hover:text-indigo-600 transition-colors duration-200">Teaching
                            Model</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/academics/jee.php"
                            class="text-sm text-slate-600 hover:text-indigo-600 transition-colors duration-200">JEE
                            Preparation</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/academics/neet.php"
                            class="text-sm text-slate-600 hover:text-indigo-600 transition-colors duration-200">NEET
                            Preparation</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/facilities/index.php"
                            class="text-sm text-slate-600 hover:text-indigo-600 transition-colors duration-200">Infrastructure</a>
                    </li>
                </ul>
            </div>

            <!-- Get In Touch -->
            <div>
                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-widest mb-8">
                    Get In Touch
                </h3>
                <div class="space-y-6">
                    <div class="flex items-start">
                        <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center shrink-0 mr-4">
                            <i class="fas fa-map-marker-alt text-blue-600 text-sm"></i>
                        </div>
                        <p class="text-sm leading-relaxed text-slate-600">
                            Kalvibid, Bhavnagar - 364002,<br>Gujarat, India
                        </p>
                    </div>
                    <div class="flex items-center">
                        <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center shrink-0 mr-4">
                            <i class="fas fa-envelope text-indigo-600 text-sm"></i>
                        </div>
                        <a href="mailto:gyanmanjaribvn@gmail.com"
                            class="text-sm text-slate-600 hover:text-blue-600 transition-colors">gyanmanjaribvn@gmail.com</a>
                    </div>
                    <div class="flex items-start">
                        <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center shrink-0 mr-4">
                            <i class="fas fa-mobile-alt text-slate-600 text-sm"></i>
                        </div>
                        <div class="space-y-1">
                            <a href="tel:+919099941251" class="text-sm text-slate-600 hover:text-blue-600 block">+91
                                90999 41251</a>
                            <a href="tel:+918980015310" class="text-sm text-slate-600 hover:text-blue-600 block">+91
                                89800 15310</a>
                            <!-- <a href="tel:+919429104222" class="text-sm text-slate-600 hover:text-blue-600 block">+91 94291 04222</a> -->
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Classic Footer Bottom -->
        <div class="pt-8 border-t border-slate-200 flex flex-col md:flex-row justify-between items-center gap-4">
            <p class="text-[10px] md:text-xs text-slate-400 text-center md:text-left">
                &copy; <?php echo date('Y'); ?> <span
                    class="text-slate-600 font-semibold uppercase tracking-wider">Gyanmanjari Vidhyapith</span>. All
                rights reserved.
            </p>
            <div class="flex items-center space-x-6">
                <a href="<?php echo BASE_URL; ?>/portal/website/privacy-policy.php"
                    class="text-[10px] uppercase tracking-[0.2em] text-slate-400 hover:text-blue-600 transition-colors">Privacy</a>
                <a href="<?php echo BASE_URL; ?>/portal/website/digital-wallet-policy.php"
                    class="text-[10px] uppercase tracking-[0.2em] text-slate-400 hover:text-blue-600 transition-colors">Wallet
                    Policy</a>
                <a href="#"
                    class="text-[10px] uppercase tracking-[0.2em] text-slate-400 hover:text-blue-600 transition-colors">Terms</a>
                <a href="#"
                    class="text-[10px] uppercase tracking-[0.2em] text-slate-400 hover:text-blue-600 transition-colors">Sitemap</a>
            </div>
        </div>
    </div>
</footer>

<?php if (isset($_GET['preview'])): ?>
    <script>
        // Real-time Preview Listener
        window.addEventListener('message', function (event) {
            if (event.data.type === 'UPDATE_CONTENT') {
                const items = document.querySelectorAll(`[data-cms-key="${event.data.key}"]`);
                items.forEach(item => {
                    if (item.tagName === 'IMG') {
                        item.src = event.data.value;
                    } else if (item.tagName === 'A') {
                        if (event.data.key.includes('link') || event.data.key.includes('url')) {
                            item.href = event.data.value;
                        } else {
                            item.innerText = event.data.value;
                        }
                    } else {
                        item.innerText = event.data.value;
                    }
                });
            }
        });

        // Disable all links in preview mode to prevent navigation
        document.querySelectorAll('a').forEach(a => {
            a.addEventListener('click', e => e.preventDefault());
        });
    </script>
    <?php
endif; ?>

<?php
// Disable inspect on production
$disable_inspect = (defined('ENVIRONMENT') && ENVIRONMENT === 'production');
// Check hostname for gyanmanjari.com
if (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'gyanmanjari.com') !== false) {
    $disable_inspect = true;
}

if ($disable_inspect):
    ?>
    <script>
        document.addEventListener('contextmenu', event => event.preventDefault());
        document.onkeydown = function (e) {
            if (e.keyCode == 123) { // F12
                return false;
            }
            if (e.ctrlKey && e.shiftKey && e.keyCode == 'I'.charCodeAt(0)) { // Ctrl+Shift+I
                return false;
            }
            if (e.ctrlKey && e.shiftKey && e.keyCode == 'C'.charCodeAt(0)) { // Ctrl+Shift+C
                return false;
            }
            if (e.ctrlKey && e.shiftKey && e.keyCode == 'J'.charCodeAt(0)) { // Ctrl+Shift+J
                return false;
            }
            if (e.ctrlKey && e.keyCode == 'U'.charCodeAt(0)) { // Ctrl+U
                return false;
            }
        }
    </script>
    <?php
endif; ?>