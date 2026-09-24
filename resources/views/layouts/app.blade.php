<!DOCTYPE html>
<html lang="uz" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Bosh sahifa') · Edunova CRM</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <script>try{if(localStorage.getItem('theme')==='dark'||(!localStorage.getItem('theme')&&window.matchMedia('(prefers-color-scheme: dark)').matches)){document.documentElement.classList.add('dark')}}catch(e){}</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full" x-data="{ sidebar: false }">
@php
    $me = auth()->user();
    $branches = $me->isSuperAdmin() ? \App\Models\Branch::orderBy('name')->get(['id', 'name', 'status']) : collect();
    $selectedBranch = \App\Support\BranchContext::id();
@endphp

{{-- Mobil menyu fon qatlami --}}
<div x-show="sidebar" x-transition.opacity x-cloak @click="sidebar = false" class="fixed inset-0 z-30 bg-ink-950/50 lg:hidden"></div>

<aside :class="sidebar ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
       class="fixed inset-y-0 left-0 z-40 flex w-72 flex-col border-r border-ink-200/70 bg-white transition-transform dark:border-ink-800 dark:bg-ink-900">
    <div class="flex h-16 items-center gap-3 px-5">
        <x-logo />
        <div class="leading-tight">
            <div class="text-base font-extrabold tracking-tight text-ink-900 dark:text-white">Edunova</div>
            <div class="text-[11px] font-medium uppercase tracking-widest text-ink-400">CRM tizimi</div>
        </div>
        <button class="ml-auto lg:hidden" @click="sidebar = false"><x-icon name="x" /></button>
    </div>

    <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
        <x-nav-link :href="route('dashboard')" icon="home" :active="request()->routeIs('dashboard')">Bosh sahifa</x-nav-link>
        @can('students.view')
            <x-nav-link :href="route('students.index')" icon="cap" :active="request()->routeIs('students.*')">O'quvchilar</x-nav-link>
        @endcan
        @can('attendance.view')
            <x-nav-link :href="route('attendance.today')" icon="calendar" :active="request()->routeIs('attendance.today')">Bugungi davomad</x-nav-link>
        @endcan
        @can('viewAny', \App\Models\Group::class)
            <x-nav-link :href="route('groups.index')" icon="users" :active="request()->routeIs('groups.*')">{{ $me->role === \App\Enums\Role::Teacher ? 'Guruhlarim' : 'Guruhlar' }}</x-nav-link>
        @endcan
        @can('attendance.stats')
            <x-nav-link :href="route('attendance.stats')" icon="chart" :active="request()->routeIs('attendance.stats')">Davomad statistikasi</x-nav-link>
        @endcan
        @can('leads.view')
            <x-nav-link :href="route('leads.index')" icon="funnel" :active="request()->routeIs('leads.*')">Varonka</x-nav-link>
        @endcan
        @can('cashbox.view')
            <x-nav-link :href="route('cashbox.index')" icon="wallet" :active="request()->routeIs('cashbox.*')">Kassa</x-nav-link>
        @endcan
        @can('finance.view')
            <x-nav-link :href="route('finance.index')" icon="chart" :active="request()->routeIs('finance.*')">Moliya</x-nav-link>
        @endcan
        @can('payments.view')
            <x-nav-link :href="route('payments.index')" icon="banknote" :active="request()->routeIs('payments.*')">To'lovlar</x-nav-link>
        @endcan
        @can('statistics.view')
            <x-nav-link :href="route('statistics.index')" icon="chart" :active="request()->routeIs('statistics.*')">Statistika</x-nav-link>
        @endcan
        @can('reports.view')
            <x-nav-link :href="route('reports.index')" icon="log" :active="request()->routeIs('reports.*')">Hisobotlar</x-nav-link>
        @endcan
        <x-nav-link :href="route('help.index')" icon="sparkles" :active="request()->routeIs('help.*', 'ai.*')">Yordam</x-nav-link>
        @can('settings.branch')
            <x-nav-link :href="route('catalog.index', 'rooms')" icon="cog" :active="request()->is('settings/*') && ! request()->is('settings/courses*')">Sozlamalar</x-nav-link>
        @endcan
        @canany(['courses.view', 'courses.manage'])
            <x-nav-link :href="route('catalog.index', 'courses')" icon="book" :active="request()->is('settings/courses*') || request()->routeIs('courses.*')">Kurslar</x-nav-link>
        @endcanany
        @if ($me->canany(['sms.view', 'sms.send', 'sms.manage']))
            <x-nav-link :href="route('sms.index')" icon="message" :active="request()->routeIs('sms.*')">SMS</x-nav-link>
        @endif
        @can('staff.view_all_branches')
            <x-nav-link :href="route('staff.directory')" icon="users" :active="request()->routeIs('staff.directory')">Filiallar xodimlari</x-nav-link>
        @endcan
        @can('staff.view')
            <x-nav-link :href="route('staff.index')" icon="shield" :active="request()->routeIs('staff.index') || request()->routeIs('staff.create') || request()->routeIs('staff.edit')">Hodimlar</x-nav-link>
        @elsecan('teachers.view')
            <x-nav-link :href="route('staff.index', ['role' => 'teacher'])" icon="shield" :active="request()->routeIs('staff.index') || request()->routeIs('staff.create') || request()->routeIs('staff.edit')">O'qituvchilar</x-nav-link>
        @endcan
        @if ($me->can('teachers.view') || $me->can('staff.view'))
            <x-nav-link :href="route('payroll.index')" icon="briefcase" :active="request()->routeIs('payroll.*')">Ish haqi</x-nav-link>
        @endif
        @if ($me->isSuperAdmin())
            <x-nav-link :href="route('branches.index')" icon="building" :active="request()->routeIs('branches.*')">Filiallar</x-nav-link>
            <x-nav-link :href="route('system-status.index')" icon="cog" :active="request()->routeIs('system-status.*')">Tizim holati</x-nav-link>
            <x-nav-link :href="route('notifications.index')" icon="bell" :active="request()->routeIs('notifications.*')">Bildirishnomalar</x-nav-link>
            <x-nav-link :href="route('app-version.edit')" icon="phone" :active="request()->routeIs('app-version.*')">Ilova versiyasi</x-nav-link>            
        @endif
        @can('audit.view')
            <x-nav-link :href="route('audit.index')" icon="log" :active="request()->routeIs('audit.*')">Harakatlar jurnali</x-nav-link>
            <x-nav-link href="{{ route('docs.index') }}" icon="book" target="_blank">API hujjati</x-nav-link>
        @endcan
    </nav>
    <div class="border-t border-ink-100 p-3 dark:border-ink-800">
        <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 rounded-xl p-2 hover:bg-ink-50 dark:hover:bg-ink-800">
            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-brand-100 text-sm font-bold text-brand-700 dark:bg-brand-950 dark:text-brand-300">{{ mb_strtoupper(mb_substr($me->name, 0, 1)) }}</span>
            <span class="min-w-0 flex-1 leading-tight">
                <span class="block truncate text-sm font-semibold text-ink-900 dark:text-white">{{ $me->name }}</span>
                <span class="block text-xs text-ink-500">{{ $me->role->label() }}</span>
            </span>
        </a>
    </div>
</aside>

<div class="lg:pl-72">
    <header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-ink-200/70 bg-white/85 px-4 backdrop-blur dark:border-ink-800 dark:bg-ink-900/85 sm:px-6">
        <button class="btn-ghost -ml-2 p-2 lg:hidden" @click="sidebar = true" aria-label="Menyu"><x-icon name="menu" /></button>

        <div class="flex-1"></div>

        @if ($me->isSuperAdmin())
            <form method="POST" action="{{ route('branches.switch') }}" class="flex items-center gap-2">
                @csrf
                <x-icon name="building" class="hidden h-5 w-5 text-ink-400 sm:block" />
                <select name="branch_id" onchange="this.form.submit()" class="input w-44 py-2 sm:w-56" aria-label="Filial">
                    <option value="">Barcha filiallar</option>
                    @foreach ($branches as $b)
                        <option value="{{ $b->id }}" @selected($selectedBranch === $b->id)>{{ $b->name }}@if (! $b->isActive()) (arxiv)@endif</option>
                    @endforeach
                </select>
            </form>
        @else
            <span class="badge-red hidden sm:inline-flex"><x-icon name="building" class="h-3.5 w-3.5" /> {{ $me->branch?->name }}</span>
        @endif

        <button class="btn-ghost p-2" @click="$store.theme.toggle()" aria-label="Rejimni almashtirish">
            <x-icon name="moon" x-show="!$store.theme.dark" />
            <x-icon name="sun" x-show="$store.theme.dark" x-cloak />
        </button>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="btn-ghost p-2" title="Chiqish" aria-label="Chiqish"><x-icon name="logout" /></button>
        </form>
    </header>

    <main class="mx-auto max-w-7xl p-4 sm:p-6 lg:p-8">
        <x-alert />
        @yield('content')
    </main>
</div>
@stack('scripts')
</body>
</html>
