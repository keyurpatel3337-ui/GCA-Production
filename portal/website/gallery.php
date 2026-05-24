<?php
require_once dirname(dirname(__DIR__)) . '/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;

// Initialize Database Operations
$dbOps = new DatabaseOperations();

// Fetch Gallery items - Using Operation.php
try {
    $galleryItems = $dbOps->select(
        'tbl_website_gallery',
        ['*'],
        ['is_active' => 1],
        'display_order ASC'
    );
    if ($galleryItems === false) {
        $galleryItems = [];
    }
} catch (Exception $e) {
    $galleryItems = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Photo Gallery | Gyanmanjari Vidyapith</title>
    <link rel="icon" type="image/x-icon" href="<?php echo BASE_URL; ?>/assets/images/logogmn.png">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Google Fonts -->
    <link
        href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Playfair+Display:wght@700;800;900&display=swap"
        rel="stylesheet">

    <!-- Lightbox CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/glightbox/3.2.0/css/glightbox.min.css">

    
</head>

<body class="text-slate-900 overflow-x-hidden">
    <!-- Header -->
    <?php include dirname(dirname(__DIR__)) . '/include/public-header.php'; ?>

    <!-- Hero Section -->
    <section class="relative py-24 md:py-32 overflow-hidden bg-white">
        <!-- Floating shapes -->
        <div
            class="absolute top-0 right-0 w-96 h-96 bg-blue-100/50 rounded-full translate-x-1/2 -translate-y-1/2 blur-3xl">
        </div>
        <div
            class="absolute bottom-0 left-0 w-80 h-80 bg-indigo-100/50 rounded-full -translate-x-1/2 translate-y-1/2 blur-3xl">
        </div>

        <div class="container mx-auto px-4 relative z-10">
            <div class="text-center max-w-4xl mx-auto">
                <div
                    class="inline-block px-4 py-1.5 mb-6 bg-blue-50 text-blue-600 rounded-full text-xs font-bold tracking-widest uppercase">
                    Visual Journey
                </div>
                <h1 class="text-5xl md:text-7xl font-black mb-8 heading-font leading-tight">
                    Capturing <span
                        class="text-transparent bg-clip-text bg-gradient-to-r from-blue-600 to-indigo-600">Excellence</span>
                    In Every Frame
                </h1>
                <p class="text-slate-500 text-lg md:text-xl font-medium leading-relaxed max-w-2xl mx-auto">
                    Explore the vibrant life at Gyanmanjari Vidyapith through our comprehensive photo collection of
                    events, achievements, and daily learning.
                </p>
            </div>
        </div>
    </section>

    <!-- Gallery Grid -->
    <section class="py-12 md:py-24 bg-slate-50">
        <div class="container mx-auto px-4">
            <?php if (empty($galleryItems)): ?>
                <div class="text-center py-20 glass-card rounded-3xl">
                    <div class="w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-images text-slate-400 text-3xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-slate-800 mb-2">No Images Yet</h3>
                    <p class="text-slate-500">We are currently updating our moments. Check back soon!</p>
                </div>
            <?php else: ?>
                <div class="columns-1 md:columns-2 lg:columns-3 xl:columns-4 gap-6 space-y-6">
                    <?php foreach ($galleryItems as $item): ?>
                        <div class="break-inside-avoid">
                            <a href="<?php echo BASE_URL . '/' . $item['image_path']; ?>"
                                class="glightbox block gallery-item relative overflow-hidden rounded-3xl group shadow-lg hover:shadow-2xl transition-all duration-500"
                                data-gallery="main-gallery" data-title="Gyanmanjari Moment">
                                <img src="<?php echo BASE_URL . '/' . $item['image_path']; ?>" alt="Gyanmanjari Vidyapith"
                                    class="w-full h-auto object-cover">

                                <!-- Overlay -->
                                <div
                                    class="overlay absolute inset-0 bg-gradient-to-t from-slate-900/80 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex flex-col justify-end p-8">
                                    <h4
                                        class="text-white font-bold text-xl mb-1 translate-y-4 group-hover:translate-y-0 transition-transform duration-500">
                                        Our Moment
                                    </h4>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-20 relative overflow-hidden">
        <div class="container mx-auto px-4">
            <div
                class="bg-gradient-to-br from-blue-600 to-indigo-700 rounded-[3rem] p-12 md:p-20 text-center text-white shadow-2xl relative overflow-hidden">
                <!-- Decorative elements -->
                <div class="absolute top-0 right-0 w-64 h-64 bg-white/10 rounded-full translate-x-1/2 -translate-y-1/2">
                </div>
                <div
                    class="absolute bottom-0 left-0 w-48 h-48 bg-white/10 rounded-full -translate-x-1/2 translate-y-1/2">
                </div>

                <div class="relative z-10 max-w-2xl mx-auto">
                    <h2 class="text-4xl md:text-5xl font-black mb-6 heading-font">Join Our Legacy</h2>
                    <p class="text-blue-100 text-lg md:text-xl mb-10 leading-relaxed font-medium">
                        Be a part of our next big moment. Admissions are now open for the academic year 2026-27.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <a href="<?php echo BASE_URL; ?>/11Reg.php"
                            class="bg-white text-blue-600 hover:bg-blue-50 px-10 py-4 rounded-2xl font-bold text-lg transition-all shadow-xl hover:-translate-y-1">
                            Register Now
                        </a>
                        <a href="<?php echo BASE_URL; ?>/portal/website/contact.php"
                            class="bg-blue-500/30 backdrop-blur-md text-white border border-white/30 hover:bg-blue-500/40 px-10 py-4 rounded-2xl font-bold text-lg transition-all shadow-xl hover:-translate-y-1">
                            Contact Admission
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <?php include dirname(dirname(__DIR__)) . '/include/public-footer.php'; ?>

    <!-- Lightbox JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/glightbox/3.2.0/js/glightbox.min.js"></script>
    <script>
        const lightbox = GLightbox({
            touchNavigation: true,
            loop: true,
            autoplayVideos: true
        });
    </script>
</body>

</html>