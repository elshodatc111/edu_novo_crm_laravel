<?php

use App\Support\Format;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            // Oldindan to'lov chegirmasi muddati: guruh boshlanishidan necha kun OLDIN va necha kun KEYIN
            $table->unsignedSmallInteger('discount_days_before')->default(30)->after('charity_percent');
            $table->unsignedSmallInteger('discount_days_after')->default(3)->after('discount_days_before');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->json('ai_analysis')->nullable();
            $table->timestamp('ai_analyzed_at')->nullable();
        });

        Schema::table('lead_notes', function (Blueprint $table) {
            $table->string('type', 12)->default('note');   // note | ai
        });

        Schema::table('ai_chats', function (Blueprint $table) {
            $table->string('kind', 12)->default('analytics');   // analytics | help
        });

        $this->normalizePhones();

        Schema::table('users', function (Blueprint $table) {
            // Bir filialda bir rolda telefon takrorlanmaydi (NULL lar ta'sir qilmaydi)
            $table->unique(['branch_id', 'role', 'phone'], 'users_branch_role_phone_unique');
        });

        // Mavjud adminlarga kassa tarixini ko'rish ruxsati beriladi
        $now = now();
        $rows = DB::table('users')->where('role', 'admin')->pluck('id')->map(fn ($id) => [
            'user_id' => $id, 'permission' => 'cashbox.history', 'created_at' => $now, 'updated_at' => $now,
        ])->all();
        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('user_permissions')->insertOrIgnore($chunk);
        }
    }

    /** Telefonlarni +998 90 123 4567 ko'rinishiga keltiradi, takrorlanganlarini bo'shatadi (jurnalga yozadi). */
    private function normalizePhones(): void
    {
        $invalid = [];

        foreach (DB::table('users')->whereNotNull('phone')->select('id', 'phone', 'phone2', 'role')->orderBy('id')->cursor() as $u) {
            $canonical = Format::canonicalPhone($u->phone);
            if (! $canonical && $u->role !== 'sadmin') {
                $invalid[] = "#{$u->id} ({$u->phone})";
            }
            DB::table('users')->where('id', $u->id)->update([
                'phone' => $canonical,
                'phone2' => $u->phone2 ? (Format::canonicalPhone($u->phone2) ?? $u->phone2) : null,
            ]);
        }

        foreach (DB::table('leads')->select('id', 'phone')->cursor() as $l) {
            DB::table('leads')->where('id', $l->id)->update(['phone' => Format::canonicalPhone($l->phone) ?? $l->phone]);
        }

        $duplicates = DB::table('users')->whereNotNull('phone')->whereNotNull('branch_id')
            ->select('branch_id', 'role', 'phone', DB::raw('min(id) as keep_id'), DB::raw('count(*) as c'))
            ->groupBy('branch_id', 'role', 'phone')->having(DB::raw('count(*)'), '>', 1)->get();

        $cleared = [];
        foreach ($duplicates as $d) {
            $ids = DB::table('users')->where('branch_id', $d->branch_id)->where('role', $d->role)->where('phone', $d->phone)->where('id', '!=', $d->keep_id)->pluck('id')->all();
            DB::table('users')->whereIn('id', $ids)->update(['phone' => null]);
            $cleared[] = "{$d->phone} ({$d->role}, filial {$d->branch_id}): qoldi #{$d->keep_id}, raqami bo'shatildi #".implode(', #', $ids);
        }

        if ($invalid || $cleared) {
            Log::warning('Telefon migratsiyasi: raqamni qo\'lda tuzatish kerak bo\'lgan foydalanuvchilar', ['noto\'g\'ri raqam' => $invalid, 'takrorlangan' => $cleared]);
        }
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropUnique('users_branch_role_phone_unique'));
        Schema::table('ai_chats', fn (Blueprint $t) => $t->dropColumn('kind'));
        Schema::table('lead_notes', fn (Blueprint $t) => $t->dropColumn('type'));
        Schema::table('leads', fn (Blueprint $t) => $t->dropColumn(['ai_analysis', 'ai_analyzed_at']));
        Schema::table('branches', fn (Blueprint $t) => $t->dropColumn(['discount_days_before', 'discount_days_after']));
    }
};
