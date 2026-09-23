<?php

namespace Database\Seeders;

use App\Enums\PayMethod;
use App\Enums\Role;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Branch;
use App\Models\CashRequest;
use App\Models\Course;
use App\Models\DiscountCampaign;
use App\Models\Group;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\LeadSource;
use App\Models\LessonTime;
use App\Models\PricePlan;
use App\Models\Room;
use App\Models\User;
use App\Services\CashboxService;
use App\Services\EnrollmentService;
use App\Services\GroupService;
use App\Services\HolidayService;
use App\Services\PaymentService;
use App\Services\PayrollService;
use App\Services\StudentService;
use App\Support\Format;
use App\Support\PermissionRegistry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Sinov ma'lumotlari: 2 ta filial (Test filiali, Qarshi filiali), har birida admin, 2 menejer,
 * 2 o'qituvchi, ~15 o'quvchi, guruhlar (tugagan/davom etayotgan/yangi boshlanadigan), to'lovlar,
 * qarzdorlar, davomad, murojaatlar, kassa va ish haqi.
 *
 *   php artisan db:seed --class=DemoSeeder
 *
 * Barcha demo foydalanuvchilar paroli: demo12345. HAQIQIY serverda ishlatmang.
 */
class DemoSeeder extends Seeder
{
    private const PASSWORD = 'demo12345';

    private const BRANCHES = [
        ['Test filiali', 'test', '90'], ['Qarshi filiali', 'qarshi', '91'],
    ];

    private const MALE = ['Aziz', 'Bobur', 'Jasur', 'Sardor', 'Otabek', 'Dilshod', 'Rustam', 'Shohruh', 'Umid', 'Behruz', 'Anvar', 'Sanjar', 'Farhod', 'Ulugbek', 'Jahongir', 'Nodir', 'Eldor', 'Islom', 'Murod', 'Abbos'];
    private const FEMALE = ['Malika', 'Dilnoza', 'Zilola', 'Nigora', 'Madina', 'Sevara', 'Gulnora', 'Feruza', 'Kamola', 'Shahnoza', 'Sarvinoz', 'Nilufar', 'Mohira', 'Lola', 'Barno', 'Iroda', 'Munisa', 'Zarina', 'Sabina', 'Ozoda'];
    private const SURNAMES = ['Karimov', 'Rahimov', 'Aliyev', 'Yusupov', 'Nazarov', 'Ismoilov', 'Tursunov', 'Abdullayev', 'Qodirov', 'Sobirov', 'Hamidov', 'Mirzayev', 'Ergashev', 'Saidov', 'Umarov', 'Xolmatov', 'Norboyev', 'Jurayev', 'Ganiyev', 'Sharipov'];

    private int $phoneSeq = 0;
    private int $nameSeq = 0;
    private string $code = '90';

    public function run(): void
    {
        mt_srand(2026);   // har safar bir xil ma'lumotlar
        $summary = [];

        foreach (self::BRANCHES as $i => [$name, $slug, $code]) {
            if (Branch::where('code', $slug)->exists()) {
                $this->command?->warn("«{$name}» filiali allaqachon bor, o'tkazib yuborildi.");

                continue;
            }

            $this->code = $code;
            $summary[] = $this->seedBranch($name, $slug, $i);
        }

        Auth::logout();

        if ($summary) {
            $this->command?->info("Demo ma'lumotlar tayyor. Barcha parollar: ".self::PASSWORD);
            $this->command?->table(['Filial', 'Admin', 'Menejerlar', "O'qituvchi", "O'quvchilar", 'Guruhlar', 'Murojaatlar'], $summary);
        }
    }

    private function seedBranch(string $name, string $slug, int $index): array
    {
        $branch = Branch::create(['name' => $name, 'code' => $slug, 'phone' => $this->phone(), 'address' => "{$name} sh., Markaziy ko'cha", 'status' => 'active', 'opened_at' => now()->subMonths(6), 'charity_percent' => 5]);

        $admin = $this->staff($branch, Role::Admin, "{$slug}.admin", "{$name} Admin");
        $managers = [$this->staff($branch, Role::Manager, "{$slug}.menejer1", "{$name} Menejer 1"), $this->staff($branch, Role::Manager, "{$slug}.menejer2", "{$name} Menejer 2")];
        $teachers = [];
        foreach (range(1, 2) as $n) {
            $teachers[] = $this->staff($branch, Role::Teacher, "{$slug}.ustoz{$n}", $this->fullName());
        }

        Auth::login($admin);
        $cat = $this->catalog($branch);

        // Guruhlar: tugagan, davom etayotgan va yangi boshlanadigan (kamroq ma'lumot uchun - har birida bittadan)
        $groupService = app(GroupService::class);
        $make = fn (string $nm, int $offsetDays, int $lessons, string $sch, int $t, int $room, int $time, int $plan, int $course) => $groupService->create([
            'name' => "{$nm}", 'course_id' => $cat['courses'][$course]->id, 'teacher_id' => $teachers[$t]->id, 'room_id' => $cat['rooms'][$room]->id,
            'lesson_time_id' => $cat['times'][$time]->id, 'price_plan_id' => $cat['plans'][$plan]->id, 'schedule' => $sch,
            'starts_on' => today()->addDays($offsetDays)->toDateString(), 'lesson_count' => $lessons, 'teacher_rate' => 60000, 'teacher_bonus_rate' => 20000,
        ], $admin);

        $groups = [
            'finished' => $make('A1 TOQ 09:00', -20, 8, 'odd', 0, 0, 0, 0, 0),
            'active' => $make('A2 JUFT 11:00', -8, 12, 'even', 1, 1, 1, 0, 0),
            'upcoming' => $make('B1 TOQ 16:00', 8, 8, 'odd', 0, 2, 2, 0, 0),
        ];

        // O'quvchilar (kamroq ma'lumot uchun - 15 ta)
        $students = [];
        for ($n = 0; $n < 15; $n++) {
            $students[] = $this->student($branch, $cat['sources'], $admin, $n);
        }

        // Guruhlarga taqsimlash: to'lov -> qo'shish (qarzi bor o'quvchi qo'shilmaydi)
        $pay = app(PaymentService::class);
        $enroll = app(EnrollmentService::class);
        $layout = ['finished' => [0, 4], 'active' => [4, 10], 'upcoming' => [10, 14]];
        foreach ($layout as $key => [$from, $to]) {
            $group = $groups[$key];
            foreach (array_slice($students, $from, $to - $from) as $k => $student) {
                $student->refresh();
                $prepaid = $key === 'upcoming' ? $group->price - $group->early_discount : ($k % 5 === 0 ? 0 : $group->price - ($k % 3 === 0 ? 250000 : 0));
                if ($prepaid > 0 && $student->balance < $prepaid) {
                    $method = $k % 2 ? ['cash' => $prepaid] : ['card' => $prepaid];
                    $pay->receive($student, $method, null, "Demo to'lov", $managers[$k % 2]);
                }
                $student->refresh();
                if ($student->balance >= 0) {
                    $enroll->enroll($group, $student, 'Demo', $managers[0]);
                }
            }
        }

        $this->attendanceHistory($groups, $teachers);
        $leadCount = $this->leads($branch, $cat['sources'], $students, $managers[0]);

        // Kassa va ish haqi
        $cashbox = app(CashboxService::class);
        $withdrawal = $cashbox->request(CashRequest::WITHDRAWAL, PayMethod::Cash, 500000, "Moliya balansiga o'tkazish", $managers[0]);
        $cashbox->approve($withdrawal, $admin);
        $cashbox->request(CashRequest::EXPENSE, PayMethod::Cash, 120000, 'Ofis xarajati (kutmoqda)', $managers[1]);
        app(PayrollService::class)->payTeacher($teachers[0], $groups['finished'], PayMethod::Cash, 200000, 'Demo ish haqi', $admin);

        $studentCount = User::where('branch_id', $branch->id)->where('role', Role::Student)->count();

        return [$name, "{$slug}.admin", "{$slug}.menejer1, {$slug}.menejer2", "{$slug}.ustoz1..2", $studentCount, count($groups), $leadCount];
    }

    /** @return array<string,mixed> */
    private function catalog(Branch $branch): array
    {
        $courses = array_map(fn ($n) => Course::create(['name' => $n]), ['Koreys tili', 'Ingliz tili (IELTS)']);
        $rooms = array_map(fn ($n) => Room::create(['name' => $n]), ['1-xona', '2-xona', '3-xona']);
        $times = array_map(fn ($t) => LessonTime::create(['starts_at' => $t[0], 'ends_at' => $t[1]]), [['09:00', '10:30'], ['11:00', '12:30'], ['14:00', '15:30'], ['16:00', '17:30']]);
        $plans = [
            PricePlan::create(['name' => 'Standart', 'amount' => 500000, 'early_discount' => 50000, 'max_discount' => 100000]),
            PricePlan::create(['name' => 'Intensiv', 'amount' => 700000, 'early_discount' => 70000, 'max_discount' => 150000]),
        ];
        $sources = array_map(fn ($n) => LeadSource::create(['name' => $n]), ['Telegram', 'Instagram', 'Tanishlar', 'Reklama']);
        DiscountCampaign::create(['name' => 'Mustaqillik aksiyasi', 'amount' => 500000, 'bonus' => 100000, 'starts_on' => today()->subDays(10)->toDateString(), 'ends_on' => today()->addDays(20)->toDateString()]);
        app(HolidayService::class)->generateYear();

        return compact('courses', 'rooms', 'times', 'plans', 'sources');
    }

    private function staff(Branch $branch, Role $role, string $username, string $name): User
    {
        $user = User::create(['branch_id' => $branch->id, 'role' => $role, 'name' => $name, 'username' => $username, 'phone' => $this->phone(), 'password' => self::PASSWORD]);
        $user->syncPermissions(PermissionRegistry::defaultsFor($role));

        return $user;
    }

    private function student(Branch $branch, array $sources, User $actor, int $n): User
    {
        [$student] = app(StudentService::class)->create([
            'name' => $this->fullName(), 'phone' => $this->phone(),
            'phone2' => $n % 4 === 0 ? $this->phone() : null,
            'birthday' => $n === 0 ? today()->subYears(15)->toDateString() : today()->subYears(mt_rand(9, 22))->subDays(mt_rand(0, 300))->toDateString(),
            'address' => $branch->name.' sh.', 'lead_source_id' => $sources[$n % count($sources)]->id,
        ], $actor, notify: false);

        return $student;
    }

    /** O'tgan dars kunlari uchun davomad (faol va tugagan guruhlar). */
    private function attendanceHistory(array $groups, array $teachers): void
    {
        foreach (['finished', 'active'] as $key) {
            $group = $groups[$key];
            $members = $group->activeMembers()->pluck('student_id');

            foreach ($group->days()->whereDate('date', '<', today())->get() as $day) {
                $session = AttendanceSession::create(['branch_id' => $group->branch_id, 'group_id' => $group->id, 'date' => $day->date->toDateString(), 'taken_by' => $group->teacher_id]);

                foreach ($members as $studentId) {
                    Attendance::create([
                        'branch_id' => $group->branch_id, 'attendance_session_id' => $session->id, 'group_id' => $group->id,
                        'student_id' => $studentId, 'date' => $day->date->toDateString(), 'is_present' => mt_rand(1, 100) <= 82,
                    ]);
                }
            }
        }
    }

    private function leads(Branch $branch, array $sources, array $students, User $manager): int
    {
        $plan = [['new', 2], ['in_progress', 1], ['converted', 1], ['cancelled', 1]];
        $count = 0;

        foreach ($plan as [$status, $n]) {
            for ($i = 0; $i < $n; $i++) {
                $existing = $status === 'converted' ? $students[$count % count($students)] : null;
                $lead = Lead::create([
                    'branch_id' => $branch->id, 'name' => mb_strtoupper($this->fullName()), 'phone' => $existing?->phone ?? $this->phone(),
                    'lead_source_id' => $sources[$count % count($sources)]->id, 'status' => $status, 'student_id' => $existing?->id,
                    'created_by' => null,
                ]);
                LeadNote::create(['lead_id' => $lead->id, 'body' => 'Murojaat sayt orqali qabul qilindi']);
                if ($status === 'in_progress') {
                    LeadNote::create(['lead_id' => $lead->id, 'user_id' => $manager->id, 'body' => "Qo'ng'iroq qilindi, ertaga qayta bog'lanamiz"]);
                }
                $count++;
            }
        }

        return $count;
    }

    private function fullName(): string
    {
        $i = $this->nameSeq++;
        $female = $i % 2 === 1;
        $first = ($female ? self::FEMALE : self::MALE)[intdiv($i, 2) % 20];
        $last = self::SURNAMES[($i * 7 + intdiv($i, 20)) % 20].($female ? 'a' : '');

        return "{$last} {$first}";
    }

    private function phone(): string
    {
        $n = 1000000 + $this->phoneSeq++ * 7;

        return sprintf('+998 %s %s %s', $this->code, substr((string) $n, 0, 3), substr((string) $n, 3, 4));
    }
}
