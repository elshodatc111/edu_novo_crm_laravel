<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Support\BranchContext;
use Illuminate\Support\Facades\DB;

/**
 * v10 (7-band): filialni BUTUNLAY o'chirish - shu filialga tegishli BARCHA ma'lumot
 * (o'quvchi, hodim, guruh, to'lov, kassa, lid, SMS, AI suhbat, hujjat va h.k.) qaytarib
 * bo'lmaydigan tarzda o'chadi. Faqat sAdmin (route: role:sadmin), UI'da filial nomini
 * aniq yozib tasdiqlash talab qilinadi (`BranchController::destroy`).
 *
 * ISTISNO: `audit_logs` o'CHIRILMAYDI - faqat `branch_id`si bo'shatiladi (mavjud DB sxemasidagi
 * `nullOnDelete()` andozasiga mos). Sabab: harakatlar jurnali - filial o'chirilgani va undan
 * oldingi barcha harakatlar tarixi (kim, qachon, nima qilgani) kelajakda kerak bo'lishi mumkin;
 * bu yagona MAXSUS istisno, boshqa hech qanday jadval saqlanmaydi.
 *
 * Jadvallar FK bog'liqligini hisobga olib QAT'IY TARTIBDA o'chiriladi (bolalar avval,
 * ota-onalar keyin) - aks holda `restrictOnDelete()` xatolik beradi. Tez va xotira tejamli
 * bo'lishi uchun Eloquent modellar emas, to'g'ridan-to'g'ri `DB::table()` ishlatiladi.
 */
class BranchDeletionService
{
    public function delete(Branch $branch): void
    {
        $id = $branch->id;
        $name = $branch->name;

        DB::transaction(function () use ($id, $name, $branch) {
            $leadIds = DB::table('leads')->where('branch_id', $id)->pluck('id');
            $chatIds = DB::table('ai_chats')->where('branch_id', $id)->pluck('id');
            $userIds = DB::table('users')->where('branch_id', $id)->pluck('id');

            // 1) Guruh/davomad/to'lov bilan bog'liq "bolalar" jadvallari
            DB::table('attendances')->where('branch_id', $id)->delete();
            DB::table('attendance_sessions')->where('branch_id', $id)->delete();
            DB::table('group_students')->where('branch_id', $id)->delete();
            DB::table('balance_transactions')->where('branch_id', $id)->delete();
            DB::table('wallet_transactions')->where('branch_id', $id)->delete();
            DB::table('payments')->where('branch_id', $id)->delete();     // users.student_id dan OLDIN (restrictOnDelete)
            DB::table('payouts')->where('branch_id', $id)->delete();      // users.recipient_id dan OLDIN (restrictOnDelete)
            DB::table('cash_requests')->where('branch_id', $id)->delete();
            DB::table('cash_closings')->where('branch_id', $id)->delete();

            // 2) Lidlar
            if ($leadIds->isNotEmpty()) {
                DB::table('lead_notes')->whereIn('lead_id', $leadIds)->delete();
            }
            DB::table('leads')->where('branch_id', $id)->delete();

            // 3) Kurs kontenti va murojaatlar
            DB::table('test_attempts')->where('branch_id', $id)->delete();
            DB::table('course_videos')->where('branch_id', $id)->delete();
            DB::table('course_audios')->where('branch_id', $id)->delete();
            DB::table('course_questions')->where('branch_id', $id)->delete();
            DB::table('books')->where('branch_id', $id)->delete();

            // 4) SMS, ichki eslatmalar, kunlik vazifalar, AI suhbatlar
            DB::table('sms_messages')->where('branch_id', $id)->delete();
            DB::table('sms_templates')->where('branch_id', $id)->delete();
            DB::table('student_notes')->where('branch_id', $id)->delete();
            DB::table('task_dismissals')->where('branch_id', $id)->delete();
            if ($chatIds->isNotEmpty()) {
                DB::table('ai_messages')->whereIn('ai_chat_id', $chatIds)->delete();
            }
            DB::table('ai_messages')->where('branch_id', $id)->delete();
            DB::table('ai_chats')->where('branch_id', $id)->delete();

            // 5) Xodim/o'quvchiga bog'langan, lekin branch_id ustuni bo'lmagan yordamchi jadvallar
            if ($userIds->isNotEmpty()) {
                DB::table('submission_tokens')->whereIn('user_id', $userIds)->delete();
                DB::table('import_batches')->whereIn('user_id', $userIds)->delete();
            }

            // 6) Guruhlar (bolalar - group_days, group_students, attendance_sessions/attendances
            //    allaqachon tozalandi; group_days `group_id` bo'yicha kaskad o'chadi)
            DB::table('discount_campaigns')->where('branch_id', $id)->delete();
            DB::table('groups')->where('branch_id', $id)->delete();

            // 7) Moliyaviy hisoblar va ma'lumotnomalar (guruhlardan KEYIN - course/room/lesson_time
            //    ustiga restrictOnDelete bor edi)
            DB::table('expense_categories')->where('branch_id', $id)->delete();
            DB::table('wallets')->where('branch_id', $id)->delete();
            DB::table('courses')->where('branch_id', $id)->delete();
            DB::table('rooms')->where('branch_id', $id)->delete();
            DB::table('lesson_times')->where('branch_id', $id)->delete();
            DB::table('price_plans')->where('branch_id', $id)->delete();
            DB::table('lead_sources')->where('branch_id', $id)->delete();
            DB::table('holidays')->where('branch_id', $id)->delete();

            // 8) Xodim va o'quvchilar (endi ularga bog'liq restrictOnDelete jadvallar bo'sh -
            //    `user_permissions` kaskad o'chadi)
            DB::table('users')->where('branch_id', $id)->delete();

            // 9) Jurnalga filial HALI mavjud paytida yoziladi (aks holda `branch_id` FK
            //    xatolik beradi) - filial darrov o'chirilgach, DB sxemasidagi `nullOnDelete()`
            //    ORQALI shu yozuvning o'zi ham (boshqalar qatori) branch_id=null bo'ladi,
            //    lekin O'CHIRILMAYDI - shunday qilib "filial o'chirildi" tarixi saqlanib qoladi.
            AuditLog::record('branch.deleted', $branch, "Filial butunlay o'chirildi: {$name} (barcha ma'lumotlari bilan)", branchId: $id);

            // 10) Filialning o'zi
            DB::table('branches')->where('id', $id)->delete();
        });

        if (BranchContext::id() === $id) {
            BranchContext::select(null);
        }
    }
}
