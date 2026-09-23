<?php

namespace Tests\Feature;

use App\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** v12: mobil ilovada profilni tahrirlash (telefon + rasm). */
class V12ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_phone(): void
    {
        $user = $this->user(Role::Teacher, attrs: ['phone' => '+998901234567']);
        $token = $user->createToken('t')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/me/profile', ['phone' => '+998 90 765 4321'])
            ->assertOk()->assertJsonPath('data.phone', '+998 90 765 4321');

        $this->assertSame('+998 90 765 4321', $user->fresh()->phone);
    }

    public function test_phone_must_be_canonical_format(): void
    {
        $user = $this->user(Role::Teacher, attrs: ['phone' => '+998901234567']);
        $token = $user->createToken('t')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/me/profile', ['phone' => '998907654321'])
            ->assertStatus(422)->assertJsonValidationErrors('phone');
    }

    public function test_phone_cannot_collide_with_another_user_in_same_branch_and_role(): void
    {
        $branch = $this->branch();
        $this->user(Role::Teacher, $branch, attrs: ['phone' => '+998 90 111 1111']);
        $user = $this->user(Role::Teacher, $branch, attrs: ['phone' => '+998 90 222 2222']);
        $token = $user->createToken('t')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/me/profile', ['phone' => '+998 90 111 1111'])
            ->assertStatus(422)->assertJsonValidationErrors('phone');
    }

    public function test_user_can_upload_and_replace_photo(): void
    {
        Storage::fake('public');

        $user = $this->user(Role::Student, attrs: ['phone' => '+998901234567']);
        $token = $user->createToken('t')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/v1/me/profile', [
            'photo' => UploadedFile::fake()->image('rasm.jpg'),
        ])->assertOk();

        $path = $user->fresh()->photo_path;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
        $this->assertNotNull($response->json('data.photo_url'));

        // Yangi rasm yuklanganda eskisi o'chiriladi
        $this->withToken($token)->postJson('/api/v1/me/profile', [
            'photo' => UploadedFile::fake()->image('rasm2.jpg'),
        ])->assertOk();

        Storage::disk('public')->assertMissing($path);
        $this->assertNotSame($path, $user->fresh()->photo_path);
    }

    public function test_user_can_remove_photo(): void
    {
        Storage::fake('public');

        $user = $this->user(Role::Student, attrs: ['phone' => '+998901234567']);
        $token = $user->createToken('t')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/me/profile', ['photo' => UploadedFile::fake()->image('rasm.jpg')])->assertOk();
        $path = $user->fresh()->photo_path;

        $this->withToken($token)->postJson('/api/v1/me/profile', ['remove_photo' => 1])->assertOk()
            ->assertJsonPath('data.photo_url', null);

        Storage::disk('public')->assertMissing($path);
        $this->assertNull($user->fresh()->photo_path);
    }

    public function test_non_image_file_is_rejected(): void
    {
        Storage::fake('public');

        $user = $this->user(Role::Student, attrs: ['phone' => '+998901234567']);
        $token = $user->createToken('t')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/me/profile', [
            'photo' => UploadedFile::fake()->create('hujjat.pdf', 100, 'application/pdf'),
        ])->assertStatus(422)->assertJsonValidationErrors('photo');
    }
}
