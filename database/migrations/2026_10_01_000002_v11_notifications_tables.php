<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v11: sAdmin tomonidan mobil ilova foydalanuvchilariga yuboriladigan bildirishnomalar.
 * `branch_id` NULL = barcha filial foydalanuvchilariga; qiymat bo'lsa - faqat shu filialga.
 * Qabul qiluvchilar yuborish PAYTIDA `notification_recipients`ga materiallashtiriladi (SMS
 * tarixidagi kabi) - shu bilan "o'qildi" holati va push yuborilganmi degan holat har bir
 * foydalanuvchi uchun alohida saqlanadi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('title', 150);
            $table->text('body');
            $table->json('data')->nullable(); // ixtiyoriy: ilovada qayerga o'tish kerakligi (masalan {"type":"lead","id":5})
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('recipients_count')->default(0);
            $table->timestamps();
        });

        Schema::create('notification_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')->constrained('notifications')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->string('push_status', 20)->default('pending'); // pending | sent | failed | skipped
            $table->timestamps();

            $table->unique(['notification_id', 'user_id']);
            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_recipients');
        Schema::dropIfExists('notifications');
    }
};
