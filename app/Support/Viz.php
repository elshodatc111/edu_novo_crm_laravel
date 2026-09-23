<?php

namespace App\Support;

/**
 * Grafik ma'lumotlari (spec) yordamchisi: bitta massivdan ham grafik (resources/js/charts.js),
 * ham jadval ko'rinishi quriladi, shuning uchun ikkalasi doim bir xil raqamlarni ko'rsatadi.
 *
 * Birlik (unit): 'int' | 'money' | 'percent'.
 * Rang: 'slot' (1..8, tartibli toifalar) yoki 'color' (good | critical | warning | neutral | s1..s8).
 */
class Viz
{
    /** @param array<int, array{label:string,data:array<int,int|float|null>,slot?:int,color?:string}> $datasets */
    public static function column(array $labels, array $datasets, string $unit = 'int', bool $stacked = false, ?string $nameColumn = null): array
    {
        return self::make('bar', $labels, $datasets, $unit, $stacked, $nameColumn);
    }

    public static function bar(array $labels, array $datasets, string $unit = 'int', bool $stacked = false, ?string $nameColumn = null): array
    {
        return self::make('hbar', $labels, $datasets, $unit, $stacked, $nameColumn);
    }

    public static function line(array $labels, array $datasets, string $unit = 'int', bool $area = false, ?string $nameColumn = null): array
    {
        return self::make($area ? 'area' : 'line', $labels, $datasets, $unit, false, $nameColumn);
    }

    /**
     * @param  array<int,string>|null  $colors  bo'lak ranglari (masalan ['good', 'critical']); bo'lmasa tartib bo'yicha
     * @param  array{value:string,label:string}|null  $center  aylana markazidagi yozuv
     */
    public static function donut(array $labels, array $data, string $unit = 'int', ?array $center = null, ?array $colors = null, string $seriesLabel = 'Qiymat', ?string $nameColumn = null): array
    {
        return ['type' => 'donut', 'labels' => array_values($labels), 'datasets' => [['label' => $seriesLabel, 'data' => array_values($data)]],
            'unit' => $unit, 'center' => $center, 'colors' => $colors, 'name_column' => $nameColumn ?? 'Nomi'];
    }

    private static function make(string $type, array $labels, array $datasets, string $unit, bool $stacked, ?string $nameColumn): array
    {
        return ['type' => $type, 'labels' => array_values($labels), 'unit' => $unit, 'stacked' => $stacked,
            'datasets' => array_values($datasets), 'name_column' => $nameColumn ?? 'Nomi'];
    }

    public static function format(string $unit, int|float|null $v): string
    {
        if ($v === null) {
            return '—';
        }

        return match ($unit) {
            'money' => number_format((int) round($v), 0, '', ' ')." so'm",
            'percent' => rtrim(rtrim(number_format((float) $v, 1, '.', ''), '0'), '.').'%',
            default => number_format((float) $v, 0, '', ' '),
        };
    }

    /** Qisqa ko'rinish: 12,3 mln / 450 ming (aylana markazi uchun). */
    public static function short(int|float $v): string
    {
        $a = abs($v);
        $f = fn (float $x, string $u) => rtrim(rtrim(number_format($x, 1, ',', ''), '0'), ',').' '.$u;

        return match (true) {
            $a >= 1e9 => $f($v / 1e9, 'mlrd'),
            $a >= 1e6 => $f($v / 1e6, 'mln'),
            $a >= 1e3 => $f($v / 1e3, 'ming'),
            default => (string) (int) $v,
        };
    }

    /** "2026-09" -> "Sen 26", "2026-09-05" -> "05.09" */
    public static function label(string $key): string
    {
        $mon = ['', 'Yan', 'Fev', 'Mar', 'Apr', 'May', 'Iyn', 'Iyl', 'Avg', 'Sen', 'Okt', 'Noy', 'Dek'];
        if (strlen($key) === 7) {
            return $mon[(int) substr($key, 5, 2)].' '.substr($key, 2, 2);
        }

        return substr($key, 8, 2).'.'.substr($key, 5, 2);
    }

    /** Ma'lumotlar yo'qmi (hammasi 0 yoki bo'sh)? */
    public static function isEmpty(array $spec): bool
    {
        foreach ($spec['datasets'] as $d) {
            foreach ($d['data'] as $v) {
                if ($v) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Spec'dan jadval: ustunlar, qatorlar va (ko'p seriyali bo'lsa) jami.
     *
     * @return array{columns: array<int,string>, rows: array<int, array<int,string>>, foot: array<int,string>|null}
     */
    public static function table(array $spec): array
    {
        $unit = $spec['unit'] ?? 'int';
        $isDonut = $spec['type'] === 'donut';
        $columns = [$spec['name_column'] ?? 'Nomi'];
        $rows = [];
        $foot = null;

        if ($isDonut) {
            $data = $spec['datasets'][0]['data'];
            $total = array_sum($data);
            $columns[] = $spec['datasets'][0]['label'] ?? 'Qiymat';
            $columns[] = 'Ulushi';
            foreach ($spec['labels'] as $i => $label) {
                $rows[] = [$label, self::format($unit, $data[$i] ?? 0), $total ? self::format('percent', round(($data[$i] ?? 0) * 100 / $total, 1)) : '—'];
            }
            $foot = ['Jami', self::format($unit, $total), $total ? '100%' : '—'];

            return compact('columns', 'rows', 'foot');
        }

        foreach ($spec['datasets'] as $d) {
            $columns[] = $d['label'];
        }
        foreach ($spec['labels'] as $i => $label) {
            $row = [$label];
            foreach ($spec['datasets'] as $d) {
                $row[] = self::format($unit, $d['data'][$i] ?? null);
            }
            $rows[] = $row;
        }

        // Jami faqat yig'ish ma'noli bo'lganda (son/pul, bir nechta seriya yoki ustma-ust ustunlar)
        if ($unit !== 'percent' && (count($spec['datasets']) > 1 || ! empty($spec['stacked']))) {
            $foot = ['Jami'];
            foreach ($spec['datasets'] as $d) {
                $foot[] = self::format($unit, array_sum(array_map(fn ($v) => (float) $v, $d['data'])));
            }
        }

        return compact('columns', 'rows', 'foot');
    }
}
