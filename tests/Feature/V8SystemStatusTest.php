<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\Wallet;
use App\Services\SystemStatusService;
use App\Support\KnowledgeBase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

/** v8 A3 (avtomatik zaxira) + A4 ("Tizim holati" sahifasi) testlari. */
class V8SystemStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        // Har ehtimolga qarshi - sinov davomida yozilgan zaxira fayllarini tozalash
        $dir = storage_path('app/backups');
        if (File::isDirectory($dir)) {
            File::cleanDirectory($dir);
        }

        parent::tearDown();
    }

    public function test_only_sadmin_can_view_system_status(): void
    {
        $admin = $this->user(Role::Admin, permissions: ['staff.view']);
        $this->actingAs($admin)->get('/system-status')->assertForbidden();

        $sadmin = $this->user(Role::SAdmin);
        $this->actingAs($sadmin)->get('/system-status')->assertOk()->assertViewIs('system-status.index')->assertViewHas('checks');
    }

    public function test_knowledge_base_describes_system_status_only_to_sadmin(): void
    {
        $admin = $this->user(Role::Admin, permissions: ['staff.view']);
        $sadmin = $this->user(Role::SAdmin);

        $forAdmin = KnowledgeBase::forUser($admin);
        $this->assertStringContainsString('Tizim holati', $forAdmin);
        $this->assertStringNotContainsString('Zaxira nusxa har kuni soat 03:00', $forAdmin);

        $forSadmin = KnowledgeBase::forUser($sadmin);
        $this->assertStringContainsString('rejalashtiruvchi (cron) ishlayaptimi', $forSadmin);
    }

    public function test_backup_command_creates_sqlite_gzip_and_prunes_old_copies(): void
    {
        if (config('database.default') !== 'sqlite') {
            $this->markTestSkipped("Faqat standart ulanish sqlite bo'lganda tekshiriladi.");
        }

        $dbFile = tempnam(sys_get_temp_dir(), 'edunova_test_db_');
        file_put_contents($dbFile, 'FAKE-SQLITE-CONTENT-FOR-BACKUP-TEST');
        config(['database.connections.sqlite.database' => $dbFile]);

        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir);
        File::cleanDirectory($dir);

        File::put($dir.'/backup_old.sql.gz', 'x');
        touch($dir.'/backup_old.sql.gz', now()->subDays(20)->getTimestamp());
        File::put($dir.'/backup_recent.sql.gz', 'x');
        touch($dir.'/backup_recent.sql.gz', now()->subDays(2)->getTimestamp());

        $this->artisan('app:backup', ['--keep' => 14])->assertExitCode(0);

        $files = collect(File::files($dir))->keyBy(fn ($f) => $f->getFilename());
        $this->assertFalse($files->has('backup_old.sql.gz'), "14 kundan eski zaxira o'chirilishi kerak edi.");
        $this->assertTrue($files->has('backup_recent.sql.gz'), "14 kundan yangi zaxira saqlanishi kerak edi.");

        $created = $files->except('backup_recent.sql.gz')->first();
        $this->assertNotNull($created, "Yangi zaxira fayli yaratilmadi.");
        $this->assertSame('FAKE-SQLITE-CONTENT-FOR-BACKUP-TEST', gzdecode(File::get($created->getPathname())));

        @unlink($dbFile);
    }

    public function test_backup_command_fails_cleanly_when_sqlite_source_is_missing(): void
    {
        if (config('database.default') !== 'sqlite') {
            $this->markTestSkipped("Faqat standart ulanish sqlite bo'lganda tekshiriladi.");
        }

        config(['database.connections.sqlite.database' => '/no/such/path/db.sqlite']);

        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir);
        File::cleanDirectory($dir);

        $this->artisan('app:backup')->assertExitCode(1);

        $this->assertCount(0, File::files($dir));
    }

    public function test_backup_command_creates_real_mysqldump_when_default_is_mysql_family(): void
    {
        $default = config('database.default');
        if (! in_array(config("database.connections.{$default}.driver"), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped("Faqat standart ulanish MySQL/MariaDB bo'lganda tekshiriladi.");
        }

        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir);
        File::cleanDirectory($dir);

        $this->artisan('app:backup')->assertExitCode(0);

        $files = File::files($dir);
        $this->assertCount(1, $files);

        $dump = gzdecode(File::get($files[0]->getPathname()));
        $this->assertStringContainsString('CREATE TABLE', $dump);
    }

    public function test_backup_command_rejects_unsupported_driver(): void
    {
        // Diqqat: `database.default`ni o'zgartirmaymiz - shunday qilsak RefreshDatabase
        // testdan keyin noto'g'ri ulanishni tozalashga urinib, faol ulanishni "tranzaksiya
        // ichida qolgan" holatda qoldiradi va keyingi testlar buzilib qoladi. Shu sabab
        // faqat FAOL ulanishning driverini vaqtincha almashtiramiz (default o'zgarmaydi).
        $default = config('database.default');
        config(["database.connections.{$default}.driver" => 'oracle']);

        $this->artisan('app:backup')->assertExitCode(1);
    }

    public function test_system_status_flags_missing_cron_and_backup_as_critical(): void
    {
        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir);
        File::cleanDirectory($dir);

        $checks = collect(app(SystemStatusService::class)->checks())->keyBy('key');

        $this->assertSame('critical', $checks['cron']['status']);
        $this->assertSame('critical', $checks['backup']['status']);
    }

    public function test_system_status_ok_when_cron_and_backup_are_fresh(): void
    {
        Cache::put('system.cron_heartbeat', now());

        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir);
        File::cleanDirectory($dir);
        File::put($dir.'/backup_'.now()->format('Y-m-d_His').'.sql.gz', gzencode('ok'));

        $checks = collect(app(SystemStatusService::class)->checks())->keyBy('key');

        $this->assertSame('ok', $checks['cron']['status']);
        $this->assertSame('ok', $checks['backup']['status']);
    }

    /** v10 (4-band): navbat ishchisi (queue worker) alohida tekshiriladi - cron signalidan mustaqil. */
    public function test_system_status_flags_missing_queue_worker_as_critical(): void
    {
        $checks = collect(app(SystemStatusService::class)->checks())->keyBy('key');

        $this->assertSame('critical', $checks['queue']['status']);
    }

    public function test_system_status_ok_when_queue_worker_heartbeat_is_fresh(): void
    {
        Cache::put('system.queue_heartbeat', now());

        $checks = collect(app(SystemStatusService::class)->checks())->keyBy('key');

        $this->assertSame('ok', $checks['queue']['status']);
    }

    public function test_system_status_warns_when_queue_heartbeat_is_stale(): void
    {
        Cache::put('system.queue_heartbeat', now()->subMinutes(30));

        $checks = collect(app(SystemStatusService::class)->checks())->keyBy('key');

        $this->assertSame('warning', $checks['queue']['status']);
    }

    /** Signal faqat worker haqiqatan bajarganda yangilanadi - navbatga qo'yilishining o'zi yetarli emas. */
    public function test_queue_heartbeat_job_updates_cache_when_actually_run(): void
    {
        $this->assertNull(Cache::get('system.queue_heartbeat'));

        (new \App\Jobs\QueueHeartbeatJob())->handle();

        $this->assertNotNull(Cache::get('system.queue_heartbeat'));
    }

    public function test_system_status_flags_stale_backup_as_critical(): void
    {
        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir);
        File::cleanDirectory($dir);
        $old = $dir.'/backup_'.now()->subDays(3)->format('Y-m-d_His').'.sql.gz';
        File::put($old, gzencode('old'));
        touch($old, now()->subHours(40)->getTimestamp());

        $checks = collect(app(SystemStatusService::class)->checks())->keyBy('key');

        $this->assertSame('critical', $checks['backup']['status']);
    }

    public function test_system_status_flags_negative_wallet_as_critical(): void
    {
        $branch = $this->branch();
        $this->fund($branch, Wallet::TillCash, -5000);

        $checks = collect(app(SystemStatusService::class)->checks())->keyBy('key');

        $this->assertSame('critical', $checks['wallets']['status']);
    }

    public function test_system_status_ok_wallets_when_none_negative(): void
    {
        $branch = $this->branch();
        $this->fund($branch, Wallet::TillCash, 100000);

        $checks = collect(app(SystemStatusService::class)->checks())->keyBy('key');

        $this->assertSame('ok', $checks['wallets']['status']);
    }

    public function test_system_status_flags_failed_jobs(): void
    {
        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'sync',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'Test xatolik',
        ]);

        $checks = collect(app(SystemStatusService::class)->checks())->keyBy('key');

        $this->assertSame('warning', $checks['jobs']['status']);
    }

    public function test_system_status_migrations_ok_by_default(): void
    {
        $checks = collect(app(SystemStatusService::class)->checks())->keyBy('key');

        $this->assertSame('ok', $checks['migrations']['status']);
    }

    public function test_system_status_flags_debug_true_in_production_as_critical(): void
    {
        config(['app.env' => 'production', 'app.debug' => true]);

        $checks = collect(app(SystemStatusService::class)->checks())->keyBy('key');

        $this->assertSame('critical', $checks['debug']['status']);
    }

    public function test_system_status_debug_ok_outside_production(): void
    {
        config(['app.env' => 'testing', 'app.debug' => true]);

        $checks = collect(app(SystemStatusService::class)->checks())->keyBy('key');

        $this->assertSame('ok', $checks['debug']['status']);
    }
}
