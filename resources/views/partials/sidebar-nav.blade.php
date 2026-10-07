{{--
    v13: yon menyu bo'limlarga ajratilgan va yig'iladi. Bo'lim ichida foydalanuvchiga ko'rinadigan band
    bo'lmasa, bo'lim sarlavhasi ham chiqmaydi. Faol sahifaning bo'limi o'zi ochiq turadi; qolganlarining
    holati brauzerda (localStorage) saqlanadi.
--}}
@php
    $isTeacher = $me->role === \App\Enums\Role::Teacher;

    $groups = [
        [
            'key' => 'learn', 'title' => "O'quv jarayoni", 'icon' => 'cap',
            'items' => [
                ['label' => "O'quvchilar", 'href' => route('students.index'), 'icon' => 'cap', 'active' => request()->routeIs('students.*'), 'show' => $me->can('students.view')],
                ['label' => $isTeacher ? 'Guruhlarim' : 'Guruhlar', 'href' => route('groups.index'), 'icon' => 'users', 'active' => request()->routeIs('groups.*'), 'show' => $me->can('viewAny', \App\Models\Group::class)],
                ['label' => 'Bugungi davomad', 'href' => route('attendance.today'), 'icon' => 'calendar', 'active' => request()->routeIs('attendance.today'), 'show' => $me->can('attendance.view')],
                ['label' => 'Davomad statistikasi', 'href' => route('attendance.stats'), 'icon' => 'chart', 'active' => request()->routeIs('attendance.stats'), 'show' => $me->can('attendance.stats')],
                ['label' => 'Kurslar', 'href' => route('catalog.index', 'courses'), 'icon' => 'book', 'active' => request()->is('settings/courses*') || request()->routeIs('courses.*'), 'show' => $me->canany(['courses.view', 'courses.manage'])],
            ],
        ],
        [
            'key' => 'sales', 'title' => 'Murojaat va aloqa', 'icon' => 'funnel',
            'items' => [
                ['label' => 'Varonka', 'href' => route('leads.index'), 'icon' => 'funnel', 'active' => request()->routeIs('leads.*'), 'show' => $me->can('leads.view')],
                ['label' => 'SMS', 'href' => route('sms.index'), 'icon' => 'message', 'active' => request()->routeIs('sms.*'), 'show' => $me->canany(['sms.view', 'sms.send', 'sms.manage'])],
            ],
        ],
        [
            'key' => 'money', 'title' => 'Moliya', 'icon' => 'wallet',
            'items' => [
                ['label' => "To'lovlar", 'href' => route('payments.index'), 'icon' => 'banknote', 'active' => request()->routeIs('payments.*'), 'show' => $me->can('payments.view')],
                ['label' => 'Kassa', 'href' => route('cashbox.index'), 'icon' => 'wallet', 'active' => request()->routeIs('cashbox.*'), 'show' => $me->can('cashbox.view')],
                ['label' => 'Moliya', 'href' => route('finance.index'), 'icon' => 'chart', 'active' => request()->routeIs('finance.*'), 'show' => $me->can('finance.view')],
                ['label' => 'Ish haqi', 'href' => route('payroll.index'), 'icon' => 'briefcase', 'active' => request()->routeIs('payroll.*'), 'show' => $me->can('teachers.view') || $me->can('staff.view')],
            ],
        ],
        [
            'key' => 'analytics', 'title' => 'Tahlil', 'icon' => 'chart',
            'items' => [
                ['label' => 'Statistika', 'href' => route('statistics.index'), 'icon' => 'chart', 'active' => request()->routeIs('statistics.*'), 'show' => $me->can('statistics.view')],
                ['label' => 'Hisobotlar', 'href' => route('reports.index'), 'icon' => 'log', 'active' => request()->routeIs('reports.*'), 'show' => $me->can('reports.view')],
            ],
        ],
        [
            'key' => 'manage', 'title' => 'Boshqaruv', 'icon' => 'shield',
            'items' => [
                $me->can('staff.view')
                    ? ['label' => 'Hodimlar', 'href' => route('staff.index'), 'icon' => 'shield', 'active' => request()->routeIs('staff.index', 'staff.create', 'staff.edit'), 'show' => true]
                    : ['label' => "O'qituvchilar", 'href' => route('staff.index', ['role' => 'teacher']), 'icon' => 'shield', 'active' => request()->routeIs('staff.index', 'staff.create', 'staff.edit'), 'show' => $me->can('teachers.view')],
                ['label' => 'Filiallar xodimlari', 'href' => route('staff.directory'), 'icon' => 'users', 'active' => request()->routeIs('staff.directory'), 'show' => $me->can('staff.view_all_branches')],
                ['label' => 'Sozlamalar', 'href' => route('catalog.index', 'rooms'), 'icon' => 'cog', 'active' => request()->is('settings/*') && ! request()->is('settings/courses*'), 'show' => $me->can('settings.branch')],
            ],
        ],
        [
            'key' => 'system', 'title' => 'Tizim', 'icon' => 'cog',
            'items' => [
                ['label' => 'Filiallar', 'href' => route('branches.index'), 'icon' => 'building', 'active' => request()->routeIs('branches.*'), 'show' => $me->isSuperAdmin()],
                ['label' => 'Super adminlar', 'href' => route('superadmins.index'), 'icon' => 'shield', 'active' => request()->routeIs('superadmins.*'), 'show' => $me->isSuperAdmin()],
                ['label' => 'Tizim holati', 'href' => route('system-status.index'), 'icon' => 'cog', 'active' => request()->routeIs('system-status.*'), 'show' => $me->isSuperAdmin()],
                ['label' => 'Bildirishnomalar', 'href' => route('notifications.index'), 'icon' => 'bell', 'active' => request()->routeIs('notifications.*'), 'show' => $me->isSuperAdmin()],
                ['label' => 'Ilova versiyasi', 'href' => route('app-version.edit'), 'icon' => 'phone', 'active' => request()->routeIs('app-version.*'), 'show' => $me->isSuperAdmin()],
                ['label' => 'Harakatlar jurnali', 'href' => route('audit.index'), 'icon' => 'log', 'active' => request()->routeIs('audit.*'), 'show' => $me->can('audit.view')],
                ['label' => 'API hujjati', 'href' => route('docs.index'), 'icon' => 'book', 'active' => false, 'show' => $me->can('audit.view'), 'blank' => true],
            ],
        ],
    ];
@endphp

<x-nav-link :href="route('dashboard')" icon="home" :active="request()->routeIs('dashboard')">Bosh sahifa</x-nav-link>
<x-nav-link :href="route('help.index')" icon="sparkles" :active="request()->routeIs('help.*', 'ai.*')">Yordam</x-nav-link>

@foreach ($groups as $group)
    @php
        $visible = array_values(array_filter($group['items'], fn ($i) => $i['show']));
        $hasActive = (bool) collect($visible)->contains(fn ($i) => $i['active']);
    @endphp
    @if ($visible !== [])
        <div x-data="navGroup('{{ $group['key'] }}', {{ $hasActive ? 'true' : 'false' }})" class="pt-2">
            <button type="button" @click="toggle()" :aria-expanded="open.toString()"
                    class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-xs font-semibold uppercase tracking-wider text-ink-400 transition hover:bg-ink-100 hover:text-ink-700 dark:hover:bg-ink-800 dark:hover:text-ink-200">
                <x-icon :name="$group['icon']" class="h-4 w-4 shrink-0" />
                <span class="flex-1 text-left">{{ $group['title'] }}</span>
                @if ($hasActive)<span class="h-1.5 w-1.5 rounded-full bg-brand-500" x-show="!open"></span>@endif
                <x-icon name="chevron" class="h-4 w-4 shrink-0 transition-transform" ::class="open ? 'rotate-180' : ''" />
            </button>
            <div x-show="open" x-cloak class="mt-1 space-y-1 border-l border-ink-100 pl-2 ml-4 dark:border-ink-800">
                @foreach ($visible as $item)
                    <x-nav-link :href="$item['href']" :icon="$item['icon']" :active="$item['active']" :target="! empty($item['blank']) ? '_blank' : null">{{ $item['label'] }}</x-nav-link>
                @endforeach
            </div>
        </div>
    @endif
@endforeach
