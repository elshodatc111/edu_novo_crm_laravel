<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 60)->unique();
            $table->string('phone', 30)->nullable();
            $table->string('address')->nullable();
            $table->enum('status', ['active', 'closed'])->default('active');
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->string('closed_reason')->nullable();
            // Filialning o'z Eskiz akkaunti (bo'sh bo'lsa umumiy akkaunt ishlatiladi)
            $table->string('eskiz_email')->nullable();
            $table->text('eskiz_password')->nullable();
            $table->string('eskiz_from', 30)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
