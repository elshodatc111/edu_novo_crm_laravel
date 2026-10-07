<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\CashRequest;
use App\Models\Group;
use App\Models\GroupStudent;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Support\Viz;
use Carbon\CarbonImmutable;

/**
 * Statistikaning YAGONA manbasi. Barcha raqamlar shu yerda, aniq ta'riflar bilan hisoblanadi
 * (veb-sahifa, hisobotlar, mobil API va AI yordamchi aynan shu natijalardan foydalanadi).
 *
 * Ta'riflar:
 *  tushum     = "to'lov" turidagi yozuvlar (naqt + plastik), qaytarilganlarsiz
 *  qaytarilgan = "qaytarish" turidagi yozuvlar
 *  sof tushum = tushum - qaytarilgan
 *  xarajat    = tasdiqlangan kassa xarajatlari + moliya balansidan xarajatlar
 *  ish haqi   = o'qituvchi va hodimlarga to'langan summalar
 *  foyda      = sof tushum - xarajat - ish haqi (pul harakati asosida, taxminiy)
 * Filial doirasi joriy foydalanuvchining filial kontekstidan olinadi (BranchContext).
 * v8: storno qilingan to'lov/chegirma/bonus (reversed_at to'ldirilgan) va rad etilgan
 * qaytarish (refund_rejected_at to'ldirilgan) hech qaysi summaga qo'shilmaydi — go'yo bo'lmagandek.
 */
class StatisticsService
{
    /** @return array<string, mixed> */
    public function overview(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $range = fn ($q, string $col = 'created_at') => $q->whereDate($col, '>=', $from->toDateString())->whereDate($col, '<=', $to->toDateString());

        // v8: storno qilingan to'lov/chegirma/bonus va rad etilgan qaytarish hisobga olinmaydi (go'yo bo'lmagandek)
        $pay = $range(Payment::query())->selectRaw("
            coalesce(sum(case when type = 'payment' and method = 'cash' and reversed_at is null then amount end), 0) as cash,
            coalesce(sum(case when type = 'payment' and method = 'card' and reversed_at is null then amount end), 0) as card,
            coalesce(sum(case when type in ('discount', 'campaign_bonus') and reversed_at is null then amount end), 0) as discounts,
            coalesce(sum(case when type = 'refund' and refund_rejected_at is null then amount end), 0) as refunds,
            coalesce(sum(case when type = 'payment' and reversed_at is null then 1 end), 0) as payments_count
        ")->first();

        $income = (int) $pay->cash + (int) $pay->card;
        $net = $income - (int) $pay->refunds;

        $expenses = (int) -$range(WalletTransaction::where('type', 'expense'))->sum('amount')
            + (int) $range(CashRequest::where('kind', CashRequest::EXPENSE)->where('status', CashRequest::APPROVED), 'decided_at')->sum('amount');
        $salaries = (int) $range(Payout::query())->sum('amount');

        $students = User::visibleToContext()->where('role', Role::Student);
        $debt = (clone $students)->whereNull('archived_at')->where('balance', '<', 0);

        $att = $range(Attendance::query(), 'date')->selectRaw('coalesce(sum(is_present), 0) as present, count(*) as total')->first();

        $leads = $range(Lead::query());
        $newLeads = (clone $leads)->count();
        $convertedLeads = (clone $leads)->where('status', Lead::CONVERTED)->count();

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'income' => $income,
            'income_cash' => (int) $pay->cash,
            'income_card' => (int) $pay->card,
            'refunds' => (int) $pay->refunds,
            'discounts' => (int) $pay->discounts,
            'net_income' => $net,
            'expenses' => $expenses,
            'salaries' => $salaries,
            'profit' => $net - $expenses - $salaries,
            'payments_count' => (int) $pay->payments_count,
            'new_students' => $range(clone $students, 'users.created_at')->count(),
            'active_students' => GroupStudent::where('is_active', true)->distinct()->count('student_id'),
            'active_groups' => Group::status(Group::ACTIVE)->count(),
            'debtors' => (clone $debt)->count(),
            'debt_total' => -1 * (int) (clone $debt)->sum('balance'),
            'new_leads' => $newLeads,
            'converted_leads' => $convertedLeads,
            'lead_conversion' => $newLeads ? round($convertedLeads * 100 / $newLeads, 1) : null,
            'attendance_rate' => $att->total ? round($att->present * 100 / $att->total, 1) : null,
            'attendance_records' => (int) $att->total,
        ];
    }

    /**
     * So'nggi oylar dinamikasi (har bir ko'rsatkich bitta guruhlangan so'rov bilan).
     *
     * @return array<int, array<string,int|string>>
     */
    public function trend(int $months = 12): array
    {
        $start = CarbonImmutable::today()->startOfMonth()->subMonths($months - 1);
        $from = $start->toDateString();
        $to = $start->addMonths($months - 1)->endOfMonth()->toDateString();

        $cash = $this->bucket(Payment::where('type', Payment::PAYMENT)->where('method', 'cash')->whereNull('reversed_at'), 'created_at', 'amount', 'month', $from, $to);
        $card = $this->bucket(Payment::where('type', Payment::PAYMENT)->where('method', 'card')->whereNull('reversed_at'), 'created_at', 'amount', 'month', $from, $to);
        $refunds = $this->bucket(Payment::where('type', Payment::REFUND)->whereNull('refund_rejected_at'), 'created_at', 'amount', 'month', $from, $to);
        $walletExp = $this->bucket(WalletTransaction::where('type', 'expense'), 'created_at', '-amount', 'month', $from, $to);
        $cashExp = $this->bucket(CashRequest::where('kind', CashRequest::EXPENSE)->where('status', CashRequest::APPROVED), 'decided_at', 'amount', 'month', $from, $to);
        $salaries = $this->bucket(Payout::query(), 'created_at', 'amount', 'month', $from, $to);
        $students = $this->bucket(User::visibleToContext()->where('role', Role::Student), 'users.created_at', '1', 'month', $from, $to);
        $leads = $this->bucket(Lead::query(), 'created_at', '1', 'month', $from, $to);
        $converted = $this->bucket(Lead::where('status', Lead::CONVERTED), 'created_at', '1', 'month', $from, $to);

        $result = [];
        for ($i = 0; $i < $months; $i++) {
            $k = $start->addMonths($i)->format('Y-m');
            $income = (int) ($cash[$k] ?? 0) + (int) ($card[$k] ?? 0);
            $result[] = [
                'month' => $k,
                'income' => $income,
                'income_cash' => (int) ($cash[$k] ?? 0),
                'income_card' => (int) ($card[$k] ?? 0),
                'refunds' => (int) ($refunds[$k] ?? 0),
                'net_income' => $income - (int) ($refunds[$k] ?? 0),
                'expenses' => (int) ($walletExp[$k] ?? 0) + (int) ($cashExp[$k] ?? 0),
                'salaries' => (int) ($salaries[$k] ?? 0),
                'new_students' => (int) ($students[$k] ?? 0),
                'new_leads' => (int) ($leads[$k] ?? 0),
                'converted_leads' => (int) ($converted[$k] ?? 0),
            ];
        }

        return $result;
    }

    /**
     * Davr ichidagi kunlik (uzun davrda oylik) tushum: naqt va plastik.
     *
     * @return array{unit:string,rows:array<int,array{key:string,cash:int,card:int}>}
     */
    public function incomeSeries(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $unit = $from->diffInDays($to) > 92 ? 'month' : 'day';
        $f = $from->toDateString();
        $t = $to->toDateString();
        $cash = $this->bucket(Payment::where('type', Payment::PAYMENT)->where('method', 'cash')->whereNull('reversed_at'), 'created_at', 'amount', $unit, $f, $t);
        $card = $this->bucket(Payment::where('type', Payment::PAYMENT)->where('method', 'card')->whereNull('reversed_at'), 'created_at', 'amount', $unit, $f, $t);

        $rows = [];
        if ($unit === 'day') {
            for ($d = $from; $d->lte($to); $d = $d->addDay()) {
                $k = $d->toDateString();
                $rows[] = ['key' => $k, 'cash' => (int) ($cash[$k] ?? 0), 'card' => (int) ($card[$k] ?? 0)];
            }
        } else {
            for ($d = $from->startOfMonth(); $d->lte($to); $d = $d->addMonth()) {
                $k = $d->format('Y-m');
                $rows[] = ['key' => $k, 'cash' => (int) ($cash[$k] ?? 0), 'card' => (int) ($card[$k] ?? 0)];
            }
        }

        return ['unit' => $unit, 'rows' => $rows];
    }

    /**
     * Davrdagi eng ko'p sof tushum keltirgan guruhlar.
     *
     * @return array<int, array{group_id:int,name:string,net:int}>
     */
    public function topGroups(CarbonImmutable $from, CarbonImmutable $to, int $limit = 8): array
    {
        return Payment::query()->join('groups', 'groups.id', '=', 'payments.group_id')
            ->whereIn('payments.type', [Payment::PAYMENT, Payment::REFUND])
            ->whereDate('payments.created_at', '>=', $from->toDateString())->whereDate('payments.created_at', '<=', $to->toDateString())
            ->selectRaw("groups.id as group_id, groups.name as name, sum(case
                when payments.type = 'payment' and payments.reversed_at is null then payments.amount
                when payments.type = 'refund' and payments.refund_rejected_at is null then -payments.amount
                else 0 end) as net")
            ->groupBy('groups.id', 'groups.name')->orderByDesc('net')->limit($limit)->get()
            ->map(fn ($r) => ['group_id' => (int) $r->group_id, 'name' => (string) $r->name, 'net' => (int) $r->net])->all();
    }

    /**
     * Kurslar bo'yicha hozirgi faol o'quvchilar soni.
     *
     * @return array<int, array{name:string,students:int}>
     */
    public function studentsByCourse(): array
    {
        return GroupStudent::query()->where('group_students.is_active', true)
            ->join('groups', 'groups.id', '=', 'group_students.group_id')->join('courses', 'courses.id', '=', 'groups.course_id')
            ->selectRaw('courses.name as name, count(distinct group_students.student_id) as students')
            ->groupBy('courses.id', 'courses.name')->orderByDesc('students')->get()
            ->map(fn ($r) => ['name' => (string) $r->name, 'students' => (int) $r->students])->all();
    }

    /**
     * Qarzdorlar qarz miqdori bo'yicha guruhlanadi.
     *
     * @return array<int, array{label:string,count:int,sum:int}>
     */
    public function debtBuckets(): array
    {
        $r = User::visibleToContext()->where('role', Role::Student)->whereNull('archived_at')->where('balance', '<', 0)
            ->selectRaw('
                coalesce(sum(case when balance >= -100000 then 1 else 0 end), 0) as c1, coalesce(sum(case when balance >= -100000 then -balance else 0 end), 0) as s1,
                coalesce(sum(case when balance < -100000 and balance >= -500000 then 1 else 0 end), 0) as c2, coalesce(sum(case when balance < -100000 and balance >= -500000 then -balance else 0 end), 0) as s2,
                coalesce(sum(case when balance < -500000 and balance >= -1000000 then 1 else 0 end), 0) as c3, coalesce(sum(case when balance < -500000 and balance >= -1000000 then -balance else 0 end), 0) as s3,
                coalesce(sum(case when balance < -1000000 then 1 else 0 end), 0) as c4, coalesce(sum(case when balance < -1000000 then -balance else 0 end), 0) as s4
            ')->first();

        return [
            ['label' => '≤ 100 ming', 'count' => (int) $r->c1, 'sum' => (int) $r->s1],
            ['label' => '100–500 ming', 'count' => (int) $r->c2, 'sum' => (int) $r->s2],
            ['label' => '500 ming–1 mln', 'count' => (int) $r->c3, 'sum' => (int) $r->s3],
            ['label' => '> 1 mln', 'count' => (int) $r->c4, 'sum' => (int) $r->s4],
        ];
    }

    /**
     * Davrdagi murojaatlar holatlari bo'yicha (voronka uchun).
     *
     * @return array{total:int,new:int,in_progress:int,converted:int,cancelled:int}
     */
    public function leadStatuses(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $c = Lead::query()->whereDate('created_at', '>=', $from->toDateString())->whereDate('created_at', '<=', $to->toDateString())
            ->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        return [
            'total' => (int) $c->sum(), 'new' => (int) ($c[Lead::NEW] ?? 0), 'in_progress' => (int) ($c[Lead::IN_PROGRESS] ?? 0),
            'converted' => (int) ($c[Lead::CONVERTED] ?? 0), 'cancelled' => (int) ($c[Lead::CANCELLED] ?? 0),
        ];
    }

    /**
     * Davomad: oy bo'yicha kelganlar ulushi (so'nggi oylar).
     *
     * @return array<string,float|null> 'Y-m' => foiz
     */
    public function attendanceByMonth(int $months = 6): array
    {
        $start = CarbonImmutable::today()->startOfMonth()->subMonths($months - 1);
        $present = $this->bucket(Attendance::query(), 'date', 'is_present', 'month', $start->toDateString(), CarbonImmutable::today()->toDateString());
        $total = $this->bucket(Attendance::query(), 'date', '1', 'month', $start->toDateString(), CarbonImmutable::today()->toDateString());
        $out = [];
        for ($i = 0; $i < $months; $i++) {
            $k = $start->addMonths($i)->format('Y-m');
            $out[$k] = ($total[$k] ?? 0) ? round((float) $present[$k] * 100 / (float) $total[$k], 1) : null;
        }

        return $out;
    }

    /**
     * v13: tushum dinamikasi kun / hafta / oy kesimida (aniq, qat'iy oynalar):
     *  - day:   so'nggi 30 kun (bugun bilan);
     *  - week:  so'nggi 12 hafta (dushanba–yakshanba; joriy hafta bugungacha);
     *  - month: so'nggi 12 oy (joriy oy bugungacha).
     * Har bir davr: naqt, plastik, tushum, qaytarilgan, sof tushum, to'lovlar soni va oldingi davrga nisbatan
     * sof tushum o'zgarishi (%; oldingi davr 0 bo'lsa null). Storno qilingan to'lov va rad etilgan qaytarish kirmaydi.
     *
     * @return array{unit:string,rows:array<int,array<string,mixed>>,totals:array<string,int>,average:int}
     */
    public function incomeDynamics(string $unit): array
    {
        $unit = in_array($unit, ['day', 'week', 'month'], true) ? $unit : 'day';
        $today = CarbonImmutable::today();

        $periods = [];
        if ($unit === 'day') {
            for ($i = 29; $i >= 0; $i--) {
                $d = $today->subDays($i);
                $periods[] = ['key' => $d->toDateString(), 'label' => $d->format('d.m'), 'from' => $d, 'to' => $d];
            }
        } elseif ($unit === 'week') {
            for ($i = 11; $i >= 0; $i--) {
                $from = $today->startOfWeek()->subWeeks($i);
                $to = $from->endOfWeek()->startOfDay();
                $to = $to->gt($today) ? $today : $to;
                $periods[] = ['key' => $from->toDateString(), 'label' => $from->format('d.m').'–'.$to->format('d.m'), 'from' => $from, 'to' => $to];
            }
        } else {
            for ($i = 11; $i >= 0; $i--) {
                $from = $today->startOfMonth()->subMonths($i);
                $to = $from->endOfMonth()->startOfDay();
                $to = $to->gt($today) ? $today : $to;
                $periods[] = ['key' => $from->format('Y-m'), 'label' => Viz::label($from->format('Y-m')), 'from' => $from, 'to' => $to];
            }
        }

        $daily = $this->dailyMoney($periods[0]['from'], $today);

        $rows = [];
        $previousNet = null;
        foreach ($periods as $p) {
            $sum = ['cash' => 0, 'card' => 0, 'refunds' => 0, 'count' => 0];
            for ($d = $p['from']; $d->lte($p['to']); $d = $d->addDay()) {
                foreach (($daily[$d->toDateString()] ?? []) as $k => $v) {
                    $sum[$k] += $v;
                }
            }
            $income = $sum['cash'] + $sum['card'];
            $net = $income - $sum['refunds'];

            $rows[] = [
                'key' => $p['key'], 'label' => $p['label'],
                'cash' => $sum['cash'], 'card' => $sum['card'], 'income' => $income, 'refunds' => $sum['refunds'],
                'net' => $net, 'count' => $sum['count'],
                'change' => $previousNet ? round(($net - $previousNet) * 100 / abs($previousNet), 1) : null,
            ];
            $previousNet = $net;
        }

        $totals = [
            'cash' => array_sum(array_column($rows, 'cash')), 'card' => array_sum(array_column($rows, 'card')),
            'refunds' => array_sum(array_column($rows, 'refunds')), 'net' => array_sum(array_column($rows, 'net')),
            'count' => array_sum(array_column($rows, 'count')),
        ];

        return ['unit' => $unit, 'rows' => $rows, 'totals' => $totals, 'average' => (int) round($totals['net'] / max(1, count($rows)))];
    }

    /**
     * v13: «Bugun / Shu hafta / Shu oy» sof tushum va o'tgan davrning XUDDI SHU muddatigacha bo'lgan qismi bilan
     * solishtirish (masalan, shu hafta dushanbadan bugungacha va o'tgan haftaning dushanbadan shu kunigacha).
     *
     * @return array<string,array{net:int,previous:int,change:float|null,count:int}>
     */
    public function incomeSnapshot(): array
    {
        $today = CarbonImmutable::today();
        $weekStart = $today->startOfWeek();
        $monthStart = $today->startOfMonth();
        $prevMonthStart = $monthStart->subMonthNoOverflow();
        $prevMonthSameDay = $prevMonthStart->addDays(min($today->day, $prevMonthStart->daysInMonth) - 1);

        $windows = [
            'today' => [[$today, $today], [$today->subDay(), $today->subDay()]],
            'week' => [[$weekStart, $today], [$weekStart->subWeek(), $today->subWeek()]],
            'month' => [[$monthStart, $today], [$prevMonthStart, $prevMonthSameDay]],
        ];

        $out = [];
        foreach ($windows as $key => [[$f, $t], [$pf, $pt]]) {
            $cur = $this->netBetween($f, $t);
            $prev = $this->netBetween($pf, $pt);
            $out[$key] = ['net' => $cur['net'], 'previous' => $prev['net'], 'change' => $prev['net'] ? round(($cur['net'] - $prev['net']) * 100 / abs($prev['net']), 1) : null, 'count' => $cur['count']];
        }

        return $out;
    }

    /** @return array{net:int,count:int} */
    private function netBetween(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $r = Payment::query()->whereDate('created_at', '>=', $from->toDateString())->whereDate('created_at', '<=', $to->toDateString())
            ->selectRaw("
                coalesce(sum(case when type = 'payment' and reversed_at is null then amount end), 0) as income,
                coalesce(sum(case when type = 'refund' and refund_rejected_at is null then amount end), 0) as refunds,
                coalesce(sum(case when type = 'payment' and reversed_at is null then 1 end), 0) as cnt
            ")->first();

        return ['net' => (int) $r->income - (int) $r->refunds, 'count' => (int) $r->cnt];
    }

    /** @return array<string,array{cash:int,card:int,refunds:int,count:int}> sana => kunlik pul */
    private function dailyMoney(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = Payment::query()
            ->whereDate('created_at', '>=', $from->toDateString())->whereDate('created_at', '<=', $to->toDateString())
            ->selectRaw("DATE(created_at) as d,
                coalesce(sum(case when type = 'payment' and method = 'cash' and reversed_at is null then amount end), 0) as cash,
                coalesce(sum(case when type = 'payment' and method = 'card' and reversed_at is null then amount end), 0) as card,
                coalesce(sum(case when type = 'refund' and refund_rejected_at is null then amount end), 0) as refunds,
                coalesce(sum(case when type = 'payment' and reversed_at is null then 1 end), 0) as cnt")
            ->groupByRaw('DATE(created_at)')->get();

        $out = [];
        foreach ($rows as $r) {
            $out[substr((string) $r->d, 0, 10)] = ['cash' => (int) $r->cash, 'card' => (int) $r->card, 'refunds' => (int) $r->refunds, 'count' => (int) $r->cnt];
        }

        return $out;
    }

    /**
     * v13: bitta hodimning (admin / menejer / operator) faoliyati: qabul qilgan to'lovlari (soni va summasi),
     * qo'shgan murojaatlari (shundan qabul qilinganlari), murojaatlarga yozgan izohlari va ro'yxatga olgan o'quvchilari.
     *  - day:   so'nggi 30 kun (har kun alohida);  - month: so'nggi 12 oy.
     * Storno qilingan to'lovlar hisobga olinmaydi.
     *
     * @return array{unit:string,rows:array<int,array<string,mixed>>,totals:array<string,int>}
     */
    public function staffActivity(User $person, string $unit = 'day'): array
    {
        $unit = $unit === 'month' ? 'month' : 'day';
        $today = CarbonImmutable::today();
        $from = $unit === 'month' ? $today->startOfMonth()->subMonths(11) : $today->subDays(29);
        $f = $from->toDateString();
        $t = $today->toDateString();

        $fields = ['payments' => 0, 'cash' => 0, 'card' => 0, 'leads' => 0, 'converted' => 0, 'notes' => 0, 'students' => 0];
        $byDate = [];
        $add = function (string $date, array $values) use (&$byDate, $fields) {
            $date = substr($date, 0, 10);
            $byDate[$date] ??= $fields;
            foreach ($values as $k => $v) {
                $byDate[$date][$k] += (int) $v;
            }
        };

        $pay = Payment::query()->where('created_by', $person->id)->where('type', Payment::PAYMENT)->whereNull('reversed_at')
            ->whereDate('created_at', '>=', $f)->whereDate('created_at', '<=', $t)
            ->selectRaw("DATE(created_at) as d, count(*) as c,
                coalesce(sum(case when method = 'cash' then amount end), 0) as cash,
                coalesce(sum(case when method = 'card' then amount end), 0) as card")
            ->groupByRaw('DATE(created_at)')->get();
        foreach ($pay as $r) {
            $add($r->d, ['payments' => $r->c, 'cash' => $r->cash, 'card' => $r->card]);
        }

        $leads = Lead::query()->where('created_by', $person->id)
            ->whereDate('created_at', '>=', $f)->whereDate('created_at', '<=', $t)
            ->selectRaw("DATE(created_at) as d, count(*) as c, coalesce(sum(case when status = ? then 1 else 0 end), 0) as conv", [Lead::CONVERTED])
            ->groupByRaw('DATE(created_at)')->get();
        foreach ($leads as $r) {
            $add($r->d, ['leads' => $r->c, 'converted' => $r->conv]);
        }

        $notes = \App\Models\LeadNote::query()->where('user_id', $person->id)->where('type', 'note')->whereIn('lead_id', Lead::query()->select('id'))
            ->whereDate('created_at', '>=', $f)->whereDate('created_at', '<=', $t)
            ->selectRaw('DATE(created_at) as d, count(*) as c')->groupByRaw('DATE(created_at)')->get();
        foreach ($notes as $r) {
            $add($r->d, ['notes' => $r->c]);
        }

        $students = \App\Models\AuditLog::query()->where('user_id', $person->id)->where('action', 'student.created')->when(\App\Support\BranchContext::id(), fn ($q, $id) => $q->where('branch_id', $id))
            ->whereDate('created_at', '>=', $f)->whereDate('created_at', '<=', $t)
            ->selectRaw('DATE(created_at) as d, count(*) as c')->groupByRaw('DATE(created_at)')->get();
        foreach ($students as $r) {
            $add($r->d, ['students' => $r->c]);
        }

        // Davrlarga yig'ish (eng yangisi tepada)
        $periods = [];
        if ($unit === 'day') {
            for ($d = $today; $d->gte($from); $d = $d->subDay()) {
                $periods[$d->toDateString()] = ['label' => $d->format('d.m.Y'), 'dates' => [$d->toDateString()]];
            }
        } else {
            for ($m = $today->startOfMonth(); $m->gte($from); $m = $m->subMonthNoOverflow()) {
                $periods[$m->format('Y-m')] = ['label' => Viz::label($m->format('Y-m')), 'dates' => []];
            }
            foreach (array_keys($byDate) as $date) {
                $periods[substr($date, 0, 7)]['dates'][] = $date;
            }
        }

        $rows = [];
        $totals = $fields;
        foreach ($periods as $key => $p) {
            $row = $fields;
            foreach ($p['dates'] as $date) {
                foreach ($byDate[$date] ?? [] as $k => $v) {
                    $row[$k] += $v;
                }
            }
            foreach ($row as $k => $v) {
                $totals[$k] += $v;
            }
            $rows[] = ['key' => $key, 'label' => $p['label'], 'total' => $row['cash'] + $row['card']] + $row;
        }

        $totals['total'] = $totals['cash'] + $totals['card'];

        return ['unit' => $unit, 'rows' => $rows, 'totals' => $totals];
    }

    /**
     * Sana ustuni bo'yicha kun/oy bo'yicha guruhlab yig'adi. Ifoda MySQL/MariaDB va SQLite uchun mos.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $value  'amount' | '-amount' | '1' | 'is_present' (ustun yig'indisi, minus, sanash)
     * @return array<string,int|float>
     */
    private function bucket($query, string $col, string $value, string $unit, string $from, string $to): array
    {
        $driver = $query->getConnection()->getDriverName();
        $qcol = str_contains($col, '.') ? $col : $query->getModel()->getTable().'.'.$col;
        if ($unit === 'month') {
            $expr = $driver === 'sqlite' ? "strftime('%Y-%m', {$qcol})" : "DATE_FORMAT({$qcol}, '%Y-%m')";
        } else {
            $expr = $driver === 'sqlite' ? "strftime('%Y-%m-%d', {$qcol})" : "DATE_FORMAT({$qcol}, '%Y-%m-%d')";
        }
        $agg = match ($value) {
            '1' => 'count(*)',
            '-amount' => '-sum('.$query->getModel()->getTable().'.amount)',
            default => 'sum('.$query->getModel()->getTable().'.'.$value.')',
        };

        return $query->whereDate($qcol, '>=', $from)->whereDate($qcol, '<=', $to)
            ->selectRaw("{$expr} as b, {$agg} as v")->groupByRaw($expr)->pluck('v', 'b')->all();
    }

    /**
     * Filiallar solishtiruvi (faqat sAdmin uchun, filial kontekstidan qat'i nazar hamma filial).
     *
     * @return array<int, array<string,mixed>>
     */
    public function branchComparison(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $between = fn ($q, string $col = 'created_at') => $q->whereDate($col, '>=', $from->toDateString())->whereDate($col, '<=', $to->toDateString());

        $income = $between(Payment::withoutGlobalScopes()->where('type', Payment::PAYMENT)->whereNull('reversed_at'))->selectRaw('branch_id, sum(amount) as s')->groupBy('branch_id')->pluck('s', 'branch_id');
        $refunds = $between(Payment::withoutGlobalScopes()->where('type', Payment::REFUND)->whereNull('refund_rejected_at'))->selectRaw('branch_id, sum(amount) as s')->groupBy('branch_id')->pluck('s', 'branch_id');
        $newStudents = $between(User::where('role', Role::Student), 'created_at')->selectRaw('branch_id, count(*) as c')->groupBy('branch_id')->pluck('c', 'branch_id');
        $active = User::where('role', Role::Student)->whereNull('archived_at')->selectRaw('branch_id, count(*) as c')->groupBy('branch_id')->pluck('c', 'branch_id');
        $debt = User::where('role', Role::Student)->whereNull('archived_at')->where('balance', '<', 0)->selectRaw('branch_id, sum(balance) as s')->groupBy('branch_id')->pluck('s', 'branch_id');

        return Branch::orderBy('name')->get()->map(fn (Branch $b) => [
            'branch' => $b->name,
            'archived' => ! $b->isActive(),
            'net_income' => (int) ($income[$b->id] ?? 0) - (int) ($refunds[$b->id] ?? 0),
            'new_students' => (int) ($newStudents[$b->id] ?? 0),
            'students' => (int) ($active[$b->id] ?? 0),
            'debt' => -1 * (int) ($debt[$b->id] ?? 0),
        ])->all();
    }
}
