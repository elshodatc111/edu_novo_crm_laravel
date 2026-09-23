@extends('layouts.app')
@section('title', "O'quvchilar")

@section('content')
    <x-page-header title="O'quvchilar" subtitle="Ro'yxat, qarzdorlar va arxiv">
        <x-slot:actions>
            @can('students.import')<a href="{{ route('imports.index') }}" class="btn-secondary">Excel import</a>@endcan
            @can('students.create')
                <a href="{{ route('students.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4" /> O'quvchi qo'shish</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="card overflow-hidden">
        <form method="GET" class="grid gap-3 border-b border-ink-100 p-4 dark:border-ink-800 md:grid-cols-4">
            <div class="relative md:col-span-2">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-400" />
                <input name="q" value="{{ \App\Support\SafeInput::string(request('q')) }}" placeholder="Ism, telefon yoki login" class="input pl-9">
            </div>
            <select name="status" class="input" onchange="this.form.submit()">
                <option value="active" @selected(request('status', 'active') === 'active')>Faol o'quvchilar</option>
                <option value="debt" @selected(request('status') === 'debt')>Qarzdorlar</option>
                <option value="archived" @selected(request('status') === 'archived')>Arxiv</option>
            </select>
            <select name="group_id" class="input" onchange="this.form.submit()">
                <option value="">Barcha guruhlar</option>
                @foreach ($groups as $g)<option value="{{ $g->id }}" @selected(request('group_id') == $g->id)>{{ $g->name }}</option>@endforeach
            </select>
        </form>

        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>F.I.O</th>@if (auth()->user()->isSuperAdmin())<th>Filial</th>@endif<th>Telefon</th><th>Guruhlar</th><th class="text-right">Balans</th></tr></thead>
                <tbody>
                @forelse ($students as $s)
                    <tr>
                        <td>
                            <a href="{{ route('students.show', $s) }}" class="font-medium text-ink-900 hover:text-brand-600 dark:text-white">{{ $s->name }}</a>
                            @if ($s->archived_at)<span class="badge-gray ml-1">Arxiv</span>@endif
                        </td>
                        @if (auth()->user()->isSuperAdmin())<td>{{ $s->branch?->name }}</td>@endif
                        <td>{{ $s->phone }}</td>
                        <td>{{ $s->active_groups_count }}</td>
                        <td class="text-right font-semibold {{ $s->balance < 0 ? 'text-brand-600' : 'text-emerald-600' }}">{{ \App\Support\Format::money($s->balance) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-12 text-center text-ink-500">O'quvchi topilmadi.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $students->links() }}
    </div>
@endsection
