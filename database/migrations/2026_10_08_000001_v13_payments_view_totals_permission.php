<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * v13: «To'lovlarning umumiy yig'indisini ko'rish» (`payments.view_totals`) alohida ruxsat bo'ldi.
 * Mavjud adminlarning ko'rish imkoniyati saqlanishi uchun ularga shu ruxsat bir marta beriladi.
 * Menejer va operatorlarga admin yoki sAdmin kerak bo'lsa o'zi beradi (avvalgidek avtomatik emas).
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $rows = DB::table('users')->where('role', 'admin')->pluck('id')->map(fn ($id) => [
            'user_id' => $id, 'permission' => 'payments.view_totals', 'created_at' => $now, 'updated_at' => $now,
        ])->all();

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('user_permissions')->insertOrIgnore($chunk);
        }
    }

    public function down(): void
    {
        // Ma'lumot migratsiyasi: qaytarilmaydi
    }
};
