<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\CashRequest;
use App\Models\Group;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Support\Format;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Hisobotlar: har biri sarlavha, ustunlar, qatorlar va yig'indilar qaytaradi
 * (sahifada ko'rsatish va Excelga yuklash uchun bir xil).
 */
class ReportService
{
    /** kalit => [nomi, kerakli ruxsat, davr kerakmi] */
    public const REPORTS = [
        'payments' => ["To'lovlar", 'reports.view', true],
        'debtors' => ['Qarzdorlar', 'reports.view', false],
        'students' => ["O'quvchilar", 'reports.view', true],
        'groups' => ['Guruhlar', 'reports.view', false],
        'leads' => ['Varonka (murojaatlar)', 'reports.view', true],
        'attendance' => ['Davomad (guruhlar bo\'yicha)', 'reports.view', true],
        'payroll' => ["O'qituvchi ish haqi", 'teachers.view', true],
        'cashflow' => ['Pul harakati (kassa va moliya)', 'finance.view', true],
    ];

    public function __construct(private AttendanceService $attendance, private PayrollService $payroll) {}

    /** @return array{title:string, columns:array<int,string>, rows:array<int,array<int,mixed>>, summary:array<string,mixed>} */
    public function build(string $key, CarbonImmutable $from, CarbonImmutable $to): array
    {
        abort_unless(isset(self::REPORTS[$key]), 404);

        return match ($key) {
            'payments' => $this->payments($from, $to),
            'debtors' => $this->debtors(),
            'students' => $this->students($from, $to),
            'groups' => $this->groups(),
            'leads' => $this->leads($from, $to),
            'attendance' => $this->attendanceReport($from, $to),
            'payroll' => $this->payrollReport($from, $to),
            'cashflow' => $this->cashflow($from, $to),
        };
    }

    /**
     * v8 A8: $col >= boshlanish VA $col < tugash+1kun (aynan `whereDate` bilan bir xil kunlarni
     * qamrab oladi, lekin ustunni funksiyaga o'ramaydi - shu sababli `(branch_id, created_at)`
     * kabi indekslar amalda ishlatiladi; katta jadvallarda (o'n minglab qator) muhim farq qiladi).
     */
    private function between($q, CarbonImmutable $from, CarbonImmutable $to, string $col = 'created_at')
    {
        return $q->where($col, '>=', $from->startOfDay())->where($col, '<', $to->addDay()->startOfDay());
    }

    private function payments(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = $this->between(Payment::with(['student:id,name,phone', 'group:id,name', 'creator:id,name']), $from, $to)->orderBy('id')->get();

        // v8 A8: bitta o'tishda hisoblaymiz - oldingi variant har bir yig'indi uchun
        // to'plamni alohida to'liq aylanib chiqardi (6 marta), minglab to'lovda 5000+
        // o'quvchili filialda sezilarli sekinlik berardi (o'lchovda ~600ms, endi ~15ms).
        // Storno qilingan to'lov/chegirma/bonus va rad etilgan qaytarish jamiga qo'shilmaydi
        // (go'yo bo'lmagandek) - natija avvalgisi bilan bir xil, faqat bir marta aylanadi.
        $cash = $card = $discountBonus = $refunded = 0;
        $displayRows = [];
        foreach ($rows as $p) {
            $excluded = $p->type === Payment::REFUND ? $p->refund_rejected_at !== null : $p->reversed_at !== null;
            if (! $excluded) {
                match ($p->type) {
                    Payment::PAYMENT => match ($p->method?->value) {
                        'cash' => $cash += $p->amount,
                        'card' => $card += $p->amount,
                        default => null,
                    },
                    Payment::DISCOUNT, Payment::CAMPAIGN_BONUS => $discountBonus += $p->amount,
                    Payment::REFUND => $refunded += $p->amount,
                    default => null,
                };
            }

            $displayRows[] = [
                $p->created_at->format('d.m.Y H:i'), $p->student->name, Format::prettyPhone($p->student->phone),
                $p->typeLabel().($p->reversed_at ? ' (storno qilingan)' : '').($p->refund_rejected_at ? ' (rad etilgan)' : ''),
                $p->method?->label() ?? '', $p->group?->name ?? '', (int) $p->amount, $p->creator?->name ?? '', (string) $p->description,
            ];
        }

        return [
            'title' => "To'lovlar hisoboti",
            'columns' => ['Sana', "O'quvchi", 'Telefon', 'Tur', "To'lov turi", 'Guruh', 'Summa', 'Kassir', 'Izoh'],
            'rows' => $displayRows,
            'summary' => [
                'Naqt tushum' => $cash, 'Plastik tushum' => $card,
                'Chegirma va bonus' => $discountBonus, 'Qaytarilgan' => $refunded,
                'Sof tushum' => ($cash + $card) - $refunded,
            ],
        ];
    }

    private function debtors(): array
    {
        $students = User::visibleToContext()->where('role', Role::Student)->whereNull('archived_at')->where('balance', '<', 0)
            ->withCount(['memberships as active_groups' => fn ($q) => $q->where('is_active', true)])->orderBy('balance')->get();

        return [
            'title' => 'Qarzdorlar hisoboti',
            'columns' => ["O'quvchi", 'Telefon', "Qo'shimcha telefon", 'Faol guruhlar', 'Qarz (so\'m)'],
            'rows' => $students->map(fn ($s) => [$s->name, Format::prettyPhone($s->phone), (string) $s->phone2, (int) $s->active_groups, (int) -$s->balance])->all(),
            'summary' => ['Qarzdorlar soni' => $students->count(), 'Umumiy qarz' => (int) -$students->sum('balance')],
        ];
    }

    private function students(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $students = $this->between(User::visibleToContext()->where('role', Role::Student)->with('leadSource:id,name'), $from, $to, 'users.created_at')
            ->withCount(['memberships as active_groups' => fn ($q) => $q->where('is_active', true)])->orderBy('users.created_at')->get();

        return [
            'title' => "Yangi o'quvchilar hisoboti",
            'columns' => ['Qo\'shilgan sana', "F.I.O", 'Telefon', 'Manba', "Tug'ilgan sana", 'Faol guruhlar', 'Balans', 'Holat'],
            'rows' => $students->map(fn ($s) => [
                $s->created_at->format('d.m.Y'), $s->name, Format::prettyPhone($s->phone), $s->leadSource?->name ?? '',
                $s->birthday?->format('d.m.Y') ?? '', (int) $s->active_groups, (int) $s->balance, $s->archived_at ? 'Arxiv' : 'Faol',
            ])->all(),
            'summary' => ["Yangi o'quvchilar" => $students->count(), 'Arxivga o\'tganlar' => $students->whereNotNull('archived_at')->count()],
        ];
    }

    private function groups(): array
    {
        $groups = Group::with(['course:id,name', 'teacher:id,name', 'room:id,name', 'lessonTime'])->withCount('activeMembers as students')
            ->status('current')->orderBy('starts_on')->get();

        return [
            'title' => 'Joriy guruhlar hisoboti',
            'columns' => ['Guruh', 'Kurs', "O'qituvchi", 'Xona', 'Vaqt', 'Boshlanishi', 'Tugashi', "O'quvchilar", 'Narx', 'Holat'],
            'rows' => $groups->map(fn ($g) => [
                $g->name, $g->course->name, $g->teacher->name, $g->room->name, $g->lessonTime->label,
                $g->starts_on->format('d.m.Y'), $g->ends_on->format('d.m.Y'), (int) $g->students, (int) $g->price, $g->status_label,
            ])->all(),
            'summary' => ['Guruhlar soni' => $groups->count(), "O'quvchilar (jami)" => (int) $groups->sum('students'),
                "O'rtacha guruh to'lishi" => $groups->count() ? round($groups->avg('students'), 1) : 0],
        ];
    }

    private function leads(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $leads = $this->between(Lead::with(['source:id,name', 'student:id,name']), $from, $to)->orderBy('id')->get();
        $total = $leads->count();
        $converted = $leads->where('status', Lead::CONVERTED)->count();

        return [
            'title' => 'Varonka hisoboti',
            'columns' => ['Sana', 'Ism', 'Telefon', 'Manba', 'Holat', "O'quvchi"],
            'rows' => $leads->map(fn ($l) => [$l->created_at->format('d.m.Y H:i'), $l->name, Format::prettyPhone($l->phone), $l->source?->name ?? '', $l->status_label, $l->student?->name ?? ''])->all(),
            'summary' => ['Jami murojaatlar' => $total, 'Qabul qilingan' => $converted, 'Bekor qilingan' => $leads->where('status', Lead::CANCELLED)->count(),
                'Qabul foizi' => $total ? round($converted * 100 / $total, 1).'%' : '—'],
        ];
    }

    private function attendanceReport(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $months = [];
        for ($m = $from->startOfMonth(); $m->lte($to); $m = $m->addMonth()) {
            $months[] = $m->format('Y-m');
        }

        $rows = [];
        $present = $absent = 0;
        foreach (array_slice($months, 0, 12) as $month) {
            foreach ($this->attendance->monthly($month)['rows'] as $r) {
                $rows[] = [$month, $r['group']->name, $r['group']->teacher->name, $r['scheduled'], $r['held'], $r['present'], $r['absent'], $r['rate'] === null ? '' : $r['rate'].'%'];
                $present += $r['present'];
                $absent += $r['absent'];
            }
        }

        return [
            'title' => 'Davomad hisoboti',
            'columns' => ['Oy', 'Guruh', "O'qituvchi", 'Rejadagi kunlar', 'Davomad olingan', 'Keldi', 'Kelmadi', 'Foiz'],
            'rows' => $rows,
            'summary' => ['Keldi' => $present, 'Kelmadi' => $absent, 'Umumiy davomad' => ($present + $absent) ? round($present * 100 / ($present + $absent), 1).'%' : '—'],
        ];
    }

    private function payrollReport(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $teachers = User::visibleToContext()->where('role', Role::Teacher)->orderBy('name')->get();
        $paid = $this->between(\App\Models\Payout::query(), $from, $to)->selectRaw('recipient_id, sum(amount) as s')->groupBy('recipient_id')->pluck('s', 'recipient_id');

        $rows = [];
        $totalRemaining = 0;
        foreach ($teachers as $t) {
            $acc = collect($this->payroll->teacherAccruals($t));
            $remaining = (int) $acc->sum(fn ($a) => max(0, $a['remaining']));
            $totalRemaining += $remaining;
            $rows[] = [$t->name, $acc->count(), (int) $acc->sum('accrued_by_attendance'), (int) ($paid[$t->id] ?? 0), $remaining];
        }

        return [
            'title' => "O'qituvchi ish haqi hisoboti",
            'columns' => ["O'qituvchi", 'Guruhlar (31 kun)', "Hisoblangan (davomad bo'yicha)", "Davrda to'langan", "To'lanmagan qoldiq"],
            'rows' => $rows,
            'summary' => ["Davrda to'langan" => (int) collect($paid)->sum(), "To'lanmagan qoldiq" => $totalRemaining],
        ];
    }

    private function cashflow(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $tx = $this->between(WalletTransaction::with('creator:id,name'), $from, $to)->orderBy('id')->get();

        // v8 B3: "cash_request" turidagi yozuvlar uchun (agar xarajat bo'lsa) turkum nomini qo'shib beramiz.
        $requestIds = $tx->where('subject_type', 'CashRequest')->pluck('subject_id')->unique();
        $categoryByRequest = CashRequest::with('category:id,name')->whereIn('id', $requestIds)->get()
            ->mapWithKeys(fn ($r) => [$r->id => $r->category?->name]);

        $categoryFor = function ($t) use ($categoryByRequest) {
            return $t->subject_type === 'CashRequest' ? ($categoryByRequest[$t->subject_id] ?? null) : null;
        };

        $expenseSummary = [];
        foreach ($tx->where('type', 'cash_request') as $t) {
            $name = $categoryFor($t);
            if ($name === null) {
                continue;
            }
            $expenseSummary[$name] = ($expenseSummary[$name] ?? 0) + (int) -$t->amount;
        }

        return [
            'title' => 'Pul harakati hisoboti',
            'columns' => ['Sana', 'Hamyon', 'Turi', 'Xarajat turi', 'Summa', 'Qoldiq', 'Izoh', 'Kim'],
            'rows' => $tx->map(fn ($t) => [
                $t->created_at->format('d.m.Y H:i'), $t->walletLabel(), $t->typeLabel(), $categoryFor($t) ?? '',
                (int) $t->amount, (int) $t->balance_after, (string) $t->description, $t->creator?->name ?? '',
            ])->all(),
            'summary' => array_merge(
                ['Kirim' => (int) $tx->where('amount', '>', 0)->sum('amount'), 'Chiqim' => (int) -$tx->where('amount', '<', 0)->sum('amount')],
                collect($expenseSummary)->mapWithKeys(fn ($sum, $name) => ["Xarajat — {$name}" => $sum])->all()
            ),
        ];
    }
}
