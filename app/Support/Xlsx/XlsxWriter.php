<?php

namespace App\Support\Xlsx;

use RuntimeException;
use ZipArchive;

/**
 * Oddiy .xlsx yozuvchi (tashqi paketsiz): sarlavha qatori, ustun kengligi, qotirilgan birinchi qator.
 * Katakchalar: son (int/float) yoki matn.
 */
class XlsxWriter
{
    /**
     * @param  array<int,string>  $headers
     * @param  array<int,array<int,mixed>>  $rows
     * @return string tayyor fayl yo'li (vaqtinchalik)
     */
    public static function write(string $sheetName, array $headers, array $rows, ?string $title = null): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Excel faylini yaratib bo\'lmadi.');
        }

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="'.self::esc(self::sheetName($sheetName)).'" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="3"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font><font><b/><sz val="14"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFE11D34"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="4"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/><xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/><xf numFmtId="3" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs></styleSheet>');

        $data = [];
        $rowNo = 0;
        if ($title !== null) {
            $rowNo++;
            $data[] = '<row r="'.$rowNo.'">'.self::cell(1, $rowNo, $title, 2).'</row>';
            $rowNo++;   // bo'sh qator
        }

        $rowNo++;
        $headerRow = $rowNo;
        $cells = '';
        foreach (array_values($headers) as $i => $h) {
            $cells .= self::cell($i + 1, $rowNo, $h, 1);
        }
        $data[] = '<row r="'.$rowNo.'">'.$cells.'</row>';

        $widths = array_map(fn ($h) => max(10, mb_strlen((string) $h) + 2), array_values($headers));
        foreach ($rows as $row) {
            $rowNo++;
            $cells = '';
            foreach (array_values($row) as $i => $value) {
                $cells .= self::cell($i + 1, $rowNo, $value, is_int($value) || is_float($value) ? 3 : 0);
                $widths[$i] = min(60, max($widths[$i] ?? 10, mb_strlen((string) $value) + 2));
            }
            $data[] = '<row r="'.$rowNo.'">'.$cells.'</row>';
        }

        $cols = '';
        foreach ($widths as $i => $w) {
            $cols .= '<col min="'.($i + 1).'" max="'.($i + 1).'" width="'.$w.'" customWidth="1"/>';
        }

        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="'.$headerRow.'" topLeftCell="A'.($headerRow + 1).'" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols>'.$cols.'</cols><sheetData>'.implode('', $data).'</sheetData></worksheet>');
        $zip->close();

        return $path;
    }

    private static function cell(int $col, int $row, mixed $value, int $style): string
    {
        $ref = self::columnName($col).$row;

        if ($value === null || $value === '') {
            return '<c r="'.$ref.'" s="'.$style.'"/>';
        }

        if (is_int($value) || is_float($value)) {
            return '<c r="'.$ref.'" s="'.$style.'"><v>'.$value.'</v></c>';
        }

        return '<c r="'.$ref.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'.self::esc((string) $value).'</t></is></c>';
    }

    public static function columnName(int $index): string
    {
        $name = '';
        while ($index > 0) {
            $index--;
            $name = chr(65 + $index % 26).$name;
            $index = intdiv($index, 26);
        }

        return $name;
    }

    private static function sheetName(string $name): string
    {
        return mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/u', ' ', $name), 0, 31) ?: 'Sheet1';
    }

    private static function esc(string $value): string
    {
        // XML da ruxsat etilmagan boshqaruv belgilarini olib tashlaymiz
        $value = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $value) ?? '';

        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
