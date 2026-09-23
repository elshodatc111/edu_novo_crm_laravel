@extends('layouts.app')
@section('title', 'Ish haqi')

@section('content')
    <x-page-header title="Ish haqi" subtitle="O'qituvchi va hodimlarga to'lovlar" />

    <div class="mb-6 flex w-fit gap-1 rounded-xl bg-ink-100 p-1 dark:bg-ink-800">
        @if ($canTeachers)<a href="{{ route('payroll.index', ['tab' => 'teachers']) }}" class="rounded-lg px-4 py-2 text-sm font-medium {{ $tab === 'teachers' ? 'bg-white text-brand-700 shadow-sm dark:bg-ink-900' : 'text-ink-600 dark:text-ink-300' }}">O'qituvchilar</a>@endif
        @if ($canStaff)<a href="{{ route('payroll.index', ['tab' => 'staff']) }}" class="rounded-lg px-4 py-2 text-sm font-medium {{ $tab === 'staff' ? 'bg-white text-brand-700 shadow-sm dark:bg-ink-900' : 'text-ink-600 dark:text-ink-300' }}">Hodimlar</a>@endif
    </div>

    <div class="card overflow-hidden">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>F.I.O</th><th>Lavozim</th>@if ($tab === 'teachers')<th class="text-right">To'lanmagan qoldiq</th>@endif<th class="text-right">Shu oy to'landi</th><th></th></tr></thead>
                <tbody>
                @forelse ($rows as $r)
                    <tr>
                        <td class="font-medium text-ink-900 dark:text-white">{{ $r['user']->name }}</td>
                        <td><span class="{{ $r['user']->role->badgeClass() }}">{{ $r['user']->role->label() }}</span></td>
                        @if ($tab === 'teachers')<td class="text-right font-semibold {{ $r['remaining'] > 0 ? 'text-brand-600' : '' }}">{{ \App\Support\Format::money($r['remaining']) }}</td>@endif
                        <td class="text-right">{{ \App\Support\Format::money($r['paid_month']) }}</td>
                        <td class="text-right"><a href="{{ route('payroll.show', $r['user']) }}" class="btn-secondary btn-sm">Ochish</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-10 text-center text-ink-500">Ro'yxat bo'sh.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
