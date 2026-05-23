<?php
/**
 * Re-NEET Admission Form
 * Direct Access Link: /reneet-admission.php
 */

define('APP_INIT', true);
require_once __DIR__ . '/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;

$dbOps = new DatabaseOperations();

// AJAX: Check if Aadhaar already exists
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['check_aadhaar'])) {
    header('Content-Type: application/json');
    $a = preg_replace('/[^0-9]/', '', $_GET['aadhaar'] ?? '');
    if (strlen($a) !== 12) {
        echo json_encode(['valid' => false, 'exists' => false]);
    } else {
        $s = $conn->prepare("SELECT id FROM tbl_gm_std_registration WHERE aadhaar = ?");
        $s->execute([$a]);
        echo json_encode(['valid' => true, 'exists' => $s->rowCount() > 0]);
    }
    exit;
}

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process form submission
    try {
        $surname = trim($_POST['surname'] ?? '');
        $student_name = trim($_POST['student_name'] ?? '');
        $fathers_name = trim($_POST['fathers_name'] ?? '');
        $dob = $_POST['dob'] ?? '';
        $gender = $_POST['gender'] ?? '';
        $medium_id = $_POST['medium_id'] ?? 1; // 1: Gujarati, 2: English
        $hostel_required = ($_POST['hostel_required'] ?? 'No') === 'Yes' ? 'Yes' : 'No';
        $transport_required = ($_POST['transport_required'] ?? 'No') === 'Yes' ? 'Yes' : 'No';
        $mob = trim($_POST['mob'] ?? '');
        $amob = trim($_POST['amob'] ?? '');
        $aadhaar = preg_replace('/[^0-9]/', '', $_POST['aadhaar'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $city = '';
        $addr = trim($_POST['addr'] ?? '');
        $district = trim($_POST['district'] ?? '');
        $schoolname = trim($_POST['schoolname'] ?? '');
        $neet_application_no = preg_replace('/[^0-9]/', '', $_POST['neet_application_no'] ?? '');

        // Basic validation
        if (empty($surname) || empty($student_name) || empty($fathers_name) || empty($mob) || empty($dob) || empty($gender)) {
            throw new Exception("Please fill all required fields.");
        }

        if (!preg_match('/^[0-9]{10}$/', $mob)) {
            throw new Exception("Please enter a valid 10-digit mobile number.");
        }

        if (!empty($amob) && !preg_match('/^[0-9]{10}$/', $amob)) {
            throw new Exception("Please enter a valid 10-digit alternative mobile number.");
        }

        // Aadhaar validation
        if (empty($aadhaar) || strlen($aadhaar) !== 12) {
            throw new Exception("Please enter a valid 12-digit Aadhaar number.");
        }

        // NEET validation
        if (empty($neet_application_no) || strlen($neet_application_no) !== 12) {
            throw new Exception("Please enter a valid 12-digit NEET Application number.");
        }

        // Check if Aadhaar already registered
        $aadhaarCheck = $conn->prepare("SELECT id FROM tbl_gm_std_registration WHERE aadhaar = ?");
        $aadhaarCheck->execute([$aadhaar]);
        if ($aadhaarCheck->rowCount() > 0) {
            throw new Exception("This Aadhaar number is already registered.");
        }

        // Check if mobile already registered
        $checkStmt = $conn->prepare("SELECT id FROM tbl_gm_std_registration WHERE mob = ?");
        $checkStmt->execute([$mob]);
        if ($checkStmt->rowCount() > 0) {
            throw new Exception("This mobile number is already registered.");
        }

        // Hardcoded values for Re-NEET
        $course_id = 3; // Re-Neet
        $school_id = 1;
        $campus_id = 1;
        $standard = 3;
        $academic_year_id = 2; // 2026-2027
        $board_id = 1; // GSEB
        $group_id = 2; // B Group/NEET
        $password = $mob;
        $hash_password = password_hash($password, PASSWORD_DEFAULT);

        $sql = "INSERT INTO tbl_gm_std_registration (
            school_id, campus_id, surname, student_name, fathers_name, dob, gender, board_id, medium_id, group_id, course_id, standard,
            mob, amob, parent_mob, email, aadhaar, schoolname, schaddr, addr, district, city, neet_application_no, hostel_required, transport_required,
            fathername, fatheredu, ocupation, ofcaddr,
            hash_password, password, declaration_agreed,
            academic_year_id, status, token_fees_paid, admission_confirmed, admission_confirmed_date, created_at
        ) VALUES (
            :school_id, :campus_id, :surname, :student_name, :fathers_name, :dob, :gender, :board_id, :medium_id, :group_id, :course_id, :standard,
            :mob, :amob, :parent_mob, :email, :aadhaar, :schoolname, '', :addr, :district, :city, :neet_application_no, :hostel_required, :transport_required,
            '', '', '', '',
            :hash_password, :password, 1,
            :academic_year_id, 1, 1, 1, NOW(), NOW()
        )";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            'school_id' => $school_id,
            'campus_id' => $campus_id,
            'surname' => $surname,
            'student_name' => $student_name,
            'fathers_name' => $fathers_name,
            'dob' => $dob,
            'gender' => $gender,
            'board_id' => $board_id,
            'medium_id' => $medium_id,
            'group_id' => $group_id,
            'course_id' => $course_id,
            'standard' => $standard,
            'mob' => $mob,
            'amob' => $amob,
            'parent_mob' => $mob,
            'email' => $email,
            'aadhaar' => $aadhaar,
            'schoolname' => $schoolname,
            'addr' => $addr,
            'district' => $district,
            'city' => $city,
            'neet_application_no' => $neet_application_no,
            'hostel_required' => $hostel_required,
            'transport_required' => $transport_required,
            'hash_password' => $hash_password,
            'password' => $password,
            'academic_year_id' => $academic_year_id
        ]);

        $student_id = $conn->lastInsertId();

        // 2. Automate Enrollment
        // Generate Enrollment Number: YYYY + 4 digit sequential
        $year = date('Y');
        $seqStmt = $conn->prepare("SELECT MAX(enrollment_no) as max_enr FROM tbl_enrolled_students WHERE enrollment_no LIKE ?");
        $seqStmt->execute([$year . '%']);
        $seqRes = $seqStmt->fetch(PDO::FETCH_ASSOC);

        if (!empty($seqRes['max_enr'])) {
            $last_seq = (int) substr($seqRes['max_enr'], 4);
            $new_seq = $last_seq + 1;
        } else {
            $new_seq = 1;
        }
        $enrollment_no = $year . str_pad($new_seq, 4, '0', STR_PAD_LEFT);

        // Insert into tbl_enrolled_students
        $enrollStmt = $conn->prepare("INSERT INTO tbl_enrolled_students (
            registration_id, enrollment_no, enrollment_date, is_active, created_at
        ) VALUES (?, ?, CURDATE(), 1, NOW())");
        $enrollStmt->execute([$student_id, $enrollment_no]);
        $enrollment_id = $conn->lastInsertId();

        // Update tbl_gm_std_registration with enrollment_id and is_enrolled status
        $updateRegStmt = $conn->prepare("UPDATE tbl_gm_std_registration SET enrollment_id = ?, is_enrolled = 1 WHERE id = ?");
        $updateRegStmt->execute([$enrollment_id, $student_id]);

        // Start session to store flash message if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['success_msg'] = "Registration and Enrollment successful! Use your registered mobile number as password.";

        // Redirect to student login page
        header("Location: portal/modules/student-portal/student-login.php");
        exit;
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Re-NEET Admission 2026-27 | Gyanmanjari Career Academy</title>
    <meta name="description"
        content="Official admission form for GCA Re-NEET repeater program. Register now to excel in NEET 2027.">

    <!-- Stylesheets -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

    <style>
        :root {
            --primary: #2563eb;
            --primary-light: #60a5fa;
            --secondary: #4f46e5;
            --glass: rgba(255, 255, 255, 0.85);
            --glass-border: rgba(255, 255, 255, 0.5);
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: radial-gradient(circle at top right, #e0f2fe, transparent),
                radial-gradient(circle at bottom left, #f0f9ff, transparent),
                #f8fafc;
            min-height: 100vh;
            color: #1e293b;
        }

        h1,
        h2,
        h3 {
            font-family: 'Outfit', sans-serif;
        }

        .glass-container {
            background: var(--glass);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid var(--glass-border);
            border-radius: 32px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.08);
            position: relative;
            overflow: hidden;
        }

        .glass-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }

        .input-premium {
            background: #ffffff;
            border: 1.5px solid #e2e8f0;
            border-radius: 16px;
            padding: 14px 20px;
            font-weight: 500;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            color: #334155;
        }

        .input-premium:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
            outline: none;
            transform: translateY(-1px);
        }

        .input-premium::placeholder {
            color: #94a3b8;
            font-weight: 400;
        }

        select.input-premium {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%23475569' stroke-width='2'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 20px center;
            background-size: 18px;
            padding-right: 50px;
            cursor: pointer;
        }

        select.input-premium:hover {
            border-color: var(--primary-light);
            background-color: #f8fafc;
        }

        .btn-premium {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 16px 32px;
            border-radius: 18px;
            font-weight: 700;
            font-size: 1.1rem;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 10px 25px -5px rgba(37, 99, 235, 0.4);
            letter-spacing: -0.01em;
        }

        .btn-premium:hover {
            transform: translateY(-3px) scale(1.01);
            box-shadow: 0 20px 35px -8px rgba(37, 99, 235, 0.5);
            filter: brightness(1.1);
        }

        .btn-premium:active {
            transform: translateY(0);
        }

        .section-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            margin-right: 14px;
            font-size: 1.1rem;
        }

        /* Floating Animation */
        @keyframes float {
            0% {
                transform: translateY(0px);
            }

            50% {
                transform: translateY(-10px);
            }

            100% {
                transform: translateY(0px);
            }
        }

        .floating-orb {
            position: fixed;
            border-radius: 50%;
            filter: blur(80px);
            z-index: -1;
            opacity: 0.5;
            animation: float 10s ease-in-out infinite;
        }
    </style>
</head>

<body class="flex items-center justify-center py-16 px-6">
    <!-- Background Orbs -->
    <div class="floating-orb w-96 h-96 bg-blue-100 top-[-10%] right-[-5%]"></div>
    <div class="floating-orb w-80 h-80 bg-indigo-100 bottom-[-10%] left-[-5%] animation-delay-2000"></div>

    <div class="max-w-5xl w-full">
        <!-- Logo & Title -->
        <div class="text-center mb-14">
            <img src="assets/images/logogmn.png" alt="GCA Logo" class="h-24 mx-auto mb-8 drop-shadow-sm">
            <h1 class="text-4xl md:text-5xl font-black text-slate-900 tracking-tight mb-4">
                Re-NEET <span class="text-blue-600">Admission</span> 2026-27
            </h1>
            <p class="text-lg text-slate-500 font-medium max-w-2xl mx-auto">
                Step into a world of focused preparation and expert mentorship.
                Your journey to medical excellence starts here.
            </p>
        </div>

        <?php if ($success_message): ?>
            <div class="glass-container p-12 text-center animate-in fade-in zoom-in duration-500">
                <div
                    class="w-24 h-24 bg-green-50 text-green-500 rounded-3xl flex items-center justify-center mx-auto mb-8 shadow-inner">
                    <i class="fas fa-check-circle text-5xl"></i>
                </div>
                <h2 class="text-3xl font-extrabold text-slate-900 mb-4">Registration Successful!</h2>
                <p class="text-slate-600 mb-10 text-lg">Thank you for registering. You can now access your dashboard and
                    complete the admission process.</p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="portal/modules/student-portal/student-login.php" class="btn-premium">
                        <i class="fas fa-sign-in-alt mr-2"></i> Login to Portal
                    </a>
                    <a href="index.php"
                        class="px-8 py-4 rounded-2xl bg-slate-100 text-slate-700 font-bold hover:bg-slate-200 transition-all">
                        Return to Home
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="glass-container p-8 md:p-14">
                <?php if ($error_message): ?>
                    <div
                        class="bg-red-50 border-l-4 border-red-500 text-red-700 p-5 mb-10 rounded-2xl flex items-center shadow-sm">
                        <i class="fas fa-exclamation-circle text-xl mr-4"></i>
                        <div>
                            <p class="font-bold">Attention Required</p>
                            <p class="text-sm opacity-90"><?php echo $error_message; ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <form action="" method="POST" class="space-y-10">
                    <!-- Student Full Name -->
                    <div>
                        <div class="flex items-center mb-6">
                            <div class="section-icon bg-blue-50 text-blue-600 shadow-sm">
                                <i class="fas fa-user"></i>
                            </div>
                            <h3 class="text-xl font-bold text-slate-800">Student Full Name</h3>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="space-y-2">
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider ml-1">(SURNAME)
                                    <span class="text-red-500">*</span></label>
                                <input type="text" name="surname" required class="w-full input-premium"
                                    placeholder="Enter Surname">
                            </div>
                            <div class="space-y-2">
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider ml-1">(STUDENT'S
                                    NAME) <span class="text-red-500">*</span></label>
                                <input type="text" name="student_name" required class="w-full input-premium"
                                    placeholder="Enter Student Name">
                            </div>
                            <div class="space-y-2">
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider ml-1">(FATHER'S
                                    NAME) <span class="text-red-500">*</span></label>
                                <input type="text" name="fathers_name" required class="w-full input-premium"
                                    placeholder="Enter Father's Name">
                            </div>
                        </div>
                    </div>

                    <!-- Personal & Medium -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                        <div class="space-y-2">
                            <label class="text-sm font-bold text-slate-700 ml-1">Date of Birth <span
                                    class="text-red-500">*</span></label>
                            <input type="date" name="dob" required class="w-full input-premium">
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-bold text-slate-700 ml-1">Gender <span
                                    class="text-red-500">*</span></label>
                            <div class="grid grid-cols-2 gap-3">
                                <label
                                    class="flex items-center justify-center p-3 border border-slate-200 rounded-2xl cursor-pointer hover:bg-blue-50 transition-all">
                                    <input type="radio" name="gender" value="Male" checked class="mr-2">
                                    <span class="font-bold text-sm">Male</span>
                                </label>
                                <label
                                    class="flex items-center justify-center p-3 border border-slate-200 rounded-2xl cursor-pointer hover:bg-blue-50 transition-all">
                                    <input type="radio" name="gender" value="Female" class="mr-2">
                                    <span class="font-bold text-sm">Female</span>
                                </label>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-bold text-slate-700 ml-1">Medium <span
                                    class="text-red-500">*</span></label>
                            <div class="grid grid-cols-2 gap-3">
                                <label
                                    class="flex items-center justify-center p-3 border border-slate-200 rounded-2xl cursor-pointer hover:bg-blue-50 transition-all">
                                    <input type="radio" name="medium_id" value="1" checked class="mr-2">
                                    <span class="font-bold text-sm">Gujarati</span>
                                </label>
                                <label
                                    class="flex items-center justify-center p-3 border border-slate-200 rounded-2xl cursor-pointer hover:bg-blue-50 transition-all">
                                    <input type="radio" name="medium_id" value="2" class="mr-2">
                                    <span class="font-bold text-sm">English</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Hostel & Transport Preference -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div class="space-y-2">
                            <label class="text-sm font-bold text-slate-700 ml-1">Hostel Required? <span
                                    class="text-red-500">*</span></label>
                            <div class="grid grid-cols-2 gap-3">
                                <label
                                    class="flex items-center justify-center p-3 border border-slate-200 rounded-2xl cursor-pointer hover:bg-blue-50 transition-all">
                                    <input type="radio" name="hostel_required" value="Yes" class="mr-2">
                                    <span class="font-bold text-sm">Yes</span>
                                </label>
                                <label
                                    class="flex items-center justify-center p-3 border border-slate-200 rounded-2xl cursor-pointer hover:bg-blue-50 transition-all">
                                    <input type="radio" name="hostel_required" value="No" checked class="mr-2">
                                    <span class="font-bold text-sm">No</span>
                                </label>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-bold text-slate-700 ml-1">Transport Required? <span
                                    class="text-red-500">*</span></label>
                            <div class="grid grid-cols-2 gap-3">
                                <label
                                    class="flex items-center justify-center p-3 border border-slate-200 rounded-2xl cursor-pointer hover:bg-blue-50 transition-all">
                                    <input type="radio" name="transport_required" value="Yes" class="mr-2">
                                    <span class="font-bold text-sm">Yes</span>
                                </label>
                                <label
                                    class="flex items-center justify-center p-3 border border-slate-200 rounded-2xl cursor-pointer hover:bg-blue-50 transition-all">
                                    <input type="radio" name="transport_required" value="No" checked class="mr-2">
                                    <span class="font-bold text-sm">No</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Contact -->
                    <div class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="space-y-2">
                                <label class="text-sm font-bold text-slate-700 ml-1">Mobile <span
                                        class="text-red-500">*</span></label>
                                <input type="tel" name="mob" id="mob" required maxlength="10" pattern="[0-9]{10}"
                                    class="w-full input-premium" placeholder="Enter 10 digit mobile"
                                    oninput="this.value=this.value.replace(/[^0-9]/g,'')">
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-bold text-slate-700 ml-1">Alternative Mobile <span
                                        class="text-slate-400 text-xs font-normal">(optional)</span></label>
                                <input type="tel" name="amob" id="amob" maxlength="10" pattern="[0-9]{10}"
                                    class="w-full input-premium" placeholder="Enter alternative mobile"
                                    oninput="this.value=this.value.replace(/[^0-9]/g,'')">
                                <p id="amob_error" class="text-xs text-red-500 ml-1 hidden">Must be 10 digits</p>
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-bold text-slate-700 ml-1">Email <span
                                        class="text-red-500">*</span></label>
                                <input type="email" name="email" required class="w-full input-premium"
                                    placeholder="student@example.com">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-1 gap-6">
                            <div class="space-y-2">
                                <label class="text-sm font-bold text-slate-700 ml-1">Aadhaar Number <span
                                        class="text-red-500">*</span></label>
                                <div class="relative">
                                    <input type="text" name="aadhaar" id="aadhaar" required maxlength="12"
                                        class="w-full input-premium pr-12" placeholder="Enter 12 digit Aadhaar"
                                        oninput="onAadhaarInput(this)" autocomplete="off">
                                    <span id="aadhaar_icon"
                                        class="absolute right-4 top-1/2 -translate-y-1/2 text-xl hidden"></span>
                                </div>
                                <p id="aadhaar_msg" class="text-xs ml-1 hidden"></p>
                            </div>
                        </div>
                    </div>

                    <!-- Address -->
                    <div class="space-y-6">
                        <div class="space-y-2">
                            <label class="text-sm font-bold text-slate-700 ml-1">Residence Address <span
                                    class="text-red-500">*</span></label>
                            <textarea name="addr" required rows="2" class="w-full input-premium"
                                placeholder="Enter Full Residence Address"></textarea>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div class="space-y-2">
                                <label class="text-sm font-bold text-slate-700 ml-1">District <span
                                        class="text-red-500">*</span></label>
                                <select name="district" required class="w-full input-premium bg-white">
                                    <option value="">Select District</option>
                                    <option value="Ahmedabad">Ahmedabad</option>
                                    <option value="Amreli">Amreli</option>
                                    <option value="Anand">Anand</option>
                                    <option value="Aravalli">Aravalli</option>
                                    <option value="Banaskantha">Banaskantha</option>
                                    <option value="Bharuch">Bharuch</option>
                                    <option value="Bhavnagar" selected>Bhavnagar</option>
                                    <option value="Botad">Botad</option>
                                    <option value="Chhota Udepur">Chhota Udepur</option>
                                    <option value="Dahod">Dahod</option>
                                    <option value="Dang">Dang</option>
                                    <option value="Devbhumi Dwarka">Devbhumi Dwarka</option>
                                    <option value="Gandhinagar">Gandhinagar</option>
                                    <option value="Gir Somnath">Gir Somnath</option>
                                    <option value="Jamnagar">Jamnagar</option>
                                    <option value="Junagadh">Junagadh</option>
                                    <option value="Kheda">Kheda</option>
                                    <option value="Kutch">Kutch</option>
                                    <option value="Mahisagar">Mahisagar</option>
                                    <option value="Mehsana">Mehsana</option>
                                    <option value="Morbi">Morbi</option>
                                    <option value="Narmada">Narmada</option>
                                    <option value="Navsari">Navsari</option>
                                    <option value="Panchmahal">Panchmahal</option>
                                    <option value="Patan">Patan</option>
                                    <option value="Porbandar">Porbandar</option>
                                    <option value="Rajkot">Rajkot</option>
                                    <option value="Sabarkantha">Sabarkantha</option>
                                    <option value="Surat">Surat</option>
                                    <option value="Surendranagar">Surendranagar</option>
                                    <option value="Tapi">Tapi</option>
                                    <option value="Vadodara">Vadodara</option>
                                    <option value="Valsad">Valsad</option>
                                </select>
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-bold text-slate-700 ml-1">12th School Name <span
                                        class="text-red-500">*</span></label>
                                <input type="text" name="schoolname" required class="w-full input-premium"
                                    placeholder="Enter School Name">
                            </div>
                        </div>
                    </div>

                    <!-- NEET Info -->
                    <div class="space-y-2">
                        <label class="text-sm font-bold text-slate-700 ml-1">NEET Application No. <span
                                class="text-red-500">*</span></label>
                        <input type="text" name="neet_application_no" id="neet_application_no" required maxlength="12"
                            pattern="[0-9]{12}" class="w-full input-premium"
                            placeholder="Enter 12 digit NEET Application Number"
                            oninput="this.value=this.value.replace(/[^0-9]/g,'')">
                    </div>

                    <div class="space-y-4 pt-6">
                        <label class="flex items-start cursor-pointer group">
                            <input type="checkbox" required
                                class="mt-1.5 mr-4 h-5 w-5 rounded border-slate-300 text-blue-600 focus:ring-blue-500 transition-all">
                            <span class="text-slate-600 text-sm leading-relaxed">
                                I hereby declare that all the information provided above is true and correct. I agree to the
                                terms and conditions of Gyanmanjari Career Academy.
                            </span>
                        </label>

                        <button type="submit" class="w-full btn-premium flex items-center justify-center group">
                            <span>Register for Re-NEET</span>
                            <i class="fas fa-arrow-right ml-3 transition-transform group-hover:translate-x-2"></i>
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <!-- Support Info -->
        <div class="mt-16 text-center">
            <div class="inline-flex flex-wrap items-center justify-center gap-6 text-slate-400">
                <div class="flex items-center gap-2">
                    <i class="fas fa-envelope"></i>
                    <span>Email ID: gyanmanjaribvn@gmail.com</span>
                </div>
                <div class="hidden sm:block w-1.5 h-1.5 rounded-full bg-slate-200"></div>
                <div class="flex items-center gap-2">
                    <i class="fas fa-headset"></i>
                    <span>Help desk number: 9429292063</span>
                </div>
            </div>
            <p class="mt-8 text-slate-400 text-sm font-medium">
                &copy; <?php echo date('Y'); ?> Gyanmanjari Career Academy. All rights reserved.
            </p>
        </div>
    </div>
    <script>
        function onAadhaarInput(input) {
            input.value = input.value.replace(/[^0-9]/g, '').slice(0, 12);
            checkAadhaar(input.value);
        }

        let aadhaarTimer = null;
        function checkAadhaar(digits) {
            const icon = document.getElementById('aadhaar_icon');
            const msg = document.getElementById('aadhaar_msg');

            if (digits.length < 12) {
                icon.className = 'absolute right-4 top-1/2 -translate-y-1/2 text-xl hidden';
                msg.className = 'text-xs ml-1 hidden';
                return;
            }

            // Show loading
            icon.textContent = '⏳';
            icon.className = 'absolute right-4 top-1/2 -translate-y-1/2 text-xl';
            msg.className = 'text-xs ml-1 hidden';

            clearTimeout(aadhaarTimer);
            aadhaarTimer = setTimeout(() => {
                fetch('?check_aadhaar=1&aadhaar=' + encodeURIComponent(digits))
                    .then(r => r.json())
                    .then(data => {
                        if (!data.valid) {
                            icon.textContent = '❌';
                            msg.textContent = 'Invalid Aadhaar number.';
                            msg.className = 'text-xs ml-1 text-red-500';
                        } else if (data.exists) {
                            icon.textContent = '❌';
                            msg.textContent = 'This Aadhaar is already registered.';
                            msg.className = 'text-xs ml-1 text-red-500';
                        } else {
                            icon.textContent = '✅';
                            msg.textContent = 'Aadhaar is available.';
                            msg.className = 'text-xs ml-1 text-green-600';
                        }
                        icon.className = 'absolute right-4 top-1/2 -translate-y-1/2 text-xl';
                        msg.classList.remove('hidden');
                    });
            }, 500);
        }

        // Alternative mobile: show error if not 10 digits when filled
        document.getElementById('amob').addEventListener('blur', function () {
            const err = document.getElementById('amob_error');
            if (this.value.length > 0 && this.value.length !== 10) {
                err.classList.remove('hidden');
                this.style.borderColor = '#ef4444';
            } else {
                err.classList.add('hidden');
                this.style.borderColor = '';
            }
        });
        document.getElementById('amob').addEventListener('input', function () {
            if (this.value.length === 10 || this.value.length === 0) {
                document.getElementById('amob_error').classList.add('hidden');
                this.style.borderColor = '';
            }
        });

        // Block form submit if aadhaar already exists
        document.querySelector('form').addEventListener('submit', function (e) {
            const msg = document.getElementById('aadhaar_msg');
            const amob = document.getElementById('amob');
            let block = false;

            if (msg && msg.textContent.includes('already registered')) {
                e.preventDefault();
                msg.classList.remove('hidden');
                block = true;
            }

            const digits = document.getElementById('aadhaar').value;
            if (digits.length !== 12) {
                e.preventDefault();
                document.getElementById('aadhaar_msg').textContent = 'Aadhaar must be exactly 12 digits.';
                document.getElementById('aadhaar_msg').className = 'text-xs ml-1 text-red-500';
                document.getElementById('aadhaar_msg').classList.remove('hidden');
                block = true;
            }

            if (amob.value.length > 0 && amob.value.length !== 10) {
                e.preventDefault();
                document.getElementById('amob_error').classList.remove('hidden');
                amob.style.borderColor = '#ef4444';
                block = true;
            }

            if (block) {
                window.scrollTo({ top: document.querySelector('form').offsetTop - 20, behavior: 'smooth' });
            }
        });
    </script>
</body>

</html>