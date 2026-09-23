<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\Format;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StudentService
{
    /**
     * O'quvchi yaratadi. Login - telefon raqami, parol tasodifiy yaratiladi
     * va bir marta ko'rsatiladi.
     *
     * @return array{0: User, 1: string} [o'quvchi, ochiq parol]
     */
    public function create(array $data, User $actor, bool $notify = true): array
    {
        $password = $this->generatePassword();
        $data['phone'] = Format::canonicalPhone($data['phone']) ?? $data['phone'];
        $data['phone2'] = ! empty($data['phone2']) ? (Format::canonicalPhone($data['phone2']) ?? $data['phone2']) : null;

        $student = User::create([
            ...$data,
            'role' => Role::Student,
            'branch_id' => $data['branch_id'] ?? \App\Support\BranchContext::id(),
            'username' => $this->makeUsername($data['phone']),
            'name' => mb_strtoupper(trim($data['name'])),
            'password' => $password,
            'status' => UserStatus::Active,
            'balance' => 0,
        ]);

        AuditLog::record('student.created', $student, 'Markazga tashrif: yangi o\'quvchi qo\'shildi');
        if ($notify) {
            app(SmsNotifier::class)->notify('student_welcome', $student, ['login' => $student->username, 'password' => $password]);
        }

        return [$student, $password];
    }

    public function update(User $student, array $data): User
    {
        $data['name'] = mb_strtoupper(trim($data['name']));
        $data['phone'] = Format::canonicalPhone($data['phone']) ?? $data['phone'];
        $data['phone2'] = ! empty($data['phone2']) ? (Format::canonicalPhone($data['phone2']) ?? $data['phone2']) : null;
        $student->update($data);

        AuditLog::record('student.updated', $student, "Ma'lumotlari yangilandi");

        return $student;
    }

    public function resetPassword(User $student): string
    {
        $password = $this->generatePassword();
        $student->update(['password' => $password]);
        $student->tokens()->delete();

        AuditLog::record('student.password_reset', $student, 'Parol yangilandi');
        app(SmsNotifier::class)->notify('password_reset', $student, ['login' => $student->username, 'password' => $password]);

        return $password;
    }

    public function archive(User $student): void
    {
        if ($student->memberships()->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['student' => "Avval o'quvchini barcha guruhlardan chiqaring."]);
        }

        $student->update(['archived_at' => now()]);
        $student->tokens()->delete();

        AuditLog::record('student.archived', $student, 'Arxivga o\'tkazildi');
    }

    public function restore(User $student): void
    {
        $student->update(['archived_at' => null]);

        AuditLog::record('student.restored', $student, 'Arxivdan qaytarildi');
    }

    private function makeUsername(string $phone): string
    {
        $base = Format::normalizePhone($phone) ?? (Format::digits($phone) ?: 's'.time());
        $username = $base;

        for ($i = 2; User::where('username', $username)->exists(); $i++) {
            $username = $base.'-'.$i;
        }

        return $username;
    }

    private function generatePassword(): string
    {
        return Str::lower(Str::random(8));
    }
}
