<?php
require_once __DIR__ . '/../../common/constants.php';
require_once ENV_CONFIG_FILE;
require_once __DIR__ . '/../../layouts/website/page-template.php';
$page = getPage('privacy-policy');
$pageTitle = $page ? $page['page_title'] : 'Privacy Policy';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle ?? ''); ?></title>
    <?php if ($page && !empty($page['meta_description'])): ?>
        <meta name="description" content="<?php echo htmlspecialchars($page['meta_description'] ?? ''); ?>">
    <?php endif; ?>
    <link rel="icon" type="image/x-icon" href="assets/images/logogmn.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/gm-public.css">
</head>

<body class="text-gray-800">
    <?php include __DIR__ . '/../../include/public-header.php'; ?>
    <section class="container mx-auto px-4 py-16 md:py-24">
        <div class="bg-white p-8 rounded-lg shadow-xl">
            <?php renderPageContent('privacy-policy'); ?>
        </div>
    </section>
    <?php include __DIR__ . '/../../include/public-footer.php'; ?>
</body>

</html>