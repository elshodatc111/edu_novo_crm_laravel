<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** v13: «Guruh narxini o'zgartirish» (`groups.change_price`) - mavjud adminlarga bir marta beriladi. Additive. */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $rows = DB::table('users')->where('role', 'admin')->pluck('id')->map(fn ($id) => [
            'user_id' => $id, 'permission' => 'groups.change_price', 'created_at' => $now, 'updated_at' => $now,
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
