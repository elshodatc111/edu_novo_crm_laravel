<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\BalanceTransaction;
use App\Models\Branch;
use App\Models\ImportBatch;
use App\Models\LeadSource;
use App\Models\User;
use App\Support\BranchContext;
use App\Support\Format;
use App\Support\Xlsx\XlsxReader;
use App\Support\Xlsx\XlsxWriter;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * O'quvchilarni Excel/CSV dan import qilish. Ikki bosqich: 1) tekshiruv va ko'rib chiqish, 2) tasdiqlash.
 * sAdmin faylda "Filial" ustuni orqali yangi filiallarni ham ochishi mumkin (tasdiqdan keyin).
 */
class ImportService
{
    private const ALIASES = [
        'name' => ['fio', 'ism', 'ismfamiliya', 'ismfamilya', 'familiyaism', 'oquvchi', 'name', 'fullname'],
        'phone' => ['telefon', 'tel', 'telefonraqam', 'telefonraqami', 'phone', 'raqam', 'telefon1'],
        'phone2' => ['qoshimchatelefon', 'telefon2', 'phone2', 'otaonatelefoni'],
        'birthday' => ['tugilgansana', 'tugilgankun', 'tugilgan', 'birthday', 'sana'],
        'address' => ['manzil', 'address'],
        'source' => ['manba', 'source', 'qayerdaneshitdi'],
        'balance' => ['balans', 'balance', 'qoldiq'],
        'about' => ['eslatma', 'izoh', 'about', 'comment'],
        'branch' => ['filial', 'branch', 'markaz'],
    ];

    public function __construct(private StudentService $students, private BalanceService $balance) {}

    /** Namuna fayl (bo'sh shablon). */
    public function template(bool $withBranch): string
    {
        $headers = ["F.I.O", 'Telefon', "Qo'shimcha telefon", "Tug'ilgan sana", 'Manzil', 'Manba', 'Balans', 'Eslatma'];
        $row1 = ['Aliyev Vali', '90 123 45 67', '91 765 43 21', '15.03.2008', 'Samarqand sh.', 'Telegram', 0, ''];
        $row2 = ['Karimova Sara', '93 555 66 77', '', '2007-11-02', '', 'Tanishlar', -150000, 'Qarzi bor'];

        if ($withBranch) {
            $headers[] = 'Filial';
            $row1[] = 'Samarqand';
            $row2[] = 'Samarqand';
        }

        return XlsxWriter::write("O'quvchilar", $headers, [$row1, $row2]);
    }

    public function preview(User $actor, UploadedFile $file): ImportBatch
    {
        $rows = XlsxReader::read($file->getRealPath(), $file->getClientOriginalExtension());
        $header = array_shift($rows);
        $map = $this->mapHeader($header ?? []);

        if (! isset($map['name'], $map['phone'])) {
            throw ValidationException::withMessages(['file' => "Faylning birinchi qatorida «F.I.O» va «Telefon» ustunlari bo'lishi kerak. Namuna faylni yuklab oling."]);
        }
        if (! $rows) {
            throw ValidationException::withMessages(['file' => "Faylda ma'lumot qatorlari yo'q."]);
        }

        $parsed = $this->parseRows($actor, $rows, $map);
        $summary = $this->summarize($parsed);

        return ImportBatch::create([
            'user_id' => $actor->id, 'status' => 'pending', 'filename' => $file->getClientOriginalName(),
            'summary' => $summary, 'rows' => $parsed,
        ]);
    }

    /**
     * Tasdiqlash: yangi filiallar ochiladi, o'quvchilar yaratiladi. Natija (login/parol) Excel faylida saqlanadi.
     *
     * @return array<string,int>
     */
    public function confirm(ImportBatch $batch, User $actor): array
    {
        return DB::transaction(function () use ($batch, $actor) {
            $locked = ImportBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages(['batch' => "Bu import allaqachon bajarilgan yoki bekor qilingan."]);
            }

            $branchIds = [];
            $createdBranches = 0;
            $created = 0;
            $credentials = [];
            $sourceCache = [];

            foreach ($locked->rows as $r) {
                if ($r['status'] !== 'ok') {
                    continue;
                }

                $branchId = $r['branch_id'];
                if (! $branchId) {
                    $key = Str::lower($r['branch_name']);
                    if (! isset($branchIds[$key])) {
                        abort_unless($actor->isSuperAdmin(), 403);
                        $branch = Branch::create(['name' => $r['branch_name'], 'code' => Branch::makeCode($r['branch_name']), 'status' => 'active', 'opened_at' => now()]);
                        AuditLog::record('branch.created', $branch, "Import orqali yangi filial ochildi: {$branch->name}");
                        $branchIds[$key] = $branch->id;
                        $createdBranches++;
                    }
                    $branchId = $branchIds[$key];
                }

                $sourceId = null;
                if ($r['source']) {
                    $ck = $branchId.'|'.Str::lower($r['source']);
                    $sourceId = $sourceCache[$ck] ??= (LeadSource::withoutGlobalScopes()->where('branch_id', $branchId)->whereRaw('lower(name) = ?', [Str::lower($r['source'])])->value('id')
                        ?? LeadSource::create(['branch_id' => $branchId, 'name' => $r['source']])->id);
                }

                [$student, $password] = $this->students->create([
                    'branch_id' => $branchId, 'name' => $r['name'], 'phone' => $r['phone'], 'phone2' => $r['phone2'],
                    'birthday' => $r['birthday'], 'address' => $r['address'], 'about' => $r['about'], 'lead_source_id' => $sourceId,
                ], $actor, notify: false);

                if ($r['balance'] !== 0) {
                    $this->balance->post($student, $r['balance'], BalanceTransaction::OPENING, null, "Import: boshlang'ich balans", $actor);
                }

                $credentials[] = [$r['branch_name'], $student->name, $student->username, $password];
                $created++;
            }

            $path = null;
            if ($credentials) {
                $tmp = XlsxWriter::write('Login va parollar', ['Filial', "O'quvchi", 'Login', 'Parol'], $credentials, "Import natijasi — parollarni o'quvchilarga bering va faylni o'chiring");
                $path = "imports/{$locked->id}.xlsx";
                Storage::disk('local')->put($path, file_get_contents($tmp));
                @unlink($tmp);
            }

            $locked->update(['status' => 'done', 'result_path' => $path, 'summary' => $locked->summary + ['created' => $created, 'created_branches' => $createdBranches]]);
            AuditLog::record('students.imported', null, "Excel import: {$created} ta o'quvchi, {$createdBranches} ta yangi filial ({$locked->filename})", branchId: BranchContext::id());

            return ['created' => $created, 'created_branches' => $createdBranches];
        });
    }

    public function cancel(ImportBatch $batch): void
    {
        if ($batch->status === 'pending') {
            $batch->update(['status' => 'cancelled', 'rows' => []]);
        }
    }

    /** @return array<string,int> ustun nomi => indeks */
    private function mapHeader(array $header): array
    {
        $map = [];
        foreach ($header as $i => $h) {
            $norm = preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii(str_replace(["ʻ", "ʼ", "’", "'", "`"], '', (string) $h))));
            foreach (self::ALIASES as $field => $aliases) {
                if (! isset($map[$field]) && in_array($norm, $aliases, true)) {
                    $map[$field] = $i;
                }
            }
        }

        return $map;
    }

    private function parseRows(User $actor, array $rows, array $map): array
    {
        $branches = Branch::all()->keyBy(fn ($b) => Str::lower($b->name));
        $contextBranch = BranchContext::id() ? Branch::find(BranchContext::id()) : null;

        $parsed = [];
        foreach ($rows as $i => $row) {
            $get = fn (string $f) => isset($map[$f]) ? trim((string) ($row[$map[$f]] ?? '')) : '';
            $errors = [];

            // Filial
            $branchName = $get('branch');
            $branchId = null;
            if (! $actor->isSuperAdmin()) {
                $own = $actor->branch;
                if ($branchName !== '' && Str::lower($branchName) !== Str::lower($own->name)) {
                    $errors[] = "Boshqa filial ko'rsatilgan ({$branchName})";
                }
                $branchId = $own->id;
                $branchName = $own->name;
            } elseif ($branchName !== '') {
                $existing = $branches->get(Str::lower($branchName));
                $branchId = $existing?->id;
            } elseif ($contextBranch) {
                $branchId = $contextBranch->id;
                $branchName = $contextBranch->name;
            } else {
                $errors[] = "Filial ko'rsatilmagan (faylda «Filial» ustuni yoki yuqoridan filial tanlang)";
            }

            $name = $get('name');
            $phone = Format::canonicalPhone($get('phone'));
            $birthday = $this->parseDate($get('birthday'));
            $balanceRaw = str_replace([' ', "\u{00A0}", ','], ['', '', '.'], $get('balance'));

            if ($name === '') {
                $errors[] = 'F.I.O bo\'sh';
            }
            if (! $phone) {
                $errors[] = "Telefon noto'g'ri (+998 90 123 4567 ko'rinishiga keltirib bo'lmadi)";
            }
            if ($get('birthday') !== '' && $birthday === false) {
                $errors[] = "Tug'ilgan sana noto'g'ri";
            }
            if ($balanceRaw !== '' && ! is_numeric($balanceRaw)) {
                $errors[] = "Balans son bo'lishi kerak";
            }

            $parsed[] = [
                'row' => $i + 2, 'branch_id' => $branchId, 'branch_name' => $branchName, 'is_new_branch' => $branchName !== '' && ! $branchId && $actor->isSuperAdmin(),
                'name' => mb_strtoupper($name), 'phone' => $phone ?? $get('phone'), 'phone2' => $get('phone2') ? (Format::canonicalPhone($get('phone2')) ?? $get('phone2')) : null,
                'birthday' => $birthday ?: null, 'address' => $get('address') ?: null, 'source' => $get('source') ?: null,
                'balance' => $balanceRaw !== '' && is_numeric($balanceRaw) ? (int) round((float) $balanceRaw) : 0, 'about' => $get('about') ?: null,
                'status' => $errors ? 'error' : 'ok', 'message' => implode('; ', $errors),
            ];
        }

        return $this->markDuplicates($parsed);
    }

    /** Bazada yoki faylning o'zida bir xil (filial + telefon) bo'lganlarni belgilaydi. */
    private function markDuplicates(array $parsed): array
    {
        $branchIds = collect($parsed)->pluck('branch_id')->filter()->unique()->all();
        $existing = [];
        if ($branchIds) {
            foreach (User::where('role', 'student')->whereIn('branch_id', $branchIds)->whereNotNull('phone')->get(['branch_id', 'phone']) as $u) {
                $existing[$u->branch_id.'|'.Format::canonicalPhone($u->phone)] = true;
            }
        }

        $seen = [];
        foreach ($parsed as &$r) {
            if ($r['status'] !== 'ok') {
                continue;
            }
            $key = ($r['branch_id'] ?: 'new:'.Str::lower($r['branch_name'])).'|'.$r['phone'];

            if ($r['branch_id'] && isset($existing[$key])) {
                $r['status'] = 'duplicate';
                $r['message'] = "Bu telefon raqami bilan o'quvchi bazada bor — o'tkazib yuboriladi";
            } elseif (isset($seen[$key])) {
                $r['status'] = 'duplicate';
                $r['message'] = "Bu telefon raqami faylda takrorlangan — o'tkazib yuboriladi";
            }
            $seen[$key] = true;
        }

        return $parsed;
    }

    /** @return string|false|null Y-m-d, null (bo'sh) yoki false (noto'g'ri) */
    private function parseDate(string $value): string|false|null
    {
        if ($value === '') {
            return null;
        }

        try {
            if (is_numeric($value) && (float) $value > 1 && (float) $value < 80000) {
                return Carbon::create(1899, 12, 30)->addDays((int) $value)->toDateString();   // Excel sana raqami
            }
            if (preg_match('/^(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{4})$/', $value, $m)) {
                return Carbon::createFromDate((int) $m[3], (int) $m[2], (int) $m[1])->toDateString();
            }

            $date = Carbon::parse($value);

            return $date->year >= 1930 && $date->lte(today()) ? $date->toDateString() : false;
        } catch (\Throwable) {
            return false;
        }
    }

    private function summarize(array $parsed): array
    {
        $ok = collect($parsed)->where('status', 'ok');
        $byBranch = $ok->groupBy('branch_name')->map->count()->all();
        $newBranches = $ok->where('is_new_branch', true)->groupBy('branch_name')->map->count()->all();

        return [
            'total' => count($parsed),
            'ok' => $ok->count(),
            'duplicates' => collect($parsed)->where('status', 'duplicate')->count(),
            'errors' => collect($parsed)->where('status', 'error')->count(),
            'by_branch' => $byBranch,
            'new_branches' => $newBranches,
            'new_sources' => $ok->pluck('source')->filter()->unique()->values()->all(),
            'opening_balance' => (int) $ok->sum('balance'),
        ];
    }
}
