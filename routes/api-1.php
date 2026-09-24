<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\CashboxController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\FinanceController;
use App\Http\Controllers\Api\GroupController;
use App\Http\Controllers\Api\GroupStudentController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\SmsController;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\Api\StaffDirectoryController;
use App\Http\Controllers\Api\StatisticsController;
use App\Http\Controllers\Api\StudentController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:20,1');

    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('auth/change-password', [AuthController::class, 'changePassword']);

        // O'zim haqimda
        Route::get('me/profile', [MeController::class, 'profile']);
        Route::get('me/balance', [MeController::class, 'balance']);
        Route::get('me/payroll', [MeController::class, 'payroll']);
        Route::get('me/groups', [MeController::class, 'groups']);
        Route::get('me/attendance', [MeController::class, 'attendanceHistory']);

        // v11: push-bildirishnoma - qurilma tokeni va o'ziga kelgan xabarlar
        Route::post('me/device-token', [MeController::class, 'storeDeviceToken']);
        Route::delete('me/device-token', [MeController::class, 'destroyDeviceToken']);
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/read-all', [NotificationController::class, 'readAll']);
        Route::post('notifications/{notification}/read', [NotificationController::class, 'read']);

        // v11: filial almashtirish (sAdmin/operator) - tanlangan filial keyingi so'rovlarda X-Branch-Id bilan yuboriladi
        Route::get('branches', [BranchController::class, 'index']);

        // v11: bosh sahifa vazifalari
        Route::get('dashboard/todo', [DashboardController::class, 'todo']);

        // Xodimlar uchun
        Route::get('students', [StudentController::class, 'index']);
        Route::get('students/{student}', [StudentController::class, 'show']);
        Route::post('students/{student}/payments', [StudentController::class, 'pay'])->middleware('once');
        Route::get('statistics/overview', [StatisticsController::class, 'overview']);
        // v13: statistika grafiklari (tushum dinamikasi, top guruhlar, kurslar, qarzdorlar, davomad, voronka)
        Route::get('statistics/charts', [StatisticsController::class, 'charts']);

        Route::get('books', [CourseController::class, 'books']);
        Route::get('courses', [CourseController::class, 'index']);
        Route::get('courses/{course}/videos', [CourseController::class, 'videos']);
        Route::get('courses/{course}/audios', [CourseController::class, 'audios']);
        Route::post('courses/{course}/tests/start', [CourseController::class, 'startTest']);
        Route::get('courses/{course}/tests/results', [CourseController::class, 'results']);
        Route::post('tests/{attempt}/submit', [CourseController::class, 'submitTest']);

        Route::get('groups', [GroupController::class, 'index']);
        Route::get('groups/{group}', [GroupController::class, 'show']);
        Route::get('groups/{group}/attendance', [AttendanceController::class, 'index']);
        Route::post('groups/{group}/attendance', [AttendanceController::class, 'store']);
        // v11: guruhga yangi o'quvchi biriktirish/chiqarish
        Route::post('groups/{group}/students', [GroupStudentController::class, 'store']);
        Route::delete('groups/{group}/students/{student}', [GroupStudentController::class, 'destroy']);

        // v11: Varonka (lidlar/CRM)
        Route::get('leads', [LeadController::class, 'index']);
        Route::get('leads/{lead}', [LeadController::class, 'show']);
        Route::post('leads', [LeadController::class, 'store'])->middleware('branch');
        Route::post('leads/{lead}/note', [LeadController::class, 'note']);
        Route::post('leads/{lead}/cancel', [LeadController::class, 'cancel']);
        Route::post('leads/{lead}/reopen', [LeadController::class, 'reopen']);
        Route::post('leads/{lead}/convert', [LeadController::class, 'convert'])->middleware('branch');

        // v11: Kassa (chiqim/xarajat so'rovi) - smena yopish va qaytarish tasdig'i ataylab veb-only
        // v12 A/C: GET so'rovlar ham 'branch' bilan - sAdmin filial ko'rsatmasa 500 o'rniga aniq 422 qaytadi
        Route::get('cashbox', [CashboxController::class, 'index'])->middleware('branch');
        Route::get('cashbox/expense-categories', [CashboxController::class, 'expenseCategories'])->middleware('branch');
        Route::post('cashbox/requests', [CashboxController::class, 'store'])->middleware(['once', 'branch']);
        Route::post('cashbox/requests/{cashRequest}/approve', [CashboxController::class, 'approve']);
        Route::post('cashbox/requests/{cashRequest}/cancel', [CashboxController::class, 'cancel']);

        // v11: Moliya - ko'rish + shaxsiy mablag' kiritish; v13: chiqimlar ikki bosqichli
        // v12 A: GET ham 'branch' bilan (yuqoridagi kabi)
        Route::get('finance/overview', [FinanceController::class, 'overview'])->middleware('branch');
        Route::post('finance/deposit', [FinanceController::class, 'deposit'])->middleware(['once', 'branch']);
        // v13: chiqim/xarajat/ehson chiqimi - IKKI BOSQICHLI (preview -> confirm + foydalanuvchi paroli)
        Route::post('finance/outflow/preview', [FinanceController::class, 'outflowPreview'])->middleware(['branch', 'throttle:30,1']);
        Route::post('finance/outflow/confirm', [FinanceController::class, 'outflowConfirm'])->middleware(['once', 'branch', 'throttle:20,1']);

        // v11: Hodimlar - ish haqi TO'LASH ataylab veb-only (ikki bosqichli tasdiqlash)
        // v12 B: staff qo'shish endi ham 'branch' (X-Branch-Id) orqali - body'dagi branch_id endi API'da kerak emas
        Route::get('staff', [StaffController::class, 'index']);
        Route::get('staff/{user}', [StaffController::class, 'show']);
        Route::post('staff', [StaffController::class, 'store'])->middleware('branch');
        Route::put('staff/{user}', [StaffController::class, 'update']);
        Route::get('staff/{user}/payroll', [StaffController::class, 'payroll']);

        // v9/v10: boshqa filiallarni FAQAT KO'RISH
        Route::get('staff-directory', [StaffDirectoryController::class, 'index']);
        Route::get('staff-directory/{branch}', [StaffDirectoryController::class, 'branch']);

        // v11: SMS - tarix + ommaviy yuborishni oldindan ko'rish (haqiqiy yuborish veb-only)
        // v12 C: bulk-preview ham 'branch' bilan
        Route::get('sms', [SmsController::class, 'index']);
        Route::post('sms/bulk-preview', [SmsController::class, 'bulkPreview'])->middleware('branch');

        // v12: profilni tahrirlash, ilova versiyasi
        Route::post('me/profile', [MeController::class, 'updateProfile']);
    });
});

// v12: ilova versiyasi tekshiruvi - login talab qilmaydi (ilova ochilganda darhol so'raladi)
Route::prefix('v1')->group(function () {
    Route::get('app/version', [\App\Http\Controllers\Api\AppVersionController::class, 'check']);

    // v12: parolni unutganda SMS OTP orqali tiklash (login talab qilmaydi)
    Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
    Route::post('auth/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:10,1');
});
