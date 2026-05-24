<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once __DIR__ . '/../../common/api_client.php';
require_once PORTAL_GLOBALVARIABLE;

// Restrict access to students
if (!hasRole(ROLE_STUDENT)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = 'My Timetable';
include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>



<div class="container-fluid py-4">
    <!-- Header Summary Card -->
    <div class="card shadow-sm border-0 mb-4 rounded-3 bg-white">
        <div class="card-body p-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
                <h3 class="fw-bold text-dark mb-1">
                    <i class="fas fa-calendar-alt me-2 text-primary"></i> My Class Timetable
                </h3>
                <p class="text-muted mb-0" id="studentDetails">
                    <i class="fas fa-spinner fa-spin me-1"></i> Resolving your division and course details...
                </p>
            </div>
            <div>
                <span class="badge bg-primary rounded-pill px-3 py-2 fs-6 shadow-sm css-timetable-224b51" id="classBadge"></span>
            </div>
        </div>
    </div>

    <!-- Mobile Day Switcher (Visible on small screens) -->
    <div class="d-lg-none mb-3 overflow-auto py-2 d-flex gap-2 css-timetable-0ca503" id="mobileDayTabs">
        <button class="btn btn-outline-secondary mobile-tab-btn active" onclick="switchMobileDay('Monday')">Mon</button>
        <button class="btn btn-outline-secondary mobile-tab-btn" onclick="switchMobileDay('Tuesday')">Tue</button>
        <button class="btn btn-outline-secondary mobile-tab-btn" onclick="switchMobileDay('Wednesday')">Wed</button>
        <button class="btn btn-outline-secondary mobile-tab-btn" onclick="switchMobileDay('Thursday')">Thu</button>
        <button class="btn btn-outline-secondary mobile-tab-btn" onclick="switchMobileDay('Friday')">Fri</button>
        <button class="btn btn-outline-secondary mobile-tab-btn" onclick="switchMobileDay('Saturday')">Sat</button>
    </div>

    <!-- Student Timetable Workspace -->
    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-body p-4">
            <!-- Desktop Layout (Columns side-by-side) -->
            <div class="d-none d-lg-block" id="desktopGrid">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <h6 class="mt-2 text-muted">Loading your weekly schedule...</h6>
                </div>
            </div>

            <!-- Mobile Layout (Single Day List) -->
            <div class="d-lg-none" id="mobileList">
                <!-- Populated dynamically -->
            </div>
        </div>
    </div>
</div>

<?php include '../../include/footer.php'; ?>

<script>
    let timetableData = [];
    let activeMobileDay = 'Monday';

    function getPastelColor(str) {
        let hash = 0;
        for (let i = 0; i < str.length; i++) {
            hash = str.charCodeAt(i) + ((hash << 5) - hash);
        }
        const h = Math.abs(hash % 360);
        return `hsl(${h}, 70%, 45%)`;
    }

    function loadStudentTimetable() {
        $.api.get('academics/timetable').then(response => {
            if (response.success) {
                timetableData = response.data.timetable || [];
                
                if (timetableData.length > 0) {
                    const first = timetableData[0];
                    $('#studentDetails').html(`
                        <i class="fas fa-graduation-cap me-1 text-primary"></i> Enrolled in <strong>${first.course_name} (${first.group_name})</strong> &bull; Academic Year: <strong>${first.academic_year}</strong>
                    `);
                    $('#classBadge').text(`Division ${first.division_name}`).show();
                } else {
                    $('#studentDetails').html('<i class="fas fa-exclamation-circle me-1 text-warning"></i> No lectures scheduled for your division yet.');
                }

                renderDesktopGrid();
                renderMobileList();
            } else {
                showToast('error', 'Error', response.message || 'Failed to load timetable.');
            }
        }).catch(error => {
            showToast('error', 'Connection Error', error.message || 'Error occurred');
        });
    }

    function renderDesktopGrid() {
        const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        let html = `<div class="row row-cols-1 row-cols-lg-6 g-3">`;

        days.forEach(day => {
            const dayLectures = timetableData.filter(t => t.day_of_week === day);

            html += `
                <div class="col">
                    <div class="day-column-student pb-3 h-100 bg-light shadow-xs">
                        <div class="text-center py-3 bg-white border-bottom rounded-3 rounded-bottom-0">
                            <h6 class="mb-0 fw-bold text-dark text-uppercase letter-spacing-1 small">${day}</h6>
                        </div>
                        <div class="px-2 pt-3">
            `;

            if (dayLectures.length === 0) {
                html += `
                    <div class="text-center py-4 text-muted small">
                        <i class="far fa-clock mb-1 d-block"></i> No Classes
                    </div>
                `;
            } else {
                dayLectures.forEach(lec => {
                    const color = getPastelColor(lec.subject_name);
                    html += `
                        <div class="timeline-card p-3 mb-3 border shadow-xs css-timetable-acf3bb">
                            <div class="fw-bold text-dark small text-truncate mb-1" title="${lec.subject_name}">${lec.subject_name}</div>
                            <div class="text-primary small fw-semibold mb-2 css-timetable-d05093">
                                <i class="far fa-clock me-1"></i> ${lec.time_range}
                            </div>
                            <div class="text-muted small text-truncate css-timetable-af89d6">
                                <i class="fas fa-chalkboard-teacher me-1"></i> ${lec.teacher_name}
                            </div>
                            ${lec.room_no ? `<div class="text-muted mt-1 css-timetable-af89d6"><i class="fas fa-map-marker-alt me-1"></i> ${lec.room_no}</div>` : ''}
                        </div>
                    `;
                });
            }

            html += `
                        </div>
                    </div>
                </div>
            `;
        });

        html += `</div>`;
        $('#desktopGrid').html(html);
    }

    function renderMobileList() {
        const dayLectures = timetableData.filter(t => t.day_of_week === activeMobileDay);
        let html = '';

        if (dayLectures.length === 0) {
            html = `
                <div class="empty-state py-5">
                    <i class="far fa-calendar-times fa-3x mb-2 text-muted"></i>
                    <h6 class="fw-bold mb-1">No Classes Scheduled</h6>
                    <p class="text-muted small">Enjoy your day off! No lectures are scheduled for ${activeMobileDay}.</p>
                </div>
            `;
        } else {
            html += `<div class="d-flex flex-column gap-3">`;
            dayLectures.forEach(lec => {
                const color = getPastelColor(lec.subject_name);
                html += `
                    <div class="card border border-light-dark shadow-sm rounded-3 timeline-card css-timetable-a868aa">
                        <div class="card-body p-3 d-flex align-items-center justify-content-between">
                            <div class="css-timetable-60dfc2">
                                <h5 class="fw-bold text-dark mb-1 fs-6">${lec.subject_name}</h5>
                                <div class="text-muted small mb-2">
                                    <i class="fas fa-chalkboard-teacher me-1"></i> ${lec.teacher_name}
                                </div>
                                ${lec.room_no ? `<span class="badge bg-light border text-dark"><i class="fas fa-map-marker-alt me-1 text-primary"></i> ${lec.room_no}</span>` : ''}
                            </div>
                            <div class="text-end">
                                <span class="time-badge shadow-xs d-inline-block small">
                                    <i class="far fa-clock me-1"></i> ${lec.time_range}
                                </span>
                            </div>
                        </div>
                    </div>
                `;
            });
            html += `</div>`;
        }

        $('#mobileList').html(html);
    }

    function switchMobileDay(day) {
        activeMobileDay = day;
        
        // Update active tab buttons styling
        $('#mobileDayTabs button').removeClass('active');
        const dayAbbrs = {
            'Monday': 'Mon', 'Tuesday': 'Tue', 'Wednesday': 'Wed', 
            'Thursday': 'Thu', 'Friday': 'Fri', 'Saturday': 'Sat'
        };
        
        $('#mobileDayTabs button').each(function() {
            if ($(this).text() === dayAbbrs[day]) {
                $(this).addClass('active');
            }
        });

        renderMobileList();
    }

    $(document).ready(function () {
        loadStudentTimetable();
    });
</script>
