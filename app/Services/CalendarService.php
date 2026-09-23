<?php

namespace App\Services;

use App\Models\GroupDay;
use App\Models\Room;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Bosh sahifa kalendari: tanlangan filialda oy bo'yicha qaysi kuni, qaysi vaqtda, qaysi xonada, qaysi guruhning darsi bor.
 * Manba — guruh dars kunlari jadvali (group_days), shuning uchun xona/vaqt/o'qituvchi o'zgarishlari ham hisobga olingan.
 */
class CalendarService
{
    /**
     * @return array{month:string,label:string,prev:string,next:string,cells:array<int,?string>,days:array<string,array<int,array<string,mixed>>>,rooms:array<int,array{id:int,name:string}>}
     */
    public function month(string $month, User $viewer): array
    {
        $first = CarbonImmutable::createFromFormat('!Y-m', $month)->startOfMonth();
        $last = $first->endOfMonth();

        $rows = GroupDay::query()
            ->whereHas('group')                                            // guruhning filial doirasi (BranchScope) shu yerda ham amal qiladi
            ->whereBetween('date', [$first->toDateString(), $last->toDateString()])
            ->with(['group:id,branch_id,name,teacher_id,course_id', 'group.course:id,name', 'room:id,name', 'lessonTime:id,starts_at,ends_at', 'teacher:id,name'])
            ->orderBy('date')
            ->get();

        $days = [];
        foreach ($rows as $r) {
            if (! $r->group) {
                continue;
            }
            $days[$r->date->format('Y-m-d')][] = [
                'group_id' => $r->group->id,
                'group' => $r->group->name,
                'course' => $r->group->course?->name,
                'teacher' => $r->teacher?->name,
                'room_id' => $r->room_id,
                'room' => $r->room?->name ?? '—',
                'start' => $r->lessonTime ? substr($r->lessonTime->starts_at, 0, 5) : '',
                'end' => $r->lessonTime ? substr($r->lessonTime->ends_at, 0, 5) : '',
                'url' => $viewer->can('view', $r->group) ? route('groups.show', $r->group) : null,
            ];
        }
        foreach ($days as &$list) {
            usort($list, fn ($a, $b) => [$a['start'], $a['room'], $a['group']] <=> [$b['start'], $b['room'], $b['group']]);
        }
        unset($list);

        // Dushanba boshlangan hafta jadvali: oydan oldingi bo'sh kataklar null
        $cells = array_fill(0, $first->dayOfWeekIso - 1, null);
        for ($d = $first; $d->lte($last); $d = $d->addDay()) {
            $cells[] = $d->toDateString();
        }

        $usedRooms = collect($days)->flatten(1)->pluck('room_id')->filter()->unique();

        return [
            'month' => $first->format('Y-m'),
            'label' => self::monthName((int) $first->format('n')).' '.$first->format('Y'),
            'prev' => $first->subMonth()->format('Y-m'),
            'next' => $first->addMonth()->format('Y-m'),
            'cells' => $cells,
            'days' => $days,
            'rooms' => Room::query()->whereIn('id', $usedRooms)->orderBy('name')->get(['id', 'name'])->map(fn ($r) => ['id' => $r->id, 'name' => $r->name])->all(),
        ];
    }

    public static function monthName(int $n): string
    {
        return ['', 'Yanvar', 'Fevral', 'Mart', 'Aprel', 'May', 'Iyun', 'Iyul', 'Avgust', 'Sentabr', 'Oktabr', 'Noyabr', 'Dekabr'][$n];
    }
}
