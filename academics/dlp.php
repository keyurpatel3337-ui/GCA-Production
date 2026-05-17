<?php
header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/../common/constants.php';
require_once ENV_CONFIG_FILE;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Distance Learning Program - Gyanmanjari Vidyapith</title>
    <link rel="icon" type="image/x-icon" href="../assets/images/logogmn.png">
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Anek+Gujarati:wght@100..800&family=Jost:ital,wght@0,100..900;1,100..900&display=swap"
        rel="stylesheet">
    <!-- Link to external CSS file -->
    <link rel="stylesheet" href="../assets/css/gm-public.css">
    <style>
        .font-gujarati {
            font-family: 'Anek Gujarati', 'Jost', sans-serif;
        }
    </style>
</head>

<body class="text-gray-800 bg-slate-50">
    <!-- Header/Navbar -->
    <?php include __DIR__ . '/../include/public-header.php'; ?>

    <!-- DLP Content Section -->
    <section
        class="relative py-12 md:py-24 overflow-hidden bg-gradient-to-b from-slate-50 via-indigo-50/30 to-slate-50">
        <!-- Decorative background elements -->
        <div class="absolute top-0 right-0 w-96 h-96 bg-indigo-100 rounded-full translate-x-1/2 -translate-y-1/2 opacity-20 animate-float"
            style="animation-delay: 0s;"></div>
        <div class="absolute bottom-0 left-0 w-64 h-64 bg-blue-100 rounded-full -translate-x-1/2 translate-y-1/2 opacity-20 animate-float"
            style="animation-delay: 1s;"></div>
        <div class="absolute top-1/4 left-10 w-40 h-40 bg-purple-100 rounded-full opacity-15 animate-float"
            style="animation-delay: 2s;"></div>

        <div class="container mx-auto px-4 relative z-10">
            <!-- Page Header -->
            <div class="text-center mb-12 md:mb-20">
                <div
                    class="inline-block px-4 py-1.5 mb-4 bg-indigo-50 text-indigo-600 rounded-full text-xs md:text-sm font-bold tracking-wide uppercase">
                    Remote Learning Excellence
                </div>
                <h1 class="text-3xl md:text-5xl lg:text-7xl font-black mb-6">
                    <span class="bg-clip-text text-transparent bg-gradient-to-r from-indigo-600 to-blue-600">Distance
                        Learning Program</span>
                </h1>
                <p class="text-gray-500 text-sm md:text-lg max-w-2xl mx-auto">Bringing Gyanmanjari's expertise to your
                    doorstep with our comprehensive DLP curriculum.</p>
            </div>

            <!-- Main Content Card -->
            <div class="max-w-6xl mx-auto mb-16 md:mb-28">
                <div class="bg-white rounded-[2.5rem] shadow-2xl overflow-hidden border border-gray-100">
                    <div class="flex flex-col lg:flex-row">
                        <!-- Image Side -->
                        <div
                            class="lg:w-2/5 p-6 md:p-10 bg-gradient-to-br from-indigo-50 to-white flex items-start justify-center">
                            <div class="sticky top-24">
                                <div class="relative group">
                                    <div
                                        class="absolute -inset-1 bg-gradient-to-r from-indigo-600 to-blue-600 rounded-3xl opacity-20 group-hover:opacity-30 blur transition duration-300">
                                    </div>
                                    <img src="../assets/images/dlp.jpeg" alt="DLP Program"
                                        class="relative rounded-3xl w-full h-auto object-cover border-4 border-white shadow-2xl">
                                </div>
                                <div class="mt-8 grid grid-cols-2 gap-4">
                                    <div class="bg-indigo-50/50 p-4 rounded-2xl border border-indigo-100 text-center">
                                        <p class="text-2xl font-black text-indigo-600">100%</p>
                                        <p class="text-[10px] uppercase font-bold text-gray-400 tracking-widest">
                                            Syllabus</p>
                                    </div>
                                    <div class="bg-blue-50/50 p-4 rounded-2xl border border-blue-100 text-center">
                                        <p class="text-2xl font-black text-blue-600">Top</p>
                                        <p class="text-[10px] uppercase font-bold text-gray-400 tracking-widest">Faculty
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Content Side -->
                        <div class="lg:w-3/5 p-8 md:p-12 lg:p-16">
                            <h2 class="text-3xl font-black text-gray-900 mb-8 border-l-4 border-indigo-600 pl-6">What is
                                DLP?</h2>

                            <div class="space-y-6 text-gray-700 leading-relaxed font-gujarati">
                                <p class="text-lg md:text-xl">
                                    આપ જાણો છો કે વર્તમાન સમયમાં ધોરણ ૧૨ સાયન્સ પછી વિદ્યાર્થીઓ વિશાળ ફલક પર
                                    એન્જીનિયરિંગ તથા મેડિકલ ક્ષેત્રમાં પ્રવેશ લેવા ઈચ્છુક હોય છે. જેમાં પ્રવેશ મેળવવા
                                    હાલના સમય પ્રમાણે વિદ્યાર્થીએ એન્જીનિયરિંગ માટે <span
                                        class="text-indigo-600 font-bold">JEE / GUJCET</span> તથા મેડિકલ માટે <span
                                        class="text-blue-600 font-bold">NEET</span> ની Entrance test આપવી પડે. આ પરીક્ષા
                                    All India level એક સાથે યોજાય છે. જેનો સિલેબસ NCERT પ્રમાણે હોય છે જેના પરિણામને
                                    આધારે ઉપરોક્ત શાખાઓમાં પ્રવેશ મેળવી શકાય છે.
                                </p>

                                <p class="text-lg md:text-xl">
                                    NEET / JEEનો અભ્યાસક્રમ NCERT દ્વારા તૈયાર કરવામાં આવેલ હોય છે. તે અભ્યાસક્રમ અંગેની
                                    તમામ માહિતી કોઈ એક જ બૂક દ્વારા ઉપલબ્ધ થવું ઘણું મુશ્કેલ હોય છે. જેના માટે ઘણી
                                    રેફરન્સ બૂકના અભ્યાસ દ્વારા અલગ અલગ ટોપીકની ઊંડાણપૂર્વક તૈયારી કરવી પડે છે. ભાવનગરની
                                    જ્ઞાનમંજરી વિદ્યાપીઠ NEET / JEE અંગેની સચોટ તૈયારી માટે ગુજરાતની અગ્રગણ્ય સંસ્‍થા
                                    છે. તેના અનુભવી શિક્ષકો દ્વારા તે અંગેનું Study Materials તૈયાર કરવામાં આવેલ છે. જે
                                    અત્યાર સુધી સંસ્થાના જ વિદ્યાર્થીઓને ઉપલબ્ધ થતું હતું. પરંતુ સંસ્થા દ્વારા ચાલુ
                                    વર્ષે <span class="text-indigo-600 font-bold italic">Distance Learning Program
                                        (DLP)</span> અંતર્ગત આ સ્ટડી મટિરિયલ્સ અને ટેસ્ટ સિરીઝ ગુજરાતના તમામ વિદ્યાર્થીઓ
                                    સમક્ષ મુકતા હર્ષની લાગણી અનુભવીએ છીએ.
                                </p>

                                <p class="text-lg md:text-xl">
                                    આ સ્ટડી મટિરિયલ્સ, ટેસ્ટ સિરીઝ અને સંસ્થાના સચોટ માર્ગદર્શન દ્વારા 10 વર્ષના
                                    ટૂંકાગાળમાં <span class="text-blue-600 font-bold text-2xl">MBBS</span> માં આશરે ૯૮૩
                                    વિદ્યાર્થીઓ તેમજ એન્જીનિયરિંગ ક્ષેત્રે ખ્યાતનામ NITS / IIT'S /એન્જીનિયરિંગ કોલેજમાં
                                    આશરે ૩૫૦૦ વિદ્યાર્થીઓ ઉચ્ચ શિક્ષણમાં પ્રવેશ મેળવી ચૂક્યા છે. જ્ઞાનમંજરી કેરિયર
                                    એકેડેમીના "Distance Learning Program" (DLP) માં આપને સંસ્થાના મુખ્ય તજજ્ઞો દ્વારા
                                    તૈયાર કરાયેલ NEET અને GUJCET / JEE / BOARD નું સ્ટડી મટિરિયલ્સ, ટેસ્ટ સિરીઝ, MCQ's
                                    વગેરે આપવામાં આવશે. જે આપને તૈયારી માટે ખુબ જ ઉપયોગી થશે.
                                </p>

                                <p class="text-lg md:text-xl">
                                    મેડિકલ, એન્જીન્યરીંગ, JEE, NEET, BOARD, GUJCET વગેરે પ્રકારની એકઝામીનેશન ક્ષેત્રે
                                    ખુબજ ટૂંકાગાળામાં દેશની ટોપ ક્લાસ સંસ્થાઓમાં જ્ઞાનમંજરી સ્કૂલના વિદ્યાર્થીઓ મેળવી
                                    ચુક્યા છે.
                                </p>

                                <p class="text-lg md:text-xl">
                                    આ મુદ્દાને ધ્યાનમાં લઈને, હવેથી જે વિદ્યાર્થીઓ મેડિકલ, એન્જીન્યરીંગ, JEE, NEET,
                                    BOARD, GUJCET ક્ષેત્રમાં એકદમ સરળતાથી ૧૦૦% સફળતા મેળવવા ઇચ્છતા હોય તેવા મહેનતુ
                                    વિદ્યાર્થીઓ માટે DLP (ડિસ્ટન્સ લર્નિંગ પ્રોગ્રામ) દ્વારા સંસ્થાના મુખ્ય વિષયના,
                                    અનુભવી શિક્ષકો દ્વારા તૈયાર થયેલ મટીરીયલ્સ, ટેસ્ટ સિરીઝ, અને MCQ હવે આપને સરળતાથી
                                    મળશે. આ સંદર્ભે વિશેષ માહિતી માટે નીચે દર્શાવેલ ફોન નંબર તેમજ વેબસાઇટ પર સંપર્ક
                                    કરવો.
                                </p>

                                <div
                                    class="bg-indigo-50/50 p-8 rounded-3xl border border-indigo-100 mt-10 shadow-inner">
                                    <h3 class="font-bold text-indigo-900 mb-4 flex items-center text-xl">
                                        <i class="fas fa-map-marker-alt mr-3"></i> સંપર્ક માહિતી
                                    </h3>
                                    <div class="space-y-3">
                                        <p class="text-gray-800">
                                            <span class="font-bold">સ્થળ:</span> જ્ઞાનમંજરી કેરિયર અકડેમી, પ્લોટ નં.
                                            B-6300, જ્ઞાનમંજરી વિદ્યાપીઠ પાસે, કાળિયાબીડ, ભાવનગર.
                                        </p>
                                        <p class="text-gray-800">
                                            <span class="font-bold">મોબાઇલ:</span> <a href="tel:7069020211"
                                                class="hover:text-indigo-600">70690 20211</a>, <a href="tel:7069020212"
                                                class="hover:text-indigo-600">70690 20212</a>, <a href="tel:7069020213"
                                                class="hover:text-indigo-600">70690 20213</a>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats/Impact Section -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 max-w-5xl mx-auto mb-16 md:mb-28 px-4">
                <div
                    class="bg-white p-10 rounded-[2.5rem] shadow-xl border border-indigo-50 flex flex-col items-center text-center group hover:bg-indigo-600 transition-all duration-500 transform hover:-translate-y-2">
                    <div
                        class="w-20 h-20 bg-indigo-50 rounded-2xl flex items-center justify-center mb-6 group-hover:bg-white/20 transition-colors">
                        <i class="fas fa-user-md text-indigo-600 text-4xl group-hover:text-white"></i>
                    </div>
                    <h3 class="text-5xl font-black text-gray-900 mb-2 group-hover:text-white">983+</h3>
                    <p class="text-gray-500 font-bold uppercase tracking-wider group-hover:text-indigo-100">MBBS
                        Placements</p>
                </div>
                <div
                    class="bg-white p-10 rounded-[2.5rem] shadow-xl border border-blue-50 flex flex-col items-center text-center group hover:bg-blue-600 transition-all duration-500 transform hover:-translate-y-2">
                    <div
                        class="w-20 h-20 bg-blue-50 rounded-2xl flex items-center justify-center mb-6 group-hover:bg-white/20 transition-colors">
                        <i class="fas fa-university text-blue-600 text-4xl group-hover:text-white"></i>
                    </div>
                    <h3 class="text-5xl font-black text-gray-900 mb-2 group-hover:text-white">3500+</h3>
                    <p class="text-gray-500 font-bold uppercase tracking-wider group-hover:text-blue-100">Engineering
                        Success</p>
                </div>
            </div>

            <!-- Final CTA -->
            <div
                class="text-center bg-white rounded-[3rem] p-12 md:p-20 shadow-2xl border border-gray-100 max-w-5xl mx-auto relative overflow-hidden">
                <div
                    class="absolute top-0 right-0 w-64 h-64 bg-indigo-50/50 rounded-full blur-3xl translate-x-1/2 -translate-y-1/2">
                </div>
                <div class="relative z-10">
                    <h2 class="text-3xl md:text-4xl font-black text-gray-900 mb-6">Ready to Start Your Journey?</h2>
                    <p class="text-gray-500 text-lg mb-10 max-w-2xl mx-auto">Get access to professional study materials
                        and test series prepared by Gujarat's finest educators.</p>
                    <a href="../contact.php"
                        class="inline-flex items-center justify-center px-10 py-5 bg-gradient-to-r from-indigo-600 to-blue-600 text-white font-black rounded-2xl shadow-xl shadow-indigo-500/20 hover:shadow-indigo-500/40 hover:scale-105 transition-all duration-300 group tracking-widest uppercase text-sm">
                        <span>Enquire for DLP Program</span>
                        <i class="fas fa-arrow-right ml-3 group-hover:translate-x-2 transition-transform"></i>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <?php include __DIR__ . '/../include/public-footer.php'; ?>
</body>

</html>