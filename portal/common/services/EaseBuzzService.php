<?php

require_once __DIR__ . '/../../common/easebuzz_loader.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/config/app-config.php';

class EaseBuzzService
{
    private string $merchant_key;
    private string $merchant_salt;
    private string $env;
    private ?object $easebuzz;

    public function __construct()
    {
        $config = getPaymentGatewayConfig('easebuzz');
        if (!$config) {
            throw new Exception("EaseBuzz gateway not configured");
        }

        $this->merchant_key = $config['api_key'];
        $this->merchant_salt = $config['api_secret'];
        $this->env = $config['environment'] ?? 'prod';

        if (empty($this->merchant_key) || empty($this->merchant_salt)) {
            throw new Exception("EaseBuzz credentials missing");
        }

        $this->easebuzz = new Easebuzz($this->merchant_key, $this->merchant_salt, $this->env);
    }

    /**
     * Initiate a payment and get the redirect URL
     */
    public function initiatePayment(array $params)
    {
        $result = $this->easebuzz->initiatePaymentAPI($params, false);
        $result_data = json_decode($result, true);

        if (!isset($result_data['status']) || $result_data['status'] != 1) {
            $error_msg = $result_data['data'] ?? $result_data['error'] ?? 'Payment initiation failed';
            throw new Exception($error_msg);
        }

        $access_key = $result_data['access_key'] ?? $result_data['data'] ?? '';
        if (empty($access_key)) {
            throw new Exception("Invalid payment link - missing access key");
        }

        $base_url = ($this->env === 'prod')
            ? "https://pay.easebuzz.in/pay/"
            : "https://testpay.easebuzz.in/pay/";

        return $base_url . $access_key;
    }

    /**
     * Verify callback response hash
     */
    public function verifyResponse(array $response)
    {
        $result = json_decode($this->easebuzz->easebuzzResponse($response), true);
        return ($result && isset($result['status']) && $result['status'] == 1);
    }

    /**
     * Get split amounts based on fee configuration
     */
    public function getSplitAmounts($conn, $student_id, string $payment_type, $amount, $session_splits = null)
    {
        if (!empty($session_splits)) {
            return $session_splits;
        }

        $stmt = $conn->prepare("SELECT fc.school_fee, fc.trust_facilities_fee, fc.tuition_fee_part1, fc.tuition_fee_part2,
                                fc.school_fee_label, fc.trust_fee_label, fc.tuition_fee_label
                                FROM tbl_fee_config fc
                                INNER JOIN tbl_gm_std_registration s ON s.course_id = fc.course_id
                                    AND s.medium_id = fc.medium_id
                                    AND s.group_id = fc.group_id
                                WHERE s.id = ? AND fc.is_active = 1");
        $stmt->execute([$student_id]);
        $fee_config = $stmt->fetch();

        // Fallback: if no exact match (e.g. English-medium Re-NEET students whose fee config
        // was only created for Gujarati medium), try matching by course_id alone so that
        // settlement labels (GHSS/MST/GCA) are still resolved correctly.
        if (!$fee_config) {
            $stmt_fallback = $conn->prepare("SELECT fc.school_fee, fc.trust_facilities_fee, fc.tuition_fee_part1, fc.tuition_fee_part2,
                                            fc.school_fee_label, fc.trust_fee_label, fc.tuition_fee_label
                                            FROM tbl_fee_config fc
                                            INNER JOIN tbl_gm_std_registration s ON s.course_id = fc.course_id
                                            WHERE s.id = ? AND fc.is_active = 1
                                            ORDER BY fc.id DESC LIMIT 1");
            $stmt_fallback->execute([$student_id]);
            $fee_config = $stmt_fallback->fetch();
        }

        if (!$fee_config)
            return [];

        $split_amounts = [];
        $validated_amount = floatval($amount);

        if ($payment_type === 'token_fee') {
            $tuition_part1 = floatval($fee_config['tuition_fee_part1']);
            $tuition_with_gst = $tuition_part1 + ($tuition_part1 * 0.18);
            $tuition_label = $fee_config['tuition_fee_label'];
            if (!empty($tuition_label) && $tuition_with_gst > 0) {
                $split_amounts[$tuition_label] = number_format(round($tuition_with_gst), 2, '.', '');
            }
        } else {
            // General pending fee logic
            $split_label = '';
            switch ($payment_type) {
                case 'school_fee':
                    $split_label = $fee_config['school_fee_label'];
                    break;
                case 'trust_facilities_fee':
                    $split_label = $fee_config['trust_fee_label'];
                    break;
                case 'tuition_fee_part2':
                    $split_label = $fee_config['tuition_fee_label'];
                    break;
                case 'hostel_fee':
                case 'hostel_security':
                case 'transport_fee':
                    $split_label = $fee_config['trust_fee_label'] ?? 'MST';
                    break;
            }

            if (!empty($split_label) && $validated_amount > 0) {
                $split_amounts[$split_label] = number_format(round($validated_amount), 2, '.', '');
            }
        }

        return $split_amounts;
    }

    /**
     * Helper to format common parameters
     */
    public static function formatBasicParams(array $student, $amount, string $txnid, string $productinfo, string $surl, string $furl)
    {
        $firstname = trim(($student['surname'] ?? '') . ' ' . ($student['student_name'] ?? '') . ' ' . ($student['fathers_name'] ?? ''));
        $firstname = preg_replace('/[^a-zA-Z0-9 ]/', '', substr($firstname, 0, 50));
        if (empty($firstname))
            $firstname = 'Student' . ($student['id'] ?? 'Unknown');

        $email = !empty($student['email']) ? $student['email'] : 'student' . ($student['id'] ?? '0') . '@institution.edu';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = 'student' . ($student['id'] ?? '0') . '@institution.edu';
        }

        $phone = preg_replace('/[^0-9]/', '', $student['mob'] ?? '');
        if (strlen($phone) != 10) {
            throw new Exception("Invalid mobile number for payment (must be 10 digits)");
        }

        return [
            'txnid' => $txnid,
            'amount' => number_format(round((float) $amount), 2, '.', ''),
            'productinfo' => 'Fees',
            'firstname' => $firstname,
            'email' => $email,
            'phone' => (string) $phone,
            'surl' => $surl,
            'furl' => $furl,
        ];
    }
}
