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
    // v13: qo'shimcha filialli operator uchun ham filial tanlagich (faqat o'ziga ruxsat etilgan filiallar)
    $multiBranch = ! $me->isSuperAdmin() && $me->role === \App\Enums\Role::Operator && \App\Support\BranchContext::extraBranchIds($me) !== [];
    if ($multiBranch) {
        $branches = \App\Models\Branch::whereIn('id', \App\Support\BranchContext::accessibleBranchIds($me))->orderBy('name')->get(['id', 'name', 'status']);
    }
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
        @include('partials.sidebar-nav', ['me' => $me])
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

        @if ($me->isSuperAdmin() || $multiBranch)
            <form method="POST" action="{{ route('branches.switch') }}" class="flex items-center gap-2">
                @csrf
                <x-icon name="building" class="hidden h-5 w-5 text-ink-400 sm:block" />
                <select name="branch_id" onchange="this.form.submit()" class="input w-44 py-2 sm:w-56" aria-label="Filial">
                    @if ($me->isSuperAdmin())<option value="">Barcha filiallar</option>@endif
                    @foreach ($branches as $b)
                        <option value="{{ $b->id }}" @selected($selectedBranch === $b->id)>{{ $b->name }}@if (! $b->isActive()) (arxiv)@endif</option>
                    @endforeach
                </select>
            </form>
        @else
            <span class="badge-red hidden sm:inline-flex"><x-icon name="building" class="h-3.5 w-3.5" /> {{ $me->branch?->name }}</span>
        @endif

        @if ($me->can('students.notes'))
            <x-notes-bell :count="\App\Models\StudentNote::active()->count()" />
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
