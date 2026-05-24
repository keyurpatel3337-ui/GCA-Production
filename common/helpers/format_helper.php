<?php
/**
 * Global helper functions for data formatting
 */

if (!function_exists('formatIndianCurrency')) {
    /**
     * Format a number into Indian currency format (e.g., 4,11,98,125.00)
     * 
     * @param float|int $num The number to format
     * @param bool $decimal Whether to include decimals (default: true)
     * @return string Formatted string
     */
    function formatIndianCurrency($num, $decimal = false)
    {
        if ($num === null || $num === '')
            return '0';

        $isNegative = floatval($num) < 0;
        $num = abs(floatval($num));

        if ($decimal) {
            $num_parts = explode('.', number_format($num, 2, '.', ''));
            $num_str = $num_parts[0];
            $decimal_part = '.' . $num_parts[1];
        } else {
            $num_str = (string) round($num);
            $decimal_part = '';
        }

        if (strlen($num_str) <= 3) {
            $result = $num_str . $decimal_part;
        } else {
            $last3 = substr($num_str, -3);
            $rest = substr($num_str, 0, -3);
            // Group the rest by 2 digits
            $rest = preg_replace("/\B(?=(\d{2})+(?!\d))/", ",", $rest);
            $result = $rest . "," . $last3 . $decimal_part;
        }

        return ($isNegative ? '-' : '') . $result;
    }

    /**
     * Standard WhatsApp Amount Format: Rs.XXXX/-
     */
    function formatWhatsAppAmount($num) {
        $clean = preg_replace('/[^0-9.]/', '', (string)$num);
        if ($clean === '') $clean = '0';
        return number_format((float)$clean, 0, '', '');
    }
}

if (!function_exists('formatFeeKey')) {
    /**
     * Format a database fee key into a human-readable title
     * 
     * @param string $key The database key (e.g., 'school_fee')
     * @return string Human readable title (e.g., 'School Fee')
     */
    function formatFeeKey($key, $standard = '')
    {
        switch ($key) {
            case 'school_fee':
                return 'Tuition Fee';
            case 'trust_facilities_fee':
                return 'Trust Facilities Fee';
            case 'tuition_fee_part1':
            case 'tuition_fee_part2':
                return 'Tuition Fee (including CGST 9% + SGST 9%)';
            case 'transport_fee':
                if ($standard && strpos((string)$standard, '12') !== false) {
                    return 'Annual Vehicle Fee';
                }
                return 'Vehicle Fee';
            case 'hostel_fee':
                return 'Hostel Fee';
            case 'hostel_security':
                return 'Hostel Security Deposit';
            default:
                $key = str_replace('_', ' ', $key);
                return ucwords($key);
        }
    }
}
