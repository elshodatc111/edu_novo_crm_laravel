@extends('layouts.app')
@section('title', 'Bosh sahifa')

@section('content')
    <x-page-header :title="'Salom, '.auth()->user()->name" :subtitle="$currentBranch ? $currentBranch->name.' filiali' : (auth()->user()->isSuperAdmin() ? 'Barcha filiallar bo\'yicha umumiy ko\'rinish' : '')" />

    @if (auth()->user()->needsPasswordReminder())
        <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
            Parolingiz {{ auth()->user()->password_changed_at->diffInDays(now()) }} kundan beri o'zgarmagan. Xavfsizlik uchun vaqti-vaqti bilan yangilab turishni tavsiya qilamiz -
            <a href="{{ route('profile.edit') }}" class="font-semibold underline">profil sahifasida</a> o'zgartirishingiz mumkin.
        </div>
    @endif

    @if ($todo)
        <div class="mb-6 grid gap-4 lg:grid-cols-2">
            @foreach ($todo as $group)
                <div class="card card-body">
                    <div class="mb-3 flex items-center gap-2">
                        <x-icon :name="$group['icon']" class="h-5 w-5 text-brand-600" />
                        <h3 class="text-sm font-semibold text-ink-900 dark:text-white">{{ $group['title'] }}</h3>
                        <span class="badge-gray ml-auto">{{ count($group['items']) }}</span>
                    </div>
                    <div class="space-y-2 {{ in_array($group['key'], ['debtors', 'leads'], true) ? 'max-h-72 overflow-y-auto pr-1' : '' }}">
                        @foreach ($group['items'] as $item)
                            <div class="flex items-center justify-between gap-3 rounded-lg bg-ink-50 px-3 py-2 text-sm dark:bg-ink-800/60">
                                <a href="{{ $item['url'] }}" class="min-w-0 flex-1">
                                    <div class="truncate font-medium text-ink-900 dark:text-white">{{ $item['label'] }}</div>
                                    @if ($item['meta'])<div class="truncate text-xs text-ink-500">{{ $item['meta'] }}</div>@endif
                                </a>
                                <form method="POST" action="{{ route('tasks.dismiss') }}">
                                    @csrf
                                    <input type="hidden" name="task_key" value="{{ $item['key'] }}">
                                    <button class="btn-ghost btn-sm shrink-0" title="Bajardim (bugunga yashirish)">
                                        <x-icon name="check" class="h-4 w-4" />
                                    </button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @if (! empty($weeklyActivity))
        <div class="card card-body mb-6">
            <h3 class="mb-3 text-sm font-semibold text-ink-900 dark:text-white">Xodimlar faolligi (so'nggi 7 kun, bajarilgan vazifalar)</h3>
            <div class="flex flex-wrap gap-2">
                @foreach ($weeklyActivity as $row)
                    <span class="badge-gray">{{ $row['name'] }}: {{ $row['count'] }}</span>
                @endforeach
            </div>
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @php
            $cards = [
                ['Adminlar', $staffCounts['admin'] ?? 0, 'shield'],
                ['Menejerlar', $staffCounts['manager'] ?? 0, 'users'],
                ["O'qituvchilar", $staffCounts['teacher'] ?? 0, 'user'],
                ['Operatorlar', $staffCounts['operator'] ?? 0, 'phone'],
            ];
            if (auth()->user()->isSuperAdmin()) {
                $cards[] = ['Faol filiallar', $branches->where('status', \App\Enums\BranchStatus::Active)->count(), 'building'];
            }
        @endphp
        @foreach ($cards as [$label, $value, $icon])
            <div class="card card-body flex items-center gap-4">
                <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-950 dark:text-brand-300"><x-icon :name="$icon" class="h-6 w-6" /></span>
                <div>
                    <div class="text-2xl font-bold text-ink-900 dark:text-white">{{ number_format($value, 0, '', ' ') }}</div>
                    <div class="text-sm text-ink-500 dark:text-ink-400">{{ $label }}</div>
                </div>
            </div>
        @endforeach
    </div>

    @if ($money)
        <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([["Bugungi tushum", $money['income'], 'text-emerald-600'], ['Bugun qaytarilgan', $money['refunds'], 'text-brand-600'], ['Qarzdorlar soni', $money['debtors'], 'text-ink-900 dark:text-white'], ['Umumiy qarz', $money['debt'], 'text-brand-600']] as [$l, $v, $c])
                <div class="card card-body"><div class="text-sm text-ink-500">{{ $l }}</div><div class="mt-1 text-xl font-bold {{ $c }}">{{ $l === 'Qarzdorlar soni' ? $v : \App\Support\Format::money($v) }}</div></div>
            @endforeach
        </div>
    @endif

    @if ($groupStats)
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div class="card card-body flex items-center gap-4">
                <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-300"><x-icon name="users" class="h-6 w-6" /></span>
                <div>
                    <div class="text-2xl font-bold text-ink-900 dark:text-white">{{ number_format($groupStats['active']['groups'], 0, '', ' ') }}</div>
                    <div class="text-sm text-ink-500 dark:text-ink-400">Faol guruhlar <span class="text-ink-400">&middot; {{ number_format($groupStats['active']['students'], 0, '', ' ') }} o'quvchi</span></div>
                </div>
            </div>
            <div class="card card-body flex items-center gap-4">
                <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-950 dark:text-amber-300"><x-icon name="calendar" class="h-6 w-6" /></span>
                <div>
                    <div class="text-2xl font-bold text-ink-900 dark:text-white">{{ number_format($groupStats['upcoming']['groups'], 0, '', ' ') }}</div>
                    <div class="text-sm text-ink-500 dark:text-ink-400">Boshlanishi kutilayotgan guruhlar <span class="text-ink-400">&middot; {{ number_format($groupStats['upcoming']['students'], 0, '', ' ') }} o'quvchi</span></div>
                </div>
            </div>
        </div>
    @endif

    @if ($calendar)
        @include('dashboard._calendar')
    @else
        <div class="card card-body mt-8 flex items-center gap-4 text-sm text-ink-500">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-950 dark:text-brand-300"><x-icon name="building" class="h-5 w-5" /></span>
            <p><b class="text-ink-800 dark:text-ink-100">Dars kalendari.</b> Kalendarni ko'rish uchun yuqoridagi ro'yxatdan <b>filialni tanlang</b>: shu filialda qaysi kuni, qaysi vaqtda, qaysi xonada qaysi guruhning darsi borligi ko'rinadi.</p>
        </div>
    @endif

    @if (auth()->user()->isSuperAdmin())
        <div class="card mt-8 overflow-hidden">
            <div class="flex items-center justify-between px-5 pt-5 sm:px-6">
                <h2 class="text-lg font-semibold text-ink-900 dark:text-white">Filiallar</h2>
                <a href="{{ route('branches.index') }}" class="btn-ghost btn-sm">Barchasi</a>
            </div>
            <div class="table-wrap mt-3">
                <table class="table">
                    <thead><tr><th>Filial</th><th>Hodimlar</th><th>O'quvchilar</th><th>Holat</th></tr></thead>
                    <tbody>
                    @forelse ($branches as $b)
                        <tr>
                            <td class="font-medium text-ink-900 dark:text-white">{{ $b->name }}</td>
                            <td>{{ $b->staff_count }}</td>
                            <td>{{ $b->students_count }}</td>
                            <td><span class="{{ $b->isActive() ? 'badge-green' : 'badge-gray' }}">{{ $b->status->label() }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-10 text-center text-ink-500">Hali filial ochilmagan. <a class="font-semibold text-brand-600" href="{{ route('branches.create') }}">Birinchi filialni oching</a>.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
