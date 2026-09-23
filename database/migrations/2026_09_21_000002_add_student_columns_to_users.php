<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone2', 30)->nullable()->after('phone');
            $table->text('about')->nullable()->after('address');
            $table->bigInteger('balance')->default(0)->after('about');
            $table->foreignId('lead_source_id')->nullable()->after('balance')->constrained('lead_sources')->nullOnDelete();
            $table->timestamp('archived_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lead_source_id');
            $table->dropColumn(['phone2', 'about', 'balance', 'archived_at']);
        });
    }
};
