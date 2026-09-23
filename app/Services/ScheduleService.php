<?php

namespace App\Services;

use App\Enums\Schedule;
use App\Models\Holiday;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

class ScheduleService
{
    /**
     * Dars sanalarini hisoblaydi: tanlangan hafta kunlari, bayram va yakshanbalarsiz.
     *
     * @return array<int, string> Y-m-d sanalar
     */
    public function lessonDates(string $startsOn, int $count, Schedule $schedule): array
    {
        if ($count < 1) {
            throw new InvalidArgumentException("Darslar soni 1 dan kam bo'lishi mumkin emas.");
        }

        $start = CarbonImmutable::parse($startsOn)->startOfDay();
        $holidays = Holiday::query()
            ->whereDate('date', '>=', $start->toDateString())
            ->pluck('date')
            ->map(fn ($d) => $d->toDateString())
            ->flip();

        $weekdays = $schedule->weekdays();
        $dates = [];
        $day = $start;
        $limit = $count * 14 + 366; // cheksiz tsiklga qarshi himoya

        for ($i = 0; $i < $limit && count($dates) < $count; $i++, $day = $day->addDay()) {
            if (in_array($day->dayOfWeekIso, $weekdays, true) && ! isset($holidays[$day->toDateString()])) {
                $dates[] = $day->toDateString();
            }
        }

        return $dates;
    }
}
