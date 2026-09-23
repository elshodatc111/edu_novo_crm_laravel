<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\Wallet;
use App\Models\CashRequest;
use App\Models\Payment;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** v8 A1: bir xil so'rov ("_once" / Idempotency-Key) ikki marta yuborilsa, amal faqat bir marta bajariladi. */
class V8DuplicateSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_payment_submission_is_ignored(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['students.view', 'payments.create']);
        $student = $this->student($branch);

        $once = 'tok-'.uniqid();
        $payload = ['cash' => 100000, 'card' => 0, 'description' => 'Sentabr', '_once' => $once];

        $this->actingAs($admin)->post("/students/{$student->id}/payments", $payload)->assertRedirect()->assertSessionHas('success');
        $this->actingAs($admin)->post("/students/{$student->id}/payments", $payload)->assertRedirect()->assertSessionHas('error');

        // Faqat bitta to'lov yozuvi va balans bir marta oshgan
        $this->assertSame(1, Payment::where('type', 'payment')->count());
        $this->assertSame(100000, $student->fresh()->balance);
        $this->assertSame(100000, app(WalletService::class)->balance($branch->id, Wallet::TillCash));
    }

    public function test_different_once_token_is_not_blocked(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['students.view', 'payments.create']);
        $student = $this->student($branch);

        $this->actingAs($admin)->post("/students/{$student->id}/payments", ['cash' => 50000, '_once' => 'a-'.uniqid()])->assertSessionHas('success');
        $this->actingAs($admin)->post("/students/{$student->id}/payments", ['cash' => 50000, '_once' => 'b-'.uniqid()])->assertSessionHas('success');

        $this->assertSame(2, Payment::where('type', 'payment')->count());
        $this->assertSame(100000, $student->fresh()->balance);
    }

    public function test_missing_once_token_is_not_blocked_for_backward_compatibility(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['students.view', 'payments.create']);
        $student = $this->student($branch);

        $this->actingAs($admin)->post("/students/{$student->id}/payments", ['cash' => 10000])->assertSessionHas('success');
        $this->actingAs($admin)->post("/students/{$student->id}/payments", ['cash' => 10000])->assertSessionHas('success');

        $this->assertSame(2, Payment::where('type', 'payment')->count());
    }

    public function test_duplicate_cashbox_request_is_ignored(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['cashbox.view', 'cashbox.request']);
        $this->fund($branch, Wallet::TillCash, 500000);

        $once = 'cb-'.uniqid();
        $payload = ['kind' => CashRequest::EXPENSE, 'method' => 'cash', 'amount' => 120000, 'description' => 'Ijara', '_once' => $once];

        $this->actingAs($admin)->post('/cashbox/requests', $payload)->assertRedirect()->assertSessionHas('success');
        $this->actingAs($admin)->post('/cashbox/requests', $payload)->assertRedirect()->assertSessionHas('error');

        $this->assertSame(1, CashRequest::count());
        $this->assertSame(380000, app(WalletService::class)->balance($branch->id, Wallet::TillCash));
    }

    public function test_api_idempotency_key_prevents_duplicate_mobile_payment(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['students.view', 'payments.create']);
        $student = $this->student($branch);
        $token = $admin->createToken('t')->plainTextToken;

        $key = 'idem-'.uniqid();
        $this->api($token)->withHeader('Idempotency-Key', $key)
            ->postJson("/api/v1/students/{$student->id}/payments", ['cash' => 75000])
            ->assertOk();
        $this->api($token)->withHeader('Idempotency-Key', $key)
            ->postJson("/api/v1/students/{$student->id}/payments", ['cash' => 75000])
            ->assertStatus(409);

        $this->assertSame(1, Payment::where('type', 'payment')->count());
        $this->assertSame(75000, $student->fresh()->balance);
    }
}
