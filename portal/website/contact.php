<?php require_once dirname(dirname(__DIR__)) . '/common/constants.php';
require_once ENV_CONFIG_FILE; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - Gyanmanjari Vidyapith</title>
    <link rel="icon" type="image/x-icon" href="<?php echo BASE_URL; ?>/assets/images/logogmn.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/gm-public.css">
</head>

<body class="text-gray-800 bg-slate-70">
    <!-- Header/Navbar -->
    <?php include dirname(dirname(__DIR__)) . '/include/public-header.php'; ?>

    <!-- Contact Section -->
    <section class="relative py-12 md:py-24 overflow-hidden bg-gradient-to-b from-slate-50 via-blue-50/10 to-slate-50">
        <!-- Floating background elements -->
        <div
            class="absolute top-0 right-0 w-96 h-96 bg-blue-100 rounded-full translate-x-1/2 -translate-y-1/2 opacity-20 animate-float">
        </div>
        <div class="absolute bottom-0 left-0 w-64 h-64 bg-indigo-100 rounded-full -translate-x-1/2 translate-y-1/2 opacity-20 animate-float css-contact-bd845f"></div>

        <div class="container mx-auto px-4 relative z-10">
            <!-- Header -->
            <div class="text-center mb-12">
                <div
                    class="inline-block px-3 py-1 mb-3 bg-blue-50 text-blue-600 rounded-full text-xs font-bold uppercase tracking-wider">
                    Connect With Us
                </div>
                <h1 class="text-3xl md:text-5xl font-black mb-4">
                    <span class="bg-clip-text text-transparent bg-gradient-to-r from-blue-600 to-indigo-600">Get In
                        Touch</span>
                </h1>
                <p class="text-gray-500 text-sm md:text-base max-w-xl mx-auto">Reach out to us for any academic or
                    admission-related queries.</p>
            </div>

            <!-- Side-by-Side Layout with Classic Styling -->
            <div class="max-w-7xl mx-auto">
                <div class="bg-white rounded-[2.5rem] shadow-2xl overflow-hidden border border-gray-100">
                    <div class="flex flex-col lg:flex-row">

                        <!-- Left: Contact Info (Classic White Style) -->
                        <div class="lg:w-1/2 p-8 md:p-12 lg:p-16 bg-white shrink-0">
                            <h2 class="text-2xl md:text-3xl font-black text-gray-900 mb-10">Contact Information</h2>

                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-1 gap-8">
                                <!-- Location -->
                                <div class="flex items-start space-x-6 group">
                                    <div
                                        class="w-14 h-14 bg-blue-50 rounded-2xl flex items-center justify-center shrink-0 group-hover:bg-blue-600 transition-colors duration-300 shadow-sm">
                                        <i
                                            class="fas fa-map-marker-alt text-xl text-blue-600 group-hover:text-white"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-bold text-gray-900 mb-1">Our Campus</h3>
                                        <p class="text-gray-500 text-sm leading-relaxed mb-2">Kalvibid, Bhavnagar,
                                            Gujarat 364002, India</p>
                                        <a href="https://maps.app.goo.gl/Yy7pXhT..." target="_blank"
                                            class="text-blue-600 font-bold text-xs uppercase tracking-widest hover:underline">View
                                            on Map →</a>
                                    </div>
                                </div>

                                <!-- Phone -->
                                <div class="flex items-start space-x-6 group">
                                    <div
                                        class="w-14 h-14 bg-indigo-50 rounded-2xl flex items-center justify-center shrink-0 group-hover:bg-indigo-600 transition-colors duration-300 shadow-sm">
                                        <i class="fas fa-phone-alt text-xl text-indigo-600 group-hover:text-white"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-bold text-gray-900 mb-1">Call Us</h3>
                                        <p class="text-gray-500 text-sm leading-relaxed mb-2">+91 94291 04222<br>+91
                                            94291 04333</p>
                                        <a href="tel:+919429104222"
                                            class="text-indigo-600 font-bold text-xs uppercase tracking-widest hover:underline">Call
                                            Now →</a>
                                    </div>
                                </div>

                                <!-- Email -->
                                <div class="flex items-start space-x-6 group">
                                    <div
                                        class="w-14 h-14 bg-sky-50 rounded-2xl flex items-center justify-center shrink-0 group-hover:bg-sky-600 transition-colors duration-300 shadow-sm">
                                        <i class="fas fa-envelope text-xl text-sky-600 group-hover:text-white"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-bold text-gray-900 mb-1">Email Us</h3>
                                        <p class="text-gray-500 text-sm leading-relaxed mb-2">
                                            info@gyanmanjari.com<br>admission@gyanmanjari.com</p>
                                        <a href="mailto:info@gyanmanjari.com"
                                            class="text-sky-600 font-bold text-xs uppercase tracking-widest hover:underline">Send
                                            Email →</a>
                                    </div>
                                </div>

                                <!-- Hours -->
                                <div class="flex items-start space-x-6 group">
                                    <div
                                        class="w-14 h-14 bg-blue-50 rounded-2xl flex items-center justify-center shrink-0 group-hover:bg-blue-600 transition-colors duration-300 shadow-sm">
                                        <i class="fas fa-clock text-xl text-blue-600 group-hover:text-white"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-bold text-gray-900 mb-1">Office Hours</h3>
                                        <p class="text-gray-500 text-sm leading-relaxed mb-2">Mon - Sat: 8:00 AM - 6:00
                                            PM<br>Sunday: Closed</p>
                                        <span
                                            class="text-blue-600 font-bold text-[10px] uppercase tracking-[0.2em]">Open
                                            Now</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right: Form Section -->
                        <div class="lg:w-1/2 p-8 md:p-12 lg:p-16 bg-gray-50/50 border-l border-gray-100">
                            <h2 class="text-2xl md:text-3xl font-black text-gray-900 mb-10">Send a Message</h2>
                            <form action="#" class="space-y-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div class="space-y-2">
                                        <label
                                            class="text-xs font-bold text-gray-400 uppercase tracking-widest ml-1">Full
                                            Name</label>
                                        <input type="text" placeholder="John Doe"
                                            class="w-full px-6 py-4 bg-white border border-gray-100 rounded-2xl focus:outline-none focus:ring-2 focus:ring-blue-500/10 focus:border-blue-500 transition-all text-sm shadow-sm">
                                    </div>
                                    <div class="space-y-2">
                                        <label
                                            class="text-xs font-bold text-gray-400 uppercase tracking-widest ml-1">Phone
                                            Number</label>
                                        <input type="tel" placeholder="+91 00000 00000"
                                            class="w-full px-6 py-4 bg-white border border-gray-100 rounded-2xl focus:outline-none focus:ring-2 focus:ring-blue-500/10 focus:border-blue-500 transition-all text-sm shadow-sm">
                                    </div>
                                </div>
                                <div class="space-y-2">
                                    <label class="text-xs font-bold text-gray-400 uppercase tracking-widest ml-1">Email
                                        Address</label>
                                    <input type="email" placeholder="john@example.com"
                                        class="w-full px-6 py-4 bg-white border border-gray-100 rounded-2xl focus:outline-none focus:ring-2 focus:ring-blue-500/10 focus:border-blue-500 transition-all text-sm shadow-sm">
                                </div>
                                <div class="space-y-2">
                                    <label
                                        class="text-xs font-bold text-gray-400 uppercase tracking-widest ml-1">Inquiry
                                        Type</label>
                                    <div class="relative">
                                        <select
                                            class="w-full px-6 py-4 bg-white border border-gray-100 rounded-2xl focus:outline-none focus:ring-2 focus:ring-blue-500/10 focus:border-blue-500 transition-all text-sm shadow-sm appearance-none text-gray-600">
                                            <option>Admission Inquiry</option>
                                            <option>Academic Query</option>
                                            <option>Other</option>
                                        </select>
                                        <div
                                            class="absolute right-6 top-1/2 -translate-y-1/2 pointer-events-none text-gray-400">
                                            <i class="fas fa-chevron-down text-xs"></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="space-y-2">
                                    <label class="text-xs font-bold text-gray-400 uppercase tracking-widest ml-1">Your
                                        Message</label>
                                    <textarea rows="4" placeholder="How can we help you today?"
                                        class="w-full px-6 py-4 bg-white border border-gray-100 rounded-2xl focus:outline-none focus:ring-2 focus:ring-blue-500/10 focus:border-blue-500 transition-all text-sm shadow-sm"></textarea>
                                </div>
                                <button type="submit"
                                    class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-black py-5 rounded-2xl shadow-xl shadow-blue-500/20 hover:shadow-blue-500/40 transform hover:-translate-y-1 transition-all duration-300 tracking-widest uppercase text-xs">
                                    Send Message
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Minimal Map Section -->
            <div class="max-w-7xl mx-auto mt-12 md:mt-20">
                <div class="rounded-[2.5rem] overflow-hidden shadow-2xl border border-gray-100 h-[350px] md:h-[450px]">
                    <iframe
                        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3706.3465753125047!2d72.12695327601126!3d21.728085663391084!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x395f515043093fa3%3A0x465ac7ed35881ce6!2sGyanmanjari%20Vidhyapith!5e0!3m2!1sen!2sin!4v1744020288951!5m2!1sen!2sin"
                        class="w-full h-full border-0 grayscale-[0.2] hover:grayscale-0 transition-all duration-700"
                        allowfullscreen="" loading="lazy">
                    </iframe>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <?php include dirname(dirname(__DIR__)) . '/include/public-footer.php'; ?>
</body>

</html>