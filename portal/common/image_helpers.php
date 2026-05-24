<?php

/**
 * Image Helper Functions
 * Common image processing utilities
 */

if (!function_exists('cleanPngImage')) {
  /**
   * Clean PNG images by removing color profile warnings
   * 
   * @param string $source_path Path to the source PNG image
   * @return string Path to cleaned image (temporary file) or original path if cleaning failed
   */
  function cleanPngImage($source_path)
  {
    if (!file_exists($source_path)) {
      return $source_path;
    }

    // Create image from source
    $img = @imagecreatefrompng($source_path);
    if (!$img) {
      return $source_path;
    }

    // Create temporary file
    $temp_path = sys_get_temp_dir() . '/' . uniqid('clean_') . '.png';

    // Save without color profile
    imagepng($img, $temp_path, 9);
    imagedestroy($img);

    return $temp_path;
  }
}
