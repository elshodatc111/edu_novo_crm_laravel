<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v11: mobil ilova uchun push-bildirishnoma (Firebase/FCM) qurilma tokenlari.
 * Bir foydalanuvchi bir nechta qurilmadan kirishi mumkin (masalan telefon + planshet) -
 * shuning uchun (user_id, device_name) juftligi unikal: xuddi shu qurilma nomi bilan
 * qayta ro'yxatdan o'tilsa, eski token yangisiga almashtiriladi (AuthController'dagi
 * "bitta qurilma = bitta token" mantig'iga mos).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('token', 255);
            $table->string('platform', 20)->default('android'); // android | ios | web
            $table->string('device_name', 100)->default('mobile');
            $table->timestamps();

            $table->unique(['user_id', 'device_name']);
            $table->index('token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};
