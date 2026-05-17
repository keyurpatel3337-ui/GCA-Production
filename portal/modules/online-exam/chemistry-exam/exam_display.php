<?php
// c:\xampp\htdocs\GCA-Production\portal\modules\online-exam\chemistry-exam\exam_display.php

// In a real scenario, you'd fetch from DB. For now, we simulate a mock question.
// require_once 'db_connect.php';
// $stmt = $conn->prepare("SELECT * FROM tbl_unified_questions WHERE id = ?");
// $stmt->execute([1]);
// $q = $stmt->fetch();

$mockHtml = '<p class="mb-2">A circular hole of radius a/2 is cut out from a disc of radius a. Find the center of mass: <span class="math-equation">$$\frac{1}{2} \times \frac{2}{3}$$</span></p> <p>What is the chemical formula for water? <span class="math-equation">$$\ce{H2O}$$</span></p> <p>Here is an embedded image: <br><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII="></p>';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Chemistry & Math unified display</title>
    <!-- MathJax CDN -->
    <script src="https://polyfill.io/v3/polyfill.min.js?features=es6"></script>
    <script>
      window.MathJax = {
        loader: { load: ['[tex]/mhchem'] }, // Chemistry extension
        tex: {
          packages: {'[+]': ['mhchem']},
          inlineMath: [['$', '$'], ['\\(', '\\)']],
          displayMath: [['$$', '$$'], ['\\[', '\\]']]
        },
        startup: {
          pageReady: () => {
            return MathJax.startup.defaultPageReady(); // Render on load
          }
        }
      };
    </script>
    <script id="MathJax-script" async src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>
    <link rel="stylesheet" href="<?php echo PORTAL_URL; ?>/assets/css/modules/online-exam/chemistry-exam/exam_display.css">
</head>
<body>

    <div class="question-card">
        <h3>Question 1</h3>
        <!-- Just output the parsed HTML, browser and MathJax handle the rest -->
        <div class="question-content">
            <?php echo $mockHtml; ?>
        </div>
    </div>

</body>
</html>
