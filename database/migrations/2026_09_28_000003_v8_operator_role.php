<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Yangi "operator" roli. `users.role` ustuni native ENUM bo'lgani uchun
 * ro'yxatni kengaytirish kerak (additive — eski qiymatlar o'zgarmaydi,
 * mavjud foydalanuvchilar buzilmaydi).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['sadmin', 'admin', 'manager', 'teacher', 'student', 'operator'])->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['sadmin', 'admin', 'manager', 'teacher', 'student'])->change();
        });
    }
};
