<?php
include '../include/checklogin.php';
require_once __DIR__ . '/../../../common/helpers/format_helper.php';

if (isset($_POST['ins'])) {
    $insid = $_POST['ins'];
    $conditionsstd = array("registration_id" => $studentid, "is_active" => 1);
    $studentrecordsstd = $crud->readRecordsWithConditions("tbl_student_data", $conditionsstd);
    $crud->readSingleRecordColumn("tbl_schools", "school_address", ["id" => $school], $school_address);
    $crud->readSingleRecordColumn("tbl_schools", "school_logo", ["id" => $school], $school_logo);
    $crud->readSingleRecordColumn("tbl_websettings", "content", ["content_type" => "institute_detail"], $institute_address);
    $crud->readSingleRecordColumn("tbl_websettings", "heading", ["content_type" => "institute_detail"], $institute_name);
    $crud->readSingleRecordColumn("tbl_websettings", "content", ["content_type" => "institute_pan"], $institute_pan);
    $crud->readSingleRecordColumn("tbl_websettings", "content", ["content_type" => "institute_gstin"], $institute_gstin);

    function numberToWords($number)
    {
        if (!is_numeric($number)) {
            return "Zero";
        }

        // Cast to (int) to explicitly remove any decimal part
        $integerPart = (int) $number;

        if ($integerPart == 0) {
            return "Zero";
        }

        // Check if NumberFormatter class exists (requires intl extension)
        if (class_exists('NumberFormatter')) {
            $formatter = new NumberFormatter("en", NumberFormatter::SPELLOUT);
            return ucfirst($formatter->format($integerPart));
        }

        // Fallback: Manual number to words conversion
        $ones = [
            '',
            'One',
            'Two',
            'Three',
            'Four',
            'Five',
            'Six',
            'Seven',
            'Eight',
            'Nine',
            'Ten',
            'Eleven',
            'Twelve',
            'Thirteen',
            'Fourteen',
            'Fifteen',
            'Sixteen',
            'Seventeen',
            'Eighteen',
            'Nineteen'
        ];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

        $num = $integerPart;

        if ($num < 20)
            return $ones[$num];
        if ($num < 100)
            return $tens[(int) ($num / 10)] . ($num % 10 ? ' ' . $ones[$num % 10] : '');
        if ($num < 1000)
            return $ones[(int) ($num / 100)] . ' Hundred' . ($num % 100 ? ' ' . numberToWords($num % 100) : '');
        if ($num < 100000)
            return numberToWords((int) ($num / 1000)) . ' Thousand' . ($num % 1000 ? ' ' . numberToWords($num % 1000) : '');
        if ($num < 10000000)
            return numberToWords((int) ($num / 100000)) . ' Lakh' . ($num % 100000 ? ' ' . numberToWords($num % 100000) : '');
        return numberToWords((int) ($num / 10000000)) . ' Crore' . ($num % 10000000 ? ' ' . numberToWords($num % 10000000) : '');
    }

    if (is_array($studentrecordsstd)) {
        foreach ($studentrecordsstd as $std) {
            $total_ins = $std['total_installments'];

            $conditionPay = array("registration_id" => $studentid, "is_active" => 1, "installment_id" => $insid);
            $studentrecordPay = $crud->readRecordsWithConditions("tbl_installments_receipt", $conditionPay);

            if (is_array($studentrecordPay)) {
                foreach ($studentrecordPay as $pay) {
                    $payment_date_raw = $pay['receipt_date'];
                    $ins_i = $pay['receipt_no'];
                    $installment_no = $pay['installment_no'];
                    $payment_date = date("d/m/Y", strtotime($payment_date_raw));
                    $payment_status = $pay['payment_status'];
                    $payment_id = $pay['payment_id'];
                    $transaction_id = $pay['transaction_id'];

                    // Decode JSON safely
                    $token_split = $pay['split_amounts'];

                    // Debug: Log what we're getting
                    error_log("DEBUG split_amounts raw: " . print_r($token_split, true));

                    $data_split = json_decode($token_split, true);

                    // Debug: Log decoded data
                    error_log("DEBUG split_amounts decoded: " . print_r($data_split, true));

                    $school_value_split = isset($data_split['School']) ? $data_split['School'] : 0;
                    $institute_value_split = isset($data_split['Institute']) ? $data_split['Institute'] : 0;

                    // Debug: Log final values
                    error_log("DEBUG school_value_split: $school_value_split, institute_value_split: $institute_value_split");

                    // Handle academic year
                    $year = date('Y', strtotime($payment_date_raw));
                    $term = $year . '-' . (intval($year) + 1);
                }
            }
        }
    }

    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>GCA Receipt</title>
<link rel="stylesheet" href="<?php echo PORTAL_URL; ?>/assets/css/modules/payments/fee_receiept.php.css">
    </head>

    <body onload="check()">
        <div class="main pr1">
            <div class="header">
                <div class="tag">
                    Fees Receipt
                </div>
                <div class="logo">
                    <img src="<?= '../../../@dmin/uploads/school/logo/' . $school_logo ?>" alt="Institute Logo"
                        class="logo">
                </div>
                <div class="title-main">
                    <div class="title">
                        <div class="name">
                            <?= $school_name ?>
                        </div>
                        <div class="address">
                            <?= $school_address ?>
                        </div>
                    </div>
                    <!-- <div class="info">
                        <div class="gst">
                            <b>GSTIN</b> - 24AAKCG3743M1ZB
                        </div>
                        <div class="pan">
                            <b>PAN</b> - AAKCG3743M
                        </div>
                    </div> -->
                </div>
            </div>
            <hr class="line" />
            <div class="student">
                <div class="student-1">
                    <div class="student-name">
                        Student Name - <?= $full_name ?>
                    </div>
                    <div class="receipt-no">
                        Receipt No - <?= $ins_i ?>
                    </div>
                </div>
                <div class="student-1">
                    <div class="student-std">
                        Standard - <?= $course_name ?>
                    </div>
                    <div class="date">
                        Payment Date - <?= $payment_date ?>
                    </div>
                </div>
                <div class="student-1">
                    <div class="student-term">
                        Term - <?= $term ?> <span style="margin-right: 10px;"></span>
                    </div>
                    <div class="student-term">
                        Installment - <?= $installment_no ?> / <?= $total_ins ?><span style="margin-right: 10px;"></span>
                    </div>
                </div>
                <div class="student-1">
                    <div class="student-medium">
                        Medium - <?= $medium ?>
                    </div>
                    <div class="student-medium">
                        Payment Status - <span style="text-transform: capitalize;"><?= $payment_status ?></span>
                    </div>
                </div>
                <div class="student-1">
                    <div class="student-medium">
                        Transaction Id - <?= $transaction_id ?>
                    </div>
                    <div class="student-medium">
                        Payment Id - <?= $payment_id ?>
                    </div>
                </div>
            </div>
            <hr class="line" />
            <div class="table">
                <table>
                    <thead>
                        <tr>
                            <td style="width: 10%;">
                                Sr No
                            </td>
                            <td style="width: auto; display: flex; justify-content: center; border: 2px;">
                                Particulars
                            </td>
                            <td style="width: 15%;">
                                Amount
                            </td>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <p class="table-row mb-30">1</p>
                                <!-- <p class="table-row mb-30">2</p> -->
                            </td>
                            <td>
                                <p class="table-row mb-30">Tution Fees</p>
                                <!-- <p class="table-row mb-30">CGST 9% + SGST 9%</p> -->
                            </td>
                            <td>
                                <p class="table-row mb-30">
                                    ₹
                                    <?php
                                    if ($school_value_split !== null) {
                                        // Ensure it's treated as a number, casting can help if it's a string that looks like a number
                                        echo formatIndianCurrency((float) $school_value_split);
                                    } else {
                                        // Provide a default numeric value (e.g., 0) if it's null
                                        echo formatIndianCurrency(0); // Display 0 if the value is missing
                                    }
                                    ?>
                                </p>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="2" style="border-right: 0;">
                                <div style="display:flex; justify-content: space-between; border: 0;">
                                    <div>Rupees - <?= numberToWords((float) $school_value_split) ?></div>
                                    <div>Total&nbsp;&nbsp;</div>
                                </div>
                            </td>
                            <td style="padding-left: 0px; border-left: 0;">
                                <p class="table-row mb-30">
                                    ₹
                                    <?php
                                    if ($school_value_split !== null) {
                                        // Ensure it's treated as a number, casting can help if it's a string that looks like a number
                                        echo formatIndianCurrency((float) $school_value_split);
                                    } else {
                                        // Provide a default numeric value (e.g., 0) if it's null
                                        echo formatIndianCurrency(0); // Display 0 if the value is missing
                                    }
                                    ?>
                                </p>
                            </td>
                        </tr>
                    </tfoot>
                </table>
                <div class="foot-boottm">
                    <div style="font-size: 20px;">
                        <p class="table-row">- Subject to realization of online transaction.</p>
                        <p class="table-row">- Fees once paid is non-refundable and non-transferable.</p>
                        <!-- <p class="table-row">Check / D.D. No - </p> -->
                    </div>
                    <div
                        style="display:flex; flex-direction: column; justify-content: center; align-items: center;margin-left:10px;margin-right: 10px;margin-bottom: 10px;">
                        <p style=""><img src="../include/sign-GCA-reciept.png" alt="" style="width: 70px; height:70px;"></p>
                        <p style="margin:0;">Authorised Signatory</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="main">
            <div class="header">
                <div class="tag">
                    Fees Receipt
                </div>
                <div class="logo">
                    <img src="../mng/GCA_LOGO.png" alt="Institute Logo" class="logo">
                </div>
                <div class="title-main">
                    <div class="title">
                        <div class="name">
                            <?= $institute_name ?>
                        </div>
                        <div class="address">
                            <?= $institute_address ?>
                        </div>
                    </div>
                    <div class="info">
                        <div class="gst">
                            <b>GSTIN</b> - <?= $institute_gstin ?>
                        </div>
                        <div class="pan">
                            <b>PAN</b> - <?= $institute_pan ?>
                        </div>
                    </div>
                </div>
            </div>
            <hr class="line" />
            <div class="student">
                <div class="student-1">
                    <div class="student-name">
                        Student Name - <?= $full_name ?>
                    </div>
                    <div class="receipt-no">
                        Receipt No - <?= $ins_i ?>
                    </div>
                </div>
                <div class="student-1">
                    <div class="student-std">
                        Standard - <?= $course_name ?>
                    </div>
                    <div class="date">
                        Payment Date - <?= $payment_date ?>
                    </div>
                </div>
                <div class="student-1">
                    <div class="student-term">
                        Term - <?= $term ?> <span style="margin-right: 10px;"></span>
                    </div>
                    <div class="student-term">
                        Installment - <?= $installment_no ?> / <?= $total_ins ?><span style="margin-right: 10px;"></span>
                    </div>
                </div>
                <div class="student-1">
                    <div class="student-medium">
                        Medium - <?= $medium ?>
                    </div>
                    <div class="student-medium">
                        Payment Status - <span style="text-transform: capitalize;"><?= $payment_status ?></span>
                    </div>
                </div>
                <div class="student-1">
                    <div class="student-medium">
                        Transaction Id - <?= $transaction_id ?>
                    </div>
                    <div class="student-medium">
                        Payment Id - <?= $payment_id ?>
                    </div>
                </div>
            </div>
            <hr class="line" />
            <div class="table">
                <table>
                    <thead>
                        <tr>
                            <td style="width: 10%;">
                                Sr No
                            </td>
                            <td style="width: auto; display: flex; justify-content: center; border: 2px;">
                                Particulars
                            </td>
                            <td style="width: 15%;">
                                Amount
                            </td>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <p class="table-row mb-30">1</p>
                                <!-- <p class="table-row mb-30">2</p> -->
                            </td>
                            <td>
                                <p class="table-row mb-30">Tution Fees</p>
                                <!-- <p class="table-row mb-30">CGST 9% + SGST 9%</p> -->
                            </td>
                            <td>
                                <p class="table-row mb-30">
                                    ₹
                                    <?php
                                    if ($institute_value_split !== null) {
                                        // Ensure it's treated as a number, casting can help if it's a string that looks like a number
                                        echo formatIndianCurrency((float) $institute_value_split);
                                    } else {
                                        // Provide a default numeric value (e.g., 0) if it's null
                                        echo formatIndianCurrency(0); // Display 0 if the value is missing
                                    }
                                    ?>
                                </p>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="2" style="border-right: 0;">
                                <div style="display:flex; justify-content: space-between; border: 0;">
                                    <div>Rupees - <?= numberToWords((float) $institute_value_split) ?></div>
                                    <div>Total&nbsp;&nbsp;</div>
                                </div>
                            </td>
                            <td style="padding-left: 0px; border-left: 0;">
                                <p class="table-row mb-30">
                                    ₹
                                    <?php
                                    if ($institute_value_split !== null) {
                                        // Ensure it's treated as a number, casting can help if it's a string that looks like a number
                                        echo formatIndianCurrency((float) $institute_value_split);
                                    } else {
                                        // Provide a default numeric value (e.g., 0) if it's null
                                        echo formatIndianCurrency(0); // Display 0 if the value is missing
                                    }
                                    ?>
                                </p>
                            </td>
                        </tr>
                    </tfoot>
                </table>
                <div class="foot-boottm">
                    <div style="font-size: 20px;">
                        <p class="table-row">- Subject to realization of online transaction.</p>
                        <p class="table-row">- Fees once paid is non-refundable and non-transferable.</p>
                        <!-- <p class="table-row">Check / D.D. No - </p> -->
                    </div>
                    <div
                        style="display:flex; flex-direction: column; justify-content: center; align-items: center;margin-left:10px;margin-right: 10px;margin-bottom: 10px;">
                        <p style=""><img src="../include/sign-GCA-reciept.png" alt="" style="width: 70px; height:70px;"></p>
                        <p style="margin:0;">Authorised Signatory</p>
                    </div>
                </div>
            </div>
        </div>
        <center>
            <button class="print-button" onclick="window.print()">Print</button>
        </center>
        <script>
            function check() {
                if (/Android|webOS|iPhone|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
                    document.body.style.zoom = 0.65;
                } else {
                    document.body.style.zoom = 0.75;
                }
            }
            window.addEventListener('beforeprint', function () {
                document.body.style.zoom = 0.80;
            });
            window.addEventListener('afterprint', function () {
                if (/Android|webOS|iPhone|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
                    document.body.style.zoom = 0.65;
                } else {
                    document.body.style.zoom = 0.75;
                }
            });
        </script>
    </body>

    </html>
    <?php
} else {
    echo "Invalid Request!";
}
?>