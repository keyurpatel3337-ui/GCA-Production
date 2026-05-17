<?php
// c:\xampp\htdocs\GCA-Production\portal\modules\online-exam\chemistry-exam\DocxParser.php

class DocxParser {
    private $docxPath;
    private $relationships = [];
    private $zip;

    /**
     * Initialize the parser with the path to the DOCX file.
     */
    public function __construct($docxPath) {
        $this->docxPath = $docxPath;
    }

    /**
     * Parses the Word Document in a single pass.
     * Extracts relationships, processes OMML, Images, and Text.
     * Returns the formatted HTML string.
     */
    public function parse() {
        $this->zip = new ZipArchive();
        if ($this->zip->open($this->docxPath) !== TRUE) {
            throw new Exception("Failed to open DOCX file.");
        }

        // STEP 1: Load Relationships (Maps rId5 -> word/media/image1.png)
        $relsXml = $this->zip->getFromName('word/_rels/document.xml.rels');
        if ($relsXml) {
            $dom = new DOMDocument();
            libxml_use_internal_errors(true);
            $dom->loadXML($relsXml);
            libxml_clear_errors();
            
            $relations = $dom->getElementsByTagName('Relationship');
            foreach ($relations as $rel) {
                $id = $rel->getAttribute('Id');
                $target = $rel->getAttribute('Target');
                $this->relationships[$id] = $target;
            }
        }

        // STEP 2: Open and Parse main document.xml
        $docXml = $this->zip->getFromName('word/document.xml');
        if (!$docXml) {
            throw new Exception("document.xml not found in ZIP.");
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadXML($docXml);
        libxml_clear_errors();

        // Register namespaces for drawing and math elements
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $xpath->registerNamespace('m', 'http://schemas.openxmlformats.org/officeDocument/2006/math');
        $xpath->registerNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');
        $xpath->registerNamespace('pic', 'http://schemas.openxmlformats.org/drawingml/2006/picture');
        $xpath->registerNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');

        $html = '';
        $body = $dom->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'body')->item(0);
        
        if ($body) {
            foreach ($body->childNodes as $node) {
                $html .= $this->processNode($node);
            }
        }

        $this->zip->close();
        return $html;
    }

    /**
     * Recursively process XML nodes into HTML
     */
    private function processNode(DOMNode $node) {
        $html = '';
        $name = str_replace(['w:', 'm:', 'a:', 'pic:'], '', $node->nodeName);

        switch ($name) {
            case 'p': // Paragraphs
                $content = '';
                foreach ($node->childNodes as $child) {
                    $content .= $this->processNode($child);
                }
                // Only wrap non-empty paragraphs
                if (trim(strip_tags($content)) !== '' || strpos($content, '<img') !== false || strpos($content, '<math') !== false || strpos($content, '$$') !== false) {
                    $html .= "<p style='margin-bottom: 8px;'>" . $content . "</p>";
                }
                break;
                
            case 'r': // Text Runs
                $isSub = false;
                $isSup = false;
                
                // Detect Subscript and Superscript
                $rPrs = $this->getChildrenByName($node, 'w:rPr');
                foreach ($rPrs as $rPr) {
                    $vertAligns = $this->getChildrenByName($rPr, 'w:vertAlign');
                    foreach ($vertAligns as $va) {
                        $val = $va->getAttribute('w:val');
                        if ($val === 'subscript') $isSub = true;
                        if ($val === 'superscript') $isSup = true;
                    }
                }

                $text = '';
                foreach ($node->childNodes as $child) {
                    $text .= $this->processNode($child);
                }

                if ($isSub) $text = "<sub>" . $text . "</sub>";
                if ($isSup) $text = "<sup>" . $text . "</sup>";
                
                $html .= $text;
                break;

            case 't': // Plain text
                $html .= htmlspecialchars($node->textContent);
                break;

            case 'oMath': // Word Equation (OMML)
            case 'oMathPara':
                $latex = $this->ommlToLatex($node);
                if (trim($latex) !== '') {
                    $html .= ' <span class="math-equation">$$' . $latex . '$$</span> ';
                }
                break;
                
            case 'drawing': // Inline drawing
            case 'pict':    // VML Picture
                $rId = '';
                $xpath = new DOMXPath($node->ownerDocument);
                $xpath->registerNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');
                $xpath->registerNamespace('v', 'urn:schemas-microsoft-com:vml');
                $xpath->registerNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');

                $blips = $xpath->evaluate('.//a:blip', $node);
                if ($blips->length > 0) {
                    $rId = $blips->item(0)->getAttribute('r:embed');
                } else {
                    $imagedata = $xpath->evaluate('.//v:imagedata', $node);
                    if ($imagedata->length > 0) {
                        $rId = $imagedata->item(0)->getAttribute('r:id');
                    }
                }

                // If image relationship is found, extract as Base64
                if ($rId && isset($this->relationships[$rId])) {
                    $targetPath = 'word/' . $this->relationships[$rId];
                    $imgData = $this->zip->getFromName($targetPath);
                    if ($imgData) {
                        $ext = pathinfo($targetPath, PATHINFO_EXTENSION);
                        $mime = 'image/png';
                        if (in_array(strtolower($ext), ['jpeg', 'jpg'])) $mime = 'image/jpeg';
                        if (strtolower($ext) === 'gif') $mime = 'image/gif';
                        
                        $base64 = base64_encode($imgData);
                        $html .= '<img src="data:' . $mime . ';base64,' . $base64 . '" style="max-width:100%; height:auto;" />';
                    }
                }
                break;

            default:
                if ($node->hasChildNodes()) {
                    foreach ($node->childNodes as $child) {
                        $html .= $this->processNode($child);
                    }
                }
                break;
        }

        return $html;
    }

    private function getChildrenByName(DOMNode $node, $name) {
        $result = [];
        foreach ($node->childNodes as $child) {
            if ($child->nodeName === $name) {
                $result[] = $child;
            }
        }
        return $result;
    }

    /**
     * Native PHP Converter: OMML (Microsoft Math) to LaTeX
     */
    private function ommlToLatex(DOMNode $node) {
        if ($node->nodeType === XML_TEXT_NODE) {
            return htmlspecialchars($node->textContent);
        }
        
        $latex = '';
        foreach ($node->childNodes as $child) {
            $name = str_replace('m:', '', $child->nodeName);
            switch ($name) {
                case 'f':
                    $num = ''; $den = '';
                    foreach ($child->childNodes as $fChild) {
                        if ($fChild->nodeName == 'm:num') $num = $this->ommlToLatex($fChild);
                        if ($fChild->nodeName == 'm:den') $den = $this->ommlToLatex($fChild);
                    }
                    $latex .= "\\frac{{$num}}{{{$den}}} ";
                    break;
                case 'rad':
                    $deg = ''; $e = '';
                    foreach ($child->childNodes as $rChild) {
                        if ($rChild->nodeName == 'm:deg') $deg = $this->ommlToLatex($rChild);
                        if ($rChild->nodeName == 'm:e') $e = $this->ommlToLatex($rChild);
                    }
                    $latex .= $deg ? "\\sqrt[{$deg}]{{{$e}}} " : "\\sqrt{{$e}} ";
                    break;
                case 'sSup':
                    $e = ''; $sup = '';
                    foreach ($child->childNodes as $sChild) {
                        if ($sChild->nodeName == 'm:e') $e = $this->ommlToLatex($sChild);
                        if ($sChild->nodeName == 'm:sup') $sup = $this->ommlToLatex($sChild);
                    }
                    $latex .= "{$e}^{{{$sup}}} ";
                    break;
                case 'sSub':
                    $e = ''; $sub = '';
                    foreach ($child->childNodes as $sChild) {
                        if ($sChild->nodeName == 'm:e') $e = $this->ommlToLatex($sChild);
                        if ($sChild->nodeName == 'm:sub') $sub = $this->ommlToLatex($sChild);
                    }
                    $latex .= "{$e}_{{{$sub}}} ";
                    break;
                case 'sSubSup':
                    $e = ''; $sub = ''; $sup = '';
                    foreach ($child->childNodes as $sChild) {
                        if ($sChild->nodeName == 'm:e') $e = $this->ommlToLatex($sChild);
                        if ($sChild->nodeName == 'm:sub') $sub = $this->ommlToLatex($sChild);
                        if ($sChild->nodeName == 'm:sup') $sup = $this->ommlToLatex($sChild);
                    }
                    $latex .= "{$e}_{{{$sub}}}^{{{$sup}}} ";
                    break;
                case 'd':
                    $e = '';
                    foreach ($child->childNodes as $dChild) {
                        if ($dChild->nodeName == 'm:e') $e = $this->ommlToLatex($dChild);
                    }
                    $latex .= "\\left( {$e} \\right) ";
                    break;
                case 'r':
                    $t = '';
                    foreach ($child->childNodes as $rChild) {
                        if ($rChild->nodeName == 'm:t') $t = $rChild->textContent;
                    }
                    $t = str_replace(['×', '÷', '−'], ['\times', '\div', '-'], $t);
                    $latex .= $t . ' ';
                    break;
                case '#text':
                    $latex .= $child->textContent;
                    break;
                default:
                    if ($child->hasChildNodes()) {
                        $latex .= $this->ommlToLatex($child);
                    }
                    break;
            }
        }
        return trim($latex);
    }
}
?>
