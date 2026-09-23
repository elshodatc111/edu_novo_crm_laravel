@extends('layouts.app')
@section('title', 'Harakatlar jurnali')

@section('content')
    <x-page-header title="Harakatlar jurnali" subtitle="Kim, qachon, nimani o'zgartirgani">
        <x-slot:actions>
            @if ($scopeBranch)
                <span class="badge-blue">Filial: {{ $scopeBranch->name }}</span>
            @elseif ($allMode)
                <span class="badge-gray">Barcha filiallar</span>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="card overflow-hidden">
        <form method="GET" class="grid gap-3 border-b border-ink-100 p-4 dark:border-ink-800 sm:grid-cols-2 {{ $allMode ? 'lg:grid-cols-6' : 'lg:grid-cols-5' }}">
            @if ($allMode)
                <select name="branch" class="input" aria-label="Filial" onchange="this.form.submit()">
                    <option value="">Barcha filiallar</option>
                    @foreach ($branches as $b)<option value="{{ $b->id }}" @selected($branchFilter === (string) $b->id)>{{ $b->name }}</option>@endforeach
                    <option value="none" @selected($branchFilter === 'none')>Filialsiz (tizim)</option>
                </select>
            @endif
            <input name="user" value="{{ \App\Support\SafeInput::string(request('user')) }}" placeholder="Foydalanuvchi" class="input">
            <input name="action" value="{{ \App\Support\SafeInput::string(request('action')) }}" placeholder="Harakat (masalan: auth, branch)" class="input">
            <input type="date" name="from" value="{{ \App\Support\SafeInput::date(request('from')) }}" class="input">
            <input type="date" name="to" value="{{ \App\Support\SafeInput::date(request('to')) }}" class="input">
            <button class="btn-secondary">Saralash</button>
        </form>

        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Vaqt</th><th>Foydalanuvchi</th>@if ($allMode)<th>Filial</th>@endif<th>Harakat</th><th>Izoh</th><th>IP</th></tr></thead>
                <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td class="whitespace-nowrap">{{ $log->created_at->format('d.m.Y H:i') }}</td>
                        <td>{{ $log->user?->name ?? '—' }}</td>
                        @if ($allMode)<td>{{ $log->branch?->name ?? '—' }}</td>@endif
                        <td><span class="badge-gray">{{ $log->action }}</span></td>
                        <td>{{ $log->description }}</td>
                        <td class="text-xs text-ink-400">{{ $log->ip_address }}</td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $allMode ? 6 : 5 }}" class="py-12 text-center text-ink-500">Yozuv topilmadi.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $logs->links() }}
    </div>
@endsection
