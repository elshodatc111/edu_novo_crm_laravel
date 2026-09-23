<?php

namespace Tests\Feature;

use App\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * v12: xodim qo'shish (`POST /api/v1/staff`) avval sAdmin uchun so'rov tanasidagi
 * `branch_id`dan foydalanardi - boshqa barcha sAdmin yozish amallaridan farqli edi.
 * Endi u ham `X-Branch-Id` sarlavhasidan (BranchContext) foydalanadi, mexanizm izchil.
 */
class V12StaffBranchHeaderTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $over = []): array
    {
        return [
            'name' => 'Yangi Xodim', 'username' => 'yangi.xodim.'.uniqid(),
            'phone' => '+998 90 555 '.random_int(1000, 9999),
            'status' => 'active', 'password' => 'parol12345', 'password_confirmation' => 'parol12345',
            'role' => 'manager', ...$over,
        ];
    }

    public function test_sadmin_creates_staff_via_branch_header(): void
    {
        $branch = $this->branch();
        $sadmin = $this->user(Role::SAdmin);
        $token = $sadmin->createToken('mobile')->plainTextToken;

        $response = $this->api($token)->withHeaders(['X-Branch-Id' => $branch->id])
            ->postJson('/api/v1/staff', $this->payload(['username' => 'header.orqali']));

        $response->assertCreated();
        $this->assertDatabaseHas('users', ['username' => 'header.orqali', 'branch_id' => $branch->id]);
    }

    public function test_sadmin_create_staff_without_branch_header_fails(): void
    {
        $sadmin = $this->user(Role::SAdmin);
        $token = $sadmin->createToken('mobile')->plainTextToken;

        $response = $this->api($token)->postJson('/api/v1/staff', $this->payload());

        $response->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_sadmin_sending_branch_id_in_body_is_rejected(): void
    {
        $branch = $this->branch();
        $other = $this->branch('Boshqa');
        $sadmin = $this->user(Role::SAdmin);
        $token = $sadmin->createToken('mobile')->plainTextToken;

        // X-Branch-Id sarlavhasi bilan birga eski (so'rov tanasidagi) branch_id ham yuborilsa,
        // u e'tiborga olinmasligi kerak - so'rov tanasida bu maydon umuman ruxsat etilmagan.
        $response = $this->api($token)->withHeaders(['X-Branch-Id' => $branch->id])
            ->postJson('/api/v1/staff', $this->payload(['branch_id' => $other->id]));

        $response->assertStatus(422)->assertJsonValidationErrors(['branch_id']);
    }

    public function test_admin_still_uses_own_branch_without_header(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['staff.view', 'staff.manage']);
        $token = $admin->createToken('mobile')->plainTextToken;

        $response = $this->api($token)->postJson('/api/v1/staff', $this->payload());

        $response->assertCreated();
        $this->assertDatabaseHas('users', ['branch_id' => $branch->id, 'role' => 'manager']);
    }
}
