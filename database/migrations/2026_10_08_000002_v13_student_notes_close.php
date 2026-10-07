<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v13: ichki eslatmalarni «faolsizlantirish» (o'chirmasdan yopish). Yopilgan eslatma ro'yxatda va tarixda
 * qoladi, lekin qo'ng'iroqchadagi faol eslatmalar sonidan chiqadi. Qayta faollashtirish mumkin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_notes', function (Blueprint $table) {
            $table->timestamp('closed_at')->nullable()->after('body');
            $table->foreignId('closed_by')->nullable()->after('closed_at')->constrained('users')->nullOnDelete();

            $table->index(['branch_id', 'closed_at'], 'student_notes_branch_closed_index');
        });
    }

    public function down(): void
    {
        Schema::table('student_notes', function (Blueprint $table) {
            $table->dropIndex('student_notes_branch_closed_index');
            $table->dropConstrainedForeignId('closed_by');
            $table->dropColumn('closed_at');
        });
    }
};
