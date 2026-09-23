<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('price_plans', function (Blueprint $table) {
            $table->unsignedBigInteger('max_discount')->default(0)->after('early_discount');
        });

        Schema::table('groups', function (Blueprint $table) {
            $table->unsignedBigInteger('max_discount')->default(0)->after('early_discount');
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->decimal('charity_percent', 5, 2)->default(0)->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('branches', fn (Blueprint $t) => $t->dropColumn('charity_percent'));
        Schema::table('groups', fn (Blueprint $t) => $t->dropColumn('max_discount'));
        Schema::table('price_plans', fn (Blueprint $t) => $t->dropColumn('max_discount'));
    }
};
