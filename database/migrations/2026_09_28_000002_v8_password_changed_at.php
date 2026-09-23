<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * v8 A5: parolni majburiy o'zgartirish YO'Q - faqat 30 kunda bir marta yumshoq eslatma (bosh sahifada).
 * `password_changed_at` User modelida `saving` hodisasida (parol o'zgarganda) avtomatik yangilanadi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('password_changed_at')->nullable()->after('password');
        });

        // Eski foydalanuvchilar uchun - hisob ochilgan kunni boshlang'ich sana deb olamiz
        // (parol chindan ham o'shandan beri o'zgarmagan bo'lishi mumkin, shuning uchun eslatma to'g'ri ko'rinadi).
        DB::table('users')->whereNull('password_changed_at')->update(['password_changed_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('password_changed_at');
        });
    }
};
