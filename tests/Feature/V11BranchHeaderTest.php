<?php

namespace Tests\Feature;

use App\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * v11: mobil API stateless (sessiya yo'q), shuning uchun sAdmin qaysi filial bilan
 * ishlayotganini `X-Branch-Id` sarlavhasi orqali ko'rsatadi. Bu testlar shu mexanizmni
 * va uning veb sessiyaga hech qanday ta'sir qilmasligini tekshiradi.
 */
class V11BranchHeaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_sadmin_without_header_sees_all_branches_unrestricted(): void
    {
        $a = $this->branch('A');
        $b = $this->branch('B');
        $this->student($a);
        $this->student($b);

        $sadmin = $this->user(Role::SAdmin);
        $token = $sadmin->createToken('mobile')->plainTextToken;

        $response = $this->api($token)->getJson('/api/v1/students');
        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_sadmin_with_header_is_scoped_to_that_branch(): void
    {
        $a = $this->branch('A');
        $b = $this->branch('B');
        $this->student($a);
        $this->student($b);

        $sadmin = $this->user(Role::SAdmin);
        $token = $sadmin->createToken('mobile')->plainTextToken;

        $response = $this->api($token)->withHeaders(['X-Branch-Id' => $a->id])->getJson('/api/v1/students');
        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_sadmin_write_endpoint_requires_branch_header(): void
    {
        $branch = $this->branch();
        $sadmin = $this->user(Role::SAdmin);
        $token = $sadmin->createToken('mobile')->plainTextToken;

        $response = $this->api($token)->postJson('/api/v1/leads', ['name' => 'X', 'phone' => '+998 90 111 2233']);

        $response->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_sadmin_write_endpoint_succeeds_with_branch_header(): void
    {
        $branch = $this->branch();
        $sadmin = $this->user(Role::SAdmin);
        $token = $sadmin->createToken('mobile')->plainTextToken;

        $response = $this->api($token)->withHeaders(['X-Branch-Id' => $branch->id])
            ->postJson('/api/v1/leads', ['name' => 'X', 'phone' => '+998 90 111 2233']);

        $response->assertCreated();
        $this->assertDatabaseHas('leads', ['name' => 'X', 'branch_id' => $branch->id]);
    }

    public function test_non_sadmin_role_ignores_branch_header(): void
    {
        $a = $this->branch('A');
        $b = $this->branch('B');
        $this->student($a);
        $admin = $this->user(Role::Admin, $a, ['students.view']);
        $token = $admin->createToken('mobile')->plainTextToken;

        // Boshqa filial ID sini yuborsa ham, admin faqat OZINING filialini ko'radi (header e'tiborga olinmaydi)
        $response = $this->api($token)->withHeaders(['X-Branch-Id' => $b->id])->getJson('/api/v1/students');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_header_does_not_affect_web_session_branch_selection(): void
    {
        $a = $this->branch('A');
        $b = $this->branch('B');
        $sadmin = $this->user(Role::SAdmin);

        // Veb sessiyada B tanlangan
        $this->actingAs($sadmin);
        \App\Support\BranchContext::select($b->id);
        $this->assertSame($b->id, \App\Support\BranchContext::id());
    }
}
