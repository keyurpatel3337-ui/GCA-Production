<?php
require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';

abstract class AbstractGenerator
{
    protected $db;
    protected $op;

    public function __construct()
    {
        global $conn;
        $this->db = $conn;
        require_once OPERATION_FILE;
        $this->op = new Operation();
    }

    protected function fetchStudentDetails($student_id)
    {
        $student = $this->op->readWithJoin(
            'tbl_gm_std_registration s',
            [
                's.*',
                'b.board_name',
                'm.medium_name',
                'g.group_name',
                'c.course_name',
                'ay.year_name as academic_year',
                'sch.school_name as current_school_name'
            ],
            [
                ['type' => 'LEFT', 'table' => 'tbl_boards b', 'on' => 's.board_id = b.id'],
                ['type' => 'LEFT', 'table' => 'tbl_medium m', 'on' => 's.medium_id = m.id'],
                ['type' => 'LEFT', 'table' => 'tbl_group g', 'on' => 's.group_id = g.id'],
                ['type' => 'LEFT', 'table' => 'tbl_courses c', 'on' => 's.course_id = c.id'],
                ['type' => 'LEFT', 'table' => 'tbl_academic_years ay', 'on' => 's.academic_year_id = ay.id'],
                ['type' => 'LEFT', 'table' => 'tbl_schools sch', 'on' => 's.school_id = sch.id']
            ],
            ['s.id' => $student_id]
        );

        if (!$student) {
            return false;
        }

        $enrollment = $this->op->readWithJoin(
            'tbl_enrolled_students e',
            ['e.*', 'sch.school_name', 'd.division_name'],
            [
                ['type' => 'LEFT', 'table' => 'tbl_schools sch', 'on' => 'e.school_id = sch.id'],
                ['type' => 'LEFT', 'table' => 'tbl_division d', 'on' => 'e.division_id = d.id']
            ],
            ['e.registration_id' => $student_id]
        );

        $student['enrollment'] = $enrollment ? $enrollment : [];
        return $student;
    }

    protected function saveCertificateRecord($student_id, $certificate_type, $serial_number, $issued_by, $academic_year_id = null)
    {
        $sql = "INSERT INTO tbl_issued_certificates (student_id, certificate_type, serial_number, issued_date, issued_by, academic_year_id) VALUES (?, ?, ?, CURDATE(), ?, ?)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$student_id, $certificate_type, $serial_number, $issued_by, $academic_year_id]);
    }

    abstract public function generate($student_id, $issued_by);
}
