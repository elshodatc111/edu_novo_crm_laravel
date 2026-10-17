<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v13: o'qituvchi ustamasi «Usluga» (staj bo'yicha qat'iy summa).
 * Faqat yangi, bo'sh (nullable) ustunlar: mavjud ma'lumot o'zgarmaydi va o'chmaydi.
 *  - users.hired_at            : o'qituvchining ishga olingan sanasi
 *  - branches.usluga_from      : shu sanadan keyin boshlangan guruhlarga qo'llanadi (bo'sh = o'chiq)
 *  - branches.usluga_rates     : 4 ta pog'ona uchun summalar (JSON)
 *  - groups.usluga_*           : guruh boshlanganda qotiriladigan summalar
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'hired_at')) {
                $table->date('hired_at')->nullable();
            }
        });

        Schema::table('branches', function (Blueprint $table) {
            if (! Schema::hasColumn('branches', 'usluga_from')) {
                $table->date('usluga_from')->nullable();
                $table->json('usluga_rates')->nullable();
            }
        });

        Schema::table('groups', function (Blueprint $table) {
            if (! Schema::hasColumn('groups', 'usluga_tier')) {
                $table->unsignedTinyInteger('usluga_tier')->nullable();
                $table->unsignedBigInteger('usluga_student_amount')->nullable();
                $table->unsignedBigInteger('usluga_bonus_amount')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Additive migratsiya: ma'lumot saqlanadi, ustunlar qaytarilmaydi.
    }
};
