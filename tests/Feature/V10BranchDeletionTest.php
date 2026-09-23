<?php

namespace Tests\Feature;

use App\Enums\PayMethod;
use App\Enums\Role;
use App\Enums\Wallet;
use App\Models\AiChat;
use App\Models\AiMessage;
use App\Models\AuditLog;
use App\Models\Book;
use App\Models\Branch;
use App\Models\CashRequest;
use App\Models\CourseVideo;
use App\Models\DiscountCampaign;
use App\Models\ExpenseCategory;
use App\Models\Holiday;
use App\Models\ImportBatch;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\SmsMessage;
use App\Models\SmsTemplate;
use App\Models\StudentNote;
use App\Models\SubmissionToken;
use App\Models\TaskDismissal;
use App\Models\TestAttempt;
use App\Models\User;
use App\Models\WalletAccount;
use App\Services\BranchDeletionService;
use App\Services\CashboxService;
use App\Services\DashboardService;
use App\Services\EnrollmentService;
use App\Services\FinanceService;
use App\Services\GroupService;
use App\Services\PaymentService;
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * v10 (7-band): filialni BUTUNLAY o'chirish - barcha tegishli ma'lumot ham o'chishi, boshqa
 * filialga tegishli hech narsa buzilmasligi, audit_logs saqlanib qolishi (faqat branch_id
 * bo'shatiladi), faqat sAdmin va nom tasdiqlash bilan ishlashi tekshiriladi.
 */
class V10BranchDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-21 10:00:00');
    }

    /** To'liq stsenariy: guruh, to'lov, lid, SMS, AI, kassa va h.k. bo'lgan filial yaratadi. */
    private function seedRichBranch(string $name): array
    {
        $branch = $this->branch($name);
        $admin = $this->user(Role::Admin, $branch, [
            'groups.create', 'groups.members', 'students.view', 'students.notes', 'payments.view', 'payments.create',
            'cashbox.request', 'cashbox.approve', 'finance.view', 'finance.manage', 'leads.view', 'leads.manage',
        ]);
        $this->actingAs($admin);

        $cat = $this->catalog($branch);
        $group = app(GroupService::class)->create($this->groupPayload($cat), $admin);
        $student = $this->student($branch, ['name' => 'Talaba', 'phone' => '+998 90 555 0101']);
        app(EnrollmentService::class)->enroll($group, $student, null, $admin);
        app(PaymentService::class)->receive($student, ['cash' => 300000], $group, null, $admin);

        $lead = Lead::create(['branch_id' => $branch->id, 'name' => 'Lid', 'phone' => '+998 90 111 2233', 'status' => Lead::NEW]);
        LeadNote::create(['lead_id' => $lead->id, 'body' => 'Izoh']);

        $cashbox = app(CashboxService::class);
        $cashbox->approve($cashbox->request(CashRequest::EXPENSE, PayMethod::Cash, 20000, 'Xarajat', $admin), $admin);

        $this->fund($branch, Wallet::TreasuryCash, 100000);
        app(FinanceService::class)->expense(PayMethod::Cash, 10000, 'Ijara', $admin);

        ExpenseCategory::create(['branch_id' => $branch->id, 'name' => 'Ofis']);
        DiscountCampaign::create(['branch_id' => $branch->id, 'name' => 'Aksiya', 'amount' => 10000, 'bonus' => 5000, 'starts_on' => today(), 'ends_on' => today()->addDays(10)]);
        Holiday::create(['branch_id' => $branch->id, 'date' => today()->addDays(5)->toDateString(), 'comment' => 'Bayram']);
        SmsTemplate::create(['branch_id' => $branch->id, 'key' => 'welcome', 'body' => 'Salom', 'is_enabled' => true]);
        SmsMessage::create(['branch_id' => $branch->id, 'phone' => $student->phone, 'message' => 'Salom', 'status' => 'sent']);
        StudentNote::create(['branch_id' => $branch->id, 'student_id' => $student->id, 'user_id' => $admin->id, 'body' => 'Eslatma']);
        Book::create(['branch_id' => $branch->id, 'name' => 'Kitob', 'url' => 'https://example.test/book.pdf']);
        CourseVideo::create(['branch_id' => $branch->id, 'course_id' => $cat['course']->id, 'number' => 1, 'title' => 'Dars 1', 'url' => 'https://example.test/v1']);
        TestAttempt::create(['branch_id' => $branch->id, 'student_id' => $student->id, 'course_id' => $cat['course']->id, 'questions' => [], 'answer_key' => [], 'total' => 5, 'started_at' => now()]);

        $chat = AiChat::create(['user_id' => $admin->id, 'branch_id' => $branch->id, 'title' => 'Suhbat']);
        AiMessage::create(['ai_chat_id' => $chat->id, 'branch_id' => $branch->id, 'user_id' => $admin->id, 'role' => 'user', 'content' => 'Salom']);

        app(DashboardService::class)->dismiss($admin, 'debtor:1');

        SubmissionToken::create(['token' => bin2hex(random_bytes(16)), 'user_id' => $student->id]);
        ImportBatch::create(['user_id' => $admin->id, 'status' => 'done', 'filename' => 'x.xlsx', 'summary' => [], 'rows' => '[]']);

        return compact('branch', 'admin', 'student', 'group', 'lead');
    }

    public function test_deleting_a_branch_removes_all_its_related_data(): void
    {
        $sadmin = $this->user(Role::SAdmin);
        $this->actingAs($sadmin);
        $data = $this->seedRichBranch('O\'chiriladigan filial');
        $branch = $data['branch'];
        $branchId = $branch->id;

        // O'chirishni sAdmin sifatida bajaramiz (seedRichBranch ichida faol foydalanuvchi
        // vaqtincha filial adminiga almashtirilgan edi - u ham shu filial bilan o'chadi).
        $this->actingAs($sadmin);
        app(BranchDeletionService::class)->delete($branch);

        $this->assertSame(0, User::where('branch_id', $branchId)->count());
        $this->assertSame(0, DB::table('groups')->where('branch_id', $branchId)->count());
        $this->assertSame(0, DB::table('group_students')->where('branch_id', $branchId)->count());
        $this->assertSame(0, DB::table('group_days')->count()); // faqat shu filialning guruhi bor edi
        $this->assertSame(0, DB::table('attendance_sessions')->where('branch_id', $branchId)->count());
        $this->assertSame(0, DB::table('attendances')->where('branch_id', $branchId)->count());
        $this->assertSame(0, DB::table('payments')->where('branch_id', $branchId)->count());
        $this->assertSame(0, DB::table('cash_requests')->where('branch_id', $branchId)->count());
        $this->assertSame(0, DB::table('wallet_transactions')->where('branch_id', $branchId)->count());
        $this->assertSame(0, WalletAccount::where('branch_id', $branchId)->count());
        $this->assertSame(0, DB::table('leads')->where('branch_id', $branchId)->count());
        $this->assertSame(0, DB::table('lead_notes')->where('lead_id', $data['lead']->id)->count());
        $this->assertSame(0, ExpenseCategory::where('branch_id', $branchId)->count());
        $this->assertSame(0, DiscountCampaign::where('branch_id', $branchId)->count());
        $this->assertSame(0, Holiday::where('branch_id', $branchId)->count());
        $this->assertSame(0, SmsTemplate::where('branch_id', $branchId)->count());
        $this->assertSame(0, SmsMessage::where('branch_id', $branchId)->count());
        $this->assertSame(0, StudentNote::where('branch_id', $branchId)->count());
        $this->assertSame(0, Book::where('branch_id', $branchId)->count());
        $this->assertSame(0, CourseVideo::where('branch_id', $branchId)->count());
        $this->assertSame(0, TestAttempt::where('branch_id', $branchId)->count());
        $this->assertSame(0, AiChat::where('branch_id', $branchId)->count());
        $this->assertSame(0, AiMessage::where('branch_id', $branchId)->count());
        $this->assertSame(0, TaskDismissal::where('branch_id', $branchId)->count());
        $this->assertSame(0, SubmissionToken::where('user_id', $data['student']->id)->count());
        $this->assertSame(0, ImportBatch::where('user_id', $data['admin']->id)->count());
        $this->assertSame(0, DB::table('courses')->where('branch_id', $branchId)->count());
        $this->assertSame(0, DB::table('rooms')->where('branch_id', $branchId)->count());
        $this->assertSame(0, DB::table('lesson_times')->where('branch_id', $branchId)->count());
        $this->assertSame(0, DB::table('price_plans')->where('branch_id', $branchId)->count());
        $this->assertSame(0, DB::table('lead_sources')->where('branch_id', $branchId)->count());

        $this->assertNull(Branch::find($branchId));
    }

    public function test_deleting_a_branch_does_not_touch_another_branchs_data(): void
    {
        $sadmin = $this->user(Role::SAdmin);
        $this->actingAs($sadmin);
        $victim = $this->seedRichBranch("O'chiriladigan");
        $survivor = $this->seedRichBranch('Omon qoladigan');

        $this->actingAs($sadmin);
        app(BranchDeletionService::class)->delete($victim['branch']);

        $this->assertNotNull(Branch::find($survivor['branch']->id));
        $this->assertSame(1, User::where('branch_id', $survivor['branch']->id)->where('role', Role::Student)->count());
        $this->assertSame(1, DB::table('groups')->where('branch_id', $survivor['branch']->id)->count());
        $this->assertSame(1, DB::table('leads')->where('branch_id', $survivor['branch']->id)->count());
        $this->assertSame(1, DB::table('payments')->where('branch_id', $survivor['branch']->id)->count());
    }

    public function test_audit_logs_are_preserved_with_branch_id_nulled_instead_of_deleted(): void
    {
        $sadmin = $this->user(Role::SAdmin);
        $this->actingAs($sadmin);
        $data = $this->seedRichBranch('O\'chiriladigan');
        $branchId = $data['branch']->id;

        $beforeCount = AuditLog::withoutGlobalScopes()->count();
        $this->assertGreaterThan(0, AuditLog::withoutGlobalScopes()->where('branch_id', $branchId)->count());

        $this->actingAs($sadmin);
        app(BranchDeletionService::class)->delete($data['branch']);

        // Jurnal yozuvlari SONI kamaymaydi (o'chirilmadi), faqat branch_id bo'shatildi,
        // va o'chirish harakatining o'zi ham jurnalga yozildi (shuning uchun soni ORTADI, kamaymaydi).
        $this->assertGreaterThan($beforeCount, AuditLog::withoutGlobalScopes()->count());
        $this->assertSame(0, AuditLog::withoutGlobalScopes()->where('branch_id', $branchId)->count());
        $this->assertTrue(AuditLog::withoutGlobalScopes()->where('action', 'branch.deleted')->whereNull('branch_id')->exists());
    }

    public function test_only_sadmin_can_delete_a_branch(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['staff.manage']);

        $this->actingAs($admin)->delete("/branches/{$branch->id}", ['confirm_name' => $branch->name])->assertForbidden();
        $this->assertNotNull(Branch::find($branch->id));
    }

    public function test_wrong_confirmation_name_is_rejected_and_nothing_is_deleted(): void
    {
        $sadmin = $this->user(Role::SAdmin);
        $data = $this->seedRichBranch('Ehtiyot filiali');

        $this->actingAs($sadmin)->delete("/branches/{$data['branch']->id}", ['confirm_name' => "Noto'g'ri nom"])
            ->assertSessionHasErrors('confirm_name');

        $this->assertNotNull(Branch::find($data['branch']->id));
        $this->assertSame(1, User::where('branch_id', $data['branch']->id)->where('role', Role::Student)->count());
    }

    public function test_correct_confirmation_name_deletes_the_branch_via_http(): void
    {
        $sadmin = $this->user(Role::SAdmin);
        $data = $this->seedRichBranch('To\'g\'ri tasdiq filiali');

        $this->actingAs($sadmin)->delete("/branches/{$data['branch']->id}", ['confirm_name' => $data['branch']->name])
            ->assertRedirect(route('branches.index'));

        $this->assertNull(Branch::find($data['branch']->id));
    }

    public function test_deleting_the_currently_selected_branch_clears_the_sadmin_selection(): void
    {
        $sadmin = $this->user(Role::SAdmin);
        $this->actingAs($sadmin);
        $data = $this->seedRichBranch('Tanlangan filial');

        // seedRichBranch faol foydalanuvchini vaqtincha filial adminiga almashtirgan edi -
        // sAdmin sifatida davom etamiz (filial tanlash sof sAdmin mexanizmi).
        $this->actingAs($sadmin);
        BranchContext::select($data['branch']->id);
        $this->assertSame($data['branch']->id, BranchContext::id());

        app(BranchDeletionService::class)->delete($data['branch']);

        $this->assertNull(BranchContext::id());
    }
}
