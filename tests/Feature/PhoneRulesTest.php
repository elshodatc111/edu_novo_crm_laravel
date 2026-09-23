<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use App\Support\Format;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PhoneRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_canonical_phone_formatting(): void
    {
        $this->assertSame('+998 90 123 4567', Format::canonicalPhone('901234567'));
        $this->assertSame('+998 90 123 4567', Format::canonicalPhone('+998 (90) 123-45-67'));
        $this->assertSame('+998 90 123 4567', Format::canonicalPhone('998901234567'));
        $this->assertNull(Format::canonicalPhone('12345'));
        $this->assertNull(Format::canonicalPhone(null));
        $this->assertSame('+998 90 123 4567', Format::prettyPhone('901234567'));
    }

    #[DataProvider('badPhones')]
    public function test_only_exact_format_is_accepted(string $phone): void
    {
        $branch = $this->branch();
        $manager = $this->user(Role::Manager, $branch, ['students.create', 'students.view']);

        $this->actingAs($manager)->post('/students', ['name' => 'Ali', 'phone' => $phone])->assertSessionHasErrors('phone');
        $this->assertSame(0, User::where('role', 'student')->count());
    }

    public static function badPhones(): array
    {
        return [['901234567'], ['998901234567'], ['+998901234567'], ['+998 90 123 45 67'], ['+998 90 12 34567'], ['+7 90 123 4567'], ['+998 90 123 456a'], ['']];
    }

    public function test_format_is_enforced_for_staff_profile_leads_and_public_form(): void
    {
        $branch = $this->branch();
        $sadmin = $this->user(Role::SAdmin);
        $payload = ['name' => 'Yangi', 'username' => 'yangi', 'password' => 'parol12345', 'password_confirmation' => 'parol12345', 'status' => 'active', 'role' => 'manager', 'branch_id' => $branch->id];

        $this->actingAs($sadmin)->post('/staff', $payload + ['phone' => '901234567'])->assertSessionHasErrors('phone');
        $this->actingAs($sadmin)->post('/staff', $payload)->assertSessionHasErrors('phone');                   // majburiy
        $this->actingAs($sadmin)->post('/staff', $payload + ['phone' => '+998 90 123 4567'])->assertSessionHasNoErrors();

        $manager = User::where('username', 'yangi')->firstOrFail();
        $this->actingAs($manager->fresh())->put('/profile', ['name' => 'X', 'phone' => '901112233'])->assertSessionHasErrors('phone');
        $this->actingAs($manager->fresh())->put('/profile', ['name' => 'X', 'phone' => '+998 91 111 2233'])->assertSessionHasNoErrors();
        $this->assertSame('+998 91 111 2233', $manager->fresh()->phone);

        $lead = $this->user(Role::Manager, $branch, ['leads.view', 'leads.manage']);
        $this->actingAs($lead)->post('/leads', ['name' => 'Lid', 'phone' => '90 123 45 67'])->assertSessionHasErrors('phone');
        $this->post("/apply/{$branch->code}", ['name' => 'Sayt', 'phone' => '901234567'])->assertSessionHasErrors('phone');
        $this->actingAs($sadmin)->post('/branches', ['name' => 'Yangi filial', 'phone' => '12345'])->assertSessionHasErrors('phone');
    }

    public function test_same_phone_allowed_across_roles_and_branches_but_not_within_role_and_branch(): void
    {
        $a = $this->branch('A');
        $b = $this->branch('B');
        $admin = $this->user(Role::Admin, $a, ['staff.view', 'staff.manage', 'students.create', 'students.view', 'teachers.manage']);
        $phone = '+998 90 123 4567';

        // Bir filialda: o'quvchi, o'qituvchi va menejer bitta raqamda bo'la oladi
        $this->actingAs($admin)->post('/students', ['name' => 'Talaba', 'phone' => $phone])->assertSessionHasNoErrors();
        $staff = ['password' => 'parol12345', 'password_confirmation' => 'parol12345', 'status' => 'active', 'phone' => $phone];
        $this->actingAs($admin)->post('/staff', $staff + ['name' => 'Ustoz', 'username' => 'ustoz1', 'role' => 'teacher'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post('/staff', $staff + ['name' => 'Menejer', 'username' => 'menejer1', 'role' => 'manager'])->assertSessionHasNoErrors();

        // Lekin har bir rolda ikkinchi marta emas
        $this->actingAs($admin)->post('/students', ['name' => 'Ikkinchi', 'phone' => $phone])->assertSessionHasErrors('phone');
        $this->actingAs($admin)->post('/staff', $staff + ['name' => 'Ustoz 2', 'username' => 'ustoz2', 'role' => 'teacher'])->assertSessionHasErrors('phone');
        $this->actingAs($admin)->post('/staff', $staff + ['name' => 'Menejer 2', 'username' => 'menejer2', 'role' => 'manager'])->assertSessionHasErrors('phone');

        // Boshqa filialda ruxsat
        $adminB = $this->user(Role::Admin, $b, ['students.create', 'students.view']);
        $this->actingAs($adminB)->post('/students', ['name' => 'Boshqa filial', 'phone' => $phone])->assertSessionHasNoErrors();
        $this->assertSame(2, User::where('role', 'student')->where('phone', $phone)->count());

        // Tahrirlashda o'z raqamini saqlab qolish mumkin, boshqasinikiga o'zgartirib bo'lmaydi
        $student = User::where('role', 'student')->where('branch_id', $a->id)->firstOrFail();
        $other = $this->student($a, ['phone' => '+998 90 999 8877']);
        $this->actingAs($admin)->put("/students/{$student->id}", ['name' => 'Talaba', 'phone' => $phone])->assertSessionHasNoErrors();
        $this->actingAs($admin)->put("/students/{$student->id}", ['name' => 'Talaba', 'phone' => '+998 90 999 8877'])->assertSessionHasErrors('phone');

        // Qo'shimcha telefon takrorlanishi mumkin (aka-uka)
        $this->actingAs($admin)->post('/students', ['name' => 'Aka', 'phone' => '+998 93 111 0001', 'phone2' => '+998 90 555 0000'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post('/students', ['name' => 'Uka', 'phone' => '+998 93 111 0002', 'phone2' => '+998 90 555 0000'])->assertSessionHasNoErrors();
    }

    public function test_database_enforces_uniqueness_even_without_validation(): void
    {
        $branch = $this->branch();
        $this->user(Role::Student, $branch, [], ['phone' => '+998 90 123 4567']);

        $this->expectException(QueryException::class);
        $this->user(Role::Student, $branch, [], ['phone' => '+998 90 123 4567']);
    }

    public function test_phone_inputs_carry_mask_attributes_and_money_inputs_format(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['students.create', 'students.view', 'payments.create', 'payments.discount', 'payments.refund']);
        $student = $this->student($branch);

        $html = $this->actingAs($admin)->get('/students/create')->getContent();
        $this->assertStringContainsString('data-phone', $html);
        $this->assertStringContainsString('placeholder="+998 90 123 4567"', $html);

        $show = $this->get("/students/{$student->id}")->getContent();
        $this->assertStringContainsString('data-money', $show);
        $this->assertStringNotContainsString('name="cash" type="number"', $show);
    }

    public function test_money_spaces_are_stripped_on_the_server(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['payments.create', 'students.view']);
        $student = $this->student($branch);

        $this->actingAs($admin)->post("/students/{$student->id}/payments", ['cash' => '1 250 000', 'card' => "500\u{00A0}000"])->assertSessionHasNoErrors();

        $this->assertSame(1750000, $student->fresh()->balance);
    }
}
