<?php

/**
 * Dynamic Page Template
 * Fetches page content from database and renders it
 * 
 * Usage: Include this file and call renderPage('page-slug')
 */

require_once dirname(dirname(__DIR__)) . '/common/constants.php'; require_once DB_CONNECT_FILE;

/**
 * Get page data from database
 * @param string $slug The page slug (URL identifier)
 * @return array|null Page data or null if not found
 */
function getPage($slug)
{
    global $conn;

    try {
        $stmt = $conn->prepare("SELECT * FROM tbl_pages WHERE page_slug = ? AND is_active = 1");
        $stmt->execute([$slug]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Error fetching page: " . $e->getMessage());
        return null;
    }
}

/**
 * Get page sections from database
 * @param int $pageId The page ID
 * @return array Sections array
 */
function getPageSections($pageId)
{
    global $conn;

    try {
        $stmt = $conn->prepare("SELECT * FROM tbl_page_sections WHERE page_id = ? AND is_active = 1 ORDER BY display_order ASC");
        $stmt->execute([$pageId]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error fetching page sections: " . $e->getMessage());
        return [];
    }
}

/**
 * Get page features/values from database
 * @param int $pageId The page ID
 * @param int|null $sectionId Optional section ID to filter
 * @return array Features array
 */
function getPageFeatures($pageId, $sectionId = null)
{
    global $conn;

    try {
        if ($sectionId) {
            $stmt = $conn->prepare("SELECT * FROM tbl_page_features WHERE page_id = ? AND section_id = ? AND is_active = 1 ORDER BY display_order ASC");
            $stmt->execute([$pageId, $sectionId]);
        } else {
            $stmt = $conn->prepare("SELECT * FROM tbl_page_features WHERE page_id = ? AND is_active = 1 ORDER BY display_order ASC");
            $stmt->execute([$pageId]);
        }
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error fetching page features: " . $e->getMessage());
        return [];
    }
}

/**
 * Get team members for a page
 * @param int $pageId The page ID
 * @return array Team members array
 */
function getPageTeam($pageId)
{
    global $conn;

    try {
        $stmt = $conn->prepare("SELECT * FROM tbl_page_team WHERE page_id = ? AND is_active = 1 ORDER BY display_order ASC");
        $stmt->execute([$pageId]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error fetching page team: " . $e->getMessage());
        return [];
    }
}

/**
 * Render a section based on its type
 * @param array $section Section data
 * @param array $features Features for this page
 */
function renderSection($section, $features = [])
{
    switch ($section['section_type']) {
        case 'image_text':
            renderImageTextSection($section);
            break;
        case 'text_image':
            renderTextImageSection($section);
            break;
        case 'values':
        case 'features':
            renderValuesSection($section, $features);
            break;
        case 'html':
            renderHtmlSection($section);
            break;
        case 'text':
            renderTextSection($section);
            break;
        case 'cta':
            renderCtaSection($section);
            break;
        default:
            renderTextSection($section);
    }
}

function renderImageTextSection($section)
{
    ?>
    <div class="flex flex-col lg:flex-row items-start lg:space-x-12 mb-12">
        <div class="lg:w-1/2 mb-8 lg:mb-0">
            <?php if (!empty($section['image_path'])): ?>
                <img src="<?php echo htmlspecialchars('../' . ($section['image_path'] ?? '')); ?>"
                    alt="<?php echo htmlspecialchars($section['image_alt'] ?? $section['section_title']); ?>"
                    class="rounded-lg shadow-md w-full h-auto object-cover">
            <?php endif; ?>
        </div>
        <div class="lg:w-1/2">
            <?php if (!empty($section['section_title'])): ?>
                <h2 class="text-3xl md:text-4xl font-bold text-gray-800 mb-6">
                    <?php echo htmlspecialchars($section['section_title'] ?? ''); ?></h2>
            <?php endif; ?>
            <?php echo $section['content']; ?>
        </div>
    </div>
    <?php
}

function renderTextImageSection($section)
{
    ?>
    <div class="flex flex-col lg:flex-row-reverse items-start lg:space-x-reverse lg:space-x-12 mb-12">
        <div class="lg:w-1/2 mb-8 lg:mb-0">
            <?php if (!empty($section['image_path'])): ?>
                <img src="<?php echo htmlspecialchars('../' . ($section['image_path'] ?? '')); ?>"
                    alt="<?php echo htmlspecialchars($section['image_alt'] ?? $section['section_title']); ?>"
                    class="rounded-lg shadow-md w-full h-auto object-cover">
            <?php endif; ?>
        </div>
        <div class="lg:w-1/2">
            <?php if (!empty($section['section_title'])): ?>
                <h2 class="text-3xl md:text-4xl font-bold text-gray-800 mb-6">
                    <?php echo htmlspecialchars($section['section_title'] ?? ''); ?></h2>
            <?php endif; ?>
            <?php echo $section['content']; ?>
        </div>
    </div>
    <?php
}

function renderValuesSection($section, $features)
{
    ?>
    <div class="text-center py-8">
        <?php if (!empty($section['section_title'])): ?>
            <h2 class="text-3xl md:text-4xl font-bold text-gray-800 mb-6">
                <?php echo htmlspecialchars($section['section_title'] ?? ''); ?></h2>
        <?php endif; ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 max-w-4xl mx-auto">
            <?php foreach ($features as $feature): ?>
                <div class="bg-blue-50 p-6 rounded-lg shadow-md">
                    <?php if (!empty($feature['icon'])): ?>
                        <i
                            class="fas <?php echo htmlspecialchars($feature['icon'] ?? ''); ?> text-<?php echo htmlspecialchars($feature['icon_color'] ?? 'blue'); ?>-700 text-3xl mb-3"></i>
                    <?php endif; ?>
                    <h3 class="text-xl font-semibold text-blue-700 mb-2"><?php echo htmlspecialchars($feature['title'] ?? ''); ?></h3>
                    <p class="text-gray-700"><?php echo htmlspecialchars($feature['description'] ?? ''); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}

function renderHtmlSection($section)
{
    echo $section['content'];
}

function renderTextSection($section)
{
    ?>
    <div class="mb-12">
        <?php if (!empty($section['section_title'])): ?>
            <h2 class="text-3xl md:text-4xl font-bold text-gray-800 mb-6">
                <?php echo htmlspecialchars($section['section_title'] ?? ''); ?></h2>
        <?php endif; ?>
        <?php echo $section['content']; ?>
    </div>
    <?php
}

function renderCtaSection($section)
{
    ?>
    <div class="text-center py-8 border-t border-gray-200 mt-12">
        <?php if (!empty($section['section_title'])): ?>
            <h2 class="text-3xl md:text-4xl font-bold text-gray-800 mb-6">
                <?php echo htmlspecialchars($section['section_title'] ?? ''); ?></h2>
        <?php endif; ?>
        <?php if (!empty($section['content'])): ?>
            <p class="text-lg text-gray-700 leading-relaxed max-w-2xl mx-auto mb-8"><?php echo $section['content']; ?></p>
        <?php endif; ?>
        <?php if (!empty($section['button_text']) && !empty($section['button_link'])): ?>
            <a href="<?php echo htmlspecialchars($section['button_link'] ?? ''); ?>"
                class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-full shadow-lg transition duration-300 ease-in-out transform hover:scale-105">
                <?php echo htmlspecialchars($section['button_text'] ?? ''); ?>
            </a>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Render the complete page
 * @param string $slug The page slug
 * @param string $basePath Base path for assets (default: '../')
 */
function renderPageContent($slug, $basePath = '../')
{
    $page = getPage($slug);

    if (!$page) {
        // Don't set response code here as headers may already be sent
        echo '<div class="text-center py-16"><h1 class="text-4xl font-bold text-gray-800">Page Not Found</h1><p class="text-gray-600 mt-4">The requested page could not be found.</p></div>';
        return;
    }

    $sections = getPageSections($page['id']);
    $features = getPageFeatures($page['id']);

    // Render page heading
    echo '<h1 class="text-4xl md:text-5xl font-extrabold text-gray-800 text-center mb-12">' . htmlspecialchars($page['page_heading'] ?? '') . '</h1>';

    // Render each section
    foreach ($sections as $section) {
        renderSection($section, $features);
    }
}
