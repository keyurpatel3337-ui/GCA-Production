<?php

/**
 * Pagination Component
 * 
 * Usage:
 * include '../common/pagination.php';
 * echo renderPagination($currentPage, $totalPages, $baseUrl);
 * 
 * Or use the function directly in your page.
 */

/**
 * Render pagination UI
 * 
 * @param int $currentPage Current page number (1-based)
 * @param int $totalPages Total number of pages
 * @param string $baseUrl Base URL for pagination links (e.g., "list.php?search=test")
 * @param int $showPages Number of page links to show around current page
 * @return string HTML pagination markup
 */
function renderPagination($currentPage, $totalPages, $baseUrl = '', $showPages = 2, $totalItems = null, $label = 'records')
{
    echo '<link rel="stylesheet" href="' . PORTAL_URL . '/assets/css/modules/common/pagination.css">';
    if ($totalPages <= 1) {
        if ($totalItems !== null && $totalItems > 0) {
            return '<div class="d-flex justify-content-end w-100"><div class="text-muted small">Total <strong>' . number_format($totalItems) . '</strong> ' . $label . ' found</div></div>';
        }
        return '';
    }

    // Ensure current page is within bounds
    $currentPage = max(1, min($currentPage, $totalPages));

    // Determine URL separator
    $separator = (strpos($baseUrl, '?') !== false) ? '&' : '?';

    // Build pagination HTML
    $htmlArr = [];
    $htmlArr[] = '<div class="d-flex flex-wrap justify-content-between align-items-center w-100 gap-3 mt-3">';
    if ($totalItems !== null) {
        $htmlArr[] = '<div class="text-muted small">Showing <strong>' . number_format($totalItems) . '</strong> ' . $label . ' found</div>';
    }
    
    $htmlArr[] = '<nav aria-label="Page navigation">';
    $htmlArr[] = '<ul class="pagination pagination-sm mb-0">';

    // << First button
    if ($currentPage > 1) {
        $htmlArr[] = '<li class="page-item"><a class="page-link" href="' . $baseUrl . $separator . 'page=1" title="First"><i class="fas fa-angle-double-left"></i></a></li>';
    } else {
        $htmlArr[] = '<li class="page-item disabled"><span class="page-link"><i class="fas fa-angle-double-left"></i></span></li>';
    }

    // Previous button
    if ($currentPage > 1) {
        $htmlArr[] = '<li class="page-item"><a class="page-link" href="' . $baseUrl . $separator . 'page=' . ($currentPage - 1) . '">Previous</a></li>';
    } else {
        $htmlArr[] = '<li class="page-item disabled"><span class="page-link">Previous</span></li>';
    }

    // Calculate visible page range
    $start = max(1, $currentPage - $showPages);
    $end = min($totalPages, $currentPage + $showPages);

    // Adjust start and end to show more pages if possible
    if ($end - $start < $showPages * 2) {
        if ($start == 1) {
            $end = min($totalPages, $start + ($showPages * 2));
        } else {
            $start = max(1, $end - ($showPages * 2));
        }
    }

    // Page numbers
    for ($i = $start; $i <= $end; $i++) {
        if ($i == $currentPage) {
            $htmlArr[] = '<li class="page-item active" aria-current="page"><span class="page-link shadow-sm">' . $i . '</span></li>';
        } else {
            $htmlArr[] = '<li class="page-item"><a class="page-link" href="' . $baseUrl . $separator . 'page=' . $i . '">' . $i . '</a></li>';
        }
    }

    // Next button
    if ($currentPage < $totalPages) {
        $htmlArr[] = '<li class="page-item"><a class="page-link" href="' . $baseUrl . $separator . 'page=' . ($currentPage + 1) . '">Next</a></li>';
    } else {
        $htmlArr[] = '<li class="page-item disabled"><span class="page-link">Next</span></li>';
    }

    // >> Last button
    if ($currentPage < $totalPages) {
        $htmlArr[] = '<li class="page-item"><a class="page-link" href="' . $baseUrl . $separator . 'page=' . $totalPages . '" title="Last"><i class="fas fa-angle-double-right"></i></a></li>';
    } else {
        $htmlArr[] = '<li class="page-item disabled"><span class="page-link"><i class="fas fa-angle-double-right"></i></span></li>';
    }

    $htmlArr[] = '</ul>';
    $htmlArr[] = '</nav>';

    $htmlArr[] = '</div>';

    return implode("\n", $htmlArr);
}

/**
 * Render POST-based pagination UI (for clean URLs)
 * 
 * @param int $currentPage Current page number (1-based)
 * @param int $totalPages Total number of pages
 * @param int $showPages Number of page links to show around current page
 * @param int $perPage Items per page (optional)
 * @param array $extraParams Additional hidden fields to include in the form
 * @return string HTML pagination markup with POST forms
 */
function renderPaginationPost($currentPage, $totalPages, $showPages = 2, $perPage = null, $extraParams = [], $totalItems = null, $label = 'records')
{
    $htmlArr = [];
    $htmlArr[] = '<link rel="stylesheet" href="' . PORTAL_URL . '/assets/css/modules/common/pagination.css">';
    if ($totalPages <= 1) {
        if ($totalItems !== null && $totalItems > 0) {
            return '<div class="d-flex justify-content-end w-100"><div class="text-muted small">Total <strong>' . number_format($totalItems) . '</strong> ' . $label . ' found</div></div>';
        }
        return '';
    }

    $formFieldsHtml = $perPage ? '<input type="hidden" name="per_page" value="' . (int) $perPage . '">' : '';

    if (!empty($extraParams)) {
        foreach ($extraParams as $key => $value) {
            $formFieldsHtml .= '<input type="hidden" name="' . htmlspecialchars($key ?? '') . '" value="' . htmlspecialchars($value ?? '') . '">';
        }
    }

    // Ensure current page is within bounds
    $currentPage = max(1, min($currentPage, $totalPages));

    // Build pagination HTML
    $htmlArr = [];
    $htmlArr[] = '<div class="d-flex flex-wrap justify-content-between align-items-center w-100 gap-3 mt-3">';
    if ($totalItems !== null) {
        $start = (($currentPage - 1) * ($perPage ?: 10)) + 1;
        $end = min($currentPage * ($perPage ?: 10), $totalItems);
        if ($totalItems == 0) {
            $htmlArr[] = '<div class="text-muted small">No entries found</div>';
        } else {
            $htmlArr[] = '<div class="text-muted small">Showing <strong>' . $start . '</strong> to <strong>' . $end . '</strong> of <strong>' . number_format($totalItems) . '</strong> ' . $label . '</div>';
        }
    }

    $htmlArr[] = '<nav aria-label="Page navigation">';
    $htmlArr[] = '<ul class="pagination pagination-sm mb-0">';

    // << First button
    if ($currentPage > 1) {
        $htmlArr[] = '<li class="page-item"><form method="POST" class="pagination-form"><input type="hidden" name="page" value="1">' . $formFieldsHtml . '<button type="submit" class="page-link" title="First"><i class="fas fa-angle-double-left"></i></button></form></li>';
    } else {
        $htmlArr[] = '<li class="page-item disabled"><span class="page-link"><i class="fas fa-angle-double-left"></i></span></li>';
    }

    // Previous button
    if ($currentPage > 1) {
        $htmlArr[] = '<li class="page-item"><form method="POST" class="pagination-form"><input type="hidden" name="page" value="' . ($currentPage - 1) . '">' . $formFieldsHtml . '<button type="submit" class="page-link">Previous</button></form></li>';
    } else {
        $htmlArr[] = '<li class="page-item disabled"><span class="page-link">Previous</span></li>';
    }

    // Calculate visible page range
    $start_p = max(1, $currentPage - $showPages);
    $end_p = min($totalPages, $currentPage + $showPages);

    // Adjust start and end to show more pages if possible
    if ($end_p - $start_p < $showPages * 2) {
        if ($start_p == 1) {
            $end_p = min($totalPages, $start_p + ($showPages * 2));
        } else {
            $start_p = max(1, $end_p - ($showPages * 2));
        }
    }

    // Page numbers
    for ($i = $start_p; $i <= $end_p; $i++) {
        if ($i == $currentPage) {
            $htmlArr[] = '<li class="page-item active" aria-current="page"><span class="page-link shadow-sm">' . $i . '</span></li>';
        } else {
            $htmlArr[] = '<li class="page-item"><form method="POST" class="pagination-form"><input type="hidden" name="page" value="' . $i . '">' . $formFieldsHtml . '<button type="submit" class="page-link">' . $i . '</button></form></li>';
        }
    }

    // Next button
    if ($currentPage < $totalPages) {
        $htmlArr[] = '<li class="page-item"><form method="POST" class="pagination-form"><input type="hidden" name="page" value="' . ($currentPage + 1) . '">' . $formFieldsHtml . '<button type="submit" class="page-link">Next</button></form></li>';
    } else {
        $htmlArr[] = '<li class="page-item disabled"><span class="page-link">Next</span></li>';
    }

    // >> Last button
    if ($currentPage < $totalPages) {
        $htmlArr[] = '<li class="page-item"><form method="POST" class="pagination-form"><input type="hidden" name="page" value="' . $totalPages . '">' . $formFieldsHtml . '<button type="submit" class="page-link" title="Last"><i class="fas fa-angle-double-right"></i></button></form></li>';
    } else {
        $htmlArr[] = '<li class="page-item disabled"><span class="page-link"><i class="fas fa-angle-double-right"></i></span></li>';
    }

    $htmlArr[] = '</ul>';
    $htmlArr[] = '</nav>';

    $htmlArr[] = '</div>';

    return implode("\n", $htmlArr);
}

/**
 * Get pagination info text
 * 
 * @param int $currentPage Current page
 * @param int $perPage Items per page
 * @param int $totalItems Total number of items
 * @return string Info text like "Showing 1-10 of 100 entries"
 */
function getPaginationInfo($currentPage, $perPage, $totalItems)
{
    $start = (($currentPage - 1) * $perPage) + 1;
    $end = min($currentPage * $perPage, $totalItems);

    if ($totalItems == 0) {
        return "No entries found";
    }

    return "Showing <strong>$start</strong> to <strong>$end</strong> of <strong>$totalItems</strong> entries";
}
