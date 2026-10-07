<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * v13: «Chiqim (moliya balansiga o'tkazish)» so'rovi alohida ruxsat (`cashbox.withdraw`) bo'ldi;
 * `cashbox.request` endi faqat «Xarajat» so'rovini bildiradi. Avval ikkalasi bitta ruxsat edi, shuning uchun
 * mavjud hodimlarning imkoniyati o'zgarmasligi uchun `cashbox.request` borlarga `cashbox.withdraw` bir marta beriladi.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $rows = DB::table('user_permissions')->where('permission', 'cashbox.request')->pluck('user_id')->map(fn ($id) => [
            'user_id' => $id, 'permission' => 'cashbox.withdraw', 'created_at' => $now, 'updated_at' => $now,
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
