<?php
function ommlToLatex(DOMNode $node) {
    if ($node->nodeType === XML_TEXT_NODE) {
        return htmlspecialchars($node->textContent);
    }
    
    $latex = '';
    foreach ($node->childNodes as $child) {
        $name = str_replace('m:', '', $child->nodeName);
        
        switch ($name) {
            case 'f': // Fraction
                $num = ''; $den = '';
                foreach ($child->childNodes as $fChild) {
                    if ($fChild->nodeName == 'm:num') $num = ommlToLatex($fChild);
                    if ($fChild->nodeName == 'm:den') $den = ommlToLatex($fChild);
                }
                $latex .= "\\frac{{$num}}{{{$den}}} ";
                break;
            case 'rad': // Radical / Root
                $deg = ''; $e = '';
                foreach ($child->childNodes as $rChild) {
                    if ($rChild->nodeName == 'm:deg') $deg = ommlToLatex($rChild);
                    if ($rChild->nodeName == 'm:e') $e = ommlToLatex($rChild);
                }
                if ($deg) {
                    $latex .= "\\sqrt[{$deg}]{{{$e}}} ";
                } else {
                    $latex .= "\\sqrt{{$e}} ";
                }
                break;
            case 'sSup': // Superscript
                $e = ''; $sup = '';
                foreach ($child->childNodes as $sChild) {
                    if ($sChild->nodeName == 'm:e') $e = ommlToLatex($sChild);
                    if ($sChild->nodeName == 'm:sup') $sup = ommlToLatex($sChild);
                }
                $latex .= "{$e}^{{{$sup}}} ";
                break;
            case 'sSub': // Subscript
                $e = ''; $sub = '';
                foreach ($child->childNodes as $sChild) {
                    if ($sChild->nodeName == 'm:e') $e = ommlToLatex($sChild);
                    if ($sChild->nodeName == 'm:sub') $sub = ommlToLatex($sChild);
                }
                $latex .= "{$e}_{{{$sub}}} ";
                break;
            case 'sSubSup': // Subscript and Superscript
                $e = ''; $sub = ''; $sup = '';
                foreach ($child->childNodes as $sChild) {
                    if ($sChild->nodeName == 'm:e') $e = ommlToLatex($sChild);
                    if ($sChild->nodeName == 'm:sub') $sub = ommlToLatex($sChild);
                    if ($sChild->nodeName == 'm:sup') $sup = ommlToLatex($sChild);
                }
                $latex .= "{$e}_{{{$sub}}}^{{{$sup}}} ";
                break;
            case 'd': // Delimiter (brackets)
                $e = '';
                foreach ($child->childNodes as $dChild) {
                    if ($dChild->nodeName == 'm:e') $e = ommlToLatex($dChild);
                }
                $latex .= "\\left( {$e} \\right) ";
                break;
            case 'r': // Run
                $t = '';
                foreach ($child->childNodes as $rChild) {
                    if ($rChild->nodeName == 'm:t') $t = $rChild->textContent;
                }
                // Check if it's text or operators
                // Basic cleanup for special word operators
                $t = str_replace(['×', '÷', '−'], ['\times', '\div', '-'], $t);
                $latex .= $t . ' ';
                break;
            case '#text':
                $latex .= $child->textContent;
                break;
            default:
                if ($child->hasChildNodes()) {
                    $latex .= ommlToLatex($child);
                }
                break;
        }
    }
    return trim($latex);
}

$omml = '<m:oMathPara><m:oMath><m:f><m:num><m:r><m:t>1</m:t></m:r></m:num><m:den><m:r><m:t>2</m:t></m:r></m:den></m:f></m:oMath></m:oMathPara>';
$doc = new DOMDocument();
$doc->loadXML($omml);
$latex = ommlToLatex($doc->documentElement);
echo "LaTeX: $$" . $latex . "$$\n";
