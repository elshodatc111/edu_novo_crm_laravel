<?php

namespace App\Support\Xlsx;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/** .xlsx (birinchi varaq) va .csv fayllarni qatorlar massiviga o'qiydi. */
class XlsxReader
{
    public const MAX_ROWS = 5000;

    /** @return array<int, array<int, string>> qatorlar (hech bo'lmasa bitta to'ldirilgan katakli) */
    public static function read(string $path, string $extension): array
    {
        $rows = strtolower($extension) === 'csv' ? self::readCsv($path) : self::readXlsx($path);

        if (count($rows) > self::MAX_ROWS + 1) {
            throw new RuntimeException('Faylda '.self::MAX_ROWS.' tadan ortiq qator bor. Uni bo\'laklarga bo\'ling.');
        }

        return $rows;
    }

    private static function readCsv(string $path): array
    {
        $content = file_get_contents($path);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);   // BOM
        $first = strtok($content, "\n") ?: '';
        $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';

        $rows = [];
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);
        // PHP 8.4: $escape parametrini aniq ko'rsatmaslik "deprecated" - eski xatti-harakatni ("\") saqlab qolamiz.
        while (($line = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            $line = array_map(fn ($v) => trim((string) $v), $line);
            if (array_filter($line, fn ($v) => $v !== '')) {
                $rows[] = $line;
            }
        }
        fclose($handle);

        return $rows;
    }

    private static function readXlsx(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Excel faylini ochib bo\'lmadi. Fayl .xlsx ekanligini tekshiring.');
        }

        $shared = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            foreach ((new SimpleXMLElement($xml))->si as $si) {
                $text = '';
                if (isset($si->t)) {
                    $text = (string) $si->t;
                } else {
                    foreach ($si->r as $run) {
                        $text .= (string) $run->t;
                    }
                }
                $shared[] = $text;
            }
        }

        $sheetPath = self::firstSheetPath($zip);
        $xml = $zip->getFromName($sheetPath);
        $zip->close();

        if ($xml === false) {
            throw new RuntimeException('Excel faylida varaq topilmadi.');
        }

        $rows = [];
        foreach ((new SimpleXMLElement($xml))->sheetData->row ?? [] as $row) {
            $cells = [];
            foreach ($row->c as $c) {
                $index = self::columnIndex((string) $c['r']);
                $type = (string) $c['t'];

                $value = match ($type) {
                    's' => $shared[(int) $c->v] ?? '',
                    'inlineStr' => (string) ($c->is->t ?? ''),
                    default => isset($c->v) ? (string) $c->v : '',
                };

                $cells[$index] = trim($value);
            }

            if ($cells === [] || ! array_filter($cells, fn ($v) => $v !== '')) {
                continue;
            }

            $line = [];
            for ($i = 0; $i <= max(array_keys($cells)); $i++) {
                $line[] = $cells[$i] ?? '';
            }
            $rows[] = $line;
        }

        return $rows;
    }

    private static function firstSheetPath(ZipArchive $zip): string
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($workbook !== false && $rels !== false) {
            $wb = new SimpleXMLElement($workbook);
            $rid = (string) ($wb->sheets->sheet[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'] ?? '');

            foreach ((new SimpleXMLElement($rels))->Relationship as $rel) {
                if ((string) $rel['Id'] === $rid) {
                    $target = ltrim((string) $rel['Target'], '/');

                    return str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
                }
            }
        }

        return 'xl/worksheets/sheet1.xml';
    }

    /** "C5" -> 2 */
    private static function columnIndex(string $ref): int
    {
        preg_match('/^[A-Z]+/i', $ref, $m);
        $letters = strtoupper($m[0] ?? 'A');
        $n = 0;
        foreach (str_split($letters) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return $n - 1;
    }
}
