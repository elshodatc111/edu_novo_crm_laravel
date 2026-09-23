<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v8 B3: kassa smenasini yopish (kutilgan/haqiqiy naqt solishtirish - farq faqat yoziladi,
 * kassa hech qachon qo'lda o'zgartirilmaydi) va kassa xarajat so'rovlari uchun ixtiyoriy turkum.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['branch_id', 'name']);
        });

        Schema::table('cash_requests', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('kind')->constrained('expense_categories')->nullOnDelete();
        });

        Schema::create('cash_closings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('expected_cash');
            $table->unsignedBigInteger('actual_cash');
            $table->bigInteger('difference');
            $table->string('note')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['branch_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_closings');
        Schema::table('cash_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });
        Schema::dropIfExists('expense_categories');
    }
};
