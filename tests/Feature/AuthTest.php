<?php

namespace Tests\Feature;

use App\Enums\BranchStatus;
use App\Enums\Role;
use App\Enums\UserStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_user_can_login_with_username_and_email(): void
    {
        $user = $this->user(Role::Admin, attrs: ['email' => 'admin@edunova.uz']);

        $this->post('/login', ['login' => $user->username, 'password' => 'parol12345'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        auth()->logout();
        $this->post('/login', ['login' => 'ADMIN@edunova.uz', 'password' => 'parol12345'])->assertRedirect(route('dashboard'));
    }

    public function test_wrong_password_is_rejected(): void
    {
        $user = $this->user(Role::Admin);

        $this->from('/login')->post('/login', ['login' => $user->username, 'password' => 'xato'])
            ->assertRedirect('/login')->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_blocked_user_cannot_login(): void
    {
        $user = $this->user(Role::Manager, attrs: ['status' => UserStatus::Blocked]);

        $this->post('/login', ['login' => $user->username, 'password' => 'parol12345'])->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_user_of_closed_branch_cannot_login(): void
    {
        $branch = $this->branch();
        $user = $this->user(Role::Admin, $branch);
        $branch->update(['status' => BranchStatus::Closed]);

        $this->post('/login', ['login' => $user->username, 'password' => 'parol12345'])->assertSessionHasErrors('login');
    }

    public function test_student_cannot_use_web_panel(): void
    {
        $user = $this->user(Role::Student);

        $this->post('/login', ['login' => $user->username, 'password' => 'parol12345'])->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_login_is_rate_limited(): void
    {
        $user = $this->user(Role::Admin);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['login' => $user->username, 'password' => 'xato']);
        }

        $this->post('/login', ['login' => $user->username, 'password' => 'parol12345'])->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_session_ends_when_branch_gets_closed(): void
    {
        $branch = $this->branch();
        $user = $this->user(Role::Admin, $branch);

        $this->actingAs($user)->get('/')->assertOk();

        $branch->update(['status' => BranchStatus::Closed]);

        $this->actingAs($user->fresh())->get('/')->assertRedirect(route('login'));
    }

    public function test_api_login_me_and_logout(): void
    {
        $user = $this->user(Role::Student);

        $response = $this->postJson('/api/v1/auth/login', ['login' => $user->username, 'password' => 'parol12345', 'device_name' => 'telefon'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.role', 'student');

        $token = $response->json('data.token');

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.username', $user->username);
        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_api_returns_uniform_errors(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized()->assertJson(['success' => false]);
        $this->postJson('/api/v1/auth/login', ['login' => 'yoq', 'password' => 'yoq'])
            ->assertStatus(422)->assertJsonStructure(['success', 'message', 'errors' => ['login']]);
    }

    public function test_api_blocked_user_token_is_rejected(): void
    {
        $user = $this->user(Role::Student);
        $token = $user->createToken('t')->plainTextToken;
        $user->update(['status' => UserStatus::Blocked]);

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertForbidden();
    }

    public function test_api_change_password(): void
    {
        $user = $this->user(Role::Teacher);
        $token = $user->createToken('t')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/auth/change-password', [
            'current_password' => 'parol12345', 'password' => 'yangiParol99', 'password_confirmation' => 'yangiParol99',
        ])->assertOk();

        $this->postJson('/api/v1/auth/login', ['login' => $user->username, 'password' => 'yangiParol99'])->assertOk();
    }
}
