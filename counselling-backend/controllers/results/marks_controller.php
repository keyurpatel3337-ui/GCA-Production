<?php
/**
 * Marks Entry Controller
 * Handles saving and updating student exam marks
 */

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;

class MarksController
{
    private $db;
    private $op;

    public function __construct()
    {
        global $conn;
        $this->db = $conn;
        $this->op = new Operation();
    }

    /**
     * Get marks for a student for a specific exam type
     */
    public function getStudentMarks($student_id, $exam_type)
    {
        $sql = "SELECT m.*, s.subject_name 
                FROM tbl_student_exam_marks m
                JOIN tbl_subjects s ON m.subject_id = s.id
                WHERE m.student_id = ? AND m.exam_type = ?
                ORDER BY s.id";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$student_id, $exam_type]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            logDatabaseError($e, "Fetch Student Marks");
            return [];
        }
    }

    /**
     * Save marks for multiple subjects for a student
     */
    public function saveMarks($data)
    {
        $student_id = $data['student_id'];
        $exam_type = $data['exam_type'];
        $academic_year_id = $data['academic_year_id'];
        $course_id = $data['course_id'];
        $marks_data = $data['marks']; // Array of [subject_id, theory, practical, internal, is_present]

        $this->db->beginTransaction();
        try {
            foreach ($marks_data as $m) {
                $subject_id = $m['subject_id'];
                $theory = (float) ($m['theory'] ?? 0);
                $practical = (float) ($m['practical'] ?? 0);
                $internal = (float) ($m['internal'] ?? 0);
                $total = $theory + $practical + $internal;
                $is_present = $m['is_present'] ?? 1;

                // Check if entry already exists
                $check_sql = "SELECT id FROM tbl_student_exam_marks 
                              WHERE student_id = ? AND subject_id = ? AND exam_type = ?";
                $stmt = $this->db->prepare($check_sql);
                $stmt->execute([$student_id, $subject_id, $exam_type]);
                $existing = $stmt->fetch();

                if ($existing) {
                    // Update
                    $update_sql = "UPDATE tbl_student_exam_marks SET 
                                   theory_marks = ?, practical_marks = ?, internal_marks = ?, 
                                   total_marks = ?, is_present = ?, academic_year_id = ?, 
                                   course_id = ?, updated_at = NOW()
                                   WHERE id = ?";
                    $this->db->prepare($update_sql)->execute([
                        $theory,
                        $practical,
                        $internal,
                        $total,
                        $is_present,
                        $academic_year_id,
                        $course_id,
                        $existing['id']
                    ]);
                } else {
                    // Insert
                    $insert_sql = "INSERT INTO tbl_student_exam_marks (
                                   student_id, academic_year_id, course_id, exam_type, 
                                   subject_id, theory_marks, practical_marks, internal_marks, 
                                   total_marks, is_present, created_at
                                   ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                    $this->db->prepare($insert_sql)->execute([
                        $student_id,
                        $academic_year_id,
                        $course_id,
                        $exam_type,
                        $subject_id,
                        $theory,
                        $practical,
                        $internal,
                        $total,
                        $is_present
                    ]);
                }
            }
            $this->db->commit();
            return ['success' => true, 'message' => 'Marks saved successfully'];
        } catch (PDOException $e) {
            $this->db->rollBack();
            logDatabaseError($e, "Save Marks");
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}

// API Handling
if (defined('API_MODE') || (isset($_GET['route']) && strpos($_GET['route'], 'results/marks') !== false)) {
    $controller = new MarksController();
    $action = $_GET['action'] ?? '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Data is already decoded into $_POST by bootstrap.php
        $data = $_POST;

        if ($action === 'save') {
            $result = $controller->saveMarks($data);
            if ($result['success']) {
                sendSuccessResponse(null, $result['message']);
            } else {
                sendErrorResponse($result['error']);
            }
        }
    } else {
        if ($action === 'get') {
            $student_id = $_GET['student_id'] ?? 0;
            $exam_type = $_GET['exam_type'] ?? '';
            sendSuccessResponse($controller->getStudentMarks($student_id, $exam_type));
        }
    }
}
