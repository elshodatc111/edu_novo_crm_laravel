<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v8 B1: avtomatik SMS (qarz eslatmasi va darsga kelmaganlik xabari) uchun filial bayroqlari.
 * SMS xarajati sabab, ikkalasi ham boshlang'ich holatda O'CHIQ (mavjud filiallarda ham).
 * Ota-ona telefoni uchun alohida ustun kerak emas — mavjud `users.phone2`
 * ("Qo'shimcha telefon (ota-ona)") ishlatiladi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->boolean('sms_auto_debt')->default(false)->after('sms_enabled');
            $table->boolean('sms_auto_absent')->default(false)->after('sms_auto_debt');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn(['sms_auto_debt', 'sms_auto_absent']);
        });
    }
};
