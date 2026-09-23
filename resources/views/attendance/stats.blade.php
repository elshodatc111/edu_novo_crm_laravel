@extends('layouts.app')
@section('title', 'Davomad statistikasi')

@section('content')
    <x-page-header title="Davomad statistikasi" subtitle="Kunlik va oylik tahlil" />

    <form method="GET" class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end">
        <div><label class="label">Kun</label><input type="date" name="date" value="{{ $date }}" class="input" onchange="this.form.submit()"></div>
        <div><label class="label">Oy</label><input type="month" name="month" value="{{ $month }}" class="input" onchange="this.form.submit()"></div>
    </form>

    <h2 class="mb-3 text-lg font-semibold text-ink-900 dark:text-white">Kunlik: {{ \Carbon\Carbon::parse($date)->format('d.m.Y') }}</h2>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([['Dars bo\'lgan guruhlar', $daily['totals']['groups']], ['Davomad olingan', $daily['totals']['taken'].' / '.$daily['totals']['groups']], ['Keldi', $daily['totals']['present']], ['Kelmadi', $daily['totals']['absent']]] as [$l, $v])
            <div class="card card-body"><div class="text-sm text-ink-500">{{ $l }}</div><div class="mt-1 text-2xl font-bold text-ink-900 dark:text-white">{{ $v }}</div></div>
        @endforeach
    </div>
    <div class="mt-4 grid gap-6 lg:grid-cols-3">
        <x-dash.card title="Kelganlar va kelmaganlar" subtitle="Shu kunda davomad olingan o'quvchilar" :spec="$charts['day_split']" height="h-64" />
        <x-dash.card class="lg:col-span-2" title="Guruhlar bo'yicha davomad" subtitle="Shu kundagi kelganlar foizi (davomad olingan guruhlar)" :spec="$charts['day_groups']" :height="$charts['day_groups_h']" />
    </div>
    <div class="card mt-4 overflow-hidden">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Guruh</th><th>O'qituvchi</th><th>Holat</th><th>Keldi</th><th>Kelmadi</th><th>Foiz</th></tr></thead>
                <tbody>
                @forelse ($daily['rows'] as $r)
                    <tr>
                        <td><a href="{{ route('groups.show', $r['group']) }}" class="font-medium hover:text-brand-600">{{ $r['group']->name }}</a></td>
                        <td>{{ $r['group']->teacher->name }}</td>
                        <td><span class="{{ $r['taken'] ? 'badge-green' : 'badge-amber' }}">{{ $r['taken'] ? 'Olingan' : 'Olinmagan' }}</span></td>
                        <td>{{ $r['present'] }}</td><td>{{ $r['absent'] }}</td>
                        <td>{{ $r['rate'] === null ? '—' : $r['rate'].'%' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-ink-500">Bu kunda dars bo'lgan guruh yo'q.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <h2 class="mb-3 mt-10 text-lg font-semibold text-ink-900 dark:text-white">Oylik: {{ $month }}</h2>
    <div class="grid gap-4 sm:grid-cols-3">
        @foreach ([['Guruhlar', $monthly['totals']['groups']], ['Umumiy davomad', $monthly['totals']['rate'] === null ? '—' : $monthly['totals']['rate'].'%'], ['Kelmagan (jami)', $monthly['totals']['absent']]] as [$l, $v])
            <div class="card card-body"><div class="text-sm text-ink-500">{{ $l }}</div><div class="mt-1 text-2xl font-bold text-ink-900 dark:text-white">{{ $v }}</div></div>
        @endforeach
    </div>

    @if ($monthly['series'])
        <div class="mt-4 grid gap-6 lg:grid-cols-2">
            <x-dash.card title="Kunlar bo'yicha davomad foizi" subtitle="Har bir dars kunida kelganlar ulushi" :spec="$charts['month_rate']" height="h-64" />
            <x-dash.card title="Kelganlar va kelmaganlar" subtitle="Kunlar bo'yicha o'quvchilar soni" :spec="$charts['month_split']" height="h-64" />
        </div>
    @else
        <div class="card card-body mt-4 text-sm text-ink-500">Bu oyda davomad yozuvlari yo'q.</div>
    @endif

    <div class="mt-4 grid gap-6 lg:grid-cols-2">
        <x-dash.card title="Guruhlar bo'yicha davomad" subtitle="Oy davomida kelganlar foizi (yuqoridan pastga: yaxshidan yomonga)" :spec="$charts['month_groups']" :height="$charts['month_groups_h']" />
        <x-dash.card title="Davomad olinganmi?" subtitle="Rejalangan dars kunlari va davomad olingan kunlar. Farq bo'lsa, davomad olinmagan kunlar bor." :spec="$charts['held']" :height="$charts['held_h']" />
    </div>
    <div class="mt-4">
        <x-dash.card title="Eng ko'p qoldirayotgan o'quvchilar" subtitle="Kamida 3 ta yozuvi bor o'quvchilar; foiz — kelganlar ulushi (past bo'lsa, xavf yuqori)" :spec="$charts['worst']" :height="$charts['worst_h']" />
    </div>

    <h3 class="mb-2 mt-8 text-base font-semibold text-ink-900 dark:text-white">Guruhlar bo'yicha batafsil</h3>
    <div class="mt-2 grid gap-6">
        <div class="card overflow-hidden">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Guruh</th><th>Rejalangan kunlar</th><th>Davomad olingan</th><th>Keldi</th><th>Kelmadi</th><th>Foiz</th></tr></thead>
                    <tbody>
                    @forelse ($monthly['rows'] as $r)
                        <tr>
                            <td><a href="{{ route('groups.show', $r['group']) }}" class="font-medium hover:text-brand-600">{{ $r['group']->name }}</a></td>
                            <td>{{ $r['scheduled'] }}</td>
                            <td class="{{ $r['held'] < $r['scheduled'] ? 'text-amber-600 font-semibold' : '' }}">{{ $r['held'] }}</td>
                            <td>{{ $r['present'] }}</td><td>{{ $r['absent'] }}</td>
                            <td>{{ $r['rate'] === null ? '—' : $r['rate'].'%' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-8 text-center text-ink-500">Bu oyda guruh yo'q.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@push('scripts')@vite('resources/js/charts.js')@endpush
@endsection
