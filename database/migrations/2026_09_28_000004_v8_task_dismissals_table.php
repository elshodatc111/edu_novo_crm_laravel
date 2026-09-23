<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kunlik vazifalar paneli: "Bajardim" bosilganda shu kunga yozuv qo'shiladi.
 * Ertaga shart hali to'g'ri bo'lsa (masalan hali ham qarzdor), vazifa qayta chiqadi —
 * bu soxta "bajardim" bosishning foyda bermasligini ta'minlaydi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_dismissals', function (Blueprint $table) {
            $table->id();
            // sAdmin filial tanlamagan holatda ham "bajardim" bosa olishi uchun nullable
            // (BelongsToBranch ishlatilmaydi — creating() qoidasi shu holatda xatolik berardi).
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('task_key', 120);
            $table->date('dismissed_on');
            $table->timestamp('created_at')->nullable();

            $table->unique(['user_id', 'task_key', 'dismissed_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_dismissals');
    }
};
