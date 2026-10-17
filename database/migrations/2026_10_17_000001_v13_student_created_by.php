<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * v13: o'quvchini kim ro'yxatga olgani - `users.created_by` va `users.registered_via`.
 * Additive: mavjud ma'lumot o'chirilmaydi. Eski o'quvchilar uchun jurnaldan (`student.created`)
 * best-effort ko'chiriladi; topilmaganlari bo'sh qoladi.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'created_by')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('registered_via', 20)->nullable();
            });
        }

        // Eng birinchi 'student.created' yozuvi (bir o'quvchiga bittadan ortiq bo'lsa ham) - kim yaratgan.
        $logs = DB::table('audit_logs')
            ->where('action', 'student.created')
            ->whereNotNull('subject_id')
            ->whereNotNull('user_id')
            ->orderBy('id')
            ->get(['subject_id', 'user_id']);

        $users = array_flip(DB::table('users')->pluck('id')->all());
        $seen = [];
        foreach ($logs as $log) {
            if (isset($seen[$log->subject_id]) || ! isset($users[$log->user_id])) {
                continue;
            }
            $seen[$log->subject_id] = true;
            DB::table('users')
                ->where('id', $log->subject_id)
                ->where('role', 'student')
                ->whereNull('created_by')
                ->update(['created_by' => $log->user_id, 'registered_via' => 'manual']);
        }
    }

    public function down(): void
    {
        // Additive migratsiya: ma'lumot saqlanadi, ustunlar qaytarilmaydi.
    }
};
