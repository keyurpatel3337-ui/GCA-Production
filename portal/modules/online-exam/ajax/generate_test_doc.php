<?php
require_once 'C:/xampp/htdocs/GCA-Production/portal/vendor/autoload.php';

$phpWord = new \PhpOffice\PhpWord\PhpWord();
$section = $phpWord->addSection();

$section->addTitle('JEE Maths Mock Test', 1);
$section->addText('Standard: 12th', ['bold' => true]);
$section->addText('Subject: Mathematics', ['bold' => true]);
$section->addText('Chapter: Integration', ['bold' => true]);
$section->addText('');

// Question 1
$section->addText('1. Find the value of the following integral:');
$section->addText('Integral of sin(x) dx from 0 to pi.');
$section->addText('A) 0');
$section->addText('B) 1');
$section->addText('C) 2');
$section->addText('D) -2');
$section->addText('');

// Question 2
$section->addText('2. Which of the following is an even function?');
$section->addText('A) sin(x)');
$section->addText('B) cos(x)');
$section->addText('C) tan(x)');
$section->addText('D) x^3');
$section->addText('');

// Question 3 (With Table)
$section->addText('3. Match the following:');
$table = $section->addTable(['borderSize' => 6]);
$table->addRow();
$table->addCell(2000)->addText('Function');
$table->addCell(2000)->addText('Derivative');
$table->addRow();
$table->addCell(2000)->addText('sin(x)');
$table->addCell(2000)->addText('cos(x)');
$table->addRow();
$table->addCell(2000)->addText('x^2');
$table->addCell(2000)->addText('2x');
$section->addText('A) (1-a), (2-b)');
$section->addText('B) (1-b), (2-a)');
$section->addText('C) All correct');
$section->addText('D) None');

$objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
$filePath = 'C:/xampp/htdocs/GCA-Production/sample word file/12th JEE Maths [Paper-1] 06-05-26.docx';
$objWriter->save($filePath);

echo "Sample test file created at: " . $filePath . "\n";
?>