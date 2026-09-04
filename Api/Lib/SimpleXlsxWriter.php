<?php
/**
 * Minimal XLSX writer (Office Open XML).
 * Uses PHP ZipArchive when available; falls back to MinimalZipBuilder otherwise.
 */
declare(strict_types=1);

require_once __DIR__ . '/MinimalZipBuilder.php';

class SimpleXlsxWriter
{
    /** @var array<int,array<int,string|int|float|null>> */
    private array $rows = [];

    /** @var array<string,array{min:int,max:int}>> */
    private array $columnWidths = [];

    /** @param array<int,string|int|float|null> $cells */
    public function addRow(array $cells): void
    {
        $this->rows[] = $cells;
    }

    public function setColumnWidth(int $colIndex, float $width): void
    {
        $letter = self::colLetter($colIndex);
        $this->columnWidths[$letter] = ['min' => $colIndex, 'max' => $colIndex, 'width' => $width];
    }

    public function saveToOutput(string $filename): void
    {
        $sheet = $this->sheetXml();
        $files = [
            '[Content_Types].xml' => self::contentTypesXml(),
            '_rels/.rels' => self::relsXml(),
            'xl/_rels/workbook.xml.rels' => self::workbookRelsXml(),
            'xl/workbook.xml' => self::workbookXml(),
            'xl/styles.xml' => self::stylesXml(),
            'xl/worksheets/sheet1.xml' => $sheet,
        ];

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . self::safeFilename($filename) . '"');
        header('Cache-Control: max-age=0');
        header('Pragma: public');

        if (extension_loaded('zip') && class_exists('ZipArchive')) {
            $tmp = tempnam(sys_get_temp_dir(), 'xlsx_');
            if ($tmp === false) {
                throw new RuntimeException('Unable to create temporary file for export.');
            }

            $zip = new ZipArchive();
            if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
                @unlink($tmp);
                throw new RuntimeException('Unable to create XLSX archive.');
            }

            foreach ($files as $path => $content) {
                $zip->addFromString($path, $content);
            }
            $zip->close();
            readfile($tmp);
            @unlink($tmp);
            return;
        }

        $builder = new MinimalZipBuilder();
        foreach ($files as $path => $content) {
            $builder->addString($path, $content);
        }
        echo $builder->build();
    }

    private function sheetXml(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';

        if ($this->columnWidths) {
            $xml .= '<cols>';
            foreach ($this->columnWidths as $letter => $info) {
                $idx = $info['min'] + 1;
                $xml .= '<col min="' . $idx . '" max="' . $idx . '" width="' . $info['width'] . '" customWidth="1"/>';
            }
            $xml .= '</cols>';
        }

        $xml .= '<sheetData>';
        foreach ($this->rows as $rIdx => $cells) {
            $rowNum = $rIdx + 1;
            $xml .= '<row r="' . $rowNum . '">';
            foreach ($cells as $cIdx => $value) {
                $ref = self::colLetter($cIdx) . $rowNum;
                if ($value === null || $value === '') {
                    $xml .= '<c r="' . $ref . '" t="inlineStr"><is><t></t></is></c>';
                    continue;
                }
                if (is_int($value) || is_float($value)) {
                    $xml .= '<c r="' . $ref . '"><v>' . htmlspecialchars((string)$value, ENT_XML1) . '</v></c>';
                    continue;
                }
                $text = htmlspecialchars((string)$value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $xml .= '<c r="' . $ref . '" t="inlineStr"><is><t>' . $text . '</t></is></c>';
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData></worksheet>';
        return $xml;
    }

    private static function colLetter(int $index): string
    {
        $index += 1;
        $letters = '';
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $letters = chr(65 + $mod) . $letters;
            $index = (int)(($index - $mod) / 26);
        }
        return $letters;
    }

    private static function safeFilename(string $name): string
    {
        $name = preg_replace('/[^\w\-. ]+/u', '_', $name) ?? 'export.xlsx';
        if (!str_ends_with(strtolower($name), '.xlsx')) {
            $name .= '.xlsx';
        }
        return $name;
    }

    private static function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    private static function relsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private static function workbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private static function workbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Client Billing" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private static function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
            . '<borders count="1"><border/></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>'
            . '</styleSheet>';
    }
}
