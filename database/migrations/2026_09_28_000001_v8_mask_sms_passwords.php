<?php

use App\Support\SmsPasswordMasker;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * v8 A5: SMS tarixida parollarni berkitish.
 * - `secret`: yuborilishi kerak bo'lgan HAQIQIY matn (parol bilan) - yuborilgach (muvaffaqiyatli
 *   yoki xato bo'lsa ham) darhol NULL qilinadi. `message` ustuni har doim tarix/ro'yxatda
 *   ko'rinadigan, parol o'rniga "••••••••" qo'yilgan matnni saqlaydi.
 * - Eski yozuvlarda ochiq saqlanib qolgan parollar shu migratsiyada berkitiladi (bir martalik tozalash).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_messages', function (Blueprint $table) {
            $table->text('secret')->nullable()->after('message');
        });

        DB::table('sms_messages')
            ->where(function ($q) {
                $q->whereIn('template_key', ['student_welcome', 'password_reset'])
                    ->orWhere('message', 'like', '%parol%');
            })
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $masked = SmsPasswordMasker::mask($row->message);

                    if ($masked !== $row->message) {
                        DB::table('sms_messages')->where('id', $row->id)->update(['message' => $masked]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Diqqat: berkitilgan parollarni asl holiga qaytarib bo'lmaydi (xavfsizlik uchun ataylab).
        Schema::table('sms_messages', function (Blueprint $table) {
            $table->dropColumn('secret');
        });
    }
};
