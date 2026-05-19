<?php
$project_root = 'c:/xampp/htdocs/GCA-Production';
require_once $project_root . '/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;

// Include mPDF
require_once $project_root . '/portal/vendor/autoload.php';

try {
    echo "Initializing mPDF with Shruti font (useOTL => 0xFF)...\n";
    
    $defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();
    $fontDirs = $defaultConfig['fontDir'];
    $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
    $fontData = $defaultFontConfig['fontdata'];
    
    $fontData['shruti'] = [
        'R' => 'shruti.ttf',
        'useOTL' => 0xFF, // Full OTL including GPOS
        'useKashida' => 0xFF,
    ];
    
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'fontDir' => array_merge($fontDirs, [
            $project_root . '/portal/assets/fonts'
        ]),
        'fontdata' => $fontData,
        'default_font' => 'shruti'
    ]);
    
    $html = '
    <html>
    <head>
        <style>
            body { font-family: "shruti", sans-serif; font-size: 14pt; }
        </style>
    </head>
    <body>
        <h1>ડાયમિથાઇલ હેક્ઝેન (Shruti with useOTL => 0xFF)</h1>
        <p>પ્રક્રિયામાં મળતી નીપજનું બંધારણ આપો.</p>
    </body>
    </html>
    ';
    
    $mpdf->WriteHTML($html);
    $destFile = $project_root . '/portal/modules/online-exam/scratch/test_shruti_final.pdf';
    $mpdf->Output($destFile, 'F');
    
    echo "SUCCESS! PDF compiled using Shruti: $destFile (" . filesize($destFile) . " bytes)\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
