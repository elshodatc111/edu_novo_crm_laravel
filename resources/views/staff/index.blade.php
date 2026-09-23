@extends('layouts.app')
@section('title', 'Hodimlar')

@section('content')
    <x-page-header title="Hodimlar" subtitle="Adminlar va menejerlar">
        <x-slot:actions>
            @if ($canCreate)
                <a href="{{ route('staff.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4" /> Hodim qo'shish</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="card overflow-hidden">
        <form method="GET" class="flex flex-col gap-3 border-b border-ink-100 p-4 dark:border-ink-800 sm:flex-row">
            <div class="relative flex-1">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-400" />
                <input name="q" value="{{ \App\Support\SafeInput::string(request('q')) }}" placeholder="Ism, login yoki telefon bo'yicha qidirish" class="input pl-9">
            </div>
            <select name="role" class="input sm:w-44" onchange="this.form.submit()">
                <option value="">Barcha lavozimlar</option>
                @foreach ($roles as $role)
                    <option value="{{ $role->value }}" @selected(request('role') === $role->value)>{{ $role->label() }}</option>
                @endforeach
            </select>
            <select name="status" class="input sm:w-40" onchange="this.form.submit()">
                <option value="">Barcha holatlar</option>
                @foreach (\App\Enums\UserStatus::cases() as $s)
                    <option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->label() }}</option>
                @endforeach
            </select>
            <button class="btn-secondary">Qidirish</button>
        </form>

        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr><th>F.I.O</th><th>Lavozim</th>@if (auth()->user()->isSuperAdmin())<th>Filial</th>@endif<th>Telefon</th><th>Holat</th><th class="text-right">Amallar</th></tr>
                </thead>
                <tbody>
                @forelse ($users as $u)
                    <tr>
                        <td>
                            <div class="font-medium text-ink-900 dark:text-white">{{ $u->name }}</div>
                            <div class="text-xs text-ink-400">{{ $u->username }}</div>
                        </td>
                        <td><span class="{{ $u->role->badgeClass() }}">{{ $u->role->label() }}</span></td>
                        @if (auth()->user()->isSuperAdmin())<td>{{ $u->branch?->name ?? '—' }}</td>@endif
                        <td>{{ $u->phone ?? '—' }}</td>
                        <td><span class="{{ $u->isActive() ? 'badge-green' : 'badge-gray' }}">{{ $u->status->label() }}</span></td>
                        <td>
                            <div class="flex justify-end gap-1">
                                @can('assignPermissions', $u)
                                    <a href="{{ route('staff.permissions.edit', $u) }}" class="btn-ghost btn-sm" title="Ruxsatlar"><x-icon name="key" class="h-4 w-4" /> Ruxsatlar</a>
                                @endcan
                                @can('manage', $u)
                                    <a href="{{ route('staff.edit', $u) }}" class="btn-ghost btn-sm" title="Tahrirlash"><x-icon name="edit" class="h-4 w-4" /></a>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-12 text-center text-ink-500">Hodim topilmadi.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $users->links() }}
    </div>
@endsection
