<?php

/**
 * Test Marks Module - Data Helper Functions
 * Common database queries used across test-marks pages
 */

require_once OPERATION_FILE;

// Initialize Database Operations if not already done
if (!isset($dbOps)) {
  $dbOps = new DatabaseOperations();
}

/**
 * Get all students for dropdown
 * @return array
 */
function getStudentsForDropdown()
{
  global $dbOps;

  try {
    return $dbOps->customSelect("
            SELECT r.id, CONCAT(r.surname, ' ', r.student_name, ' (', r.mob, ')') AS display_name, 
                   r.mob, e.enrollment_id, e.enrollment_no
            FROM tbl_gm_std_registration r
            LEFT JOIN tbl_enrolled_students e ON r.id = e.registration_id
            WHERE r.status = 1
            ORDER BY r.surname, r.student_name
        ", []) ?: [];
  } catch (PDOException $e) {
    return [];
  }
}

/**
 * Get active paper sets for dropdown
 * @return array
 */
function getPaperSetsForDropdown()
{
  global $dbOps;

  try {
    return $dbOps->select(
      'tbl_paper_sets',
      ['id', 'paper_set_name', 'paper_code', 'total_questions'],
      ['status' => 'active'],
      'paper_set_name ASC'
    ) ?: [];
  } catch (PDOException $e) {
    return [];
  }
}

/**
 * Get answer keys for dropdown
 * @return array
 */
function getAnswerKeysForDropdown()
{
  global $dbOps;

  try {
    return $dbOps->customSelect("
            SELECT ak.id, ak.test_name, ak.test_date, ps.paper_set_name, ps.paper_code
            FROM tbl_answer_keys ak
            JOIN tbl_paper_sets ps ON ak.paper_set_id = ps.id
            WHERE ak.status = 'active'
            ORDER BY ak.test_date ASC
        ", []) ?: [];
  } catch (PDOException $e) {
    return [];
  }
}


