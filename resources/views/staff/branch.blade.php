@extends('layouts.app')
@section('title', $branch->name.' — faqat ko\'rish')

@section('content')
    <x-page-header :title="$branch->name" subtitle="Boshqa filial ma'lumotlari — FAQAT KO'RISH, hech narsa tahrirlanmaydi" />

    <div class="mb-6 flex flex-wrap items-center gap-2">
        <a href="{{ route('staff.directory') }}" class="btn-ghost btn-sm">&larr; Xodimlar ro'yxatiga</a>
        <span class="mx-1 text-ink-300">|</span>
        <span class="text-xs font-semibold uppercase text-ink-400">Filial:</span>
        @foreach ($branches as $b)
            <a href="{{ route('staff.directory.branch', $b) }}"
               class="{{ $b->id === $branch->id ? 'badge-blue' : 'badge-gray' }}">{{ $b->name }}</a>
        @endforeach
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card overflow-hidden lg:col-span-1">
            <div class="border-b border-ink-100 bg-ink-50 px-4 py-2.5 text-sm font-semibold text-ink-700 dark:border-ink-800 dark:bg-ink-800/40 dark:text-ink-200">
                O'quvchilar <span class="text-ink-400">({{ $students->total() }})</span>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>F.I.O</th><th>Telefon</th><th class="text-right">Balans</th></tr></thead>
                    <tbody>
                    @forelse ($students as $s)
                        <tr>
                            <td class="font-medium text-ink-900 dark:text-white">{{ $s->name }}</td>
                            <td>{{ $s->phone ? \App\Support\Format::prettyPhone($s->phone) : '—' }}</td>
                            <td class="text-right font-semibold {{ $s->balance < 0 ? 'text-brand-600' : 'text-emerald-600' }}">{{ \App\Support\Format::money($s->balance) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-6 text-center text-ink-500">O'quvchi yo'q.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-ink-100 p-3 dark:border-ink-800">{{ $students->links() }}</div>
        </div>

        <div class="card overflow-hidden lg:col-span-1">
            <div class="border-b border-ink-100 bg-ink-50 px-4 py-2.5 text-sm font-semibold text-ink-700 dark:border-ink-800 dark:bg-ink-800/40 dark:text-ink-200">
                Guruhlar <span class="text-ink-400">({{ $groups->total() }})</span>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Nomi</th><th>Kurs</th><th>Holati</th></tr></thead>
                    <tbody>
                    @forelse ($groups as $g)
                        <tr>
                            <td class="font-medium text-ink-900 dark:text-white">{{ $g->name }}</td>
                            <td>{{ $g->course?->name ?? '—' }}</td>
                            <td><span class="badge-gray">{{ $g->status_label }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-6 text-center text-ink-500">Guruh yo'q.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-ink-100 p-3 dark:border-ink-800">{{ $groups->links() }}</div>
        </div>

        <div class="card overflow-hidden lg:col-span-1">
            <div class="border-b border-ink-100 bg-ink-50 px-4 py-2.5 text-sm font-semibold text-ink-700 dark:border-ink-800 dark:bg-ink-800/40 dark:text-ink-200">
                Lidlar <span class="text-ink-400">({{ $leads->total() }})</span>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Ism</th><th>Telefon</th><th>Holati</th></tr></thead>
                    <tbody>
                    @forelse ($leads as $l)
                        <tr>
                            <td class="font-medium text-ink-900 dark:text-white">{{ $l->name }}</td>
                            <td>{{ \App\Support\Format::prettyPhone($l->phone) }}</td>
                            <td><span class="badge-gray">{{ $l->status_label }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-6 text-center text-ink-500">Lid yo'q.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-ink-100 p-3 dark:border-ink-800">{{ $leads->links() }}</div>
        </div>
    </div>
@endsection
