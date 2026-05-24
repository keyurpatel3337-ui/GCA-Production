<!-- Facilities Section - Redesigned with Icons and Animations -->
<style>
    .facility-btn {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        flex: 0 0 auto;
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        color: rgba(255, 255, 255, 0.8);
        outline: none !important;
    }

    .facility-btn.active {
        background-color: white;
        color: #1e3a8a;
        border: none !important;
        transform: translateY(-5px) scale(1.05);
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3), 0 8px 10px -6px rgba(0, 0, 0, 0.3);
    }

    .facility-btn:not(.active):hover {
        background-color: rgba(255, 255, 255, 0.2);
        border-color: rgba(255, 255, 255, 0.4);
        color: white;
    }

    /* Custom scrollbar for the horizontal menu */
    .facility-scroll-container::-webkit-scrollbar {
        height: 4px;
    }

    .facility-scroll-container::-webkit-scrollbar-track {
        background: rgba(255, 255, 255, 0.1);
    }

    .facility-scroll-container::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.3);
        border-radius: 10px;
    }

    .facility-content-animate {
        animation: fadeInScale 0.4s ease-out forwards;
    }

    @keyframes fadeInScale {
        from {
            opacity: 0;
            transform: scale(0.98) translateY(10px);
        }

        to {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }

    .icon-wrapper {
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        margin-bottom: 8px;
        background: rgba(255, 255, 255, 0.1);
        transition: all 0.3s ease;
    }

    .facility-btn.active .icon-wrapper {
        background: rgba(30, 58, 138, 0.1);
        color: #1e3a8a;
    }
</style>

<div class="max-w-6xl mx-auto px-4 py-12">
    <div class="text-center mb-16">
        <div class="inline-block px-4 py-1.5 mb-4 bg-white/10 text-white rounded-full text-sm font-bold tracking-wide uppercase reveal-content">
            World-Class Infrastructure
        </div>
        <h2 class="text-4xl md:text-5xl font-black text-white mb-4 char-animate">
            <span class="bg-clip-text text-transparent bg-gradient-to-r from-white to-blue-100">Facilities for a Brighter Future</span>
        </h2>
        <div class="w-24 h-1 bg-white/30 mx-auto rounded-full reveal-content"></div>
    </div>

    <div class="facility-scroll-container flex flex-nowrap lg:flex-wrap justify-start lg:justify-center gap-3 mb-12 overflow-x-auto pt-6 pb-4 lg:pb-0 reveal-content">
        <button class="facility-btn active flex flex-col items-center justify-center p-3 rounded-2xl w-28 md:w-36 text-xs md:text-sm font-bold" data-content="computer_lab">
            <div class="icon-wrapper"><i class="fas fa-laptop-code text-lg md:text-xl"></i></div>
            <span>Computer Lab</span>
        </button>
        <button class="facility-btn flex flex-col items-center justify-center p-3 rounded-2xl w-28 md:w-36 text-xs md:text-sm font-bold" data-content="library">
            <div class="icon-wrapper"><i class="fas fa-book text-lg md:text-xl"></i></div>
            <span>Library</span>
        </button>
        <button class="facility-btn flex flex-col items-center justify-center p-3 rounded-2xl w-28 md:w-36 text-xs md:text-sm font-bold" data-content="lab_facility">
            <div class="icon-wrapper"><i class="fas fa-flask text-lg md:text-xl"></i></div>
            <span>Lab Facility</span>
        </button>
        <button class="facility-btn flex flex-col items-center justify-center p-3 rounded-2xl w-28 md:w-36 text-xs md:text-sm font-bold" data-content="playground">
            <div class="icon-wrapper"><i class="fas fa-running text-lg md:text-xl"></i></div>
            <span>Playground</span>
        </button>
        <button class="facility-btn flex flex-col items-center justify-center p-3 rounded-2xl w-28 md:w-36 text-xs md:text-sm font-bold" data-content="solution_desk">
            <div class="icon-wrapper"><i class="fas fa-headset text-lg md:text-xl"></i></div>
            <span>Solution Desk</span>
        </button>
        <button class="facility-btn flex flex-col items-center justify-center p-3 rounded-2xl w-28 md:w-36 text-xs md:text-sm font-bold" data-content="dining_hall">
            <div class="icon-wrapper"><i class="fas fa-utensils text-lg md:text-xl"></i></div>
            <span>Dining Hall</span>
        </button>
        <button class="facility-btn flex flex-col items-center justify-center p-3 rounded-2xl w-28 md:w-36 text-xs md:text-sm font-bold" data-content="counseling">
            <div class="icon-wrapper"><i class="fas fa-user-friends text-lg md:text-xl"></i></div>
            <span>Counseling</span>
        </button>
        <button class="facility-btn flex flex-col items-center justify-center p-3 rounded-2xl w-28 md:w-36 text-xs md:text-sm font-bold" data-content="security">
            <div class="icon-wrapper"><i class="fas fa-shield-alt text-lg md:text-xl"></i></div>
            <span>Safety & Security</span>
        </button>
    </div>

    <div class="max-w-3xl mx-auto reveal-content">
        <div class="bg-white/20 backdrop-blur-xl border border-white/30 rounded-3xl p-8 md:p-12 shadow-2xl relative overflow-hidden" id="facility-content-wrapper">
            <div id="facility-content" class="facility-content-animate">
                <h3 class="text-2xl font-bold text-white mb-4 flex items-center">
                    <i class="fas fa-laptop-code text-white mr-3"></i>
                    Computer Lab
                </h3>
                <p class="text-white/90 text-lg leading-relaxed">Now a-days computer has become a necessity for every child in todays world. And this is what Gyanmanjari wants their students to growth with. Computer in GM Bhavnagar is fun as we impart education, through interactive techniques using computer CDs, LCD Projectors and Multimedia.</p>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    const facilityData = {
        computer_lab: {
            title: "Computer Lab",
            icon: "fa-laptop-code",
            text: "Now a-days computer has become a necessity for every child in today's world. And this is what Gyanmanjari wants their students to grow with. Computer in GM Bhavnagar is fun as we impart education through interactive techniques using computer CDs, LCD Projectors and Multimedia."
        },
        library: {
            title: "Library",
            icon: "fa-book",
            text: "Gyanmanjari Vidyapith has 450 partially isolated self study tables in which 11-12(Sc.). students do their study during self study hours since this is a day school."
        },
        lab_facility: {
            title: "Lab Facility",
            icon: "fa-flask",
            text: "we should keeping in a mind that practicals are the backbone to understand science. Gyanmanjari has facilitated labs for Physics, Chemistry, Biology, Maths under the lab assistants. laboratory facilities for the students bright future & development of various skills."
        },
        playground: {
            title: "Playground",
            icon: "fa-running",
            text: "Health is Wealth. A healthy body leads to healthy mind. So Gyanmanjari provides extensive training and coaching in various sports like Athletics , Basketball , Cricket , Football , Kabaddi ,Kho-Kho,Volleyball,Table Tennis,Badminton,Chess,Caroms,Yoga,Karate, and Skating."
        },
        solution_desk: {
            title: "Solution Desk",
            icon: "fa-headset",
            text: "Sometimes student feel fear during the lecture and he is not able ask his query. so, to help students to come out of this fear and to get their queries solved, GYANMANJARI has specially designed one system called Difficulty Solution Desk for the purpose of solution to each query."
        },
        dining_hall: {
            title: "Dining Hall",
            icon: "fa-utensils",
            text: "Since we are a school which provides self study facility after school hours,Our Trust has established huge dining facility besides the school area.We provide hygienic food with well planned menu.More important is our Trustees,all teachers and staffers take the same food with the students."
        },
        counseling: {
            title: "Counseling",
            icon: "fa-user-friends",
            text: "As per the need hierarchy theory or basic needs theory by greatest management personalities Abraham Maslow who gives the Human motivational theory in the world."
        },
        security: {
            title: "Safety & Security",
            icon: "fa-shield-alt",
            text: "For the safety of the students the campus is covered with CCTV CAMERAS, FIRE EXTINGUISHER and well trained Security Staff."
        }
    };

    $('.facility-btn').click(function() {
        if ($(this).hasClass('active')) return;

        $('.facility-btn').removeClass('active');
        $(this).addClass('active');

        const key = $(this).data('content');
        const facility = facilityData[key];

        const contentArea = $('#facility-content');

        // Remove animation class to re-trigger it
        contentArea.removeClass('facility-content-animate');

        // Force reflow
        void contentArea[0].offsetWidth;

        contentArea.html(`
            <h3 class="text-2xl font-bold text-white mb-4 flex items-center">
                <i class="fas ${facility.icon} text-white mr-3"></i>
                ${facility.title}
            </h3>
            <p class="text-white/90 text-lg leading-relaxed">${facility.text}</p>
        `).addClass('facility-content-animate');
    });
</script>