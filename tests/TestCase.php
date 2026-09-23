<?php

namespace Tests;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Filial uchun guruh ochishga kerak bo'lgan ma'lumotnomalar. */
    protected function catalog(Branch $branch): array
    {
        $teacher = $this->user(Role::Teacher, $branch, ['attendance.view', 'attendance.take']);

        return [
            'teacher' => $teacher,
            'course' => \App\Models\Course::create(['branch_id' => $branch->id, 'name' => 'Koreys tili']),
            'room' => \App\Models\Room::create(['branch_id' => $branch->id, 'name' => '1-xona']),
            'time' => \App\Models\LessonTime::create(['branch_id' => $branch->id, 'starts_at' => '09:00', 'ends_at' => '10:30']),
            'plan' => \App\Models\PricePlan::create(['branch_id' => $branch->id, 'name' => 'Standart', 'amount' => 500000, 'early_discount' => 50000]),
        ];
    }

    protected function groupPayload(array $c, array $over = []): array
    {
        return [
            'name' => 'a1 guruh', 'course_id' => $c['course']->id, 'teacher_id' => $c['teacher']->id, 'room_id' => $c['room']->id,
            'lesson_time_id' => $c['time']->id, 'price_plan_id' => $c['plan']->id, 'schedule' => 'odd',
            'starts_on' => '2026-09-21', 'lesson_count' => 4, ...$over,
        ];
    }

    /** Filial kassasiga pul qo'yadi (sinov uchun). */
    protected function fund(Branch $branch, \App\Enums\Wallet $wallet, int $amount): void
    {
        \App\Models\WalletAccount::updateOrCreate(['branch_id' => $branch->id, 'code' => $wallet->value], ['balance' => $amount]);
    }

    /** Har bir API so'rovi uchun yangi autentifikatsiya holati (testda guard keshi bo'lmasligi uchun). */
    protected function api(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    protected function student(Branch $branch, array $attrs = []): User
    {
        return $this->user(Role::Student, $branch, [], $attrs);
    }

    protected function branch(string $name = 'Filial'): Branch
    {
        return Branch::create(['name' => $name, 'code' => Branch::makeCode($name), 'status' => 'active', 'opened_at' => now()]);
    }

    protected function user(Role $role, ?Branch $branch = null, array $permissions = [], array $attrs = []): User
    {
        static $i = 0;
        $i++;

        $user = User::create([
            'role' => $role,
            'branch_id' => $role === Role::SAdmin ? null : ($branch ?? $this->branch("Filial {$i}"))->id,
            'name' => "Foydalanuvchi {$i}",
            'username' => "user{$i}",
            'password' => 'parol12345',
            ...$attrs,
        ]);

        $user->syncPermissions($permissions);

        return $user;
    }
}
