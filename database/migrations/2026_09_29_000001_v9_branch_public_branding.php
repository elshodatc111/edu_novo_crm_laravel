<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v9 (3-band): ochiq murojaat (Varonka/apply) sahifasida Edunova o'rniga har bir filialning
 * o'z rangi va qisqa ma'lumoti ko'rsatilishi uchun. Fayl yuklash yo'q — faqat rang (HEX) va matn.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->string('brand_color', 7)->nullable()->after('address');
            $table->text('public_about')->nullable()->after('brand_color');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn(['brand_color', 'public_about']);
        });
    }
};
