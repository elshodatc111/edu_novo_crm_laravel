<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\Group;
use App\Models\Lead;
use App\Models\User;
use App\Models\Concerns\BranchScope;

/**
 * v9: barcha filiallar bo'yicha xodimlar ro'yxati (faqat ism, rol, filial, telefon).
 * Odatiy filial izolyatsiyasidan MAXSUS istisno - shu sababli `User::visibleToContext()`
 * emas, filial cheklovisiz to'g'ridan-to'g'ri so'rov ishlatiladi. Boshqa hech qanday
 * ma'lumotga (o'quvchi, to'lov, sozlama) bu sahifadan kirish yo'q.
 *
 * v10 (1-band): xuddi shu ruxsat bilan `branch()` orqali istalgan filialning o'quvchi,
 * guruh va lidlar ro'yxatini FAQAT KO'RISH mumkin (sAdmin kabi "barcha filiallar", lekin
 * yozish/tahrirlash imkoniyatisiz). Muhim: bu yerda `BranchContext::id()` (joriy ishchi
 * filial) HECH QACHON o'zgartirilmaydi - shu sababli yozuv huquqi boshqa filialga
 * "sizib chiqish" xavfi yo'q. `Group`/`Lead` kabi `BelongsToBranch` global scope'li
 * modellarda `withoutGlobalScope` bilan ataylab boshqa filial tanlanadi (faqat shu
 * metodda, faqat o'qish uchun).
 */
class StaffDirectoryController extends Controller
{
    public function index()
    {
        $this->authorize('staff.view_all_branches');

        $staff = User::whereIn('role', [Role::SAdmin, Role::Admin, Role::Manager, Role::Operator, Role::Teacher])
            ->where('status', 'active')
            ->with('branch:id,name')
            ->orderBy('branch_id')
            ->orderBy('name')
            ->get(['id', 'name', 'role', 'phone', 'branch_id'])
            ->groupBy(fn (User $u) => $u->branch_id ?: 'sadmin');

        $branches = Branch::whereIn('id', $staff->keys()->filter(fn ($k) => $k !== 'sadmin'))
            ->orderBy('name')->get(['id', 'name'])->keyBy('id');

        return view('staff.directory', compact('staff', 'branches'));
    }

    /** v10 (1-band): boshqa filialning o'quvchi/guruh/lid ro'yxati - faqat o'qish, hech qanday amal tugmasi yo'q. */
    public function branch(Branch $branch)
    {
        $this->authorize('staff.view_all_branches');

        $students = User::where('branch_id', $branch->id)->where('role', Role::Student)->whereNull('archived_at')
            ->orderBy('name')->paginate(15, ['id', 'name', 'phone', 'balance'], 'students_page')->withQueryString();

        // Diqqat: eager-load qilingan `course`/`source` munosabatlari o'z modelining
        // (Course/LeadSource) alohida BelongsToBranch global scope'iga ega - shu sababli
        // ularda ham withoutGlobalScope kerak, aks holda boshqa filial yozuvi eager-load
        // paytida jim o'chirilib, natijada "—" (topilmadi) ko'rinadi.
        $groups = Group::withoutGlobalScope(BranchScope::class)->where('branch_id', $branch->id)
            ->with(['course' => fn ($q) => $q->withoutGlobalScope(BranchScope::class)->select('id', 'name')])
            ->orderByDesc('starts_on')
            ->paginate(15, ['*'], 'groups_page')->withQueryString();

        $leads = Lead::withoutGlobalScope(BranchScope::class)->where('branch_id', $branch->id)
            ->with(['source' => fn ($q) => $q->withoutGlobalScope(BranchScope::class)->select('id', 'name')])
            ->latest('created_at')
            ->paginate(15, ['*'], 'leads_page')->withQueryString();

        $branches = Branch::orderBy('name')->get(['id', 'name']);

        return view('staff.branch', compact('branch', 'branches', 'students', 'groups', 'leads'));
    }
}
