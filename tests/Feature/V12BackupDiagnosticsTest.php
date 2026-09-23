<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Services\SystemStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * v12: production'da "Zaxira nusxa: Muammo" holati aniq sababsiz ko'rinar edi (faqat logda).
 * Endi har urinishdan keyin (muvaffaqiyatli/yo'q) natija `.status.json`ga yoziladi va
 * "Tizim holati" sahifasida haqiqiy sabab ko'rsatiladi + sAdmin qo'lda qayta urinib ko'rishi mumkin.
 */
class V12BackupDiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $dir = storage_path('app/backups');
        if (File::isDirectory($dir)) {
            File::cleanDirectory($dir);
        }

        parent::tearDown();
    }

    public function test_missing_mysqldump_gives_actionable_error_and_is_surfaced_on_status_page(): void
    {
        $default = config('database.default');
        config(["database.connections.{$default}.driver" => 'mysql']);

        Process::fake(fn () => Process::result(exitCode: 127, errorOutput: 'command not found'));

        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir);
        File::cleanDirectory($dir);

        $this->artisan('app:backup')->assertExitCode(1);

        $status = json_decode(File::get($dir.'/.status.json'), true);
        $this->assertFalse($status['ok']);
        $this->assertStringContainsString('mysqldump', $status['message']);

        $checks = collect(app(SystemStatusService::class)->checks())->keyBy('key');
        $this->assertSame('critical', $checks['backup']['status']);
        $this->assertStringContainsString('mysqldump', $checks['backup']['detail']);
    }

    public function test_successful_sqlite_backup_records_ok_status(): void
    {
        if (config('database.default') !== 'sqlite') {
            $this->markTestSkipped("Faqat standart ulanish sqlite bo'lganda tekshiriladi.");
        }

        $dbFile = tempnam(sys_get_temp_dir(), 'edunova_test_db_');
        file_put_contents($dbFile, 'FAKE-CONTENT');
        config(['database.connections.sqlite.database' => $dbFile]);

        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir);
        File::cleanDirectory($dir);

        $this->artisan('app:backup')->assertExitCode(0);

        $status = json_decode(File::get($dir.'/.status.json'), true);
        $this->assertTrue($status['ok']);

        $checks = collect(app(SystemStatusService::class)->checks())->keyBy('key');
        $this->assertSame('ok', $checks['backup']['status']);

        @unlink($dbFile);
    }

    public function test_old_failure_is_ignored_once_a_newer_backup_file_exists(): void
    {
        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir);
        File::cleanDirectory($dir);

        File::put($dir.'/.status.json', json_encode(['ok' => false, 'message' => 'Eski xato', 'at' => now()->subDay()->timestamp]));
        File::put($dir.'/backup_'.now()->format('Y-m-d_His').'.sql.gz', gzencode('ok'));

        $checks = collect(app(SystemStatusService::class)->checks())->keyBy('key');
        $this->assertSame('ok', $checks['backup']['status']);
    }

    public function test_sadmin_can_trigger_manual_backup(): void
    {
        if (config('database.default') !== 'sqlite') {
            $this->markTestSkipped("Faqat standart ulanish sqlite bo'lganda tekshiriladi.");
        }

        $sadmin = $this->user(Role::SAdmin);

        $dbFile = tempnam(sys_get_temp_dir(), 'edunova_test_db_');
        file_put_contents($dbFile, 'FAKE-CONTENT');
        config(['database.connections.sqlite.database' => $dbFile]);

        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir);
        File::cleanDirectory($dir);

        $this->actingAs($sadmin)->post(route('system-status.backup'))->assertRedirect();

        $this->assertGreaterThan(0, count(File::glob($dir.'/backup_*.sql.gz')));

        @unlink($dbFile);
    }

    public function test_non_sadmin_cannot_trigger_manual_backup(): void
    {
        $admin = $this->user(Role::Admin);

        $this->actingAs($admin)->post(route('system-status.backup'))->assertForbidden();
    }
}
