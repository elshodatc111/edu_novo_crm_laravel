@extends('layouts.app')
@section('title', 'Guruhlar')

@section('content')
    <x-page-header :title="auth()->user()->role === \App\Enums\Role::Teacher ? 'Guruhlarim' : 'Guruhlar'">
        <x-slot:actions>
            @can('create', \App\Models\Group::class)
                <a href="{{ route('groups.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4" /> Yangi guruh</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="card overflow-hidden">
        <form method="GET" class="grid gap-3 border-b border-ink-100 p-4 dark:border-ink-800 md:grid-cols-4">
            <div class="relative">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-400" />
                <input name="q" value="{{ \App\Support\SafeInput::string(request('q')) }}" placeholder="Guruh nomi" class="input pl-9">
            </div>
            <select name="status" class="input" onchange="this.form.submit()">
                @foreach (['current' => 'Joriy (davom etayotgan va yangi)', 'active' => 'Davom etmoqda', 'new' => 'Boshlanmagan', 'finished' => 'Tugagan', 'all' => 'Barchasi'] as $k => $v)
                    <option value="{{ $k }}" @selected($status === $k)>{{ $v }}</option>
                @endforeach
            </select>
            @if ($teachers->isNotEmpty())
                <select name="teacher_id" class="input" onchange="this.form.submit()">
                    <option value="">Barcha o'qituvchilar</option>
                    @foreach ($teachers as $t)<option value="{{ $t->id }}" @selected(request('teacher_id') == $t->id)>{{ $t->name }}</option>@endforeach
                </select>
            @endif
            <button class="btn-secondary">Qidirish</button>
        </form>

        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Guruh</th><th>Kurs</th><th>O'qituvchi</th><th>Vaqt / Xona</th><th>Muddat</th><th>O'quvchilar</th><th>Holat</th></tr></thead>
                <tbody>
                @forelse ($groups as $g)
                    <tr>
                        <td><a href="{{ route('groups.show', $g) }}" class="font-medium text-ink-900 hover:text-brand-600 dark:text-white">{{ $g->name }}</a></td>
                        <td>{{ $g->course->name }}</td>
                        <td>{{ $g->teacher->name }}</td>
                        <td>{{ $g->lessonTime->label }}<div class="text-xs text-ink-400">{{ $g->room->name }} · {{ $g->schedule->label() }}</div></td>
                        <td class="whitespace-nowrap">{{ $g->starts_on->format('d.m.Y') }}<div class="text-xs text-ink-400">— {{ $g->ends_on->format('d.m.Y') }}</div></td>
                        <td>{{ $g->students_count }}</td>
                        <td><span class="{{ ['new' => 'badge-blue', 'active' => 'badge-green', 'finished' => 'badge-gray'][$g->status] }}">{{ $g->status_label }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-12 text-center text-ink-500">Guruh topilmadi.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $groups->links() }}
    </div>
@endsection
