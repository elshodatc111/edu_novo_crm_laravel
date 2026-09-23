<?php

namespace App\Support;

/**
 * v12: `API_DOC.md`ni onlayn (`/docs`) ko'rsatish uchun o'zimizning, tashqi Composer
 * paketsiz Markdown → HTML render'i (loyiha qoidasi: zaruratsiz tashqi paket qo'shilmaydi,
 * `XlsxWriter`/`XlsxReader`ga o'xshab). Faqat shu loyihaning `API_DOC.md`sida ishlatiladigan
 * konstruksiyalarni qo'llab-quvvatlaydi: sarlavhalar, **qalin**, `kod`, ```kod bloklari```,
 * jadvallar, ro'yxatlar, `> iqtibos`, havolalar, `---` chizig'i.
 */
class MarkdownRenderer
{
    public static function toHtml(string $markdown): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $markdown);
        $html = [];
        $i = 0;
        $count = count($lines);

        while ($i < $count) {
            // Xavfsizlik: har bir aylanish $i'ni albatta oldinga siljitishi kerak - agar biror
            // yangi holat (branch) buni unutib qo'ysa ham, shu tekshiruv cheksiz tsiklning oldini oladi.
            $before = $i;
            $line = $lines[$i];

            if (trim($line) === '') {
                $i++;

                continue;
            }

            // Kod bloki: ```...```
            if (preg_match('/^```/', $line)) {
                $code = [];
                $i++;
                while ($i < $count && ! preg_match('/^```/', $lines[$i])) {
                    $code[] = $lines[$i];
                    $i++;
                }
                $i++; // yopuvchi ```
                $html[] = '<pre><code>'.e(implode("\n", $code)).'</code></pre>';

                continue;
            }

            // Ajratuvchi chiziq
            if (preg_match('/^-{3,}$/', trim($line))) {
                $html[] = '<hr>';
                $i++;

                continue;
            }

            // Sarlavha
            if (preg_match('/^(#{1,6})\s+(.*)$/', $line, $m)) {
                $level = strlen($m[1]);
                $html[] = "<h{$level}>".self::inline($m[2])."</h{$level}>";
                $i++;

                continue;
            }

            // Jadval: joriy qator `|` bilan va keyingi qator ajratuvchi (---|---)
            if (str_contains($line, '|') && $i + 1 < $count && preg_match('/^\|?\s*:?-+:?\s*(\|\s*:?-+:?\s*)+\|?$/', trim($lines[$i + 1]))) {
                $headerCells = self::tableCells($line);
                $i += 2; // sarlavha + ajratuvchi qator
                $rows = [];
                while ($i < $count && str_contains($lines[$i], '|') && trim($lines[$i]) !== '') {
                    $rows[] = self::tableCells($lines[$i]);
                    $i++;
                }

                $thead = '<tr>'.implode('', array_map(fn ($c) => '<th>'.self::inline($c).'</th>', $headerCells)).'</tr>';
                $tbody = implode('', array_map(
                    fn ($row) => '<tr>'.implode('', array_map(fn ($c) => '<td>'.self::inline($c).'</td>', $row)).'</tr>',
                    $rows
                ));
                $html[] = "<table><thead>{$thead}</thead><tbody>{$tbody}</tbody></table>";

                continue;
            }

            // Iqtibos (blockquote)
            if (str_starts_with(trim($line), '>')) {
                $quote = [];
                while ($i < $count && str_starts_with(trim($lines[$i]), '>')) {
                    $quote[] = self::inline(ltrim(ltrim($lines[$i]), '> '));
                    $i++;
                }
                $html[] = '<blockquote><p>'.implode('<br>', $quote).'</p></blockquote>';

                continue;
            }

            // Ro'yxat (tartibsiz yoki tartibli)
            if (preg_match('/^\s*[-*]\s+/', $line) || preg_match('/^\s*\d+\.\s+/', $line)) {
                $ordered = (bool) preg_match('/^\s*\d+\.\s+/', $line);
                $items = [];
                while ($i < $count && (preg_match('/^\s*[-*]\s+(.*)$/', $lines[$i], $m) || preg_match('/^\s*\d+\.\s+(.*)$/', $lines[$i], $m))) {
                    $items[] = '<li>'.self::inline($m[1]).'</li>';
                    $i++;
                }
                $tag = $ordered ? 'ol' : 'ul';
                $html[] = "<{$tag}>".implode('', $items)."</{$tag}>";

                continue;
            }

            // Oddiy paragraf - bo'sh qatorgacha bo'lgan qatorlarni birlashtiradi
            $paragraph = [];
            while ($i < $count && trim($lines[$i]) !== '' && ! self::isBlockStart($lines[$i])) {
                $paragraph[] = self::inline($lines[$i]);
                $i++;
            }
            $html[] = '<p>'.implode('<br>', $paragraph).'</p>';

            if ($i === $before) {
                $i++;
            }
        }

        return implode("\n", $html);
    }

    /**
     * Paragraf yig'ishni to'xtatish kerak bo'lgan qator - boshqa blok konstruksiyasi boshlanishi.
     * Diqqat: oddiy `|` belgisi (masalan "android | ios" kabi matn ichida) blok boshlanishi
     * hisoblanmaydi - faqat haqiqiy jadval qatori (jadval tekshiruvi asosiy tsiklda alohida
     * amalga oshiriladi). Aks holda paragraf tsikli hech narsa yutmasdan to'xtab, cheksiz
     * tsiklga aylanib qolishi mumkin edi.
     */
    private static function isBlockStart(string $line): bool
    {
        $trimmed = trim($line);

        return (bool) preg_match('/^```/', $line)
            || (bool) preg_match('/^#{1,6}\s/', $line)
            || str_starts_with($trimmed, '>')
            || (bool) preg_match('/^-{3,}$/', $trimmed)
            || (bool) preg_match('/^[-*]\s+/', $trimmed)
            || (bool) preg_match('/^\d+\.\s+/', $trimmed);
    }

    /** @return array<int,string> */
    private static function tableCells(string $line): array
    {
        $line = trim($line);
        $line = preg_replace('/^\||\|$/', '', $line);

        return array_map('trim', explode('|', $line));
    }

    /** Qator ichidagi formatlash: **qalin**, `kod`, [matn](havola). Avval HTML sifatida xavfsizlantiriladi. */
    private static function inline(string $text): string
    {
        $text = e(trim($text));

        $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);
        $text = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text);
        $text = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" target="_blank" rel="noopener">$1</a>', $text);

        return $text;
    }
}
