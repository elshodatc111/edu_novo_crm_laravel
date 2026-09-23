<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v10: Moliya bo'limidagi to'g'ridan-to'g'ri xarajat (`finance.expense`, kassa so'rovisiz) uchun ham
 * ixtiyoriy xarajat turi (`expense_categories`) tanlash imkoniyati. Kassa orqali o'tgan xarajatlar
 * turkumi CashRequest orqali (subject) allaqachon bor edi; bu ustun faqat to'g'ridan-to'g'ri
 * moliya chiqimlari uchun kerak.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('subject_id')->constrained('expense_categories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });
    }
};
