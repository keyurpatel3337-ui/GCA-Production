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
function renderPagination($currentPage, $totalPages, $baseUrl = '', $showPages = 2)
{
    if ($totalPages <= 1) {
        return '';
    }

    // Ensure current page is within bounds
    $currentPage = max(1, min($currentPage, $totalPages));

    // Determine URL separator
    $separator = (strpos($baseUrl, '?') !== false) ? '&' : '?';

    // Build pagination HTML
    $html = '<nav aria-label="Page navigation">';
    $html .= '<ul class="pagination justify-content-center mb-0">';

    // First button
    if ($currentPage > 1) {
        $html .= '<li class="page-item">';
        $html .= '<a class="page-link" href="' . $baseUrl . $separator . 'page=1" aria-label="First">';
        $html .= '<i class="fas fa-angle-double-left"></i>';
        $html .= '</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link"><i class="fas fa-angle-double-left"></i></span></li>';
    }

    // Previous button
    if ($currentPage > 1) {
        $html .= '<li class="page-item">';
        $html .= '<a class="page-link" href="' . $baseUrl . $separator . 'page=' . ($currentPage - 1) . '">';
        $html .= 'Previous</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">Previous</span></li>';
    }

    // Page 1 always shown
    if ($currentPage > $showPages + 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . $separator . 'page=1">1</a></li>';
        if ($currentPage > $showPages + 2) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }

    // Page numbers around current
    $start = max(1, $currentPage - $showPages);
    $end = min($totalPages, $currentPage + $showPages);

    for ($i = $start; $i <= $end; $i++) {
        if ($i == $currentPage) {
            $html .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
        } else {
            $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . $separator . 'page=' . $i . '">' . $i . '</a></li>';
        }
    }

    // Last pages
    if ($currentPage < $totalPages - $showPages) {
        if ($currentPage < $totalPages - $showPages - 1) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . $separator . 'page=' . $totalPages . '">' . $totalPages . '</a></li>';
    }

    // Next button
    if ($currentPage < $totalPages) {
        $html .= '<li class="page-item">';
        $html .= '<a class="page-link" href="' . $baseUrl . $separator . 'page=' . ($currentPage + 1) . '">';
        $html .= 'Next</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">Next</span></li>';
    }

    // Last button
    if ($currentPage < $totalPages) {
        $html .= '<li class="page-item">';
        $html .= '<a class="page-link" href="' . $baseUrl . $separator . 'page=' . $totalPages . '" aria-label="Last">';
        $html .= '<i class="fas fa-angle-double-right"></i>';
        $html .= '</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link"><i class="fas fa-angle-double-right"></i></span></li>';
    }

    $html .= '</ul>';
    $html .= '</nav>';

    return $html;
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

/**
 * Render POST-based pagination UI (for clean URLs)
 * 
 * @param int $currentPage Current page number (1-based)
 * @param int $totalPages Total number of pages
 * @param int $showPages Number of page links to show around current page
 * @param int $perPage Items per page (optional, will be included in forms if provided)
 * @return string HTML pagination markup with POST forms
 */
function renderPaginationPost($currentPage, $totalPages, $showPages = 2, $perPage = null)
{
    if ($totalPages <= 1) {
        return '';
    }
    
    $perPageHtml = $perPage ? '<input type="hidden" name="per_page" value="' . (int)$perPage . '">' : '';

    // Ensure current page is within bounds
    $currentPage = max(1, min($currentPage, $totalPages));

    // Build pagination HTML
    $html = '<nav aria-label="Page navigation">';
    $html .= '<ul class="pagination justify-content-start mb-0">';

    // << First button
    if ($currentPage > 1) {
        $html .= '<li class="page-item">';
        $html .= '<form method="POST" style="display:inline;margin:0;">';
        $html .= '<input type="hidden" name="page" value="1">' . $perPageHtml;
        $html .= '<button type="submit" class="page-link" aria-label="First"><i class="fas fa-angle-double-left"></i></button>';
        $html .= '</form></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link"><i class="fas fa-angle-double-left"></i></span></li>';
    }

    // Previous button
    if ($currentPage > 1) {
        $html .= '<li class="page-item">';
        $html .= '<form method="POST" style="display:inline;margin:0;">';
        $html .= '<input type="hidden" name="page" value="' . ($currentPage - 1) . '">' . $perPageHtml;
        $html .= '<button type="submit" class="page-link" aria-label="Previous">Previous</button>';
        $html .= '</form></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">Previous</span></li>';
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
            $html .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
        } else {
            $html .= '<li class="page-item">';
            $html .= '<form method="POST" style="display:inline;margin:0;">';
            $html .= '<input type="hidden" name="page" value="' . $i . '">' . $perPageHtml;
            $html .= '<button type="submit" class="page-link">' . $i . '</button>';
            $html .= '</form></li>';
        }
    }

    // Next button
    if ($currentPage < $totalPages) {
        $html .= '<li class="page-item">';
        $html .= '<form method="POST" style="display:inline;margin:0;">';
        $html .= '<input type="hidden" name="page" value="' . ($currentPage + 1) . '">' . $perPageHtml;
        $html .= '<button type="submit" class="page-link" aria-label="Next">Next</button>';
        $html .= '</form></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">Next</span></li>';
    }

    // >> Last button
    if ($currentPage < $totalPages) {
        $html .= '<li class="page-item">';
        $html .= '<form method="POST" style="display:inline;margin:0;">';
        $html .= '<input type="hidden" name="page" value="' . $totalPages . '">' . $perPageHtml;
        $html .= '<button type="submit" class="page-link" aria-label="Last"><i class="fas fa-angle-double-right"></i></button>';
        $html .= '</form></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link"><i class="fas fa-angle-double-right"></i></span></li>';
    }

    $html .= '</ul>';
    $html .= '</nav>';

    return $html;
}
