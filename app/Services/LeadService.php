<?php

namespace App\Services;

use App\Jobs\AnalyzeLeadJob;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Group;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\User;
use App\Support\Format;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Varonka: murojaatlar (lidlar) bilan ishlash. */
class LeadService
{
    public function __construct(private StudentService $students, private EnrollmentService $enrollment) {}

    /**
     * Yangi murojaat (sayt shakli yoki xodim tomonidan). Bir xil telefon 10 daqiqa ichida takror yuborilsa,
     * yangi yozuv yaratilmaydi.
     */
    public function register(Branch $branch, array $data, ?User $actor = null): Lead
    {
        $phone = Format::canonicalPhone($data['phone']) ?? $data['phone'];

        $recent = Lead::withoutGlobalScopes()->where('branch_id', $branch->id)->where('phone', $phone)
            ->where('status', Lead::NEW)->where('created_at', '>=', now()->subMinutes(10))->first();

        if ($recent) {
            return $recent;
        }

        $lead = DB::transaction(function () use ($branch, $data, $phone, $actor) {
            $lead = Lead::create([
                'branch_id' => $branch->id,
                'name' => mb_strtoupper(trim($data['name'])),
                'phone' => $phone,
                'phone2' => ! empty($data['phone2']) ? (Format::canonicalPhone($data['phone2']) ?? $data['phone2']) : null,
                'address' => $data['address'] ?? null,
                'lead_source_id' => $data['lead_source_id'] ?? null,
                'status' => Lead::NEW,
                'is_repeat' => $this->findStudentByPhone($branch->id, $phone) !== null,
                'created_by' => $actor?->id,
            ]);

            $this->note($lead, $actor ? "Murojat qo'lda kiritildi" : 'Murojat sayt orqali qabul qilindi', $actor);

            return $lead;
        });

        $this->queueAnalysis($lead);

        return $lead;
    }

    /** Murojaat tushganda va izoh yozilganda AI tahlili navbatga qo'yiladi (OpenAI kaliti sozlangan bo'lsa). */
    private function queueAnalysis(Lead $lead): void
    {
        if (app(OpenAiService::class)->configured()) {
            AnalyzeLeadJob::dispatch($lead->id);
        }
    }

    public function addNote(Lead $lead, string $body, User $actor): Lead
    {
        $this->note($lead, $body, $actor);

        if ($lead->status === Lead::NEW) {
            $lead->update(['status' => Lead::IN_PROGRESS]);
        }

        $this->queueAnalysis($lead);

        return $lead;
    }

    public function cancel(Lead $lead, ?string $reason, User $actor): Lead
    {
        $this->assertOpen($lead);

        $lead->update(['status' => Lead::CANCELLED]);
        $this->note($lead, 'Murojat bekor qilindi'.($reason ? ": {$reason}" : ''), $actor);

        return $lead;
    }

    public function reopen(Lead $lead, User $actor): Lead
    {
        if ($lead->status !== Lead::CANCELLED) {
            throw ValidationException::withMessages(['lead' => "Faqat bekor qilingan murojaatni qayta ochish mumkin."]);
        }

        $lead->update(['status' => Lead::IN_PROGRESS]);
        $this->note($lead, 'Murojat qayta ochildi', $actor);

        return $lead;
    }

    /**
     * Murojaatni o'quvchiga aylantiradi. Telefon raqami bilan o'quvchi bor bo'lsa (bir filialda bir raqam bitta o'quvchiga tegishli),
     * yangisi yaratilmaydi, mavjud o'quvchi bog'lanadi.
     *
     * v9: $groupId berilsa, o'quvchi bir vaqtning o'zida (bitta tranzaksiyada) shu guruhga ham qo'shiladi.
     * Guruhga qo'shish muvaffaqiyatsiz bo'lsa (masalan guruh juda oldin tugagan), butun amal bekor qilinadi.
     *
     * @return array{0: Lead, 1: User, 2: string|null} [murojaat, o'quvchi, yangi parol (yangi o'quvchi bo'lsa)]
     */
    public function convert(Lead $lead, User $actor, ?string $about = null, ?int $groupId = null): array
    {
        $this->assertOpen($lead);

        return DB::transaction(function () use ($lead, $actor, $about, $groupId) {
            $existing = $this->findStudentByPhone($lead->branch_id, $lead->phone);
            $password = null;

            if ($existing) {
                $student = $existing;
                $text = "Mavjud o'quvchi bilan bog'landi: {$student->name}";
            } else {
                [$student, $password] = $this->students->create([
                    'name' => $lead->name, 'phone' => $lead->phone, 'phone2' => $lead->phone2, 'address' => $lead->address,
                    'about' => $about, 'lead_source_id' => $lead->lead_source_id,
                ], $actor);
                $text = "Murojat ro'yxatga olindi, o'quvchi yaratildi: {$student->name}";
            }

            $lead->update(['status' => Lead::CONVERTED, 'student_id' => $student->id]);
            $this->note($lead, $text, $actor);
            AuditLog::record('lead.converted', $lead, $text);

            if ($groupId) {
                $group = Group::where('branch_id', $lead->branch_id)->findOrFail($groupId);
                $this->enrollment->enroll($group, $student, 'Lidni o\'quvchi qilishda qo\'shildi', $actor);
                $this->note($lead, "Guruhga qo'shildi: {$group->name}", $actor);
            }

            return [$lead, $student, $password];
        });
    }

    /** Shu filialda shu telefon raqamli o'quvchi (bir filialda bir raqam bitta o'quvchiga tegishli). */
    public function findStudentByPhone(int $branchId, string $phone): ?User
    {
        $canonical = Format::canonicalPhone($phone);

        return $canonical ? User::where('role', 'student')->where('branch_id', $branchId)->where('phone', $canonical)->first() : null;
    }

    private function note(Lead $lead, string $body, ?User $actor): void
    {
        LeadNote::create(['lead_id' => $lead->id, 'user_id' => $actor?->id, 'body' => $body]);
    }

    private function assertOpen(Lead $lead): void
    {
        if (! $lead->isOpen()) {
            throw ValidationException::withMessages(['lead' => "Bu murojaat allaqachon yopilgan."]);
        }
    }
}
