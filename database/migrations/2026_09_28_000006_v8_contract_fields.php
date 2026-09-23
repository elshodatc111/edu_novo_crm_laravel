<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v8 B2: chek va shartnoma. Shartnoma matni filialda tahrirlanadi (SMS shablonlariga o'xshab -
 * bo'sh bo'lsa ContractService::defaultTemplate() ishlatiladi). STIR va direktor - shartnomaning
 * imzo bo'limi uchun, ixtiyoriy (bo'sh qoldirilsa shartnomada bo'sh qoladi).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->string('stir', 30)->nullable()->after('address');
            $table->string('director_name', 120)->nullable()->after('stir');
            $table->string('director_title', 60)->nullable()->after('director_name');
            $table->text('contract_template')->nullable()->after('director_title');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn(['stir', 'director_name', 'director_title', 'contract_template']);
        });
    }
};
