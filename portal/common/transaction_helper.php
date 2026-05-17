<?php

/**
 * Transaction Helper Functions
 * Utility functions for payment transaction management
 */

/**
 * Generate a unique transaction ID
 * @param string $prefix The prefix for the transaction ID (default: 'GMI')
 * @return string The generated unique transaction ID
 */
function generateUniqueTransactionID($prefix = 'GMI')
{
  // Generate a timestamp
  $timestamp = date('YmdHis');

  // Generate a unique identifier (shortened to 6 chars)
  $uniqueIdentifier = strtoupper(substr(uniqid(), -6));

  // Generate a random number to add to the ID
  $randomNumber = rand(1000, 9999);

  // Combine the elements to create a unique ID (Prefix + Timestamp + Unique + Random)
  // Total length: 2 + 14 + 6 + 4 = 26 characters (safely under 32)
  $transactionID = $prefix . $timestamp . $uniqueIdentifier . $randomNumber;

  return substr($transactionID, 0, 30);
}
