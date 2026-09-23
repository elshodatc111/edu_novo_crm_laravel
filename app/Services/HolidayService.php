<?php

namespace App\Services;

use App\Models\Holiday;
use App\Support\BranchContext;
use Carbon\CarbonImmutable;

class HolidayService
{
    private const PUBLIC_HOLIDAYS = [
        '01-01' => 'Yangi yil',
        '03-08' => 'Xalqaro xotin-qizlar kuni',
        '03-21' => "Navro'z bayrami",
        '05-09' => 'Xotira va qadrlash kuni',
        '09-01' => 'Mustaqillik kuni',
        '10-01' => "O'qituvchi va murabbiylar kuni",
        '12-08' => 'Konstitutsiya kuni',
    ];

    /**
     * Bugundan bir yil oldinga yakshanba va rasmiy bayramlarni qo'shadi.
     * Mavjud kunlarga tegmaydi (qayta bosish xavfsiz).
     *
     * @return int qo'shilgan kunlar soni
     */
    public function generateYear(): int
    {
        $branchId = BranchContext::id();
        $existing = Holiday::pluck('date')->map(fn ($d) => $d->toDateString())->flip();

        $rows = [];
        $day = CarbonImmutable::today();
        $end = $day->addYear();

        for (; $day->lte($end); $day = $day->addDay()) {
            $date = $day->toDateString();
            $comment = self::PUBLIC_HOLIDAYS[$day->format('m-d')] ?? ($day->isSunday() ? 'Dam olish kuni (yakshanba)' : null);

            if ($comment && ! isset($existing[$date])) {
                $rows[] = ['branch_id' => $branchId, 'date' => $date, 'comment' => $comment, 'created_at' => now(), 'updated_at' => now()];
            }
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            Holiday::insert($chunk);
        }

        return count($rows);
    }
}
