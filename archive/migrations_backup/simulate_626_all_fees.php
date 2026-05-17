<?php
/**
 * Simulate Student 626 Paying All Pending Fees
 * Shows exact receipt numbers they will receive
 */

require_once __DIR__ . '/../../common/db_connect.php';
require_once __DIR__ . '/../../common/helpers/receipt_sequence_helper.php';

echo "╔═══════════════════════════════════════════════════════════════════════════════╗\n";
echo "║         STUDENT 626 - PENDING FEE PAYMENT SIMULATION                          ║\n";
echo "╚═══════════════════════════════════════════════════════════════════════════════╝\n\n";

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
echo str_repeat('-', 80) . "\n";
echo "Student ID: {$student['id']}\n";
echo "Name: {$student['name']}\n";
echo "School: " . ($student['school_id'] == 1 ? 'GM School (ID: 1)' : 'SGM School (ID: 2)') . "\n";
echo str_repeat('-', 80) . "\n\n";

$academic_year = getCurrentAcademicYear($conn);

// Pending fees to pay
$pending_fees = [
    ['fee_type' => 'school_fee', 'label' => 'School Fee', 'amount' => 50000.00],
    ['fee_type' => 'trust_facilities_fee', 'label' => 'Trust Facilities Fee', 'amount' => 25000.00],
    ['fee_type' => 'tuition_fee_part2', 'label' => 'Tuition Fee Part 2', 'amount' => 30000.00]
];

echo "Pending Fees to be Paid:\n";
echo str_repeat('-', 80) . "\n";
foreach ($pending_fees as $fee) {
    printf("• %-40s ₹%s\n", $fee['label'] . ':', number_format($fee['amount'], 2));
}
$total_amount = array_sum(array_column($pending_fees, 'amount'));
echo str_repeat('-', 80) . "\n";
printf("  TOTAL AMOUNT TO PAY:                     ₹%s\n", number_format($total_amount, 2));
echo str_repeat('=', 80) . "\n\n";

// Check current sequences BEFORE payment
echo "Current Sequence Status (BEFORE Payment):\n";
echo str_repeat('-', 80) . "\n";
printf("%-35s %-20s %-20s\n", 'Fee Type', 'Current Last', 'Next Receipt');
echo str_repeat('-', 80) . "\n";

$current_sequences = [];
foreach ($pending_fees as $fee) {
    $school_id = ($fee['fee_type'] === 'school_fee') ? $student['school_id'] : null;

    // Map tuition_fee_part2 to tuition_fee (as per new logic)
    $lookup_fee = $fee['fee_type'];
    if ($lookup_fee === 'tuition_fee_part2') {
        $lookup_fee = 'tuition_fee';
    }

    $stmt = $conn->prepare("SELECT last_sequence FROM tbl_receipt_sequences 
                           WHERE fee_type = ? 
                           AND (school_id = ? OR (school_id IS NULL AND ? IS NULL))
                           AND (academic_year = ? OR academic_year IS NULL)");
    $stmt->execute([$lookup_fee, $school_id, $school_id, $academic_year]);
    $seq = $stmt->fetch(PDO::FETCH_ASSOC);

    $last = $seq['last_sequence'] ?? 0;
    $next = $last + 1;

    $current_sequences[$fee['fee_type']] = ['last' => $last, 'next' => $next];

    printf(
        "%-35s %-20s %-20s\n",
        $fee['label'],
        $last == 0 ? 'None' : $last,
        $next
    );
}
echo str_repeat('-', 80) . "\n\n";

// Process payment - generate receipts
echo "🔄 PROCESSING PAYMENT...\n\n";

$generated_receipts = [];

foreach ($pending_fees as $fee) {
    $school_id = ($fee['fee_type'] === 'school_fee') ? $student['school_id'] : null;

    $result = getNextReceiptNumber($conn, $fee['fee_type'], $school_id, $academic_year);

    if ($result['success']) {
        $generated_receipts[] = [
            'fee_label' => $fee['label'],
            'fee_type' => $fee['fee_type'],
            'receipt_no' => $result['receipt_no'],
            'amount' => $fee['amount']
        ];

        echo "✅ {$fee['label']}: Receipt #{$result['receipt_no']} generated\n";
    } else {
        echo "❌ {$fee['label']}: Failed - {$result['error']}\n";
    }
}

echo "\n" . str_repeat('=', 80) . "\n\n";

// Display receipts
echo "✅ PAYMENT SUCCESSFUL - RECEIPTS GENERATED\n";
echo str_repeat('=', 80) . "\n";
echo "Student 626 ({$student['name']}) will receive 3 SEPARATE RECEIPTS:\n\n";

printf("%-40s %-20s %-20s\n", 'Fee Type', 'Receipt Number', 'Amount');
echo str_repeat('-', 80) . "\n";

foreach ($generated_receipts as $receipt) {
    printf(
        "%-40s %-20s ₹%-19s\n",
        $receipt['fee_label'],
        $receipt['receipt_no'],
        number_format($receipt['amount'], 2)
    );
}

echo str_repeat('-', 80) . "\n";
printf("%-40s %-20s ₹%-19s\n", 'TOTAL', '', number_format($total_amount, 2));
echo str_repeat('=', 80) . "\n\n";

// Show updated sequences AFTER payment
echo "Updated Sequence Status (AFTER Payment):\n";
echo str_repeat('-', 80) . "\n";
printf("%-35s %-20s\n", 'Fee Type', 'New Last Sequence');
echo str_repeat('-', 80) . "\n";

foreach ($pending_fees as $fee) {
    $school_id = ($fee['fee_type'] === 'school_fee') ? $student['school_id'] : null;

    $lookup_fee = $fee['fee_type'];
    if ($lookup_fee === 'tuition_fee_part2') {
        $lookup_fee = 'tuition_fee';
    }

    $stmt = $conn->prepare("SELECT last_sequence FROM tbl_receipt_sequences 
                           WHERE fee_type = ? 
                           AND (school_id = ? OR (school_id IS NULL AND ? IS NULL))
                           AND (academic_year = ? OR academic_year IS NULL)");
    $stmt->execute([$lookup_fee, $school_id, $school_id, $academic_year]);
    $seq = $stmt->fetch(PDO::FETCH_ASSOC);

    printf("%-35s %-20s\n", $fee['label'], $seq['last_sequence'] ?? 0);
}
echo str_repeat('-', 80) . "\n\n";

echo "📝 KEY POINTS:\n";
echo str_repeat('-', 80) . "\n";
echo "1. Student receives 3 SEPARATE receipts (one for each fee type)\n";
echo "2. School Fee receipt uses GM School's independent sequence\n";
echo "3. Trust Facilities Fee receipt uses shared sequence (all schools)\n";
echo "4. Tuition Fee Part 2 receipt uses the same sequence as Tuition Part 1\n";
echo "   (They are the same fee, just collected in installments)\n";
echo "5. All receipt numbers are simple sequential: 2, 1, 8, etc. (no prefixes)\n";
echo "6. Each fee type maintains independent numbering\n";
echo str_repeat('-', 80) . "\n\n";

echo "🎉 Payment processing complete!\n";
