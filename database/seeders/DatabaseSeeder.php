<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /** Birinchi sAdmin akkauntini yaratadi (.env dagi SADMIN_* qiymatlari bo'yicha). */
    public function run(): void
    {
        $username = env('SADMIN_USERNAME', 'sadmin');

        if (User::where('role', Role::SAdmin)->exists()) {
            $this->command?->info('sAdmin allaqachon mavjud, o\'tkazib yuborildi.');

            return;
        }

        $password = env('SADMIN_PASSWORD') ?: Str::password(12, symbols: false);

        User::create([
            'role' => Role::SAdmin,
            'name' => env('SADMIN_NAME', 'Bosh administrator'),
            'username' => $username,
            'password' => $password,
            'status' => UserStatus::Active,
        ]);

        $this->command?->info("sAdmin yaratildi.  Login: {$username}   Parol: {$password}");
    }
}
