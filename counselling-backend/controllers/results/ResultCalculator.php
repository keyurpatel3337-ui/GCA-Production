<?php

/**
 * Result Calculator Service
 * Handles aggregation, percentage calculation, grace marks, and grading.
 */

class ResultCalculator
{
    /**
     * Grading Slabs (Percentage based)
     */
    const GRADE_SLABS = [
        ['min' => 90, 'max' => 100, 'grade' => 'A1'],
        ['min' => 80, 'max' => 90, 'grade' => 'A2'],
        ['min' => 70, 'max' => 80, 'grade' => 'B1'],
        ['min' => 60, 'max' => 70, 'grade' => 'B2'],
        ['min' => 50, 'max' => 60, 'grade' => 'C1'],
        ['min' => 40, 'max' => 50, 'grade' => 'C2'],
        ['min' => 33, 'max' => 40, 'grade' => 'D'],
        ['min' => 0, 'max' => 33, 'grade' => 'E (Needs Improvement)'] // Failing
    ];

    /**
     * Calculate Result for a single student
     * 
     * @param array $marks Array of marks for all exams.
     * Expected structure:
     * [
     *   'subject_id' => [
     *      'First Exam' => ['theory' => 40, 'max' => 50],
     *      'Second Exam' => ['theory' => 35, 'max' => 50],
     *      'Internal' => ['internal' => 18, 'max' => 20],
     *      'Annual' => ['theory' => 60, 'max' => 80],
     *      'Practical' => ['practical' => 45, 'max' => 50] // Optional
     *   ]
     * ]
     * 
     * @return array Calculated result with totals, percentage, grade, and status.
     */
    public function calculateResult($studentMarks)
    {
        $grandTotalObtained = 0;
        $grandTotalMax = 0;
        $subjectResults = [];
        $passStatus = 'PASS';
        $graceMarksGiven = 0;

        foreach ($studentMarks as $subjectId => $exams) {
            $totalObtained = 0;
            $totalMax = 0;
            $grace = isset($exams['grace_marks']) ? floatval($exams['grace_marks']) : 0;

            foreach ($exams as $key => $data) {
                if ($key === 'grace_marks')
                    continue; // Skip configuration keys

                // Handle absent as 0
                $obtained = isset($data['obtained']) ? floatval($data['obtained']) : 0;
                $max = isset($data['max']) ? floatval($data['max']) : 0;

                // Adjust for specific keys if passed differently (e.g. theory, internal)
                if (isset($data['theory']))
                    $obtained += $data['theory'];
                if (isset($data['practical']))
                    $obtained += $data['practical'];
                if (isset($data['internal']))
                    $obtained += $data['internal'];

                $totalObtained += $obtained;
                $totalMax += $max;
            }

            // Effective Obtained (for Grading)
            $effectiveObtained = $totalObtained + $grace;

            // Subject Percentage
            $percentage = ($totalMax > 0) ? ($effectiveObtained / $totalMax) * 100 : 0;
            $grade = $this->getGrade($percentage);

            // Check Pass/Fail per subject (assuming 33% passing)
            $isPass = $percentage >= 33;
            if (!$isPass) {
                // If failing, check if grace can help? 
                // For now, we assume 'grace' passed in input is APPROVED grace.
                // So if grace is added, it should be enough to pass or just extra.
                // If still < 33, it's fail.
                $passStatus = 'FAIL';
            }

            $subjectResults[$subjectId] = [
                'total_obtained' => $totalObtained,
                'grace_marks' => $grace,
                'total_max' => $totalMax,
                'percentage' => round($percentage, 2),
                'grade' => $grade,
                'is_pass' => $isPass
            ];

            $grandTotalObtained += $effectiveObtained;
            $grandTotalMax += $totalMax;
        }

        // Aggregate Result
        $overallPercentage = ($grandTotalMax > 0) ? ($grandTotalObtained / $grandTotalMax) * 100 : 0;
        $overallGrade = $this->getGrade($overallPercentage);

        return [
            'subjects' => $subjectResults,
            'grand_total_obtained' => $grandTotalObtained,
            'grand_total_max' => $grandTotalMax,
            'overall_percentage' => round($overallPercentage, 2),
            'overall_grade' => $overallGrade,
            'final_result' => $passStatus
        ];
    }

    /**
     * Get Grade based on percentage
     */
    private function getGrade($percentage)
    {
        foreach (self::GRADE_SLABS as $slab) {
            // Using >= for min, < for max (except top range)
            if ($percentage >= $slab['min'] && ($percentage < $slab['max'] || $percentage == 100 && $slab['max'] == 100)) {
                return $slab['grade'];
            }
        }
        return 'E'; // Default low
    }
}
