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
