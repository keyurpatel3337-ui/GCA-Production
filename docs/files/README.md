# PDF Templates Directory

This directory contains PDF templates used for generating admission letters and other documents.

## Required Files

### Admission Letter Template
- **Filename:** `ADMISSION LETTER online.pdf`
- **Location:** `c:\wamp64\www\counselling\docs\files\ADMISSION LETTER online.pdf`
- **Purpose:** Background template for admission letter generation
- **Format:** A4 size (210mm x 297mm)

## Usage

The PDF generation scripts will automatically use these templates if they exist:
- `portal/scripts/generate_pdf.php` - Main admission letter generator
- `portal/modules/students/admission-letter-pdf.php` - Alternative PDF generator

If the template file is not found, the PDF will be generated without the background template.

## Notes

- Ensure the PDF template file has the exact name: `ADMISSION LETTER online.pdf`
- The template should be in A4 format
- Both POST and GET methods are supported for generating PDFs
