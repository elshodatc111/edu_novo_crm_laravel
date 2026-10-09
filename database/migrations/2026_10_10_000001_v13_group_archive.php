<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * v13: boshlanmagan guruhni ARXIVLASH (soft delete) - `groups.deleted_at`, kim va nima sababdan arxivlagani.
 * Additive: mavjud ma'lumotga tegmaydi. Mavjud adminlarga `groups.delete` ruxsati bir marta beriladi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->softDeletes();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('delete_reason', 255)->nullable();
        });

        $now = now();
        $rows = DB::table('users')->where('role', 'admin')->pluck('id')->map(fn ($id) => [
            'user_id' => $id, 'permission' => 'groups.delete', 'created_at' => $now, 'updated_at' => $now,
        ])->all();

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('user_permissions')->insertOrIgnore($chunk);
        }
    }

    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deleted_by');
            $table->dropColumn(['delete_reason', 'deleted_at']);
        });
    }
};
