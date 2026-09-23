<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Lead;
use App\Services\EnrollmentService;
use App\Services\GroupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * v10 (1-band): `staff.view_all_branches` ruxsati bilan operator (yoki admin/menejer) boshqa
 * filialning o'quvchi/guruh/lidlarini FAQAT KO'RISH imkoniyati - sAdmin kabi barcha filiallar,
 * lekin yozish huquqisiz. Joriy ishchi filial (BranchContext) o'zgarmasligi alohida tekshiriladi.
 */
class V10CrossBranchViewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-21 10:00:00');
    }

    public function test_operator_with_permission_can_view_another_branchs_students_groups_and_leads(): void
    {
        $home = $this->branch('Uy filiali');
        $other = $this->branch('Boshqa filial');
        $operator = $this->user(Role::Operator, $home, ['staff.view_all_branches']);
        $otherAdmin = $this->user(Role::Admin, $other, ['groups.create', 'groups.members']);

        // "Boshqa filial" ma'lumotlari o'sha filialning o'zi nomidan yaratiladi (guruh yaratish
        // kabi yozish amallari doim joriy BranchContext bilan cheklanadi) - keyin operator
        // sifatida shu ma'lumotlarni FAQAT KO'RISH tekshiriladi.
        $this->actingAs($otherAdmin);
        $cat = $this->catalog($other);
        $group = app(GroupService::class)->create($this->groupPayload($cat, ['name' => 'Boshqa guruh']), $otherAdmin);
        $student = $this->student($other, ['name' => 'Boshqa Oquvchi']);
        app(EnrollmentService::class)->enroll($group, $student, null, $otherAdmin);
        Lead::create(['branch_id' => $other->id, 'name' => 'Boshqa Lid', 'phone' => '+998 90 777 7777', 'status' => Lead::NEW]);

        $this->actingAs($operator)->get("/staff-directory/{$other->id}")->assertOk()
            ->assertSee('Boshqa Oquvchi')
            ->assertSee('BOSHQA GURUH')
            ->assertSee($cat['course']->name)
            ->assertSee('Boshqa Lid');
    }

    public function test_user_without_permission_cannot_browse_other_branch(): void
    {
        $home = $this->branch();
        $other = $this->branch();
        $manager = $this->user(Role::Manager, $home, ['leads.view']);

        $this->actingAs($manager)->get("/staff-directory/{$other->id}")->assertForbidden();
    }

    public function test_browsing_other_branch_does_not_change_the_operators_own_working_branch(): void
    {
        $home = $this->branch('Uy filiali');
        $other = $this->branch('Boshqa filial');
        $operator = $this->user(Role::Operator, $home, ['staff.view_all_branches', 'students.view']);
        $this->student($home, ['name' => "O'z o'quvchim"]);
        $this->student($other, ['name' => 'Yot oquvchi']);

        $this->actingAs($operator)->get("/staff-directory/{$other->id}")->assertOk()->assertSee('Yot oquvchi');

        // Operatorning haqiqiy ishchi ekrani (o'quvchilar ro'yxati) hamon faqat O'Z filialini ko'rsatadi -
        // ko'zdan kechirish yozish kontekstiga (BranchContext) hech qanday ta'sir qilmadi.
        $this->actingAs($operator)->get('/students')->assertOk()->assertSee("O'z o'quvchim")->assertDontSee('Yot oquvchi');
    }

    public function test_branch_view_page_has_no_edit_or_write_controls(): void
    {
        $home = $this->branch('Uy filiali');
        $other = $this->branch('Boshqa filial');
        $operator = $this->user(Role::Operator, $home, ['staff.view_all_branches']);
        $this->student($other, ['name' => 'Yot oquvchi']);

        $html = $this->actingAs($operator)->get("/staff-directory/{$other->id}")->assertOk()->getContent();

        // Faqat sahifaning asosiy (<main>) qismini tekshiramiz - joylashuvdagi chiqish/filial
        // almashtirish kabi umumiy formalar bu yerga kirmaydi, faqat shu sahifaning o'zi tekshiriladi.
        preg_match('/<main.*?<\/main>/s', $html, $m);
        $main = $m[0] ?? '';
        $this->assertNotSame('', $main, 'Sahifaning asosiy qismi topilmadi.');
        $this->assertStringNotContainsString('method="POST"', $main);
        $this->assertStringNotContainsString('<form', $main);
        $this->assertStringNotContainsString('Tahrirlash', $main);
    }
}
