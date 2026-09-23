<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v12: mobil ilovada "Parolni unutdingizmi" - SMS orqali 6 xonali tasdiqlash kodi bilan
 * parolni tiklash. Kod bazada oddiy matn emas, hash qilib saqlanadi (parol kabi); har bir
 * so'rov (`auth/forgot-password`) foydalanuvchining eski, ishlatilmagan kodlarini bekor qiladi
 * (`used_at`ga hozirgi vaqt qo'yiladi), shuning uchun bir vaqtda faqat bitta kod amal qiladi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_reset_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'used_at', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_codes');
    }
};
