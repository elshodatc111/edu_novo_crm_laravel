<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * v8: to'lov stornosi (xato yozuvni teskari yozuv bilan bekor qilish) va qaytarishni rad etish.
 * Mavjud adminlarga yangi "payments.reverse" (storno) ruxsati beriladi (faqat sAdmin va admin storno qila oladi).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('reversal_of_id')->nullable()->after('refund_confirmed_at')->constrained('payments')->nullOnDelete();
            $table->foreignId('reversed_by')->nullable()->after('reversal_of_id')->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable()->after('reversed_by');
            $table->string('reverse_reason')->nullable()->after('reversed_at');

            $table->foreignId('refund_rejected_by')->nullable()->after('reverse_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('refund_rejected_at')->nullable()->after('refund_rejected_by');
            $table->string('refund_reject_reason')->nullable()->after('refund_rejected_at');
        });

        $now = now();
        $rows = DB::table('users')->where('role', 'admin')->pluck('id')->map(fn ($id) => [
            'user_id' => $id, 'permission' => 'payments.reverse', 'created_at' => $now, 'updated_at' => $now,
        ])->all();
        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('user_permissions')->insertOrIgnore($chunk);
        }
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reversal_of_id');
            $table->dropConstrainedForeignId('reversed_by');
            $table->dropColumn(['reversed_at', 'reverse_reason']);
            $table->dropConstrainedForeignId('refund_rejected_by');
            $table->dropColumn(['refund_rejected_at', 'refund_reject_reason']);
        });

        DB::table('user_permissions')->where('permission', 'payments.reverse')->delete();
    }
};
