<?php
$omml = '<m:oMath xmlns:m="http://schemas.openxmlformats.org/officeDocument/2006/math"><m:r><m:t>x</m:t></m:r><m:r><m:t>=</m:t></m:r><m:r><m:t>1</m:t></m:r></m:oMath>';
$doc = new DOMDocument();
$doc->loadXML($omml);

$xsl = new DOMDocument();
$xsl->load(__DIR__ . '/OMML2MML.xsl');

$proc = new XSLTProcessor();
$proc->importStyleSheet($xsl);

$mathml = $proc->transformToXML($doc);
echo "Result:\n" . $mathml . "\n";
