<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v12: mobil ilova versiyasini tekshirish (`GET /api/v1/app/version`, login talab qilmaydi -
 * ilova ochilganda darhol so'raladi). sAdmin veb panelda ("Mobil ilova versiyasi" sahifasida)
 * har platforma uchun `min_version` (bundan pastini MAJBURIY yangilash) va `latest_version`
 * (yumshoq taklif) ni sozlaydi - shu bilan eski ilova versiyalarini serverni qayta joylashtirmasdan
 * bloklash mumkin. Har ikkala qator boshidanoq mavjud (standart 1.0.0) - API hech qachon
 * "topilmadi" holatiga tushmaydi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_versions', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 20)->unique(); // android | ios
            $table->string('min_version', 20)->default('1.0.0');
            $table->string('latest_version', 20)->default('1.0.0');
            $table->string('update_url')->nullable();
            $table->string('message')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        foreach (['android', 'ios'] as $platform) {
            \Illuminate\Support\Facades\DB::table('app_versions')->insert([
                'platform' => $platform, 'min_version' => '1.0.0', 'latest_version' => '1.0.0',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('app_versions');
    }
};
