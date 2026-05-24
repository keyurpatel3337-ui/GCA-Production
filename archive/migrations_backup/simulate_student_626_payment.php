<?php
/**
 * Simulate Student 626 Paying Pending Fees
 */

require_once __DIR__ . '/../../common/db_connect.php';
require_once __DIR__ . '/../../common/helpers/receipt_sequence_helper.php';

echo "═══════════════════════════════════════════════════════════════════════════════\n";
echo "  STUDENT 626 - PENDING FEE PAYMENT SIMULATION\n";
echo "═══════════════════════════════════════════════════════════════════════════════\n\n";

$student_id = 626;

// Get student details
$stmt = $conn->prepare("SELECT id, CONCAT(surname, ' ', student_name) as name, school_id 
                        FROM tbl_gm_std_registration WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    die("❌ Student not found!\n");
}

echo "Student Information:\n";
echo "───────────────────────────────────────────────────────────────────────────────\n";
echo "Student ID: {$student['id']}\n";
echo "Name: {$student['name']}\n";
echo "School: " . ($student['school_id'] == 1 ? 'GM School (ID: 1)' : 'SGM School (ID: 2)') . "\n";
echo "───────────────────────────────────────────────────────────────────────────────\n\n";

$academic_year = getCurrentAcademicYear($conn);

// Pending fees
$pending_fees = [
    ['fee_type' => 'school_fee', 'label' => 'School Fee', 'amount' => 50000.00],
    ['fee_type' => 'trust_facilities_fee', 'label' => 'Trust Facilities Fee', 'amount' => 25000.00],
    ['fee_type' => 'tuition_fee_part2', 'label' => 'Tuition Fee Part 2', 'amount' => 30000.00]
];

echo "Pending Fees to be Paid:\n";
echo "───────────────────────────────────────────────────────────────────────────────\n";
foreach ($pending_fees as $fee) {
    printf("• %-30s ₹%s\n", $fee['label'] . ':', number_format($fee['amount'], 2));
}
$total_amount = array_sum(array_column($pending_fees, 'amount'));
echo "───────────────────────────────────────────────────────────────────────────────\n";
printf("  TOTAL AMOUNT:                    ₹%s\n", number_format($total_amount, 2));
echo "═══════════════════════════════════════════════════════════════════════════════\n\n";

// Check current sequences
echo "Current Sequence Status (Before Payment):\n";
echo "───────────────────────────────────────────────────────────────────────────────\n";
printf("%-30s %-15s %-15s\n", 'Fee Type', 'Last Receipt', 'Next Receipt');
echo "───────────────────────────────────────────────────────────────────────────────\n";

foreach ($pending_fees as $fee) {
    $school_id = ($fee['fee_type'] === 'school_fee') ? $student['school_id'] : null;

    $stmt = $conn->prepare("SELECT last_sequence FROM tbl_receipt_sequences 
                           WHERE fee_type = ? 
                           AND (school_id = ? OR (school_id IS NULL AND ? IS NULL))
                           AND (academic_year = ? OR academic_year IS NULL)");
    $stmt->execute([$fee['fee_type'], $school_id, $school_id, $academic_year]);
    $seq = $stmt->fetch(PDO::FETCH_ASSOC);

    $last = $seq['last_sequence'] ?? 0;
    $next = $last + 1;

    printf("%-30s %-15s %-15s\n", $fee['label'], $last == 0 ? 'None' : $last, $next);
}
echo "───────────────────────────────────────────────────────────────────────────────\n\n";

// Simulate payment processing
echo "🔄 SIMULATING PAYMENT PROCESS...\n\n";

echo "When payment gateway confirms success, the system will:\n";
echo "───────────────────────────────────────────────────────────────────────────────\n";

$receipts_to_generate = [];

foreach ($pending_fees as $fee) {
    $school_id = ($fee['fee_type'] === 'school_fee') ? $student['school_id'] : null;

    // Get what the receipt number would be (without actually generating)
    $stmt = $conn->prepare("SELECT last_sequence FROM tbl_receipt_sequences 
                           WHERE fee_type = ? 
                           AND (school_id = ? OR (school_id IS NULL AND ? IS NULL))
                           AND (academic_year = ? OR academic_year IS NULL)");
    $stmt->execute([$fee['fee_type'], $school_id, $school_id, $academic_year]);
    $seq = $stmt->fetch(PDO::FETCH_ASSOC);

    $receipt_no = ($seq['last_sequence'] ?? 0) + 1;

    $receipts_to_generate[] = [
        'fee_label' => $fee['label'],
        'fee_type' => $fee['fee_type'],
        'receipt_no' => $receipt_no,
        'amount' => $fee['amount']
    ];

    echo "Step " . count($receipts_to_generate) . ": Generate receipt for {$fee['label']}\n";
    echo "  → Receipt Number: {$receipt_no}\n";
    echo "  → Amount: ₹" . number_format($fee['amount'], 2) . "\n";
    echo "  → Fee Type: {$fee['fee_type']}\n\n";
}

echo "───────────────────────────────────────────────────────────────────────────────\n\n";

// Summary of receipts
echo "✅ PAYMENT SUCCESS - RECEIPTS GENERATED\n";
echo "═══════════════════════════════════════════════════════════════════════════════\n";
echo "Student 626 ({$student['name']}) will receive 3 SEPARATE RECEIPTS:\n\n";

printf("%-35s %-20s %-20s\n", 'Fee Type', 'Receipt Number', 'Amount');
echo "───────────────────────────────────────────────────────────────────────────────\n";

foreach ($receipts_to_generate as $receipt) {
    printf(
        "%-35s %-20s ₹%-19s\n",
        $receipt['fee_label'],
        $receipt['receipt_no'],
        number_format($receipt['amount'], 2)
    );
}

echo "───────────────────────────────────────────────────────────────────────────────\n";
printf("%-35s %-20s ₹%-19s\n", 'TOTAL', '', number_format($total_amount, 2));
echo "═══════════════════════════════════════════════════════════════════════════════\n\n";

echo "📝 IMPORTANT NOTES:\n";
echo "───────────────────────────────────────────────────────────────────────────────\n";
echo "1. Each fee gets its OWN receipt with INDEPENDENT numbering\n";
echo "2. All three receipts will be issued simultaneously upon payment success\n";
echo "3. Receipt numbers are simple: 1, 2, 3... (no prefixes)\n";
echo "4. School Fee receipt uses GM School's sequence (separate from SGM)\n";
echo "5. Trust & Tuition Part 2 receipts use shared sequences (all schools)\n";
echo "───────────────────────────────────────────────────────────────────────────────\n\n";

echo "📧 After payment, student will receive:\n";
echo "   • Payment confirmation email with all 3 receipt numbers\n";
echo "   • WhatsApp notification (if enabled)\n";
echo "   • Receipts can be downloaded from student portal\n\n";
