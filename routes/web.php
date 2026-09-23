<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\AiChatController;
use App\Http\Controllers\AppVersionSettingsController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\CashboxController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\ConfirmationController;
use App\Http\Controllers\ContractSettingsController;
use App\Http\Controllers\CourseContentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiscountRuleController;
use App\Http\Controllers\DocsController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\GroupStudentController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicLeadController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SmsController;
use App\Http\Controllers\StatisticsController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StaffDirectoryController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentNoteController;
use App\Http\Controllers\SystemStatusController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'show'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->middleware('throttle:30,1');
});

// Saytdan murojaat qoldirish (ochiq)
Route::get('apply', [PublicLeadController::class, 'index'])->name('apply.index');
Route::get('apply/{branch:code}', [PublicLeadController::class, 'show'])->name('apply.show');
Route::post('apply/{branch:code}', [PublicLeadController::class, 'store'])->middleware('throttle:10,60')->name('apply.store');

// v12: mobil ilova dasturchisi uchun API hujjati - ataylab ochiq (login talab qilmaydi, sir yo'q)
Route::get('docs', [DocsController::class, 'index'])->name('docs.index');

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('tasks/dismiss', [DashboardController::class, 'dismissTask'])->name('tasks.dismiss');

    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [ProfileController::class, 'password'])->name('profile.password');

    // Filiallar (faqat sAdmin)
    Route::middleware('role:sadmin')->group(function () {
        Route::post('branch/switch', [BranchController::class, 'switch'])->name('branches.switch');
        Route::resource('branches', BranchController::class)->except(['show', 'destroy']);
        Route::post('branches/{branch}/close', [BranchController::class, 'close'])->name('branches.close');
        Route::post('branches/{branch}/reopen', [BranchController::class, 'reopen'])->name('branches.reopen');
        // v10 (7-band): filialni BUTUNLAY o'chirish (barcha ma'lumoti bilan) - faqat sAdmin, nom tasdiqlash bilan
        Route::delete('branches/{branch}', [BranchController::class, 'destroy'])->name('branches.destroy');

        // v8 A4: butun tizim darajasidagi holat (cron, zaxira, disk, ...) - filialga bog'liq emas
        Route::get('system-status', [SystemStatusController::class, 'index'])->name('system-status.index');
        // v12: zaxirani qo'lda, darhol ishga tushirish (cron jadvalini kutmasdan)
        Route::post('system-status/backup', [SystemStatusController::class, 'runBackupNow'])->middleware('throttle:5,1')->name('system-status.backup');

        // v11: mobil ilova foydalanuvchilariga push/ilova-ichi bildirishnoma yuborish
        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('notifications', [NotificationController::class, 'store'])->middleware('throttle:20,1')->name('notifications.store');

        // v12: mobil ilova versiyasi (majburiy/yumshoq yangilash) sozlamalari
        Route::get('app-version', [AppVersionSettingsController::class, 'edit'])->name('app-version.edit');
        Route::put('app-version', [AppVersionSettingsController::class, 'update'])->name('app-version.update');
    });

    // Hodimlar va ruxsatlar
    Route::resource('staff', StaffController::class)
        ->parameters(['staff' => 'user'])
        ->except(['show', 'destroy']);
    Route::get('staff/{user}/permissions', [PermissionController::class, 'edit'])->name('staff.permissions.edit');
    Route::put('staff/{user}/permissions', [PermissionController::class, 'update'])->name('staff.permissions.update');
    // v9: filial cheklovisiz, faqat ism/rol/filial ro'yxati (odatda Operatorga)
    Route::get('staff-directory', [StaffDirectoryController::class, 'index'])->name('staff.directory');
    // v10 (1-band): shu ruxsat bilan boshqa filialning o'quvchi/guruh/lidlarini FAQAT KO'RISH (yozish yo'q)
    Route::get('staff-directory/{branch}', [StaffDirectoryController::class, 'branch'])->name('staff.directory.branch');

    // O'quvchilar
    Route::get('students/create', [StudentController::class, 'create'])->middleware('branch')->name('students.create');
    Route::post('students', [StudentController::class, 'store'])->middleware('branch')->name('students.store');
    Route::get('students/search', [StudentController::class, 'search'])->name('students.search');
    Route::get('students', [StudentController::class, 'index'])->name('students.index');
    Route::get('students/{student}', [StudentController::class, 'show'])->name('students.show');
    Route::get('students/{student}/edit', [StudentController::class, 'edit'])->name('students.edit');
    Route::put('students/{student}', [StudentController::class, 'update'])->name('students.update');
    Route::post('students/{student}/reset-password', [StudentController::class, 'resetPassword'])->name('students.reset-password');
    Route::post('students/{student}/archive', [StudentController::class, 'archive'])->name('students.archive');
    Route::post('students/{student}/restore', [StudentController::class, 'restore'])->name('students.restore');
    Route::post('students/{student}/notes', [StudentNoteController::class, 'store'])->name('students.notes.store');
    Route::delete('students/{student}/notes/{note}', [StudentNoteController::class, 'destroy'])->name('students.notes.destroy');

    // Guruhlar
    Route::get('groups/create', [GroupController::class, 'create'])->middleware('branch')->name('groups.create');
    Route::post('groups', [GroupController::class, 'store'])->middleware('branch')->name('groups.store');
    Route::get('groups', [GroupController::class, 'index'])->name('groups.index');
    Route::get('groups/{group}', [GroupController::class, 'show'])->name('groups.show');
    Route::get('groups/{group}/edit', [GroupController::class, 'edit'])->name('groups.edit');
    Route::put('groups/{group}', [GroupController::class, 'update'])->name('groups.update');
    Route::post('groups/{group}/continue', [GroupController::class, 'continue'])->name('groups.continue');
    Route::post('groups/{group}/students', [GroupStudentController::class, 'store'])->name('groups.students.store');
    Route::delete('groups/{group}/students/{student}', [GroupStudentController::class, 'destroy'])->name('groups.students.destroy');
    Route::get('groups/{group}/students/{student}/contract', [GroupStudentController::class, 'contract'])->name('groups.students.contract');

    // Davomad
    Route::get('attendance', [AttendanceController::class, 'today'])->name('attendance.today');
    Route::get('attendance/stats', [AttendanceController::class, 'stats'])->name('attendance.stats');
    Route::post('groups/{group}/attendance', [AttendanceController::class, 'take'])->name('attendance.take');
    Route::get('groups/{group}/attendance/past', [AttendanceController::class, 'pastForm'])->name('attendance.past.show');
    Route::post('groups/{group}/attendance/past', [AttendanceController::class, 'editPast'])->name('attendance.past.store');

    // Yordam (AI): barcha panel foydalanuvchilari uchun
    Route::get('help', [HelpController::class, 'index'])->name('help.index');
    Route::post('help', [HelpController::class, 'send'])->middleware('throttle:30,1')->name('help.send');
    Route::delete('help/{chat}', [HelpController::class, 'destroy'])->name('help.destroy');

    // AI yordamchi
    Route::get('ai', [AiChatController::class, 'index'])->name('ai.index');
    Route::post('ai', [AiChatController::class, 'send'])->middleware('throttle:30,1')->name('ai.send');
    Route::delete('ai/{chat}', [AiChatController::class, 'destroy'])->name('ai.destroy');

    // Excel import
    Route::get('imports', [ImportController::class, 'index'])->name('imports.index');
    Route::get('imports/template', [ImportController::class, 'template'])->name('imports.template');
    Route::post('imports', [ImportController::class, 'upload'])->name('imports.upload');
    Route::get('imports/{batch}', [ImportController::class, 'show'])->name('imports.show');
    Route::post('imports/{batch}/confirm', [ImportController::class, 'confirm'])->name('imports.confirm');
    Route::post('imports/{batch}/cancel', [ImportController::class, 'cancel'])->name('imports.cancel');
    Route::get('imports/{batch}/result', [ImportController::class, 'result'])->name('imports.result');

    // Statistika va hisobotlar
    Route::get('statistics', [StatisticsController::class, 'index'])->name('statistics.index');
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/{report}', [ReportController::class, 'show'])->name('reports.show');
    Route::get('reports/{report}/export', [ReportController::class, 'export'])->name('reports.export');

    // Varonka (lidlar)
    Route::get('leads/create', [LeadController::class, 'create'])->middleware('branch')->name('leads.create');
    Route::post('leads', [LeadController::class, 'store'])->middleware('branch')->name('leads.store');
    Route::get('leads', [LeadController::class, 'index'])->name('leads.index');
    Route::get('leads/{lead}', [LeadController::class, 'show'])->name('leads.show');
    Route::post('leads/{lead}/note', [LeadController::class, 'note'])->name('leads.note');
    Route::post('leads/{lead}/cancel', [LeadController::class, 'cancel'])->name('leads.cancel');
    Route::post('leads/{lead}/analyze', [LeadController::class, 'analyze'])->middleware('throttle:20,1')->name('leads.analyze');
    Route::post('leads/{lead}/reopen', [LeadController::class, 'reopen'])->name('leads.reopen');
    Route::post('leads/{lead}/convert', [LeadController::class, 'convert'])->middleware('branch')->name('leads.convert');

    // SMS
    Route::get('sms', [SmsController::class, 'index'])->name('sms.index');
    Route::middleware('branch')->group(function () {
        Route::put('sms/settings', [SmsController::class, 'settings'])->name('sms.settings');
        Route::post('sms/bulk', [SmsController::class, 'bulk'])->name('sms.bulk');
    });

    // Kurs materiallari
    Route::get('courses/{course}', [CourseContentController::class, 'show'])->name('courses.show');
    Route::post('courses/{course}/videos', [CourseContentController::class, 'storeVideo'])->name('courses.videos.store');
    Route::delete('courses/{course}/videos/{video}', [CourseContentController::class, 'destroyVideo'])->name('courses.videos.destroy');
    Route::post('courses/{course}/audios', [CourseContentController::class, 'storeAudio'])->name('courses.audios.store');
    Route::delete('courses/{course}/audios/{audio}', [CourseContentController::class, 'destroyAudio'])->name('courses.audios.destroy');
    Route::post('courses/{course}/questions', [CourseContentController::class, 'storeQuestion'])->name('courses.questions.store');
    Route::post('courses/{course}/questions/ai', [CourseContentController::class, 'aiGenerate'])->middleware('throttle:20,60')->name('courses.questions.ai');
    Route::post('courses/{course}/questions/ai-store', [CourseContentController::class, 'storeAiQuestions'])->name('courses.questions.ai-store');
    Route::delete('courses/{course}/questions/{question}', [CourseContentController::class, 'destroyQuestion'])->name('courses.questions.destroy');

    // To'lovlar
    Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::post('students/{student}/payments', [PaymentController::class, 'store'])->middleware('once')->name('payments.store');
    Route::post('students/{student}/discount', [PaymentController::class, 'discount'])->middleware('once')->name('payments.discount');
    Route::post('students/{student}/refund', [PaymentController::class, 'refund'])->middleware('once')->name('payments.refund');
    Route::post('payments/{payment}/reverse', [PaymentController::class, 'reverseInitiate'])->name('payments.reverse');
    Route::get('payments/{payment}/receipt', [PaymentController::class, 'receipt'])->name('payments.receipt');

    // Kassa, moliya, ish haqi (filial tanlangan bo'lishi kerak)
    Route::middleware('branch')->group(function () {
        Route::get('cashbox', [CashboxController::class, 'index'])->name('cashbox.index');
        Route::post('cashbox/requests', [CashboxController::class, 'store'])->middleware('once')->name('cashbox.store');
        Route::post('cashbox/requests/{cashRequest}/approve', [CashboxController::class, 'approve'])->name('cashbox.approve');
        Route::post('cashbox/requests/{cashRequest}/cancel', [CashboxController::class, 'cancel'])->name('cashbox.cancel');
        Route::post('cashbox/refunds/{payment}/confirm', [CashboxController::class, 'confirmRefund'])->name('cashbox.refund-confirm');
        Route::post('cashbox/refunds/{payment}/reject', [CashboxController::class, 'rejectRefund'])->name('cashbox.refund-reject');
        Route::post('cashbox/close', [CashboxController::class, 'closeShift'])->middleware('once')->name('cashbox.close');

        Route::get('finance', [FinanceController::class, 'index'])->name('finance.index');
        Route::post('finance/withdraw', [FinanceController::class, 'withdraw'])->name('finance.withdraw');
        Route::post('finance/charity-withdraw', [FinanceController::class, 'charityWithdraw'])->name('finance.charity-withdraw');
        Route::post('finance/expense', [FinanceController::class, 'expense'])->name('finance.expense');
        Route::post('finance/charity', [FinanceController::class, 'charity'])->name('finance.charity');
        Route::post('finance/deposit', [FinanceController::class, 'deposit'])->middleware('once')->name('finance.deposit');

        // Ikki bosqichli tasdiqlash ("Tekshiring" sahifasi): pul chiqadigan amallar shu yerda bajariladi
        Route::get('confirm/{token}', [ConfirmationController::class, 'show'])->name('confirm.show');
        Route::post('confirm/{token}', [ConfirmationController::class, 'store'])->name('confirm.store');

        Route::get('payroll', [PayrollController::class, 'index'])->name('payroll.index');
        Route::get('payroll/{user}', [PayrollController::class, 'show'])->name('payroll.show');
        Route::post('payroll/{user}', [PayrollController::class, 'pay'])->name('payroll.pay');
    });

    // Filial sozlamalari va kurslar (sAdmin uchun filial tanlangan bo'lishi kerak)
    Route::middleware('branch')->group(function () {
        Route::get('settings/discount-rules', [DiscountRuleController::class, 'edit'])->name('discount-rules.edit');
        Route::put('settings/discount-rules', [DiscountRuleController::class, 'update'])->name('discount-rules.update');
        Route::get('settings/holidays', [HolidayController::class, 'index'])->name('holidays.index');
        Route::post('settings/holidays', [HolidayController::class, 'store'])->name('holidays.store');
        Route::post('settings/holidays/generate', [HolidayController::class, 'generate'])->name('holidays.generate');
        Route::delete('settings/holidays/{holiday}', [HolidayController::class, 'destroy'])->name('holidays.destroy');
        Route::get('settings/contract', [ContractSettingsController::class, 'edit'])->name('contract-settings.edit');
        Route::put('settings/contract', [ContractSettingsController::class, 'update'])->name('contract-settings.update');
        Route::put('settings/contract/reset', [ContractSettingsController::class, 'reset'])->name('contract-settings.reset');

        Route::prefix('settings/{catalog}')->whereIn('catalog', ['courses', 'rooms', 'lesson-times', 'price-plans', 'campaigns', 'books', 'lead-sources', 'expense-categories'])->group(function () {
            Route::get('/', [CatalogController::class, 'index'])->name('catalog.index');
            Route::post('/', [CatalogController::class, 'store'])->name('catalog.store');
            Route::put('{id}', [CatalogController::class, 'update'])->name('catalog.update');
            Route::post('{id}/toggle', [CatalogController::class, 'toggle'])->name('catalog.toggle');
        });
    });

    Route::get('audit-log', [AuditLogController::class, 'index'])->name('audit.index');
});
