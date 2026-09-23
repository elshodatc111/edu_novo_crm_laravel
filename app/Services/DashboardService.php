<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\AttendanceSession;
use App\Models\Group;
use App\Models\GroupStudent;
use App\Models\Lead;
use App\Models\TaskDismissal;
use App\Models\User;
use App\Support\BranchContext;
use App\Support\Format;
use Carbon\CarbonImmutable;

/**
 * Kunlik vazifalar paneli (B5). Har bir vazifa turi tegishli ruxsatga qarab ko'rinadi
 * (masalan o'qituvchida `payments.view` yo'q — u qarzdorlarni ko'rmaydi), shuning uchun
 * rol emas, balki ruxsat asosida filtrlanadi. "Bajardim" faqat BUGUNGA yashiradi:
 * `TaskDismissal` yozuvi bugungi sana bilan saqlanadi, ertaga shart hali to'g'ri bo'lsa
 * (masalan hali ham qarzdor), vazifa qayta ro'yxatga chiqadi.
 */
class DashboardService
{
    public function __construct(private DiscountWindow $window, private EarlyDiscountService $earlyDiscount) {}

    /** @return array<int, array{key:string, icon:string, title:string, items:array<int,array{key:string,label:string,meta:string,url:string}>}> */
    public function todo(User $user): array
    {
        if (! $user->role->canUsePanel()) {
            return [];
        }

        // sAdmin filial tanlamagan holatda (Gate::before orqali barcha ruxsatlarga ega va
        // BranchScope barcha filiallarni ko'rsatadi) bir nechta filial ma'lumoti bitta
        // ro'yxatda aralashib ketmasligi uchun — xuddi bosh sahifadagi kalendar kabi —
        // vazifalar ko'rsatilmaydi.
        if ($user->isSuperAdmin() && ! BranchContext::id()) {
            return [];
        }

        $dismissed = TaskDismissal::where('user_id', $user->id)
            ->where('dismissed_on', today()->toDateString())
            ->pluck('task_key')->all();

        $groups = [
            ['key' => 'debtors', 'icon' => 'wallet', 'title' => 'Bugungi qarzdorlar', 'items' => $user->can('payments.view') ? $this->debtors() : []],
            ['key' => 'attendance', 'icon' => 'calendar', 'title' => 'Davomad olinmagan guruhlar', 'items' => $user->can('attendance.take') ? $this->attendanceMissing($user) : []],
            ['key' => 'leads', 'icon' => 'message', 'title' => "24 soatdan ortiq javobsiz lidlar", 'items' => ($user->can('leads.view') || $user->can('leads.manage')) ? $this->staleLeads() : []],
            ['key' => 'birthdays', 'icon' => 'sparkles', 'title' => "Bugun tug'ilgan kun", 'items' => $user->can('students.view') ? $this->birthdaysToday() : []],
            ['key' => 'discount', 'icon' => 'banknote', 'title' => "Chegirma muddati yaqinlashmoqda", 'items' => $user->can('payments.view') ? $this->discountReminders() : []],
        ];

        foreach ($groups as &$g) {
            $g['items'] = array_values(array_filter($g['items'], fn ($i) => ! in_array($i['key'], $dismissed, true)));
        }
        unset($g);

        return array_values(array_filter($groups, fn ($g) => $g['items'] !== []));
    }

    /** "Bajardim" — faqat bugunga. Shart ertaga ham to'g'ri bo'lsa, vazifa qayta chiqadi. */
    public function dismiss(User $user, string $taskKey): void
    {
        TaskDismissal::updateOrCreate(
            ['user_id' => $user->id, 'task_key' => $taskKey, 'dismissed_on' => today()->toDateString()],
            ['branch_id' => $user->branch_id],
        );
    }

    /**
     * v10 (6-band): bosh sahifada faol guruhlar va boshlanishi kutilayotgan (hali boshlanmagan)
     * guruhlar soni, har birida jami (faol) o'quvchilar soni bilan. sAdmin filial tanlamagan
     * holatda ko'rsatilmaydi - aks holda bir nechta filial ma'lumoti aralashib ketadi.
     *
     * @return array{active: array{groups:int, students:int}, upcoming: array{groups:int, students:int}}|null
     */
    public function groupStats(): ?array
    {
        if (! BranchContext::id()) {
            return null;
        }

        $active = Group::status(Group::ACTIVE)->withCount('activeMembers')->get();
        $upcoming = Group::status(Group::NEW)->withCount('activeMembers')->get();

        return [
            'active' => ['groups' => $active->count(), 'students' => (int) $active->sum('active_members_count')],
            'upcoming' => ['groups' => $upcoming->count(), 'students' => (int) $upcoming->sum('active_members_count')],
        ];
    }

    /** Rahbariyat uchun: oxirgi 7 kunda har bir xodim "bajardim" bosgan vazifalar soni. */
    public function weeklyActivity(): array
    {
        $userIds = User::visibleToContext()->pluck('id');

        return TaskDismissal::query()
            ->whereIn('user_id', $userIds)
            ->where('dismissed_on', '>=', today()->subDays(6)->toDateString())
            ->selectRaw('user_id, count(*) as total')
            ->groupBy('user_id')
            ->with('user:id,name')
            ->orderByDesc('total')
            ->limit(10)->get()
            ->map(fn ($r) => ['name' => $r->user?->name ?? '—', 'count' => (int) $r->total])
            ->all();
    }

    private function debtors(): array
    {
        return User::visibleToContext()->where('role', Role::Student)->whereNull('archived_at')
            ->where('balance', '<', 0)->orderBy('balance')->limit(30)->get(['id', 'name', 'balance'])
            ->map(fn ($s) => [
                'key' => "debtor:{$s->id}", 'label' => $s->name,
                'meta' => Format::money(-$s->balance).' qarz',
                'url' => route('students.show', $s),
            ])->all();
    }

    private function attendanceMissing(User $user): array
    {
        $groups = Group::with('course:id,name')
            ->havingLessonOn(today())
            ->when($user->role === Role::Teacher, fn ($q) => $q->where('teacher_id', $user->id))
            ->get(['id', 'name', 'course_id']);

        if ($groups->isEmpty()) {
            return [];
        }

        $taken = AttendanceSession::whereIn('group_id', $groups->pluck('id'))->whereDate('date', today())->pluck('group_id')->all();

        return $groups->reject(fn ($g) => in_array($g->id, $taken, true))
            ->map(fn ($g) => [
                'key' => "attendance:{$g->id}", 'label' => $g->name,
                'meta' => $g->course?->name ?? '',
                'url' => route('attendance.today'),
            ])->values()->all();
    }

    private function staleLeads(): array
    {
        $cutoff = now()->subHours(24);

        return Lead::query()
            ->whereIn('status', [Lead::NEW, Lead::IN_PROGRESS])
            ->select('leads.*')
            ->selectRaw('(select max(created_at) from lead_notes where lead_notes.lead_id = leads.id) as last_note_at')
            ->get()
            ->filter(function ($lead) use ($cutoff) {
                $last = CarbonImmutable::parse($lead->last_note_at ?? $lead->created_at);

                return $last->lt($cutoff);
            })
            ->sortBy('created_at')->take(30)
            ->map(fn ($l) => [
                'key' => "lead:{$l->id}", 'label' => $l->name, 'meta' => $l->phone,
                'url' => route('leads.show', $l),
            ])->values()->all();
    }

    private function birthdaysToday(): array
    {
        return User::visibleToContext()->where('role', Role::Student)->whereNull('archived_at')
            ->whereNotNull('birthday')->whereMonth('birthday', today()->month)->whereDay('birthday', today()->day)
            ->get(['id', 'name'])
            ->map(fn ($s) => [
                'key' => "birthday:{$s->id}", 'label' => $s->name, 'meta' => "Tug'ilgan kuni bilan tabriklang",
                'url' => route('students.show', $s),
            ])->all();
    }

    private function discountReminders(): array
    {
        $branchId = BranchContext::id();
        if (! $branchId) {
            return [];
        }

        [$before] = $this->window->days($branchId);

        $groups = Group::where('early_discount', '>', 0)
            ->whereDate('starts_on', '>=', today())
            ->whereDate('starts_on', '<=', today()->addDays($before))
            ->get();

        $items = [];
        foreach ($groups as $group) {
            $members = GroupStudent::where('group_id', $group->id)->where('is_active', true)->with('student:id,name')->get();

            foreach ($members as $m) {
                if (! $m->student || $this->earlyDiscount->hasDiscount($m->student, $group)) {
                    continue;
                }

                $items[] = [
                    'key' => "discount:{$group->id}:{$m->student_id}",
                    'label' => $m->student->name,
                    'meta' => $group->name.' — '.today()->diffInDays($group->starts_on, false).' kun qoldi',
                    'url' => route('students.show', $m->student_id),
                ];
            }
        }

        return array_slice($items, 0, 30);
    }
}
