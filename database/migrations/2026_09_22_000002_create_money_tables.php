<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Filialning barcha "hamyon"lari: kassa (naqt/plastik) va moliya balansi (naqt/plastik/ehson)
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('code', 30);
            $table->bigInteger('balance')->default(0);
            $table->timestamps();
            $table->unique(['branch_id', 'code']);
        });

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('wallet', 30);
            $table->bigInteger('amount');
            $table->bigInteger('balance_after');
            $table->string('type', 40);
            $table->string('description')->nullable();
            $table->string('subject_type', 60)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['branch_id', 'wallet', 'id']);
            $table->index(['branch_id', 'created_at']);
        });

        Schema::create('discount_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('name', 120);
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('bonus');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['branch_id', 'name']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('group_id')->nullable()->constrained('groups')->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained('discount_campaigns')->nullOnDelete();
            $table->string('type', 20);           // payment | discount | campaign_bonus | refund
            $table->string('method', 10)->nullable(); // cash | card
            $table->unsignedBigInteger('amount');
            $table->string('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('refund_confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('refund_confirmed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['branch_id', 'created_at']);
            $table->index(['student_id', 'type']);
        });

        Schema::create('cash_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('kind', 15);              // withdrawal | expense
            $table->string('method', 10);            // cash | card
            $table->unsignedBigInteger('amount');
            $table->string('description');
            $table->string('status', 15)->default('pending'); // pending | approved | cancelled
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
        });

        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('recipient_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('group_id')->nullable()->constrained('groups')->nullOnDelete();
            $table->string('method', 10);
            $table->unsignedBigInteger('amount');
            $table->string('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['recipient_id', 'id']);
            $table->index(['branch_id', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['payouts', 'cash_requests', 'payments', 'discount_campaigns', 'wallet_transactions', 'wallets'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
