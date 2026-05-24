<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once __DIR__ . '/../../common/api_client.php';
require_once PORTAL_GLOBALVARIABLE;

// Restrict write access to admins, principal, establishment, and department heads
if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE, ROLE_ESTABLISHMENT, ROLE_DEPT_HEAD])) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$apiClient = new APIClient();
$response = $apiClient->get('academics/timetable');

$dropdowns = $response['success'] ? $response['data']['dropdowns'] : [];
$courses = $dropdowns['courses'] ?? [];
$groups = $dropdowns['groups'] ?? [];
$divisions = $dropdowns['divisions'] ?? [];
$subjects = $dropdowns['subjects'] ?? [];
$teachers = $dropdowns['teachers'] ?? [];
$academic_years = $dropdowns['academic_years'] ?? [];

$page_title = 'Timetable Management';
include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>



<div class="container-fluid py-4">
    <!-- Filter Panel -->
    <div class="card shadow-sm border-0 mb-4 rounded-3">
        <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
            <h5 class="card-title mb-0 fw-bold text-dark">
                <i class="fas fa-calendar-alt me-2 text-primary"></i> Timetable Management
            </h5>
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3" onclick="exportTimetable()">
                    <i class="fas fa-file-excel me-1"></i> Export Excel
                </button>
                <button type="button" class="btn btn-primary btn-sm rounded-pill px-3 css-timetable-224b51" id="addBtn" onclick="openAddModal()">
                    <i class="fas fa-plus me-1"></i> Add Lecture
                </button>
            </div>
        </div>
        <div class="card-body bg-light-alt">
            <form id="filterForm" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label fw-bold">Course <span class="text-danger">*</span></label>
                    <select name="course_id" id="filter_course_id" class="form-select rounded-3" required>
                        <option value="">-- Select Course --</option>
                        <?php foreach ($courses as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['course_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Group <span class="text-danger">*</span></label>
                    <select name="group_id" id="filter_group_id" class="form-select rounded-3" required>
                        <option value="">-- Select Group --</option>
                        <?php foreach ($groups as $g): ?>
                            <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['group_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Division <span class="text-danger">*</span></label>
                    <select name="division_id" id="filter_division_id" class="form-select rounded-3" required>
                        <option value="">-- Select Division --</option>
                        <?php foreach ($divisions as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['division_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Academic Year <span class="text-danger">*</span></label>
                    <select name="academic_year" id="filter_academic_year" class="form-select rounded-3" required>
                        <option value="">-- Select Year --</option>
                        <?php foreach ($academic_years as $y): ?>
                            <option value="<?= htmlspecialchars($y['academic_year']) ?>" <?= $y['is_current'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($y['academic_year']) ?> <?= $y['is_current'] ? '(Current)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <!-- Timetable Grid -->
    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-body p-4">
            <div id="timetableWrapper">
                <div class="empty-state">
                    <i class="fas fa-calendar-week fa-4x mb-3 text-muted"></i>
                    <h5 class="fw-bold">No Schedule Displayed</h5>
                    <p class="text-muted text-center css-timetable-fbe98b">
                        Please select a Course, Group, Division, and Academic Year from the filters above to load the schedule workspace.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="scheduleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow rounded-3">
            <div class="modal-header border-bottom py-3">
                <h5 class="modal-title fw-bold" id="modalTitle">
                    <i class="fas fa-plus me-2 text-primary"></i> Add Schedule Slot
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="scheduleForm" method="POST">
                <input type="hidden" name="id" id="slot_id">
                <input type="hidden" name="course_id" id="form_course_id">
                <input type="hidden" name="group_id" id="form_group_id">
                <input type="hidden" name="division_id" id="form_division_id">
                <input type="hidden" name="academic_year" id="form_academic_year">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Day of the Week <span class="text-danger">*</span></label>
                        <select name="day_of_week" id="form_day_of_week" class="form-select" required>
                            <option value="">-- Select Day --</option>
                            <option value="Monday">Monday</option>
                            <option value="Tuesday">Tuesday</option>
                            <option value="Wednesday">Wednesday</option>
                            <option value="Thursday">Thursday</option>
                            <option value="Friday">Friday</option>
                            <option value="Saturday">Saturday</option>
                            <option value="Sunday">Sunday</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Subject <span class="text-danger">*</span></label>
                        <select name="subject_id" id="form_subject_id" class="form-select" required>
                            <option value="">-- Select Subject --</option>
                            <?php foreach ($subjects as $s): ?>
                                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['subject_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Teacher / Faculty <span class="text-danger">*</span></label>
                        <select name="teacher_id" id="form_teacher_id" class="form-select" required>
                            <option value="">-- Select Teacher --</option>
                            <?php foreach ($teachers as $t): ?>
                                <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Start Time <span class="text-danger">*</span></label>
                            <input type="time" name="start_time" id="form_start_time" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">End Time <span class="text-danger">*</span></label>
                            <input type="time" name="end_time" id="form_end_time" class="form-control" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Room / Classroom Number</label>
                        <input type="text" name="room_no" id="form_room_no" class="form-control" placeholder="e.g. Room 102, Lab A">
                    </div>

                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input type="checkbox" name="is_active" value="1" class="form-check-input" id="form_active" checked>
                            <label class="form-check-label fw-bold" for="form_active">Active Status</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top py-3">
                    <button type="button" class="btn btn-outline-danger me-auto css-timetable-224b51" id="deleteSlotBtn" onclick="deleteSlot()">
                        <i class="fas fa-trash me-1"></i> Delete
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary" id="saveSlotBtn">Save Schedule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../include/footer.php'; ?>

<!-- SheetJS Export Library -->
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>

<script>
    let timetableData = [];

    // Auto-hash subject strings to yield beautiful pastel border colors
    function getPastelColor(str) {
        let hash = 0;
        for (let i = 0; i < str.length; i++) {
            hash = str.charCodeAt(i) + ((hash << 5) - hash);
        }
        const h = Math.abs(hash % 360);
        return `hsl(${h}, 70%, 45%)`; // Rich Tailored HSL Colors
    }

    // Load timetable dynamically based on filters
    function loadTimetable() {
        const courseId = $('#filter_course_id').val();
        const groupId = $('#filter_group_id').val();
        const divisionId = $('#filter_division_id').val();
        const academicYear = $('#filter_academic_year').val();

        if (!courseId || !groupId || !divisionId || !academicYear) {
            $('#addBtn').hide();
            return;
        }

        $('#addBtn').show();
        
        // Render beautiful Loading state
        $('#timetableWrapper').html(`
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status"></div>
                <h6 class="mt-2 text-muted">Retrieving weekly schedule...</h6>
            </div>
        `);

        $.api.get('academics/timetable', {
            course_id: courseId,
            group_id: groupId,
            division_id: divisionId,
            academic_year: academicYear
        }).then(response => {
            if (response.success) {
                timetableData = response.data.timetable || [];
                renderGrid();
            } else {
                showToast('error', 'Error', response.message || 'Failed to fetch timetable');
            }
        }).catch(error => {
            showToast('error', 'Error', error.message || 'Connection error');
        });
    }

    // Render 6-day timeline grid
    function renderGrid() {
        const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        let html = `<div class="row row-cols-1 row-cols-lg-6 g-3">`;

        days.forEach(day => {
            const dayLectures = timetableData.filter(t => t.day_of_week === day);
            
            html += `
                <div class="col">
                    <div class="day-column pb-3 h-100 bg-light border border-light-dark shadow-xs">
                        <div class="day-header text-center py-3 bg-white border-bottom border-light">
                            <h6 class="mb-0 fw-bold text-dark text-uppercase letter-spacing-1">${day}</h6>
                            <span class="badge bg-light border text-dark mt-1">${dayLectures.length} Class(es)</span>
                        </div>
                        <div class="px-2 pt-3 day-card-container">
            `;

            if (dayLectures.length === 0) {
                html += `
                    <div class="text-center py-4 text-muted small">
                        <i class="far fa-clock mb-1 d-block text-muted"></i> Empty Slot
                    </div>
                `;
            } else {
                dayLectures.forEach(lec => {
                    const color = getPastelColor(lec.subject_name);
                    html += `
                        <div class="lecture-card p-3 mb-3 border-left-4 border border-1 rounded shadow-xs css-timetable-acf3bb" 
                             onclick="openEditModal(${lec.id})">
                            <div class="d-flex align-items-start justify-content-between mb-1">
                                <span class="subject-pill text-dark text-truncate d-inline-block css-timetable-ce586b" title="${lec.subject_name}">
                                    ${lec.subject_name}
                                </span>
                                <span class="text-muted small fw-bold">${lec.room_no || 'N/A'}</span>
                            </div>
                            <div class="text-primary small fw-semibold mb-2">
                                <i class="far fa-clock me-1"></i> ${lec.time_range}
                            </div>
                            <div class="text-muted small text-truncate">
                                <i class="fas fa-chalkboard-teacher me-1"></i> ${lec.teacher_name}
                            </div>
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
        $('#timetableWrapper').html(html);
    }

    // Handle Form Submit (Add/Update)
    $('#scheduleForm').on('submit', function (e) {
        e.preventDefault();

        // Feed hidden forms from main filters
        $('#form_course_id').val($('#filter_course_id').val());
        $('#form_group_id').val($('#filter_group_id').val());
        $('#form_division_id').val($('#filter_division_id').val());
        $('#form_academic_year').val($('#filter_academic_year').val());

        $.api.post('academics/timetable-save', $(this).serialize()).then(response => {
            if (response.success) {
                showToast('success', 'Success', response.message);
                $('#scheduleModal').modal('hide');
                loadTimetable();
            } else {
                // Return overlapping conflict nicely to user
                showToast('error', 'Conflict / Error', response.message);
            }
        }).catch(error => {
            showToast('error', 'Connection Error', error.message || 'Error occurred while saving');
        });
    });

    function openAddModal() {
        $('#scheduleForm')[0].reset();
        $('#slot_id').val(0);
        $('#modalTitle').html('<i class="fas fa-plus me-2 text-primary"></i> Add Schedule Slot');
        $('#deleteSlotBtn').hide();
        $('#scheduleModal').modal('show');
    }

    function openEditModal(id) {
        $('#scheduleForm')[0].reset();
        $('#slot_id').val(id);
        $('#modalTitle').html('<i class="fas fa-edit me-2 text-warning"></i> Edit Schedule Slot');
        $('#deleteSlotBtn').show();

        // Fetch slot details
        $.api.get('academics/timetable-get', { id: id }).then(response => {
            if (response.success) {
                const s = response.data.slot;
                $('#form_day_of_week').val(s.day_of_week);
                $('#form_subject_id').val(s.subject_id);
                $('#form_teacher_id').val(s.teacher_id);
                
                // Format times for HTML input (HH:MM)
                $('#form_start_time').val(s.start_time.substring(0, 5));
                $('#form_end_time').val(s.end_time.substring(0, 5));
                
                $('#form_room_no').val(s.room_no);
                $('#form_active').prop('checked', s.is_active == 1);
                $('#scheduleModal').modal('show');
            } else {
                showToast('error', 'Error', 'Failed to retrieve slot details');
            }
        });
    }

    function deleteSlot() {
        const id = $('#slot_id').val();
        if (id <= 0) return;

        showConfirm({
            title: 'Delete Lecture?',
            message: "Are you sure you want to delete this lecture from the timetable schedule?",
            confirmText: 'Yes, delete it!',
            confirmButtonClass: 'btn-danger',
            onConfirm: function () {
                $.api.post('academics/timetable-delete', { id: id }).then(response => {
                    if (response.success) {
                        showToast('success', 'Deleted!', response.message);
                        $('#scheduleModal').modal('hide');
                        loadTimetable();
                    } else {
                        showToast('error', 'Error!', response.message);
                    }
                });
            }
        });
    }

    // Export current Grid to Excel using SheetJS
    function exportTimetable() {
        const course = $('#filter_course_id option:selected').text();
        const group = $('#filter_group_id option:selected').text();
        const division = $('#filter_division_id option:selected').text();
        const year = $('#filter_academic_year option:selected').text();

        if (timetableData.length === 0) {
            showToast('warning', 'Empty', 'No timetable data to export.');
            return;
        }

        // Prepare flat export rows
        const dataRows = timetableData.map((lec, idx) => ({
            '#' : idx + 1,
            'Day' : lec.day_of_week,
            'Subject' : lec.subject_name,
            'Teacher' : lec.teacher_name,
            'Time' : lec.time_range,
            'Room No' : lec.room_no || 'N/A'
        }));

        const wb = XLSX.utils.book_new();
        const ws = XLSX.utils.json_to_sheet(dataRows);

        // Styling column widths
        const wscols = [
            {wch: 5},
            {wch: 15},
            {wch: 25},
            {wch: 15},
            {wch: 25},
            {wch: 25},
            {wch: 12}
        ];
        ws['!cols'] = wscols;

        XLSX.utils.book_append_sheet(wb, ws, 'Timetable');
        XLSX.writeFile(wb, `Timetable_${course}_${group}_Division_${division}_${year}.xlsx`.replace(/\s+/g, '_'));
    }

    // Listen to changes in Filters
    $(document).ready(function () {
        $('#filter_course_id, #filter_group_id, #filter_division_id, #filter_academic_year').on('change', function () {
            loadTimetable();
        });

        // Relocate Modals to end of body
        $('#scheduleModal').appendTo("body");
    });
</script>
