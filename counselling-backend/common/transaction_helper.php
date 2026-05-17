<?php

/**
 * Transaction Helper Functions
 * Contains utility functions for payment and transaction processing
 */

/**
 * Generate a unique transaction ID with the given prefix
 * 
 * @param string $prefix The prefix for the transaction ID (default: 'GMI')
 * @return string The generated unique transaction ID
 */
function generateUniqueTransactionID($prefix = 'GMI')
{
    // Generate a timestamp
    $timestamp = date('YmdHis');

    // Generate a unique identifier (you can customize this part)
    $uniqueIdentifier = strtoupper(uniqid());

    // Generate a random number to add to the ID
    $randomNumber = rand(1000, 9999);

    // Combine all parts to create a unique transaction ID
    $transactionID = $prefix . '_' . $timestamp . '_' . $uniqueIdentifier . '_' . $randomNumber;

    return $transactionID;
}
