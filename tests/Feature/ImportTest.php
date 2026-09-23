<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\ImportBatch;
use App\Models\LeadSource;
use App\Models\User;
use App\Support\Xlsx\XlsxReader;
use App\Support\Xlsx\XlsxWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function xlsx(array $headers, array $rows): UploadedFile
    {
        $path = XlsxWriter::write('X', $headers, $rows);

        return new UploadedFile($path, 'oquvchilar.xlsx', null, null, true);
    }

    private function sadmin(): User
    {
        return $this->user(Role::SAdmin);
    }

    public function test_sadmin_preview_shows_new_branches_and_confirm_creates_everything(): void
    {
        $existing = $this->branch('Toshkent');
        $this->student($existing, ['name' => 'Mavjud Odam', 'phone' => '+998 90 111 2233']);
        $sadmin = $this->sadmin();

        $file = $this->xlsx(['F.I.O', 'Telefon', "Tug'ilgan sana", 'Manba', 'Balans', 'Filial'], [
            ['Ali Valiyev', '90 123 45 67', '15.03.2008', 'Telegram', '-150 000', 'Samarqand'],     // yangi filial
            ['Sara Karimova', '93 555 66 77', '2007-11-02', 'Telegram', 0, 'Samarqand'],
            ['Mavjud Odam', '90 111 22 33', '', '', 0, 'Toshkent'],                                     // bazada bor
            ['Ali Valiyev', '901234567', '', '', 0, 'Samarqand'],                                       // faylda takror
            ['', '90 999 88 77', '', '', 0, 'Toshkent'],                                                // ism yo'q
            ['Xato Telefon', '123', '', '', 0, 'Toshkent'],
            ['Yangi Toshkent', '97 777 66 55', '', 'Instagram', 250000, 'toshkent'],                    // katta-kichik harf farqi
        ]);

        $response = $this->actingAs($sadmin)->post('/imports', ['file' => $file]);
        $batch = ImportBatch::firstOrFail();
        $response->assertRedirect(route('imports.show', $batch));

        $s = $batch->summary;
        $this->assertSame(7, $s['total']);
        $this->assertSame(3, $s['ok']);
        $this->assertSame(2, $s['duplicates']);
        $this->assertSame(2, $s['errors']);
        $this->assertSame(['Samarqand' => 2], $s['new_branches']);
        $this->assertSame(-150000 + 250000, $s['opening_balance']);

        $this->actingAs($sadmin)->get("/imports/{$batch->id}")->assertOk()->assertSee('Yangi filiallar ochiladi', false)->assertSee('Samarqand')->assertSee('2 ta o');
        $this->assertSame(0, Branch::where('name', 'Samarqand')->count());   // tasdiqlanmaguncha yaratilmaydi

        $this->actingAs($sadmin)->post("/imports/{$batch->id}/confirm")->assertRedirect();

        $samarqand = Branch::where('name', 'Samarqand')->firstOrFail();
        $this->assertTrue($samarqand->isActive());
        $ali = User::where('branch_id', $samarqand->id)->where('name', 'ALI VALIYEV')->firstOrFail();
        $this->assertSame('998901234567', $ali->username);
        $this->assertSame('2008-03-15', $ali->birthday->toDateString());
        $this->assertSame(-150000, $ali->balance);
        $this->assertSame('Telegram', $ali->leadSource->name);
        $this->assertDatabaseHas('balance_transactions', ['student_id' => $ali->id, 'type' => 'opening', 'amount' => -150000]);
        $this->assertSame(2, User::where('branch_id', $samarqand->id)->where('role', 'student')->count());
        $this->assertSame(1, LeadSource::where('branch_id', $samarqand->id)->count());        // manba bir marta yaratilgan

        $new = User::where('name', 'YANGI TOSHKENT')->firstOrFail();
        $this->assertSame($existing->id, $new->branch_id);                                     // "toshkent" mavjud filialga bog'landi
        $this->assertSame(1, Branch::where('name', 'like', 'Toshkent')->count());

        // Ikkinchi marta tasdiqlab bo'lmaydi
        $this->actingAs($sadmin)->post("/imports/{$batch->id}/confirm")->assertSessionHasErrors('batch');
        $this->assertSame(3, User::where('role', 'student')->where('name', '!=', 'Mavjud Odam')->count());
    }

    public function test_credentials_file_downloads_once_and_contains_working_passwords(): void
    {
        $sadmin = $this->sadmin();
        $branch = $this->branch('Toshkent');
        $file = $this->xlsx(['F.I.O', 'Telefon', 'Filial'], [['Ali', '901234567', 'Toshkent']]);

        $this->actingAs($sadmin)->post('/imports', ['file' => $file]);
        $batch = ImportBatch::firstOrFail();
        $this->actingAs($sadmin)->post("/imports/{$batch->id}/confirm");

        $response = $this->actingAs($sadmin)->get("/imports/{$batch->id}/result")->assertOk();
        $tmp = tempnam(sys_get_temp_dir(), 'c').'.xlsx';
        file_put_contents($tmp, $response->getContent());
        $rows = XlsxReader::read($tmp, 'xlsx');
        unlink($tmp);

        $this->assertSame(['Toshkent', 'ALI', '998901234567'], array_slice($rows[2], 0, 3));
        $this->postJson('/api/v1/auth/login', ['login' => '998901234567', 'password' => $rows[2][3]])->assertOk();

        // Ikkinchi marta yuklab bo'lmaydi
        $this->actingAs($sadmin)->get("/imports/{$batch->id}/result")->assertNotFound();
    }

    public function test_admin_imports_only_into_own_branch(): void
    {
        $branch = $this->branch('Toshkent');
        $this->branch('Boshqa');
        $admin = $this->user(Role::Admin, $branch, ['students.import', 'students.view']);

        $file = $this->xlsx(['F.I.O', 'Telefon', 'Filial'], [
            ['Ali', '901234567', ''],
            ['Vali', '902345678', 'Toshkent'],
            ['Hack', '903456789', 'Boshqa'],
            ['Yangi', '904567890', 'Namangan'],
        ]);
        $this->actingAs($admin)->post('/imports', ['file' => $file]);
        $batch = ImportBatch::firstOrFail();

        $this->assertSame(2, $batch->summary['ok']);
        $this->assertSame(2, $batch->summary['errors']);
        $this->assertSame([], $batch->summary['new_branches']);   // admin filial ocha olmaydi

        $this->actingAs($admin)->post("/imports/{$batch->id}/confirm");
        $this->assertSame(2, User::where('role', 'student')->where('branch_id', $branch->id)->count());
        $this->assertSame(0, Branch::where('name', 'Namangan')->count());
    }

    public function test_sadmin_needs_branch_when_column_missing(): void
    {
        $branch = $this->branch('Toshkent');
        $sadmin = $this->sadmin();
        $file = $this->xlsx(['F.I.O', 'Telefon'], [['Ali', '901234567']]);

        $this->actingAs($sadmin)->post('/imports', ['file' => $file]);
        $this->assertSame(1, ImportBatch::firstOrFail()->summary['errors']);

        ImportBatch::query()->delete();
        $file2 = $this->xlsx(['F.I.O', 'Telefon'], [['Ali', '901234567']]);
        $this->actingAs($sadmin)->withSession(['current_branch_id' => $branch->id])->post('/imports', ['file' => $file2]);
        $this->assertSame(1, ImportBatch::firstOrFail()->summary['ok']);
    }

    public function test_file_validation_template_csv_and_access_control(): void
    {
        $branch = $this->branch('Toshkent');
        $admin = $this->user(Role::Admin, $branch, ['students.import']);
        $other = $this->user(Role::Admin, $branch, ['students.import']);

        // Majburiy ustunlar yo'q
        $this->actingAs($admin)->post('/imports', ['file' => $this->xlsx(['Nimadir', 'Boshqa'], [['a', 'b']])])->assertSessionHasErrors('file');
        // Bo'sh fayl
        $this->actingAs($admin)->post('/imports', ['file' => $this->xlsx(['F.I.O', 'Telefon'], [])])->assertSessionHasErrors('file');

        // CSV (nuqtali vergul, BOM)
        $csv = tempnam(sys_get_temp_dir(), 'c').'.csv';
        file_put_contents($csv, "\xEF\xBB\xBFIsm familiya;Tel;Balans\nAli Aka;90 123 45 67;100 000\n");
        $this->actingAs($admin)->post('/imports', ['file' => new UploadedFile($csv, 'a.csv', null, null, true)])->assertRedirect();
        $batch = ImportBatch::firstOrFail();
        $this->assertSame(1, $batch->summary['ok']);
        $this->assertSame(100000, $batch->rows[0]['balance']);

        // Boshqa foydalanuvchining importiga kira olmaydi
        $this->actingAs($other)->get("/imports/{$batch->id}")->assertNotFound();
        $this->actingAs($other)->post("/imports/{$batch->id}/confirm")->assertNotFound();

        // Ruxsatsiz
        $manager = $this->user(Role::Manager, $branch, ['students.create']);
        $this->actingAs($manager)->get('/imports')->assertForbidden();

        // Namuna va bekor qilish
        $this->actingAs($admin)->get('/imports/template')->assertOk();
        $this->actingAs($admin)->post("/imports/{$batch->id}/cancel")->assertRedirect();
        $this->assertSame('cancelled', $batch->fresh()->status);
        $this->actingAs($admin)->post("/imports/{$batch->id}/confirm")->assertSessionHasErrors('batch');
    }

    public function test_excel_serial_dates_are_understood(): void
    {
        $branch = $this->branch('Toshkent');
        $admin = $this->user(Role::Admin, $branch, ['students.import']);
        // 39522 = 2008-03-15 (Excel sana raqami)
        $this->actingAs($admin)->post('/imports', ['file' => $this->xlsx(['F.I.O', 'Telefon', "Tug'ilgan sana"], [['Ali', '901234567', 39522]])]);

        $this->assertSame('2008-03-15', ImportBatch::firstOrFail()->rows[0]['birthday']);
    }
}
